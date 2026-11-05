<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Tenant\Orders\OrderController;
use App\Http\Controllers\Tenant\Orders\OrderStatusController;
use App\Http\Controllers\Tenant\Orders\OrderLogisticsController;
use App\Http\Controllers\Tenant\Orders\OrderTransferController;
use App\Http\Controllers\Tenant\Orders\OrderPrintController;
use App\Http\Controllers\Tenant\Orders\OrderCreationController;
use App\Http\Controllers\Tenant\Orders\OrderBillController;
use App\Http\Controllers\Tenant\Orders\CheckoutPaymentController;
use App\Http\Controllers\Tenant\Orders\CheckoutFiscalController;
use App\Http\Controllers\Tenant\Orders\OrderAppendController;
use App\Http\Controllers\Tenant\RestaurantOps\CashierController;

use App\Http\Controllers\Tenant\Operations\ProcurementController;
use App\Http\Controllers\Tenant\Operations\ProductionController;
use App\Http\Controllers\Tenant\Operations\InventoryMovementController;
use App\Http\Controllers\Tenant\Operations\TableController;
use App\Http\Controllers\Tenant\Operations\WmsRestockController;
use App\Http\Controllers\Tenant\Operations\ScheduleController;

use App\Http\Controllers\Tenant\RestaurantOps\KdsProductionController;
use App\Http\Controllers\Tenant\RestaurantOps\PrintQueueController;
use App\Http\Controllers\Tenant\RestaurantOps\EmployeeWalletController;
use App\Http\Controllers\Tenant\RestaurantOps\ChecklistApiController;

use App\Http\Controllers\Tenant\Catalog\AvailabilityController;
use App\Http\Controllers\Tenant\Customers\CustomerController;
use App\Http\Controllers\Tenant\Marketing\CrmController;

Route::prefix('operations')->group(function () {
    // ==========================================
    // 🔗 REDIRECIONAMENTOS BÁSICOS
    // ==========================================
    Route::get('/', fn () => redirect()->to('/team' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')))->name('operations.index');
    Route::get('/crm', fn () => redirect()->to('/team' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')))->name('operations.crm.index');

    // ==========================================
    // 🔓 PRÉ-TURNO (Sem exigência de ponto)
    // ==========================================
    Route::withoutMiddleware([\App\Http\Middleware\EnsureShiftAttendance::class])->group(function () {
        Route::get('/availability', [AvailabilityController::class, 'index'])->name('operations.availability');
        Route::get('/carteira', [EmployeeWalletController::class, 'index'])->name('employee.wallet');
    });

    // ==========================================
    // 📦 GESTÃO DE PEDIDOS (Orders)
    // ==========================================
    Route::prefix('orders')->group(function () {
        // Criação e Leitura
        Route::post('/', [OrderCreationController::class, 'store'])->name('operations.orders.store');
        Route::get('/{order}', [OrderController::class, 'show'])->name('operations.orders.show');
        Route::post('/{order}/toggle-rating', [OrderController::class, 'toggleRating'])->name('operations.orders.toggle-rating');
        Route::post('/{order}/append', [OrderAppendController::class, 'store'])->name('operations.orders.append');

        // Status & Cancelamentos
        Route::controller(OrderStatusController::class)->group(function () {
            Route::post('/{order}/status', 'update')->name('operations.orders.update-status');
            Route::post('/{order}/cancel', 'cancel')->name('operations.orders.cancel');
            Route::post('/{order}/cancel-item', 'cancelItem')->name('operations.orders.cancel-item');
            Route::post('/{order}/reactivate', 'reactivate')->name('operations.orders.reactivate');
        });

        // Logística & Delivery
        Route::controller(OrderLogisticsController::class)->group(function () {
            Route::post('/bulk-complete-dispatched', 'bulkCompleteDispatched')->name('operations.orders.bulk-complete-dispatched');
            Route::post('/{order}/transform', 'transform')->name('operations.orders.transform');
            Route::post('/{order}/update-delivery-payment', 'updateDeliveryPayment')->name('operations.orders.update-delivery-payment');
            Route::post('/{order}/change-address', 'changeAddress')->name('operations.orders.change-address');
            Route::post('/{order}/assign-courier', 'assignCourier')->name('operations.orders.assign-courier');
        });

        // Comanda, Carrinho e Conta
        Route::controller(OrderBillController::class)->group(function () {
            Route::get('/{order}/checkout', 'show')->name('operations.checkout');
            Route::post('/{order}/items', 'addItem')->name('operations.orders.items.store');
            Route::post('/{order}/quick-add', 'quickAdd')->name('operations.quick-add');
            Route::delete('/{order}/items/{item}', 'cancelItem')->name('operations.orders.items.destroy');
            Route::post('/{order}/discounts', 'applyDiscount')->name('operations.orders.discounts.apply');
            Route::delete('/{order}/discounts', 'removeDiscount')->name('operations.orders.discounts.remove');
            Route::post('/{order}/service-fee/toggle', 'toggleServiceFee')->name('operations.orders.service-fee.toggle');
            Route::post('/{order}/link-customer', 'linkCustomer')->name('operations.orders.link-customer');
        });

        // Pagamentos
        Route::controller(CheckoutPaymentController::class)->group(function () {
            Route::post('/{order}/pay', 'processPayment')->name('operations.orders.pay');
            Route::delete('/{order}/payments/{payment}', 'destroyPayment')->name('operations.orders.payments.destroy');
            Route::post('/{order}/generate-pix', 'generatePix')->name('operations.orders.generate-pix');
            Route::post('/{order}/cancel-pix-intent', 'cancelPixIntent')->name('operations.orders.cancel-intent');
        });

        // Emissão Fiscal & Conferência
        Route::post('/{order}/emit-nfce', [CheckoutFiscalController::class, 'emitNfce'])->name('operations.orders.emit-nfce');
        Route::post('/{order}/finalize-receipt', [CheckoutFiscalController::class, 'finalizeReceipt'])->name('operations.finalize-receipt');
        Route::post('/{order}/print-conference', [CheckoutFiscalController::class, 'printConference'])->name('operations.print-conference');
        Route::post('/{order}/print-delivery', [OrderPrintController::class, 'printDelivery'])->name('operations.orders.print-delivery');
        Route::post('/{order}/print-pix', [OrderPrintController::class, 'printPix'])->name('operations.orders.print-pix');

        // Estorno Financeiro
        Route::delete('/{order}/payments/{payment}/rollback', [CashierController::class, 'rollbackPayment'])->name('operations.orders.payments.rollback');

        // Operações Especiais
        Route::post('/transfer-items', [OrderTransferController::class, 'store'])->name('operations.transfer-items');
    });

    // ==========================================
    // 🗓️ ESCALAS E JORNADAS (Schedules)
    // ==========================================
    Route::prefix('schedules')->controller(ScheduleController::class)->group(function () {
        Route::get('/', fn () => redirect()->to('/pos/schedules' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')))->name('operations.schedules.index');
        Route::post('/batch', 'batchSave')->name('operations.schedules.batch');
        Route::post('/clone', 'cloneWeek')->name('operations.schedules.clone');
    });

    // ==========================================
    // 🪑 MESAS (Tables)
    // ==========================================
    Route::prefix('tables/{table}')->controller(TableController::class)->name('operations.tables.')->group(function () {
        Route::post('/status', 'updateStatus')->name('status');
        Route::post('/move', 'move')->name('move');
        Route::post('/join', 'join')->name('join');
        Route::post('/split', 'split')->name('split');
    });

    // ==========================================
    // 👥 CLIENTES E CRM (Customers & CRM)
    // ==========================================
    Route::prefix('crm')->controller(CrmController::class)->name('operations.crm.')->group(function () {
        Route::post('/toggle-automation', 'toggleAutomation')->name('toggle-automation');
        Route::post('/create-coupon', 'createCoupon')->name('create-coupon');
    });

    Route::prefix('customers')->controller(CustomerController::class)->name('operations.customers.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::post('/{customer}/addresses', 'storeAddress')->name('addresses.store');
        Route::post('/{customer}/addresses/{address}/deactivate', 'deactivateAddress')->name('addresses.deactivate');
    });

    // ==========================================
    // 📦 ESTOQUE E DISPONIBILIDADE
    // ==========================================
    Route::prefix('availability')->controller(AvailabilityController::class)->name('operations.availability.')->group(function () {
        Route::post('/toggle', 'toggle')->name('toggle');
        Route::post('/loss', 'registerLoss')->name('loss');
    });

    Route::prefix('inventory')->controller(InventoryMovementController::class)->name('operations.inventory.')->group(function () {
        Route::post('/batch-adjust', 'batchAdjust')->name('batch-adjust');
        Route::post('/{product}/adjust', 'adjust')->name('adjust');
    });

    // ==========================================
    // 🍳 KDS (Kitchen Display System)
    // ==========================================
    Route::prefix('kds')->controller(KdsProductionController::class)->name('operations.kds.')->group(function () {
        Route::get('/', fn () => redirect()->route('kds.index'))->name('index');
        Route::post('/items/bulk-print', 'bulkPrint')->name('bulk-print');
        Route::post('/items/{item}/status', 'updateStatus')->name('update-status');
        Route::post('/items/{item}/loss', 'registerLoss')->name('register-loss');
        Route::post('/items/{item}/cancel', 'cancelItem')->name('cancel-item');
        Route::post('/items/{item}/print', 'printItem')->name('print-item');
    });

    // ==========================================
    // ✅ CHECKLISTS & SOP
    // ==========================================
    Route::prefix('checklists')->controller(ChecklistApiController::class)->name('operations.checklists.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/overview', 'overview')->name('overview');
        Route::get('/management', 'managementIndex')->name('management');
        Route::get('/search-inventory', 'searchInventoryItems')->name('search-inventory');
        Route::post('/sync', 'syncTask')->name('sync');
        Route::post('/', 'storeChecklist')->name('store');
        Route::put('/{checklist}', 'updateChecklist')->name('update');
        Route::delete('/{checklist}', 'destroyChecklist')->name('destroy');
        Route::post('/{checklist}/tasks', 'storeTask')->name('tasks.store');
        Route::put('/tasks/{task}', 'updateTask')->name('tasks.update');
        Route::delete('/tasks/{task}', 'destroyTask')->name('tasks.destroy');
    });

    // ==========================================
    // 🖨️ FILA DE IMPRESSÃO (Printers)
    // ==========================================
    Route::prefix('printers')->controller(PrintQueueController::class)->name('operations.printers.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/retry', 'retryJobs')->name('retry');
        Route::post('/retry-all-failed', 'retryAllFailed')->name('retry-all');
        Route::post('/dismiss', 'dismissJobs')->name('dismiss');
        Route::post('/{printer}/fallback', 'setFallback')->name('fallback');
    });

    // ==========================================
    // 🛒 PRODUÇÃO E COMPRAS
    // ==========================================
    Route::controller(WmsRestockController::class)->group(function () {
        Route::get('/restock-board', 'index')->name('operations.restock-board');
        Route::post('/restock-board/stock', 'updateStock')->name('operations.restock-board.stock');
    });

    Route::controller(ProcurementController::class)->group(function () {
        Route::get('/shopping-list', 'index')->name('operations.shopping-list');
        Route::post('/shopping-list/remove', 'removeItem')->name('operations.shopping-list.remove');
        Route::post('/shopping-list/approve', 'approve')->name('operations.shopping-list.approve');
    });

    Route::controller(ProductionController::class)->group(function () {
        Route::get('/production-board', 'index')->name('operations.production-board');
        Route::post('/production-board/status', 'updateStatus')->name('operations.production-board.status');
        Route::post('/production-board/finish', 'finish')->name('operations.production-board.finish');
    });
});
