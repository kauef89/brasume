This file is a merged representation of a subset of the codebase, containing specifically included files and files not matching ignore patterns, combined into a single document by Repomix.

# File Summary

## Purpose
This file contains a packed representation of a subset of the repository's contents that is considered the most important context.
It is designed to be easily consumable by AI systems for analysis, code review,
or other automated processes.

## File Format
The content is organized as follows:
1. This summary section
2. Repository information
3. Directory structure
4. Repository files (if enabled)
5. Multiple file entries, each consisting of:
  a. A header with the file path (## File: path/to/file)
  b. The full contents of the file in a code block

## Usage Guidelines
- This file should be treated as read-only. Any changes should be made to the
  original repository files, not this packed version.
- When processing this file, use the file path to distinguish
  between different files in the repository.
- Be aware that this file may contain sensitive information. Handle it with
  the same level of security as you would the original repository.

## Notes
- Some files may have been excluded based on .gitignore rules and Repomix's configuration
- Binary files are not included in this packed representation. Please refer to the Repository Structure section for a complete list of file paths, including binary files
- Only files matching these patterns are included: app/Http/Controllers/**/*
- Files matching these patterns are excluded: vendor/**, node_modules/**, public/**, storage/**, bootstrap/cache/**, database/migrations/**, database/factories/**, tests/**, .git/**, *.lock, package-lock.json, composer.lock, repomix*.xml
- Files matching patterns in .gitignore are excluded
- Files matching default ignore patterns are excluded
- Files are sorted by Git change count (files with more changes are at the bottom)

# Directory Structure
```
app/
  Http/
    Controllers/
      Api/
        WhatsAppWebhookController.php
      Tenant/
        Apps/
          CustomerAppController.php
          KdsAppController.php
          PosAppController.php
          TeamAppController.php
          TeamDispatcherController.php
        Auth/
          LogoutController.php
          PinAuthController.php
          QrAuthController.php
          RemoteUnlockController.php
          SocialLoginReceiverController.php
          TeamGatewayController.php
          TwoFactorController.php
        Catalog/
          AvailabilityController.php
          TopSellingController.php
        Customers/
          CustomerController.php
          OrderRatingController.php
          StreetController.php
        Financial/
          SicrediWebhookController.php
        Fiscal/
          FiscalDocumentController.php
          LegacyOrderPrintController.php
        Marketing/
          CouponController.php
          CrmController.php
        Operations/
          InventoryMovementController.php
          ProcurementController.php
          ProductionController.php
          ScheduleController.php
          TableController.php
          WmsRestockController.php
        Orders/
          CheckoutFiscalController.php
          CheckoutPaymentController.php
          OrderAppendController.php
          OrderBillController.php
          OrderClaimController.php
          OrderController.php
          OrderCreationController.php
          OrderLogisticsController.php
          OrderPrintController.php
          OrderStatusController.php
          OrderTransferController.php
        RestaurantOps/
          BridgeApiController.php
          CashierController.php
          ChecklistApiController.php
          EmployeeWalletController.php
          KdsProductionController.php
          PrintQueueController.php
          ShiftAttendanceController.php
        Terminals/
          CustomerCheckoutController.php
          CustomerGuestController.php
          CustomerLoginController.php
          CustomerRegistrationController.php
          CustomerReorderController.php
          PosOrderEntryController.php
          PosSettingsController.php
          WhatsAppAuthController.php
        DynamicManifestController.php
        MediaServeController.php
        PredictiveMatrixController.php
        PushSubscriptionController.php
        QrCodeDownloadController.php
      Controller.php
      FacebookController.php
      GoogleController.php
```

# Files

## File: app/Http/Controllers/Api/WhatsAppWebhookController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Validação do Webhook (GET) e Recepção de Eventos (POST).
     */
    public function handle(Request $request)
    {
        // 1. Handshake de Verificação da Meta (GET)
        if ($request->isMethod('get')) {
            $verifyToken = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN');

            // O PHP converte pontos em query strings para underlines (hub.mode vira hub_mode)
            $mode = $request->query('hub_mode', $request->query('hub.mode'));
            $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
            $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

            if ($mode === 'subscribe' && $token === $verifyToken) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            return response('Token inválido', 403);
        }

        // 2. Recepção de Status / Mensagens (POST)
        if ($request->isMethod('post')) {
            $payload = $request->all();

            // Log para debug durante homologação
            Log::info('WhatsApp Webhook Recebido:', $payload);

            return response()->json(['status' => 'EVENT_RECEIVED'], 200);
        }

        return response('Método não permitido', 405);
    }
}
```

## File: app/Http/Controllers/Tenant/Apps/CustomerAppController.php
```php
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
```

## File: app/Http/Controllers/Tenant/Apps/KdsAppController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Services\RestaurantOps\KdsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KdsAppController extends Controller
{
    public function index(Request $request, KdsService $kdsService, $kitchen_id = 'all'): Response
    {
        return Inertia::render("Kds/KdsApp", [
            'kitchens'        => Kitchen::where('is_active', true)->get(),
            'activeKitchenId' => $kitchen_id,
            'initialItems'    => $kdsService->getItems($kitchen_id),
        ]);
    }
}
```

## File: app/Http/Controllers/Tenant/Apps/TeamAppController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\RestaurantOps\ShiftAttendanceService;
use App\Services\RestaurantOps\OperationsMenuService;
use App\Services\RestaurantOps\EmployeeWalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamAppController extends Controller
{
    public function index(
        Request $request,
        OperationsMenuService $menuService,
        OperationsDashboardService $opsService,
        ShiftAttendanceService $attendanceService,
        EmployeeWalletService $walletService
    ): Response {
        $user = auth()->user();

        // 🚀 Identifica se é motoboy
        $isDriver = false;
        if ($user) {
            $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [$user->role ?? ''];
            $isDriver = in_array('driver', $userRoles, true) || in_array('motoboy', $userRoles, true);
        }

        // Se a rota não especificou aba, motoristas abrem em 'driver'; os demais em 'menu'
        $defaultTabFallback = $isDriver ? 'driver' : 'menu';
        $activeTab = $request->route('tab', $defaultTabFallback);

        $data = $menuService->getOrderEntryData($request);
        $data['defaultTab'] = $activeTab;
        $data['isDriver'] = $isDriver;
        $data['isOnDuty'] = fn () => $user ? $attendanceService->checkIsOnDuty($user) : false;
        $data['initialOrders'] = fn () => $opsService->getActiveOrdersDashboard()['initialOrders'];
        $data['managerData'] = fn () => $opsService->getManagerShiftSummary($user);

        // Carteira corporativa
        $data['employeeWalletData'] = fn () => ($user && $user->employee_id)
            ? $walletService->getWalletDashboardData($user->employee_id)
            : ['balance' => 0];

        $data['catalog'] = fn () => Product::with(['category', 'storageLocations'])
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        return Inertia::render("Team/TeamApp", $data);
    }
}
```

## File: app/Http/Controllers/Tenant/Apps/TeamDispatcherController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Services\RestaurantOps\ShiftAttendanceService;
use Detection\MobileDetect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamDispatcherController extends Controller
{
    /**
     * O Guarda de Trânsito: detecta o dispositivo/função e despacha para o aplicativo correto.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $queryParams = $request->query();

        // 1. Perfil Cozinha: despacha para o KDS
        if ($user && $user->hasRole('kitchen')) {
            return redirect()->route('kds.index', $queryParams);
        }

        $detect = new MobileDetect();

        // 2. Dispositivo Móvel / Tablet: despacha para o TeamApp Mobile
        if ($detect->isMobile() || $detect->isTablet()) {
            $routeParams = $queryParams;
            if ($request->has('tab')) {
                $routeParams['tab'] = $request->get('tab');
            }
            return redirect()->route('team.mobile', $routeParams);
        }

        // 3. Desktop / Terminal de Caixa: despacha para o PosApp (sem redundância /pos/pos)
        $tab = $request->get('tab');
        $routeParams = $queryParams;
        if (!empty($tab) && !in_array($tab, ['pos', 'new'], true)) {
            $routeParams['tab'] = $tab;
        }

        return redirect()->route('pos.index', $routeParams);
    }

    public function startShift(Request $request, ShiftAttendanceService $attendanceService): RedirectResponse
    {
        try {
            $attendanceService->startUserShift(auth()->user());
            return back()->with('success', 'Turno iniciado com sucesso! Boas vendas.');
        } catch (\Exception $e) {
            return back()->withErrors(['shift' => $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Auth/LogoutController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Str;

class LogoutController extends Controller
{
    /**
     * Destrói a sessão de qualquer tipo de usuário (Cliente, KDS ou Equipe).
     */
    public function destroy(Request $request)
    {
        $isTeam = Auth::guard('team')->check();
        $previousUrl = url()->previous();

        // 1. O colaborador (Caixa, KDS, Garçom/Equipe) está saindo?
        if ($isTeam) {
            Auth::guard('team')->logout();
        }

        // 2. O cliente B2C (Cardápio) ou Admin está saindo?
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        // 3. Remove identificadores voláteis de visitantes
        $request->session()->forget([
            'guest_customer_id',
            'guest_addresses',
            'social_registration'
        ]);

        // 4. Invalida a sessão inteira e regenera o token CSRF
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 5. Redirecionamento inteligente com base no terminal de origem
        $redirectUrl = '/customer/home';

        if ($isTeam) {
            if (Str::contains($previousUrl, ['/pos', '/caixa', '/pdv'])) {
                $redirectUrl = '/pos/login';
            } elseif (Str::contains($previousUrl, ['/team/app', '/team/mobile', '/waiter', '/garcom'])) {
                $redirectUrl = '/waiter/login';
            } elseif (Str::contains($previousUrl, ['/kds', '/cozinha'])) {
                $redirectUrl = '/kds/login';
            } else {
                $redirectUrl = '/team/login';
            }
        }

        return Inertia::location($redirectUrl);
    }
}
```

## File: app/Http/Controllers/Tenant/Auth/PinAuthController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\Auth\AuthService;
use App\Models\User;

class PinAuthController extends Controller
{
    public function login(Request $request, AuthService $authService)
    {
        $request->validate(['pin' => 'required|string|min:4']);

        $email = trim($request->email ?? '');
        $pin = trim($request->pin ?? '');

        try {
            if ($request->filled('email')) {
                $user = User::where('email', $email)->first();

                if (!$user || !$user->is_active) {
                    return back()->withErrors(['pin' => 'Colaborador não localizado ou inativo.']);
                }

                // Validação estrita: exige que a coluna contenha uma hash segura
                if (!Hash::check($pin, $user->pin)) {
                    return back()->withErrors(['pin' => 'PIN incorreto. Tente novamente.']);
                }

                Auth::guard('web')->login($user, true);
                Auth::guard('team')->login($user, true);
            } else {
                $authService->loginWithPin($pin);
            }

            $request->session()->regenerate();
            return redirect()->intended('/team');

        } catch (\Exception $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }
    }

    public function loginWithEmail(Request $request, AuthService $authService)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string'
        ]);

        try {
            $authService->loginWithEmail($credentials['email'], $credentials['password']);
            $request->session()->regenerate();

            return redirect()->intended('/team');
        } catch (\Exception $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Auth/QrAuthController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\QrAuthService;

class QrAuthController extends Controller
{
    public function authorizeLogin(Request $request, QrAuthService $qrService)
    {
        Log::info('--- 📲 INICIANDO AUTORIZAÇÃO DE QR CODE (MOBILE) ---');

        $userTeam = auth('team')->user();
        $userWeb  = auth('web')->user();
        $user     = $userTeam ?? $userWeb ?? auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Sessão expirada. Faça login novamente no aplicativo.'], 401);
        }

        try {
            $token = $request->input('token') ?? $request->input('uuid');

            if (!$token) {
                return response()->json(['error' => 'Código do QR Code inválido ou em formato incorreto.'], 422);
            }

            $qrService->authorizeTerminal($token, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Terminal autorizado com sucesso!'
            ]);

        } catch (\Exception $e) {
            Log::error('🔥 ERRO CRÍTICO no authorizeLogin: ' . $e->getMessage());
            return response()->json(['error' => 'Falha interna ao autorizar terminal.'], 500);
        }
    }

    public function exchangeToken(Request $request, QrAuthService $qrService)
    {
        $request->validate([
            'device_uuid' => 'required|string',
            'token'       => 'required|string',
        ]);

        try {
            $userId = $qrService->exchangeToken($request->device_uuid, $request->token);

            if ($userId) {
                auth('team')->loginUsingId($userId);
                $request->session()->regenerate();

                return response()->json([
                    'success'  => true,
                    'redirect' => '/team' // 🚀 CORRIGIDO AQUI!
                ]);
            }

            return response()->json(['success' => false, 'status' => 'pending']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function validateDevice(Request $request, \App\Services\Auth\QrAuthService $qrService)
        {
            \Illuminate\Support\Facades\Log::info('📡 [Sonda QR Validate] Recebendo pedido de validação. UUID enviado pelo App: ' . $request->device_uuid);

            $request->validate(['device_uuid' => 'required|string']);

            try {
                $device = $qrService->validateAndTouchDevice($request->device_uuid);

                \Illuminate\Support\Facades\Log::info('✅ [Sonda QR Validate] Máquina encontrada: ' . $device->name);

                return response()->json([
                    'valid' => true,
                    'device' => [
                        'name' => $device->name,
                        'type' => $device->type,
                        'uuid' => $device->device_uuid,
                    ]
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('❌ [Sonda QR Validate] Falha na validação: ' . $e->getMessage());
                return response()->json(['valid' => false, 'error' => $e->getMessage()], $e->getCode() ?: 400);
            }
        }
}
```

## File: app/Http/Controllers/Tenant/Auth/RemoteUnlockController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\RemoteUnlockService;

class RemoteUnlockController extends Controller
{
    public function __construct(
        protected RemoteUnlockService $unlockService
    ) {}

    public function fleet()
    {
        return response()->json($this->unlockService->getDeviceFleet());
    }

    public function requestUnlock(Request $request)
    {
        $request->validate(['device_uuid' => 'required|string']);
        $this->unlockService->setDeviceWaiting($request->device_uuid);
        return response()->json(['success' => true]);
    }

    public function registerQrSession(Request $request)
    {
        $request->validate([
            'device_uuid' => 'required|string',
            'session_token' => 'required|string'
        ]);

        \Illuminate\Support\Facades\Cache::put(
            "qr_session_rev_{$request->session_token}",
            $request->device_uuid,
            65
        );

        return response()->json(['success' => true]);
    }

    public function handleQuickUnlockScan(Request $request, string $token)
    {
        $user = auth('team')->user() ?? auth('web')->user();

        if (!$user || !$user->hasAnyRole(['admin', 'gerente', 'manager', 'pos', 'kitchen'])) {
            // 🚀 Atualizado para a nova rota do WaiterApp
            return redirect()->route('waiter.index', ['tab' => 'menu'])
                             ->withErrors(['acesso' => 'Você não possui permissão para autorizar terminais.']);
        }

        $deviceUuid = \Illuminate\Support\Facades\Cache::get("qr_session_rev_{$token}");

        if (!$deviceUuid) {
            return redirect()->route('waiter.index', ['tab' => 'menu'])
                             ->withErrors(['acesso' => 'QR Code expirado por segurança. Escaneie novamente.']);
        }

        return redirect()->route('waiter.index', [
            'tab' => 'menu',
            'quick_unlock_token' => $deviceUuid,
        ]);
    }

    public function approve(Request $request)
    {
        try {
            $request->validate(['device_uuid' => 'required|string']);
            $user = auth('team')->user() ?? auth()->user();

            $this->unlockService->approveUnlock($user, $request->device_uuid);

            if ($request->wantsJson() || $request->ajax()) return response()->json(['success' => true]);
            return back()->with('success', 'Terminal liberado com sucesso!');

        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) return response()->json(['error' => $e->getMessage()], 400);
            return back()->with('error', $e->getMessage());
        }
    }

    public function exchange(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        try {
            $userId = $this->unlockService->exchangeToken($request->token);
            auth('team')->loginUsingId($userId);
            $request->session()->regenerate();

            if ($request->wantsJson()) return response()->json(['success' => true]);
            return back()->with('success', 'Acesso liberado.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) return response()->json(['error' => $e->getMessage()], 403);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function lock(Request $request)
    {
        $request->validate(['device_uuid' => 'required|string']);
        $this->unlockService->lockDevice($request->device_uuid);
        return response()->json(['success' => true]);
    }
}
```

## File: app/Http/Controllers/Tenant/Auth/SocialLoginReceiverController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Services\Customers\CustomerRegistrationService;
use Exception;

class SocialLoginReceiverController extends Controller
{
    public function magicLogin(Request $request, CustomerRegistrationService $registrationService)
    {
        $encryptedToken = $request->query('token');

        try {
            $payload = json_decode(Crypt::decryptString($encryptedToken), true);

            if (now()->timestamp > $payload['expires_at']) {
                throw new Exception('O link de login expirou. Tente novamente.');
            }

            // Delega para o motor de unificação de clientes
            $guestId = session('guest_customer_id');
            $result = $registrationService->evaluateSocialLogin($payload, $guestId);

            $targetUrl = $payload['return_to'] ?? '/';
            if (empty($targetUrl) || str_contains($targetUrl, '/auth/')) {
                $targetUrl = '/';
            }

            // Se o cliente ainda não vinculou CPF (Âncora necessária para o ecossistema)
            if ($result['status'] === 'requires_cpf') {
                $targetUrl = preg_replace('/([?&])open_upgrade=[^&]+(&|$)/', '$1', $targetUrl);
                $targetUrl = rtrim($targetUrl, '?&');
                $separator = str_contains($targetUrl, '?') ? '&' : '?';
                $targetUrl .= $separator . 'open_upgrade=true';

                return redirect()->to($targetUrl)->with('info', $result['message']);
            }

            if ($result['status'] === 'authenticated') {
                Auth::login($result['user'], true);
                $request->session()->regenerate();
            }

            return redirect()->to($targetUrl)->with('success', $result['message']);

        } catch (Exception $e) {
            Log::error('Falha no Magic Login Social: ' . $e->getMessage());
            return redirect()->to('/')->with('error', $e->getMessage());
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Auth/TeamGatewayController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Services\RestaurantOps\StoreAvailabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamGatewayController extends Controller
{
    public function show(Request $request, StoreAvailabilityService $availabilityService): Response|\Illuminate\Http\RedirectResponse
    {
        $portal = $request->segment(1); // 'pos', 'waiter', 'kds', ou 'team'

        // Se já está logado, joga para o Guarda de Trânsito (/team) para rotear corretamente
        if (auth('team')->check()) {
            return redirect()->route('team.index');
        }

        if (session()->has('guest_customer_id')) {
            session()->forget('guest_customer_id');
            return redirect($request->getRequestUri());
        }

        // 🚀 Define o layout/comportamento da tela de login
        $mode = match($portal) {
            'pos' => 'desktop',
            'waiter' => 'mobile',
            default => null
        };

        $activeShift = CashShift::where('status', 'open')->latest()->first();
        $authorizedStaff = [];

        if ($activeShift) {
            $authorizedStaff = $activeShift->attendees()
                ->wherePivot('attendance_status', 'present')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email
                ]);
        }

        return Inertia::render('Gateway/TeamGateway', [
            'portal' => $portal, // Informa ao Vue se é pos, waiter ou kds
            'mode' => $mode,
            'authorizedStaff' => $authorizedStaff,
            'storeStatus' => $availabilityService->getStoreStatus(),
        ]);
    }
}
```

## File: app/Http/Controllers/Tenant/Auth/TwoFactorController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Auth\TwoFactorService;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    /**
     * Exibe o ecrã apropriado: Leitura do QR Code (Setup) ou Apenas Código (Challenge)
     */
    public function showChallenge()
    {
        $user = auth()->user();

        // CENÁRIO 1: Configuração Inédita
        if (empty($user->two_factor_secret)) {
            $setupData = $this->twoFactorService->getSetupData($user);

            return Inertia::render('Auth/TwoFactorSetup', [
                'qrCodeUrl' => $setupData['qrCodeUrl'],
                'secret' => $setupData['secret']
            ]);
        }

        // CENÁRIO 2: Desafio de Rotina
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Recebe o código de 6 dígitos do Vue e processa a libertação.
     */
    public function verifyChallenge(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $isValid = $this->twoFactorService->verifyAndSave(auth()->user(), $request->code);

        if ($isValid) {
            // Marca a sessão como duplamente segura
            session(['2fa_verified' => true]);

            // Redireciona para o painel de gestão ou origem
            return redirect()->intended('/admin');
        }

        return back()->withErrors(['code' => 'O código de 6 dígitos está incorreto ou expirou.']);
    }
}
```

## File: app/Http/Controllers/Tenant/Catalog/AvailabilityController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\AvailabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AvailabilityController extends Controller
{
    public function __construct(protected AvailabilityService $availabilityService) {}

    public function index()
    {
        $catalog = Product::with(['category' => function ($q) {
                $q->active();
            }])
            ->active()
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        return Inertia::render('Operations/Availability', [
            'catalog' => $catalog,
        ]);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'action'     => 'required|in:available,paused,out_of_stock',
            'new_stock'  => 'nullable|numeric|min:0',
        ]);

        $this->availabilityService->updateAvailability(
            (int) $data['product_id'],
            $data['action'],
            isset($data['new_stock']) ? (float) $data['new_stock'] : null
        );

        return back()->with('success', 'Disponibilidade atualizada!');
    }

    public function registerLoss(Request $request)
    {
        $data = $request->validate([
            'target_id' => 'required|exists:products,id',
            'quantity'  => 'required|numeric|min:0.001',
            'reason'    => 'required|string|max:255',
        ]);

        $this->availabilityService->registerLoss($data, (int) auth()->id());

        return back()->with('success', 'Perda registrada com sucesso!');
    }
}
```

## File: app/Http/Controllers/Tenant/Catalog/TopSellingController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Services\Catalog\TopSellingCombinationsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TopSellingController extends Controller
{
    public function index(TopSellingCombinationsService $topSellingService): JsonResponse
    {
        try {
            $combinations = Cache::store('file')->remember('tenant_top_combinations', 3600, function () use ($topSellingService) {
                return $topSellingService->getTopCombinations(10);
            });

            return response()->json(['data' => $combinations]);
        } catch (\Throwable $e) {
            Log::error('Erro ao consultar combinações mais vendidas: ' . $e->getMessage());

            return response()->json([
                'error' => 'Ocorreu um erro ao carregar os dados do catálogo.'
            ], 500);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Customers/CustomerController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Customers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Address;
use App\Services\Customers\CustomerRegistrationService;
use App\Services\Customers\CustomerProfileService;
use App\Services\Customers\CustomerValidationService;
use App\Services\Customers\CustomerDashboardService;
use App\Services\Customers\SerproService;
use App\Services\Customers\CustomerFavoritesService;

class CustomerController extends Controller
{
    public function search(Request $request, CustomerProfileService $profileService)
    {
        if (!$request->query('q')) return response()->json([]);
        return response()->json($profileService->searchCustomers($request->query('q')));
    }

    public function checkCpf(Request $request, CustomerValidationService $validationService, SerproService $serpro)
    {
        $cpf = preg_replace('/[^0-9]/', '', $request->query('cpf'));

        if (strlen($cpf) !== 11) {
            return response()->json(['available' => false, 'message' => 'CPF com formato inválido.'], 400);
        }

        return response()->json($validationService->checkCpfAvailability($cpf, $serpro));
    }

    public function store(Request $request, CustomerRegistrationService $registrationService)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'phone'      => 'required|string|max:20',
            'cpf'        => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'email'      => 'nullable|email|max:255',
        ]);

        $customer = $registrationService->createCustomer($validated);

        return back()->with([
            'success'         => 'Cliente cadastrado com sucesso!',
            'createdCustomer' => ($customer instanceof Customer) ? $customer->loadMissing('addresses') : $customer,
        ]);
    }

    public function storeAddress(CustomerProfileService $profileService, Request $request, ?Customer $customer = null)
    {
        // 🚀 Resolve o cliente a partir do parâmetro de rota (B2B) ou da sessão autenticada (B2C)
        $targetCustomer = ($customer && $customer->exists)
            ? $customer
            : (auth('web')->user()?->customer ?? auth()->user()?->customer);

        if (!$targetCustomer && session()->has('guest_customer_id')) {
            $targetCustomer = Customer::find(session('guest_customer_id'));
        }

        if (!$targetCustomer) {
            return back()->withErrors(['address' => 'Sessão inválida. Identifique-se novamente.']);
        }

        $validated = $request->validate([
            'nickname'        => 'nullable|string|max:50',
            'street'          => 'required|string|max:255',
            'street_id'       => 'nullable|integer',
            'number'          => 'required|string|max:20',
            'noNumber'        => 'nullable|boolean',
            'no_number'       => 'nullable|boolean',
            'neighborhood'    => 'required|string|max:255',
            'neighborhood_id' => 'nullable|integer',
            'zipcode'         => 'nullable|string|max:15',
            'complement'      => 'nullable|string|max:255',
            'reference'       => 'nullable|string|max:255',
        ]);

        $validated['nickname'] = $validated['nickname'] ?? 'Casa';
        $validated['noNumber'] = $validated['noNumber'] ?? $validated['no_number'] ?? false;

        $address = $profileService->addAddress($targetCustomer, $validated);

        return back()->with([
            'success'        => 'Endereço cadastrado com sucesso!',
            'createdAddress' => $address->loadMissing(['street', 'neighborhood']),
        ]);
    }

    public function deactivateAddress(Request $request, CustomerProfileService $profileService, ?Address $address = null)
    {
        $customer = auth()->user()?->customer;
        if (!$customer && session()->has('guest_customer_id')) {
            $customer = Customer::find(session('guest_customer_id'));
        }

        if (!$customer) {
            return back()->withErrors(['address' => 'Sessão inválida. Identifique-se novamente.']);
        }

        $targetAddress = ($address && $address->exists) ? $address : null;

        if (!$targetAddress) {
            $addressId = $request->route('address')
                ?? $request->input('address_id')
                ?? $request->input('id')
                ?? $request->input('address');

            $targetAddress = Address::find($addressId);
        }

        if (!$targetAddress) {
            return back()->withErrors(['address' => 'Endereço não encontrado.']);
        }

        try {
            $profileService->deactivateAddress($customer, $targetAddress);

            if (session()->has('guest_addresses')) {
                $guestAddresses = array_values(array_diff(session('guest_addresses', []), [$targetAddress->id]));
                session()->put('guest_addresses', $guestAddresses);
            }

            return back()->with('success', 'Endereço removido com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['address' => $e->getMessage()]);
        }
    }

    public function updateProfile(Request $request, CustomerProfileService $profileService)
    {
        $validated = $request->validate([
            'name'       => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
        ]);

        try {
            $profileService->updateCustomerProfile($validated, auth()->user(), session('guest_customer_id'));
            return back()->with('success', 'Os seus dados foram atualizados com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function favorites(Customer $customer, CustomerFavoritesService $favoritesService)
    {
        $loggedUser = null;
        $isTeamMember = false;

        try {
            if (auth('team')->check()) {
                $loggedUser = auth('team')->user();
                $isTeamMember = true;
            }
        } catch (\Exception $e) {}

        if (!$loggedUser && auth()->check()) {
            $loggedUser = auth()->user();
        }

        if (!$loggedUser) {
            return response()->json(['message' => 'Não autorizado. Faça login.'], 401);
        }

        $isTheCustomer = false;

        if (!$isTeamMember) {
            $isTheCustomer = ($loggedUser->id === $customer->id) || ($loggedUser->id === $customer->user_id);
        }

        if (!$isTeamMember && !$isTheCustomer) {
            return response()->json(['message' => 'Acesso negado a este perfil.'], 403);
        }

        $favorites = $favoritesService->getFavorites($customer);

        return response()->json([
            'data' => $favorites
        ]);
    }

    public function deleteAccount(Request $request, CustomerRegistrationService $registrationService)
    {
        $registrationService->deleteCustomerAccount(auth()->user(), session('guest_customer_id'));

        // Resolve a dependência e executa a destruição da sessão de forma interna
        return app(\App\Http\Controllers\Tenant\Auth\LogoutController::class)->destroy($request);
    }

    public function storeGuestAddress(Request $request, CustomerProfileService $profileService)
    {
        $guestId = session('guest_customer_id');

        if (!$guestId) {
            return redirect()->back()->withErrors(['address' => 'Sessão inválida. Identifique-se novamente.']);
        }

        $data = $request->validate([
            'street'          => 'required|string',
            'street_id'       => 'nullable|integer',
            'number'          => 'required|string',
            'neighborhood'    => 'required|string',
            'neighborhood_id' => 'nullable|integer',
            'complement'      => 'nullable|string',
            'nickname'        => 'nullable|string',
        ]);

        try {
            $address = $profileService->addAddress($guestId, $data);
            session()->push('guest_addresses', $address->id);

            return redirect()->back()->with('success', 'Endereço adicionado com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['address' => 'Erro ao salvar o endereço.']);
        }
    }

    public function toggleOpeningNotification(Request $request)
    {
        $request->validate(['wants_notification' => 'required|boolean']);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $user->update(['wants_opening_notification' => $request->wants_notification]);

        return response()->json(['success' => true]);
    }
}
```

## File: app/Http/Controllers/Tenant/Customers/OrderRatingController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Customers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderRatingController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        // 🔒 1. Exige autenticação oficial do cliente
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Você precisa estar logado para avaliar este pedido.'
            ], 401);
        }

        // 🔍 2. Localiza o perfil de cliente vinculado ao usuário
        $customer = $user->customer;

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil de cliente não localizado para esta conta.'
            ], 403);
        }

        // 🛡️ 3. Validação estrita de posse por ID
        if ((int) $order->customer_id !== (int) $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido não pertence à sua conta cadastrada.'
            ], 403);
        }

        // 📦 4. Regra de negócio: Apenas pedidos concluídos/entregues podem ser avaliados
        if (!in_array($order->status, ['delivered', 'completed', 'closed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas pedidos já entregues ou finalizados podem ser avaliados.'
            ], 422);
        }

        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'tags'    => ['nullable', 'array'],
            'tags.*'  => ['string', 'max:50'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $orderRating = OrderRating::updateOrCreate(
            ['order_id' => $order->id],
            [
                'customer_id' => $customer->id,
                'rating'      => $validated['rating'],
                'tags'        => $validated['tags'] ?? [],
                'comment'     => $validated['comment'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Avaliação registrada com sucesso!',
            'data'    => $orderRating,
        ]);
    }
}
```

## File: app/Http/Controllers/Tenant/Customers/StreetController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Customers;

use App\Http\Controllers\Controller;
use App\Models\Street;
use Illuminate\Http\Request;
use Exception;

class StreetController extends Controller
{
    /**
     * Retorna todas as ruas do Tenant mapeando suas faixas de numeração e bairros
     */
    public function index()
    {
        try {
            // 🚀 Buscamos a rua carregando o relacionamento do Repeater (segments) e do Select (neighborhood)
            return Street::with('segments.neighborhood')
                ->get()
                ->map(function ($street) {
                    return [
                        'id' => $street->id,
                        'name' => $street->name,
                        'segments' => $street->segments->map(function ($seg) {
                            return [
                                'start' => (int) $seg->start_number,
                                'end' => $seg->end_number ? (int) $seg->end_number : null,
                                'neighborhood' => $seg->neighborhood->name ?? 'Sem Bairro'
                            ];
                        })->values()->toArray()
                    ];
                });
        } catch (Exception $e) {
            return response()->json([
                'dev_error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Financial/SicrediWebhookController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Financial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\Orders\OrderPaymentService;
use App\Services\Financial\Gateways\SicrediPixGateway;

class SicrediWebhookController extends Controller
{
    public function handlePix(Request $request, OrderPaymentService $paymentService, SicrediPixGateway $gateway)
    {
        $payload = $request->all();

        // 🚀 SONDA 1: O banco chegou a bater na nossa porta?
        Log::info('📍 [SONDA 1] Webhook Sicredi Recebido. Payload:', $payload);

        if (!$gateway->validateWebhookToken($request->query('token', ''))) {
            Log::warning('🚨 [SONDA 2] Falha na validação do Token do Webhook!', [
                'ip' => $request->ip(),
                'token_recebido' => $request->query('token')
            ]);
            return response()->json(['message' => 'Acesso Negado'], 403);
        }

        Log::info('📍 [SONDA 3] Token de segurança validado com sucesso.');

        if (!isset($payload['pix']) || !is_array($payload['pix'])) {
            Log::warning('🚨 [SONDA 4] Payload recebido não contém o array "pix". Ignorando.');
            return response()->json(['message' => 'Payload ignorado'], 400);
        }

        foreach ($payload['pix'] as $pix) {
            if (empty($pix['txid'])) {
                Log::warning('🚨 [SONDA 5] Transação veio sem TXID.', $pix);
                continue;
            }

            Log::info("📍 [SONDA 6] Iniciando processamento do TXID: {$pix['txid']}");

            // Trava atômica
            $lock = Cache::lock("webhook_pix_processing_{$pix['txid']}", 300);

            if ($lock->get()) {
                Log::info("📍 [SONDA 7] Lock adquirido! Enviando para o OrderPaymentService...");
                try {
                    $paymentService->processWebhookPayment($pix['txid'], $pix['valor'], $pix['endToEndId'] ?? null);
                    Log::info("📍 [SONDA 12] Sucesso absoluto! O TXID {$pix['txid']} foi processado até o fim.");
                } catch (\Exception $e) {
                    Log::error("🚨 [SONDA ERRO] Falha ao processar TXID {$pix['txid']}: " . $e->getMessage() . " | Linha: " . $e->getLine());
                } finally {
                    $lock->release();
                }
            } else {
                Log::warning("🚨 [SONDA 7 - BLOCK] TXID {$pix['txid']} ignorado. Já está em processamento concorrente.");
            }
        }

        return response()->json(['message' => 'Webhook recebido com sucesso'], 200);
    }
}
```

## File: app/Http/Controllers/Tenant/Fiscal/FiscalDocumentController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\LegacyOrder;
use App\Services\Fiscal\SpedDocumentService;

class FiscalDocumentController extends Controller
{
    /**
     * Faz o download do PDF (DANFE) de um pedido legado
     */
    public function downloadLegacyDanfe($id, SpedDocumentService $spedService)
    {
        $order = LegacyOrder::findOrFail($id);

        if (empty($order->xml_content)) {
            abort(404, "XML não encontrado para este pedido.");
        }

        try {
            // Delega a geração pesada do PDF para o Service
            $pdfContent = $spedService->renderDanfePdf($order->xml_content);

            // Força o navegador a fazer o download
            return response()->streamDownload(
                function () use ($pdfContent) {
                    echo $pdfContent;
                },
                "DANFE_{$order->nfe_key}.pdf",
                [
                    "Content-Type" => "application/pdf",
                ],
            );
        } catch (\Exception $e) {
            abort(500, "Erro ao gerar o PDF da DANFE: " . $e->getMessage());
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Fiscal/LegacyOrderPrintController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\LegacyOrder;
use App\Services\Fiscal\SpedDocumentService;

class LegacyOrderPrintController extends Controller
{
    public function print($id, SpedDocumentService $spedService)
    {
        $order = LegacyOrder::findOrFail($id);

        if (empty($order->xml_content)) {
            return back()->with('error', 'O conteúdo do XML não foi encontrado para este pedido legado.');
        }

        try {
            $pdf = $spedService->renderPdfFromXml($order->xml_content);

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="DANFE_' . $order->nfe_key . '.pdf"');

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao gerar PDF: ' . $e->getMessage());
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Marketing/CouponController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Marketing\CouponService;

class CouponController extends Controller
{
    public function validateCode(Request $request, CouponService $couponService)
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            'is_pos' => 'nullable|boolean',
            'customer_id' => 'nullable|integer',
        ]);

        $customerId = $request->customer_id ?? auth()->user()?->customer_id ?? session('guest_customer_id');
        $isPos = $request->boolean('is_pos');

        $result = $couponService->applyCoupon(
            $request->code,
            (float) $request->subtotal,
            $customerId ? (int) $customerId : null,
            $isPos
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json($result);
    }

    public function getWallet(CouponService $couponService)
    {
        $customerId = auth()->user()?->customer_id ?? session('guest_customer_id');

        return response()->json([
            'coupons' => $couponService->getWalletForCustomer($customerId ? (int) $customerId : null)
        ]);
    }

    /**
     * Retorna a lista de cupons para o modal do PDV
     */
    public function getPosAvailableCoupons(Request $request, CouponService $couponService)
    {
        $subtotal = (float) $request->query('subtotal', 0);
        $customerId = $request->query('customer_id') ? (int) $request->query('customer_id') : null;

        return response()->json([
            'coupons' => $couponService->getAvailableCouponsForPos($subtotal, $customerId)
        ]);
    }
}
```

## File: app/Http/Controllers/Tenant/Marketing/CrmController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Marketing\CrmService;

class CrmController extends Controller
{
    public function __construct(
        protected CrmService $crmService
    ) {}

    public function toggleAutomation(Request $request)
    {
        $request->validate([
            'key' => 'required|string|in:auto_birthday_coupon,auto_churn_coupon',
            'value' => 'required|boolean'
        ]);

        $this->crmService->toggleAutomation($request->key, $request->value);

        return back();
    }

    public function createCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|string|in:percent,fixed,free_delivery',
            'value' => 'required_unless:type,free_delivery|numeric|min:0',
            'expires_at' => 'nullable|date'
        ]);

        $this->crmService->createCoupon($data);

        return back();
    }
}
```

## File: app/Http/Controllers/Tenant/Operations/InventoryMovementController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\InventoryLoss;
use App\Events\CatalogUpdated;
use App\Services\Catalog\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryMovementController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function adjust(Request $request, Product $product)
    {
        $validated = $request->validate([
            'new_stock' => 'required|numeric|min:0',
            'reason'    => 'required|string|max:255',
        ]);

        $this->inventoryService->adjustStock(
            $product,
            (float) $validated['new_stock'],
            $validated['reason'],
            auth()->id()
        );

        return back()->with('success', 'Estoque ajustado com sucesso!');
    }

    public function batchAdjust(Request $request)
    {
        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.location_id'   => 'nullable|exists:storage_locations,id',
            'items.*.counted_stock' => 'required|numeric|min:0',
            'reason'                => 'nullable|string|max:255',
        ]);

        $userId = auth()->id();
        $reason = $validated['reason'] ?? 'Balanço Físico Operacional (TeamApp)';
        $lossesCount = 0;
        $totalLossAmount = 0.0;

        DB::transaction(function () use ($validated, $userId, $reason, &$lossesCount, &$totalLossAmount) {
            foreach ($validated['items'] as $entry) {
                $product = Product::find($entry['product_id']);
                if (!$product || !$product->track_inventory) continue;

                $locationId = !empty($entry['location_id']) ? (int) $entry['location_id'] : null;
                $newLocationStock = (float) $entry['counted_stock'];

                if ($locationId) {
                    $pivot = DB::table('product_storage_location')
                        ->where('product_id', $product->id)
                        ->where('storage_location_id', $locationId)
                        ->first();

                    $oldLocationStock = $pivot ? (float) $pivot->current_stock : 0.0;
                    $locationDelta = round($newLocationStock - $oldLocationStock, 4);

                    DB::table('product_storage_location')->updateOrInsert(
                        ['product_id' => $product->id, 'storage_location_id' => $locationId],
                        ['current_stock' => $newLocationStock, 'updated_at' => now()]
                    );

                    $sumLocations = (float) DB::table('product_storage_location')
                        ->where('product_id', $product->id)
                        ->sum('current_stock');

                    $loc = StorageLocation::find($locationId);
                    $locName = $loc?->name ?? "Local #{$locationId}";

                    // 🚀 KARDEX RECORD
                    $this->inventoryService->logMovement(
                        product: $product,
                        type: 'adjustment',
                        quantity: $locationDelta,
                        newBalance: $sumLocations,
                        description: "Balanço Mobile ({$locName}): {$reason}",
                        locationId: $locationId
                    );

                    $product->update(['current_stock' => $sumLocations]);

                    if ($locationDelta < 0) {
                        $lossQty = abs($locationDelta);

                        $lossRecord = $this->inventoryService->registerLoss(
                            product: $product,
                            qty: $lossQty,
                            reason: "Furo de Balanço: {$locName}",
                            notes: "Contagem física: {$newLocationStock} {$product->unit_of_measure} (Anterior: {$oldLocationStock}). Motivo: {$reason}",
                            userId: $userId,
                            orderItemId: null,
                            lossType: InventoryLoss::TYPE_INVENTORY_AUDIT,
                            storageLocationId: $locationId,
                            skipDeduction: true // 🚀 Impede dedução dupla
                        );

                        $lossesCount++;
                        $totalLossAmount += (float) $lossRecord->total_cost;
                    }
                } else {
                    $oldStock = (float) $product->current_stock;
                    $delta = round($newLocationStock - $oldStock, 4);

                    $this->inventoryService->adjustStock(
                        $product,
                        $newLocationStock,
                        $reason,
                        $userId
                    );

                    if ($delta < 0) {
                        $lossesCount++;
                    }
                }

                $freshStock = (float) $product->fresh()->current_stock;
                if ($freshStock <= 0) {
                    if ($product->status === 'available') {
                        $this->inventoryService->triggerStockRupture($product);
                    }
                } else {
                    if ($product->status === 'out_of_stock') {
                        $this->inventoryService->triggerStockRestored($product);
                    }
                }
            }
        });

        broadcast(new CatalogUpdated(tenant('id') ?? 'central'));

        $msg = "Balanço salvo com sucesso!";
        if ($lossesCount > 0) {
            $msg .= " ({$lossesCount} divergências de quebra registradas em auditoria).";
        }

        return back()->with('success', $msg);
    }
}
```

## File: app/Http/Controllers/Tenant/Operations/ProcurementController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ShoppingListItem;
use App\Services\SupplyChain\ProcurementService;

class ProcurementController extends Controller
{
    public function index(ProcurementService $procurementService)
    {
        $smartMatrix = $procurementService->getSmartBiddingMatrix();

        return Inertia::render('Operations/SupplyChain/ShoppingList', [
            'biddingMatrix' => $smartMatrix,
        ]);
    }

    public function removeItem(Request $request)
    {
        $request->validate(['id' => 'required|exists:shopping_list_items,id']);

        // Mantém o histórico, apenas cancela a intenção
        ShoppingListItem::find($request->id)->update(['status' => 'cancelled']);

        return back()->with('success', 'Item removido da lista de intenções.');
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:shopping_list_items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
        ]);

        foreach ($data['items'] as $itemData) {
            ShoppingListItem::where('id', $itemData['id'])->update([
                'status' => 'ordered',
                'approved_qty' => $itemData['qty']
            ]);
        }

        return back()->with('success', 'Pedido fechado e enviado ao fornecedor!');
    }
}
```

## File: app/Http/Controllers/Tenant/Operations/ProductionController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProductionOrder;
use App\Services\RestaurantOps\ProductionService;

class ProductionController extends Controller
{
    public function __construct(
        protected ProductionService $productionService
    ) {}

    public function index()
    {
        $productionOrders = ProductionOrder::with('product')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('due_date', 'asc')
            ->get();

        return Inertia::render('Operations/SupplyChain/ProductionBoard', [
            'orders' => $productionOrders,
        ]);
    }

    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:production_orders,id',
            'status' => 'required|in:pending,in_progress,completed,cancelled'
        ]);

        try {
            $this->productionService->updateStatus($data['id'], $data['status']);
            return back()->with('success', 'Status atualizado com sucesso.');
        } catch (\Exception $e) {
            return back()->withErrors(['production' => 'Erro ao atualizar: ' . $e->getMessage()]);
        }
    }

    public function finish(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:production_orders,id',
            'actual_qty' => 'required|numeric|min:0'
        ]);

        try {
            // 🚀 Delega toda a regra de estoque e WebSockets para o Service
            $this->productionService->finishOrder($data['id'], $data['actual_qty']);

            return back()->with('success', 'Lote concluído! Estoque reabastecido.');
        } catch (\Exception $e) {
            return back()->withErrors(['production' => 'Erro ao finalizar lote: ' . $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Operations/ScheduleController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Services\RestaurantOps\ScheduleService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function batchSave(Request $request, ScheduleService $scheduleService)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'schedules' => 'present|array',
            'slots' => 'nullable|array'
        ]);

        $scheduleService->saveWeeklySchedule(
            $validated['start_date'],
            $validated['schedules'],
            $validated['slots'] ?? null
        );

        return redirect()->to('/pos/schedules?start_date=' . $validated['start_date'])
            ->with('success', 'Escala semanal e slots salvos com sucesso!');
    }

    public function cloneWeek(Request $request, ScheduleService $scheduleService)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
        ]);

        $scheduleService->clonePreviousWeek($validated['start_date']);

        return redirect()->to('/pos/schedules?start_date=' . $validated['start_date'])
            ->with('success', 'Escala da semana anterior clonada com sucesso!');
    }
}
```

## File: app/Http/Controllers/Tenant/Operations/TableController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Services\RestaurantOps\TablesService;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function __construct(protected TablesService $tablesService) {}

    public function updateStatus(Request $request, DiningTable $table)
    {
        $validated = $request->validate([
            // Modificado para 'available'
            'status' => 'required|string|in:available,occupied,reserved,cleaning,maintenance',
        ]);

        $this->tablesService->updateStatus($table, $validated['status']);

        $message = "Status da mesa {$table->name} alterado com sucesso.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function move(Request $request, DiningTable $table)
    {
        $validated = $request->validate([
            'target_table_id' => 'required|exists:dining_tables,id',
        ]);

        $targetTable = $this->tablesService->move($table, (int) $validated['target_table_id']);

        $message = "Comanda transferida da {$table->name} para {$targetTable->name}.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function join(Request $request, DiningTable $table)
    {
        $validated = $request->validate([
            'secondary_table_ids'   => 'required|array|min:1',
            'secondary_table_ids.*' => 'required|integer|exists:dining_tables,id|different:table',
        ]);

        $this->tablesService->join($table, $validated['secondary_table_ids']);

        $message = "Mesas agrupadas na {$table->name} com sucesso.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function split(Request $request, DiningTable $table)
    {
        $this->tablesService->split($table);

        $message = "Mesa {$table->name} desmembrada com sucesso.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
```

## File: app/Http/Controllers/Tenant/Operations/WmsRestockController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Services\RestaurantOps\WmsRestockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WmsRestockController extends Controller
{
    protected WmsRestockService $wmsService;

    public function __construct(WmsRestockService $wmsService)
    {
        $this->wmsService = $wmsService;
    }

    public function index(Request $request)
    {
        // 🚀 Chama o método correto do WmsRestockService
        $restockBoard = $this->wmsService->getRestockBoard();

        if ($request->hasHeader('X-Inertia')) {
            return inertia('Operations/RestockBoard', [
                'restockData' => $restockBoard
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $restockBoard
        ]);
    }

    public function updateStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_id'     => 'required|integer|exists:product_storage_location,id',
            'added_stock' => 'required|numeric|min:0.01',
        ]);

        // 🚀 Chama o addStockToShelf e recebe a Model atualizada
        $rule = $this->wmsService->addStockToShelf($validated['rule_id'], (float) $validated['added_stock']);

        return response()->json([
            'success' => true,
            'message' => 'Reposição confirmada!',
            'data'    => [
                'rule_id'       => $rule->id,
                'current_stock' => (float) $rule->current_stock,
                'total_stock'   => (float) ($rule->product?->current_stock ?? 0),
            ]
        ]);
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/CheckoutFiscalController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\OrderPrintService;
use App\Events\OrderUpdated;
use Throwable;

class CheckoutFiscalController extends Controller
{
    public function printConference(Order $order, OrderPrintService $printService)
    {
        try {
            $printService->printConferenceBill($order);
            return back()->with('success', 'Conta enviada para a impressora!');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function emitNfce(Order $order, Request $request)
    {
        $data = $request->validate([
            'document' => 'nullable|string'
        ]);

        $settings = CompanySetting::first();

        if (!$settings || !$settings->is_fiscal_emitter) {
            $msg = 'Empresa não está configurada como emissora fiscal.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        // 🚀 GUARDA: Se já possui NFC-e autorizada, retorna sucesso imediatamente
        if ($order->fiscalDocument && $order->fiscalDocument->status === 'authorized') {
            $msg = "NFC-e do pedido #{$order->id} já está emitida e autorizada (NF nº {$order->fiscalDocument->number})!";
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                ]);
            }
            return back()->with('success', $msg);
        }

        try {
            $spedService = app(\App\Services\Fiscal\SpedDocumentService::class);
            $spedService->emitNfce($order, $data['document'] ?? null, false);

            $order->load('fiscalDocument');

            if (!$request->wantsJson()) {
                try {
                    broadcast(new OrderUpdated($order));
                } catch (\Throwable $e) {}
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "NFC-e do pedido #{$order->id} autorizada com sucesso na SEFAZ!"
                ]);
            }

            return back()->with('success', 'NFC-e autorizada com sucesso na Sefaz!');
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function finalizeReceipt(Order $order, Request $request, OrderPrintService $printService)
    {
        $data = $request->validate([
            'print_action' => 'required|string',
        ]);

        $printService->dispatchCheckoutReceipts($order, $data['print_action']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comandos enviados à impressora!'
            ]);
        }

        return back()->with('success', 'Comandos enviados à impressora!');
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/CheckoutPaymentController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Events\OrderUpdated;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderPaymentService;
use App\Services\Marketing\CouponService;
use App\Services\RestaurantOps\TablesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckoutPaymentController extends Controller
{
    public function processPayment(Order $order, Request $request, OrderPaymentService $paymentService, CouponService $couponService)
    {
        // 🚀 Sanitiza a lista de itens removendo qualquer item com quantidade zerada ou não selecionado
        if ($request->has('items') && is_array($request->input('items'))) {
            $filteredItems = array_values(array_filter($request->input('items'), function ($item) {
                return isset($item['pay_qty']) && (float) $item['pay_qty'] > 0;
            }));
            $request->merge(['items' => $filteredItems]);
        }

        $data = $request->validate([
            'payments'             => 'required|array|min:1',
            'payments.*.method_id' => 'required',
            'payments.*.amount'    => 'required|numeric|gt:0',
            'items'                => 'nullable|array',
            'items.*.id'           => 'required_with:items|integer',
            'items.*.pay_qty'      => 'required_with:items|numeric|gt:0',
            'items.*.amount'       => 'nullable|numeric',
            'service_fee'          => 'nullable|numeric',
            'cashback_used'        => 'nullable|numeric',
            'coupon_code'          => 'nullable|string',
        ]);

        // 🚀 Aplica o cupom de desconto se ele vier na requisição
        if (!empty($data['coupon_code'])) {
            $baseTotal = (float) $order->subtotal + (float) $order->delivery_fee;

            $couponResult = $couponService->applyCoupon(
                $data['coupon_code'],
                $baseTotal,
                $order->customer_id,
                true // isPos
            );

            if ($couponResult['success']) {
                $coupon = \App\Models\Coupon::find($couponResult['coupon_id']);

                // Calcula quanto de desconto esse cupom gera em dinheiro
                $discountValue = $couponService->calculateDiscountValue(
                    $coupon,
                    (float) $order->subtotal,
                    (float) $order->delivery_fee,
                    isset($data['service_fee']) ? (float) $data['service_fee'] : (float) $order->service_fee
                );

                $order->coupon_id = $coupon->id;
                $order->discount = round($discountValue, 2);
                $order->save();

                // Incrementa a contagem de uso do cupom globalmente
                $coupon->increment('usage_count');
            }
        }

        try {
            $paymentService->processPayment($order, $data);

            $order->refresh();

            // 🚀 Hidratação completa dos pagamentos e itens anexados
            $order->load([
                'items.modifiers',
                'payments.items.modifiers',
                'payments.transaction',
                'customer',
                'diningTable'
            ]);

            $shouldClose = ($order->status === 'dispatched' && $order->payment_status === 'paid')
                || ($order->type === 'counter' && in_array($order->status, ['ready', 'dispatched']) && $order->payment_status === 'paid')
                || ($order->type === 'table' && $order->payment_status === 'paid');

            if ($shouldClose) {
                $order->status = 'closed';
                if (true) {
                    $order->closed_at = now();
                }
                $order->save();

                if ($order->type === 'table') {
                    $table = $order->diningTable
                        ?? ($order->dining_table_id ? DiningTable::find($order->dining_table_id) : null)
                        ?? (!empty($order->table_number) ? DiningTable::where('name', $order->table_number)->first() : null);

                    if ($table) {
                        // Verifica se ainda existem pedidos pendentes em qualquer mesa do grupo
                        $hasOtherActiveOrders = $table->hasActiveOrdersInGroup($order->id);

                        if (!$hasOtherActiveOrders) {
                            $primaryTable = $table->parent_table_id ? $table->parentTable : $table;
                            if ($primaryTable) {
                                // O split já atualiza os status para 'available' e faz o broadcast via afterCommit
                                app(TablesService::class)->split($primaryTable);
                            }
                        }
                    }
                }

                broadcast(new OrderUpdated($order->fresh()));
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pagamento processado com sucesso!',
                    'order'   => $order,
                ]);
            }

            return redirect()->back()->with('success', 'Pagamento processado!');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroyPayment(Order $order, Payment $payment, Request $request)
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Este pagamento não pertence ao pedido.'], 403);
            }
            abort(403, 'Este pagamento não pertence ao pedido.');
        }

        DB::transaction(function () use ($payment, $order) {
            $payment->loadMissing('transaction');
            if ($payment->transaction) {
                $payment->transaction->delete();
            }

            $payment->delete();

            if (method_exists($order, 'recalculatePaymentStatus')) {
                $order->recalculatePaymentStatus();
            } else {
                $totalPaid = (float) $order->payments()->sum('amount');
                $totalOrder = (float) $order->total;

                if ($totalPaid <= 0) {
                    $order->update(['payment_status' => 'pending']);
                } elseif ($totalPaid < ($totalOrder - 0.01)) {
                    $order->update(['payment_status' => 'partial']);
                } else {
                    $order->update(['payment_status' => 'paid']);
                }
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pagamento estornado com sucesso.'
            ]);
        }

        return back()->with('success', 'Pagamento estornado com sucesso.');
    }

    public function generatePix(Order $order, Request $request, \App\Services\Financial\Gateways\SicrediPixGateway $pixGateway)
    {
        $settings = \App\Models\CompanySetting::getCached() ?? \App\Models\CompanySetting::first();

        if (!$settings || !$settings->sicredi_is_active) {
            return response()->json([
                'success' => false,
                'message' => 'A integração PIX Sicredi não está ativa nas configurações.'
            ], 422);
        }

        // Calcula saldo devedor real abatendo pagamentos já parciais
        $alreadyPaid = (float) $order->payments()->sum('amount');
        $balanceDue = max(0, round((float) $order->total - $alreadyPaid, 2));

        if ($balanceDue <= 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido já está quitado.'
            ], 422);
        }

        try {
            $pixData = $pixGateway->generatePayment($order, $balanceDue);

            $order->update([
                'txid' => $pixData['txid'],
                'payment_intent_status' => 'pending_pix',
            ]);

            broadcast(new OrderUpdated($order->fresh()));

            return response()->json([
                'success'  => true,
                'txid'     => $pixData['txid'],
                'qr_code'  => $pixData['qr_code'],
                'amount'   => $balanceDue,
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("🚨 [SicrediPix] Erro ao gerar PIX para pedido #{$order->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Falha ao comunicar com o Sicredi: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderBillController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\PosCheckoutService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderBillController extends Controller
{
    public function __construct(protected PosCheckoutService $checkoutService) {}

    public function show(Request $request, Order $order)
    {
        $payload = $this->checkoutService->getCheckoutData($order);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return Inertia::render('Pos/Checkout', $payload);
    }

    public function quickAdd(Request $request, Order $order)
    {
        $itemId = (int) $request->input('item_id');
        $shouldPrint = $request->boolean('should_print', false);

        $newItem = $this->checkoutService->quickAdd($order, $itemId, $shouldPrint);

        return back()->with('success', "Item {$newItem->name} duplicado (+1).");
    }

    public function addItem(Request $request, Order $order)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'nullable|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'notes'      => 'nullable|string',
        ]);

        $item = $this->checkoutService->addItem($order, $validated);

        return back()->with('success', "Item {$item->name} adicionado ao pedido.");
    }

    public function cancelItem(Request $request, Order $order, ?OrderItem $item = null)
    {
        $itemId = $item ? $item->id : (int) $request->input('item_id');
        $qty = $request->input('qty', 'all');
        $reason = $request->input('reason', 'Cancelado no checkout');

        $result = $this->checkoutService->cancelItem($order, $itemId, $qty, $reason);

        return back()->with(
            'success',
            $result['is_partial']
                ? "{$result['cancelled_qty']} unidade(s) cancelada(s) com sucesso."
                : 'Item cancelado com sucesso.'
        );
    }

    public function applyDiscount(Request $request, Order $order)
    {
        $validated = $request->validate([
            'discount' => 'required|numeric|min:0',
            'reason'   => 'nullable|string',
        ]);

        $this->checkoutService->applyDiscount($order, (float) $validated['discount']);

        return back()->with('success', 'Desconto aplicado.');
    }

    public function removeDiscount(Order $order)
    {
        $this->checkoutService->removeDiscount($order);

        return back()->with('success', 'Desconto removido.');
    }

    public function toggleServiceFee(Request $request, Order $order)
    {
        $this->checkoutService->toggleServiceFee($order, $request->boolean('enabled'));

        return back();
    }

    public function linkCustomer(Request $request, Order $order)
    {
        $validated = $request->validate([
            'customer_id'   => 'nullable|integer',
            'customer_name' => 'nullable|string',
        ]);

        $this->checkoutService->linkCustomer(
            $order,
            $validated['customer_id'] ?? null,
            $validated['customer_name'] ?? null
        );

        return back()->with('success', 'Cliente vinculado ao pedido.');
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderClaimController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Marketing\OrderClaimService;
use Exception;

class OrderClaimController extends Controller
{
    public function __construct(
        protected OrderClaimService $claimService
    ) {}

    /**
     * Ponto de entrada do QR Code: /resgate/{hash}
     */
    public function handleScan(string $hash)
    {
        $hash = strtoupper(trim($hash));
        $user = auth()->user();

        // 1. Se o cliente já estiver autenticado e com perfil Customer criado
        if ($user && $user->customer) {
            try {
                $order = $this->claimService->claimOrder($hash, $user->customer);
                $cashbackFormatted = number_format($order->cashback_earned, 2, ',', '.');

                return redirect()->route('customer.app', ['tab' => 'wallet'])
                    ->with('success', "🎉 Incrível! Você resgatou R$ {$cashbackFormatted} de Cashback do Pedido #{$order->id}!");
            } catch (Exception $e) {
                return redirect()->route('customer.app', ['tab' => 'wallet'])
                    ->with('error', $e->getMessage());
            }
        }

        // 2. Se for um cliente visitante / não autenticado:
        // Guarda a intenção de resgate na sessão e abre o app solicitando identificação
        session(['pending_claim_hash' => $hash]);

        return redirect()->route('customer.app', [
            'claim' => $hash,
            'tab'   => 'account',
        ]);
    }

    /**
     * Processamento da pendência após login ou cadastro no CustomerApp
     */
    public function processPendingClaim(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->customer) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $hash = $request->input('hash') ?: session('pending_claim_hash');
        if (!$hash) {
            return response()->json(['success' => false, 'message' => 'Nenhum comprovante pendente.'], 400);
        }

        try {
            $order = $this->claimService->claimOrder($hash, $user->customer);
            session()->forget('pending_claim_hash');

            return response()->json([
                'success'         => true,
                'message'         => "Parabéns! R$ " . number_format($order->cashback_earned, 2, ',', '.') . " creditados na sua conta!",
                'points'          => $order->loyalty_points_earned,
                'cashback_earned' => $order->cashback_earned,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\RestaurantOps\PosMenuService;
use App\Services\Orders\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Order $order, PosMenuService $posMenuService, OperationsDashboardService $operationsService): Response
    {
        $flatItems = OrderItem::withoutGlobalScopes()
            ->with(['modifiers', 'product.kitchen.printer'])
            ->where('order_id', $order->id)
            ->get();

        $order->setRelation('items', $flatItems);

        // 🚀 Carregamento ansioso do relacionamento relacional deliveryAddress
        $order->load([
            'customer.addresses.street',
            'customer.addresses.neighborhood',
            'deliveryAddress.street',
            'deliveryAddress.neighborhood',
            'payments',
            'rating',
            'diningTable.tableArea',
        ]);

        return Inertia::render('Operations/OrderDetails', [
            'order'           => $order,
            'tableAreas'      => $posMenuService->getTableAreas(),
            'companySettings' => CompanySetting::getCached(),
            'couriers'        => $operationsService->getAvailableCouriers(),
        ]);
    }

    public function toggleRating(Request $request, Order $order, OrderStatusService $statusService): RedirectResponse
    {
        $statusService->toggleRatingSuppression($order, $request->boolean('skip_rating'));

        return back();
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderLogisticsController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\Orders\OrderLogisticsService;
use Illuminate\Http\Request;

class OrderLogisticsController extends Controller
{
    public function __construct(protected OrderLogisticsService $logisticsService) {}

    public function transform(Order $order, Request $request)
    {
        $validated = $request->validate([
            'new_type'        => 'nullable|string|in:table,counter,delivery',
            'newType'         => 'nullable|string|in:table,counter,delivery',
            'type'            => 'nullable|string|in:table,counter,delivery',
            'table_id'        => 'nullable|integer|exists:dining_tables,id',
            'dining_table_id' => 'nullable|integer|exists:dining_tables,id',
            'table_number'    => 'nullable|string',
            'identifier'      => 'nullable|string',
            'customer_name'   => 'nullable|string',
        ]);

        // 🚀 Normalização canônica da Mesa
        $tableId = $validated['dining_table_id'] ?? $validated['table_id'] ?? null;

        if ($tableId) {
            $validated['dining_table_id'] = (int) $tableId;
            $validated['table_id'] = (int) $tableId;
            if (empty($validated['table_number'])) {
                $validated['table_number'] = DiningTable::find($tableId)?->name;
            }
        } elseif (!empty($validated['table_number'])) {
            $foundTable = DiningTable::where('name', $validated['table_number'])->first();
            if ($foundTable) {
                $validated['dining_table_id'] = $foundTable->id;
                $validated['table_id'] = $foundTable->id;
            }
        }

        try {
            $this->logisticsService->transformOrder($order, $validated);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'order' => $order->fresh(['diningTable'])]);
            }

            return back()->with('success', 'Pedido transformado com sucesso!');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 400);
            }

            return back()->withErrors(['transform' => $e->getMessage()]);
        }
    }

    public function assignCourier(Order $order, Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $this->logisticsService->assignCourier($order, (int) $validated['driver_id']);
            return back()->with('success', 'Entregador atribuído com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['driver_id' => $e->getMessage()]);
        }
    }

    public function changeAddress(Order $order, Request $request)
    {
        $validated = $request->validate([
            'address_id'   => 'nullable',
            'address'      => 'nullable',
            'delivery_fee' => 'nullable|numeric|min:0',
        ]);

        try {
            $rawAddress = is_array($validated['address'] ?? null) ? $validated['address'] : null;
            $fee = isset($validated['delivery_fee']) ? (float) $validated['delivery_fee'] : null;

            $this->logisticsService->changeAddress($order, $validated['address_id'] ?? null, $fee, $rawAddress);
            return back()->with('success', 'Endereço atualizado com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['address' => $e->getMessage()]);
        }
    }

    public function updateDeliveryPayment(Order $order, Request $request)
    {
        $validated = $request->validate([
            'payment_intent_status'  => 'nullable|string',
            'payment_intent_methods' => 'nullable',
            'change_target'          => 'nullable|numeric',
        ]);

        $status = $validated['payment_intent_status'] ?? $order->payment_intent_status;
        $methods = $validated['payment_intent_methods'] ?? $order->payment_intent_methods;

        if (is_string($methods)) {
            $methods = json_decode($methods, true) ?: $methods;
        }

        $order->update([
            'payment_intent_status'  => $status,
            'payment_intent_methods' => $methods,
            'change_target'          => $validated['change_target'] ?? $order->change_target,
        ]);

        broadcast(new \App\Events\OrderUpdated($order->fresh()));

        return back()->with('success', 'Forma de pagamento de entrega atualizada!');
    }

    public function bulkCompleteDispatched()
    {
        $result = $this->logisticsService->bulkCompleteDispatched();
        return back()->with('success', "{$result['processed']} pedidos finalizados com sucesso!");
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderPrintController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\OrderPrintService;

class OrderPrintController extends Controller
{
    public function printDelivery(Order $order, Request $request, OrderPrintService $printService)
    {
        $settings = CompanySetting::getCached()?->toArray() ?? [];

        $isLoyaltyActive = !empty($settings['loyalty_is_active']) && !empty($settings['print_loyalty_flyer']);
        $isGrowthActive  = !empty($settings['print_growth_flyer']);

        $defaultLoyalty = $isLoyaltyActive && (($settings['loyalty_flyer_mode'] ?? '') === 'auto');
        $defaultGrowth  = $isGrowthActive  && (($settings['growth_flyer_mode'] ?? '') === 'auto');

        $requestLoyalty = $request->boolean('loyalty', $defaultLoyalty);
        $requestGrowth  = $request->boolean('growth', $defaultGrowth);

        try {
            $printService->printDeliveryDispatch($order, $requestLoyalty, $requestGrowth);
            return back()->with('success', 'Via do entregador enviada para impressão!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderStatusController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\Orders\OrderCancellationService;

class OrderStatusController extends Controller
{
    public function update(Order $order, Request $request, OperationsDashboardService $operationsService)
    {
        $validated = $request->validate([
            'status'              => 'required|string',
            'stamp_intermediates' => 'nullable|boolean',
        ]);

        $operationsService->updateOrderStatus(
            $order,
            $validated['status'],
            $request->boolean('stamp_intermediates')
        );

        return redirect()->back()->with('success', 'Status do pedido atualizado!');
    }

    public function cancel(Order $order, Request $request, OrderCancellationService $cancellationService)
    {
        $validated = $request->validate([
            'reason'     => 'nullable|string',
            'is_loss'    => 'nullable|boolean',
            'lost_items' => 'nullable|array',
            'pin'        => 'nullable|string',
        ]);

        $cancellationService->cancelOrder($order, $validated);

        return back()->with('success', 'Pedido cancelado e estornado com sucesso.');
    }

    public function cancelItem(Order $order, Request $request, OrderCancellationService $cancellationService)
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:order_items,id',
            'qty'     => 'nullable',
            'is_loss' => 'nullable|boolean',
            'reason'  => 'nullable|string',
            'pin'     => 'nullable|string',
        ]);

        try {
            $cancellationService->cancelItem($order, $validated);

            return back()->with('success', 'Item estornado com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }
    }

    public function reactivate(Order $order, Request $request)
    {
        $request->validate([
            'pin'    => 'nullable|string',
            'reason' => 'nullable|string|min:15|max:255',
            'mode'   => 'required|string|in:reactivate,correct_payment'
        ], [
            'reason.min' => 'A justificativa para a SEFAZ deve conter no mínimo 15 caracteres.'
        ]);

        $settings = \App\Models\CompanySetting::getCached() ?? \App\Models\CompanySetting::first();
        if ($settings && $settings->require_pin_for_voids) {
            if (!$request->pin || !\App\Models\TeamMember::validatePinAuth($request->pin)) {
                return back()->withErrors(['pin' => 'PIN de Gerente inválido ou ausente.']);
            }
        }

        // Garante justificativa válida com mais de 15 caracteres para o Fisco
        $rawReason = trim((string) $request->input('reason', ''));
        $fiscalJustification = strlen($rawReason) >= 15
            ? $rawReason
            : "Reabertura e correcao operacional da conta do pedido #{$order->id}";

        $fiscalErrors = [];

        DB::transaction(function () use ($order, $request, $fiscalJustification, &$fiscalErrors) {
            $mode = $request->input('mode');

            // 🧾 TRATAMENTO FISCAL: Cancelamento de NFC-e Autorizada
            $authorizedDocs = $order->fiscalDocuments()->where('status', 'authorized')->get();

            foreach ($authorizedDocs as $doc) {
                // Checa o prazo legal de 30 minutos da NFC-e
                $minutesSinceIssue = $doc->issue_date ? $doc->issue_date->diffInMinutes(now()) : 999;

                if ($minutesSinceIssue > 30) {
                    $fiscalErrors[] = "A NFC-e #{$doc->number} foi emitida há mais de 30 minutos (limite da SEFAZ excedido). O documento não pôde ser cancelado automaticamente e precisará de estorno contábil.";
                    continue;
                }

                try {
                    app(\App\Services\Fiscal\SpedDocumentService::class)->cancelNfce($doc, $fiscalJustification);
                } catch (\Exception $e) {
                    $fiscalErrors[] = "Falha ao cancelar NFC-e #{$doc->number} na SEFAZ: " . $e->getMessage();
                }
            }

            // Se o cancelamento fiscal falhou, interrompe para não gerar inconsistência com a Receita
            if (!empty($fiscalErrors)) {
                throw new \Exception(implode("\n", $fiscalErrors));
            }

            // Se for 'correct_payment', estorna os registros financeiros para nova cobrança
            if ($mode === 'correct_payment') {
                foreach ($order->payments as $payment) {
                    if ($payment->transaction) {
                        $payment->transaction->delete();
                    }
                    $payment->delete();
                }

                foreach ($order->items as $item) {
                    $item->update(['paid_qty' => 0]);
                }

                $order->payment_status = 'pending';
            }

            // Reverte o status operacional
            $order->status = ($order->type === 'counter') ? 'ready' : 'pending';
            $order->closed_at = null;
            $order->save();

            // Reocupa a mesa física
            if ($order->type === 'table') {
                $table = $order->diningTable ?? ($order->dining_table_id ? \App\Models\DiningTable::find($order->dining_table_id) : null);
                if ($table) {
                    $table->update(['status' => 'occupied']);
                }
            }
        });

        return back()->with('success', 'Conta reativada com sucesso.');
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderTransferController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Services\Orders\OrderTransferService;
use Illuminate\Http\Request;

class OrderTransferController extends Controller
{
    public function store(Request $request, OrderTransferService $service)
    {
        $validated = $request->validate([
            'source_order_id'             => 'required|integer|exists:orders,id',
            'item_ids'                    => 'required|array',
            'destination_type'            => 'required|string|in:table,counter_delivery',
            'destination_table_id'        => 'nullable|integer|exists:dining_tables,id',
            'destination_dining_table_id' => 'nullable|integer|exists:dining_tables,id',
            'destination_table_name'      => 'nullable|string',
            'destination_order_intent'    => 'nullable|string',
            'new_subaccount_name'         => 'nullable|string',
        ]);

        // 🚀 Normalização canônica da mesa de destino
        $destTableId = $validated['destination_dining_table_id'] ?? $validated['destination_table_id'] ?? null;

        if ($destTableId && empty($validated['destination_table_name'])) {
            $validated['destination_table_name'] = DiningTable::find($destTableId)?->name;
        } elseif (!empty($validated['destination_table_name']) && !$destTableId) {
            $foundTable = DiningTable::where('name', $validated['destination_table_name'])->first();
            if ($foundTable) {
                $destTableId = $foundTable->id;
            }
        }

        if ($destTableId) {
            $validated['destination_dining_table_id'] = (int) $destTableId;
            $validated['destination_table_id'] = (int) $destTableId;
        }

        try {
            $service->execute($validated);

            if ($request->wantsJson()) return response()->json(['success' => true]);
            return back()->with('success', 'Itens transferidos com sucesso!');

        } catch (\Exception $e) {
            if ($request->wantsJson()) return response()->json(['error' => $e->getMessage()], 400);
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/RestaurantOps/BridgeApiController.php
```php
<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\RestaurantOps\BridgeService;

class BridgeApiController extends Controller
{
    public function handshake(Request $request, BridgeService $bridgeService): JsonResponse
    {
        try {
            $version = $request->input('version', '0.0.0');
            $tenantId = tenant('id') ?? 'central';

            $response = $bridgeService->processHandshake($request->header('X-Bridge-Token', ''), $version, $tenantId);

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function updateJobStatus(Request $request, $id, BridgeService $bridgeService): JsonResponse
    {
        try {
            $bridgeService->updateJob(
                $request->header('X-Bridge-Token', ''),
                $id,
                $request->input('status'),
                $request->input('error_message', null)
            );
            return response()->json(['message' => 'Recebido']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function getPrinters(BridgeService $bridgeService): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'printers' => $bridgeService->getActivePrinters()
        ]);
    }
}
```

## File: app/Http/Controllers/Tenant/RestaurantOps/CashierController.php
```php
<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CloseShiftRequest;
use App\Http\Requests\Pos\OpenShiftRequest;
use App\Models\CashShift;
use App\Models\Order;
use App\Models\Printer;
use App\Services\Printing\Documents\ShiftClosureReport;
use App\Services\Printing\PrintDispatcher;
use App\Services\RestaurantOps\ShiftAttendanceService;
use App\Services\RestaurantOps\ShiftCashierService;
use App\Services\RestaurantOps\OperationalShiftClosureService;
use Illuminate\Http\Request;

class CashierController extends Controller
{
    public function __construct(protected ShiftCashierService $cashierService) {}

    protected function getOperator()
    {
        return auth('team')->user() ?? auth('web')->user() ?? auth()->user();
    }

    public function open(OpenShiftRequest $request, ShiftAttendanceService $attendanceService)
    {
        $data = $request->validated();
        $operator = $this->getOperator();

        try {
            $shift = $this->cashierService->openShift($data, $operator?->id ?? 1, $operator?->name ?? 'Sistema');

            if (!empty($data['attendees'])) {
                $attendanceService->syncShiftAttendees($data['attendees'], $shift);
            }

            return back()->with('success', 'Caixa aberto e equipe registrada!');
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    public function close(Request $request, ShiftCashierService $cashierService, ShiftAttendanceService $attendanceService)
    {
        $shift = CashShift::where('status', 'open')->latest('opened_at')->first();

        if (!$shift) {
            return back()->withErrors(['error' => 'Nenhum turno aberto encontrado.', 'shift' => 'Nenhum turno aberto.']);
        }

        // 1. AUTO-LIMPEZA: Cancela pedidos vazios ou zerados
        $abandonedOrders = Order::whereNotIn('status', ['closed', 'cancelled'])
            ->where(function ($q) {
                $q->whereDoesntHave('items', function ($iq) {
                    $iq->where('status', '!=', 'cancelled');
                })
                ->orWhere(function ($sub) {
                    $sub->where('total', '<=', 0.01)
                        ->whereDoesntHave('payments');
                });
            })
            ->get();

        foreach ($abandonedOrders as $abandoned) {
            $abandoned->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled'
            ]);

            $tableId = $abandoned->dining_table_id;
            if ($tableId) {
                $hasOtherActive = Order::where('dining_table_id', $tableId)
                    ->where('id', '!=', $abandoned->id)
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->exists();

                if (!$hasOtherActive) {
                    \App\Models\DiningTable::where('id', $tableId)->update(['status' => 'free']);
                }
            }
        }

        // 2. VERIFICAÇÃO DE COMANDAS: Bloqueia apenas comandas pendentes de pagamento
        $blockingOrders = Order::whereNotIn('status', ['closed', 'cancelled'])
            ->whereHas('items', function ($iq) {
                $iq->where('status', '!=', 'cancelled');
            })
            ->where(function ($q) {
                $q->where('payment_status', '!=', 'paid')
                  ->orWhere('total', '>', 0.01);
            })
            ->get();

        if ($blockingOrders->isNotEmpty()) {
            $tableNumbers = $blockingOrders->where('type', 'table')->pluck('table_number')->filter()->unique()->join(', ');
            $otherCounts = $blockingOrders->where('type', '!=', 'table')->count();

            $details = [];
            if ($tableNumbers) $details[] = "Mesa(s): {$tableNumbers}";
            if ($otherCounts > 0) $details[] = "{$otherCounts} pedido(s) balcão/delivery";

            $detailMsg = !empty($details) ? ' (' . implode(' | ', $details) . ')' : '';
            $msg = "Não é possível fechar o caixa com pedidos em aberto{$detailMsg}. Finalize ou cancele as contas pendentes.";

            return back()->withErrors(['error' => $msg, 'shift' => $msg]);
        }

        // 4. Execução do fechamento
        try {
            $operator = $this->getOperator();
            $operatorId = $operator?->id ?? 1;
            $operatorName = $operator?->name ?? 'Operador';

            $cashierService->closeShift($shift, $request->all(), $operatorId, $operatorName);

            return back()->with('success', 'Caixa fechado com sucesso.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage(), 'shift' => $e->getMessage()]);
        }
    }

    public function closeOperationalShift(
        Request $request,
        OperationalShiftClosureService $operationalService
    ) {
        $closeStore = $request->boolean('close_store', true);
        $activeShift = CashShift::where('status', 'open')->latest()->first();

        try {
            $operationalService->closeShift($activeShift?->id, $closeStore);

            $msg = $closeStore
                ? 'Turno encerrado e loja fechada no app do cliente!'
                : 'Turno operacional encerrado. A loja continua aberta no app!';

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Falha ao encerrar turno: ' . $e->getMessage()]);
        }
    }

    /**
     * 🚀 SANGRIA E SUPRIMENTO (operations.cashier.transaction)
     */
    public function transaction(Request $request)
    {
        $data = $request->validate([
            'type'        => 'required|in:in,out',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'pin'         => 'nullable|string',
        ]);

        $operator = $this->getOperator();

        try {
            $this->cashierService->recordTransaction($data, $operator?->name ?? 'Operador');
            return back()->with('success', 'Movimentação registrada com sucesso!');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * 🚀 REIMPRESSÃO DO RELATÓRIO DO TURNO (operations.cashier.print-shift-report)
     */
    public function printShiftReport(CashShift $shift, PrintDispatcher $printDispatcher)
    {
        try {
            $cashierPrinter = Printer::where('is_cashier', true)->where('is_active', true)->first()
                ?? Printer::where('is_active', true)->first();

            if (!$cashierPrinter) {
                return back()->withErrors(['error' => 'Nenhuma impressora ativa encontrada para imprimir o relatório.']);
            }

            $report = new ShiftClosureReport($shift);
            $printDispatcher->dispatch($cashierPrinter, $report);

            return back()->with('success', 'Relatório enviado para a impressora!');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Falha na impressão: ' . $e->getMessage()]);
        }
    }

    /**
     * 🚀 ESTORNO E CLONAGEM (operations.orders.refund-and-clone)
     */
    public function refundAndClone(Order $order, Request $request)
    {
        try {
            foreach ($order->payments as $payment) {
                $this->cashierService->rollbackPayment($order, $payment->id);
            }

            $order->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled'
            ]);

            return back()->with('success', "Pedido #{$order->id} estornado com sucesso.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Falha ao estornar pedido: ' . $e->getMessage()]);
        }
    }

    public function rollbackPayment(Order $order, $payment, Request $request)
    {
        $paymentId = $payment instanceof \App\Models\Payment ? $payment->id : (int) $payment;

        if ($request->filled('pin')) {
            $pin = (string) $request->input('pin');
            $authorized = false;

            $checkHashOrPlain = function (string $input, ?string $hashCandidate): bool {
                if (empty($hashCandidate)) return false;
                if (str_starts_with($hashCandidate, '$2y$') || str_starts_with($hashCandidate, '$2a$') || str_starts_with($hashCandidate, '$2b$')) {
                    try {
                        return \Illuminate\Support\Facades\Hash::check($input, $hashCandidate);
                    } catch (\Throwable $e) {
                        return false;
                    }
                }
                return hash_equals((string) $hashCandidate, (string) $input);
            };

            if (auth()->user()) {
                $user = auth()->user();
                if ($checkHashOrPlain($pin, $user->pin) || $checkHashOrPlain($pin, $user->password)) {
                    $authorized = true;
                }
            }

            if (!$authorized) {
                $managers = \App\Models\User::whereHas('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'gerente', 'manager']);
                })->get();

                foreach ($managers as $manager) {
                    if ($checkHashOrPlain($pin, $manager->pin) || $checkHashOrPlain($pin, $manager->password)) {
                        $authorized = true;
                        break;
                    }
                }
            }

            if (!$authorized) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'PIN de autorização incorreto.'], 403);
                }
                return back()->withErrors(['pin' => 'PIN de autorização incorreto.']);
            }
        }

        try {
            $this->cashierService->rollbackPayment($order, $paymentId);

            $order->refresh()->load([
                'items.modifiers',
                'payments.items.modifiers',
                'payments.transaction',
                'customer',
                'diningTable'
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pagamento estornado com sucesso!',
                    'order'   => $order,
                ]);
            }

            return back()->with('success', 'Pagamento estornado com sucesso!');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/RestaurantOps/ChecklistApiController.php
```php
<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistTask;
use App\Models\Product;
use App\Models\Supply;
use App\Services\RestaurantOps\ChecklistService;
use Illuminate\Http\Request;

class ChecklistApiController extends Controller
{
    public function __construct(
        protected ChecklistService $checklistService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user() ?? auth('team')->user();
        $checklists = $this->checklistService->getChecklistsForUser($user);

        return response()->json(['checklists' => $checklists]);
    }

    public function overview()
    {
        $data = $this->checklistService->getShiftOverview();
        return response()->json($data);
    }

    public function managementIndex()
    {
        $data = $this->checklistService->getManagementData();
        return response()->json($data);
    }

    public function syncTask(Request $request)
    {
        $data = $request->validate([
            'task_id' => 'required|exists:checklist_tasks,id',
            'status'  => 'required|in:done,issue,pending',
            'notes'   => 'nullable|string',
        ]);

        try {
            $userId = $request->user()?->id ?? auth('team')->id();
            $this->checklistService->syncTask($userId, $data);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function storeChecklist(Request $request)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $checklist = $this->checklistService->createChecklist($data);
        return response()->json(['success' => true, 'checklist' => $checklist]);
    }

    public function updateChecklist(Request $request, Checklist $checklist)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $checklist = $this->checklistService->updateChecklist($checklist, $data);
        return response()->json(['success' => true, 'checklist' => $checklist]);
    }

    public function destroyChecklist(Checklist $checklist)
    {
        $this->checklistService->deleteChecklist($checklist);
        return response()->json(['success' => true]);
    }

    public function storeTask(Request $request, Checklist $checklist)
    {
        $data = $request->validate([
            'description'         => 'required|string|max:255',
            'instructions'        => 'nullable|string',
            'assignment_type'     => 'required|in:all,roles,users',
            'target_roles'        => 'nullable|array',
            'target_users'        => 'nullable|array',
            'completion_type'     => 'required|in:shared,individual',
            'trigger_type'        => 'required|in:manual,shift_start,shift_end,scheduled',
            'trigger_time'        => 'nullable',
            'deadline_minutes'    => 'nullable|numeric',
            'recurrence_type'     => 'required|in:daily,weekly,monthly,once',
            'recurrence_days'     => 'nullable|array',
            'deadline_date'       => 'nullable',
            'is_inventory_task'   => 'nullable|boolean',
            'inventory_item_type' => 'nullable|string',
            'inventory_item_id'   => 'nullable|integer',
        ]);

        $task = $this->checklistService->createTask($checklist, $data);
        return response()->json(['success' => true, 'task' => $task]);
    }

    public function updateTask(Request $request, ChecklistTask $task)
    {
        $data = $request->validate([
            'description'         => 'required|string|max:255',
            'instructions'        => 'nullable|string',
            'assignment_type'     => 'required|in:all,roles,users',
            'target_roles'        => 'nullable|array',
            'target_users'        => 'nullable|array',
            'completion_type'     => 'required|in:shared,individual',
            'trigger_type'        => 'required|in:manual,shift_start,shift_end,scheduled',
            'trigger_time'        => 'nullable',
            'deadline_minutes'    => 'nullable|numeric',
            'recurrence_type'     => 'required|in:daily,weekly,monthly,once',
            'recurrence_days'     => 'nullable|array',
            'deadline_date'       => 'nullable',
            'is_inventory_task'   => 'nullable|boolean',
            'inventory_item_type' => 'nullable|string',
            'inventory_item_id'   => 'nullable|integer',
        ]);

        $task = $this->checklistService->updateTask($task, $data);
        return response()->json(['success' => true, 'task' => $task]);
    }

    public function destroyTask(ChecklistTask $task)
    {
        $this->checklistService->deleteTask($task);
        return response()->json(['success' => true]);
    }

    public function searchInventoryItems(Request $request)
    {
        $type = $request->input('type', 'App\\Models\\Product');
        $query = $request->input('q', '');

        if ($type === 'App\\Models\\Supply') {
            $items = Supply::where('name', 'like', "%{$query}%")
                ->limit(15)
                ->get(['id', 'name']);
        } else {
            $items = Product::where('name', 'like', "%{$query}%")
                ->limit(15)
                ->get(['id', 'name', 'type', 'current_stock', 'unit_of_measure', 'track_inventory']);
        }

        return response()->json($items);
    }
}
```

## File: app/Http/Controllers/Tenant/RestaurantOps/EmployeeWalletController.php
```php
<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\RestaurantOps\EmployeeWalletService;

class EmployeeWalletController extends Controller
{
    public function index(Request $request, EmployeeWalletService $walletService)
    {
        $user = auth()->user();

        // Garante que o usuário logado realmente tem um perfil de funcionário
        if (!$user || !$user->employee_id) {
            abort(403, 'Acesso negado. Apenas colaboradores possuem carteira.');
        }

        $dashboardData = $walletService->getWalletDashboardData($user->employee_id);

        return Inertia::render('Operations/Employee/Wallet', $dashboardData);
    }
}
```

## File: app/Http/Controllers/Tenant/RestaurantOps/KdsProductionController.php
```php
<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RestaurantOps\KdsService;
use App\Services\Printing\OrderPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KdsProductionController extends Controller
{
    public function updateStatus(Request $request, OrderItem $item, KdsService $kdsService): RedirectResponse
    {
        $request->validate(['production_status' => 'required|string|max:50']);
        $kdsService->updateItemStatus($item, $request->production_status);

        return redirect()->back();
    }

    public function sendItemsToKitchen(Request $request, Order $order, KdsService $kdsService): JsonResponse
    {
        $validated = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $kdsService->dispatchItemsToKitchen($order, $validated['items']);

            return response()->json([
                'success' => true,
                'message' => 'Itens enviados para a cozinha com sucesso!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Falha ao enviar itens para a cozinha: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function registerLoss(Request $request, OrderItem $item, KdsService $kdsService): RedirectResponse
    {
        $validated = $request->validate([
            'type'      => 'required|string',
            'target_id' => 'required|integer',
            'quantity'  => 'required|numeric|min:0.001',
            'reason'    => 'required|string',
            'notes'     => 'nullable|string',
        ]);

        $kdsService->registerLoss($item, $validated, auth()->id());
        return redirect()->back();
    }

    public function cancelItem(Request $request, OrderItem $item, KdsService $kdsService): RedirectResponse
    {
        $validated = $request->validate([
            'reason'      => 'required|string',
            'block_sales' => 'boolean',
            'block_type'  => 'nullable|string',
            'block_id'    => 'nullable|integer',
        ]);

        $kdsService->cancelItemAndRefund($item, $validated);
        return redirect()->back();
    }

    public function printItem(Request $request, OrderItem $item, OrderPrintService $printService): RedirectResponse
    {
        try {
            $requestedQty = $request->input('qty', 'all');
            $originalQty = (float) ($item->quantity ?: 1);

            $item->loadMissing([
                'order',
                'modifiers',
                'product.kitchen.printer',
                'product.category',
                'subItems.modifiers'
            ]);

            $printableItem = clone $item;
            $printableItem->setRelations($item->getRelations());

            if ($requestedQty === 'single' || (is_numeric($requestedQty) && (float) $requestedQty < $originalQty)) {
                $targetQty = ($requestedQty === 'single') ? 1.0 : (float) $requestedQty;
                $printableItem->quantity = $targetQty;

                $ratio = $targetQty / $originalQty;

                if ($item->relationLoaded('modifiers')) {
                    $scaledModifiers = $item->modifiers->map(function ($mod) use ($ratio) {
                        $clonedMod = clone $mod;
                        $clonedMod->quantity = max(1, (int) round(($clonedMod->quantity ?: 1) * $ratio));
                        return $clonedMod;
                    });
                    $printableItem->setRelation('modifiers', $scaledModifiers);
                }

                if ($item->relationLoaded('subItems')) {
                    $scaledSubItems = $item->subItems->map(function ($sub) use ($ratio) {
                        $clonedSub = clone $sub;
                        $clonedSub->quantity = max(1, (int) round(($clonedSub->quantity ?: 1) * $ratio));
                        return $clonedSub;
                    });
                    $printableItem->setRelation('subItems', $scaledSubItems);
                }
            }

            $printService->reprintKdsItem($printableItem);
            return back()->with('success', 'Via enviada para impressão!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * 🚀 Impressão em lote de itens selecionados (Bulk Print)
     */
    public function bulkPrint(Request $request, OrderPrintService $printService)
    {
        $validated = $request->validate([
            'item_ids'   => 'required|array|min:1',
            'item_ids.*' => 'required|integer|exists:order_items,id',
        ]);

        try {
            $items = OrderItem::with([
                'order',
                'modifiers',
                'product.kitchen.printer',
                'product.category',
                'subItems.modifiers'
            ])
            ->whereIn('id', $validated['item_ids'])
            ->get();

            foreach ($items as $item) {
                $printableItem = clone $item;
                $printableItem->setRelations($item->getRelations());
                $printService->reprintKdsItem($printableItem);
            }

            $count = $items->count();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$count} item(ns) enviado(s) para impressão!",
                ]);
            }

            return back()->with('success', "{$count} item(ns) enviado(s) para impressão!");
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Falha na impressão em lote: ' . $e->getMessage());
        }
    }
}
```

## File: app/Http/Controllers/Tenant/RestaurantOps/PrintQueueController.php
```php
<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Printer;
use App\Services\Printing\PrintDispatcher;
use App\Services\Printing\PrintQueueService;

class PrintQueueController extends Controller
{
    public function index(PrintQueueService $service)
    {
        return Inertia::render('Operations/Printers/Printers', $service->getQueueDashboardData());
    }

    public function retryJobs(Request $request, PrintDispatcher $dispatcher)
    {
        $request->validate(['job_ids' => 'required|array']);
        $count = $dispatcher->retryJobs($request->job_ids);

        return back()->with('success', "{$count} trabalhos reenviados para a fila!");
    }

    public function retryAllFailed(PrintQueueService $service, PrintDispatcher $dispatcher)
    {
        $count = $service->retryAllFailed($dispatcher);

        if ($count === 0) {
            return back()->with('info', 'Não há impressões falhas recentes.');
        }

        return back()->with('success', "{$count} trabalhos falhos foram reenviados!");
    }

    public function setFallback(Request $request, Printer $printer, PrintQueueService $service)
    {
        $request->validate(['fallback_printer_id' => 'nullable|exists:printers,id']);

        $service->updateFallback($printer, $request->fallback_printer_id);

        return back()->with('success', 'Regra de desvio atualizada com sucesso!');
    }

    public function dismissJobs(Request $request, PrintQueueService $service)
    {
        $request->validate(['job_ids' => 'required|array']);

        $service->dismissJobs($request->job_ids);

        return back()->with('success', count($request->job_ids) . ' trabalhos descartados do histórico.');
    }
}
```

## File: app/Http/Controllers/Tenant/RestaurantOps/ShiftAttendanceController.php
```php
<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\RestaurantOps\ShiftAttendanceService;

class ShiftAttendanceController extends Controller
{
    public function sync(Request $request, ShiftAttendanceService $service)
    {
        $data = $request->validate([
            'attendees' => 'required|array',
            'attendees.*.user_id' => 'required|integer|exists:users,id',
            'attendees.*.attendance_status' => 'required|string|in:present,absent,vacation,day_off',
            'attendees.*.daily_rate' => 'required|numeric|min:0',
            'attendees.*.assigned_role' => 'nullable|string',
        ]);

        $service->syncShiftAttendees($data['attendees']);

        return back()->with('success', 'Equipe atualizada com sucesso!');
    }

    /**
     * 🚀 Retorna os detalhes de diária, consumo e valor líquido para o modal de pagamento
     */
    public function payoutDetails(int $userId, ShiftAttendanceService $service)
    {
        try {
            $details = $service->getFreelancerPayoutDetails($userId);
            return response()->json($details);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function payFreelancer(Request $request, ShiftAttendanceService $service)
    {
        $data = $request->validate([
            'user_id'              => 'required|integer|exists:users,id',
            'amount'               => 'required|numeric|min:0',
            'gross_rate'           => 'nullable|numeric|min:0',
            'consumption_deducted' => 'nullable|numeric|min:0',
            'payment_method'       => 'required|string|in:cash,bank,accumulate',
            'bank_account_id'      => 'nullable|integer|exists:bank_accounts,id'
        ]);

        try {
            $service->payFreelancer($data, auth()->user());

            $isAccumulated = $data['payment_method'] === 'accumulate' || (float) $data['amount'] <= 0.00;
            $msg = $isAccumulated
                ? 'Diária acumulada no extrato do colaborador com sucesso.'
                : 'Diária liquidada e recibo emitido com sucesso!';

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Terminals/CustomerCheckoutController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Order;
use App\Http\Requests\Customer\CustomerCheckoutRequest;
use App\Services\Orders\CustomerCheckoutService;
use App\Services\Fiscal\SpedDocumentService;
use Exception;

class CustomerCheckoutController extends Controller
{
    /**
     * 🚀 Processa o checkout do cliente (B2C)
     */
    public function store(CustomerCheckoutRequest $request, CustomerCheckoutService $checkoutService)
    {
        $payload = $request->validated();

        $user = auth()->user();
        $isGuest = !auth()->check();
        $guestCustomerId = session('guest_customer_id');

        $customer = $user ? $user->customer : Customer::find($guestCustomerId);

        if (!$customer) {
            abort(403, 'Sessão inválida. Por favor, identifique-se novamente.');
        }

        try {
            $result = $checkoutService->processB2CCheckout($payload, $customer, $isGuest);

            if ($result['isBankApi']) {
                return back()->with([
                    'pix_data' => $result['pixData'],
                    'order_id' => $result['order']->id,
                    'success'  => 'Código PIX gerado com sucesso!'
                ]);
            }

            return back()->with([
                'order_id' => $result['order']->id,
                'success'  => 'Pedido recebido com sucesso!'
            ]);

        } catch (\Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 500;
            if ($code === 403) {
                abort(403, $e->getMessage());
            }

            return redirect()->back()->with('error', 'Erro ao processar checkout: ' . $e->getMessage());
        }
    }

    /**
     * 🛑 Cancelamento ativo pelo cliente (B2C)
     */
    public function cancel(Order $order, CustomerCheckoutService $checkoutService)
    {
        try {
            $customerId = auth()->user()?->customer?->id ?? session('guest_customer_id');

            $checkoutService->cancelOrderByCustomer($order, $customerId ? (int) $customerId : null);

            return redirect()->back()->with('success', 'Pedido cancelado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * 🌟 Recebe a avaliação do cliente (1 a 5 estrelas)
     */
    public function rate(Order $order, Request $request, CustomerCheckoutService $checkoutService)
    {
        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'tags'    => 'nullable|array',
            'comment' => 'nullable|string|max:500',
        ]);

        $customerId = auth()->user()?->customer?->id ?? session('guest_customer_id');

        try {
            $checkoutService->rateOrder($order, $validated, $customerId ? (int) $customerId : null);

            return redirect()->back()->with('success', 'Avaliação enviada com sucesso!');
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                abort(403, $e->getMessage());
            }
            return redirect()->back()->with('error', 'Erro ao enviar avaliação: ' . $e->getMessage());
        }
    }

    /**
     * 📄 Gera e exibe o recibo do pedido em PDF
     */
    public function receipt(Order $order, SpedDocumentService $spedService)
    {
        $customerId = auth()->user()?->customer?->id ?? session('guest_customer_id');
        if ($order->customer_id && (int) $order->customer_id !== (int) $customerId) {
            abort(403, 'Acesso negado. Você não tem permissão para visualizar este recibo.');
        }

        $order->load('fiscalDocument');
        if (!$order->fiscalDocument || empty($order->fiscalDocument->xml_content)) {
            abort(404, 'A Nota Fiscal (NFC-e) ainda não está disponível para este pedido.');
        }

        try {
            $pdfContent = $spedService->renderDanfePdf($order->fiscalDocument->xml_content);

            return response()->streamDownload(
                function () use ($pdfContent) {
                    echo $pdfContent;
                },
                "Recibo_Pedido_{$order->id}.pdf",
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"Recibo_Pedido_{$order->id}.pdf\""
                ]
            );

        } catch (\Exception $e) {
            abort(500, 'Erro ao gerar o PDF da Nota Fiscal: ' . $e->getMessage());
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Terminals/CustomerLoginController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Customers\CustomerRegistrationService;

class CustomerLoginController extends Controller
{
    public function login(Request $request, CustomerRegistrationService $registrationService)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();
            $registrationService->ensureUserHasCustomerProfile(Auth::user());

            // 🚀 CORREÇÃO: Redirecionamento blindado! Ignora qualquer lixo na sessão e vai pra Home.
            return redirect()->route('customer.app', ['tab' => 'home'])->with('success', 'Bem-vindo de volta!');
        }

        return back()->withErrors(['email' => 'As credenciais fornecidas não correspondem aos nossos registros.']);
    }
}
```

## File: app/Http/Controllers/Tenant/Terminals/CustomerRegistrationController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Services\Customers\CustomerRegistrationService;
use App\Services\Customers\SerproService;
use Illuminate\Support\Facades\Auth;
use Exception;

class CustomerRegistrationController extends Controller
{
    public function upgrade(Request $request, CustomerRegistrationService $registrationService, SerproService $serproService)
    {
        $data = $request->validate([
            'cpf'                 => 'required|string|min:11|max:18',
            'phone'               => 'required|string|min:10',
            'name'                => 'nullable|string|min:2',
            'birth_date'          => 'nullable|date',
            'fallback_name'       => 'nullable|string|min:2',
            'fallback_birth_date' => 'nullable|date',
        ]);

        try {
            $cleanCpf = preg_replace('/[^0-9]/', '', $data['cpf']);
            $cleanPhone = preg_replace('/\D/', '', $data['phone']);
            $nameToSave = $data['name'] ?? ($data['fallback_name'] ?? null);
            $birthDateToSave = $data['birth_date'] ?? ($data['fallback_birth_date'] ?? null);

            // CENÁRIO 1: Social Login Pendente (Google)
            if (session()->has('pending_social_auth')) {
                $user = $registrationService->completeSocialRegistration($data, session('guest_customer_id'), $serproService);
                if ($user->customer) {
                    $user->customer->update(['phone' => $cleanPhone]);
                }
                Auth::login($user, true);
                $request->session()->regenerate();
                return back()->with('success', 'Cadastro concluído com sucesso!');
            }

            // CENÁRIO 2: Usuário já autenticado vinculando CPF
            if (Auth::check()) {
                $user = Auth::user();
                $customer = $user->customer;

                if ($customer) {
                    $customer->update([
                        'cpf'        => $cleanCpf,
                        'name'       => $nameToSave ?? $customer->name,
                        'birth_date' => $birthDateToSave ?? $customer->birth_date,
                        'phone'      => $cleanPhone
                    ]);
                }

                return back()->with('success', 'Perfil completado com sucesso!');
            }

            // CENÁRIO 3: Visitante promovido a cliente oficial
            $guestId = session('guest_customer_id');
            if (!$guestId) {
                return back()->withErrors(['upgrade' => 'Sessão expirada.']);
            }

            $user = $registrationService->upgradeGuestAccount($guestId, $data, $serproService);
            if ($user->customer) {
                $user->customer->update(['phone' => $cleanPhone]);
            }

            session()->forget('guest_customer_id');
            Auth::login($user, true);
            $request->session()->regenerate();

            return back()->with('success', 'Conta criada com sucesso!');
        } catch (Exception $e) {
            return back()->withErrors(['upgrade' => $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Terminals/CustomerReorderController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\CustomerReorderService;
use Illuminate\Http\Request;

class CustomerReorderController extends Controller
{
    public function store(Request $request, Order $order, CustomerReorderService $reorderService)
    {
        // 🚀 CORREÇÃO: Navegando pela relação auth()->user()->customer?->id
        $isOwner = auth()->check() && auth()->user()->customer?->id === $order->customer_id;
        $isSessionGuest = session('guest_customer_id') === $order->customer_id;

        if (!$isOwner && !$isSessionGuest) {
            abort(403, 'Acesso negado. Você não tem permissão para refazer este pedido.');
        }

        try {
            // A ação pode ser 'same' (Gerar novo PIX) ou 'change' (Mudar forma de pagamento)
            $action = $request->input('new_payment_method', 'same');

            $result = $reorderService->processReorder($order, $action);

            return back()->with('flash', [
                'success' => 'Pedido recriado com sucesso!',
                'pix_data' => $result['pix_data'] ?? null
            ]);

        } catch (\Exception $e) {
            return back()->with('flash', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Terminals/PosOrderEntryController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use App\Services\RestaurantOps\OperationsMenuService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosOrderEntryController extends Controller
{
    /**
     * Renderiza a tela isolada de lançamento de comandas (Mesa / Balcão).
     */
    public function index(Request $request, OperationsMenuService $menuService): Response
    {
        return Inertia::render("Pos/OrderEntry", $menuService->getOrderEntryData($request));
    }
}
```

## File: app/Http/Controllers/Tenant/Terminals/PosSettingsController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\RestaurantOps\PosSettingsService;

class PosSettingsController extends Controller
{
    public function index(PosSettingsService $service)
    {
        return Inertia::render('Settings/Settings', $service->getSettingsData());
    }

    public function reorder(Request $request, \App\Services\RestaurantOps\PosSettingsService $service)
    {
        // 🚀 SONDA LARAVEL 1: O que chegou no Controller antes de validar?
        \Illuminate\Support\Facades\Log::info('📦 CONTROLLER RECEBEU:', $request->input('items', []));

        $validated = $request->validate([
            'type' => 'required|in:categories,products',
            'items' => 'required|array', // Valida apenas que é um array
        ]);

        try {
            // Passamos o input direto para evitar que o validate() corte nós filhos não declarados
            $service->reorderItems($validated['type'], $request->input('items'));

            return redirect()->back()->with('success', 'Ordem atualizada!');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('🚨 ERRO NO REORDER: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Terminals/WhatsAppAuthController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CompanySetting;

class WhatsAppAuthController extends Controller
{
    /**
     * Gera e envia o código OTP usando o template oficial auth_otp_code.
     */
    public function sendOtp(Request $request)
    {
        // 🛡️ TRAVA DE SEGURANÇA: Bloqueia requisições forçadas se desativado no painel
        $setting = CompanySetting::getCached();
        $methods = $setting?->customer_auth_methods;

        if (is_string($methods)) {
            $methods = json_decode($methods, true) ?: [];
        }

        if (!is_array($methods) || !in_array('whatsapp', $methods, true)) {
            return response()->json([
                'message' => 'A autenticação via WhatsApp está desativada no momento.'
            ], 403);
        }

        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        // 1. Sanitização do telefone: remove tudo que não for número
        $phone = preg_replace('/\D/', '', $request->input('phone'));

        // Adiciona DDI 55 caso o cliente tenha digitado apenas DDD + número
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }

        // 2. Proteção contra flood (Rate Limit: 1 envio a cada 60s por número)
        $throttleKey = "whatsapp_otp_throttle_{$phone}";
        if (Cache::has($throttleKey)) {
            return response()->json([
                'message' => 'Aguarde 60 segundos antes de solicitar um novo código.'
            ], 429);
        }

        // 3. Geração do código OTP criptograficamente seguro (6 dígitos)
        $otp = (string) random_int(100000, 999999);

        // 4. Parâmetros da API Meta
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $version = config('services.whatsapp.version', 'v21.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        // 5. Disparo do Template auth_otp_code com botão de Copiar Código
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'     => 'auth_otp_code',
                'language' => [
                    'code' => 'pt_BR'
                ],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otp
                            ]
                        ]
                    ],
                    [
                        'type'       => 'button',
                        'sub_type'   => 'url',
                        'index'      => '0',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otp
                            ]
                        ]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Erro ao enviar WhatsApp OTP:', [
                    'phone'    => $phone,
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);

                return response()->json([
                    'message' => 'Falha ao entregar o código via WhatsApp. Tente novamente mais tarde.'
                ], 502);
            }

            // 6. Armazena no Cache com validade de 10 minutos
            Cache::put("whatsapp_otp_{$phone}", $otp, now()->addMinutes(10));
            Cache::put($throttleKey, true, now()->addSeconds(60));

            return response()->json([
                'success' => true,
                'message' => 'Código de verificação enviado com sucesso.'
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Exceção ao disparar WhatsApp OTP: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erro interno de comunicação com a Meta.'
            ], 500);
        }
    }

    /**
     * Valida o código inserido pelo cliente.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        $phone = preg_replace('/\D/', '', $request->input('phone'));
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }

        $cachedOtp = Cache::get("whatsapp_otp_{$phone}");

        if (!$cachedOtp || !hash_equals((string) $cachedOtp, (string) $request->input('code'))) {
            return response()->json([
                'message' => 'Código inválido ou expirado.'
            ], 422);
        }

        // Código validado: expira o cache para evitar reutilização
        Cache::forget("whatsapp_otp_{$phone}");

        // TODO: Localizar ou criar o registro do Customer correspondente ao tenant atual
        // e emitir o token Sanctum ou autenticar a sessão do cliente.

        return response()->json([
            'success' => true,
            'message' => 'Telefone autenticado com sucesso.'
        ], 200);
    }
}
```

## File: app/Http/Controllers/Tenant/DynamicManifestController.php
```php
<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicManifestController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        $portal = $request->query('portal', 'customer');
        $isTeam = $portal === 'team';

        $appName = $settings->trade_name ?? $settings->app_name ?? $settings->company_name ?? 'Delivery';
        $shortName = $isTeam ? "{$appName} Equipe" : $appName;
        $startUrl = $isTeam ? '/team' : '/customer/home';

        $themeColor = $settings->theme_color ? $this->getHexCode($settings->theme_color) : '#f59e0b';
        $bgColor = $isTeam ? '#09090b' : '#000000';

        // 🚀 Resolve as imagens através da rota canônica /media/file do tenant
        $icon192 = $this->resolveIconUrl($settings->icon_192_path ?? $settings->icon_path ?? null, '192');
        $icon512 = $this->resolveIconUrl($settings->icon_path ?? $settings->icon_192_path ?? null, '512');

        $manifest = [
            "name"             => $appName,
            "short_name"       => $shortName,
            "start_url"        => $startUrl,
            "scope"            => "/",
            "display"          => "standalone",
            "background_color" => $bgColor,
            "theme_color"      => $themeColor,
            "icons"            => [
                [
                    "src"     => $icon192,
                    "sizes"   => "192x192",
                    "type"    => "image/png",
                    "purpose" => "any maskable"
                ],
                [
                    "src"     => $icon512,
                    "sizes"   => "512x512",
                    "type"    => "image/png",
                    "purpose" => "any maskable"
                ]
            ]
        ];

        return response()->json($manifest)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    private function resolveIconUrl(?string $path, string $size): string
    {
        if (!empty($path)) {
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }

            // 🚀 Rota canônica de streaming do tenant
            return url('/media/file?path=' . ltrim($path, '/'));
        }

        // Fallback para ícone estático caso o tenant não tenha configurado
        $defaultPath = "assets/icons/icon-{$size}.png";
        if (file_exists(public_path($defaultPath))) {
            return asset($defaultPath);
        }

        return asset('favicon.ico');
    }

    private function getHexCode(string $color): string
    {
        $colors = [
            'amber'   => '#f59e0b',
            'red'     => '#ef4444',
            'emerald' => '#10b981',
            'blue'    => '#3b82f6',
            'violet'  => '#8b5cf6',
            'rose'    => '#f43f5e',
        ];

        return $colors[$color] ?? '#f59e0b';
    }
}
```

## File: app/Http/Controllers/Tenant/MediaServeController.php
```php
<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaServeController extends Controller
{
    public function serve(Request $request)
    {
        $path = $request->query('path');

        if (blank($path)) {
            abort(400, 'Caminho ausente.');
        }

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Arquivo não localizado.');
        }

        return Storage::disk('public')->response($path);
    }
}
```

## File: app/Http/Controllers/Tenant/PredictiveMatrixController.php
```php
<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\TrainPredictiveAiJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PredictiveMatrixController extends Controller
{
    public function show(): JsonResponse
    {
        $matrix = Cache::store('file')->get('predictive_modifier_matrix', []);

        if (empty($matrix)) {
            $tenantId = function_exists('tenant') ? tenant('id') : null;

            // 🚀 Despacha o Job para a fila de background e não trava o servidor!
            if (class_exists(TrainPredictiveAiJob::class)) {
                TrainPredictiveAiJob::dispatch($tenantId);
            }

            return response()->json([]);
        }

        return response()->json($matrix);
    }
}
```

## File: app/Http/Controllers/Tenant/PushSubscriptionController.php
```php
<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        Log::info("📍 [SONDA PUSH BACK] Inscrição Push recebida:", $request->all());

        $request->validate([
            'endpoint'    => 'required',
            'keys.auth'   => 'required',
            'keys.p256dh' => 'required'
        ]);

        $subscribableType = null;
        $subscribableId = null;

        if (auth('team')->check()) {
            $subscribableType = \App\Models\User::class;
            $subscribableId = auth('team')->id();
        } elseif (auth('web')->check()) {
            $subscribableType = \App\Models\User::class;
            $subscribableId = auth('web')->id();
        } elseif (session()->has('guest_customer_id')) {
            $subscribableType = \App\Models\Customer::class;
            $subscribableId = session('guest_customer_id');
        } elseif ($request->filled('customer_id')) {
            $subscribableType = \App\Models\Customer::class;
            $subscribableId = $request->customer_id;
        } else {
            return response()->json(['error' => 'Identificador do usuário não encontrado'], 401);
        }

        // Salva diretamente na tabela do banco do Tenant ativo
        DB::table('push_subscriptions')->where('endpoint', $request->endpoint)->delete();

        DB::table('push_subscriptions')->insert([
            'subscribable_type' => $subscribableType,
            'subscribable_id'   => $subscribableId,
            'endpoint'          => $request->endpoint,
            'public_key'        => $request->keys['p256dh'],
            'auth_token'        => $request->keys['auth'],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        Log::info("📍 [SONDA PUSH BACK] Inscrição salva no banco do Tenant para {$subscribableType} #{$subscribableId}!");

        return response()->json(['success' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required']);
        DB::table('push_subscriptions')->where('endpoint', $request->endpoint)->delete();
        return response()->json(['success' => true]);
    }
}
```

## File: app/Http/Controllers/Tenant/QrCodeDownloadController.php
```php
<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeDownloadController extends Controller
{
    public function download(Request $request)
    {
        $url = $request->query('url');
        $format = $request->query('format', 'svg');

        if (!$url) {
            return abort(400, 'A URL é obrigatória.');
        }

        $fileName = 'Brasume_Equipe_QR.' . $format;
        $qr = QrCode::size(500)->margin(2);

        if ($format === 'png') {
            $image = $qr->format('png')->generate($url);
            return response($image)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }

        $svg = $qr->generate($url);
        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }
}
```

## File: app/Http/Controllers/Controller.php
```php
<?php

namespace App\Http\Controllers;

abstract class Controller
{
    //
}
```

## File: app/Http/Controllers/FacebookController.php
```php
<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class FacebookController extends Controller
{
    public function getFacebookUrl(Request $request)
    {
        $tenantHost = $request->query('host', request()->getHost());
        $intent = $request->query('intent', 'customer');
        $returnTo = $request->input('return_to', $request->header('referer', '/'));

        $statePayload = base64_encode(json_encode([
            'host'      => $tenantHost,
            'intent'    => $intent,
            'return_to' => $returnTo,
        ]));

        $redirectUrl = Socialite::driver('facebook')
            ->scopes(['email'])
            ->with(['state' => $statePayload])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $redirectUrl]);
    }

    public function handleFacebookCallback(Request $request)
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();
            $stateDecoded = base64_decode($request->input('state'));
            $stateData = json_decode($stateDecoded, true);

            $tenantHost = $stateData['host'] ?? request()->getHost();
            $intent = $stateData['intent'] ?? 'customer';
            $returnTo = $stateData['return_to'] ?? '/';

            $payload = json_encode([
                'provider'   => 'facebook',
                'id'         => $facebookUser->getId(),
                'email'      => $facebookUser->getEmail(),
                'name'       => $facebookUser->getName(),
                'intent'     => $intent,
                'return_to'  => $returnTo,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            $token = urlencode(Crypt::encryptString($payload));

            // Devolve para o domínio do tenant
            return redirect("https://{$tenantHost}/auth/social/magic-login?token={$token}");

        } catch (\Exception $e) {
            Log::error('Erro no callback do Facebook: ' . $e->getMessage());
            return redirect('/')->with('error', 'Falha ao autenticar com o Facebook.');
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Apps/PosAppController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Services\Marketing\CrmService;
use App\Services\RestaurantOps\ScheduleService;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CashShift;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Street;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Product;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\RestaurantOps\OperationsMenuService;
use App\Services\RestaurantOps\ShiftAttendanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class PosAppController extends Controller
{
    public function index(
        Request $request,
        OperationsMenuService $menuService,
        OperationsDashboardService $opsService,
        ScheduleService $scheduleService
    ): Response {
        $rawTab = $request->route('tab', 'new');
        $activeTab = in_array($rawTab, ['pos', 'new', '']) ? 'new' : $rawTab;
        $user = auth()->user();

        $data = $menuService->getOrderEntryData($request);
        $data['activeTab'] = $activeTab;
        $data['tab'] = $activeTab;
        $data['defaultTab'] = $activeTab;
        $data['initialStreets'] = fn () => Street::with('segments.neighborhood')->get();

        $data['initialOrders'] = fn () => $opsService->getActiveOrdersDashboard()['initialOrders'] ?? [];
        $data['couriers'] = fn () => User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['driver', 'motoboy', 'entregador']);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $shift = CashShift::where('status', 'open')->latest()->first();
        $attendanceService = app(ShiftAttendanceService::class);

        $data['cashierData'] = fn () => [
            'currentShift' => $shift,
            'managerData' => $opsService->getManagerShiftSummary($user),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'users' => $attendanceService->getShiftUsersData(),
            'currentAttendees' => $attendanceService->getCurrentAttendees($shift),
            'todayScheduledAttendees' => $scheduleService->getTodayScheduledAttendees(),
            'ordersHistory' => function () use ($shift) {
                if (!$shift || !$shift->opened_at) return [];

                return Order::with(['customer', 'payments.paymentMethod', 'employee', 'diningTable'])
                    ->where(function ($q) use ($shift) {
                        $q->whereHas('payments', function ($pq) use ($shift) {
                            $pq->where('cash_shift_id', $shift->id);
                        })
                        ->orWhere(function ($oq) use ($shift) {
                            $oq->where('created_at', '>=', $shift->opened_at)
                               ->when($shift->closed_at, fn ($sq) => $sq->where('created_at', '<=', $shift->closed_at))
                               ->where(function ($sub) {
                                   $sub->whereIn('payment_status', ['paid', 'partial'])
                                       ->orWhereIn('status', ['closed', 'completed']);
                               });
                        });
                    })
                    ->where(function ($filterOpen) {
                        $filterOpen->where('type', '!=', 'table')
                            ->orWhereIn('status', ['closed', 'completed'])
                            ->orWhereIn('payment_status', ['paid', 'partial'])
                            ->orWhereHas('payments');
                    })
                    ->latest()
                    ->get();
            },
            'transactionsHistory' => function () use ($shift) {
                if (!$shift || !$shift->opened_at || !$shift->bank_account_id) return [];

                return Transaction::where('bank_account_id', $shift->bank_account_id)
                    ->where('transaction_date', '>=', $shift->opened_at)
                    ->whereNull('order_id')
                    ->when($shift->closed_at, fn ($q) => $q->where('transaction_date', '<=', $shift->closed_at))
                    ->latest('transaction_date')
                    ->get();
            }
        ];

        $data['printerData'] = fn () => [
            'printers' => Printer::where('is_active', true)->get()->toArray(),
            'printJobs' => PrintJob::latest('id')->limit(50)->get()->toArray(),
        ];

        $data['catalog'] = fn () => Product::with(['category', 'latestCostHistory'])
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        $data['crmData'] = fn () => app(CrmService::class)->getDashboardData();

        // 🚀 Escala 5x2 (Closures nativas compatíveis)
        $scheduleStartDate = $request->query('start_date', Carbon::now()->startOfWeek()->format('Y-m-d'));

        $data['scheduleStartDate'] = $scheduleStartDate;
        $data['employees'] = fn () => $scheduleService->getWeeklySchedule($scheduleStartDate)['employees'];
        $data['slots'] = fn () => $scheduleService->getWeeklySchedule($scheduleStartDate)['slots'];
        $data['initialSchedules'] = fn () => $scheduleService->getWeeklySchedule($scheduleStartDate)['schedules'];

        return Inertia::render('Pos/PosApp', $data);
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderAppendController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\Handlers\OrderAppendHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OrderAppendController extends Controller
{
    public function store(Order $order, Request $request, OrderAppendHandler $appendHandler): JsonResponse|RedirectResponse
    {
        Log::info("📍 [SONDA APPEND 1] Recebido no Controller para o Pedido #{$order->id}", [
            'frontend_intent' => $request->input('frontend_intent')
        ]);

        try {
            $payload = $request->validate([
                'items'            => 'required|array|min:1',
                'items.*.id'       => 'required',
                'items.*.qty'      => 'required|numeric|min:0.01',
                'items.*.price'    => 'required|numeric',
                'financials'       => 'nullable|array',
                'routing'          => 'nullable|array',
                'source'           => 'nullable|string',
                'frontend_intent'  => 'nullable|string'
            ]);
            Log::info("📍 [SONDA APPEND 2] Validação passou com sucesso.");
        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::error("🚨 [SONDA APPEND ERRO] Validação falhou:", $ve->errors());
            throw $ve;
        }

        if (!$appendHandler->canAppend($order->id)) {
            $msg = "Ação recusada: O Pedido #{$order->id} não pode receber novos itens neste momento.";
            Log::warning("⚠️ [SONDA APPEND 3] Bloqueado pelo canAppend()");

            if ($request->wantsJson()) return response()->json(['success' => false, 'message' => $msg], 422);
            return back()->with('error', $msg);
        }

        try {
            Log::info("📍 [SONDA APPEND 4] Iniciando Handler...");
            $updatedOrder = $appendHandler->handle($order->id, $payload);
            Log::info("📍 [SONDA APPEND 5] Handler concluído sem erros!");

            if ($request->wantsJson()) {
                return response()->json([
                    'success'  => true,
                    'message'  => 'Itens adicionados com sucesso!',
                    'order_id' => $updatedOrder->id,
                    'order'    => $updatedOrder,
                ]);
            }

            return back()->with([
                'success' => 'Itens adicionados com sucesso!',
                'order_id' => $updatedOrder->id
            ]);

        } catch (\Exception $e) {
            Log::error("🚨 [SONDA APPEND ERRO FATAL] Crash no Handler:", [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return back()->withErrors(['append' => $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Orders/OrderCreationController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosOrderSubmitRequest;
use App\Models\Order;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\Handlers\OrderAppendHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OrderCreationController extends Controller
{
    public function store(PosOrderSubmitRequest $request, OrderCreationService $creationService, OrderAppendHandler $appendHandler): JsonResponse|RedirectResponse
    {
        $payload = $request->validated();

        $targetOrderId = $payload['order_id']
            ?? $payload['routing']['id']
            ?? $payload['routing']['order_id']
            ?? $payload['routing']['existing_order_id']
            ?? null;

        // 🚀 O pulo do gato: Se chegou um ID na Rota de Criação, nós faremos o Append forçado pelo Backend
        if ($targetOrderId) {
            $existingOrder = Order::find($targetOrderId);

            // Regras de Bloqueio para não anexar em comanda que já expirou
            $isFinished = $existingOrder && (
                in_array($existingOrder->status, ['closed', 'cancelled', 'completed', 'merged'], true)
                || ($existingOrder->type === 'table' && $existingOrder->payment_status === 'paid')
            );

            if ($isFinished) {
                Log::warning("🛑 [OrderCreation] Pedido #{$targetOrderId} fechado. Criando nova comanda.");
                $payload['order_id'] = null;
                if (isset($payload['routing']) && is_array($payload['routing'])) {
                    $payload['routing']['id'] = null;
                    $payload['routing']['order_id'] = null;
                    $payload['routing']['existing_order_id'] = null;
                }
            } else {
                Log::info("🔄 [OrderCreation] Detectado ID #{$targetOrderId} na rota de Store. Delegando para AppendHandler.");

                try {
                    // Força a chamada do fluxo de adição em vez de criar um novo
                    $updatedOrder = $appendHandler->handle((int)$targetOrderId, $payload);

                    if ($request->wantsJson()) {
                        return response()->json([
                            'success'          => true,
                            'message'          => 'Itens adicionados com sucesso!',
                            'order_id'         => $updatedOrder->id,
                            'success_order_id' => $updatedOrder->id,
                            'order'            => $updatedOrder,
                        ]);
                    }

                    return back()->with([
                        'success'          => 'Itens adicionados com sucesso!',
                        'order_id'         => $updatedOrder->id,
                        'success_order_id' => $updatedOrder->id,
                        'order'            => $updatedOrder,
                    ]);

                } catch (\Exception $e) {
                    if ($request->wantsJson()) return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
                    return back()->withErrors(['append' => $e->getMessage()]);
                }
            }
        }

        try {
            $order = $creationService->processCheckout($payload);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'          => true,
                    'message'          => 'Pedido processado com sucesso!',
                    'order_id'         => $order->id,
                    'success_order_id' => $order->id,
                    'order'            => $order,
                ], 201);
            }

            return back()->with([
                'success'          => 'Pedido processado com sucesso!',
                'order_id'         => $order->id,
                'success_order_id' => $order->id,
                'order'            => $order,
            ]);

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }
    }
}
```

## File: app/Http/Controllers/Tenant/Terminals/CustomerGuestController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Customers\CustomerRegistrationService;
use App\Models\Customer;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Log;

class CustomerGuestController extends Controller
{
    public function register(Request $request, CustomerRegistrationService $registrationService)
    {
        Log::info('📍 [SONDA GUEST BACKEND 1] Requisição recebida em /guest-register:', [
            'payload'    => $request->all(),
            'session_id' => session()->getId(),
        ]);

        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        $requireWhatsapp = $settings->require_whatsapp ?? true;

        try {
            $data = $request->validate([
                'name'  => 'required|string|min:2|max:255',
                'phone' => $requireWhatsapp ? 'required|string|min:10' : 'nullable|string',
            ]);
            Log::info('📍 [SONDA GUEST BACKEND 2] Validação concluída com sucesso:', $data);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::error('🚨 [SONDA GUEST BACKEND ERRO] Falha de validação:', $ve->errors());
            throw $ve;
        }

        try {
            $guestId = $registrationService->registerGuestCustomer($data);
            Log::info('📍 [SONDA GUEST BACKEND 3] Guest registrado via Service. ID:', ['guest_id' => $guestId]);

            session(['guest_customer_id' => $guestId]);
            session()->save();

            Log::info('📍 [SONDA GUEST BACKEND 4] Sessão guest_customer_id gravada:', [
                'session_guest_customer_id' => session('guest_customer_id')
            ]);

            return back()->with('success', 'Identificação confirmada!');
        } catch (\Exception $e) {
            Log::error('🚨 [SONDA GUEST BACKEND ERRO] Exceção ao registrar visitante:', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile()
            ]);
            return back()->withErrors(['guest' => $e->getMessage()]);
        }
    }

    public function restore(Request $request)
    {
        $guestId = $request->input('guest_id');

        if (!$guestId) {
            return response()->json(['error' => 'ID não fornecido'], 400);
        }

        $customer = Customer::find($guestId);

        if ($customer && is_null($customer->user_id)) {
            session(['guest_customer_id' => $customer->id]);
            session()->save();
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Visitante inválido ou já registrado'], 404);
    }
}
```

## File: app/Http/Controllers/GoogleController.php
```php
<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\GoogleAuthService;
use App\Services\Customers\CustomerRegistrationService;

class GoogleController extends Controller
{
    public function redirectToGoogle(Request $request)
    {
        $tenantHost = $request->query('host', request()->getHost());
        $intent = $request->query('intent', 'team');
        $returnTo = $request->query('return_to', $request->header('referer', '/'));

        $statePayload = base64_encode(json_encode([
            'host'      => $tenantHost,
            'intent'    => $intent,
            'return_to' => $returnTo,
        ]));

        return Socialite::driver('google')
            ->with(['state' => $statePayload])
            ->redirect();
    }

    public function getGoogleUrl(Request $request)
    {
        $tenantHost = $request->query('host', request()->getHost());
        $intent = $request->query('intent', 'customer');
        $returnTo = $request->input('return_to', $request->header('referer', '/'));

        $statePayload = base64_encode(json_encode([
            'host'      => $tenantHost,
            'intent'    => $intent,
            'return_to' => $returnTo,
        ]));

        $redirectUrl = Socialite::driver('google')
            ->with(['state' => $statePayload])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $redirectUrl]);
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $stateDecoded = base64_decode($request->input('state'));
            $stateData = json_decode($stateDecoded, true);

            $returnTo = '/';
            if (is_array($stateData)) {
                $tenantHost = $stateData['host'];
                $intent = $stateData['intent'] ?? 'team';
                $returnTo = $stateData['return_to'] ?? '/';
            } else {
                $tenantHost = $stateDecoded;
                $intent = 'team';
            }

            if ($tenantHost === 'adm.brasume.com.br') {
                $user = User::where('email', $googleUser->getEmail())->first();

                if (! $user) {
                    return redirect()->route('filament.central.auth.login')->with('error', 'Acesso negado. Administrador do SaaS não encontrado.');
                }

                Auth::login($user);
                return redirect()->to('/admin');
            }

            $payload = json_encode([
                'email'      => $googleUser->getEmail(),
                'name'       => $googleUser->getName(),
                'intent'     => $intent,
                'return_to'  => $returnTo,
                'expires_at' => now()->addMinutes(5)->timestamp
            ]);

            $token = urlencode(Crypt::encryptString($payload));

            if ($intent === 'customer') {
                return redirect("https://{$tenantHost}/auth/google/magic-login-customer?token={$token}");
            }
            return redirect("https://{$tenantHost}/auth/google/magic-login-team?token={$token}");

        } catch (\Exception $e) {
            Log::error('🔥 [GOOGLE OAUTH CALLBACK ERROR] ' . $e->getMessage());
            return redirect('/')->with('error', 'Falha ao autenticar com o Google. O processo foi cancelado.');
        }
    }

    public function magicLoginCustomer(Request $request, GoogleAuthService $authService, CustomerRegistrationService $registrationService)
    {
        $token = $request->query('token');

        try {
            $result = $authService->processCustomerLogin($token, session('guest_customer_id'), $registrationService);

            $targetUrl = $result['return_to'] ?? '/';

            // Higienização de segurança para evitar loops
            if (empty($targetUrl) || str_contains($targetUrl, '/auth/google')) {
                $targetUrl = '/';
            }

            if ($result['status'] === 'requires_cpf') {
                $targetUrl = preg_replace('/([?&])open_upgrade=[^&]+(&|$)/', '$1', $targetUrl);
                $targetUrl = rtrim($targetUrl, '?&');

                $separator = str_contains($targetUrl, '?') ? '&' : '?';
                $targetUrl .= $separator . 'open_upgrade=true';

                return redirect()->to($targetUrl)->with('info', $result['message']);
            }

            $request->session()->regenerate();
            $request->session()->save();

            return redirect()->to($targetUrl)->with('success', $result['message']);

        } catch (\Exception $e) {
            Log::error('🔥 [ERRO CRÍTICO] Falha no Magic Login B2C: ' . $e->getMessage());
            return redirect()->to('/')->with('error', $e->getMessage());
        }
    }

    public function magicLoginTeam(Request $request, GoogleAuthService $authService)
    {
        $token = $request->query('token');

        try {
            $result = $authService->processTeamLogin($token);
            $request->session()->regenerate();
            $request->session()->save();

            return redirect()->to($result['redirect'] ?? '/team');

        } catch (\Exception $e) {
            Log::error('🛑 [GOOGLE TEAM ERROR] ' . $e->getMessage());
            return redirect()->route('team.login')->with('error', $e->getMessage());
        }
    }
}
```
