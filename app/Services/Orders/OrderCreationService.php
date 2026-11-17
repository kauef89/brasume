<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\CashShift;
use Exception;
use App\Services\Orders\Handlers\OrderAppendHandler;
use App\Services\Orders\Handlers\TableOrderHandler;
use App\Services\Orders\Handlers\CounterOrderHandler;
use App\Services\Orders\Handlers\PickupOrderHandler;
use App\Services\Orders\Handlers\DeliveryOrderHandler;
use App\Services\RestaurantOps\KitchenDispatchService;
use App\Notifications\OrderStatusUpdated;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Detection\MobileDetect;

class OrderCreationService
{
    public function __construct(
        protected OrderAppendHandler $appendHandler,
        protected TableOrderHandler $tableHandler,
        protected CounterOrderHandler $counterHandler,
        protected PickupOrderHandler $pickupHandler,
        protected DeliveryOrderHandler $deliveryHandler,
        protected OrderItemProcessor $itemProcessor,
        protected KitchenDispatchService $kitchenDispatchService
    ) {}

    public function processCheckout(array $payload): Order
    {
        $routing = $payload["routing"] ?? [];
        $targetOrderId = $payload["order_id"] ?? ($routing["id"] ?? ($routing["order_id"] ?? null));

        // 🚀 1. FLUXO DE ADIÇÃO (APPEND TO EXISTING ORDER)
        if ($this->appendHandler->canAppend($targetOrderId)) {
            return $this->appendHandler->handle((int) $targetOrderId, $payload);
        }

        // 🚀 2. FLUXO DE CRIAÇÃO DE NOVO PEDIDO
        $order = DB::transaction(function () use ($payload, $routing) {
            $settings = \App\Models\CompanySetting::getCached() ?? \App\Models\CompanySetting::first();
            $enforceTeam = (bool) ($settings?->enforce_cashier_open_team ?? false);

            $shift = CashShift::where('status', 'open')->latest()->first();

            // Só lança exceção se o tenant configurou obrigatoriedade para a equipe interna
            if ($enforceTeam && !$shift) {
                throw new Exception("O caixa está fechado no momento. Peça ao caixa ou gerente para abrir o turno antes de lançar novos pedidos.");
            }
            $orderType = $routing["type"] ?? "table";
            $source    = $this->resolveSource($payload);
            $operatorId = auth('team')->id() ?? auth('web')->id() ?? auth()->id() ?? 1;

            // Se for mesa sem ID explícito, verifica se a mesa já possui comanda em aberto
            if ($orderType === 'table') {
                $tableId = $this->tableHandler->resolveTableId($routing);
                $isSubaccount = !empty($routing['is_subaccount']) || !empty($routing['new_subaccount']);

                $existingOrder = $this->tableHandler->findActiveTableOrder($tableId, $isSubaccount);
                if ($existingOrder && $this->appendHandler->canAppend($existingOrder->id)) {
                    return $this->appendHandler->handle($existingOrder->id, $payload);
                }

                $orderData = $this->tableHandler->buildOrderData($payload, $tableId, $operatorId, $source);
            } elseif ($orderType === 'pickup') {
                $orderData = $this->pickupHandler->buildOrderData($payload, $operatorId, $source);
            } elseif ($orderType === 'counter') {
                $orderData = $this->counterHandler->buildOrderData($payload, $operatorId, $source);
            } else {
                $orderData = $this->deliveryHandler->buildOrderData($payload, $operatorId, $source);
            }

            $financials = $payload['financials'] ?? [];
            $couponId = $financials['coupon_id'] ?? ($orderData['coupon_id'] ?? null);
            $couponCode = $financials['coupon_code'] ?? null;

            if ($couponId || !empty($couponCode)) {
                $coupon = $couponId
                    ? \App\Models\Coupon::find((int) $couponId)
                    : \App\Models\Coupon::where('code', strtoupper(trim((string) $couponCode)))->first();

                if ($coupon) {
                    $orderData['coupon_id'] = $coupon->id;
                    $currentDiscount = (float) ($orderData['discount'] ?? 0);

                    if ($currentDiscount <= 0) {
                        $couponService = app(\App\Services\Marketing\CouponService::class);
                        $calculatedDiscount = $couponService->calculateDiscountValue(
                            $coupon,
                            (float) ($orderData['subtotal'] ?? 0),
                            (float) ($orderData['delivery_fee'] ?? 0),
                            (float) ($orderData['service_fee'] ?? 0)
                        );
                        $orderData['discount'] = round($calculatedDiscount, 2);
                        $orderData['total'] = max(0, round(
                            (float) ($orderData['subtotal'] ?? 0)
                            + (float) ($orderData['delivery_fee'] ?? 0)
                            + (float) ($orderData['service_fee'] ?? 0)
                            - $orderData['discount'],
                            2
                        ));
                    }

                    if (!empty($orderData['payment_intent_methods']) && is_array($orderData['payment_intent_methods']) && count($orderData['payment_intent_methods']) === 1) {
                        $orderData['payment_intent_methods'][0]['amount'] = (float) $orderData['total'];
                    }

                    $coupon->increment('usage_count');
                }
            }

            // Processa promessa de Cashback Fidelidade (internal_loyalty) vinda do PDV
            $posCashbackUsed = 0.0;
            if (!empty($orderData['payment_intent_methods']) && is_array($orderData['payment_intent_methods'])) {
                $loyaltyService = app(\App\Services\Marketing\LoyaltyService::class);
                $customerForLoyalty = !empty($orderData['customer_id']) ? \App\Models\Customer::find((int) $orderData['customer_id']) : null;

                foreach ($orderData['payment_intent_methods'] as $idx => $promise) {
                    $pMethod = !empty($promise['method_id']) ? \App\Models\PaymentMethod::find((int) $promise['method_id']) : null;
                    $pEngine = is_object($pMethod?->engine_type) ? $pMethod->engine_type->value : ($pMethod?->engine_type ?? ($promise['engine_type'] ?? null));

                    if ($pEngine === \App\Enums\PaymentEngineType::INTERNAL_LOYALTY->value) {
                        $requestedCb = (float) ($promise['amount'] ?? 0);
                        if ($requestedCb > 0) {
                            if (!$customerForLoyalty) {
                                throw new Exception("Identifique o cliente antes de utilizar Cashback Fidelidade como forma de pagamento.");
                            }
                            $validatedCb = $loyaltyService->validateCashback($customerForLoyalty, (float) ($orderData['subtotal'] ?? 0), $requestedCb);
                            if ($validatedCb <= 0) {
                                throw new Exception("O cliente não possui saldo de cashback disponível para este pedido.");
                            }
                            $orderData['payment_intent_methods'][$idx]['amount'] = round($validatedCb, 2);
                            $orderData['payment_intent_methods'][$idx]['engine_type'] = \App\Enums\PaymentEngineType::INTERNAL_LOYALTY->value;
                            $posCashbackUsed = round($posCashbackUsed + $validatedCb, 2);
                        }
                    }
                }
            }

            if ($posCashbackUsed > 0) {
                $orderData['cashback_used'] = $posCashbackUsed;
            }

            $orderData['cash_shift_id'] = $shift?->id;
            $order = Order::create($orderData);

            foreach ($payload["items"] as $item) {
                $this->itemProcessor->process($order, $item);
            }

            if ($posCashbackUsed > 0 && $order->customer) {
                app(\App\Services\Marketing\LoyaltyService::class)->consumeCashback($order, $order->customer, $posCashbackUsed);
            }

            return $order;
        });

        // 🚀 3. EFEITOS COLATERAIS (KDS, Impressão e WebSockets)
        $isBankApiPending = ($routing['payment_engine'] ?? null) === 'bank_api'
            || ($routing['payment_intent_status'] ?? null) === 'pix_waiting';

        if (!$isBankApiPending) {
            $this->kitchenDispatchService->dispatchNewItems(
                $order,
                $routing['kitchen_print_mode'] ?? 'station',
                (bool) ($routing['kitchen_separate_multiples'] ?? false)
            );

            $this->notifyProductionTeam($order);
        // 🚀 Notificação WebPush para o Cliente no balcão/telefone
        if ($order->customer) {
            try {
                if ($order->customer->user) {
                    $order->customer->user->notify(new OrderStatusUpdated($order, "Seu pedido #{$order->id} foi lançado e já está confirmado!"));
                } else {
                    $order->customer->notify(new OrderStatusUpdated($order, "Seu pedido #{$order->id} foi lançado e já está confirmado!"));
                }
            } catch (\Throwable $e) {
                Log::error("Falha ao notificar cliente via push no pedido #{$order->id}: " . $e->getMessage());
            }
        }

        }

        try {
            broadcast(new OrderCreated($order));
            broadcast(new OrderUpdated($order));
        } catch (\Throwable $e) {
            Log::error("🚨 [WebSockets] Falha ao transmitir evento do pedido #{$order->id}: " . $e->getMessage());
        }

        return $order;
    }

    protected function resolveSource(array $payload): string
    {
        $rawSource = $payload["source"] ?? ($payload["routing"]["source"] ?? null);

        if (in_array($rawSource, Order::VALID_SOURCES, true)) {
            return $rawSource;
        }
        if (request()->is('team*') || request()->routeIs('team.*')) {
            return Order::SOURCE_TEAM_APP;
        }
        if (request()->is('pos*') || request()->routeIs('pos.*')) {
            return Order::SOURCE_POS_APP;
        }

        $detect = new MobileDetect();
        return ($detect->isMobile() || $detect->isTablet())
            ? Order::SOURCE_TEAM_APP
            : Order::SOURCE_POS_APP;
    }

    private function notifyProductionTeam(Order $order): void
    {
        $attendanceService = app(\App\Services\RestaurantOps\ShiftAttendanceService::class);
        $order->loadMissing('items.product');

        $activeStations = $order->items->pluck('product.production_station')->filter()->unique();
        $notifiedBar = false;
        $notifiedKitchen = false;

        foreach ($activeStations as $station) {
            $stationLower = strtolower($station);
            if (!$notifiedBar && (str_contains($stationLower, 'bar') || $stationLower === 'bebidas')) {
                $notifiedBar = true;
                $barStaff = $attendanceService->getActiveShiftStaffByRoles(['bar', 'bartender', 'copa']);
                if ($barStaff->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($barStaff, new \App\Notifications\OrderStatusUpdated($order, "🍹 Preparo de Bar solicitado para o Pedido #{$order->id}!"));
                }
            }

            if (!$notifiedKitchen && (str_contains($stationLower, 'cozinha') || $stationLower === 'quente')) {
                $notifiedKitchen = true;
                $kitchenStaff = $attendanceService->getActiveShiftStaffByRoles(['cozinha', 'cook', 'chef', 'kds']);
                if ($kitchenStaff->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($kitchenStaff, new \App\Notifications\OrderStatusUpdated($order, "🍳 Novos pratos na tela do KDS - Pedido #{$order->id}!"));
                }
            }
        }
    }
}
