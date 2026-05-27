<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class SmartMergeAction extends Action
{
    // 🚀 Agora a inteligência vai filtrar pelo "tipo da CATEGORIA" e não do produto!
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Limpar Base')
            ->icon('heroicon-o-sparkles')
            ->color('warning')
            ->badge(function () {
                // 🚀 Conta APENAS se a categoria do produto bater com a macro-área atual
                $count = Product::whereHas('category', fn($q) => $q->where('type', $this->categoryType))
                    ->select('name')
                    ->groupBy('name')
                    ->havingRaw('COUNT(id) > 1')
                    ->get()
                    ->count();

                return $count > 0 ? $count : null;
            })
            ->badgeColor('danger')
            ->modalHeading(fn () => match($this->categoryType) {
                'internal' => 'Inteligência de Mesclagem de Insumos',
                'supply' => 'Inteligência de Mesclagem de Materiais',
                default => 'Inteligência de Mesclagem de Produtos',
            })
            ->modalDescription('O sistema encontrou registros com o mesmo nome e avaliou qual tem o histórico mais rico para ser mantido.')
            ->modalSubmitActionLabel('Mesclar e Avançar')
            ->modalWidth('3xl')
            ->form(function () {
                $duplicateName = Product::whereHas('category', fn($q) => $q->where('type', $this->categoryType))
                    ->select('name')
                    ->groupBy('name')
                    ->havingRaw('COUNT(id) > 1')
                    ->value('name');

                $itemLabel = match($this->categoryType) {
                    'internal' => 'Insumo',
                    'supply' => 'Material de Consumo',
                    default => 'Produto'
                };

                if (!$duplicateName) {
                    return [
                        Placeholder::make('empty')
                            ->hiddenLabel()
                            ->content(new HtmlString("<div class='text-center text-gray-500 py-4'>🎉 Parabéns! Nenhum(a) {$itemLabel} duplicado(a) foi encontrado(a) na base.</div>")),
                    ];
                }

                $duplicates = Product::where('name', $duplicateName)
                    ->whereHas('category', fn($q) => $q->where('type', $this->categoryType))
                    ->get();

                foreach ($duplicates as $dup) {
                    $score = 0;

                    if ($dup->status === 'active') $score += 10;
                    if ($dup->image_path) $score += 10;
                    if ((float) $dup->current_stock > 0) $score += 15;
                    if ((float) $dup->base_cost > 0) $score += 10;

                    try { $score += $dup->costHistories()->count() * 20; } catch (\Exception $e) {}
                    try { $score += $dup->technicalSheets()->count() * 20; } catch (\Exception $e) {}
                    try { $score += $dup->modifiers()->count() * 15; } catch (\Exception $e) {}

                    $dup->smart_score = $score;
                }

                $duplicates = $duplicates->sortByDesc('smart_score')->values();
                $bestCandidate = $duplicates->first();

                $options = [];
                $descriptions = [];

                foreach ($duplicates as $dup) {
                    $options[$dup->id] = "ID #{$dup->id} - {$dup->name}";

                    $isRecommended = ($dup->id === $bestCandidate->id)
                        ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] uppercase tracking-wider font-bold bg-success-100 text-success-800 dark:bg-success-800 dark:text-success-100">Recomendado pelo Sistema 💡</span>'
                        : '';

                    $price = number_format((float) ($dup->dine_in_price ?? $dup->base_cost), 2, ',', '.');
                    $stock = (float) $dup->current_stock;
                    $status = $dup->status === 'active' ? 'Ativo' : 'Inativo';

                    $descriptions[$dup->id] = new HtmlString("
                        <div class='mt-1 text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border border-gray-100 dark:border-gray-700'>
                            <div class='flex gap-4 mb-2'>
                                <span><strong>Valor:</strong> R$ {$price}</span>
                                <span><strong>Estoque:</strong> {$stock}</span>
                                <span><strong>Status:</strong> {$status}</span>
                            </div>
                            <span class='text-xs text-gray-400'>Pontuação de Relevância: <b>{$dup->smart_score} pontos</b></span>
                            {$isRecommended}
                        </div>
                    ");
                }

                return [
                    Placeholder::make('target')
                        ->hiddenLabel()
                        ->content(new HtmlString("
                            <div class='text-xl font-bold text-primary-600 dark:text-primary-400 mb-2'>{$itemLabel} Duplicado: {$duplicateName}</div>
                            <p class='text-sm text-gray-500'>Selecione qual registro é o oficial. Os demais serão apagados e seus históricos serão transferidos.</p>
                        ")),

                    Radio::make('master_id')
                        ->hiddenLabel()
                        ->options($options)
                        ->descriptions($descriptions)
                        ->default($bestCandidate->id)
                        ->required(),
                ];
            })
            ->action(function (array $data, $livewire, Action $action) {
                if (!isset($data['master_id'])) return;

                $masterId = $data['master_id'];
                $master = Product::find($masterId);

                if (!$master) return;

                $losers = Product::where('name', $master->name)
                    ->where('id', '!=', $masterId)
                    ->whereHas('category', fn($q) => $q->where('type', $this->categoryType))
                    ->get();

                foreach ($losers as $loser) {
                    DB::table('technical_sheets')->where('parent_product_id', $loser->id)->update(['parent_product_id' => $masterId]);
                    DB::table('technical_sheets')->where('ingredient_product_id', $loser->id)->update(['ingredient_product_id' => $masterId]);

                    DB::table('product_cost_histories')->where('product_id', $loser->id)->update(['product_id' => $masterId]);
                    DB::table('product_modifier')->where('product_id', $loser->id)->update(['product_id' => $masterId]);
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
                    ->title("Mesclado com sucesso!")
                    ->success()
                    ->send();
            })
            ->after(function ($livewire) {
                $hasMore = Product::whereHas('category', fn($q) => $q->where('type', $this->categoryType))
                    ->select('name')
                    ->groupBy('name')
                    ->havingRaw('COUNT(id) > 1')
                    ->exists();

                if ($hasMore) {
                    $livewire->mountAction('smart_merge');
                } else {
                    $livewire->dispatch('$refresh');
                }
            });
    }
}
