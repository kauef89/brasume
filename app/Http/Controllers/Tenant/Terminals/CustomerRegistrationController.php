<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Services\Customers\CustomerRegistrationService;
use App\Services\Customers\SerproService;
use Illuminate\Support\Facades\Auth;
use Exception;

class CustomerRegistrationController extends Controller
{
    public function upgrade(Request $request, CustomerRegistrationService $registrationService, SerproService $serproService)
    {
        $data = $request->validate([
            'cpf'                 => 'required|string|min:11|max:18',
            'phone'               => 'required|string|min:10',
            'name'                => 'nullable|string|min:2',
            'birth_date'          => 'nullable|date',
            'fallback_name'       => 'nullable|string|min:2',
            'fallback_birth_date' => 'nullable|date',
        ]);

        try {
            $cleanCpf = preg_replace('/[^0-9]/', '', $data['cpf']);
            $cleanPhone = preg_replace('/\D/', '', $data['phone']);
            $nameToSave = $data['name'] ?? ($data['fallback_name'] ?? null);
            $birthDateToSave = $data['birth_date'] ?? ($data['fallback_birth_date'] ?? null);

            // CENÁRIO 1: Social Login Pendente (Google)
            if (session()->has('pending_social_auth')) {
                $user = $registrationService->completeSocialRegistration($data, session('guest_customer_id'), $serproService);
                if ($user->customer) {
                    $user->customer->update(['phone' => $cleanPhone]);
                }
                Auth::login($user, true);
                $request->session()->regenerate();
                return back()->with('success', 'Cadastro concluído com sucesso!');
            }

            // CENÁRIO 2: Usuário já autenticado vinculando CPF
            if (Auth::check()) {
                $user = Auth::user();
                $customer = $user->customer;

                if ($customer) {
                    $customer->update([
                        'cpf'        => $cleanCpf,
                        'name'       => $nameToSave ?? $customer->name,
                        'birth_date' => $birthDateToSave ?? $customer->birth_date,
                        'phone'      => $cleanPhone
                    ]);
                }

                return back()->with('success', 'Perfil completado com sucesso!');
            }

            // CENÁRIO 3: Visitante promovido a cliente oficial
            $guestId = session('guest_customer_id');
            if (!$guestId) {
                return back()->withErrors(['upgrade' => 'Sessão expirada.']);
            }

            $user = $registrationService->upgradeGuestAccount($guestId, $data, $serproService);
            if ($user->customer) {
                $user->customer->update(['phone' => $cleanPhone]);
            }

            session()->forget('guest_customer_id');
            Auth::login($user, true);
            $request->session()->regenerate();

            return back()->with('success', 'Conta criada com sucesso!');
        } catch (Exception $e) {
            return back()->withErrors(['upgrade' => $e->getMessage()]);
        }
    }
}
