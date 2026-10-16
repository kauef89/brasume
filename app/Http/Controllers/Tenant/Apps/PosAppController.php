<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Services\Marketing\CrmService;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CashShift;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Street;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Product;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\RestaurantOps\OperationsMenuService;
use App\Services\RestaurantOps\ShiftAttendanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosAppController extends Controller
{
    public function index(
        Request $request,
        OperationsMenuService $menuService,
        OperationsDashboardService $opsService
    ): Response {
        $rawTab = $request->route('tab', 'new');
        $activeTab = in_array($rawTab, ['pos', 'new', '']) ? 'new' : $rawTab;
        $user = auth()->user();

        $data = $menuService->getOrderEntryData($request);
        $data['activeTab'] = $activeTab;
        $data['tab'] = $activeTab;
        $data['defaultTab'] = $activeTab;
        $data['initialStreets'] = fn () => Street::with('segments.neighborhood')->get();

        $data['initialOrders'] = fn () => $opsService->getActiveOrdersDashboard()['initialOrders'] ?? [];
        $data['couriers'] = fn () => User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['driver', 'motoboy', 'entregador']);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $shift = CashShift::where('status', 'open')->latest()->first();
        $attendanceService = app(ShiftAttendanceService::class);

        $data['cashierData'] = fn () => [
            'currentShift' => $shift,
            'managerData' => $opsService->getManagerShiftSummary($user),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'users' => $attendanceService->getShiftUsersData(),
            'currentAttendees' => $attendanceService->getCurrentAttendees($shift),
            'ordersHistory' => function () use ($shift) {
                if (!$shift || !$shift->opened_at) return [];

                return Order::with(['customer', 'payments.paymentMethod', 'employee', 'diningTable'])
                    ->where(function ($q) use ($shift) {
                        $q->whereHas('payments', function ($pq) use ($shift) {
                            $pq->where('cash_shift_id', $shift->id);
                        })
                        ->orWhere(function ($oq) use ($shift) {
                            $oq->where('created_at', '>=', $shift->opened_at)
                               ->when($shift->closed_at, fn ($sq) => $sq->where('created_at', '<=', $shift->closed_at))
                               ->where(function ($sub) {
                                   $sub->whereIn('payment_status', ['paid', 'partial'])
                                       ->orWhereIn('status', ['closed', 'completed']);
                               });
                        });
                    })
                    ->where(function ($filterOpen) {
                        $filterOpen->where('type', '!=', 'table')
                            ->orWhereIn('status', ['closed', 'completed'])
                            ->orWhereIn('payment_status', ['paid', 'partial'])
                            ->orWhereHas('payments');
                    })
                    ->latest()
                    ->get();
            },
            'transactionsHistory' => function () use ($shift) {
                if (!$shift || !$shift->opened_at || !$shift->bank_account_id) return [];

                return Transaction::where('bank_account_id', $shift->bank_account_id)
                    ->where('transaction_date', '>=', $shift->opened_at)
                    ->whereNull('order_id')
                    ->when($shift->closed_at, fn ($q) => $q->where('transaction_date', '<=', $shift->closed_at))
                    ->latest('transaction_date')
                    ->get();
            }
        ];

        $data['printerData'] = fn () => [
            'printers' => Printer::where('is_active', true)->get()->toArray(),
            'printJobs' => PrintJob::latest('id')->limit(50)->get()->toArray(),
        ];

        // 🚀 Com 'latestCostHistory' no with, os custos vêm em uma única query agregada
        $data['catalog'] = fn () => Product::with(['category', 'latestCostHistory'])
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        $data['crmData'] = fn () => app(CrmService::class)->getDashboardData();

        return Inertia::render('Pos/PosApp', $data);
    }
}
