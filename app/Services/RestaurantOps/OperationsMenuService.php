<?php

namespace App\Services\RestaurantOps;

use App\Services\Customers\CustomerMappingService;
use App\Models\Category;
use App\Models\Product;
use App\Models\DiningTable;
use App\Models\TableArea;
use App\Models\Street;
use App\Models\Order;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OperationsMenuService
{
    protected CustomerMappingService $customerMapper;

    public function __construct(CustomerMappingService $customerMapper)
    {
        $this->customerMapper = $customerMapper;
    }

    public function getOrderEntryData(Request $request): array
    {
        // 🚀 OTIMIZAÇÃO: Injetado latestCostHistory
        $products = Product::whereIn("type", ["dish", "combo", "resale"])
            ->whereIn("status", ["available", "out_of_stock", "paused", "active"])
            ->where("show_on_menu", true)
            ->with(["modifiers.options", "comboGroups.items.product", "latestCostHistory"])
            ->get();

        $categories = Category::where("type", "external")
            ->whereNotIn("status", ["archived", "arquivado"])
            ->select("id", "name", "parent_id", "image_path", "sort_order", "status")
            ->orderBy("sort_order", "asc")
            ->get()
            ->map(function ($category) {
                $category->image_url = $category->image_path ? url("/media/file?path=" . $category->image_path) : null;
                return $category;
            });

        $categoryTree = Category::where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->where("type", "external")
            ->whereNotIn("status", ["archived", "arquivado"])
            ->with([
                'children' => fn($q) => $q->where('type', 'external')->whereNotIn('status', ['archived', 'arquivado'])->orderBy('sort_order', 'asc'),
                'children.children' => fn($q) => $q->where('type', 'external')->whereNotIn('status', ['archived', 'arquivado'])->orderBy('sort_order', 'asc'),
                'children.children.children' => fn($q) => $q->where('type', 'external')->whereNotIn('status', ['archived', 'arquivado'])->orderBy('sort_order', 'asc'),
                'children.children.children.children' => fn($q) => $q->where('type', 'external')->whereNotIn('status', ['archived', 'arquivado'])->orderBy('sort_order', 'asc'),
            ])
            ->orderBy("sort_order", "asc")
            ->get();

        $validExternalCategoryIds = $categories->pluck("id")->toArray();

        // 🚀 OTIMIZAÇÃO: Injetado latestCostHistory
        $categoryProductsCache = Product::whereNotNull('category_id')
            ->whereIn("type", ["dish", "resale", "manufactured"])
            ->whereIn("status", ["available", "out_of_stock", "paused", "active"])
            ->where("show_on_menu", true)
            ->with("latestCostHistory")
            ->get()
            ->groupBy('category_id');

        $mappedProducts = $products
            ->filter(fn($product) => in_array($product->category_id, $validExternalCategoryIds))
            ->map(function ($product) use ($categoryProductsCache) {
                return $this->mapProductData($product, false, $categoryProductsCache);
            })->values();

        $tableAreas = $this->getTableAreas();
        $customers = $this->customerMapper->getAllForPos();
        $editOrderData = null;

        if ($request->has("append_to_order")) {
            $editOrderData = Order::with(["customer", "addressModel", "items.modifiers", "diningTable"])
                                  ->find($request->input("append_to_order"));
        }

        $withRelations = [];
        if (method_exists(Street::class, 'neighborhood')) {
            $withRelations[] = 'neighborhood';
        }
        if (method_exists(Street::class, 'segments')) {
            $withRelations[] = 'segments.neighborhood';
        }

        $streetQuery = Street::with($withRelations);
        if (true) {
            $streetQuery->where('is_active', true);
        }
        $streets = $streetQuery->orderBy('name')->get();

        return [
            "operatorName"      => auth()->user()->name ?? "Operador Caixa",
            "initialCategories" => $categories,
            "categoryTree"      => $categoryTree,
            "initialProducts"   => $mappedProducts,
            "initialTableAreas" => $tableAreas,
            "initialCustomers"  => $customers,
            "initialStreets"    => $streets,
            "streets"           => $streets,
            "editOrderData"     => $editOrderData,
        ];
    }

    public function getTerminalData(Request $request): array
    {
        $categories = Category::where("type", "!=", "internal")
            ->whereNotIn("status", ["archived", "arquivado"])
            ->select("id", "name", "parent_id", "image_path", "sort_order", "status")
            ->orderBy("sort_order", "asc")
            ->get()
            ->map(function ($category) {
                $category->image_url = $category->image_path ? url("/media/file?path=" . $category->image_path) : null;
                return $category;
            });

        $tableAreas = $this->getTableAreas();

        // 🚀 OTIMIZAÇÃO: Injetado latestCostHistory
        $categoryProductsCache = Product::whereNotNull('category_id')
            ->whereIn("type", ["dish", "resale", "manufactured"])
            ->whereIn("status", ["available", "out_of_stock", "paused", "active"])
            ->where("show_on_menu", true)
            ->with("latestCostHistory")
            ->get()
            ->groupBy('category_id');

        // 🚀 OTIMIZAÇÃO: Injetado latestCostHistory
        $products = Product::with(["modifiers.options", "comboGroups.items.product", "latestCostHistory"])
            ->whereIn("type", ["resale", "recipe", "combo", "manufactured"])
            ->whereIn("status", ["available", "out_of_stock", "paused"])
            ->get()
            ->map(function ($product) use ($categoryProductsCache) {
                return $this->mapProductData($product, true, $categoryProductsCache);
            });

        return [
            "operatorName"      => auth()->user()->name ?? "Cliente",
            "initialCategories" => $categories,
            "initialProducts"   => $products,
            "initialTableAreas" => $tableAreas,
        ];
    }

    public function getTableAreas()
    {
        return TableArea::with([
            'diningTables.activeOrders' => function ($query) {
                $query->select('id', 'dining_table_id', 'table_number', 'customer_name as identifier');
            },
            'diningTables.linkedTables.activeOrders' => function ($query) {
                $query->select('id', 'dining_table_id', 'table_number', 'customer_name as identifier');
            }
        ])
        ->select("id", "name")
        ->get()
        ->map(function ($area) {
            return [
                "id"   => $area->id,
                "name" => $area->name,
                "tables" => $area->diningTables->map(function ($table) {
                    // Agrega pedidos próprios com os pedidos das mesas filhas agrupadas
                    $allOrders = collect($table->activeOrders);

                    if ($table->relationLoaded('linkedTables') && $table->linkedTables->isNotEmpty()) {
                        $childOrders = $table->linkedTables->flatMap(fn ($child) => $child->activeOrders);
                        $allOrders = $allOrders->merge($childOrders);
                    }

                    $isOccupied = $allOrders->isNotEmpty();

                    return [
                        "id"              => $table->id,
                        "name"            => $table->name,
                        "status"          => $isOccupied ? 'occupied' : ($table->status === 'free' ? 'available' : ($table->status ?? 'available')),
                        "parent_table_id" => $table->parent_table_id ?? null,
                        "linked_tables"   => $table->relationLoaded('linkedTables')
                            ? $table->linkedTables->map(fn ($lt) => ['id' => $lt->id, 'name' => $lt->name])->toArray()
                            : [],
                        "active_orders"   => $allOrders->values()->toArray(),
                    ];
                })->toArray(),
            ];
        });
    }

    private function mapProductData($product, $isTerminal, $categoryProductsCache)
    {
        $price = $isTerminal && $product->promo_price > 0 ? $product->promo_price : $product->dine_in_price;

        return [
            "id"             => $product->id,
            "category_id"    => $product->category_id,
            "name"           => $product->name,
            "description"    => $product->description,
            "type"           => $product->type,
            "status"         => $product->status,
            "price"          => (float) $price,
            "delivery_price" => (float) ($product->delivery_price > 0 ? $product->delivery_price : $product->dine_in_price),
            "image"          => $product->image_path ? url("/media/file?path=" . $product->image_path) : null,
            "is_grab_and_go" => !(bool) $product->requires_preparation,
            "track_inventory"=> (bool) $product->track_inventory,
            "current_stock"  => (float) $product->current_stock,
            "modifiers"      => $product->modifiers->map(function ($group) {
                $excluded = json_decode($group->pivot->excluded_options ?? '[]', true) ?? [];
                return [
                    "id"                     => $group->id,
                    "name"                   => $group->display_name ?? $group->name,
                    "type"                   => $group->type,
                    "pricing_strategy"       => $group->pricing_strategy,
                    "display_order_strategy" => $group->display_order_strategy,
                    "min_selections"         => $group->min_selections,
                    "max_selections"         => $group->max_selections,
                    "max_free"               => $group->max_free,
                    "increment"              => $group->increment,
                    "allow_quantity"         => $group->allow_quantity,
                    "options"                => $group->options
                        ->filter(fn($opt) => !in_array($opt->id, $excluded))
                        ->map(function ($opt) {
                            return [
                                "id"                => $opt->id,
                                "name"              => $opt->name,
                                "price"             => (float) $opt->price,
                                "sort_order"        => $opt->sort_order ?? 0,
                                "historical_weight" => (int) ($opt->historical_weight ?? 0),
                                "is_active"         => (bool) ($opt->is_active ?? true),
                            ];
                        })->values(),
                ];
            }),
            "combo_groups"   => $product->comboGroups->map(function ($group) use ($isTerminal, $categoryProductsCache) {
                return [
                    "id"          => $group->id,
                    "name"        => $group->name,
                    "min_choices" => $group->min_choices,
                    "max_choices" => $group->max_choices,
                    "items"       => $group->items->flatMap(function ($item) use ($isTerminal, $categoryProductsCache) {
                        if ($item->category_id && !$isTerminal) {
                            $categoryProducts = $categoryProductsCache->get($item->category_id, collect());

                            return $categoryProducts->map(function ($catProduct) use ($item) {
                                return [
                                    "id"               => "cat_" . $item->id . "_prod_" . $catProduct->id,
                                    "product_id"       => $catProduct->id,
                                    "category_id"      => $item->category_id,
                                    "product_name"     => $catProduct->name,
                                    "additional_price" => (float) $item->additional_price,
                                    "status"           => $catProduct->status,
                                ];
                            });
                        }
                        return [[
                            "id"               => (string) $item->id,
                            "product_id"       => $item->product_id,
                            "category_id"      => null,
                            "product_name"     => $item->product->name ?? "Item Desconhecido",
                            "additional_price" => (float) $item->additional_price,
                            "status"           => $item->product->status ?? 'available',
                        ]];
                    })->values(),
                ];
            }),
        ];
    }
}

class_alias(OperationsMenuService::class, 'App\Services\Terminals\PosMenuService');
