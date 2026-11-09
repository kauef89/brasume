<?php

namespace App\Services\RestaurantOps;

use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\Catalog\InventoryService;
use App\Services\RestaurantOps\KitchenDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MealAllowanceService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected KitchenDispatchService $kitchenDispatchService
    ) {}

    /**
     * Retorna a lista de colaboradores ativos no turno com histórico de consumo consolidado.
     */
    public function getAttendeesWithMealStatus(CashShift $activeShift): array
    {
        $shiftMealOrders = Order::with(['items.modifiers'])
            ->where('cash_shift_id', $activeShift->id)
            ->where('source', 'team_app')
            ->whereNotIn('status', ['cancelled'])
            ->get();

        return $activeShift->attendees()
            ->wherePivot('status', 'active')
            ->wherePivot('attendance_status', 'present')
            ->with(['employee'])
            ->get()
            ->map(function ($attendee) use ($shiftMealOrders) {
                $employee = $attendee->employee;
                $employeeId = $employee?->id ?? ($attendee->employee_id ?? $attendee->id);
                $userId = $attendee->id;

                $candidateIds = array_unique(array_filter([
                    $employeeId,
                    $userId,
                    $attendee->employee_id ?? null,
                    $attendee->employee?->id ?? null,
                ]));

                $firstName = explode(' ', trim($attendee->name))[0];

                $myOrders = $shiftMealOrders->filter(function ($order) use ($candidateIds, $attendee, $firstName) {
                    $isMatchEmp = in_array((int)$order->employee_id, $candidateIds, true);
                    $isMatchCust = in_array((int)$order->customer_id, $candidateIds, true);
                    $isMatchName = str_contains($order->customer_name ?? '', $attendee->name)
                        || str_contains($order->customer_name ?? '', "Lanche: {$firstName}");

                    return $isMatchEmp || $isMatchCust || $isMatchName;
                });

                $consumedItems = $myOrders->flatMap(function ($order) {
                    return $order->items->map(function ($item) {
                        $modsText = $item->modifiers
                            ->map(fn($m) => ($m->quantity > 1 ? $m->quantity . 'x ' : '') . $m->name)
                            ->implode(', ');

                        return [
                            'id'            => $item->id,
                            'product_id'    => $item->product_id,
                            'name'          => $item->product_name,
                            'qty'           => (float)$item->quantity,
                            'price'         => (float)$item->unit_price,
                            'totalPrice'    => (float)$item->total_price,
                            'modifiersText' => $modsText,
                            'is_takeaway'   => (bool)$item->is_takeaway,
                            'already_sent'  => true,
                            'order_id'      => $item->order_id,
                        ];
                    });
                })->values()->toArray();

                $consumedTotal = (float) $myOrders->sum('total');

                return [
                    'id'               => $attendee->id,
                    'user_id'          => $attendee->id,
                    'name'             => $attendee->name,
                    'role'             => $attendee->role ?? ($attendee->employee?->role ?? 'staff'),
                    'employee_id'      => $employeeId,
                    'avatar'           => $attendee->avatar,
                    'daily_rate'       => (float) ($attendee->pivot->daily_rate ?? 0),
                    'custom_allowance' => (float) ($attendee->employee?->meal_allowance ?? $attendee->employee?->food_allowance_amount ?? 0),
                    'consumed_total'   => $consumedTotal,
                    'consumed_items'   => $consumedItems,
                ];
            })->values()->toArray();
    }

    /**
     * Processa a gravação de lanches em lote com cálculo cumulativo de benefício e despacho.
     */
    public function processBatch(array $slots, CashShift $activeShift, $operator, CompanySetting $settings): void
    {
        $attendees = $activeShift->attendees()
            ->wherePivot('status', 'active')
            ->wherePivot('attendance_status', 'present')
            ->with(['employee'])
            ->get();

        $globalCap = (float) ($settings->global_allowance_amount ?? 0);
        $allowTab = (bool) ($settings->allow_employee_tab ?? true);

        $internalPaymentMethod = PaymentMethod::where('engine_type', 'internal')->first()
            ?? PaymentMethod::where('engine_type', 'like', '%internal%')->first();

        DB::transaction(function () use ($slots, $attendees, $activeShift, $operator, $settings, $globalCap, $allowTab, $internalPaymentMethod) {
            foreach ($slots as $slot) {
                $attendee = $attendees->firstWhere('id', $slot['attendee_id']);
                if (!$attendee) continue;

                $employee = $attendee->employee;
                $employeeId = $employee?->id ?? ($attendee->employee_id ?? $attendee->id);
                $userId = $attendee->id;

                $candidateIds = array_unique(array_filter([
                    $employeeId,
                    $userId,
                    $attendee->employee_id ?? null,
                    $attendee->employee?->id ?? null,
                ]));

                $firstName = explode(' ', trim($attendee->name))[0];

                // 1. Busca todos os pedidos anteriores deste colaborador no turno ativo
                $previousOrders = Order::where('cash_shift_id', $activeShift->id)
                    ->where('source', 'team_app')
                    ->whereNotIn('status', ['cancelled'])
                    ->where(function ($q) use ($candidateIds, $attendee, $firstName) {
                        $q->whereIn('employee_id', $candidateIds)
                          ->orWhereIn('customer_id', $candidateIds)
                          ->orWhere('customer_name', 'like', "Lanche: {$attendee->name}%")
                          ->orWhere('customer_name', 'like', "Lanche: {$firstName}%")
                          ->orWhere('customer_name', 'like', "%{$attendee->name}%");
                    })
                    ->get();

                $previousOrdersTotal = (float) $previousOrders->sum('total');

                // 2. Busca quanto já foi debitado como excedente no extrato do colaborador neste turno
                $previousExcessTotal = 0.0;
                if ($employee) {
                    $previousExcessTotal = (float) $employee->ledgers()
                        ->where('created_at', '>=', $activeShift->opened_at)
                        ->where('type', 'debit')
                        ->where(function ($q) use ($activeShift) {
                            $q->where('description', 'like', "%Turno #{$activeShift->id}%")
                              ->orWhere('description', 'like', '%Excedente Lanche%');
                        })
                        ->sum('amount');
                }

                // 3. Teto individual ou global do colaborador
                $cap = $globalCap;
                $indivCap = (float) ($employee?->meal_allowance ?? $employee?->food_allowance_amount ?? 0);
                if ($settings->allowance_scope === 'individual' && $indivCap > 0) {
                    $cap = $indivCap;
                }

                // Saldo restante real do benefício para este turno
                $alreadySpentAllowance = min($cap, max(0.0, round($previousOrdersTotal - $previousExcessTotal, 2)));
                $remainingCap = max(0.0, round($cap - $alreadySpentAllowance, 2));

                // 4. Montagem e precificação dos itens solicitados (qualquer item do cardápio liberado)
                $newSlotTotal = 0.0;
                $itemsToCreate = [];

                foreach ($slot['items'] as $itemData) {
                    $prod = Product::find($itemData['product_id']);
                    if (!$prod) continue;

                    $frontPrice = (float) ($itemData['price'] ?? 0);
                    $dbPrice = (float) ($prod->dine_in_price ?? $prod->delivery_price ?? 0);
                    $basePrice = $frontPrice > 0 ? $frontPrice : $dbPrice;

                    $extraPrice = (float) ($itemData['extra_price'] ?? 0);
                    $finalUnitPrice = $basePrice + $extraPrice;
                    $qty = (float) ($itemData['qty'] ?? 1);
                    $itemTotal = round($finalUnitPrice * $qty, 2);

                    $newSlotTotal += $itemTotal;

                    $itemsToCreate[] = [
                        'product'     => $prod,
                        'unit_price'  => $finalUnitPrice,
                        'total_price' => $itemTotal,
                        'qty'         => $qty,
                        'notes'       => $itemData['notes'] ?? null,
                        'is_takeaway' => !empty($itemData['is_takeaway']),
                        'modifiers'   => $itemData['modifiers'] ?? [],
                    ];
                }

                // 5. O saldo de benefício restante abate a comanda; o excedente vira débito em folha/diária
                $allowanceCovered = min($remainingCap, $newSlotTotal);
                $excess = round($newSlotTotal - $allowanceCovered, 2);

                if ($excess > 0 && !$allowTab) {
                    throw new \DomainException("{$attendee->name} já utilizou R$ " . number_format($alreadySpentAllowance, 2, ',', '.') . " de benefício. O novo pedido gera R$ " . number_format($excess, 2, ',', '.') . " de excedente não autorizado.");
                }

                $parts = explode(' ', trim($attendee->name));
                $shortName = count($parts) > 1 ? $parts[0] . ' ' . mb_substr($parts[1], 0, 1) . '.' : $parts[0];

                // 6. Criação da comanda da equipe
                $order = Order::create([
                    'type'                   => 'counter',
                    'status'                 => 'closed',
                    'closed_at'              => now(),
                    'payment_status'         => 'paid',
                    'payment_intent_status'  => 'paid',
                    'payment_intent_methods' => $internalPaymentMethod ? [[
                        'method_id'   => $internalPaymentMethod->id,
                        'method_name' => $internalPaymentMethod->name,
                        'engine_type' => 'internal',
                        'amount'      => $newSlotTotal,
                    ]] : null,
                    'source'                 => 'team_app',
                    'customer_name'          => 'Lanche: ' . $shortName,
                    'customer_id'            => null,
                    'employee_id'            => $employeeId,
                    'cashier_id'             => $operator->id ?? null,
                    'subtotal'               => $newSlotTotal,
                    'total'                  => $newSlotTotal,
                    'cash_shift_id'          => $activeShift->id,
                ]);

                // 7. Registro do pagamento interno
                $order->payments()->create([
                    'payment_method_id' => $internalPaymentMethod?->id,
                    'method'            => $internalPaymentMethod?->name ?? 'Consumo Interno',
                    'amount'            => $newSlotTotal,
                    'status'            => 'completed',
                    'is_paid'           => true,
                    'cash_shift_id'     => $activeShift->id,
                    'cashier_id'        => $operator->id ?? null,
                ]);

                // 8. Gravação dos itens e adicionais
                foreach ($itemsToCreate as $it) {
                    $orderItem = $order->items()->create([
                        'product_id'        => $it['product']->id,
                        'kitchen_id'        => $it['product']->kitchen_id,
                        'product_name'      => $it['product']->name,
                        'quantity'          => (int) $it['qty'],
                        'paid_qty'          => (float) $it['qty'],
                        'unit_price'        => $it['unit_price'],
                        'total_price'       => $it['total_price'],
                        'notes'             => $it['notes'],
                        'is_takeaway'       => $it['is_takeaway'],
                        'status'            => 'active',
                        'production_status' => 'pending',
                        'user_name'         => $operator->name ?? 'Equipe',
                    ]);

                    if (!empty($it['modifiers']) && is_array($it['modifiers'])) {
                        foreach ($it['modifiers'] as $mod) {
                            $orderItem->modifiers()->create([
                                'name'               => $mod['name'] ?? '',
                                'price'              => (float) ($mod['price'] ?? 0),
                                'quantity'           => (int) ($mod['qty'] ?? 1),
                                'modifier_option_id' => !empty($mod['id']) && is_numeric($mod['id']) ? (int) $mod['id'] : null,
                            ]);
                        }
                    }
                }

                // 9. Lançamento do débito em folha quando houver excedente
                if ($excess > 0 && $allowTab && $employee) {
                    $latestLedger = $employee->ledgers()->latest('id')->first();
                    $bal = $latestLedger ? (float) $latestLedger->balance : 0.00;
                    $employee->ledgers()->create([
                        'order_id'    => $order->id,
                        'type'        => 'debit',
                        'description' => "Lanche Turno #{$activeShift->id} (Pedido #{$order->id})",
                        'amount'      => $excess,
                        'balance'     => $bal - $excess,
                    ]);
                }

                // 10. Baixa de estoque
                try {
                    $this->inventoryService->deductOrderStock($order);
                } catch (\Throwable $invErr) {
                    Log::warning("Aviso estoque lanche: " . $invErr->getMessage());
                }

                // 11. Despacho operacional para cozinha e WebSockets
                try {
                    $this->kitchenDispatchService->dispatchNewItems($order->fresh());
                    broadcast(new \App\Events\OrderUpdated($order->fresh()));
                } catch (\Throwable $printErr) {
                    Log::warning("Aviso impressão lanche: " . $printErr->getMessage());
                }
            }
        });
    }
}
