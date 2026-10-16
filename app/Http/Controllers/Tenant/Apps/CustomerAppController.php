<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DiningTable;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Services\Catalog\CustomerCatalogService;
use App\Services\Customers\CustomerDashboardService;
use App\Services\RestaurantOps\StoreAvailabilityService;
use App\Services\RestaurantOps\StoreProfileService;

class CustomerAppController extends Controller
{
    /**
     * 🚀 Rota de Entrada via QR Code da Mesa
     * Exemplo: /mesa/a8b9c0d
     */
    public function resolveTableQrCode($hash, Request $request)
    {
        $settings = CompanySetting::getCached();

        if (!$settings || empty($settings->allow_table_self_service)) {
            return redirect()->route('customer.app')->with('error', 'O autoatendimento na mesa está desativado no momento.');
        }

        $table = DiningTable::where('hash', $hash)->first();

        if (!$table) {
            return redirect()->route('customer.app')->with('error', 'Mesa não encontrada.');
        }

        // Verifica se a mesa tem algum pedido ativo (está aberta)
        $hasActiveOrders = Order::where('dining_table_id', $table->id)
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->exists();

        if (!$hasActiveOrders) {
            // Mesa fechada/livre: bloqueia e avisa
            return redirect()->route('customer.app')->with('info', "Bem-vindo à {$table->name}! Aguarde o garçom iniciar o seu atendimento para começar a pedir.");
        }

        // Mesa ativa: salva na sessão e redireciona pro app
        session()->put('table_mode', [
            'id' => $table->id,
            'hash' => $hash,
            'name' => $table->name
        ]);

        return redirect()->route('customer.app', ['tab' => 'menu']);
    }

    public function index(
        Request $request,
        CustomerCatalogService $catalogService,
        CustomerDashboardService $dashboardService,
        StoreAvailabilityService $availabilityService,
        StoreProfileService $profileService
    ) {
        $tab = $request->route('tab') ?: 'menu';
        $baseTab = explode('/', $tab)[0];

        $isOfficial = auth()->check();
        $isGuest = session()->has('guest_customer_id');
        $isAnonymous = !$isOfficial && !$isGuest;

        $tableMode = session('table_mode');

        // Se estiver no Modo Mesa e não for um usuário logado oficialmente, força o modal de login
        if ($tableMode && !$isOfficial) {
            if (!$request->has('open_login')) {
                return redirect()->route('customer.app', ['tab' => 'menu', 'open_login' => true])
                    ->with('info', "Você está na {$tableMode['name']}. Faça login para acessar o cardápio e fazer pedidos.");
            }
        }

        $restrictedForAnonymous = ['home', 'profile', 'orders', 'wallet', 'checkout'];

        if ($isAnonymous && in_array($baseTab, $restrictedForAnonymous)) {
            return redirect()->route('customer.app', ['tab' => 'menu'])
                             ->with('info', 'Para acessar esta área, inicie um pedido ou faça login.');
        }

        $dashboardData = $dashboardService->getDashboardPayload(
            auth()->user(),
            session('guest_customer_id'),
            session('guest_addresses', [])
        );

        $posActiveStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'dispatched', 'pending_payment'];

        if (!empty($dashboardData['myActiveOrders'])) {
            $dashboardData['myActiveOrders'] = collect($dashboardData['myActiveOrders'])
                ->filter(fn ($order) => in_array(data_get($order, 'status'), $posActiveStatuses))
                ->values()
                ->all();
        }

        $catalogData = $catalogService->getCatalog();

        $editOrderData = $request->has('append_to_order')
            ? $dashboardService->getOrderForEditing((int) $request->query('append_to_order'))
            : null;

        return Inertia::render("Customer/CustomerApp", array_merge($dashboardData, [
            "isGuest"           => $isGuest,
            "isAnonymous"       => $isAnonymous,
            "initialCategories" => $catalogData['categories'],
            "initialProducts"   => $catalogData['products'],
            "paymentMethods"    => $dashboardService->getDeliveryPaymentMethods(),
            "storeStatus"       => $availabilityService->getStoreStatus(),
            "publicSettings"    => $profileService->getPublicSettings(),
            "editOrderData"     => $editOrderData,
            "tableMode"         => $tableMode,
        ]))->toResponse($request)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function callWaiter(Request $request)
{
    $tableMode = session('table_mode');

    if (!$tableMode) {
        return response()->json(['success' => false, 'message' => 'Nenhuma mesa ativa nesta sessão.'], 400);
    }

    $table = \App\Models\DiningTable::find($tableMode['id']);
    if (!$table) {
        return response()->json(['success' => false, 'message' => 'Mesa não encontrada.'], 404);
    }

    $user = auth()->user();
    $customerName = $user?->customer?->name ?? $user?->name ?? 'Cliente da Mesa';
    $tenantId = function_exists('tenant') && tenant() ? tenant('id') : 'central';

    // 1. Transmite alerta instantâneo via WebSocket para o POS e TeamApp
    broadcast(new \App\Events\WaiterCalled($table, $customerName, $tenantId));

    // 2. Notifica a equipa em turno ativo
    $attendanceService = app(\App\Services\RestaurantOps\ShiftAttendanceService::class);
    $staff = $attendanceService->getActiveShiftStaffByRoles(['pos', 'waiter', 'garcom', 'caixa', 'admin', 'manager']);

    foreach ($staff as $employee) {
        $msg = "🔔 A {$table->name} está a solicitar atendimento ({$customerName})!";
        \Illuminate\Support\Facades\Notification::send($employee, new \App\Notifications\OrderStatusUpdated(
            \App\Models\Order::where('dining_table_id', $table->id)->latest()->first() ?? new \App\Models\Order(),
            $msg
        ));
    }

    return response()->json([
        'success' => true,
        'message' => "Chamada enviada com sucesso! Um atendente virá à {$table->name}."
    ]);
}
}
