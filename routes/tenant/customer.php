<?php

use Illuminate\Support\Facades\Route;
use App\Models\CompanySetting;
use App\Http\Controllers\Tenant\Apps\CustomerAppController;
use App\Http\Controllers\Tenant\Terminals\CustomerCheckoutController;
use App\Http\Controllers\Tenant\Terminals\CustomerReorderController;
use App\Http\Controllers\Tenant\Marketing\CouponController;
use App\Http\Controllers\Tenant\Customers\CustomerController;
use App\Http\Controllers\Tenant\Customers\OrderRatingController;
use App\Http\Controllers\Tenant\Auth\LogoutController;
use App\Http\Controllers\Tenant\Terminals\CustomerLoginController;
use App\Http\Controllers\Tenant\Terminals\CustomerRegistrationController;
use App\Http\Controllers\Tenant\Terminals\CustomerGuestController;
use App\Http\Controllers\Tenant\Terminals\WhatsAppAuthController;
use App\Http\Controllers\Tenant\Orders\OrderClaimController;

// ==========================================
// 🚀 SPLASH E ATALHOS AMIGÁVEIS (NÍVEL RAIZ)
// ==========================================
Route::get("/", fn () => \Inertia\Inertia::render('Splash/Index'))->name("tenant.root");
Route::get('/cardapio', fn () => redirect()->to('/customer/menu' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')));
Route::get('/promocoes', fn () => redirect()->to('/customer/menu' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')));

// ⭐ ATALHO AMIGÁVEL DE AVALIAÇÃO NO GOOGLE
Route::get('/avaliacao', function () {
    $setting = CompanySetting::getCached() ?? CompanySetting::first();
    $targetUrl = $setting?->google_review_link;

    if (!empty($targetUrl)) {
        return redirect()->away($targetUrl);
    }

    return redirect()->to('/cardapio');
})->name('tenant.review');

// 📲 ROTA DO QR CODE DA MESA (Autoatendimento)
Route::get('/mesa/{hash}', [CustomerAppController::class, 'resolveTableQrCode'])->name('customer.table.qr');

// 🎁 ROTA DO QR CODE DE RESGATE DE CASHBACK O2O (Comprovativo Térmico)
Route::get('/resgate/{hash}', [OrderClaimController::class, 'handleScan'])->name('customer.order.claim');

// ==========================================
// 📱 APLICAÇÃO DO CLIENTE (CUSTOMER APP)
// ==========================================
Route::prefix('customer')->group(function () {

    // 🔓 Autenticação B2C & Entrada
    Route::post("/login", [CustomerLoginController::class, "login"])->name("customer.auth.login");
    Route::post("/logout", [LogoutController::class, "destroy"])->name("customer.auth.logout");
    Route::post("/register", [CustomerRegistrationController::class, "register"])->name("customer.auth.register");
    Route::post("/upgrade-account", [CustomerRegistrationController::class, "upgrade"])->name("customer.auth.upgrade");
    Route::post("/auth/cancel-upgrade", [CustomerRegistrationController::class, "cancelPendingUpgrade"])->name("customer.auth.cancel-upgrade");

    Route::post("/whatsapp-otp/send", [WhatsAppAuthController::class, "sendOtp"])
        ->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ])
        ->name("customer.auth.whatsapp.send");

    Route::post("/whatsapp-otp/verify", [WhatsAppAuthController::class, "verifyOtp"])
        ->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ])
        ->name("customer.auth.whatsapp.verify");

    // 👤 Visitante (Guest Mode)
    Route::post("/guest-register", [CustomerGuestController::class, "register"])->name("customer.auth.guest-register");
    Route::post("/guest-restore", [CustomerGuestController::class, "restore"])->name("customer.auth.guest-restore");
    Route::post("/guest-address", [CustomerController::class, "storeGuestAddress"])->name("customer.auth.guest-address");

    // 🛒 Operações de Pedidos, Avaliações e Cupons
    Route::post("/checkout", [CustomerCheckoutController::class, "store"])->name("customer.checkout.store");
    Route::post("/reorder/{order}", [CustomerReorderController::class, "store"])->name("customer.reorder.store");
    Route::post("/orders/{order}/cancel", [CustomerCheckoutController::class, "cancel"])->name("customer.orders.cancel");
    Route::get("/orders/{order}/receipt", [CustomerCheckoutController::class, "receipt"])->name("customer.orders.receipt");
    Route::post("/orders/{order}/rate", [OrderRatingController::class, "store"])->name("customer.orders.rate");

    Route::get('/pos/coupons/available', [App\Http\Controllers\Tenant\Marketing\CouponController::class, 'getPosAvailableCoupons'])->name('pos.coupons.available');
    Route::post('/coupons/validate', [CouponController::class, 'validateCode'])->name('customer.coupons.validate');
    Route::get('/coupons/wallet', [CouponController::class, 'getWallet'])->name('customer.coupons.wallet');
    Route::post("/profile/toggle-notification", [CustomerController::class, "toggleOpeningNotification"])->name("customer.profile.toggle-notification");

    // 🎁 Resgate O2O de Pedidos e Cashback (Pós-login / cadastro)
    Route::post('/order/claim-pending', [OrderClaimController::class, 'processPendingClaim'])->name('customer.order.claim-pending');

    // 🔒 Gestão de Perfil Exclusiva de Contas Oficiais
    Route::middleware(['auth:web'])->group(function () {
        Route::post("/profile/update", [CustomerController::class, "updateProfile"])->name("customer.profile.update");
        Route::post("/profile/address", [CustomerController::class, "storeAddress"])->name("customer.profile.add-address");
        Route::post("/profile/address/deactivate/{address}", [CustomerController::class, "deactivateAddress"])->name("customer.profile.deactivate-address");
        Route::post("/delete-account", [CustomerController::class, "deleteAccount"])->name("customer.auth.delete-account");
    });

    // 🛎️ Chamar garçom na mesa pelo TableMode
    Route::post("/table/call-waiter", [CustomerAppController::class, "callWaiter"])->name("customer.table.call-waiter");

    // 🚀 SPA Catch-All do App do Cliente (Sempre no final deste grupo)
    Route::get("/{tab?}", [CustomerAppController::class, "index"])
        ->where('tab', '.*')
        ->name("customer.app");
});
