<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\CashShift;
use Exception;
use App\Services\Orders\Handlers\OrderAppendHandler;
use App\Services\Orders\Handlers\TableOrderHandler;
use App\Services\Orders\Handlers\CounterOrderHandler;
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
            // Regra de Ouro: Pedidos via PDV/Garçom exigem turno aberto
            $shift = CashShift::where('status', 'open')->latest()->first();
            if (!$shift) {
                throw new Exception("Nenhum turno de caixa aberto. Abra o caixa antes de registrar pedidos no PDV.");
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
            } elseif ($orderType === 'counter') {
                $orderData = $this->counterHandler->buildOrderData($payload, $operatorId, $source);
            } else {
                $orderData = $this->deliveryHandler->buildOrderData($payload, $operatorId, $source);
            }

            $orderData['cash_shift_id'] = $shift->id;
            $order = Order::create($orderData);

            foreach ($payload["items"] as $item) {
                $this->itemProcessor->process($order, $item);
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

        foreach ($activeStations as $station) {
            $stationLower = strtolower($station);
            if (str_contains($stationLower, 'bar') || $stationLower === 'bebidas') {
                $barStaff = $attendanceService->getActiveShiftStaffByRoles(['bar', 'bartender', 'copa']);
                if ($barStaff->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($barStaff, new \App\Notifications\OrderStatusUpdated($order, "🍹 Preparo de Bar solicitado para o Pedido #{$order->id}!"));
                }
            }

            if (str_contains($stationLower, 'cozinha') || $stationLower === 'quente') {
                $kitchenStaff = $attendanceService->getActiveShiftStaffByRoles(['cozinha', 'cook', 'chef', 'kds']);
                if ($kitchenStaff->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($kitchenStaff, new \App\Notifications\OrderStatusUpdated($order, "🍳 Novos pratos na tela do KDS - Pedido #{$order->id}!"));
                }
            }
        }
    }
}
