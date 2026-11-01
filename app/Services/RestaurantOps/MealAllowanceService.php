<?php

namespace App\Services\RestaurantOps;

use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\Catalog\InventoryService;
use App\Services\RestaurantOps\KitchenDispatchService;
use Illuminate\Support\Collection;
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
                $employeeId = $attendee->employee_id ?? $attendee->employee?->id;
                $userId = $attendee->id;

                $myOrders = $shiftMealOrders->filter(function ($order) use ($attendee, $employeeId, $userId) {
                    $isMatchEmp = $employeeId && (int)$order->customer_id === (int)$employeeId;
                    $isMatchUser = (int)$order->customer_id === (int)$userId;
                    $isMatchName = str_contains($order->customer_name ?? '', $attendee->name);
                    return $isMatchEmp || $isMatchUser || $isMatchName;
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
     * Processa a gravação de lanches em lote com cálculo de excedente e despacho operacional.
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

                $employeeId = $attendee->employee_id ?? $attendee->id;

                // 1. Total consumido no turno
                $alreadySpent = (float) Order::where('cash_shift_id', $activeShift->id)
                    ->where('source', 'team_app')
                    ->whereNotIn('status', ['cancelled'])
                    ->where(function ($q) use ($attendee, $employeeId) {
                        $q->where('customer_id', $employeeId)
                          ->orWhere('customer_id', $attendee->id)
                          ->orWhere('customer_name', 'like', "%{$attendee->name}%");
                    })
                    ->sum('total');

                // 2. Teto do colaborador
                $cap = $globalCap;
                $indivCap = (float) ($attendee->employee?->meal_allowance ?? $attendee->employee?->food_allowance_amount ?? 0);
                if ($settings->allowance_scope === 'individual' && $indivCap > 0) {
                    $cap = $indivCap;
                }

                $remainingCap = max(0, $cap - $alreadySpent);

                // 3. Montagem e precificação dos itens
                $newSlotTotal = 0;
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
                    $itemTotal = $finalUnitPrice * $qty;

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

                $excess = max(0, $newSlotTotal - $remainingCap);

                if ($excess > 0 && !$allowTab) {
                    throw new \DomainException("{$attendee->name} já utilizou R$ " . number_format($alreadySpent, 2, ',', '.') . " de benefício. O novo pedido gera R$ " . number_format($excess, 2, ',', '.') . " de excedente não autorizado.");
                }

                $parts = explode(' ', trim($attendee->name));
                $shortName = count($parts) > 1 ? $parts[0] . ' ' . mb_substr($parts[1], 0, 1) . '.' : $parts[0];

                // 4. Criação da comanda
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
                    'customer_id'            => $employeeId,
                    'subtotal'               => $newSlotTotal,
                    'total'                  => $newSlotTotal,
                    'cash_shift_id'          => $activeShift->id,
                    'employee_id'            => $operator->employee_id ?? null,
                ]);

                // 5. Pagamento interno
                $order->payments()->create([
                    'payment_method_id' => $internalPaymentMethod?->id,
                    'method'            => $internalPaymentMethod?->name ?? 'Consumo Interno',
                    'amount'            => $newSlotTotal,
                    'status'            => 'completed',
                    'is_paid'           => true,
                    'cash_shift_id'     => $activeShift->id,
                    'cashier_id'        => $operator->id ?? null,
                ]);

                // 6. Itens e modificadores
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

                // 7. Débito em folha
                if ($excess > 0 && $allowTab && $attendee->employee) {
                    $latestLedger = $attendee->employee->ledgers()->latest('id')->first();
                    $bal = $latestLedger ? (float) $latestLedger->balance : 0.00;
                    $attendee->employee->ledgers()->create([
                        'type'        => 'debit',
                        'description' => "Excedente Lanche (Turno #{$activeShift->id})",
                        'amount'      => $excess,
                        'balance'     => $bal - $excess,
                    ]);
                }

                // 8. Baixa de estoque
                try {
                    $this->inventoryService->deductOrderStock($order);
                } catch (\Throwable $invErr) {
                    Log::warning("Aviso estoque lanche: " . $invErr->getMessage());
                }

                // 9. Cozinha e WebSockets
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
