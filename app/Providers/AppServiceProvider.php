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
        // 📦 EVENTOS, LISTENERS E OBSERVERS
        // =========================================================
        Order::observe(OrderObserver::class);

        Event::listen(OrderCancelled::class, ProcessGatewayRefund::class);
        Event::listen(OrderCancelled::class, ReturnStockAndNotifyCatalog::class);
    }
}
