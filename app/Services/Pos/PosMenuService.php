<?php

namespace App\Services\Pos;

use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PosMenuService
{
    /**
     * Retorna os dados completos para a tela de Novo Pedido (Caixa/Balcão)
     */
    public function getOrderEntryData(Request $request): array
    {
        $products = Product::whereIn("type", ["dish", "combo", "resale"])
            ->whereIn("status", ["available", "out_of_stock", "active"])
            ->where("show_on_menu", true)
            ->with(["modifiers.options", "comboGroups.items.product"])
            ->get();

        $categoryIdsFromProducts = $products->pluck("category_id")->unique()->filter();

        $categories = Category::whereIn("id", $categoryIdsFromProducts)
            ->where("type", "external")
            ->select("id", "name")
            ->orderBy("name")
            ->get();

        $validExternalCategoryIds = $categories->pluck("id")->toArray();

        $mappedProducts = $products
            ->filter(fn($product) => in_array($product->category_id, $validExternalCategoryIds))
            ->map(function ($product) {
                return $this->mapProductData($product, false);
            })->values();

        $tableAreas = $this->getTableAreas();
        $customers = $this->getCustomers();

        $editOrderData = null;
        if ($request->has("append_to_order")) {
            $editOrderData = Order::with(["customer", "address"])->find($request->input("append_to_order"));
        }

        return [
            "operatorName" => auth()->user()->name ?? "Operador Caixa",
            "initialCategories" => $categories,
            "initialProducts" => $mappedProducts,
            "initialTableAreas" => $tableAreas,
            "initialCustomers" => $customers,
            "editOrderData" => $editOrderData,
        ];
    }

    /**
     * Retorna os dados completos para a tela de Autoatendimento (Terminal)
     */
    public function getTerminalData(Request $request): array
    {
        $categories = Category::where("type", "!=", "internal")
            ->where("is_active", 1)
            ->orderBy("sort_order")
            ->select("id", "name")
            ->get();

        $tableAreas = $this->getTableAreas();

        $products = Product::with(["modifiers.options", "comboGroups.items.product"])
            ->whereIn("type", ["resale", "recipe", "combo", "manufactured"])
            ->whereIn("status", ["available", "unavailable"])
            ->get()
            ->map(function ($product) {
                return $this->mapProductData($product, true); // true = usa promo_price
            });

        $settings = CompanySetting::first();

        return [
            "operatorName" => auth()->user()->name ?? "Cliente",
            "initialCategories" => $categories,
            "initialProducts" => $products,
            "initialTableAreas" => $tableAreas,
            "companySettings" => [
                "has_service_tax" => (bool) ($settings->has_service_tax ?? false),
                "service_tax_percentage" => (float) ($settings->service_tax_percentage ?? 10.0),
            ],
        ];
    }

    // ==========================================
    // FUNÇÕES AUXILIARES (DRY - Don't Repeat Yourself)
    // ==========================================

    private function getTableAreas()
    {
        return DB::table("table_areas")->select("id", "name")->get()->map(function ($area) {
            return [
                "id" => $area->id,
                "name" => $area->name,
                "tables" => DB::table("dining_tables")->where("table_area_id", $area->id)->select("id", "name", "status")->get(),
            ];
        });
    }

    private function getCustomers()
    {
        return DB::table("customers")->select("id", "name", "phone", "cpf")->whereNull("deleted_at")->get()->map(function ($c) {
            $addresses = DB::table("addresses")
                ->where("customer_id", $c->id)
                ->leftJoin("streets", "addresses.street_id", "=", "streets.id")
                ->leftJoin("neighborhoods", "addresses.neighborhood_id", "=", "neighborhoods.id")
                ->select("addresses.id", "addresses.number", "addresses.complement", "streets.name as street_name", "neighborhoods.name as neighborhood_name", "neighborhoods.delivery_fee")
                ->get()
                ->map(function ($addr) {
                    return [
                        "id" => $addr->id,
                        "full_address" => trim("{$addr->street_name}, {$addr->number} - {$addr->neighborhood_name} " . ($addr->complement ? "({$addr->complement})" : "")),
                        "neighborhood" => $addr->neighborhood_name,
                        "delivery_fee" => (float) $addr->delivery_fee,
                    ];
                });
            return [
                "id" => $c->id,
                "name" => $c->name,
                "phone" => $c->phone,
                "cpf" => $c->cpf,
                "addresses" => $addresses,
            ];
        });
    }

    private function mapProductData($product, $isTerminal)
    {
        $price = $isTerminal && $product->promo_price > 0 ? $product->promo_price : $product->dine_in_price;

        return [
            "id" => $product->id,
            "category_id" => $product->category_id,
            "name" => $product->name,
            "type" => $product->type,
            "status" => $product->status,
            "price" => (float) $price,
            "delivery_price" => (float) ($product->delivery_price > 0 ? $product->delivery_price : $product->dine_in_price),
            "image" => $product->image_path ? asset("storage/" . $product->image_path) : null,
            "modifiers" => $product->modifiers->map(function ($group) {
                return [
                    "id" => $group->id,
                    "name" => $group->display_name ?? $group->name,
                    "type" => $group->type,
                    "pricing_strategy" => $group->pricing_strategy,
                    "display_order_strategy" => $group->display_order_strategy,
                    "min_selections" => $group->min_selections,
                    "max_selections" => $group->max_selections,
                    "max_free" => $group->max_free,
                    "increment" => $group->increment,
                    "allow_quantity" => $group->allow_quantity,
                    "options" => $group->options->map(function ($opt) {
                        return [
                            "id" => $opt->id,
                            "name" => $opt->name,
                            "price" => (float) $opt->price,
                            "sort_order" => $opt->sort_order ?? 0,
                        ];
                    }),
                ];
            }),
            "combo_groups" => $product->comboGroups->map(function ($group) use ($isTerminal) {
                return [
                    "id" => $group->id,
                    "name" => $group->name,
                    "min_choices" => $group->min_choices,
                    "max_choices" => $group->max_choices,
                    "items" => $group->items->flatMap(function ($item) use ($isTerminal) {
                        if ($item->category_id && !$isTerminal) {
                            $categoryProducts = Product::where("category_id", $item->category_id)
                                ->whereIn("type", ["dish", "resale", "manufactured"])
                                ->whereIn("status", ["available", "out_of_stock", "active"])
                                ->where("show_on_menu", true)
                                ->get();
                            return $categoryProducts->map(function ($catProduct) use ($item) {
                                return [
                                    "id" => "cat_" . $item->id . "_prod_" . $catProduct->id,
                                    "product_id" => $catProduct->id,
                                    "category_id" => $item->category_id,
                                    "product_name" => $catProduct->name,
                                    "additional_price" => (float) $item->additional_price,
                                ];
                            });
                        }
                        return [[
                            "id" => (string) $item->id,
                            "product_id" => $item->product_id,
                            "category_id" => null,
                            "product_name" => $item->product->name ?? "Item Desconhecido",
                            "additional_price" => (float) $item->additional_price,
                        ]];
                    })->values(),
                ];
            }),
        ];
    }
}
