<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Livewire\Livewire;
use App\Http\Controllers\Tenant\MediaServeController;
use App\Http\Middleware\EnsureShiftAttendance;

if (!app()->runningInConsole() && in_array(request()->getHost(), config("tenancy.central_domains", []))) {
    return;
}

Livewire::setUpdateRoute(fn ($handle) => Route::post("/livewire/update", $handle)->middleware(["web", InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class]));
Livewire::setScriptRoute(fn ($handle) => Route::get("/livewire/livewire.js", $handle)->middleware(["web", InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class]));

Route::middleware([
    "web",
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    Route::get('/media/file', [MediaServeController::class, 'serve'])->name('tenant.media.serve');

    Route::get('/storage/{path}', function (string $path) {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }
        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    })->where('path', '^(?!.*\.\.).*$')->name('tenant.storage');

    require base_path('routes/tenant/auth_and_base.php');
    require base_path('routes/tenant/api.php');

    Route::get('/termos-e-privacidade', fn () => inertia('Customer/Legal/PrivacyPolicy'))->name('customer.legal');

    require base_path('routes/tenant/customer.php');
    require base_path('routes/tenant/team.php');

    // 🚀 Turno Financeiro, Operacional e Equipe (Pré-turno e Fechamento)
    Route::middleware(["auth:team,web"])->group(function () {
        require base_path('routes/tenant/shift.php');
    });

    // 🚀 Rotas do ERP que exigem Turno Aberto com Ponto Batido
    Route::middleware(["auth:team,web", EnsureShiftAttendance::class])->group(function () {
        require base_path('routes/tenant/settings.php');
        require base_path('routes/tenant/operations.php');
    });
});

Route::post('/customer/addresses/{address}/location', [\App\Http\Controllers\Tenant\Customers\CustomerController::class, 'updateAddressLocation'])->name('customer.addresses.update-location');
