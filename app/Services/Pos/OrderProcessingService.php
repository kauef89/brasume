<?php

namespace App\Services\Pos;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderProcessingService
{
    /**
     * Processa o carrinho de compras e grava a encomenda e os seus modificadores
     */
    public function processCheckout(array $payload): Order
    {
        // 🚀 O Transacional protege os dados. Se ocorrer um erro no item 5, o Laravel apaga o 1, 2, 3 e 4, impedindo furos de caixa!
        return DB::transaction(function () use ($payload) {

            $routing = $payload["routing"];
            $financials = $payload["financials"];
            $order = null;

            if (!empty($payload["order_id"])) {
                $order = Order::find($payload["order_id"]);
                $order->subtotal += $financials["subtotal"];
                $order->total += $financials["subtotal"] - $financials["discount"];
                $order->save();
            } else {
                $customerName = null;
                if ($routing["type"] === "table") {
                    $customerName = "Mesa " . ($routing["table"]["name"] ?? "Avulsa");
                } elseif ($routing["type"] === "counter") {
                    $customerName = $routing["customer"]["name"] ?? ($routing["identifier"] ?? "Cliente Balcão");
                } elseif ($routing["type"] === "delivery") {
                    $customerName = $routing["customer"]["name"] ?? "Cliente Delivery";
                }

                $order = Order::create([
                                    "customer_id" => $routing["customer"]["id"] ?? null,
                                    "address_id" => $routing["address"]["id"] ?? null,
                                    "customer_name" => $customerName,
                                    "table_number" => $routing["table"]["name"] ?? null,
                                    "type" => $routing["type"],
                                    "status" => "pending",
                                    "payment_status" => "pending",
                                    "subtotal" => $financials["subtotal"],
                                    "discount" => $financials["discount"],
                                    "delivery_fee" => $financials["deliveryFee"],
                                    "total" => $financials["finalTotal"],
                                    "employee_id" => auth()->id(),

                                    // 🚀 CORREÇÃO: Usando o novo campo plural (JSON) e removendo os antigos!
                                    "delivery_payment_status" => $routing["delivery_payment_status"] ?? 'pending_choice',
                                    "delivery_payment_methods" => !empty($routing["delivery_payment_methods"])
                                                                    ? json_encode($routing["delivery_payment_methods"])
                                                                    : null,
                                ]);
            }

            foreach ($payload["items"] as $item) {
                $productName = $item["name"] . ($item["isTakeaway"] ? " (Viagem)" : "");

                $mainOrderItem = $order->items()->create([
                    "product_id" => $item["id"],
                    "product_name" => $productName,
                    "quantity" => $item["qty"],
                    "unit_price" => $item["price"],
                    "total_price" => $item["price"] * $item["qty"],
                ]);

                // Se for um item COMBO
                if (isset($item["isCombo"]) && $item["isCombo"] && !empty($item["comboItems"])) {
                    foreach ($item["comboItems"] as $cItem) {
                        $subOrderItem = $order->items()->create([
                            "parent_item_id" => $mainOrderItem->id,
                            "is_combo_item" => true,
                            "product_id" => $cItem["product_id"] ?? null,
                            "product_name" => $cItem["name"],
                            "quantity" => $item["qty"],
                            "unit_price" => 0,
                            "total_price" => 0,
                        ]);

                        if (!empty($cItem["structuredModifiers"])) {
                            foreach ($cItem["structuredModifiers"] as $mod) {
                                $subOrderItem->modifiers()->create([
                                    'modifier_option_id' => $mod['id'] ?? null,
                                    'name' => trim($mod['name']),
                                    'quantity' => $mod['qty'] * $item["qty"],
                                    'price' => $mod['price']
                                ]);
                            }
                        } elseif (!empty($cItem["modifiersText"])) {
                            foreach (explode(" • ", $cItem["modifiersText"]) as $mod) {
                                $subOrderItem->modifiers()->create([
                                    "name" => trim($mod),
                                    "quantity" => $item["qty"],
                                    "price" => 0
                                ]);
                            }
                        }
                    }
                }
                // Se for um item SIMPLES com adicionais
                elseif (!empty($item["structuredModifiers"])) {
                    foreach ($item["structuredModifiers"] as $mod) {
                        $mainOrderItem->modifiers()->create([
                            "modifier_option_id" => $mod["id"] ?? null,
                            "name" => trim($mod["name"]),
                            "quantity" => $mod["qty"] * $item["qty"],
                            "price" => $mod["price"],
                        ]);
                    }
                }
            }

            return $order;
        });
    }
}
