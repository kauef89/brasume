<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\Customers\StreetController;
use App\Http\Controllers\Tenant\Customers\CustomerController;
use App\Http\Controllers\Tenant\PushSubscriptionController;
use App\Http\Controllers\Tenant\Financial\SicrediWebhookController;
use App\Http\Controllers\Tenant\QrCodeDownloadController;
use App\Http\Controllers\Tenant\PredictiveMatrixController;
use App\Http\Controllers\Tenant\Catalog\TopSellingController;

Route::prefix("api")->group(function () {

    // ==========================================
    // 🏦 WEBHOOKS FINANCEIROS EXTERNOS DO TENANT
    // ==========================================
    Route::post('/webhooks/sicredi/pix', [SicrediWebhookController::class, 'handlePix'])
        ->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ])
        ->name('api.webhooks.sicredi');

    // ==========================================
    // 🌍 ROTAS PÚBLICAS DO CATÁLOGO / LOCALIZAÇÃO
    // ==========================================
    Route::get("/streets", [StreetController::class, "index"])->name("api.streets.index");
    Route::get("/customers/check-cpf", [CustomerController::class, "checkCpf"])->name("api.customers.check-cpf");
    Route::get("/top-combinations", [TopSellingController::class, "index"])->name("api.top-combinations");

    // ==========================================
    // 👤 DADOS DO CLIENTE (Acessível por Cliente e Equipe)
    // ==========================================
    Route::middleware('auth:web,team')->group(function () {
        Route::get('/customers/{customer}/favorites', [CustomerController::class, 'favorites'])
            ->name('api.customers.favorites');
    });

    // ==========================================
    // 🔔 NOTIFICAÇÕES PUSH (Acessível por Clientes e Equipe)

    // 🔔 NOTIFICAÇÕES PUSH (Acessível por Clientes e Equipe)
        Route::post("/push/subscribe", [PushSubscriptionController::class, "subscribe"])->name("api.push.subscribe");
        Route::post("/push/unsubscribe", [PushSubscriptionController::class, "unsubscribe"])->name("api.push.unsubscribe");

    // 🔒 ROTAS PROTEGIDAS DA EQUIPE & IA
    // ==========================================
    Route::middleware('auth:team')->group(function () {
        Route::get("/customers/search", [CustomerController::class, "search"])->name("api.customers.search");
        Route::get('/qr-code/download', [QrCodeDownloadController::class, 'download'])->name('api.qr-code.download');
        Route::get('/predictive-matrix', [PredictiveMatrixController::class, 'show'])->name('api.predictive-matrix');
    });
});
