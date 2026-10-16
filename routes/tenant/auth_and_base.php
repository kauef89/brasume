<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\DynamicManifestController;
use App\Http\Controllers\Tenant\Auth\TeamGatewayController;
use App\Http\Controllers\Tenant\Auth\PinAuthController;
use App\Http\Controllers\Tenant\Auth\QrAuthController;
use App\Http\Controllers\Tenant\Auth\RemoteUnlockController;
use App\Http\Controllers\Tenant\Auth\TwoFactorController;
use App\Http\Controllers\Tenant\RestaurantOps\BridgeApiController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\Tenant\Auth\LogoutController;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\Tenant\Auth\SocialLoginReceiverController;

// Manifest PWA
Route::get('/manifest.json', [DynamicManifestController::class, 'show']);

// Logout Unificado
Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

// Gateway B2C (Cliente)
Route::get("/customer/login", function () {
    if (auth()->check()) {
        return redirect()->intended('/customer/home');
    }

    $url = session()->get('url.intended', '/customer/home');
    $separator = str_contains($url, '?') ? '&' : '?';
    return redirect($url . $separator . 'open_login=true');
})->name("login");

// Gateways B2B (Equipe)
Route::get("/pos/login", [TeamGatewayController::class, 'show'])->name("pos.login");
Route::get("/waiter/login", [TeamGatewayController::class, 'show'])->name("waiter.login");
Route::get("/kds/login", [TeamGatewayController::class, 'show'])->name("kds.login");
Route::get("/team/login", [TeamGatewayController::class, 'show'])->name("team.login");

// 🔙 Aliases de compatibilidade para evitar RouteNotFoundException
Route::get("/team/desktop/login", fn () => redirect()->route('pos.login'))->name("team.desktop.login");
Route::get("/team/mobile/login", fn () => redirect()->route('waiter.login'))->name("team.mobile.login");

// 🔒 PIN com Blindagem contra Força Bruta (Máximo 5 tentativas por minuto por IP)
Route::prefix('auth/pin')->middleware(['throttle:5,1'])->group(function () {
    Route::post('/login', [PinAuthController::class, 'login'])->name('auth.pin.login');
    Route::post('/login-email', [PinAuthController::class, 'loginWithEmail'])->name('auth.pin.login-email');
});

// Autenticação QR
Route::prefix('auth/qr')->group(function () {
    Route::post('/exchange', [QrAuthController::class, 'exchangeToken'])->name('auth.qr.exchange');
    Route::middleware(['auth:web,team'])->post('/authorize', [QrAuthController::class, 'authorizeLogin'])->name('auth.qr.authorize');
    Route::post('/validate-device', [QrAuthController::class, 'validateDevice'])->name('auth.qr.validate');
});

// Controle de Frota (Liberação Remota)
Route::prefix('auth/remote')->group(function () {
    Route::post('/request', [RemoteUnlockController::class, 'requestUnlock'])->name('auth.remote.request');
    Route::post('/exchange', [RemoteUnlockController::class, 'exchange'])->name('auth.remote.exchange');
    Route::post('/register-qr', [RemoteUnlockController::class, 'registerQrSession'])->name('auth.remote.register-qr');

    Route::middleware(['auth:team'])->group(function () {
        Route::get('/fleet', [RemoteUnlockController::class, 'fleet'])->name('auth.remote.fleet');
        Route::post('/approve', [RemoteUnlockController::class, 'approve'])->name('team.remote-unlock.approve');
        Route::post('/lock', [RemoteUnlockController::class, 'lock'])->name('team.remote-unlock.lock');
    });
});

Route::middleware(['auth:web,team'])
    ->get('/team/quick-unlock/{token}', [RemoteUnlockController::class, 'handleQuickUnlockScan'])
    ->name('team.quick-unlock');

// 2FA
Route::middleware(['auth:web,team'])->group(function () {
    Route::get('/auth/2fa', [TwoFactorController::class, 'showChallenge'])->name('auth.2fa.challenge');
    Route::post('/auth/2fa/verify', [TwoFactorController::class, 'verifyChallenge'])->name('auth.2fa.verify');
});

// Spooler Local (Bridge API)
Route::prefix('api/bridge')->group(function () {
    Route::post('/handshake', [BridgeApiController::class, 'handshake']);
    Route::get('/printers', [BridgeApiController::class, 'getPrinters']);
    Route::post('/jobs/{id}/status', [BridgeApiController::class, 'updateJobStatus']);
});

// Google OAuth
Route::post("/auth/google/get-url", [GoogleController::class, "getGoogleUrl"])->name("google.url");
Route::get("/auth/google/redirect", [GoogleController::class, "redirectToGoogle"])
    ->name("google.redirect")
    ->withoutMiddleware(['guest', \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class]);

Route::get("/auth/google/magic-login-team", [GoogleController::class, "magicLoginTeam"])
    ->name("auth.magic.team")
    ->withoutMiddleware(['guest', \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class]);

Route::get("/auth/google/magic-login-customer", [GoogleController::class, "magicLoginCustomer"])
    ->name("auth.magic.customer")
    ->withoutMiddleware(['guest', \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class]);

// Rota para o Vue requisitar a URL de login do Facebook
Route::post('/auth/facebook/url', [FacebookController::class, 'getFacebookUrl'])->name('facebook.url');

// Receptor do Magic Login no tenant
Route::get('/auth/social/magic-login', [SocialLoginReceiverController::class, 'magicLogin'])->name('social.magic-login');
