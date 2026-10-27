<?php

namespace App\Http\Controllers\Tenant\Customers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Address;
use App\Services\Customers\CustomerRegistrationService;
use App\Services\Customers\CustomerProfileService;
use App\Services\Customers\CustomerValidationService;
use App\Services\Customers\CustomerDashboardService;
use App\Services\Customers\SerproService;
use App\Services\Customers\CustomerFavoritesService;

class CustomerController extends Controller
{
    public function search(Request $request, CustomerProfileService $profileService)
    {
        if (!$request->query('q')) return response()->json([]);
        return response()->json($profileService->searchCustomers($request->query('q')));
    }

    public function checkCpf(Request $request, CustomerValidationService $validationService, SerproService $serpro)
    {
        $cpf = preg_replace('/[^0-9]/', '', $request->query('cpf'));

        if (strlen($cpf) !== 11) {
            return response()->json(['available' => false, 'message' => 'CPF com formato inválido.'], 400);
        }

        return response()->json($validationService->checkCpfAvailability($cpf, $serpro));
    }

    public function store(Request $request, CustomerRegistrationService $registrationService)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'phone'      => 'required|string|max:20',
            'cpf'        => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'email'      => 'nullable|email|max:255',
        ]);

        $customer = $registrationService->createCustomer($validated);

        return back()->with([
            'success'         => 'Cliente cadastrado com sucesso!',
            'createdCustomer' => ($customer instanceof Customer) ? $customer->loadMissing('addresses') : $customer,
        ]);
    }

    public function storeAddress(CustomerProfileService $profileService, Request $request, ?Customer $customer = null)
    {
        // 🚀 Resolve o cliente a partir do parâmetro de rota (B2B) ou da sessão autenticada (B2C)
        $targetCustomer = ($customer && $customer->exists)
            ? $customer
            : (auth('web')->user()?->customer ?? auth()->user()?->customer);

        if (!$targetCustomer && session()->has('guest_customer_id')) {
            $targetCustomer = Customer::find(session('guest_customer_id'));
        }

        if (!$targetCustomer) {
            return back()->withErrors(['address' => 'Sessão inválida. Identifique-se novamente.']);
        }

        $validated = $request->validate([
            'nickname'        => 'nullable|string|max:50',
            'street'          => 'required|string|max:255',
            'street_id'       => 'nullable|integer',
            'number'          => 'required|string|max:20',
            'noNumber'        => 'nullable|boolean',
            'no_number'       => 'nullable|boolean',
            'neighborhood'    => 'required|string|max:255',
            'neighborhood_id' => 'nullable|integer',
            'zipcode'         => 'nullable|string|max:15',
            'complement'      => 'nullable|string|max:255',
            'reference'       => 'nullable|string|max:255',
            'location'        => 'nullable|string|max:500',
        ]);

        $validated['nickname'] = $validated['nickname'] ?? 'Casa';
        $validated['noNumber'] = $validated['noNumber'] ?? $validated['no_number'] ?? false;

        $address = $profileService->addAddress($targetCustomer, $validated);

        return back()->with([
            'success'        => 'Endereço cadastrado com sucesso!',
            'createdAddress' => $address->loadMissing(['street', 'neighborhood']),
        ]);
    }

    public function deactivateAddress(Request $request, CustomerProfileService $profileService, ?Address $address = null)
    {
        $customer = auth()->user()?->customer;
        if (!$customer && session()->has('guest_customer_id')) {
            $customer = Customer::find(session('guest_customer_id'));
        }

        if (!$customer) {
            return back()->withErrors(['address' => 'Sessão inválida. Identifique-se novamente.']);
        }

        $targetAddress = ($address && $address->exists) ? $address : null;

        if (!$targetAddress) {
            $addressId = $request->route('address')
                ?? $request->input('address_id')
                ?? $request->input('id')
                ?? $request->input('address');

            $targetAddress = Address::find($addressId);
        }

        if (!$targetAddress) {
            return back()->withErrors(['address' => 'Endereço não encontrado.']);
        }

        try {
            $profileService->deactivateAddress($customer, $targetAddress);

            if (session()->has('guest_addresses')) {
                $guestAddresses = array_values(array_diff(session('guest_addresses', []), [$targetAddress->id]));
                session()->put('guest_addresses', $guestAddresses);
            }

            return back()->with('success', 'Endereço removido com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['address' => $e->getMessage()]);
        }
    }

    public function updateProfile(Request $request, CustomerProfileService $profileService)
    {
        $validated = $request->validate([
            'name'       => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
        ]);

        try {
            $profileService->updateCustomerProfile($validated, auth()->user(), session('guest_customer_id'));
            return back()->with('success', 'Os seus dados foram atualizados com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function favorites(Customer $customer, CustomerFavoritesService $favoritesService)
    {
        $loggedUser = null;
        $isTeamMember = false;

        try {
            if (auth('team')->check()) {
                $loggedUser = auth('team')->user();
                $isTeamMember = true;
            }
        } catch (\Exception $e) {}

        if (!$loggedUser && auth()->check()) {
            $loggedUser = auth()->user();
        }

        if (!$loggedUser) {
            return response()->json(['message' => 'Não autorizado. Faça login.'], 401);
        }

        $isTheCustomer = false;

        if (!$isTeamMember) {
            $isTheCustomer = ($loggedUser->id === $customer->id) || ($loggedUser->id === $customer->user_id);
        }

        if (!$isTeamMember && !$isTheCustomer) {
            return response()->json(['message' => 'Acesso negado a este perfil.'], 403);
        }

        $favorites = $favoritesService->getFavorites($customer);

        return response()->json([
            'data' => $favorites
        ]);
    }

    public function deleteAccount(Request $request, CustomerRegistrationService $registrationService)
    {
        $registrationService->deleteCustomerAccount(auth()->user(), session('guest_customer_id'));

        // Resolve a dependência e executa a destruição da sessão de forma interna
        return app(\App\Http\Controllers\Tenant\Auth\LogoutController::class)->destroy($request);
    }

    public function storeGuestAddress(Request $request, CustomerProfileService $profileService)
    {
        $guestId = session('guest_customer_id');

        if (!$guestId) {
            return redirect()->back()->withErrors(['address' => 'Sessão inválida. Identifique-se novamente.']);
        }

        $data = $request->validate([
            'street'          => 'required|string',
            'street_id'       => 'nullable|integer',
            'number'          => 'required|string',
            'neighborhood'    => 'required|string',
            'neighborhood_id' => 'nullable|integer',
            'complement'      => 'nullable|string',
            'nickname'        => 'nullable|string',
        ]);

        try {
            $address = $profileService->addAddress($guestId, $data);
            session()->push('guest_addresses', $address->id);

            return redirect()->back()->with('success', 'Endereço adicionado com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['address' => 'Erro ao salvar o endereço.']);
        }
    }

    public function toggleOpeningNotification(Request $request)
    {
        $request->validate(['wants_notification' => 'required|boolean']);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $user->update(['wants_opening_notification' => $request->wants_notification]);

        return response()->json(['success' => true]);
    }

    public function updateAddressLocation(Request $request, Address $address)
    {
        $validated = $request->validate([
            'location' => 'required|string|max:500',
        ]);

        $address->update(['location' => $validated['location']]);

        return back()->with([
            'success' => 'Localização do endereço atualizada com sucesso!',
            'address' => $address->loadMissing(['street', 'neighborhood']),
        ]);
    }
}