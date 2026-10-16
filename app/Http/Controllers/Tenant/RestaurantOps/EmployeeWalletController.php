<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\RestaurantOps\EmployeeWalletService;

class EmployeeWalletController extends Controller
{
    public function index(Request $request, EmployeeWalletService $walletService)
    {
        $user = auth()->user();

        // Garante que o usuário logado realmente tem um perfil de funcionário
        if (!$user || !$user->employee_id) {
            abort(403, 'Acesso negado. Apenas colaboradores possuem carteira.');
        }

        $dashboardData = $walletService->getWalletDashboardData($user->employee_id);

        return Inertia::render('Operations/Employee/Wallet', $dashboardData);
    }
}
