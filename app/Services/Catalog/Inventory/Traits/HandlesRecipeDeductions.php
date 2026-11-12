<?php
namespace App\Services\Catalog\Inventory\Traits;

use App\Enums\ModifierType;
use App\Models\ModifierOption;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TechnicalSheet;

trait HandlesRecipeDeductions
{
    protected function getSuppressedIngredientIds($originModel): array
    {
        if (!($originModel instanceof OrderItem)) return [];
        $suppressed = [];
        foreach ($originModel->modifiers()->with(['modifierOption.group'])->get() as $mod) {
            $opt = $mod->modifierOption;
            if (!$opt || empty($opt->removes_ingredient_id)) continue;
            
            $groupType = $opt->group?->type;
            $isObservation = ($groupType instanceof ModifierType) ? $groupType === ModifierType::OBSERVATION : in_array((string)$groupType, [ModifierType::OBSERVATION->value, 'observation', 'removal']);
            
            if ($isObservation || !empty($opt->removes_ingredient_id)) $suppressed[] = (int) $opt->removes_ingredient_id;
        }
        return array_unique($suppressed);
    }

    public function deductRecipeStock(Product $product, float $qtySold, $originModel = null): void
    {
        $sheets = TechnicalSheet::with('ingredient')->where('parent_product_id', $product->id)->where('is_active', true)->get();
        if ($sheets->isEmpty()) return;

        $suppressed = $this->getSuppressedIngredientIds($originModel);

        foreach ($sheets as $sheet) {
            $ingredient = $sheet->ingredient;
            if ($ingredient && $ingredient->track_inventory) {
                if (in_array((int) $ingredient->id, $suppressed, true)) {
                    $orderNum = ($originModel instanceof OrderItem) ? "#{$originModel->order_id}" : "Pedido";
                    $this->logMovement($ingredient, 'omission', 0, (float) $ingredient->current_stock, "Insumo poupado no {$orderNum} ({$product->name})", $originModel);
                    continue;
                }

                $ingQty = (float) $sheet->quantity * $qtySold;
                $newIngStock = (float) $ingredient->current_stock - $ingQty;

                $this->logMovement($ingredient, 'out', -$ingQty, $newIngStock, "Saída via Ficha Técnica", $originModel);
                $ingredient->updateQuietly(['current_stock' => $newIngStock]);
                $this->deductFromStorageLocations($ingredient, $ingQty);

                if ($qtySold > 0) {
                    $ingredient->current_stock = $newIngStock;
                    $this->checkThresholds($ingredient);
                }
                if ($newIngStock <= 0 && $ingredient->status === 'available') $this->triggerStockRupture($ingredient);
            }
        }
    }

    public function restoreRecipeStock(Product $product, float $qtyReturned, $originModel = null): void
    {
        $sheets = TechnicalSheet::with('ingredient')->where('parent_product_id', $product->id)->where('is_active', true)->get();
        if ($sheets->isEmpty()) return;

        foreach ($sheets as $sheet) {
            $ingredient = $sheet->ingredient;
            if ($ingredient && $ingredient->track_inventory) {
                $ingQty = (float) $sheet->quantity * $qtyReturned;
                $newIngStock = (float) $ingredient->current_stock + $ingQty;

                $this->logMovement($ingredient, 'in', $ingQty, $newIngStock, "Estorno via Ficha Técnica", $originModel);
                $ingredient->updateQuietly(['current_stock' => $newIngStock]);
                $this->addToStorageLocations($ingredient, $ingQty);

                if ($newIngStock > 0 && $ingredient->status === 'out_of_stock') {
                    $ingredient->current_stock = $newIngStock;
                    $this->triggerStockRestored($ingredient);
                }
            }
        }
    }

    public function processOrderItemStock(OrderItem $item, string $action = 'deduct'): void
    {
        $multiplier = $action === 'restore' ? -1 : 1;
        $itemsToProcess = collect([$item])->merge($item->subItems);

        foreach ($itemsToProcess as $processItem) {
            if ($processItem->product_id) {
                $product = Product::find($processItem->product_id);
                if ($product) {
                    if ($multiplier === 1) $this->deductStock($product, $processItem->quantity, $item);
                    else $this->restoreStock($product, $processItem->quantity, $item);
                }
            }

            $mods = $processItem->modifiers()->with('modifierOption.group')->get();

            // Mapeia quantidade apenas de opções PROPORCIONAIS por grupo
            $groupPropCounts = [];
            foreach ($mods as $mod) {
                $opt = $mod->modifierOption;
                $grpId = $mod->modifier_group_id ?? $opt?->modifier_group_id;
                $isFixed = ($opt?->portion_behavior ?? 'proportional') === 'fixed';

                if ($grpId && !$isFixed) {
                    $groupPropCounts[$grpId] = ($groupPropCounts[$grpId] ?? 0) + (float) ($mod->quantity ?? 1);
                }
            }

            foreach ($mods as $mod) {
                if ($mod->modifier_option_id) {
                    $opt = $mod->modifierOption ?? ModifierOption::with('group')->find($mod->modifier_option_id);
                    if ($opt && $opt->linked_product_id) {
                        $group = $opt->group;
                        $groupType = $group?->type;
                        $hasStockDeduction = ($groupType instanceof ModifierType) ? $groupType->hasPricingRules() : in_array((string)$groupType, [ModifierType::OPTION->value, ModifierType::ADD_ON->value, 'ingredient', 'option', 'add_on']);
                        
                        if ($hasStockDeduction) {
                            $linked = Product::find($opt->linked_product_id);
                            if ($linked) {
                                $baseQty = (float) ($opt->linked_qty ?? 1) * (float) ($mod->quantity ?? 1);
                                $factor = 1.0;
                                $isFixed = ($opt->portion_behavior ?? 'proportional') === 'fixed';

                                // Se for item de dose fixa, vai 100%. Se for proporcional, divide por kProp com fartura de 15%
                                if (!$isFixed && $group && ($group->portion_distribution_mode ?? 'additive') === 'proportional_split') {
                                    $kProp = (int) ($groupPropCounts[$group->id] ?? 1);
                                    if ($kProp > 1) {
                                        $factor = (1.0 + (0.15 * ($kProp - 1))) / $kProp;
                                    }
                                }

                                $qtyToProcess = round($baseQty * $factor, 4);
                                if ($multiplier === 1) $this->deductStock($linked, $qtyToProcess, $item);
                                else $this->restoreStock($linked, $qtyToProcess, $item);
                            }
                        }
                    }
                }
            }
        }
    }
}
