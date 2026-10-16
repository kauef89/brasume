<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\Apps\TeamDispatcherController;
use App\Http\Controllers\Tenant\Apps\PosAppController;
use App\Http\Controllers\Tenant\Apps\TeamAppController;
use App\Http\Controllers\Tenant\Apps\KdsAppController;
use App\Http\Controllers\Tenant\Terminals\PosOrderEntryController;

// ==========================================
// 🚀 ATALHOS AMIGÁVEIS E ALIASES DE NAVEGAÇÃO
// ==========================================
Route::middleware(['auth:team'])->group(function () {
    Route::get('/pdv', fn () => redirect()->to('/pos' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')));
    Route::get('/caixa', fn () => redirect()->to('/pos' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')));
    Route::get('/garcom', fn () => redirect()->to('/team/app' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')));
    Route::get('/cozinha', fn () => redirect()->to('/kds' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')));

    Route::get("/team/desktop/{tab?}", fn ($tab = null) => redirect()->to('/pos' . ($tab ? '/' . $tab : '') . (request()->getQueryString() ? '?' . request()->getQueryString() : '')))->where('tab', '.*')->name('team.desktop');
    Route::get("/team/mobile/{tab?}", fn ($tab = null) => redirect()->route('team.app', ['tab' => $tab]))->where('tab', '.*')->name('team.mobile');
    Route::get("/waiter/{tab?}", fn ($tab = null) => redirect()->route('team.app', ['tab' => $tab]))->where('tab', '.*')->name('waiter.index');
    Route::get("/team/kds/{kitchen_id?}", fn ($kitchen_id = null) => redirect()->route('kds.index', ['kitchen_id' => $kitchen_id]))->name('team.kds');
});

// ==========================================
// 👥 NÚCLEO DOS 3 APPS B2B DA OPERAÇÃO
// ==========================================
Route::middleware(['auth:team'])->group(function () {

    Route::get("/team", [TeamDispatcherController::class, "index"])->name("team.index");
    Route::post("/team/start-shift", [TeamDispatcherController::class, "startShift"])->name("team.start-shift");

    // 🖥️ 1. PosApp
    Route::get("/pos/{tab?}", [PosAppController::class, "index"])
        ->where('tab', '.*')
        ->name("pos.index");

    Route::get("/pos/order-entry", [PosOrderEntryController::class, "index"])
        ->name("pos.order-entry");

    // 📱 2. TeamApp
    Route::get("/team/app/{tab?}", [TeamAppController::class, "index"])
        ->where('tab', '.*')
        ->name("team.app");

    // 🍳 3. KdsApp
    Route::get("/kds/{kitchen_id?}", [KdsAppController::class, "index"])
        ->name("kds.index");
});
