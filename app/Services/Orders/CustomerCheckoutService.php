<?php

namespace App\Services\Orders;

use App\Enums\PaymentEngineType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\CompanySetting;
use App\Services\Financial\PaymentGatewayManager;
use App\Services\Marketing\LoyaltyService;
use App\Services\Catalog\InventoryService;
use App\Services\RestaurantOps\StoreAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CustomerCheckoutService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected LoyaltyService $loyaltyService,
        protected OrderItemProcessor $itemProcessor,
        protected InventoryService $inventoryService,
        protected OrderCancellationService $cancellationService,
        protected OrderAddressResolver $addressResolver,
        protected StoreAvailabilityService $availabilityService,
        protected CustomerOrderSideEffects $sideEffects
    ) {}

    public function processB2CCheckout(array $payload, $customer, bool $isGuest): array
    {
        Log::info('📍 [SONDA CHECKOUT B2C - 1] Recebendo payload:', [
            'customer_id'       => $customer->id ?? 'guest',
            'type'              => $payload['routing']['type'] ?? 'null',
            'payment_method_id' => $payload['routing']['payment_method_id'] ?? 'NULO',
            'final_total'       => $payload['financials']['finalTotal'] ?? 0
        ]);

        $storeStatus = $this->availabilityService->getStoreStatus();
        $canOrder = $storeStatus['is_open'] || $storeStatus['is_pre_order'];

        if (!$canOrder) {
            $reasonMsg = match ($storeStatus['reason']) {
                'closed_indefinitely'    => 'O restaurante está fechado temporariamente.',
                'manual_override_closed' => 'O expediente de hoje já foi encerrado pelo operador.',
                'cashier_closed'         => 'Estamos preparando o sistema. O caixa abrirá em instantes!',
                default                  => 'O restaurante está fechado no momento para novos pedidos.'
            };
            throw new Exception($reasonMsg);
        }

        $isAppend = !empty($payload['append_to_order']);

        // 🛡️ TRAVA DE IDEMPOTÊNCIA (60s): Evita pedidos duplicados se o cliente reenviar o mesmo carrinho
        if (!$isAppend && !empty($customer?->id)) {
            $orderTypeCheck = $payload['routing']['type'] ?? 'delivery';
            $incomingSig = collect($payload['items'] ?? [])
                ->map(fn ($i) => (int) ($i['id'] ?? $i['product_id'] ?? 0) . ':' . (int) ($i['qty'] ?? 1))
                ->sort()
                ->values()
                ->implode('|');

            $recentOrders = Order::with('items')
                ->where('customer_id', $customer->id)
                ->where('type', $orderTypeCheck)
                ->whereNotIn('status', ['cancelled'])
                ->where('created_at', '>=', now()->subSeconds(60))
                ->latest('id')
                ->get();

            foreach ($recentOrders as $recent) {
                $existingSig = $recent->items
                    ->whereNull('parent_item_id')
                    ->map(fn ($i) => (int) $i->product_id . ':' . (int) $i->quantity)
                    ->sort()
                    ->values()
                    ->implode('|');

                if ($incomingSig !== '' && $incomingSig === $existingSig) {
                    Log::warning("🛡️ [Idempotency] Pedido duplicado bloqueado para Cliente #{$customer->id}. Reutilizando Pedido #{$recent->id}.");
                    $pendingPix = $recent->payments()->where('status', 'pending')->whereNotNull('txid')->latest('id')->first();

                    return [
                        'isBankApi' => (bool) ($recent->status === 'pending_payment' && $pendingPix),
                        'order'     => $recent,
                        'pixData'   => $pendingPix ? ['txid' => $pendingPix->txid, 'qr_code' => $pendingPix->pix_payload] : null,
                    ];
                }
            }
        }
        $order = null;
        $remainingBalance = 0.00;
        $needsPixPayment = false;
        $paymentMethod = null;

        // =========================================================================
        // 🚀 FASE 1: TRANSAÇÃO DE BANCO
        // =========================================================================
        DB::transaction(function () use (
            $payload, $customer, $isGuest, $isAppend,
            &$order, &$remainingBalance, &$needsPixPayment, &$paymentMethod
        ) {
            $routing    = $payload['routing'];
            $financials = $payload['financials'];
            $items      = $payload['items'];

            $targetCustomerId = $customer->id ?? null;
            $settings = CompanySetting::getCached() ?? CompanySetting::first();
            $orderType = $routing['type'] ?? 'delivery';

            if ($orderType === 'table' && !$isAppend && empty($settings?->allow_table_self_service)) {
                throw new Exception("Acesso Negado: O cardápio digital está configurado apenas para consulta nesta mesa.");
            }

            $serviceFee = $settings ? $settings->calculateServiceFee((float) $financials['subtotal'], $orderType) : 0.00;

            $couponCode   = $financials['coupon_code'] ?? null;
            $couponId     = $financials['coupon_id'] ?? null;
            $discount     = (float) ($financials['discount'] ?? 0);
            $cashbackUsed = (float) ($routing['cashback_discount'] ?? 0);

            if ($cashbackUsed > 0) {
                if ($isGuest || !$customer) {
                    $cashbackUsed = 0;
                } else {
                    try {
                        $cashbackUsed = $this->loyaltyService->validateCashback($customer, (float) $financials['subtotal'], $cashbackUsed);
                    } catch (\Throwable $e) {
                        $cashbackUsed = 0;
                        Log::warning("Cashback invalidado: " . $e->getMessage());
                    }
                }
            }

            $paymentMethodId = $routing['payment_method_id'] ?? null;
            $paymentMethod = $paymentMethodId ? PaymentMethod::find($paymentMethodId) : null;
            $isBankApi = $paymentMethod && ($paymentMethod->engine_type === PaymentEngineType::BANK_API || $paymentMethod->engine_type?->value === 'bank_api');

            $previewTotal = round((float)$financials['subtotal'] + (float)($financials['deliveryFee'] ?? 0) + $serviceFee - $discount - $cashbackUsed, 2);

            Log::info('📍 [SONDA CHECKOUT B2C - 2] Validação de Pagamento:', [
                'previewTotal'      => $previewTotal,
                'metodo_encontrado' => $paymentMethod ? $paymentMethod->name : 'NENHUM',
                'isBankApi'         => $isBankApi
            ]);

            if ($previewTotal > 0 && !$paymentMethod && !$isAppend && $orderType !== 'table') {
                Log::error('🚨 [SONDA CHECKOUT B2C - ERRO CRÍTICO] O Front-End tentou enviar um pedido pago sem selecionar a forma de pagamento!');
                throw new Exception("É obrigatório selecionar uma forma de pagamento válida para concluir este pedido online.");
            }

            // A. APPEND TO ORDER
            if ($isAppend) {
                if ($cashbackUsed > 0) throw new Exception("Não é possível utilizar Cashback ao adicionar itens.");
                if (!$settings || empty($settings->customer_can_append_orders)) throw new Exception("O restaurante não permite adicionar itens.");

                $order = Order::lockForUpdate()->findOrFail($payload['append_to_order']);

                if (in_array($order->status, ['closed', 'cancelled', 'completed', 'delivered', 'merged'], true)) {
                    throw new Exception("O pedido já saiu para entrega ou foi finalizado.");
                }

                $addedSubtotal = (float) $financials['subtotal'];
                $addedTotal    = round($addedSubtotal + $serviceFee - $discount, 2);

                $order->subtotal += $addedSubtotal;
                $order->discount += $discount;
                $order->service_fee += $serviceFee;
                $order->total += $addedTotal;
                $order->recalculatePaymentStatus();
                $order->save();

                $remainingBalance = $addedTotal;
            }
            // B. NOVO PEDIDO
            else {
                $realOrderTotal   = round((float)$financials['subtotal'] + (float)$financials['deliveryFee'] + $serviceFee - $discount, 2);
                $remainingBalance = round($realOrderTotal - $cashbackUsed, 2);

                $changeTarget = $routing['cash_change_target'] ?? ($routing['change_target'] ?? null);
                $finalNotes   = $routing['notes'] ?? null;

                $resolvedAddress = $this->addressResolver->resolve($routing, $targetCustomerId, (float) ($financials['deliveryFee'] ?? 0));

                $customerPhone = $routing['customer_phone'] ?? ($routing['customer']['phone'] ?? null) ?? ($customer->phone ?? null);

                $deliveryPaymentMethods = $routing['payment_intent_methods'] ?? null;

                if (empty($deliveryPaymentMethods) && !empty($paymentMethodId)) {
                    $deliveryPaymentMethods = [
                        [
                            'method_id'     => (int) $paymentMethodId,
                            'method_name'   => $routing['payment_method_name'] ?? ($paymentMethod->name ?? 'Não identificado'),
                            'amount'        => (float) $remainingBalance,
                            'change_target' => !empty($changeTarget) ? (float) $changeTarget : null,
                        ]
                    ];
                }

                $isFullyCoveredByCashback = ($remainingBalance <= 0);
                $needsPixPayment = $isBankApi && !$isFullyCoveredByCashback;

                $deliveryPaymentStatus = $needsPixPayment
                    ? 'pix_waiting'
                    : ($isFullyCoveredByCashback
                        ? 'paid'
                        : ($routing['payment_intent_status'] ?? ($orderType === 'delivery' ? 'pay_on_delivery' : 'pay_on_pickup')));

                $marketing = session('marketing_traffic', []);

                $currentShift = \App\Models\CashShift::where('status', 'open')->latest()->first();
                $courierService = app(\App\Services\Orders\CourierSettlementService::class);
                $soleCourier = null;

                if ($orderType === 'delivery') {
                    $activeCouriers = $courierService->getActiveCouriersOnShift($currentShift);
                    if ($activeCouriers->count() === 1) {
                        $soleCourier = $activeCouriers->first();
                    }
                }

                $order = Order::create([
                    'customer_id'            => $targetCustomerId,
                    'coupon_id'              => $couponId,
                    'cash_shift_id'          => $currentShift?->id,
                    'courier_id'             => $soleCourier?->id,
                    'driver_id'              => $soleCourier?->user_id ?? $soleCourier?->user?->id,
                    'courier_assigned_at'    => $soleCourier ? now() : null,
                    'type'                   => $orderType,
                    'source'                 => Order::SOURCE_CUSTOMER_APP,
                    'address_id'             => $resolvedAddress['address_id'],
                    'address_snapshot'       => $resolvedAddress['snapshot'],
                    'address'                => $resolvedAddress['snapshot'],
                    'customer_name'          => $routing['identifier'] ?? ($customer->name ?? ($routing['customer']['name'] ?? 'App')),
                    'customer_phone'         => $customerPhone,
                    'ip_address'             => request()->ip(),
                    'user_agent'             => request()->userAgent(),
                    'payment_intent_methods' => $deliveryPaymentMethods,
                    'payment_intent_status'  => $deliveryPaymentStatus,
                    'delivery_change_target' => $changeTarget,
                    'change_target'          => $changeTarget,
                    'status'                 => $needsPixPayment ? 'pending_payment' : 'pending',
                    'payment_status'         => 'pending',
                    'subtotal'               => $financials['subtotal'],
                    'delivery_fee'           => $financials['deliveryFee'],
                    'service_fee'            => $serviceFee,
                    'discount'               => $discount,
                    'cashback_used'          => $cashbackUsed > 0 ? $cashbackUsed : 0,
                    'total'                  => $realOrderTotal,
                    'notes'                  => $finalNotes,
                ]);

                if ($soleCourier) {
                    $order->updateQuietly([
                        'courier_fee' => $courierService->calculateFee($soleCourier, $order),
                    ]);
                }
            } // 🚀 Fechamento do bloco else (B. NOVO PEDIDO)

            foreach ($items as $item) {
                $this->itemProcessor->process($order, $item);
            }

            if ($cashbackUsed > 0 && !$isGuest && $customer) {
                $this->loyaltyService->consumeCashback($order, $customer, $cashbackUsed);
            }

            $isFullyCoveredByCashback = ($remainingBalance <= 0);
            $needsPixPayment = $isBankApi && !$isFullyCoveredByCashback;

            if ($isFullyCoveredByCashback) {
                $order->update(['payment_status' => 'paid', 'payment_intent_status' => 'paid', 'status' => 'pending']);
            }

            Log::info('📍 [SONDA CHECKOUT B2C - 3] Transação de banco concluída.', ['needsPix' => $needsPixPayment]);
        });

        // =========================================================================
        // 🚀 FASE 2: GERAÇÃO DE PIX FORA DA TRANSAÇÃO
        // =========================================================================
        $pixData = null;

        if ($needsPixPayment && $paymentMethod) {
            try {
                Log::info('📍 [SONDA CHECKOUT B2C - 4] Solicitando PIX ao Banco...');
                $gateway = $this->gatewayManager->resolveGateway($paymentMethod->gateway_driver);
                $pixData = $gateway->generatePayment($order, $remainingBalance);

                $order->payments()->create([
                    'payment_method_id' => $paymentMethod->id,
                    'method'            => $paymentMethod->name ?? 'Pix',
                    'amount'            => $remainingBalance,
                    'status'            => 'pending',
                    'is_paid'           => false,
                    'txid'              => $pixData['txid'],
                    'pix_payload'       => $pixData['qr_code']
                ]);

                $order->update([
                    'txid' => $pixData['txid']
                ]);

                Log::info('📍 [SONDA CHECKOUT B2C - 5] PIX Gerado com Sucesso!', ['txid' => $pixData['txid']]);
            } catch (Exception $e) {
                Log::error("❌ [PixGateway] Falha ao gerar cobrança Pix: " . $e->getMessage());
                if (!$isAppend) {
                    $order->update(['status' => 'cancelled', 'cancel_reason' => 'Falha bancária: ' . $e->getMessage()]);
                }
                throw new Exception("Falha de comunicação com o Banco: " . $e->getMessage());
            }
        }

        $this->sideEffects->handle($order, $isAppend, $needsPixPayment);

        return [
            'isBankApi' => $needsPixPayment,
            'order'     => $order,
            'pixData'   => $pixData
        ];
    }

    public function cancelOrderByCustomer(Order $order, ?int $activeCustomerId): void
    {
        $this->cancellationService->cancelByCustomer($order, $activeCustomerId);
    }

    public function systemCancelOrder(Order $order, string $reason = 'Cancelado pelo sistema'): void
    {
        $this->cancellationService->cancelBySystem($order, $reason);
    }

    public function rateOrder(Order $order, array $data, ?int $customerId): void
    {
        if ($order->customer_id && $order->customer_id !== $customerId) {
            throw new Exception('Você não tem permissão para avaliar este pedido.', 403);
        }

        $order->rating()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'customer_id' => $customerId,
                'rating'      => $data['rating'],
                'tags'        => $data['tags'] ?? [],
                'comment'     => $data['comment'] ?? null,
            ]
        );
    }
}
