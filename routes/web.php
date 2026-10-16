<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\Api\WhatsAppWebhookController;

// ===================================================================
// 🏢 DOMÍNIO CENTRAL (adm.brasume.com.br) - Gestão do SaaS
// ===================================================================
// 🚀 BLINDAGEM: Garante que /login só vai para o Filament SE estiver no domínio principal!
foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->group(function () {
        Route::redirect("/", "/admin");
        Route::redirect("/login", "/admin/login");
    });
}

// ===================================================================
// 🌐 HUB CENTRAL DE PONTES DE AUTENTICAÇÃO
// ===================================================================
Route::get("/auth/google/callback", [GoogleController::class, "handleGoogleCallback"])
    ->name("google.callback")
    ->withoutMiddleware(['guest', \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class]);

Route::get("/auth/facebook/callback", [FacebookController::class, "handleFacebookCallback"])
    ->name("facebook.callback")
    ->withoutMiddleware(['guest', \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class]);

// ===================================================================
// 💬 WEBHOOK CENTRAL DO WHATSAPP (Meta Cloud API)
// ===================================================================
Route::match(['get', 'post'], '/api/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->name('central.webhooks.whatsapp')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ]);
