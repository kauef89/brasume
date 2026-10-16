<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\Address;
use App\Models\Street;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\CompanySetting;
use Exception;

class CustomerProfileService
{
    public function searchCustomers(string $search)
    {
        $onlyNumbers = preg_replace('/\D/', '', $search);
        $query = Customer::query()->where('name', 'like', "%{$search}%");

        if (!empty($onlyNumbers)) {
            $query->orWhere('phone', 'like', "%{$onlyNumbers}%")
                  ->orWhere('cpf', 'like', "%{$onlyNumbers}%");
        }

        return $query->take(10)->get(['id', 'name', 'phone', 'cpf']);
    }

    public function addAddress(Customer|int $customerOrId, array $data): Address
        {
            $customerId = $customerOrId instanceof Customer ? $customerOrId->id : $customerOrId;

            // 🚀 1. TRATAMENTO DO BAIRRO (Prioriza o ID para não perder a taxa de entrega!)
            if (!empty($data['neighborhood_id'])) {
                $neighborhood = Neighborhood::findOrFail($data['neighborhood_id']);
            } else {
                $neighborhood = Neighborhood::firstOrCreate(
                    ['name' => trim($data['neighborhood'])],
                    ['delivery_fee' => 0.00]
                );
            }

            // 🚀 2. TRATAMENTO DA RUA (Prioriza o ID)
            if (!empty($data['street_id'])) {
                $street = Street::findOrFail($data['street_id']);
            } else {
                $street = Street::firstOrCreate(
                    ['name' => trim($data['street'])],
                    ['zip_code' => $data['zipcode'] ?? null]
                );
            }

            $number = isset($data['noNumber']) && $data['noNumber'] ? 'S/N' : ($data['number'] ?? 'S/N');

            return Address::create([
                'customer_id' => $customerId,
                'nickname' => $data['nickname'] ?? 'Casa',
                'street_id' => $street->id,
                'neighborhood_id' => $neighborhood->id,
                'number' => $number,
                'no_number' => $data['noNumber'] ?? false,
                'complement' => $data['complement'] ?? null,
                'is_active' => true,
            ]);
        }

    public function updateAddress(Customer $customer, int $addressId, array $data): void
    {
        $address = $customer->addresses()->findOrFail($addressId);
        $address->update($data);
    }

    public function deactivateAddress(Customer $customer, Address $address): void
    {
        if ($address->customer_id !== $customer->id) {
            throw new Exception('Operação não autorizada.');
        }

        $address->update(['is_active' => false]);
    }

    public function updateCustomerProfile(array $data, ?User $user, ?int $guestId): void
    {
        $customer = null;
        if ($user && $user->customer) {
            $customer = $user->customer;
        } elseif ($guestId) {
            $customer = Customer::find($guestId);
        }

        if (!$customer) {
            throw new Exception('Sessão inválida.');
        }

        $settings = CompanySetting::getCached();
        $isSerproActive = $settings && $settings->customer_registration_mode === 'serpro_magic';
        $canEditProfile = $settings ? $settings->customer_can_edit_profile : true;

        $isLocked = $isSerproActive || !$canEditProfile;

        if (!$isLocked) {
            if (isset($data['name'])) $customer->name = $data['name'];
            if (isset($data['birth_date'])) $customer->birth_date = $data['birth_date'];
        }

        if (isset($data['phone'])) $customer->phone = $data['phone'];

        $customer->save();

        if ($user && !$isLocked && isset($data['name'])) {
            $user->update(['name' => $data['name']]);
        }
    }
}
