<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\CompanySetting;
use App\Models\CashShift;
use App\Models\PaymentMethod;
use App\Models\PrintJob;
use App\Services\RestaurantOps\StoreAvailabilityService;
use Illuminate\Support\Facades\Cache;
use Detection\MobileDetect;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        // 🚀 1. Detecta se estamos num inquilino ativo ou no domínio central
        $isTenant = function_exists('tenant') && tenant('id') !== null;

        // 🚀 2. Só lê do banco se houver um tenant ativo (evita erro 1146 no erp-central)
        $setting = $isTenant ? CompanySetting::getCached() : null;
        $themeColor = $setting->theme_color ?? 'blue';

        $teamMethods = is_array($setting?->team_auth_methods)
            ? $setting->team_auth_methods
            : ($setting?->team_auth_methods ? [$setting->team_auth_methods] : ['native']);

        // 🛡️ BLINDAGEM DE TIPO PARA O JSON/ARRAY
        $customerMethods = $setting?->customer_auth_methods;
        if (is_string($customerMethods)) {
            $customerMethods = json_decode($customerMethods, true) ?: [];
        }

        $companyFormatted = $isTenant ? [
            'name'                    => $setting->company_name ?? 'Nossa Loja',
            'logo'                    => $setting?->logo_path,
            'allowGuest'              => (bool) ($setting->customer_allow_guest ?? true),
            'guestRestrictedPayments' => (bool) ($setting->guest_restricted_payments ?? true),
            'require_whatsapp'        => (bool) ($setting->require_whatsapp ?? false),
            'customerAuthMethods'     => is_array($customerMethods) ? $customerMethods : [],
            'teamAuthMethods'         => $teamMethods,
            'cashierAuthMethods'      => $setting->cashier_auth_methods ?? ['native', 'qr_code'],
            'kdsAuthMethods'          => $setting->kds_auth_methods ?? ['pin', 'qr_code'],
        ] : null;

        $detect = new MobileDetect();
        $os = $detect->isiOS() ? 'ios' : ($detect->isAndroidOS() ? 'android' : 'desktop');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => function () use ($request) {
                    $user = auth('team')->user() ?? auth('web')->user() ?? $request->user();
                    if (! $user) return null;

                    $userData = $user->toArray();
                    if (method_exists($user, 'getRoleNames')) {
                        $roleNames = $user->getRoleNames()->toArray();
                        $userData['roles'] = $roleNames;
                        $userData['role'] = $roleNames[0] ?? ($user->role ?? null);
                    } else {
                        $userData['roles'] = isset($user->role) ? [$user->role] : [];
                        $userData['role'] = $user->role ?? null;
                    }
                    return $userData;
                },
            ],

            'device' => [
                'is_mobile'  => $detect->isMobile(),
                'is_tablet'  => $detect->isTablet(),
                'is_desktop' => !$detect->isMobile() && !$detect->isTablet(),
                'os'         => $os,
            ],

            // 🚀 Formas de Pagamento filtradas cirurgicamente por canal
            'paymentMethods' => function () use ($isTenant, $request) {
                if (! $isTenant) return [];

                $isCustomerRoute = $request->is('/')
                    || $request->is('customer*')
                    || $request->is('mesa*')
                    || $request->routeIs('customer.*');

                $isPosRoute = $request->is('pos*')
                    || $request->routeIs('pos.*')
                    || $request->is('operations*')
                    || $request->is('team*');

                $channel = $isCustomerRoute ? 'delivery' : ($isPosRoute ? 'pos' : 'all');

                return Cache::remember(
                    'tenant_payment_methods_' . tenant('id') . '_' . $channel,
                    now()->addMinutes(15),
                    function () use ($isCustomerRoute, $isPosRoute) {
                        $query = PaymentMethod::where('is_active', true)
                            ->where('engine_type', '!=', 'internal_loyalty');

                        if ($isCustomerRoute) {
                            $query->where('show_on_delivery', true);
                        } elseif ($isPosRoute) {
                            $query->where('show_on_pos', true);
                        }

                        return $query->orderBy('id', 'asc')->get();
                    }
                );
            },

            // 🚀 Status da Loja (só roda se houver loja ativa)
            'storeStatus' => function () use ($isTenant) {
                if (! $isTenant) {
                    return null;
                }

                try {
                    return app(StoreAvailabilityService::class)->getStoreStatus();
                } catch (\Throwable) {
                    return [
                        'is_open'         => false,
                        'is_pre_order'    => false,
                        'active_channels' => ['delivery', 'counter', 'table'],
                        'next_open_time'  => null,
                        'last_closed_at'  => null,
                        'reason'          => 'system_error',
                    ];
                }
            },

            'flash' => [
                'success'         => fn () => $request->session()->get('success'),
                'error'           => fn () => $request->session()->get('error'),
                'info'            => fn () => $request->session()->get('info'),
                'warning'         => fn () => $request->session()->get('warning'),
                'pix_data'        => fn () => $request->session()->get('pix_data'),
                'order_id'        => fn () => $request->session()->get('order_id'),
                'createdCustomer' => fn () => $request->session()->get('createdCustomer'),
            ],

            'tenant'          => $isTenant ? tenant() : null,
            'tenantId'        => $isTenant ? tenant('id') : 'central',
            'theme_color'     => $themeColor,
            'companySettings' => $setting,
            'company'         => $companyFormatted,

            // 🚀 Consultas protegidas contra execução fora de um tenant
            'hasOpenShift'    => fn () => $isTenant ? CashShift::where('status', 'open')->exists() : false,
            'failedJobsCount' => fn () => $isTenant ? PrintJob::where('status', 'failed')->count() : 0,
            'vapidPublicKey'  => config('webpush.vapid.public_key'),
        ]);
    }
}
