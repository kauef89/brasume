<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use App\Enums\ModifierType;
use App\Models\Product;
use App\Services\Catalog\ProductCostingService;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

class ModifiersDemandSection
{
    public static function make(): Section
    {
        return Section::make("Engenharia de Cardápio: Demanda & Recheios")
            ->description("Raio-x de custos variáveis por taxa real de escolha (Take Rate) e rateio proporcional.")
            ->icon("heroicon-o-chart-pie")
            ->visible(fn (Get $get, ?Product $record) => $record && $record->modifiers()->exists())
            ->schema([
                Placeholder::make("modifiers_analytics")
                    ->hiddenLabel()
                    ->content(function (?Product $record) {
                        if (!$record) return '';

                        $groups = $record->modifiers()->with(['options.linkedProduct', 'options.removesIngredient'])->get();
                        if ($groups->isEmpty()) {
                            return new HtmlString('<p class="text-xs text-gray-500">Nenhum grupo de modificadores ativo vinculado a este produto.</p>');
                        }

                        $doughCost      = ProductCostingService::calculateBaseCost($record);
                        $predictiveCost = ProductCostingService::getPredictiveModifiersCost($record);
                        $totalRealCost  = $doughCost + $predictiveCost;
                        $sellPrice      = (float) ($record->dine_in_price ?: $record->delivery_price ?: 0);
                        $realMarginPct  = $sellPrice > 0 ? (($sellPrice - $totalRealCost) / $sellPrice) * 100 : 0;

                        // Cálculo do Pior Cenário de Margem (respeitando Rateio Proporcional, Dose Fixa e Adicionais)
                        $worstCaseFillingCost  = 0.0;
                        $worstCaseExtraRevenue = 0.0;

                        foreach ($groups->where('type', ModifierType::OPTION) as $g) {
                            $maxChoices     = $g->max_free > 0 ? (int) $g->max_free : max((int) ($g->min_selections ?? 1), 1);
                            $isProportional = ($g->portion_distribution_mode ?? 'additive') === 'proportional_split';

                            $scoredOptions = [];
                            foreach ($g->options->where('is_active', true) as $opt) {
                                if ($opt->linkedProduct) {
                                    $fullCost   = ProductCostingService::calculateBaseCost($opt->linkedProduct) * (float) ($opt->linked_qty ?? 1);
                                    $extraPrice = (float) ($opt->price ?? 0);

                                    $scoredOptions[] = [
                                        'cost'       => $fullCost,
                                        'price'      => $extraPrice,
                                        'net_burden' => $fullCost - $extraPrice,
                                        'is_fixed'   => ($opt->portion_behavior ?? 'proportional') === 'fixed',
                                    ];
                                }
                            }

                            usort($scoredOptions, fn ($a, $b) => $b['net_burden'] <=> $a['net_burden']);
                            $selectedWorst = array_slice($scoredOptions, 0, $maxChoices);

                            $propItems  = array_filter($selectedWorst, fn ($i) => !$i['is_fixed']);
                            $fixedItems = array_filter($selectedWorst, fn ($i) => $i['is_fixed']);

                            $kProp      = count($propItems);
                            $propFactor = ($isProportional && $kProp > 1) ? ((1.0 + (0.15 * ($kProp - 1))) / $kProp) : 1.0;

                            foreach ($fixedItems as $item) {
                                $worstCaseFillingCost  += $item['cost'];
                                $worstCaseExtraRevenue += $item['price'];
                            }
                            foreach ($propItems as $item) {
                                $worstCaseFillingCost  += ($item['cost'] * $propFactor);
                                $worstCaseExtraRevenue += $item['price'];
                            }
                        }

                        $worstCaseSellPrice = $sellPrice + $worstCaseExtraRevenue;
                        $worstCaseTotalCost = $doughCost + $worstCaseFillingCost;
                        $worstCaseMarginPct = $worstCaseSellPrice > 0
                            ? (($worstCaseSellPrice - $worstCaseTotalCost) / $worstCaseSellPrice) * 100
                            : 0;

                        $html = "
                        <div class='space-y-6 text-sm'>
                            <!-- CARDS RESUMO -->
                            <div class='grid grid-cols-1 md:grid-cols-4 gap-4'>
                                <div class='p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700'>
                                    <span class='text-xs text-gray-500 uppercase font-semibold'>Custo Base da Ficha</span>
                                    <div class='text-xl font-bold text-gray-900 dark:text-white mt-1'>R$ " . number_format($doughCost, 2, ',', '.') . "</div>
                                    <span class='text-[11px] text-gray-400'>Massa, óleo e preparos fixos</span>
                                </div>
                                <div class='p-3 bg-primary-50 dark:bg-primary-950/40 rounded-xl border border-primary-200 dark:border-primary-800'>
                                    <span class='text-xs text-primary-700 dark:text-primary-300 uppercase font-semibold'>Custo Recheios (Ponderado)</span>
                                    <div class='text-xl font-bold text-primary-600 dark:text-primary-400 mt-1'>R$ " . number_format($predictiveCost, 2, ',', '.') . "</div>
                                    <span class='text-[11px] text-primary-500'>Média ponderada com rateio</span>
                                </div>
                                <div class='p-3 bg-emerald-50 dark:bg-emerald-950/40 rounded-xl border border-emerald-200 dark:border-emerald-800'>
                                    <span class='text-xs text-emerald-700 dark:text-emerald-300 uppercase font-semibold'>CMV Real Efetivo</span>
                                    <div class='text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1'>R$ " . number_format($totalRealCost, 2, ',', '.') . "</div>
                                    <span class='text-[11px] text-emerald-600 font-medium'>Margem Bruta: " . number_format($realMarginPct, 1, ',', '.') . "%</span>
                                </div>
                                <div class='p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-800'>
                                    <span class='text-xs text-amber-700 dark:text-amber-300 uppercase font-semibold'>Pior Cenário (Mais Caros)</span>
                                    <div class='text-xl font-bold text-amber-600 dark:text-amber-400 mt-1'>R$ " . number_format($worstCaseTotalCost, 2, ',', '.') . "</div>
                                    <span class='text-[11px] text-amber-600 font-medium'>Margem Mínima: " . number_format($worstCaseMarginPct, 1, ',', '.') . "%</span>
                                </div>
                            </div>
                        ";

                        foreach ($groups as $group) {
                            $typeEnum = $group->type instanceof ModifierType ? $group->type : ModifierType::tryFrom(($group->type instanceof \BackedEnum ? $group->type->value : (string)$group->type));
                            $groupTotalWeight = $group->options->sum('historical_weight') ?: $group->options->count();
                            $isPropGroup = ($group->portion_distribution_mode ?? 'additive') === 'proportional_split';

                            $badgeClass = match($typeEnum) {
                                ModifierType::OPTION      => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
                                ModifierType::ADD_ON      => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                                ModifierType::OBSERVATION => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                                default                   => 'bg-gray-100 text-gray-800',
                            };

                            $distBadge = $isPropGroup
                                ? "<span class='px-2 py-0.5 text-[10px] rounded font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300'>Rateio Proporcional</span>"
                                : "<span class='px-2 py-0.5 text-[10px] rounded font-bold bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'>Aditivo</span>";

                            $label = $typeEnum ? $typeEnum->getLabel() : 'Modificador';

                            $html .= "
                            <div class='mt-4 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden'>
                                <div class='px-4 py-2.5 bg-gray-100/70 dark:bg-gray-800 flex justify-between items-center'>
                                    <div class='font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2'>
                                        <span>{$group->name}</span>
                                        <span class='px-2 py-0.5 text-[10px] rounded font-bold {$badgeClass}'>{$label}</span>
                                        {$distBadge}
                                    </div>
                                    <span class='text-xs text-gray-500'>{$group->options->count()} opções</span>
                                </div>
                                <table class='w-full text-left text-xs divide-y divide-gray-200 dark:divide-gray-800'>
                                    <thead class='bg-gray-50 dark:bg-gray-900 text-gray-500 uppercase'>
                                        <tr>
                                            <th class='px-3 py-2'>Opção</th>
                                            <th class='px-3 py-2'>Regra de Dose</th>
                                            <th class='px-3 py-2'>Comportamento no Estoque</th>
                                            <th class='px-3 py-2'>Custo Cheio</th>
                                            <th class='px-3 py-2 text-center'>Demanda (30d)</th>
                                            <th class='px-3 py-2 text-right'>" . ($typeEnum === ModifierType::OBSERVATION ? "Taxa de Omissão" : "Taxa de Escolha") . "</th>
                                        </tr>
                                    </thead>
                                    <tbody class='divide-y divide-gray-100 dark:divide-gray-800/50'>
                            ";

                            foreach ($group->options as $opt) {
                                $weight = (float) ($opt->historical_weight ?: 0);
                                $rate = $groupTotalWeight > 0 ? ($weight / $groupTotalWeight) * 100 : 0;
                                $isFixed = ($opt->portion_behavior ?? 'proportional') === 'fixed';

                                $doseBadge = $isFixed
                                    ? "<span class='px-2 py-0.5 text-[10px] rounded font-semibold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300'>Dose Fixa (100%)</span>"
                                    : "<span class='px-2 py-0.5 text-[10px] rounded font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'>Rateio Volume</span>";

                                $targetInsumo = $opt->linkedProduct ?? $opt->removesIngredient;
                                $insumoLabel = match (true) {
                                    $opt->removes_ingredient_id && $targetInsumo !== null => "🚫 Poupa: {$targetInsumo->name}",
                                    $opt->linked_product_id && $targetInsumo !== null     => "➕ Ref.: {$opt->linked_qty} {$targetInsumo->unit_of_measure} ({$targetInsumo->name})",
                                    default => "<span class='text-gray-400'>Apenas Informativo</span>",
                                };

                                $portionCost = 0;
                                if ($targetInsumo) {
                                    $unitCost = ProductCostingService::calculateBaseCost($targetInsumo);
                                    $portionCost = $unitCost * (float) ($opt->linked_qty ?? 1);
                                }

                                $html .= "
                                        <tr class='hover:bg-gray-50/50 dark:hover:bg-gray-800/30'>
                                            <td class='px-3 py-2 font-semibold text-gray-900 dark:text-white'>
                                                {$opt->name}
                                                " . ((float) $opt->price > 0 ? "<span class='ml-1.5 px-1.5 py-0.5 text-[10px] rounded font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'>+R$ " . number_format((float) $opt->price, 2, ',', '.') . "</span>" : "") . "
                                            </td>
                                            <td class='px-3 py-2'>{$doseBadge}</td>
                                            <td class='px-3 py-2 font-medium'>{$insumoLabel}</td>
                                            <td class='px-3 py-2 font-mono text-gray-700 dark:text-gray-300'>R$ " . number_format($portionCost, 4, ',', '.') . "</td>
                                            <td class='px-3 py-2 text-center font-bold text-gray-600 dark:text-gray-400'>{$weight}x</td>
                                            <td class='px-3 py-2 text-right font-black " . ($rate > 35 ? "text-primary-600" : "text-gray-500") . "'>" . number_format($rate, 1, ',', '.') . "%</td>
                                        </tr>
                                ";
                            }

                            $html .= "
                                    </tbody>
                                </table>
                            </div>
                            ";
                        }

                        $html .= "</div>";

                        return new HtmlString($html);
                    }),
            ])
            ->columnSpanFull();
    }
}