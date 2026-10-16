<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\RestaurantOps\ShiftAttendanceService;
use App\Services\RestaurantOps\OperationsMenuService;
use App\Services\RestaurantOps\EmployeeWalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamAppController extends Controller
{
    public function index(
        Request $request,
        OperationsMenuService $menuService,
        OperationsDashboardService $opsService,
        ShiftAttendanceService $attendanceService,
        EmployeeWalletService $walletService
    ): Response {
        $user = auth()->user();

        // 🚀 Identifica se é motoboy
        $isDriver = false;
        if ($user) {
            $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [$user->role ?? ''];
            $isDriver = in_array('driver', $userRoles, true) || in_array('motoboy', $userRoles, true);
        }

        // Se a rota não especificou aba, motoristas abrem em 'driver'; os demais em 'menu'
        $defaultTabFallback = $isDriver ? 'driver' : 'menu';
        $activeTab = $request->route('tab', $defaultTabFallback);

        $data = $menuService->getOrderEntryData($request);
        $data['defaultTab'] = $activeTab;
        $data['isDriver'] = $isDriver;
        $data['isOnDuty'] = fn () => $user ? $attendanceService->checkIsOnDuty($user) : false;
        $data['initialOrders'] = fn () => $opsService->getActiveOrdersDashboard()['initialOrders'];
        $data['managerData'] = fn () => $opsService->getManagerShiftSummary($user);

        // Carteira corporativa
        $data['employeeWalletData'] = fn () => ($user && $user->employee_id)
            ? $walletService->getWalletDashboardData($user->employee_id)
            : ['balance' => 0];

        $data['catalog'] = fn () => Product::with(['category', 'storageLocations'])
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        return Inertia::render("Team/TeamApp", $data);
    }
}
