<?php

namespace App\Services\Orders;

use App\Enums\PaymentEngineType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\DiningTable;
use App\Models\Coupon;
use App\Events\OrderUpdated;
use App\Events\DiningTableUpdated;
use App\Services\Marketing\CouponService;
use App\Services\Marketing\LoyaltyService;
use App\Services\Financial\Gateways\SicrediPixGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderPaymentService
{
    public function __construct(
        protected LoyaltyService $loyaltyService,
        protected PaymentEligibilityService $eligibilityService,
        protected OrderItemSettlementService $settlementService,
        protected OrderPixService $pixService
    ) {}

    public function attachActiveShiftIfMissing(Order $order): void
    {
        if (empty($order->cash_shift_id)) {
            $shift = CashShift::where('status', 'open')->latest()->first();
            if ($shift) {
                $order->cash_shift_id = $shift->id;
                $order->save();
            }
        }
    }

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

    public function processPayment(Order $order, array $data): void
    {
        // 1. Validação de elegibilidade (Convênio, Colaborador, Vale)
        $this->eligibilityService->validate($order, $data['payments']);

        $cashbackUsed = (float) ($data['cashback_used'] ?? 0);

        DB::transaction(function () use ($order, $data, $cashbackUsed) {
            // 🛡️ Bloqueio de linha (Pessimistic Locking)
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // 🛡️ GUARDA 1: Bloqueia se a encomenda já estiver fechada ou cancelada
            if (in_array($lockedOrder->status, ['closed', 'cancelled', 'completed'], true)) {
                throw new Exception("Operação negada: O Pedido #{$lockedOrder->id} já se encontra finalizado ou cancelado.");
            }

            $currentPaid = (float) $lockedOrder->payments()->sum('amount');
            $currentTotal = (float) $lockedOrder->total;

            // 🛡️ GUARDA 2: Bloqueia se o pedido já estiver quitado
            if ($lockedOrder->payment_status === 'paid' && $currentTotal > 0 && ($currentTotal - $currentPaid) <= 0.05) {
                throw new Exception("Operação negada: O Pedido #{$lockedOrder->id} já se encontra integralmente quitado.");
            }

            $shift = CashShift::where('status', 'open')->latest()->first();
            if (!$shift) {
                throw new Exception('Operação negada: Nenhum turno de caixa aberto. Abra o caixa antes de receber pagamentos.');
            }

            if (empty($order->cash_shift_id)) {
                $order->cash_shift_id = $shift->id;
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
                    if ($order->payments()->where('payment_method_id', $methodId)->exists()) {
                        continue;
                    }
                }

                // Débito em carteira de colaborador (Compras de Balcão / Pessoais)
                $isInternal = $paymentMethod && ($paymentMethod->engine_type === PaymentEngineType::INTERNAL || $paymentMethod->engine_type?->value === 'internal');
                if ($isInternal) {
                    $cleanCpf = preg_replace('/\D/', '', $order->customer->cpf ?? '');
                    $employee = $order->customer->employee
                        ?? Employee::where(function ($q) use ($cleanCpf, $order) {
                            if (!empty($cleanCpf)) $q->where('cpf', $cleanCpf);
                            if (!empty($order->customer->email)) $q->orWhere('email', $order->customer->email);
                        })->first()
                        ?? (auth()->user()?->employee_id ? Employee::find(auth()->user()->employee_id) : null);

                    if (!$employee) {
                        throw new Exception("O cliente não possui um cadastro de Colaborador válido para debitar o Consumo Interno.");
                    }

                    $latestLedger = $employee->ledgers()->latest('id')->first();
                    $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;

                    $employee->ledgers()->create([
                        'type'        => 'debit',
                        'description' => "Consumo Balcão - Pedido #{$order->id}",
                        'amount'      => $payment['amount'],
                        'balance'     => $currentBalance - (float) $payment['amount']
                    ]);
                }

                // 🛡️ GUARDA 3: Prevenção de duplicidade temporal exata (mesmo método e valor nos últimos 30s)
                $recentDuplicate = $lockedOrder->payments()
                    ->where('payment_method_id', $methodId)
                    ->where('amount', $payment['amount'])
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->exists();

                if ($recentDuplicate) {
                    $remainingBalance = max(0, $expectedTotal - (float) $lockedOrder->payments()->sum('amount'));
                    if ($remainingBalance <= 0.05 || $lockedOrder->payment_status === 'paid') {
                        Log::warning("⚠️ [Anti-Duplicação] Pagamento duplicado ignorado no Pedido #{$lockedOrder->id}: R$ {$payment['amount']} ({$methodName}).");
                        continue;
                    }
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
                        'bank_account_id'   => $paymentMethod?->destination_account_id ?? ($isCash ? 1 : null),
                        'cash_shift_id'     => $shift->id,
                        'order_id'          => $order->id,
                        'payment_id'        => $orderPayment->id,
                        'payment_method_id' => $methodId,
                        'type'              => 'in',
                        'category'          => 'order_sale',
                        'amount'            => $payment['amount'],
                        'description'       => "Pedido #{$order->id} ({$methodName})",
                        'operator_name'     => auth()->user()->name ?? 'Sistema',
                        'transaction_date'  => now(),
                    ]);
                }
            }

            // 2. Baixa de itens (delegada ao OrderItemSettlementService)
            if (!empty($data['items'])) {
                $targetPayment = $createdPayments[0] ?? null;
                $fallbackAmount = !empty($data['payments']) ? (float) $data['payments'][0]['amount'] : null;
                $this->settlementService->settleItems($order, $data['items'], $targetPayment, $fallbackAmount);
            }

            $totalPaid = (float) $order->payments()->sum('amount');
            $excess = round($totalPaid - $expectedTotal, 2);
            $areAllItemsPaid = $this->settlementService->areAllItemsPaid($order);

            // Registro de Troco se houver excesso em dinheiro
            if ($excess > 0.02 && $cashPaidThisRun > 0) {
                $cashChange = min($cashPaidThisRun, $excess);
                $order->change_returned = round(($order->change_returned ?? 0) + $cashChange, 2);

                Transaction::create([
                    'bank_account_id'   => 1,
                    'cash_shift_id'     => $shift->id,
                    'order_id'          => $order->id,
                    'type'              => 'out',
                    'category'          => 'change',
                    'amount'            => $cashChange,
                    'description'       => "Troco - Pedido #{$order->id}",
                    'operator_name'     => auth()->user()->name ?? 'Sistema',
                    'transaction_date'  => now(),
                ]);
            }

            // Atualização do status financeiro do pedido
            if ($totalPaid >= ($expectedTotal - 0.05) || $areAllItemsPaid || $excess > 0.02) {
                $order->service_fee = $requestedServiceFee;
                $order->total = $expectedTotal;
                $order->payment_status = 'paid';

                OrderItem::where('order_id', $order->id)
                    ->where('status', '!=', 'cancelled')
                    ->update(['paid_qty' => DB::raw('quantity')]);

                // O balcão pago permanece em 'preparing' (ou status atual de produção).
                // Ele NUNCA é fechado automaticamente aqui.
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

        // Resolve liberação de mesas (sem auto-split) e verificação de entrega de delivery
        $this->resolveOrderCompletion($order);

        try {
            broadcast(new OrderUpdated($order));
        } catch (\Throwable $e) {
            Log::error('🚨 [WebSockets] Falha ao transmitir OrderUpdated #'.$order->id.': '.$e->getMessage());
        }
    }

    public function destroyPayment(Order $order, Payment $payment): void
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            throw new Exception("Este pagamento não pertence ao pedido especificado.");
        }

        $payment->loadMissing('paymentMethod');
        $isApiEngine = $payment->paymentMethod && in_array(
            $payment->paymentMethod->engine_type?->value ?? $payment->paymentMethod->engine_type,
            ['bank_api', 'sicredi_pix', 'pix_api']
        );

        if ($isApiEngine && $payment->is_paid) {
            throw new Exception("Pagamento via API liquidado não pode ser excluído diretamente. Utilize a opção de Estorno com autorização.");
        }

        DB::transaction(function () use ($payment, $order) {
            $payment->loadMissing('transaction');
            if ($payment->transaction) {
                $payment->transaction->delete();
            }

            $payment->delete();

            if (method_exists($order, 'recalculatePaymentStatus')) {
                $order->recalculatePaymentStatus();
            }
        });

        broadcast(new OrderUpdated($order->fresh(['payments', 'items'])));
    }

    public function generatePixPayment(Order $order, ?float $requestedAmount, SicrediPixGateway $pixGateway): array
    {
        return $this->pixService->generateCharge($order, $requestedAmount, $pixGateway);
    }

    public function cancelPixPayment(Order $order): void
    {
        $this->pixService->cancelIntent($order);
    }

    public function processWebhookPayment(string $txid, float $amount, ?string $endToEndId = null): void
    {
        $this->pixService->handleWebhook($txid, $amount, $endToEndId);
    }

    /**
     * Conclui pedidos e libera mesas físicas com integridade de layout.
     */
    public function resolveOrderCompletion(Order $order): void
    {
        $shouldClose = ($order->type === 'delivery' && in_array($order->status, ['dispatched', 'delivered']) && $order->payment_status === 'paid')
            || ($order->type === 'table' && $order->payment_status === 'paid');

        if (!$shouldClose) {
            return;
        }

        $order->status = 'closed';
        $order->closed_at = now();
        $order->save();

        // Libera a mesa física preservando o layout de junção (sem auto-split)
        if ($order->type === 'table') {
            $table = $order->diningTable
                ?? ($order->dining_table_id ? DiningTable::find($order->dining_table_id) : null)
                ?? (!empty($order->table_number) ? DiningTable::where('name', $order->table_number)->first() : null);

            if ($table && !$table->hasActiveOrdersInGroup($order->id)) {
                $groupTableIds = $table->getGroupTableIds();
                DiningTable::whereIn('id', $groupTableIds)->update(['status' => 'available']);

                $broadcastTables = function () use ($groupTableIds) {
                    foreach ($groupTableIds as $id) {
                        $t = DiningTable::find($id);
                        if ($t) {
                            broadcast(new DiningTableUpdated($t));
                        }
                    }
                };

                if (DB::transactionLevel() > 0) {
                    DB::afterCommit($broadcastTables);
                } else {
                    $broadcastTables();
                }
            }
        }

        broadcast(new OrderUpdated($order->fresh()));
    }
}
