<?php

namespace App\Services\Orders;

use App\Models\Address;
use App\Models\Street;
use App\Models\Neighborhood;

class OrderAddressResolver
{
    /**
     * Resolve o endereço de entrega garantindo:
     * 1. Um registro relacional em `addresses` (com ID numérico válido para Registered ou Guest).
     * 2. Um snapshot imutável em array com dados congelados no momento do pedido.
     */
    public function resolve(array $routing, ?int $customerId = null, float$fallbackDeliveryFee = 0.0): array
    {
        if (($routing['type'] ?? 'delivery') !== 'delivery') {
            return [
                'address_id' => null,
                'snapshot'   => null,
            ];
        }

        $rawAddressId = $routing['address_id'] ?? ($routing['address']['id'] ?? null);
        $addrModel = null;

        // 1. Se veio um ID numérico que já existe no banco
        if ($rawAddressId && is_numeric($rawAddressId) && (float)$rawAddressId <= 2147483647) {
            $addrModel = Address::with(['street', 'neighborhood'])->find((int)$rawAddressId);
        }

        // 2. Se não encontrou o Model, mas temos dados brutos de endereço (Guest ou entrada manual no PDV)
        if (!$addrModel && !empty($routing['address']) && is_array($routing['address'])) {
            $addrData =$routing['address'];

            $streetData =$addrData['street'] ?? null;
            $streetName = is_array($streetData) ? ($streetData['name'] ?? 'Logradouro') : ($streetData ?: 'Logradouro');
            $streetId = is_array($streetData) ? ($streetData['id'] ?? null) : ($addrData['street_id'] ?? null);

            if (!$streetId && !empty($streetName) && class_exists(Street::class)) {
                try {
                    $streetId = Street::firstOrCreate(['name' =>$streetName])->id;
                } catch (\Throwable $e) {}
            }

            $neighborhoodData =$addrData['neighborhood'] ?? null;
            $neighborhoodName = is_array($neighborhoodData) ? ($neighborhoodData['name'] ?? 'Bairro') : ($neighborhoodData ?: 'Bairro');
            $neighborhoodId = is_array($neighborhoodData) ? ($neighborhoodData['id'] ?? null) : ($addrData['neighborhood_id'] ?? null);

            if (!$neighborhoodId && !empty($neighborhoodName) && class_exists(Neighborhood::class)) {
                try {
                    $neighborhoodId = Neighborhood::firstOrCreate(['name' =>$neighborhoodName])->id;
                } catch (\Throwable $e) {}
            }

            $number = !empty($addrData['number']) ? (string)$addrData['number'] : 'S/N';
            $noNumber = (bool) ($addrData['no_number'] ?? $addrData['noNumber'] ?? ($number === 'S/N'));

            try {
                $addrModel = Address::create([
                    'customer_id'     => $customerId,
                    'nickname'        => $addrData['nickname'] ?? 'Entrega',
                    'street_id'       => $streetId,
                    'neighborhood_id' => $neighborhoodId,
                    'number'          => $number,
                    'no_number'       => $noNumber,
                    'complement'      => $addrData['complement'] ?? null,
                    'reference'       => $addrData['reference'] ?? null,
                ]);

                $addrModel->load(['street', 'neighborhood']);
            } catch (\Throwable $e) {
                // Fallback caso a tabela addresses tenha constraints estritas não atendidas
            }
        }

        if ($addrModel) {$streetName = $addrModel->street?->name ?? ($addrModel->street_name ?? 'Logradouro');
            $neighborhoodName = $addrModel->neighborhood?->name ?? ($addrModel->neighborhood_name ?? 'Bairro');
            $number = $addrModel->no_number ? 'S/N' : ($addrModel->number ?? 'S/N');
            $comp = $addrModel->complement ? " ({$addrModel->complement})" : '';
            $fullAddress =$addrModel->full_address ?? trim("{$streetName}, {$number} - {$neighborhoodName}{$comp}");
            $deliveryFee = (float) ($addrModel->delivery_fee ?? $fallbackDeliveryFee);

            $snapshot = [
                'id'              => $addrModel->id,
                'customer_id'     => $addrModel->customer_id ?? $customerId,
                'nickname'        => $addrModel->nickname ?? 'Entrega',
                'street_id'       => $addrModel->street_id,
                'street'          => $streetName,
                'number'          => $number,
                'no_number'       => (bool) $addrModel->no_number,
                'neighborhood_id' => $addrModel->neighborhood_id,
                'neighborhood'    => $neighborhoodName,
                'complement'      => $addrModel->complement,
                'reference'       => $addrModel->reference,
                'full_address'    => $fullAddress,
                'delivery_fee'    => $deliveryFee,
            ];

            return [
                'address_id' => $addrModel->id,
                'snapshot'   => $snapshot,
            ];
        }

        // 3. Fallback de segurança caso não seja possível persistir em addresses
        if (!empty($routing['address']) && is_array($routing['address'])) {$rawAddr = $routing['address'];$streetName = is_array($rawAddr['street'] ?? null) ? ($rawAddr['street']['name'] ?? 'Logradouro') : ($rawAddr['street'] ?? 'Logradouro');$neighborhoodName = is_array($rawAddr['neighborhood'] ?? null) ? ($rawAddr['neighborhood']['name'] ?? 'Bairro') : ($rawAddr['neighborhood'] ?? 'Bairro');$number = (!empty($rawAddr['no_number']) || ($rawAddr['number'] ?? '') === 'S/N') ? 'S/N' : ($rawAddr['number'] ?? 'S/N');$fullAddress = $rawAddr['full_address'] ?? trim("{$streetName}, {$number} - {$neighborhoodName}");

            $snapshot = [
                'id'              => null,
                'customer_id'     => $customerId,
                'nickname'        => $rawAddr['nickname'] ?? 'Entrega',
                'street_id'       => $rawAddr['street_id'] ?? null,
                'street'          => $streetName,
                'number'          => $number,
                'no_number'       => !empty($rawAddr['no_number']),
                'neighborhood_id' => $rawAddr['neighborhood_id'] ?? null,
                'neighborhood'    => $neighborhoodName,
                'complement'      => $rawAddr['complement'] ?? null,
                'reference'       => $rawAddr['reference'] ?? null,
                'full_address'    => $fullAddress,
                'delivery_fee'    => (float) ($rawAddr['delivery_fee'] ?? $fallbackDeliveryFee),
            ];

            return [
                'address_id' => null,
                'snapshot'   => $snapshot,
            ];
        }

        return [
            'address_id' => null,
            'snapshot'   => null,
        ];
    }

    /**
     * Alias de retrocompatibilidade para chamadas de snapshot direto
     */
    public function resolveAddressSnapshot(array $routing, float$fallbackDeliveryFee = 0): ?array
    {
        $customerId = $routing['customer_id'] ?? ($routing['customer']['id'] ?? null);
        $resolved =$this->resolve($routing, is_numeric($customerId) ? (int)$customerId : null,$fallbackDeliveryFee);
        return $resolved['snapshot'];
    }
}
