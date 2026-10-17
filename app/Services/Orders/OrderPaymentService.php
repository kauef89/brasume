<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\CashShift;
use App\Models\Employee;
use App\Events\OrderUpdated;
use App\Events\OrderCreated;
use App\Services\Marketing\LoyaltyService;
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

                if ($paymentMethod && $paymentMethod->engine_type === 'internal_loyalty') {
                    $alreadyRegistered = $order->payments()->where('payment_method_id', $methodId)->exists();
                    if ($alreadyRegistered) {
                        continue;
                    }
                }

                $isInternalAllowance = $paymentMethod && $paymentMethod->engine_type === 'internal';

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

                $isCash = ($paymentMethod && $paymentMethod->engine_type !== 'loss' && in_array($paymentMethod->engine_type, ['money', 'cash']))
                    || str_contains(mb_strtolower($methodName), 'dinheiro');

                if ($isCash) {
                    $cashPaidThisRun += (float) $payment['amount'];
                }

                if ($paymentMethod && !in_array($paymentMethod->engine_type, ['internal_loyalty', 'internal', 'loss'])) {
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

            // Troco Real: Saída da gaveta se pago em espécie
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

            // Liquidação da conta
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
        Log::info("OrderPaymentService acionado para o TXID: {$txid}");

        $order = DB::transaction(function () use ($txid, $amount, $endToEndId) {

            // 🚀 Tenta achar via coluna nativa ou caso o frontend tenha injetado via payments pivot JSON
            $order = Order::with(['customer.user', 'items', 'payments'])
                ->where('txid', $txid)
                ->orWhere('payment_intent_methods', 'like', "%\"txid\":\"{$txid}\"%")
                ->first();

            if (!$order) {
                // Última tentativa: busca por uma tentativa prévia salva na tabela de pagamentos
                $payment = \App\Models\Payment::where('txid', $txid)->with('order')->first();
                if ($payment && $payment->order) {
                    $order = $payment->order;
                } else {
                    throw new Exception("Pedido com TXID {$txid} não foi encontrado no banco de dados.");
                }
            }

            if ($order->payment_status === 'paid') {
                return $order;
            }

            $shift = CashShift::where('status', 'open')->latest()->first();

            if (!$shift) {
                throw new Exception('Operação negada: Nenhum turno de caixa aberto. Por favor, abra o caixa antes de receber pagamentos.');
            }

            $orderPayment = $order->payments()->where('txid', $txid)->first();
            $paymentMethod = PaymentMethod::where('name', 'like', '%PIX%')->orWhere('engine_type', 'bank_api')->first();

            if ($orderPayment) {
                $orderPayment->update([
                    'status' => 'paid',
                    'is_paid' => true,
                    'transaction_id' => $endToEndId,
                    'cash_shift_id' => $shift->id
                ]);
            } else {
                $orderPayment = $order->payments()->create([
                    'payment_method_id' => $paymentMethod ? $paymentMethod->id : null,
                    'cash_shift_id'     => $shift->id,
                    'amount'            => $amount,
                    'method'            => $paymentMethod ? $paymentMethod->name : 'PIX',
                    'status'            => 'paid',
                    'is_paid'           => true,
                    'txid'              => $txid,
                    'transaction_id'    => $endToEndId
                ]);
            }

            Transaction::create([
                'bank_account_id'  => $shift->bank_account_id,
                'cash_shift_id'    => $shift->id,
                'order_id'         => $order->id,
                'payment_id'       => $orderPayment->id,
                'payment_method_id'=> $orderPayment->payment_method_id,
                'type'             => 'in',
                'category'         => 'order_sale',
                'amount'           => $amount,
                'description'      => "Pix Confirmado Webhook - Pedido #{$order->id}",
                'operator_name'    => 'Sistema (Sicredi API)',
                'transaction_date' => now(),
            ]);

            foreach ($order->items as $item) {
                if ($item->status === 'cancelled') continue;
                $item->update(['paid_qty' => $item->quantity]);

                $orderPayment->items()->syncWithoutDetaching([
                    $item->id => [
                        'quantity' => $item->quantity,
                        'amount'   => (float) $item->total_price
                    ]
                ]);
            }

            $order->payment_status = 'paid';
            $order->payment_intent_status = 'paid';

            if ($order->type === 'table') {
                $order->status = 'closed';
                $order->closed_at = now();
            } elseif (in_array($order->status, ['pending', 'pending_payment'])) {
                $order->status = 'preparing';
            }

            $order->save();

            return $order;
        });

        try {
            if ($order) {
                broadcast(new OrderCreated($order));
                broadcast(new OrderUpdated($order));

                app(KitchenDispatchService::class)->dispatchNewItems($order);
                $this->notifyTeamOfPaidPixOrder($order);

                if ($order->customer && $order->customer->user) {
                    $pushMessage = "Seu pagamento de R$ " . number_format($amount, 2, ',', '.') . " foi confirmado! Seu pedido já está em preparação.";
                    $order->customer->user->notify(new OrderStatusUpdated($order, $pushMessage));
                }
            }
        } catch (Exception $e) {
            Log::error("Falha ao notificar pedido pago via Webhook ({$txid}): " . $e->getMessage());
        }
    }

    private function notifyTeamOfPaidPixOrder(Order $order): void
    {
        $attendanceService = app(ShiftAttendanceService::class);
        $order->loadMissing('items.product');

        $cashierStaff = $attendanceService->getActiveShiftStaffByRoles(['caixa', 'pos', 'manager', 'admin', 'gerente']);

        if ($cashierStaff->isNotEmpty()) {
            $msgCaixa = "PIX Recebido! Pedido #{$order->id} pago e na fila! (Tipo: {$order->type})";
            Notification::send($cashierStaff, new NewB2COrderNotification($order, $msgCaixa));
        }

        $activeStations = $order->items->pluck('product.production_station')->filter()->unique();

        foreach ($activeStations as $station) {
            $stationLower = mb_strtolower($station);

            if (str_contains($stationLower, 'bar') || $stationLower === 'bebidas') {
                $barStaff = $attendanceService->getActiveShiftStaffByRoles(['bar', 'bartender', 'copa']);
                if ($barStaff->isNotEmpty()) {
                    $msgBar = "PIX Pago: Novos itens de Bar liberados no Pedido #{$order->id}!";
                    Notification::send($barStaff, new NewB2COrderNotification($order, $msgBar));
                }
            }

            if (str_contains($stationLower, 'cozinha') || $stationLower === 'quente') {
                $kitchenStaff = $attendanceService->getActiveShiftStaffByRoles(['cozinha', 'cook', 'chef', 'kds']);
                if ($kitchenStaff->isNotEmpty()) {
                    $msgCozinha = "PIX Pago: Novos pratos liberados para preparo - Pedido #{$order->id}!";
                    Notification::send($kitchenStaff, new NewB2COrderNotification($order, $msgCozinha));
                }
            }
        }
    }
}
