<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CompanySetting;
use App\Models\CashShift;

class EnsureShiftAttendance
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // 1. BYPASS VIP: Admins e Gerentes não são travados pelo ponto
        if ($user->hasAnyRole(['admin', 'gerente', 'manager'])) {
            return $next($request);
        }

        $settings = CompanySetting::getCached();

        // 2. REGRA ESTRITA DE PONTO / ESCALA
        if ($settings && $settings->require_shift_attendance) {

            $activeShift = CashShift::where('status', 'open')->latest()->first();

            // 🚀 LIBERAÇÃO: Se o caixa ainda não foi aberto, permite que a equipe opere
            // normalmente para lançar comandas de salão e balcão.
            if (!$activeShift) {
                return $next($request);
            }

            // Se o caixa JÁ ESTÁ ABERTO, valida se o funcionário está na escala do dia
            $isScheduled = $activeShift->attendees()
                ->where('users.id', $user->id)
                ->wherePivot('attendance_status', 'present')
                ->exists();

            if (!$isScheduled) {
                Auth::logout();
                return redirect()->route('team.login')->with('error', 'Acesso negado. O seu turno foi encerrado ou você não está escalado no momento.');
            }
        }

        return $next($request);
    }
}
