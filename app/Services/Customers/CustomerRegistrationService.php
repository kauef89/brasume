<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Models\Order;
use App\Models\Address;
use App\Models\CompanySetting;
use App\Services\Marketing\LoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class CustomerRegistrationService
{
    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}

    public function createCustomer(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * 🚀 RESTAURADO: Cria ou recupera o perfil de um visitante (Customer anônimo)
     */
    public function registerGuestCustomer(array $data): int
{
    // 1. Higieniza o telefone (apenas números)
    $cleanPhone = preg_replace('/\D/', '', $data['phone']);
    $rawPhone = $data['phone'];

    // 2. Busca se o cliente já existe por qualquer variação do telefone
    $existing = Customer::where('phone', $cleanPhone)
        ->orWhere('phone', $rawPhone)
        ->first();

    if ($existing) {
        // 🚀 SE JÁ EXISTE: Não trava! Apenas atualiza o nome (se não tiver) e reaproveita o ID
        if (empty($existing->name) && !empty($data['name'])) {
            $existing->update(['name' => $data['name']]);
        }

        return (int) $existing->id;
    }

    // 3. SE NÃO EXISTE: Cria um novo registro como guest/visitante
    $customer = Customer::create([
        'name'         => $data['name'],
        'phone'        => $cleanPhone,
        'user_id'      => null, // Sem vínculo com conta oficial ainda
    ]);

    return (int) $customer->id;
}

    public function registerOfficialCustomer(array $data): User
    {
        $cleanPhone = preg_replace('/\D/', '', $data['phone']);

        return DB::transaction(function () use ($data, $cleanPhone) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'is_active' => true,
            ]);

            Customer::create([
                'name'       => $data['name'],
                'phone'      => $cleanPhone,
                'cpf'        => !empty($data['cpf']) ? preg_replace('/\D/', '', $data['cpf']) : null,
                'birth_date' => $data['birth_date'] ?? null,
                'email'      => $data['email'],
                'user_id'    => $user->id,
                'is_active'  => true,
            ]);

            return $user;
        });
    }

    public function upgradeGuestAccount(int $guestId, array $data, SerproService $serproService): User
    {
        $customer = Customer::findOrFail($guestId);
        $cleanCpf = preg_replace('/[^0-9]/', '', $data['cpf']);

        $existingUser = User::whereHas('customer', fn($q) => $q->where('cpf', $cleanCpf))->first();
        if ($existingUser) {
            throw new Exception('Este CPF já possui cadastro. Por favor, faça Login na tela inicial.', 409);
        }

        $nameToSave = $data['name'] ?? ($data['fallback_name'] ?? $customer->name);
        $birthDateToSave = $data['birth_date'] ?? ($data['fallback_birth_date'] ?? null);

        $settings = CompanySetting::getCached();
        $isSerproActive = $settings
            && $settings->customer_registration_mode === 'serpro_magic'
            && !empty($settings->serpro_client_id);

        // Apenas consulta o Serpro se estiver ativado e o nome não foi enviado
        if ($isSerproActive && empty($data['name']) && empty($data['fallback_name']) && !empty($birthDateToSave)) {
            try {
                $serproData = $serproService->consultCpf($cleanCpf, $birthDateToSave);
                $nameToSave = $serproData['nome'];
            } catch (Exception $e) {
                // Fallback gracioso: mantém o nome informado pelo cliente
            }
        }

        return DB::transaction(function () use ($customer, $cleanCpf, $nameToSave, $birthDateToSave) {
            $customer->update([
                'cpf'        => $cleanCpf,
                'name'       => $nameToSave,
                'birth_date' => $birthDateToSave
            ]);

            $safeEmail = "cliente_" . strtolower(Str::random(6)) . "@brasume.com";

            $user = User::create([
                'name'      => $nameToSave,
                'email'     => $safeEmail,
                'password'  => Hash::make($cleanCpf),
                'is_active' => true,
            ]);

            $customer->update(['user_id' => $user->id]);

            $pastOrders = Order::where('customer_id', $customer->id)
                ->whereIn('status', ['closed', 'completed'])
                ->where('loyalty_points_earned', 0)
                ->get();

            foreach ($pastOrders as $pastOrder) {
                $this->loyaltyService->earnPoints($pastOrder, $customer);
            }

            return $user;
        });
    }

    public function completeSocialRegistration(array $data, ?int $guestId, SerproService $serproService): User
    {
        $pending = session('pending_social_auth');
        if (!$pending) {
            throw new Exception('Sessão de cadastro expirada. Faça login novamente.', 419);
        }

        $cleanCpf = preg_replace('/\D/', '', $data['cpf']);

        $nameToSave = $data['name'] ?? ($data['fallback_name'] ?? $pending['name']);
        $birthDateToSave = $data['birth_date'] ?? ($data['fallback_birth_date'] ?? null);

        return DB::transaction(function () use ($pending, $cleanCpf, $nameToSave, $birthDateToSave, $guestId) {
            $user = User::firstOrCreate(
                ['email' => $pending['email']],
                [
                    'name'      => $nameToSave,
                    'password'  => bcrypt(Str::random(24)),
                    'is_active' => true
                ]
            );

            $targetCustomer = $guestId ? Customer::find($guestId) : null;

            if ($targetCustomer && is_null($targetCustomer->user_id)) {
                $targetCustomer->update([
                    'user_id'    => $user->id,
                    'cpf'        => $cleanCpf,
                    'name'       => $nameToSave,
                    'birth_date' => $birthDateToSave,
                    'email'      => $pending['email']
                ]);
            } else {
                $targetCustomer = Customer::firstOrCreate(
                    ['email' => $pending['email']],
                    [
                        'user_id'    => $user->id,
                        'cpf'        => $cleanCpf,
                        'name'       => $nameToSave,
                        'birth_date' => $birthDateToSave,
                        'is_active'  => true
                    ]
                );
            }

            session()->forget(['pending_social_auth', 'guest_customer_id']);
            return $user;
        });
    }

    // =========================================================================
    // 🌐 ENGINE DE LOGIN SOCIAL E TRANSAÇÃO ATÔMICA ANTI-ZUMBI (RESTAURADOS)
    // =========================================================================

    public function evaluateSocialLogin(array $payload, ?int $guestId): array
    {
        Log::info("🛸 [ANTI-ZUMBI] Avaliando acesso social para: {$payload['email']}");
        $email = strtolower(trim($payload['email']));
        $user = User::where('email', $email)->first();

        // 1. Cliente já existente no banco com cadastro completo (Com CPF)
        if ($user && $user->customer && !empty($user->customer->cpf)) {
            Log::info("🛸 [ANTI-ZUMBI] Cliente com CPF validado. Autenticando imediatamente.");

            $this->mergeGuestDataToOfficialCustomer($guestId, $user->customer);

            return [
                'status' => 'authenticated',
                'user'   => $user,
                'message' => 'Login realizado com sucesso!'
            ];
        }

        // 2. Se a conta não existe, não tem CPF, ou não tem Perfil de Customer, entra em espera segura!
        Log::info("🛸 [ANTI-ZUMBI] Cliente sem CPF ou Inédito. Guardando dados na Sessão para transação futura.");
        session(['pending_social_auth' => [
            'name'      => $payload['name'],
            'email'     => $email,
            'provider'  => 'google',
            'return_to' => $payload['return_to'] ?? '/'
        ]]);

        return [
            'status'             => 'requires_cpf',
            'user'               => null,
            'pending_customer'   => [
                'name'  => $payload['name'],
                'email' => $email
            ],
            'message' => 'Por favor, informe seu CPF para concluir o acesso.'
        ];
    }

    private function mergeGuestDataToOfficialCustomer(?int $guestId, ?Customer $targetCustomer): void
    {
        if (!$guestId || !$targetCustomer) {
            return;
        }

        if ($guestId !== $targetCustomer->id) {
            Order::where('customer_id', $guestId)->update(['customer_id' => $targetCustomer->id]);
            Address::where('customer_id', $guestId)->update(['customer_id' => $targetCustomer->id]);

            $guest = Customer::find($guestId);
            if ($guest && is_null($guest->user_id)) {
                $guest->delete();
            }
        }

        session()->forget('guest_customer_id');
    }

    public function deleteCustomerAccount(?User $user, ?int $guestId): void
    {
        DB::transaction(function () use ($user, $guestId) {
            $customerToTrash = null;

            if ($user && $user->customer) {
                $customerToTrash = $user->customer;
                $user->delete();
            } elseif ($guestId) {
                $customerToTrash = Customer::find($guestId);
            }

            if ($customerToTrash) {
                $customerToTrash->update(['is_active' => false]);
                $customerToTrash->delete();
            }
        });
    }

    public function ensureUserHasCustomerProfile(User $user): void
    {
        if (!$user->customer) {
            Customer::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '00000000000',
                'is_active' => true,
            ]);
        }
    }

    private function generateUniqueGhostEmail(): string
    {
        do {
            $code = strtolower(Str::random(5));
            $email = "cliente_{$code}@brasume.com";
        } while (User::where('email', $email)->exists());

        return $email;
    }
}
