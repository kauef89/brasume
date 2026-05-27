<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Livewire\Livewire;
use App\Http\Middleware\HandleInertiaRequests;

// 🚀 Nossos Controladores Roteados para a Pasta Tenant
use App\Http\Controllers\Tenant\PosController;
use App\Http\Controllers\Tenant\OperationsController;
use App\Http\Controllers\Tenant\CheckoutController;
use App\Http\Controllers\Tenant\FiscalDocumentController;
use App\Http\Controllers\Tenant\LegacyOrderPrintController;
use App\Http\Controllers\Tenant\CashierController;

if (
    !app()->runningInConsole() &&
    in_array(request()->getHost(), config("tenancy.central_domains"))
) {
    return;
}

Livewire::setUpdateRoute(function ($handle) {
    return Route::post("/livewire/update", $handle)->middleware([
        "web",
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
    ]);
});

Livewire::setScriptRoute(function ($handle) {
    return Route::get("/livewire/livewire.js", $handle)->middleware([
        "web",
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
    ]);
});

Route::middleware([
    "web",
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // ---------------------------------------------------------
    // REDIRECIONAMENTOS BASE
    // ---------------------------------------------------------

    // Se alguém acessar a raiz do subdomínio, manda para o Admin (onde o Filament cuida do Login)
    Route::get("/", function () {
        return redirect("/admin");
    })->name("home");

    // Garantia de redirect de auth legacy
    Route::get("/login", function () {
        return redirect("/admin/login");
    })->name("login");

    // ---------------------------------------------------------
    // RELATÓRIOS E IMPRESSÕES (SEM INERTIA)
    // ---------------------------------------------------------
    Route::get("/reports/legacy-danfe/{id}", [
        FiscalDocumentController::class, // 🚀 Atualizado aqui
        "downloadLegacyDanfe",
    ])->name("tenant.danfe.legacy");

    Route::get("/admin/legacy-orders/{id}/print", [
        LegacyOrderPrintController::class,
        "print",
    ])->name("tenant.legacy-orders.print");

    // ---------------------------------------------------------
    // ROTAS AUTENTICADAS (COM VUE/INERTIA)
    // ---------------------------------------------------------
    Route::middleware(["auth", HandleInertiaRequests::class])->group(
        function () {
            // 🚀 FRENTE DE CAIXA (POS / PDV)
            Route::get("/pdv", [PosController::class, "terminal"])->name("pdv.index");
            Route::get("/pos", [PosController::class, "index"])->name("pos.index");
            Route::post("/pos/checkout", [PosController::class, "store"])->name("pos.store");

            // 🚀 GESTÃO DE SALÃO E CHECKOUT
            Route::prefix("operations")->group(function () {

                // Gestão de Status e Kanban
                Route::get("/", [OperationsController::class, "index"])->name("operations.index");
                Route::post("/orders/{order}/status", [OperationsController::class, "updateStatus"])->name("operations.update-status");

                // Checkout e Manipulação Rápida
                Route::get("/orders/{order}/checkout", [CheckoutController::class, "show"])->name("operations.checkout");
                Route::post("/orders/{order}/quick-add", [CheckoutController::class, "quickAdd"])->name("operations.quick-add");
                Route::post("/orders/{order}/cancel-item", [CheckoutController::class, "cancelItem"])->name("operations.cancel-item");

                // Processamento do Pagamento e Fecho de Conta
                Route::post("/orders/{order}/pay", [CheckoutController::class, "processPayment"])->name("operations.pay");

                // 🚀 FLUXO DE CAIXA (CASHIER) - Rotas novas corrigidas para não duplicar o /operations
                Route::get("/cashier", [CashierController::class, "index"])->name("operations.cashier");
                Route::post("/cashier/open", [CashierController::class, "open"])->name("operations.cashier.open");
                Route::post("/cashier/close", [CashierController::class, "close"])->name("operations.cashier.close");
                Route::post("/cashier/transaction", [CashierController::class, "transaction"])->name("operations.cashier.transaction");

                // Botão de Estornar e Clonar (da tela do Caixa)
                Route::post("/orders/{order}/refund-and-clone", [CashierController::class, "refundAndClone"])->name("operations.refund-and-clone");
                Route::post('/operations/orders/{order}/update-delivery-payment', [App\Http\Controllers\Tenant\OperationsController::class, 'updateDeliveryPayment']);
            });
        }
    );
});
