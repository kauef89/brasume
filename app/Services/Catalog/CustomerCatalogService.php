<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CustomerCatalogService
{
    public function getCatalog(): array
    {
        return Cache::tags(['catalog'])->remember('customer_catalog_data', 86400, function () {

            $categories = Category::where("type", "external")
                ->where("is_active", 1)
                ->orderBy("sort_order")
                // 🚀 slug adicionado ao select das categorias
                ->select("id", "name", "slug", "image_path", "parent_id", "sort_order")
                ->get()
                ->map(function ($category) {
                    $category->image_url = $category->image_path ? url("/media/file?path=" . $category->image_path) : null;
                    return $category;
                });

            $validCategoryIds = $categories->pluck("id")->toArray();

            // OTIMIZAÇÃO: Cache de produtos para evitar N+1
            $categoryProductsCache = Product::whereNotNull('category_id')
                ->whereIn("type", ["dish", "resale", "manufactured"])
                ->whereIn("status", ["available", "out_of_stock", "paused", "active"])
                ->where("show_on_delivery", true)
                ->get()
                ->groupBy('category_id');

            $products = Product::with(["modifiers.options", "comboGroups.items.product"])
                ->whereIn("category_id", $validCategoryIds)
                ->whereIn("type", ["dish", "resale", "combo", "manufactured"])
                ->whereIn("status", ["available", "out_of_stock", "paused", "active"])
                ->where("show_on_delivery", true)
                ->get()
                ->map(function ($product) use ($categoryProductsCache) {
                    return $this->formatProductForCustomer($product, $categoryProductsCache);
                })->values();

            return [
                "categories" => $categories,
                "products" => $products,
            ];
        });
    }

    private function formatProductForCustomer(Product $product, $categoryProductsCache): array
    {
        $basePrice = $product->delivery_price > 0 ? $product->delivery_price : $product->dine_in_price;
        $finalPrice = $product->promo_price > 0 ? $product->promo_price : $basePrice;

        return [
            "id" => $product->id,
            "category_id" => $product->category_id,
            "name" => $product->name,
            // 🚀 slug adicionado ao payload do produto
            "slug" => $product->slug,
            "description" => $product->description,
            "type" => $product->type,
            "status" => $product->status,
            "price" => (float) $finalPrice,
            "image" => $product->image_path ? url("/media/file?path=" . $product->image_path) : null,

            "modifiers" => $product->modifiers->map(function ($group) use ($product, $finalPrice) {
                $excluded = json_decode($group->pivot->excluded_options ?? '[]', true) ?? [];

                return [
                    "id" => $group->id,
                    "name" => $group->display_name ?? $group->name,
                    "type" => $group->type,
                    "status" => $group->status,
                    "loyalty_reward" => (bool) $product->loyalty_reward,
                    "loyalty_points_cost" => (int) $product->loyalty_points_cost,
                    "price" => (float) $finalPrice,
                    "pricing_strategy" => $group->pricing_strategy,
                    "display_order_strategy" => $group->display_order_strategy,
                    "min_selections" => $group->min_selections,
                    "max_selections" => $group->max_selections,
                    "max_free" => $group->max_free,
                    "increment" => $group->increment,
                    "allow_quantity" => $group->allow_quantity,
                    "options" => $group->options
                        ->filter(fn($opt) => !in_array($opt->id, $excluded))
                        ->map(fn($opt) => [
                            "id" => $opt->id,
                            "name" => $opt->name,
                            "price" => (float) $opt->price,
                            "sort_order" => $opt->sort_order ?? 0,
                                            "is_active" => (bool) ($opt->is_active ?? true),
                                            "is_active" => (bool) ($opt->is_active ?? true),
                                            "is_active" => (bool) ($opt->is_active ?? true),
                        ])->values(),
                ];
            }),
            "combo_groups" => $product->comboGroups->map(function ($group) use ($categoryProductsCache) {
                return [
                    "id" => $group->id,
                    "name" => $group->name,
                    "min_choices" => $group->min_choices,
                    "max_choices" => $group->max_choices,
                    "items" => $group->items->flatMap(function ($item) use ($categoryProductsCache) {
                        if ($item->category_id) {
                            $categoryProducts = $categoryProductsCache->get($item->category_id, collect());

                            return $categoryProducts->map(fn($catProduct) => [
                                "id" => "cat_" . $item->id . "_prod_" . $catProduct->id,
                                "product_id" => $catProduct->id,
                                "category_id" => $item->category_id,
                                "product_name" => $catProduct->name,
                                "additional_price" => (float) $item->additional_price,
                            ]);
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
