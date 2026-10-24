<?php

namespace App\Services\Orders;

use App\Enums\PaymentEngineType;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\CashShift;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\Employee;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\FiscalDocument;
use App\Models\DiningTable;
use App\Services\Catalog\InventoryService;
use App\Services\RestaurantOps\PosSecurityService;
use App\Services\RestaurantOps\TablesService;
use App\Services\Fiscal\SpedDocumentService;
use App\Services\Marketing\LoyaltyService;
use App\Events\OrderUpdated;
use App\Events\OrderCancelled;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class OrderCancellationService
{
    public function __construct(
        protected PosSecurityService $securityService,
        protected InventoryService $inventoryService,
        protected OrderNotificationDispatcher $notificationDispatcher
    ) {}

    public function cancelByCustomer(Order $order, ?int $activeCustomerId): void
    {
        if ($order->customer_id && (int) $order->customer_id !== (int) $activeCustomerId) {
            throw new Exception("Operação não autorizada. Este pedido não pertence à sua conta.", 403);
        }

        if (!in_array($order->status, ['pending', 'pending_payment'])) {
            throw new Exception("Este pedido já entrou em preparação e não pode mais ser cancelado.");
        }

        $this->executeB2CCancellation($order, 'Cancelado pelo cliente via App');

        $this->notificationDispatcher->notifyTeamOfCancellation(
            $order,
            "ALERTA: O cliente cancelou o pedido #{$order->id} pelo App!"
        );
    }

    public function cancelBySystem(Order $order, string $reason = 'Cancelado pelo sistema'): void
    {
        if (!in_array($order->status, ['pending', 'pending_payment'])) {
            return;
        }

        $this->executeB2CCancellation($order, $reason);

        $this->notificationDispatcher->notifyTeamOfCancellation(
            $order,
            "Sistema: Pedido #{$order->id} cancelado ({$reason})."
        );
    }

    protected function executeB2CCancellation(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order->update([
                'status'         => 'cancelled',
                'payment_status' => 'cancelled',
                'notes'          => trim(($order->notes ? $order->notes . ' | ' : '') . $reason)
            ]);

            // Carrega os itens e subitens com Eager Loading
            $order->load(['items.modifiers', 'items.subItems.modifiers']);

            foreach ($order->items as $item) {
                if ($item->status === 'cancelled') continue;

                // 🚀 OTIMIZAÇÃO: Estorna apenas se for item principal (parent_item_id = null)
                // O método processOrderItemStock JÁ lida com os subitens recursivamente!
                if ($item->parent_item_id === null) {
                    $this->inventoryService->processOrderItemStock($item, 'restore');
                }
            }

            // Atualiza o status em massa (uma query para todos os itens do pedido)
            $order->items()->update([
                'status'            => 'cancelled',
                'production_status' => 'cancelled',
                'cancel_reason'     => $reason,
            ]);

            if ($order->coupon_id) {
                $coupon = Coupon::find($order->coupon_id);
                if ($coupon && $coupon->usage_count > 0) {
                    $coupon->decrement('usage_count');
                }
            }
        });

        broadcast(new OrderUpdated($order));
        event(new OrderCancelled($order));
    }

    public function cancelItem(Order $order, array $data): void
    {
        $orderWasCancelled = false;

        DB::transaction(function () use ($order, $data, &$orderWasCancelled) {
            $item = OrderItem::with(['modifiers', 'subItems.modifiers'])
                ->where('order_id', $order->id)
                ->findOrFail($data['item_id']);

            $authorizer = $this->securityService->validateAndLog(
                'cancel_item',
                $data['pin'] ?? null,
                $data['reason'] ?? 'Sem motivo informado',
                ['item_name' => $item->product_name, 'price' => $item->total_price, 'order_id' => $order->id]
            );

            // Se for perda operacional, lança o registro
            if (!empty($data['is_loss'])) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $this->inventoryService->registerLoss(
                        $product,
                        $item->quantity,
                        'Salão: ' . ($data['reason'] ?? 'Não especificado'),
                        'Estornado diretamente na comanda.',
                        $authorizer->id,
                        $item->id
                    );
                }
            } else {
                // Estorna tudo do item pai E DOS SUBITENS usando o método centralizado
                $this->inventoryService->processOrderItemStock($item, 'restore');
            }

            // 1. Cancela o item solicitado
            $item->update([
                'status'            => 'cancelled',
                'production_status' => 'cancelled',
                'cancel_reason'     => 'Salão: ' . ($data['reason'] ?? null)
            ]);

            // 🚀 OTIMIZAÇÃO: Apenas altera o status no BD dos subitens, sem restaurar estoque de novo (evitando Kardex N+1 duplicado)
            if ($item->subItems()->exists()) {
                $item->subItems()->update([
                    'status'            => 'cancelled',
                    'production_status' => 'cancelled',
                    'cancel_reason'     => 'Salão: Combo cancelado (' . ($data['reason'] ?? 'Cancelamento') . ')'
                ]);
            }

            // 2. Recalcula o subtotal e total com base nos itens ativos não-filhos
            $remainingSubtotal = $order->items()
                ->where('status', '!=', 'cancelled')
                ->whereNull('parent_item_id')
                ->sum(DB::raw('quantity * unit_price'));

            $order->subtotal = max(0, (float) $remainingSubtotal);
            $order->total = max(0, (float) $order->subtotal + (float) $order->delivery_fee + (float) $order->service_fee - (float) $order->discount);
            $order->save();

            // 3. AUTO-CANCELAMENTO: Verifica se restou algum item ativo na comanda
            $hasActiveItems = $order->items()
                ->where('status', '!=', 'cancelled')
                ->exists();

            $totalPaid = (float) $order->payments()->sum('amount');

            // Se não restou item ativo e não há saldo pago, cancela a comanda e libera a mesa
            if (!$hasActiveItems && $totalPaid <= 0.01) {
                $order->update([
                    'status'         => 'cancelled',
                    'payment_status' => 'cancelled',
                    'total'          => 0,
                    'subtotal'       => 0,
                    'notes'          => trim(($order->notes ? $order->notes . ' | ' : '') . 'Cancelado automaticamente: todos os itens foram estornados.')
                ]);

                if (true) {
                    $order->update(['closed_at' => now()]);
                }

                // 4. Liberação da mesa física
                $tableId = $order->dining_table_id;
                if ($tableId) {
                    $hasOtherActive = Order::where('dining_table_id', $tableId)
                        ->where('id', '!=', $order->id)
                        ->whereNotIn('status', ['closed', 'cancelled'])
                        ->exists();

                    if (!$hasOtherActive) {
                        $table = DiningTable::find($tableId);
                        if ($table) {
                            $table->update(['status' => 'free']);
                            if (class_exists(TablesService::class)) {
                                app(TablesService::class)->split($table);
                            }
                        }
                    }
                }

                $orderWasCancelled = true;
            }
        });

        $freshOrder = $order->fresh(['items', 'payments', 'diningTable']);
        broadcast(new OrderUpdated($freshOrder));

        if ($orderWasCancelled) {
            event(new OrderCancelled($freshOrder));
        }
    }

    public function cancelOrder(Order $order, array $data): void
    {
        DB::transaction(function () use ($order, $data) {
            $authorizer = $this->securityService->validateAndLog(
                'cancel_order',
                $data['pin'] ?? null,
                $data['reason'] ?? 'Cancelamento de Pedido',
                ['order_id' => $order->id, 'total' => $order->total]
            );

            $reasonText = $data['reason'] ?? 'Cancelado pela loja';
            $operatorId = $authorizer?->id ?? auth()->id();

            $order->load(['items.modifiers', 'items.subItems.modifiers']);

            foreach ($order->items as $item) {
                if ($item->status === 'cancelled') continue;

                // Apenas processa se for o PAI (o processOrderItemStock vai varrer os filhos)
                if ($item->parent_item_id === null) {
                    if (!empty($data['is_loss']) && !empty($data['lost_items']) && in_array($item->id, $data['lost_items'])) {
                        if ($item->product_id) {
                            $product = Product::find($item->product_id);
                            if ($product) {
                                $this->inventoryService->registerLoss(
                                    $product,
                                    $item->quantity,
                                    'Cancelamento do Pedido #' . $order->id . ': ' . $reasonText,
                                    'Cancelamento total via POS.',
                                    $operatorId,
                                    $item->id
                                );
                            }
                        }
                    } else {
                        $this->inventoryService->processOrderItemStock($item, 'restore');
                    }
                }
            }

            $order->update([
                'status'         => 'cancelled',
                'payment_status' => 'cancelled',
                'notes'          => trim(($order->notes ? $order->notes . ' | ' : '') . 'Cancelado: ' . $reasonText),
            ]);

            $order->items()->update([
                'status'            => 'cancelled',
                'production_status' => 'cancelled',
                'cancel_reason'     => $reasonText,
            ]);

            if ($order->coupon_id) {
                $coupon = Coupon::find($order->coupon_id);
                if ($coupon && $coupon->usage_count > 0) {
                    $coupon->decrement('usage_count');
                }
            }

            // Libera a mesa física se for pedido de mesa
            $tableId = $order->dining_table_id;
            if ($tableId) {
                $hasOtherActive = Order::where('dining_table_id', $tableId)
                    ->where('id', '!=', $order->id)
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->exists();

                if (!$hasOtherActive) {
                    $table = DiningTable::find($tableId);
                    if ($table) {
                        $table->update(['status' => 'free']);
                        if (class_exists(TablesService::class)) {
                            app(TablesService::class)->split($table);
                        }
                    }
                }
            }
        });

        broadcast(new OrderUpdated($order));
        event(new OrderCancelled($order));

        if ($order->customer && $order->customer->user_id) {
            $pushMessage = "O pedido #{$order->id} foi cancelado pela loja.";
            $order->customer->user->notify(new OrderStatusUpdated($order, $pushMessage));
        }
    }

    public function refundAndClone(Order $order, array $data): void
    {
        $justification = $data['reason'] ?? 'Correcao de lancamento';
        if (strlen($justification) < 15) {
            $justification .= ' - Solicitado Reabertura';
        }

        $fiscalDoc = FiscalDocument::where('order_id', $order->id)
            ->where('status', 'authorized')
            ->first();

        if ($fiscalDoc) {
            $spedService = app(SpedDocumentService::class);
            $spedService->cancelNfce($fiscalDoc, $justification);
        }

        DB::transaction(function () use ($order, $data) {
            $shift = CashShift::where('status', 'open')->latest()->first();

            if (!$shift && $order->payments()->count() > 0) {
                throw new Exception("Não é possível realizar o estorno financeiro com o caixa fechado. Abra o turno primeiro para poder devolver o dinheiro do pedido #{$order->id}.");
            }

            $totalChangeReturned = (float) ($order->change_returned ?? 0);

            foreach ($order->payments as $payment) {
                $pm = $payment->payment_method_id ? PaymentMethod::find($payment->payment_method_id) : null;

                if ($pm && ($pm->engine_type === PaymentEngineType::INTERNAL_LOYALTY || $pm->engine_type?->value === 'internal_loyalty')) {
                    app(LoyaltyService::class)->refundOrderCashback($order);
                    continue;
                }

                if ($pm && ($pm->engine_type === PaymentEngineType::INTERNAL || $pm->engine_type === PaymentEngineType::ALLOWANCE || in_array($pm->engine_type?->value, ['internal', 'allowance']))) {
                    if ($order->customer) {
                        $employee = Employee::where('cpf', preg_replace('/\D/', '', $order->customer->cpf ?? ''))
                            ->orWhere('email', $order->customer->email)
                            ->first();

                        if ($employee) {
                            $latestLedger = $employee->ledgers()->latest('id')->first();
                            $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;

                            $employee->ledgers()->create([
                                'type'        => 'credit',
                                'description' => "Estorno de Consumo Interno - Pedido #{$order->id}",
                                'amount'      => $payment->amount,
                                'balance'     => $currentBalance + (float) $payment->amount
                            ]);
                        }
                    }
                    continue;
                }

                $isCash = ($pm && ($pm->engine_type === PaymentEngineType::MONEY || in_array($pm->engine_type?->value, ['money', 'cash'])))
                    || str_contains(mb_strtolower($payment->method ?? ''), 'dinheiro');

                $amountToRefund = (float) $payment->amount;

                if ($isCash && $totalChangeReturned > 0) {
                    $deduction = min($amountToRefund, $totalChangeReturned);
                    $amountToRefund -= $deduction;
                    $totalChangeReturned -= $deduction;
                }

                if ($amountToRefund > 0 && $shift) {
                    Transaction::create([
                        'bank_account_id'  => $shift->bank_account_id,
                        'cash_shift_id'    => $shift->id,
                        'type'             => 'out',
                        'category'         => 'refund',
                        'amount'           => $amountToRefund,
                        'description'      => 'Estorno Pedido #' . $order->id . ' (' . $payment->method . ')',
                        'operator_name'    => auth()->user()->name ?? 'Sistema',
                        'transaction_date' => now(),
                        'order_id'         => $order->id
                    ]);
                }
            }

            $order->change_returned = 0;
            $order->payments()->delete();
            $order->items()->update(['paid_qty' => 0]);

            $order->update([
                'status'                => 'pending',
                'payment_status'        => 'pending',
                'payment_intent_status' => 'pending_choice',
                'txid'                  => null,
                'notes'                 => ltrim($order->notes . "\nReativado. Motivo: " . ($data['reason'] ?? 'Correção de Lançamentos.'))
            ]);
        });

        broadcast(new OrderUpdated($order));
    }
}
