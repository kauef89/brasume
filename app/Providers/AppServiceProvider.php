<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // A sua importação original
use Illuminate\Support\Facades\Event; // Importações para o espião
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Força o Nginx/Cloudflare a usar HTTPS (Sua configuração original)
        URL::forceScheme('https');

        // =========================================================
        // O NOSSO ESPIÃO: Auditoria do Loop de Redirecionamento
        // =========================================================
        Event::listen(function (RequestHandled $event) {
            // Só queremos espiar o domínio central para não poluir o log
            if ($event->request->getHost() === 'app.brasume.com.br') {
                Log::info('--- NOVA REQUISIÇÃO (' . now()->format('H:i:s') . ') ---');
                Log::info('URL: ' . $event->request->fullUrl());
                Log::info('Logado? ' . (auth()->check() ? 'SIM (ID: '.auth()->id().')' : 'NÃO'));
                
                if ($event->response->isRedirect()) {
                    Log::warning('=> Redirecionou para: ' . $event->response->getTargetUrl());
                }
            }
        });
    }
}