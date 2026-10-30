<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SmartMergeAction extends Action
{
    public string $categoryType = 'external';

    public static function getDefaultName(): ?string
    {
        return 'smart_merge';
    }

    public function categoryType(string $type): static
    {
        $this->categoryType = $type;
        return $this;
    }

    /**
     * Normaliza nomes para agrupamento por proximidade:
     * - Converte para minúsculas e remove acentos
     * - Remove ruídos como 'polpa'
     * - Unifica unidades de medida (1,5 l -> 1.5l)
     * - Remove espaços extras e caracteres especiais
     */
    public static function normalizeName(string $name): string
    {
        $str = mb_strtolower($name, 'UTF-8');
        $str = Str::ascii($str);

        // Remove termos ruído comuns em cardápios
        $str = preg_replace('/\bpolpa\b/i', '', $str);

        // Padroniza volumes e medidas (ex: '1,5 l', '1,5L', '1.5 l' -> '1.5l')
        $str = preg_replace('/(\d+)[,\.](\d+)\s*(l|ml|kg|g)\b/i', '$1.$2$3', $str);
        $str = preg_replace('/(\d+)\s*(l|ml|kg|g)\b/i', '$1$2', $str);

        // Remove pontuação e caracteres não alfanuméricos
        $str = preg_replace('/[^\w\s\.]/', ' ', $str);

        return trim(preg_replace('/\s+/', ' ', $str));
    }

    /**
     * Busca produtos da macro-área incluindo produtos sem categoria
     */
    protected function getScopedProducts(): Collection
    {
        return Product::query()
            ->where(function ($query) {
                $query->whereNull('category_id')
                    ->orWhereHas('category', fn ($q) => $q->where('type', $this->categoryType));
            })
            ->get();
    }

    /**
     * Agrupa duplicados por proximidade textual
     */
    protected function getDuplicateGroups(): Collection
    {
        return $this->getScopedProducts()
            ->groupBy(fn (Product $product) => self::normalizeName($product->name))
            ->filter(fn (Collection $group) => $group->count() > 1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Limpar Base')
            ->icon('heroicon-o-sparkles')
            ->color('warning')
            ->badge(function () {
                $count = $this->getDuplicateGroups()->count();
                return $count > 0 ? $count : null;
            })
            ->badgeColor('danger')
            ->modalHeading(fn () => match($this->categoryType) {
                'internal' => 'Inteligência de Mesclagem de Insumos',
                'supply'   => 'Inteligência de Mesclagem de Materiais',
                default    => 'Inteligência de Mesclagem de Produtos',
            })
            ->modalDescription('O sistema agrupou itens com nomes idênticos ou aproximados (ex: variações com e sem "Polpa", caixa alta/baixa ou sem categoria).')
            ->modalSubmitActionLabel('Mesclar e Avançar')
            ->modalWidth('3xl')
            ->form(function () {
                $duplicateGroups = $this->getDuplicateGroups();
                $duplicates = $duplicateGroups->first();

                $itemLabel = match($this->categoryType) {
                    'internal' => 'Insumo',
                    'supply'   => 'Material de Consumo',
                    default    => 'Produto'
                };

                if (! $duplicates) {
                    return [
                        Placeholder::make('empty')
                            ->hiddenLabel()
                            ->content(new HtmlString("<div class='text-center text-gray-500 py-4'>🎉 Parabéns! Nenhum(a) {$itemLabel} duplicado(a) foi encontrado(a) na base.</div>")),
                    ];
                }

                // Cálculo de relevância para sugerir o melhor candidato
                foreach ($duplicates as $dup) {
                    $score = 0;

                    if ($dup->status === 'active' || $dup->status === 'available') $score += 10;
                    if ($dup->image_path) $score += 10;
                    if ($dup->category_id) $score += 10; // Prefere itens já categorizados
                    if ((float) $dup->current_stock > 0) $score += 15;
                    if ((float) $dup->base_cost > 0) $score += 10;

                    try { $score += $dup->costHistories()->count() * 20; } catch (\Throwable $e) {}
                    try { $score += $dup->technicalSheets()->count() * 20; } catch (\Throwable $e) {}
                    try { $score += $dup->modifiers()->count() * 15; } catch (\Throwable $e) {}

                    $dup->smart_score = $score;
                }

                $duplicates = $duplicates->sortByDesc('smart_score')->values();
                $bestCandidate = $duplicates->first();

                $options = [];
                $descriptions = [];

                foreach ($duplicates as $dup) {
                    $options[$dup->id] = "ID #{$dup->id} - {$dup->name}";

                    $isRecommended = ($dup->id === $bestCandidate->id)
                        ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] uppercase tracking-wider font-medium bg-success-100 text-success-800 dark:bg-success-800 dark:text-success-100">Recomendado pelo Sistema 💡</span>'
                        : '';

                    $categoryLabel = $dup->category?->name ?? '⚠️ Sem Categoria';
                    $price = number_format((float) ($dup->dine_in_price ?? $dup->base_cost), 2, ',', '.');
                    $stock = (float) $dup->current_stock;
                    $status = in_array($dup->status, ['active', 'available']) ? 'Ativo' : 'Inativo';

                    $descriptions[$dup->id] = new HtmlString("
                        <div class='mt-1 text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border border-gray-100 dark:border-gray-700'>
                            <div class='grid grid-cols-2 sm:grid-cols-4 gap-2 mb-2'>
                                <span><strong>Categoria:</strong> {$categoryLabel}</span>
                                <span><strong>Valor:</strong> R$ {$price}</span>
                                <span><strong>Estoque:</strong> {$stock}</span>
                                <span><strong>Status:</strong> {$status}</span>
                            </div>
                            <span class='text-xs text-gray-400'>Pontuação de Relevância: <b>{$dup->smart_score} pontos</b></span>
                            {$isRecommended}
                        </div>
                    ");
                }

                $namesComparison = $duplicates->pluck('name')->unique()->implode(' <span class="text-amber-500 font-bold px-1">vs</span> ');

                return [
                    Hidden::make('group_ids')
                        ->default($duplicates->pluck('id')->values()->toArray()),

                    Placeholder::make('target')
                        ->hiddenLabel()
                        ->content(new HtmlString("
                            <div class='text-lg font-semibold text-primary-600 mb-1'>Conflito Identificado:</div>
                            <div class='p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-sm text-amber-900 dark:text-amber-200 mb-3'>
                                {$namesComparison}
                            </div>
                            <p class='text-sm text-gray-500'>Selecione qual registro deve ser mantido como o oficial. Os demais serão removidos e seus históricos (pedidos, fichas técnicas e custos) transferidos.</p>
                        ")),

                    Radio::make('master_id')
                        ->hiddenLabel()
                        ->options($options)
                        ->descriptions($descriptions)
                        ->default($bestCandidate->id)
                        ->required(),
                ];
            })
            ->action(function (array $data) {
                $masterId = $data['master_id'] ?? null;
                $groupIds = $data['group_ids'] ?? [];

                if (! $masterId || empty($groupIds)) {
                    return;
                }

                $master = Product::find($masterId);
                if (! $master) {
                    return;
                }

                $losers = Product::whereIn('id', $groupIds)
                    ->where('id', '!=', $masterId)
                    ->get();

                // Herança inteligente: se o mestre estiver sem categoria ou imagem, absorve do item que tiver
                if (empty($master->category_id)) {
                    $donorCategory = $losers->firstWhere('category_id', '!==', null);
                    if ($donorCategory) {
                        $master->update(['category_id' => $donorCategory->category_id]);
                    }
                }

                if (empty($master->image_path)) {
                    $donorImage = $losers->firstWhere('image_path', '!==', null);
                    if ($donorImage) {
                        $master->update(['image_path' => $donorImage->image_path]);
                    }
                }

                // Transfere vínculos relacionais
                foreach ($losers as $loser) {
                    DB::table('technical_sheets')->where('parent_product_id', $loser->id)->update(['parent_product_id' => $masterId]);
                    DB::table('technical_sheets')->where('ingredient_product_id', $loser->id)->update(['ingredient_product_id' => $masterId]);

                    DB::table('product_cost_histories')->where('product_id', $loser->id)->update(['product_id' => $masterId]);
                    DB::table('product_modifiers')->where('product_id', $loser->id)->update(['product_id' => $masterId]);
                    DB::table('modifier_options')->where('linked_product_id', $loser->id)->update(['linked_product_id' => $masterId]);
                    DB::table('combo_groups')->where('product_id', $loser->id)->update(['product_id' => $masterId]);
                    DB::table('combo_items')->where('product_id', $loser->id)->update(['product_id' => $masterId]);
                    DB::table('offers')->where('trigger_product_id', $loser->id)->update(['trigger_product_id' => $masterId]);
                    DB::table('legacy_products')->where('current_product_id', $loser->id)->update(['current_product_id' => $masterId]);

                    if (Schema::hasTable('order_items')) {
                        DB::table('order_items')->where('product_id', $loser->id)->update(['product_id' => $masterId]);
                    }

                    $loser->delete();
                }

                Notification::make()
                    ->title("Produtos mesclados com sucesso!")
                    ->success()
                    ->send();
            })
            ->after(function ($livewire) {
                if ($this->getDuplicateGroups()->isNotEmpty()) {
                    $livewire->mountAction('smart_merge');
                } else {
                    $livewire->dispatch('$refresh');
                }
            });
    }
}
