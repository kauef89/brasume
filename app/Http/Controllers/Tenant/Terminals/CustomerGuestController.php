<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Customers\CustomerRegistrationService;
use App\Models\Customer;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Log;

class CustomerGuestController extends Controller
{
    public function register(Request $request, CustomerRegistrationService $registrationService)
    {
        Log::info('📍 [SONDA GUEST BACKEND 1] Requisição recebida em /guest-register:', [
            'payload'    => $request->all(),
            'session_id' => session()->getId(),
        ]);

        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        $requireWhatsapp = $settings->require_whatsapp ?? true;

        try {
            $data = $request->validate([
                'name'  => 'required|string|min:2|max:255',
                'phone' => $requireWhatsapp ? 'required|string|min:10' : 'nullable|string',
            ]);
            Log::info('📍 [SONDA GUEST BACKEND 2] Validação concluída com sucesso:', $data);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::error('🚨 [SONDA GUEST BACKEND ERRO] Falha de validação:', $ve->errors());
            throw $ve;
        }

        try {
            $guestId = $registrationService->registerGuestCustomer($data);
            Log::info('📍 [SONDA GUEST BACKEND 3] Guest registrado via Service. ID:', ['guest_id' => $guestId]);

            session(['guest_customer_id' => $guestId]);
            session()->save();

            Log::info('📍 [SONDA GUEST BACKEND 4] Sessão guest_customer_id gravada:', [
                'session_guest_customer_id' => session('guest_customer_id')
            ]);

            return back()->with('success', 'Identificação confirmada!');
        } catch (\Exception $e) {
            Log::error('🚨 [SONDA GUEST BACKEND ERRO] Exceção ao registrar visitante:', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile()
            ]);
            return back()->withErrors(['guest' => $e->getMessage()]);
        }
    }

    public function restore(Request $request)
    {
        $guestId = $request->input('guest_id');

        if (!$guestId) {
            return response()->json(['error' => 'ID não fornecido'], 400);
        }

        $customer = Customer::find($guestId);

        if ($customer && is_null($customer->user_id)) {
            session(['guest_customer_id' => $customer->id]);
            session()->save();
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Visitante inválido ou já registrado'], 404);
    }
}
