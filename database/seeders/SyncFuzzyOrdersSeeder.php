<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\Customer;

class SyncFuzzyOrdersSeeder extends Seeder
{
    public function run(): void
    {
        // Pega apenas os pedidos que AINDA estão órfãos
        $orders = Order::whereNull('customer_id')
                       ->whereNotNull('customer_name')
                       ->where('customer_name', '!=', '')
                       ->get();

        $count = 0;

        foreach ($orders as $order) {
            // 1. Limpa espaços fantasmas do início e do fim
            $cleanName = trim($order->customer_name);
            
            // Ignora o genérico do WhatsApp para não bagunçar
            if (str_contains(strtolower($cleanName), 'avulso')) {
                continue; 
            }

            // TENTATIVA 1: Nome exato, mas sem espaços fantasmas
            $customer = Customer::where('name', $cleanName)->first();

            // TENTATIVA 2: Busca aproximada (LIKE)
            if (!$customer) {
                // Procura clientes que contenham esse nome no meio do seu nome completo
                $possibleCustomers = Customer::where('name', 'LIKE', '%' . $cleanName . '%')->get();
                
                // Só amarra se tiver certeza absoluta (apenas 1 resultado)
                if ($possibleCustomers->count() === 1) {
                    $customer = $possibleCustomers->first();
                }
            }

            // Se o nosso detetive encontrou o cliente, fazemos o match!
            if ($customer) {
                $order->customer_id = $customer->id;
                $order->timestamps = false; // Não muda a data original do pedido
                $order->save();
                
                $count++;
                $this->command->line("Ligado: Pedido #{$order->id} ({$cleanName}) -> Cliente ID {$customer->id} ({$customer->name})");
            }
        }

        $this->command->info("==== RESULTADO DA BUSCA INTELIGENTE ====");
        $this->command->info("- Mais {$count} pedidos foram resgatados e ligados aos clientes corretos!");
    }
}