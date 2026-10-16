<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\RestaurantOps\ShiftAttendanceService;

class ShiftAttendanceController extends Controller
{
    public function sync(Request $request, ShiftAttendanceService $service)
    {
        $data = $request->validate([
            'attendees' => 'required|array',
            'attendees.*.user_id' => 'required|integer|exists:users,id',
            'attendees.*.attendance_status' => 'required|string|in:present,absent,vacation,day_off',
            'attendees.*.daily_rate' => 'required|numeric|min:0',
            'attendees.*.assigned_role' => 'nullable|string',
        ]);

        $service->syncShiftAttendees($data['attendees']);

        return back()->with('success', 'Equipe atualizada com sucesso!');
    }

    /**
     * 🚀 Retorna os detalhes de diária, consumo e valor líquido para o modal de pagamento
     */
    public function payoutDetails(int $userId, ShiftAttendanceService $service)
    {
        try {
            $details = $service->getFreelancerPayoutDetails($userId);
            return response()->json($details);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function payFreelancer(Request $request, ShiftAttendanceService $service)
    {
        $data = $request->validate([
            'user_id'              => 'required|integer|exists:users,id',
            'amount'               => 'required|numeric|min:0',
            'gross_rate'           => 'nullable|numeric|min:0',
            'consumption_deducted' => 'nullable|numeric|min:0',
            'payment_method'       => 'required|string|in:cash,bank,accumulate',
            'bank_account_id'      => 'nullable|integer|exists:bank_accounts,id'
        ]);

        try {
            $service->payFreelancer($data, auth()->user());

            $isAccumulated = $data['payment_method'] === 'accumulate' || (float) $data['amount'] <= 0.00;
            $msg = $isAccumulated
                ? 'Diária acumulada no extrato do colaborador com sucesso.'
                : 'Diária liquidada e recibo emitido com sucesso!';

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
