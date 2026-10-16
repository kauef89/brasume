<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Services\RestaurantOps\ShiftAttendanceService;
use Detection\MobileDetect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamDispatcherController extends Controller
{
    /**
     * O Guarda de Trânsito: detecta o dispositivo/função e despacha para o aplicativo correto.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $queryParams = $request->query();

        // 1. Perfil Cozinha: despacha para o KDS
        if ($user && $user->hasRole('kitchen')) {
            return redirect()->route('kds.index', $queryParams);
        }

        $detect = new MobileDetect();

        // 2. Dispositivo Móvel / Tablet: despacha para o TeamApp Mobile
        if ($detect->isMobile() || $detect->isTablet()) {
            $routeParams = $queryParams;
            if ($request->has('tab')) {
                $routeParams['tab'] = $request->get('tab');
            }
            return redirect()->route('team.mobile', $routeParams);
        }

        // 3. Desktop / Terminal de Caixa: despacha para o PosApp (sem redundância /pos/pos)
        $tab = $request->get('tab');
        $routeParams = $queryParams;
        if (!empty($tab) && !in_array($tab, ['pos', 'new'], true)) {
            $routeParams['tab'] = $tab;
        }

        return redirect()->route('pos.index', $routeParams);
    }

    public function startShift(Request $request, ShiftAttendanceService $attendanceService): RedirectResponse
    {
        try {
            $attendanceService->startUserShift(auth()->user());
            return back()->with('success', 'Turno iniciado com sucesso! Boas vendas.');
        } catch (\Exception $e) {
            return back()->withErrors(['shift' => $e->getMessage()]);
        }
    }
}
