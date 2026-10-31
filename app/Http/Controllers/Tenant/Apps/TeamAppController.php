<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Product;
use App\Services\Printing\Documents\KitchenTicket;
use App\Services\Printing\PrintDispatcher;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\RestaurantOps\ShiftAttendanceService;
use App\Services\RestaurantOps\OperationsMenuService;
use App\Services\RestaurantOps\EmployeeWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TeamAppController extends Controller
{
    public function index(
        Request $request,
        OperationsMenuService $menuService,
        OperationsDashboardService $opsService,
        ShiftAttendanceService $attendanceService,
        EmployeeWalletService $walletService
    ): Response {
        $user = auth()->user();

        $isDriver = false;
        if ($user) {
            $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [$user->role ?? ''];
            $isDriver = in_array('driver', $userRoles, true) || in_array('motoboy', $userRoles, true);
        }

        $defaultTabFallback = $isDriver ? 'driver' : 'menu';
        $activeTab = $request->route('tab', $defaultTabFallback);

        $activeShift = CashShift::where('status', 'open')->latest('opened_at')->first();

        $data = $menuService->getOrderEntryData($request);
        $data['defaultTab'] = $activeTab;
        $data['isDriver'] = $isDriver;
        $data['isOnDuty'] = fn () => $user ? $attendanceService->checkIsOnDuty($user) : false;
        $data['initialOrders'] = fn () => $opsService->getActiveOrdersDashboard()['initialOrders'];
        $data['managerData'] = fn () => $opsService->getManagerShiftSummary($user);

        // Turno e colaboradores ativos
        $data['currentShift'] = fn () => $activeShift;
        $data['currentAttendees'] = function () use ($activeShift) {
            if (!$activeShift) return [];
            return $activeShift->attendees()
                ->wherePivot('status', 'active')
                ->wherePivot('attendance_status', 'present')
                ->with(['employee'])
                ->get()
                ->map(function ($attendee) {
                    return [
                        'id' => $attendee->id,
                        'user_id' => $attendee->id,
                        'name' => $attendee->name,
                        'role' => $attendee->role ?? ($attendee->employee?->role ?? 'staff'),
                        'employee_id' => $attendee->employee_id ?? $attendee->employee?->id,
                        'avatar' => $attendee->avatar,
                        'daily_rate' => (float) ($attendee->pivot->daily_rate ?? 0),
                        'custom_allowance' => (float) ($attendee->employee?->meal_allowance ?? 0),
                    ];
                })->values()->toArray();
        };

        // Carteira corporativa
        $data['employeeWalletData'] = fn () => ($user && $user->employee_id)
            ? $walletService->getWalletDashboardData($user->employee_id)
            : ['balance' => 0];

        $data['catalog'] = fn () => Product::with(['category', 'storageLocations', 'modifiers.options'])
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        return Inertia::render("Team/TeamApp", $data);
    }

    /**
     * 🥪 LANÇAMENTO DOS LANCHES DA EQUIPA (VALE-ALIMENTAÇÃO)
     */
    public function submitMealAllowance(
        Request $request,
        PrintDispatcher $printDispatcher
    ) {
        $user = auth()->user();
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        if (empty($settings->has_food_allowance)) {
            return back()->withErrors(['error' => 'O benefício de Vale-Alimentação não está ativo nas configurações.']);
        }

        $activeShift = CashShift::where('status', 'open')->latest('opened_at')->first();
        if (!$activeShift) {
            return back()->withErrors(['error' => 'Nenhum turno de caixa aberto no momento.']);
        }

        $attendees = $activeShift->attendees()
            ->wherePivot('status', 'active')
            ->wherePivot('attendance_status', 'present')
            ->with(['employee'])
            ->get();

        // 🛡️ VALIDAÇÃO DE PERMISSÃO HIERÁRQUICA
        $selfService = (bool) ($settings->self_service_enabled ?? false);
        if (!$selfService) {
            $userRole = strtolower($user->role ?? '');
            $hasManagerPresent = $attendees->contains(function ($a) {
                $r = strtolower($a->role ?? '');
                return in_array($r, ['manager', 'gerente', 'admin', 'supervisor']);
            });

            if ($hasManagerPresent) {
                if (!in_array($userRole, ['manager', 'gerente', 'admin', 'supervisor'])) {
                    return back()->withErrors(['error' => 'Apenas o Gerente presente no turno pode autorizar e lançar o lanche da equipa.']);
                }
            } else {
                if (!in_array($userRole, ['cashier', 'caixa', 'manager', 'gerente', 'admin'])) {
                    return back()->withErrors(['error' => 'Acesso restrito ao Caixa ou Gerente.']);
                }
            }
        }

        $payload = $request->validate([
            'slots' => 'required|array|min:1',
            'slots.*.attendee_id' => 'required|integer',
            'slots.*.items' => 'required|array|min:1',
            'slots.*.items.*.product_id' => 'required|integer|exists:products,id',
            'slots.*.items.*.qty' => 'required|numeric|min:1',
            'slots.*.items.*.price' => 'required|numeric|min:0',
            'slots.*.items.*.extra_price' => 'nullable|numeric|min:0',
            'slots.*.items.*.is_takeaway' => 'nullable|boolean',
            'slots.*.items.*.notes' => 'nullable|string|max:255',
            'slots.*.items.*.modifiers' => 'nullable|array',
        ]);

        $globalCap = (float) ($settings->global_allowance_amount ?? 0);
        $allowTab = (bool) ($settings->allow_employee_tab ?? true);

        DB::beginTransaction();
        try {
            foreach ($payload['slots'] as $slot) {
                $attendee = $attendees->firstWhere('id', $slot['attendee_id']);
                if (!$attendee) continue;

                $slotTotal = 0;
                foreach ($slot['items'] as $item) {
                    $itemTotal = ((float) $item['price'] + (float) ($item['extra_price'] ?? 0)) * (int) $item['qty'];
                    $slotTotal += $itemTotal;
                }

                $cap = $globalCap;
                if ($settings->allowance_scope === 'individual' && $attendee->employee?->meal_allowance > 0) {
                    $cap = (float) $attendee->employee->meal_allowance;
                }

                $excess = max(0, $slotTotal - $cap);

                if ($excess > 0 && !$allowTab) {
                    throw new \Exception("O lanche de {$attendee->name} ultrapassou o teto de R$ " . number_format($cap, 2, ',', '.') . " e o desconto em folha não está autorizado.");
                }

                // Nome abreviado (Ex: Carlos Alberto -> Carlos A.)
                $parts = explode(' ', trim($attendee->name));
                $shortName = count($parts) > 1 ? $parts[0] . ' ' . mb_substr($parts[1], 0, 1) . '.' : $parts[0];

                // Cria o pedido finalizado (não vai para o Kanban/KDS)
                $order = Order::create([
                    'type' => 'table',
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'source' => 'team_app',
                    'customer_name' => $attendee->name,
                    'identifier' => 'Lanche: ' . $shortName,
                    'subtotal' => $slotTotal,
                    'total' => $slotTotal,
                    'cash_shift_id' => $activeShift->id,
                    'user_id' => $user->id,
                ]);

                foreach ($slot['items'] as $itemData) {
                    $prod = Product::find($itemData['product_id']);
                    $itemTotal = ((float) $itemData['price'] + (float) ($itemData['extra_price'] ?? 0)) * (int) $itemData['qty'];

                    $orderItem = $order->items()->create([
                        'product_id' => $prod->id,
                        'name' => $prod->name,
                        'quantity' => $itemData['qty'],
                        'unit_price' => $itemData['price'],
                        'extra_price' => $itemData['extra_price'] ?? 0,
                        'total_price' => $itemTotal,
                        'notes' => $itemData['notes'] ?? null,
                        'is_takeaway' => !empty($itemData['is_takeaway']),
                        'status' => 'completed',
                    ]);

                    if (!empty($itemData['modifiers'])) {
                        foreach ($itemData['modifiers'] as $mod) {
                            $orderItem->modifiers()->create([
                                'name' => $mod['name'] ?? '',
                                'price' => $mod['price'] ?? 0,
                                'quantity' => $mod['qty'] ?? 1,
                                'modifier_option_id' => $mod['id'] ?? null,
                            ]);
                        }
                    }
                }

                // Débito do excedente em folha (se houver)
                if ($excess > 0 && $allowTab && $attendee->employee) {
                    $latestLedger = $attendee->employee->ledgers()->latest('id')->first();
                    $bal = $latestLedger ? (float) $latestLedger->balance : 0.00;
                    $attendee->employee->ledgers()->create([
                        'type' => 'debit',
                        'description' => "Excedente Lanche (Turno #{$activeShift->id})",
                        'amount' => $excess,
                        'balance' => $bal - $excess,
                    ]);
                }

                // Baixa imediata de estoque / Kardex
                try {
                    $inventoryService = app(\App\Services\Catalog\InventoryService::class);
                    if ($inventoryService) {
                        $inventoryService->deductOrderStock($order);
                    }
                } catch (\Throwable $invErr) {
                    Log::warning("Aviso estoque lanche: " . $invErr->getMessage());
                }

                // Impressão térmica para a cozinha com identificação do colaborador
                try {
                    $kitchenPrinter = Printer::where('is_kitchen', true)->where('is_active', true)->first();
                    if ($kitchenPrinter) {
                        $ticket = new KitchenTicket($order);
                        $printDispatcher->dispatch($kitchenPrinter, $ticket);
                    }
                } catch (\Throwable $printErr) {
                    Log::warning("Aviso impressão lanche: " . $printErr->getMessage());
                }
            }

            DB::commit();

            return back()->with('success', 'Lanches da equipa registados e enviados para a cozinha!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Falha ao registrar lanche: ' . $e->getMessage()]);
        }
    }
}
