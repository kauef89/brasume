<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\RestaurantOps\CashierController;
use App\Http\Controllers\Tenant\RestaurantOps\ShiftAttendanceController;

/*
|--------------------------------------------------------------------------
| Rotas de Gestão de Turnos (Shift Domain)
|--------------------------------------------------------------------------
*/
Route::prefix('operations')->group(function () {

    // ==========================================
    // 💵 1. TURNO FINANCEIRO (Caixa & Gaveta)
    // ==========================================
    Route::post('/cashier/open', [CashierController::class, 'open'])
        ->name('operations.cashier.open');

    Route::post('/cashier/close', [CashierController::class, 'close'])
        ->name('operations.cashier.close');

    Route::post('/cashier/transaction', [CashierController::class, 'transaction'])
        ->name('operations.cashier.transaction');

    Route::post('/cashier/shifts/{shift}/print-report', [CashierController::class, 'printShiftReport'])
        ->name('operations.cashier.print-shift-report');

    // Aliases
    Route::post('/shift/open', [CashierController::class, 'open'])->name('operations.shift.open');
    Route::post('/shift/close', [CashierController::class, 'close'])->name('operations.shift.close');

    // ==========================================
    // 🔄 2. TURNO OPERACIONAL (WMS & Status Loja)
    // ==========================================
    Route::post('/shift/close-operational', [CashierController::class, 'closeOperationalShift'])
        ->name('operations.shift.close-operational');

    // ==========================================
    // 👥 3. EQUIPE DO TURNO (Ponto, Escala & Diárias)
    // ==========================================
    Route::post('/attendance/sync', [ShiftAttendanceController::class, 'sync'])
        ->name('operations.attendance.sync');

    Route::get('/attendance/payout-details/{user}', [ShiftAttendanceController::class, 'payoutDetails'])
        ->name('operations.attendance.payout-details');

    Route::post('/attendance/pay', [ShiftAttendanceController::class, 'payFreelancer'])
        ->name('operations.attendance.pay');

    // ==========================================
    // ↩️ 4. ESTORNOS E AUDITORIA
    // ==========================================
    Route::post('/orders/{order}/rollback-payment/{payment}', [CashierController::class, 'rollbackPayment'])
        ->name('operations.cashier.rollback-payment');

    Route::post('/orders/{order}/refund-and-clone', [CashierController::class, 'refundAndClone'])
        ->name('operations.orders.refund-and-clone');
});
