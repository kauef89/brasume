<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth:web,team']]
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->append(\App\Http\Middleware\TracePwaAuthScope::class);

        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'api/bridge/*',
            '*/api/bridge/*',
            'livewire/*',
            'api/webhooks/*',
            '*/api/webhooks/*',
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CaptureMarketingTraffic::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // ========================================================
        // 🛑 GUARDA 1: ISOLAMENTO DE CONTEXTO (SESSÃO EXPIRADA)
        // ========================================================
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin*')) {
                if (in_array($request->getHost(), config('tenancy.central_domains', []))) {
                    return route('filament.central.auth.login');
                }
                return route('filament.store.auth.login');
            }

            if ($request->is('kds*') || $request->is('cozinha')) {
                return route('kds.login');
            }

            if ($request->is('team/mobile*') || $request->is('garcom') || $request->is('waiter*')) {
                return route('waiter.login');
            }

            if ($request->is('team/desktop*') || $request->is('pos*') || $request->is('pdv') || $request->is('caixa')) {
                return route('pos.login');
            }

            if ($request->is('team*') || $request->is('operations*')) {
                return route('team.login');
            }

            return route('login');
        });

        // ========================================================
        // 🚦 GUARDA 2: BLOQUEIO DE LOGIN DUPLO (JÁ LOGADOS)
        // ========================================================
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin*')) {
                return '/admin';
            }

            if ($request->is('kds*') && auth('team')->check()) {
                return '/kds';
            }

            // 🚀 CORREÇÃO PWA: Se o usuário estiver no PDV/Caixa, mantém em /pos sem pular para /team
            if (auth('team')->check()) {
                if ($request->is('pos*') || $request->is('team/desktop*') || $request->is('caixa*') || $request->is('pdv*')) {
                    return '/pos';
                }
                return '/team';
            }

            return '/customer/home';
        });

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 🚀 1. NÃO LOGAR tentativas de acesso em domínios inexistentes / bots
        $exceptions->dontReport([
            TenantCouldNotBeIdentifiedOnDomainException::class,
        ]);

        $exceptions->reportable(function (\Throwable $e) {
            if (request()->is('horizon*')) {
                Log::error('--- 🚨 EXCEÇÃO NO HORIZON ---', [
                    'erro' => $e->getMessage(),
                    'classe_do_erro' => get_class($e),
                ]);
            }
        });

        // 🚀 2. RESPOSTA 404 SEGURA para domínios de tenant inválidos
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Restaurante ou ambiente não encontrado.'], 404);
            }

            return response(
                '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>404 - Restaurante Não Encontrado</title><style>body{font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#090d16;color:#94a3b8;flex-direction:column;text-align:center;padding:20px}h1{color:#f8fafc;font-size:1.75rem;margin:0 0 10px;font-weight:800}p{font-size:0.95rem;margin:0 0 24px;max-width:420px;line-height:1.5}a{display:inline-block;background:#f59e0b;color:#111;font-weight:800;padding:12px 24px;border-radius:14px;text-decoration:none;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.05em}</style></head><body><h1>404 • Unidade Não Encontrada</h1><p>O endereço acessado não corresponde a nenhum restaurante ou ambiente cadastrado no sistema.</p><a href="https://brasume.com.br">Voltar ao Início</a></body></html>',
                404,
                ['Content-Type' => 'text/html']
            );
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if ($request->is('api/*') || $request->is('admin*')) {
                return null;
            }

            if ($status === 419) {
                return back()->with([
                    'error' => 'A sua sessão expirou por inatividade. Por favor, tente novamente.'
                ]);
            }

            if (in_array($status, [403, 404, 500, 503])) {
                $context = match (true) {
                    $request->is('kds*') || $request->is('cozinha*') => [
                        'url'   => '/kds',
                        'label' => 'Cozinha (KDS)',
                    ],
                    $request->is('team/mobile*') || $request->is('garcom*') || $request->is('waiter*') => [
                        'url'   => '/team/mobile',
                        'label' => 'Atendimento Garçom',
                    ],
                    $request->is('team/desktop*') || $request->is('pos*') || $request->is('pdv*') || $request->is('caixa*') => [
                        'url'   => '/pos',
                        'label' => 'Frente de Caixa (PDV)',
                    ],
                    $request->is('team*') || $request->is('operations*') => [
                        'url'   => '/team',
                        'label' => 'Painel Operacional',
                    ],
                    $request->is('customer*') || $request->is('cardapio*') || $request->is('menu*') => [
                        'url'   => '/customer/home',
                        'label' => 'Cardápio',
                    ],
                    auth('team')->check() => [
                        'url'   => '/team',
                        'label' => 'Painel Operacional',
                    ],
                    default => [
                        'url'   => '/',
                        'label' => 'Início',
                    ],
                };

                $previousUrl = url()->previous();
                $safeBackUrl = ($previousUrl && $previousUrl !== $request->fullUrl())
                    ? $previousUrl
                    : $context['url'];

                return inertia('Shared/ErrorPage', [
                    'status'     => $status,
                    'home_url'   => $context['url'],
                    'app_label'  => $context['label'],
                    'return_url' => $safeBackUrl,
                ])
                ->toResponse($request)
                ->setStatusCode($status);
            }

            return null;
        });
    })->create();
