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
            ->leftJoin("suppliers", "customers.supplier_id", "=", "suppliers.id")
            ->select(
                "customers.id",
                "customers.name",
                "customers.phone",
                "customers.cpf",
                "customers.email",
                "customers.employee_id",
                "customers.supplier_id",
                "customers.loyalty_points",
                "customers.is_active",
                "suppliers.name as supplier_name"
            )
            ->whereNull("customers.deleted_at")
            ->where("customers.is_active", true)
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
                "id"             => $c->id,
                "name"           => $c->name,
                "phone"          => $c->phone,
                "cpf"            => $c->cpf,
                "email"          => $c->email,
                "employee_id"    => $c->employee_id,
                "supplier_id"    => $c->supplier_id,
                "supplier"       => $c->supplier_id ? [
                    "id"   => $c->supplier_id,
                    "name" => $c->supplier_name,
                ] : null,
                "is_employee"    => !empty($c->employee_id),
                "is_convenio"    => !empty($c->supplier_id),
                "loyalty_points" => (int) ($c->loyalty_points ?? 0),
                "addresses"      => $customerAddresses,
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

        $customer->loadMissing(['addresses.street', 'addresses.neighborhood', 'supplier']);

        return [
            "id"             => $customer->id,
            "name"           => $customer->name,
            "phone"          => $customer->phone,
            "cpf"            => $customer->cpf,
            "email"          => $customer->email,
            "employee_id"    => $customer->employee_id,
            "supplier_id"    => $customer->supplier_id,
            "supplier"       => $customer->supplier ? [
                "id"   => $customer->supplier->id,
                "name" => $customer->supplier->name,
            ] : null,
            "is_employee"    => !empty($customer->employee_id),
            "is_convenio"    => !empty($customer->supplier_id),
            "loyalty_points" => (int) ($customer->loyalty_points ?? 0),
            "addresses"      => $customer->addresses->map(function ($addr) {
                $sName = $addr->street->name ?? '';
                $nName = $addr->neighborhood->name ?? '';
                $fAddress = trim("{$sName}, {$addr->number} - {$nName} " . ($addr->complement ? "({$addr->complement})" : ""));

                $deliveryFee = $addr->delivery_fee ?? $addr->neighborhood->delivery_fee ?? 0;

                return [
                    "id"           => $addr->id,
                    "is_active"    => (bool) $addr->is_active,
                    "nickname"     => $addr->nickname,
                    "full_address" => $fAddress,
                    "delivery_fee" => (float) $deliveryFee,
                ];
            })->values()->toArray()
        ];
    }
}
