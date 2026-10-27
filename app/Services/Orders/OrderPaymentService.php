<?php

namespace App\Services\Orders;

use App\Enums\PaymentEngineType;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\DiningTable;
use App\Models\Coupon;
use App\Events\OrderUpdated;
use App\Events\OrderCreated;
use App\Services\Marketing\CouponService;
use App\Services\Marketing\LoyaltyService;
use App\Services\RestaurantOps\TablesService;
use App\Services\RestaurantOps\KitchenDispatchService;
use App\Services\RestaurantOps\ShiftAttendanceService;
use App\Notifications\NewB2COrderNotification;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Exception;

class OrderPaymentService
{
    public function __construct(protected LoyaltyService $loyaltyService) {}

    public function applyCheckoutCoupon(Order $order, string $couponCode, ?float $serviceFee = null): void
    {
        $couponService = app(CouponService::class);
        $baseTotal = (float) $order->subtotal + (float) $order->delivery_fee;

        $couponResult = $couponService->applyCoupon(
            $couponCode,
            $baseTotal,
            $order->customer_id,
            true
        );

        if ($couponResult['success']) {
            $coupon = Coupon::find($couponResult['coupon_id']);
            $discountValue = $couponService->calculateDiscountValue(
                $coupon,
                (float) $order->subtotal,
                (float) $order->delivery_fee,
                $serviceFee ?? (float) $order->service_fee
            );

            $order->coupon_id = $coupon->id;
            $order->discount = round($discountValue, 2);
            $order->save();

            $coupon->increment('usage_count');
        }
    }

    public function resolveOrderCompletion(Order $order): void
    {
        $isPosSource = in_array($order->source, [Order::SOURCE_POS_APP, Order::SOURCE_CASHIER], true);

        $shouldClose = ($order->status === 'dispatched' && $order->payment_status === 'paid')
            || ($order->type === 'counter' && $order->payment_status === 'paid' && ($isPosSource || in_array($order->status, ['ready', 'dispatched'])))
            || ($order->type === 'table' && $order->payment_status === 'paid');

        if (!$shouldClose) {
            return;
        }

        $order->status = 'closed';
        $order->closed_at = now();
        $order->save();

        if ($order->type === 'table') {
            $table = $order->diningTable
                ?? ($order->dining_table_id ? DiningTable::find($order->dining_table_id) : null)
                ?? (!empty($order->table_number) ? DiningTable::where('name', $order->table_number)->first() : null);

            if ($table && !$table->hasActiveOrdersInGroup($order->id)) {
                $primaryTable = $table->parent_table_id ? $table->parentTable : $table;
                if ($primaryTable) {
                    app(TablesService::class)->split($primaryTable);
                }
            }
        }

        broadcast(new OrderUpdated($order->fresh()));
    }

    public function processPayment(Order $order, array $data): void
    {
        $cashbackUsed = (float) ($data['cashback_used'] ?? 0);

        DB::transaction(function () use ($order, $data, $cashbackUsed) {
            $shift = CashShift::where('status', 'open')->latest()->first();

            if (!$shift) {
                throw new Exception('Operação negada: Nenhum turno de caixa aberto. Por favor, abra o caixa antes de receber pagamentos.');
            }

            if ($cashbackUsed > 0 && $order->customer) {
                $this->loyaltyService->consumeCashback($order, $order->customer, $cashbackUsed);
            }

            $baseSubAndDelivery = (float) $order->subtotal + (float) $order->delivery_fee;
            $appliedDiscount = min((float) $order->discount, $baseSubAndDelivery);

            $baseTotal = $baseSubAndDelivery - $appliedDiscount;
            $requestedServiceFee = isset($data['service_fee']) ? (float) $data['service_fee'] : (float) $order->service_fee;

            $expectedTotal = max(0, round($baseTotal + $requestedServiceFee, 2));

            $cashPaidThisRun = 0;
            $createdPayments = [];

            foreach ($data['payments'] as $payment) {
                $methodId = $payment['method_id'] ?? null;
                $paymentMethod = PaymentMethod::find($methodId);
                $methodName = $paymentMethod->name ?? 'Outros';

                if ($paymentMethod && ($paymentMethod->engine_type === PaymentEngineType::INTERNAL_LOYALTY || $paymentMethod->engine_type?->value === 'internal_loyalty')) {
                    $alreadyRegistered = $order->payments()->where('payment_method_id', $methodId)->exists();
                    if ($alreadyRegistered) {
                        continue;
                    }
                }

                $isInternalAllowance = $paymentMethod && ($paymentMethod->engine_type === PaymentEngineType::INTERNAL || $paymentMethod->engine_type === PaymentEngineType::ALLOWANCE || in_array($paymentMethod->engine_type?->value, ['internal', 'allowance']));

                if ($isInternalAllowance) {
                    if (!$order->customer) {
                        throw new Exception("Para usar o Consumo Interno, é obrigatório identificar o colaborador no pedido.");
                    }

                    $employee = Employee::where('cpf', preg_replace('/\D/', '', $order->customer->cpf ?? ''))
                        ->orWhere('email', $order->customer->email)
                        ->first();

                    if (!$employee && auth()->user() && auth()->user()->employee_id) {
                        $employee = Employee::find(auth()->user()->employee_id);
                    }

                    if (!$employee) {
                        throw new Exception("O cliente não possui um cadastro de Colaborador válido para usar o Vale-Alimentação.");
                    }

                    $latestLedger = $employee->ledgers()->latest('id')->first();
                    $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;

                    if ($currentBalance < (float) $payment['amount'] - 0.01) {
                        throw new Exception("Saldo de vale-alimentação insuficiente. Saldo atual: R$ " . number_format($currentBalance, 2, ',', '.'));
                    }

                    $employee->ledgers()->create([
                        'type'        => 'debit',
                        'description' => "Consumo Interno - Pedido #{$order->id}",
                        'amount'      => $payment['amount'],
                        'balance'     => $currentBalance - (float) $payment['amount']
                    ]);
                }

                $orderPayment = $order->payments()->create([
                    'payment_method_id' => $methodId,
                    'cash_shift_id'     => $shift->id,
                    'cashier_id'        => auth()->id(),
                    'amount'            => $payment['amount'],
                    'method'            => $methodName
                ]);

                $createdPayments[] = $orderPayment;

                $isCash = ($paymentMethod && ($paymentMethod->engine_type === PaymentEngineType::MONEY || in_array($paymentMethod->engine_type?->value, ['money', 'cash'])))
                    || str_contains(mb_strtolower($methodName), 'dinheiro');

                if ($isCash) {
                    $cashPaidThisRun += (float) $payment['amount'];
                }

                if ($paymentMethod && !in_array($paymentMethod->engine_type?->value ?? $paymentMethod->engine_type, ['internal_loyalty', 'internal', 'allowance', 'loss'])) {
                    Transaction::create([
                        'bank_account_id'  => $shift->bank_account_id,
                        'cash_shift_id'    => $shift->id,
                        'order_id'         => $order->id,
                        'payment_id'       => $orderPayment->id,
                        'payment_method_id'=> $methodId,
                        'type'             => 'in',
                        'category'         => 'order_sale',
                        'amount'           => $payment['amount'],
                        'description'      => "Pedido #{$order->id} ({$methodName})",
                        'operator_name'    => auth()->user()->name ?? 'Sistema',
                        'transaction_date' => now(),
                    ]);
                }
            }

            // Baixa de itens
            if (!empty($data['items'])) {
                foreach ($data['items'] as $pItem) {
                    if (!isset($pItem['id']) || !isset($pItem['pay_qty'])) continue;

                    $payQty = (float) $pItem['pay_qty'];
                    if ($payQty <= 0) continue;

                    $item = OrderItem::where('order_id', $order->id)->find($pItem['id']);
                    if (!$item) continue;

                    $available = max(0, (float) $item->quantity - (float) $item->paid_qty);
                    $toAdd = min($payQty, $available);

                    if ($toAdd > 0) {
                        $item->paid_qty = round((float) $item->paid_qty + $toAdd, 4);
                        $item->save();

                        $itemCoveredAmount = isset($pItem['amount']) && (float) $pItem['amount'] > 0
                            ? (float) $pItem['amount']
                            : round($toAdd * (float) $item->unit_price, 2);

                        if ($itemCoveredAmount <= 0 && count($data['items']) === 1 && !empty($data['payments'])) {
                            $itemCoveredAmount = (float) $data['payments'][0]['amount'];
                        }

                        if (!empty($createdPayments)) {
                            $targetPayment = $createdPayments[0];
                            $targetPayment->items()->attach($item->id, [
                                'quantity' => $toAdd,
                                'amount'   => $itemCoveredAmount
                            ]);
                        }

                        if ($item->parent_item_id) {
                            $parent = OrderItem::find($item->parent_item_id);
                            if ($parent && (float) $parent->total_price > 0) {
                                $proportionalAdvance = ($itemCoveredAmount / (float) $parent->total_price) * (float) $parent->quantity;
                                $parent->paid_qty = min((float) $parent->quantity, round((float) $parent->paid_qty + $proportionalAdvance, 4));
                                $parent->save();
                            }
                        }

                        $subItems = OrderItem::where('parent_item_id', $item->id)->get();
                        if ($subItems->isNotEmpty() && (float) $item->quantity > 0) {
                            $ratio = $toAdd / (float) $item->quantity;
                            foreach ($subItems as $sub) {
                                $subToAdd = (float) $sub->quantity * $ratio;
                                $sub->paid_qty = min((float) $sub->quantity, round((float) $sub->paid_qty + $subToAdd, 4));
                                $sub->save();
                            }
                        }
                    }
                }
            }

            $totalPaid = (float) $order->payments()->sum('amount');
            $excess = round($totalPaid - $expectedTotal, 2);

            $unpaidItemsCount = $order->items()
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) {
                    $q->whereNull('paid_qty')
                      ->orWhereRaw('paid_qty < quantity - 0.001');
                })
                ->count();

            $areAllItemsPaid = ($unpaidItemsCount === 0);

            if ($excess > 0.02 && $cashPaidThisRun > 0) {
                $cashChange = min($cashPaidThisRun, $excess);
                $order->change_returned = round(($order->change_returned ?? 0) + $cashChange, 2);

                Transaction::create([
                    'bank_account_id'  => $shift->bank_account_id,
                    'cash_shift_id'    => $shift->id,
                    'order_id'         => $order->id,
                    'type'             => 'out',
                    'category'         => 'change',
                    'amount'           => $cashChange,
                    'description'      => "Troco - Pedido #{$order->id}",
                    'operator_name'    => auth()->user()->name ?? 'Sistema',
                    'transaction_date' => now(),
                ]);
            }

            if ($totalPaid >= ($expectedTotal - 0.05) || $areAllItemsPaid || $excess > 0.02) {
                $order->service_fee = $requestedServiceFee;
                $order->total = $expectedTotal;
                $order->payment_status = 'paid';

                OrderItem::where('order_id', $order->id)
                    ->where('status', '!=', 'cancelled')
                    ->update(['paid_qty' => DB::raw('quantity')]);

                if ($order->type === 'table') {
                    $order->status = 'closed';
                    $order->closed_at = now();
                } elseif (in_array($order->status, ['pending', 'pending_payment'])) {
                    $order->status = 'preparing';
                }
            } else {
                $order->service_fee = $requestedServiceFee;
                $order->total = $expectedTotal;
                $order->payment_status = 'partial';
            }

            $order->save();
        });

        try {
            broadcast(new OrderUpdated($order));
        } catch (\Throwable $e) {
            Log::error('🚨 [WebSockets] Falha ao transmitir OrderUpdated #'.$order->id.': '.$e->getMessage());
        }
    }

    
    public function processWebhookPayment(string $txid, float $amount, ?string $endToEndId = null): void
    {
        $payment = \App\Models\Payment::where('txid', $txid)->first();
        $order = null;

        if ($payment) {
            $payment->update([
                'status' => 'paid',
                'is_paid' => true,
                'end_to_end_id' => $endToEndId,
                'amount' => $amount
            ]);
            $order = $payment->order;
        } else {
            $order = \App\Models\Order::where('txid', $txid)->first();
        }

        if (!$order) {
            throw new \Exception("Pedido ou Pagamento não encontrado para TXID: {$txid}");
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_intent_status' => 'paid'
        ]);

        if (in_array($order->status, ['pending_payment', 'pending'])) {
            $order->update(['status' => 'confirmed']);
        }

        // Se for balcão/mesa, emite nota e imprime automaticamente
        if (in_array($order->type, ['counter', 'table']) || empty($order->address_id)) {
            try {
                $printSvc = app(\App\Services\Printing\OrderPrintService::class);
                $printSvc->dispatchCheckoutReceipts($order, 'both');
            } catch (\Throwable $e) {}
        }

        broadcast(new \App\Events\OrderUpdated($order->fresh(['items', 'payments'])));
    }
}
