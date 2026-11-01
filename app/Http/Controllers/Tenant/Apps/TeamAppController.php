<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\TeamOrderSubmitRequest;
use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Services\RestaurantOps\EmployeeWalletService;
use App\Services\RestaurantOps\MealAllowanceService;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\RestaurantOps\OperationsMenuService;
use App\Services\RestaurantOps\ShiftAttendanceService;
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
        EmployeeWalletService $walletService,
        MealAllowanceService $mealAllowanceService
    ): Response {
        $user = auth('team')->user() ?? auth()->user();

        $isDriver = false;
        if ($user) {
            $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [$user->role ?? ''];
            $isDriver = in_array('driver', $userRoles, true) || in_array('motoboy', $userRoles, true);
        }

        $defaultTabFallback = $isDriver ? 'driver' : 'menu';
        $activeTab = $request->route('tab', $defaultTabFallback);

        $activeShift = CashShift::where('status', 'open')->latest('opened_at')->first();

        $data = $menuService->getOrderEntryData($request);
        $data['defaultTab'] = $activeTab;
        $data['isDriver'] = $isDriver;
        $data['isOnDuty'] = fn () => $user ? $attendanceService->checkIsOnDuty($user) : false;
        $data['initialOrders'] = fn () => $opsService->getActiveOrdersDashboard()['initialOrders'];
        $data['managerData'] = fn () => $opsService->getManagerShiftSummary($user);

        // Turno e colaboradores ativos desacoplados no Service
        $data['currentShift'] = fn () => $activeShift;
        $data['currentAttendees'] = fn () => $activeShift ? $mealAllowanceService->getAttendeesWithMealStatus($activeShift) : [];

        // Carteira corporativa
        $data['employeeWalletData'] = fn () => ($user && $user->employee_id)
            ? $walletService->getWalletDashboardData($user->employee_id)
            : ['balance' => 0];

        // Catálogo com injeção do atributo 'price' para serialização correta no Vue
        $data['catalog'] = fn () => Product::with(['category', 'storageLocations', 'modifiers.options'])
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get()
            ->each(function ($product) {
                $product->setAttribute('price', (float) ($product->dine_in_price ?? $product->delivery_price ?? 0));
            });

        return Inertia::render("Team/TeamApp", $data);
    }

    /**
     * 🥪 Lançamento dos lanches da equipa (Vale-Alimentação)
     */
    public function submitMealAllowance(
        TeamOrderSubmitRequest $request,
        MealAllowanceService $mealAllowanceService
    ) {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        if (empty($settings->has_food_allowance)) {
            return back()->withErrors(['error' => 'O benefício de Vale-Alimentação não está ativo nas configurações.']);
        }

        $activeShift = CashShift::where('status', 'open')->latest('opened_at')->first();
        if (!$activeShift) {
            return back()->withErrors(['error' => 'Nenhum turno de caixa aberto no momento.']);
        }

        $user = auth('team')->user() ?? auth()->user();

        try {
            $mealAllowanceService->processBatch(
                slots: $request->validated()['slots'],
                activeShift: $activeShift,
                operator: $user,
                settings: $settings
            );

            return back()->with('success', 'Lanches registrados e enviados para a cozinha!');
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Falha ao registrar lanche: ' . $e->getMessage()]);
        }
    }
}
