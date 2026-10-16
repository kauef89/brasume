<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\Terminals\PosSettingsController;

// 🚀 PROTEÇÃO SPATIE: Apenas Gestão
Route::middleware(['role:admin|gerente|manager'])->group(function () {
    Route::get('/settings', [PosSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/reorder', [PosSettingsController::class, 'reorder'])->name('settings.reorder');
});
