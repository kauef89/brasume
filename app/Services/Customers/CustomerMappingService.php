<?php

namespace App\Services\Customers;

use Illuminate\Support\Facades\DB;
use App\Models\Customer;

class CustomerMappingService
{
    /**
     * Retorna todos os clientes e endereços ativos de forma otimizada (Query Builder).
     * Usado no carregamento inicial da Tela de Vendas (PDV).
     */
    public function getAllForPos()
    {
        $customers = DB::table("customers")
            ->select("id", "name", "phone", "cpf")
            ->whereNull("deleted_at")
            ->get();

        if ($customers->isEmpty()) {
            return collect();
        }

        // 🚀 Busca todos os endereços de uma só vez (1 query em vez de N queries)
        $addressesGrouped = DB::table("addresses")
            ->whereIn("customer_id", $customers->pluck("id"))
            ->leftJoin("streets", "addresses.street_id", "=", "streets.id")
            ->leftJoin("neighborhoods", "addresses.neighborhood_id", "=", "neighborhoods.id")
            ->select(
                "addresses.id",
                "addresses.customer_id",
                "addresses.number",
                "addresses.complement",
                "addresses.is_active",
                "addresses.nickname",
                "streets.name as street_name",
                "neighborhoods.name as neighborhood_name",
                "neighborhoods.delivery_fee"
            )
            ->get()
            ->groupBy("customer_id");

        return $customers->map(function ($c) use ($addressesGrouped) {
            $customerAddresses = $addressesGrouped->get($c->id, collect())->map(function ($addr) {
                return [
                    "id"           => $addr->id,
                    "is_active"    => (bool) $addr->is_active,
                    "nickname"     => $addr->nickname,
                    "full_address" => trim("{$addr->street_name}, {$addr->number} - {$addr->neighborhood_name} " . ($addr->complement ? "({$addr->complement})" : "")),
                    "neighborhood" => $addr->neighborhood_name,
                    "delivery_fee" => (float) $addr->delivery_fee,
                ];
            });

            return [
                "id"        => $c->id,
                "name"      => $c->name,
                "phone"     => $c->phone,
                "cpf"       => $c->cpf,
                "addresses" => $customerAddresses,
            ];
        });
    }

    /**
     * Mapeia um único cliente (Model Eloquent) para a estrutura exigida pelo Vue.
     * Usado no carregamento do Kanban de Operações.
     */
    public function mapOrderCustomer(?Customer $customer): ?array
    {
        if (!$customer) {
            return null;
        }

        return [
            "id" => $customer->id,
            "name" => $customer->name,
            "phone" => $customer->phone,
            "cpf" => $customer->cpf,
            "addresses" => $customer->addresses->map(function ($addr) {
                $sName = $addr->street->name ?? '';
                $nName = $addr->neighborhood->name ?? '';
                $fAddress = trim("{$sName}, {$addr->number} - {$nName} " . ($addr->complement ? "({$addr->complement})" : ""));

                $deliveryFee = $addr->delivery_fee ?? $addr->neighborhood->delivery_fee ?? 0;

                return [
                    "id" => $addr->id,
                    "is_active" => (bool) $addr->is_active, // 🚀 Injetado aqui também!
                    "nickname" => $addr->nickname,          // 🚀 Injetado aqui também!
                    "full_address" => $fAddress,
                    "delivery_fee" => (float) $deliveryFee,
                ];
            })->values()->toArray()
        ];
    }
}
