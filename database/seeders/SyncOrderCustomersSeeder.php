<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class SyncOrderCustomersSeeder extends Seeder
{
    public function run(): void
    {
        // Pega todos os pedidos que não têm ID de cliente, mas têm o Nome preenchido
        $orders = Order::whereNull('customer_id')
                       ->whereNotNull('customer_name')
                       ->where('customer_name', '!=', '')
                       ->get();

        $count = 0;
        $notFound = 0;

        $this->command->warn("Iniciando sincronização de " . $orders->count() . " pedidos órfãos...");

        foreach ($orders as $order) {
            // Procura o cliente na tabela de clientes com exatamente o mesmo nome
            $customer = Customer::where('name', $order->customer_name)->first();

            if ($customer) {
                // Se encontrou, liga o ID do cliente ao pedido!
                $order->customer_id = $customer->id;
                
                // Desligamos os timestamps temporariamente para não alterar a data de "atualização" original do pedido
                $order->timestamps = false; 
                $order->save();
                
                $count++;
            } else {
                $notFound++;
            }
        }

        $this->command->info("==== RESULTADO DA SINCRONIZAÇÃO ====");
        $this->command->info("- Sucesso: {$count} pedidos agora estão perfeitamente ligados aos clientes!");
        
        if ($notFound > 0) {
            $this->command->error("- Atenção: {$notFound} pedidos continuam órfãos porque o cliente não foi encontrado na nova base de dados.");
        }
    }
}