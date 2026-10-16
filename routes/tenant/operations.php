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

use App\Http\Controllers\Tenant\RestaurantOps\KdsProductionController;
use App\Http\Controllers\Tenant\RestaurantOps\ShiftAttendanceController;
use App\Http\Controllers\Tenant\RestaurantOps\PrintQueueController;
use App\Http\Controllers\Tenant\RestaurantOps\EmployeeWalletController;
use App\Http\Controllers\Tenant\RestaurantOps\ChecklistApiController;

use App\Http\Controllers\Tenant\Catalog\AvailabilityController;
use App\Http\Controllers\Tenant\Customers\CustomerController;
use App\Http\Controllers\Tenant\Marketing\CrmController;

Route::prefix("operations")->group(function () {

    Route::get("/", fn () => redirect()->to('/team' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')))->name("operations.index");
    Route::get("/crm", fn () => redirect()->to('/team' . (request()->getQueryString() ? '?' . request()->getQueryString() : '')))->name("operations.crm.index");

    // Rotas pré-turno
    Route::withoutMiddleware([\App\Http\Middleware\EnsureShiftAttendance::class])->group(function () {
        Route::get("/availability", [AvailabilityController::class, "index"])->name("operations.availability");
        Route::get('/carteira', [EmployeeWalletController::class, 'index'])->name('employee.wallet');
    });

    // Gestão de Pedidos
    Route::post("/orders", [OrderCreationController::class, "store"])->name("operations.orders.store");
    Route::get("/orders/{order}", [OrderController::class, "show"])->name("operations.orders.show");
    Route::post("/orders/{order}/toggle-rating", [OrderController::class, "toggleRating"])->name("operations.orders.toggle-rating");

    Route::post('/operations/orders/{order}/append', [OrderAppendController::class, 'store'])->name('operations.orders.append');

    Route::post("/orders/{order}/status", [OrderStatusController::class, "update"])->name("operations.orders.update-status");
    Route::post("/orders/{order}/cancel", [OrderStatusController::class, "cancel"])->name("operations.orders.cancel");
    Route::post("/orders/{order}/cancel-item", [OrderStatusController::class, "cancelItem"])->name("operations.orders.cancel-item");

    // Logística & Delivery
    Route::post("/orders/{order}/transform", [OrderLogisticsController::class, "transform"])->name("operations.orders.transform");
    Route::post("/orders/{order}/update-delivery-payment", [OrderLogisticsController::class, "updateDeliveryPayment"])->name("operations.orders.update-delivery-payment");
    Route::post("/orders/{order}/change-address", [OrderLogisticsController::class, "changeAddress"])->name("operations.orders.change-address");
    Route::post("/orders/{order}/assign-courier", [OrderLogisticsController::class, "assignCourier"])->name("operations.orders.assign-courier");
    Route::post("/orders/{order}/print-delivery", [OrderPrintController::class, "printDelivery"])->name("operations.orders.print-delivery");
    Route::post("/orders/bulk-complete-dispatched", [OrderLogisticsController::class, "bulkCompleteDispatched"])->name("operations.orders.bulk-complete-dispatched");

    // Mesas
    Route::post("/orders/transfer-items", [OrderTransferController::class, "store"])->name("operations.transfer-items");
    Route::post("/tables/{table}/status", [TableController::class, "updateStatus"])->name("operations.tables.status");
    Route::post("/tables/{table}/move", [TableController::class, "move"])->name("operations.tables.move");
    Route::post("/tables/{table}/join", [TableController::class, "join"])->name("operations.tables.join");
    Route::post("/tables/{table}/split", [TableController::class, "split"])->name("operations.tables.split");

    // Comanda do Salão / Balcão (OrderBillController)
    Route::get("/orders/{order}/checkout", [OrderBillController::class, "show"])->name("operations.checkout");
    Route::post("/orders/{order}/items", [OrderBillController::class, "addItem"])->name("operations.orders.items.store");
    Route::post("/orders/{order}/quick-add", [OrderBillController::class, "quickAdd"])->name("operations.quick-add");
    Route::delete("/orders/{order}/items/{item}", [OrderBillController::class, "cancelItem"])->name("operations.orders.items.destroy");
    Route::post("/orders/{order}/discounts", [OrderBillController::class, "applyDiscount"])->name("operations.orders.discounts.apply");
    Route::delete("/orders/{order}/discounts", [OrderBillController::class, "removeDiscount"])->name("operations.orders.discounts.remove");
    Route::post("/orders/{order}/service-fee/toggle", [OrderBillController::class, "toggleServiceFee"])->name("operations.orders.service-fee.toggle");
    Route::post("/orders/{order}/link-customer", [OrderBillController::class, "linkCustomer"])->name("operations.orders.link-customer");

    // Pagamentos e Emissão Fiscal
    Route::post("/orders/{order}/pay", [CheckoutPaymentController::class, "processPayment"])->name("operations.orders.pay");
    Route::delete("/orders/{order}/payments/{payment}", [CheckoutPaymentController::class, "destroyPayment"])->name("operations.orders.payments.destroy");
    Route::post("/orders/{order}/emit-nfce", [CheckoutFiscalController::class, "emitNfce"])->name("operations.orders.emit-nfce");
    Route::post('/operations/orders/{order}/generate-pix', [CheckoutPaymentController::class, 'generatePix'])->name('operations.orders.generate-pix');
    Route::post("/orders/{order}/finalize-receipt", [CheckoutFiscalController::class, "finalizeReceipt"])->name("operations.finalize-receipt");
    Route::post("/orders/{order}/print-conference", [CheckoutFiscalController::class, "printConference"])->name("operations.print-conference");
    Route::delete("/orders/{order}/payments/{payment}/rollback", [CashierController::class, 'rollbackPayment'])->name('operations.orders.payments.rollback');
    Route::post("/orders/{order}/reactivate", [OrderStatusController::class, "reactivate"])->name("operations.orders.reactivate");

    // CRM
    Route::post("/crm/toggle-automation", [CrmController::class, "toggleAutomation"])->name("operations.crm.toggle-automation");
    Route::post("/crm/create-coupon", [CrmController::class, "createCoupon"])->name("operations.crm.create-coupon");
    Route::post("/customers", [CustomerController::class, "store"])->name("operations.customers.store");
    Route::post("/customers/{customer}/addresses", [CustomerController::class, "storeAddress"])->name("operations.customers.addresses.store");
    Route::post("/customers/{customer}/addresses/{address}/deactivate", [CustomerController::class, "deactivateAddress"])->name("operations.customers.addresses.deactivate");

    // Estoque & Disponibilidade
    Route::post("/availability/toggle", [AvailabilityController::class, "toggle"])->name("operations.availability.toggle");
    Route::post("/availability/loss", [AvailabilityController::class, "registerLoss"])->name("operations.availability.loss");
    Route::post("/inventory/batch-adjust", [InventoryMovementController::class, "batchAdjust"])->name("operations.inventory.batch-adjust");
    Route::post("/inventory/{product}/adjust", [InventoryMovementController::class, "adjust"])->name("operations.inventory.adjust");

    // Ações do KDS
    Route::get("/kds", fn () => redirect()->route('kds.index'))->name("operations.kds");
    Route::post("/kds/items/{item}/status", [KdsProductionController::class, "updateStatus"])->name("operations.kds.update-status");
    Route::post("/kds/items/{item}/loss", [KdsProductionController::class, "registerLoss"])->name("operations.kds.register-loss");
    Route::post("/kds/items/{item}/cancel", [KdsProductionController::class, "cancelItem"])->name("operations.kds.cancel-item");
    // 🚀 IMPRESSÃO EM LOTE REGISTRADA ANTES DO PARÂMETRO {item}
    Route::post("/kds/items/bulk-print", [KdsProductionController::class, "bulkPrint"])->name("operations.kds.bulk-print");
    Route::post("/kds/items/{item}/print", [KdsProductionController::class, "printItem"])->name("operations.kds.print-item");

    // Checklists & SOP
    Route::get("/checklists", [ChecklistApiController::class, "index"])->name("operations.checklists.index");
    Route::get("/checklists/overview", [ChecklistApiController::class, "overview"])->name("operations.checklists.overview");
    Route::get("/checklists/management", [ChecklistApiController::class, "managementIndex"])->name("operations.checklists.management");
    Route::post("/checklists/sync", [ChecklistApiController::class, "syncTask"])->name("operations.checklists.sync");
    Route::post("/checklists", [ChecklistApiController::class, "storeChecklist"])->name("operations.checklists.store");
    Route::put("/checklists/{checklist}", [ChecklistApiController::class, "updateChecklist"])->name("operations.checklists.update");
    Route::delete("/checklists/{checklist}", [ChecklistApiController::class, "destroyChecklist"])->name("operations.checklists.destroy");
    Route::post("/checklists/{checklist}/tasks", [ChecklistApiController::class, "storeTask"])->name("operations.checklists.tasks.store");
    Route::put("/checklists/tasks/{task}", [ChecklistApiController::class, "updateTask"])->name("operations.checklists.tasks.update");
    Route::delete("/checklists/tasks/{task}", [ChecklistApiController::class, "destroyTask"])->name("operations.checklists.tasks.destroy");
    Route::get('/operations/checklists/search-inventory', [ChecklistApiController::class, 'searchInventoryItems'])->name('operations.checklists.search-inventory');

    // Impressoras
    Route::get('/printers', [PrintQueueController::class, 'index'])->name('operations.printers.index');
    Route::post('/printers/retry', [PrintQueueController::class, 'retryJobs'])->name('operations.printers.retry');
    Route::post('/printers/retry-all-failed', [PrintQueueController::class, 'retryAllFailed'])->name('operations.printers.retry-all');
    Route::post('/printers/dismiss', [PrintQueueController::class, 'dismissJobs'])->name('operations.printers.dismiss');
    Route::post('/printers/{printer}/fallback', [PrintQueueController::class, 'setFallback'])->name('operations.printers.fallback');

    // Suprimentos & Produção
    Route::get("/restock-board", [WmsRestockController::class, "index"])->name("operations.restock-board");
    Route::post("/restock-board/stock", [WmsRestockController::class, "updateStock"])->name("operations.restock-board.stock");
    Route::get("/shopping-list", [ProcurementController::class, "index"])->name("operations.shopping-list");
    Route::post("/shopping-list/remove", [ProcurementController::class, "removeItem"])->name("operations.shopping-list.remove");
    Route::post("/shopping-list/approve", [ProcurementController::class, "approve"])->name("operations.shopping-list.approve");

    Route::get("/production-board", [ProductionController::class, "index"])->name("operations.production-board");
    Route::post("/production-board/status", [ProductionController::class, "updateStatus"])->name("operations.production-board.status");
    Route::post("/production-board/finish", [ProductionController::class, "finish"])->name("operations.production-board.finish");
});
