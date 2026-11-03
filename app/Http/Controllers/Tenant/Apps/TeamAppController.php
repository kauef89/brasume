<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\TeamOrderSubmitRequest;
use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\EmployeeSchedule;
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

        // 🛡️ Permissão dinâmica: Acesso a Dispositivos / Liberação de Terminais
        $canManageDevices = false;
        if ($user) {
            $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [$user->role ?? ''];
            $isManager = in_array('admin', $userRoles, true) || in_array('manager', $userRoles, true);

            // 1. Operador com presença registrada como caixa no turno em andamento
            $isShiftCashier = false;
            if ($activeShift) {
                $attendance = $activeShift->attendees()->where('users.id', $user->id)->first();
                if ($attendance) {
                    $role = mb_strtolower((string) ($attendance->pivot->assigned_role ?? ''));
                    $isShiftCashier = in_array($role, ['caixa', 'cashier', 'pos'], true);
                }
            }

            // 2. Operador planejado na escala de hoje na função de caixa
            $isScheduledCashier = false;
            if ($user->employee_id) {
                $todaySchedule = EmployeeSchedule::with('slot')
                    ->where('employee_id', $user->employee_id)
                    ->whereDate('date', now()->toDateString())
                    ->where('is_day_off', false)
                    ->first();

                $isScheduledCashier = $todaySchedule?->isCashierSchedule() ?? false;
            }

            $canManageDevices = $isManager || $isShiftCashier || $isScheduledCashier;
        }

        $data = $menuService->getOrderEntryData($request);
        $data['defaultTab'] = $activeTab;
        $data['isDriver'] = $isDriver;
        $data['canManageDevices'] = fn () => $canManageDevices;
        $data['isOnDuty'] = fn () => $user ? $attendanceService->checkIsOnDuty($user) : false;
        $data['initialOrders'] = fn () => $opsService->getActiveOrdersDashboard()['initialOrders'];
        $data['managerData'] = fn () => $opsService->getManagerShiftSummary($user);

        // Turno e colaboradores ativos
        $data['currentShift'] = fn () => $activeShift;
        $data['currentAttendees'] = fn () => $activeShift ? $mealAllowanceService->getAttendeesWithMealStatus($activeShift) : [];

        // Carteira corporativa
        $data['employeeWalletData'] = fn () => ($user && $user->employee_id)
            ? $walletService->getWalletDashboardData($user->employee_id)
            : ['balance' => 0];

        // Catálogo de produtos com preço resolvido
        $data['catalog'] = fn () => Product::with(['category', 'storageLocations', 'modifiers.options'])
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get()
            ->each(function ($product) {
                $product->setAttribute('price', (float) ($product->dine_in_price ?? $product->delivery_price ?? 0));
            });

        return Inertia::render('Team/TeamApp', $data);
    }

    /**
     * 🥪 Lançamento dos lanches da equipe (Vale-Alimentação)
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
