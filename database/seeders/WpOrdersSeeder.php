<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Product; // Precisamos do Model Product para a verificação

class WpOrdersSeeder extends Seeder
{
    public function run(): void
    {
        Order::unguard();
        OrderItem::unguard();
        OrderItemModifier::unguard();

        // 1. FAXINA DOS PEDIDOS ANTERIORES
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('order_item_modifiers')->truncate();
        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $csvFile = base_path('storage/app/sessions-with-items.csv');
        
        if (!file_exists($csvFile)) {
            $this->command->error("Arquivo {$csvFile} não encontrado!");
            return;
        }

        $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Remove a primeira linha (cabeçalho)
        array_shift($lines);

        if (empty($lines)) {
            $this->command->error("ERRO CRÍTICO: O ficheiro CSV está vazio!");
            return;
        }

        $ordersGrouped = [];

        // 2. PARSER AVANÇADO (Lê o CSV usando "Raio-X")
        foreach ($lines as $line) {
            $line = trim($line);

            // Procura o padrão exato do final da linha
            if (!preg_match('/,(\d+\.\d{2}),(\d+\.\d{2}),(\d+\.\d{2}),([^,]*),(.*),([^,]+),([^,\s]+)$/', $line, $endMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $qtd = (float) $endMatches[1][0];
            $preco = (float) $endMatches[2][0];
            $total = (float) $endMatches[3][0];
            $adicionais_json = $endMatches[5][0];
            $status_item = trim($endMatches[7][0]);

            $prefixLength = $endMatches[0][1];
            $prefix = substr($line, 0, $prefixLength);

            // Procura os marcadores fixos no meio da linha
            if (!preg_match('/,(counter|table|delivery|pickup),([^,]+),(\d+),(\d*),/', $prefix, $midMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $modalidade = $midMatches[1][0];
            $status_pedido = $midMatches[2][0];
            $id_item = $midMatches[3][0];
            $id_prod = $midMatches[4][0];

            $leftLength = $midMatches[0][1];
            $left = substr($prefix, 0, $leftLength);
            $nome_prod = trim(substr($prefix, $leftLength + strlen($midMatches[0][0])));

            $comma1 = strpos($left, ',');
            $comma2 = strpos($left, ',', $comma1 + 1);

            $id_pedido = substr($left, 0, $comma1);
            $data_pedido = substr($left, $comma1 + 1, $comma2 - $comma1 - 1);
            $nome_cliente = trim(substr($left, $comma2 + 1));

            if (!isset($ordersGrouped[$id_pedido])) {
                $ordersGrouped[$id_pedido] = [
                    'id_pedido' => $id_pedido,
                    'data_pedido' => $data_pedido,
                    'nome_cliente' => $nome_cliente,
                    'modalidade' => $modalidade,
                    'status_pedido' => $status_pedido,
                    'status_item' => $status_item,
                    'items' => []
                ];
            }

            $ordersGrouped[$id_pedido]['items'][] = [
                'id_produto' => $id_prod,
                'nome_produto' => $nome_prod,
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'total_item' => $total,
                'adicionais_json' => $adicionais_json
            ];
        }

        $orderCount = 0; $itemCount = 0; $modCount = 0;

        // VERIFICAÇÃO DE SEGURANÇA: Pega todos os IDs de produtos que realmente existem no banco!
        $validProductIds = Product::pluck('id')->toArray();

        // 3. INSERÇÃO NA BASE DE DADOS
        foreach ($ordersGrouped as $orderId => $info) {
            
            $totalPedido = array_sum(array_column($info['items'], 'total_item'));

            $customerName = $info['nome_cliente'];
            $tableNumber = null;
            
            if (preg_match('/^Mesa\s+(\d+)$/i', $customerName, $matches)) {
                $tableNumber = $matches[1];
                $customerName = null;
            } else if (strtolower($customerName) === 'principal' || strtolower($customerName) === 'null') {
                $customerName = 'Balcão Expresso';
            }

            $paymentStatus = 'pending';
            if ($info['status_item'] === 'paid') $paymentStatus = 'paid';
            if ($info['status_pedido'] === 'cancelled') $paymentStatus = 'cancelled';

            $order = Order::create([
                'id' => $orderId,
                'customer_name' => $customerName,
                'table_number' => $tableNumber,
                'type' => $info['modalidade'],
                'status' => $info['status_pedido'],
                'payment_status' => $paymentStatus,
                'total' => $totalPedido,
                'subtotal' => $totalPedido,
                'created_at' => $info['data_pedido'],
                'updated_at' => $info['data_pedido'],
            ]);
            $orderCount++;

            foreach ($info['items'] as $itemRow) {
                
                // MÁGICA AQUI: Se o ID vier vazio ou o produto não existir mais no banco, deixa como NULL.
                $prodId = !empty($itemRow['id_produto']) ? (int) $itemRow['id_produto'] : null;
                if ($prodId && !in_array($prodId, $validProductIds)) {
                    $prodId = null; 
                }

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $prodId, // Usa o ID verificado e seguro
                    'product_name' => $itemRow['nome_produto'],
                    'quantity' => $itemRow['quantidade'],
                    'unit_price' => $itemRow['preco_unitario'],
                    'total_price' => $itemRow['total_item'],
                ]);
                $itemCount++;

                // LIGAÇÃO DOS MODIFICADORES
                $jsonMod = $itemRow['adicionais_json'];
                if (!empty($jsonMod) && $jsonMod !== 'null' && $jsonMod !== '[]') {
                    $adicionais = json_decode($jsonMod, true);
                    if (is_array($adicionais)) {
                        foreach ($adicionais as $add) {
                            OrderItemModifier::create([
                                'order_item_id' => $orderItem->id,
                                'name' => $add['name'] ?? 'Adicional',
                                'price' => isset($add['price']) ? (float) $add['price'] : 0,
                                'quantity' => isset($add['qty']) ? (int) $add['qty'] : 1,
                            ]);
                            $modCount++;
                        }
                    }
                }
            }
        }

        $this->command->info("==== RESUMO DA IMPORTAÇÃO ====");
        $this->command->line("- Pedidos Gerados: {$orderCount}");
        $this->command->line("- Itens de Cardápio: {$itemCount}");
        $this->command->line("- Modificadores Inseridos: {$modCount}");
    }
}