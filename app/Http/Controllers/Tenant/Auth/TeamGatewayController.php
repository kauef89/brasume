<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Services\RestaurantOps\StoreAvailabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamGatewayController extends Controller
{
    public function show(Request $request, StoreAvailabilityService $availabilityService): Response|\Illuminate\Http\RedirectResponse
    {
        $portal = $request->segment(1); // 'pos', 'waiter', 'kds', ou 'team'

        // Se já está logado, joga para o Guarda de Trânsito (/team) para rotear corretamente
        if (auth('team')->check()) {
            return redirect()->route('team.index');
        }

        if (session()->has('guest_customer_id')) {
            session()->forget('guest_customer_id');
            return redirect($request->getRequestUri());
        }

        // 🚀 Define o layout/comportamento da tela de login
        $mode = match($portal) {
            'pos' => 'desktop',
            'waiter' => 'mobile',
            default => null
        };

        $activeShift = CashShift::where('status', 'open')->latest()->first();
        $authorizedStaff = [];

        if ($activeShift) {
            $authorizedStaff = $activeShift->attendees()
                ->wherePivot('attendance_status', 'present')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email
                ]);
        }

        return Inertia::render('Gateway/TeamGateway', [
            'portal' => $portal, // Informa ao Vue se é pos, waiter ou kds
            'mode' => $mode,
            'authorizedStaff' => $authorizedStaff,
            'storeStatus' => $availabilityService->getStoreStatus(),
        ]);
    }
}
