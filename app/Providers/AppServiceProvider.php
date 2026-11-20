<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;

// 🚀 Modelos, Observadores e Listeners
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Models\InventoryMovement;
use App\Observers\InventoryMovementObserver;
use App\Events\OrderCancelled;
use App\Listeners\ProcessGatewayRefund;
use App\Listeners\ReturnStockAndNotifyCatalog;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (class_exists(\Stancl\Tenancy\Events\TenancyBootstrapped::class)) {
            \Illuminate\Support\Facades\Event::listen(
                \Stancl\Tenancy\Events\TenancyBootstrapped::class,
                fn ($event) => \Illuminate\Support\Facades\Context::add('tenant_id', $event->tenancy->tenant->getTenantKey())
            );
            \Illuminate\Support\Facades\Event::listen(
                \Stancl\Tenancy\Events\TenancyEnded::class,
                fn () => \Illuminate\Support\Facades\Context::forget('tenant_id')
            );
        }
        // Força HTTPS em produção/Cloudflare
        URL::forceScheme('https');

        // Formatação numérica brasileira (R$ 1.000,00)
        Number::useLocale('pt_BR');

        // =========================================================
        // 🚀 PREVENÇÃO E LOG DE N+1 (LAZY LOADING VIOLATIONS)
        // =========================================================
        Model::preventLazyLoading(!app()->isProduction());

        Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
            Log::warning("⚠️ [N+1 Detectado] Model [".get_class($model)."] carregou [{$relation}] preguiçosamente.", [
                'tenant' => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',
                'url'    => request()->fullUrl(),
            ]);
        });

        // =========================================================
        // ⏱️ MONITORAMENTO DE QUERIES LENTAS (> 50ms)
        // =========================================================
        DB::listen(function ($query) {
            if ($query->time > 50) {
                Log::warning("⏱️ [Query Lenta] {$query->time}ms em {$query->sql}", [
                    'bindings' => $query->bindings,
                    'tenant'   => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',
                    'url'      => request()->fullUrl(),
                ]);
            }
        });

        // =========================================================
        // 🛡️ PERMISSÕES E AUDITORIA
        // =========================================================
        Gate::define('viewHorizon', fn ($user = null) => true);

        Event::listen(function (RequestHandled $event) {
            if ($event->request->getHost() === 'adm.brasume.com.br') {
                Log::info('--- REQUISIÇÃO CENTRAL (' . now()->format('H:i:s') . ') ---');
                Log::info('URL: ' . $event->request->fullUrl());
                Log::info('Logado? ' . (auth()->check() ? 'SIM (ID: '.auth()->id().')' : 'NÃO'));

                if ($event->response->isRedirect()) {
                    Log::warning('=> Redirecionou para: ' . $event->response->getTargetUrl());
                }
            }
        });


        // =========================================================
        // 🖥️ UI GLOBAL FILAMENT: ROLAGEM INTERNA E CABEÇALHO FIXO NAS TABELAS
        // =========================================================
        if (class_exists(\Filament\Support\Facades\FilamentView::class) && class_exists(\Filament\View\PanelsRenderHook::class)) {
            \Filament\Support\Facades\FilamentView::registerRenderHook(
                \Filament\View\PanelsRenderHook::STYLES_AFTER,
                fn (): string => <<<'HTML'
<style id="fi-global-scrollable-tables">
    @media (min-height: 640px) {
        .fi-main .fi-ta-content-ctn:has(table.fi-ta-table) {
            max-height: clamp(260px, calc(100dvh - 22rem), 72dvh);
            overflow-y: auto;
            overflow-x: auto;
            overscroll-behavior: contain;
            scrollbar-width: thin;
        }

        .fi-modal .fi-ta-content-ctn:has(table.fi-ta-table) {
            max-height: none;
            overflow-y: visible;
        }

        .fi-main .fi-ta-content-ctn:has(table.fi-ta-table) table.fi-ta-table > thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: var(--gray-50, #f9fafb);
            box-shadow: 0 1px 0 0 rgba(0, 0, 0, 0.08);
        }

        .dark .fi-main .fi-ta-content-ctn:has(table.fi-ta-table) table.fi-ta-table > thead {
            background-color: var(--gray-900, #111827);
            box-shadow: 0 1px 0 0 rgba(255, 255, 255, 0.08);
        }

        .fi-main .fi-ta-content-ctn:has(table.fi-ta-table) table.fi-ta-table > tfoot {
            position: sticky;
            bottom: 0;
            z-index: 9;
            background-color: var(--gray-50, #f9fafb);
            box-shadow: 0 -1px 0 0 rgba(0, 0, 0, 0.08);
        }

        .dark .fi-main .fi-ta-content-ctn:has(table.fi-ta-table) table.fi-ta-table > tfoot {
            background-color: var(--gray-900, #111827);
            box-shadow: 0 -1px 0 0 rgba(255, 255, 255, 0.08);
        }
    }
</style>
HTML
            );
        }

        // =========================================================
        // 📦 EVENTOS, LISTENERS E OBSERVERS
        // =========================================================
        Order::observe(OrderObserver::class);
        InventoryMovement::observe(InventoryMovementObserver::class);

        Event::listen(OrderCancelled::class, ProcessGatewayRefund::class);
        Event::listen(OrderCancelled::class, ReturnStockAndNotifyCatalog::class);

        // =========================================================
        // 💾 BACKUP MULTI-TENANT DINÂMICO (SPATIE LARAVEL-BACKUP)
        // =========================================================
        if ($this->app->runningInConsole()) {
            Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) {
                if (!str_starts_with((string) $event->command, 'backup:')) {
                    return;
                }

                try {
                    $centralConn = config('database.default', 'mysql');
                    $baseConfig = config("database.connections.{$centralConn}");
                    $connections = [$centralConn];

                    foreach (\App\Models\Tenant::all() as $tenant) {
                        $dbName = $tenant->database()->getName();
                        if (!$dbName) {
                            continue;
                        }

                        $connName = 'backup_tenant_' . $tenant->id;
                        config([
                            "database.connections.{$connName}" => array_merge($baseConfig, [
                                'database' => $dbName,
                            ]),
                        ]);
                        $connections[] = $connName;
                    }

                    config(['backup.backup.source.databases' => array_unique($connections)]);
                } catch (\Throwable $e) {
                    Log::warning('[Backup] Falha ao registrar bancos dos tenants: ' . $e->getMessage());
                }
            });
        }
    }
}
