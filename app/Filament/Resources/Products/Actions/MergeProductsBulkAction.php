<?php

namespace App\Filament\Resources\Products\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Filament\Tables\Contracts\HasTable;

// 🚀 IMPORTS PRINCIPAIS
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\LegacyProduct;
use App\Models\ProductCostHistory;
use App\Models\InventoryLoss;
use App\Models\ProductionOrder;
use App\Models\ShoppingListItem;

class MergeProductsBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'merge_products';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Mesclar Selecionados')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Mesclar Produtos Duplicados')
            ->modalDescription('O histórico de Vendas, Compras, Fichas Técnicas, Produção e Perdas das cópias selecionadas será transferido para o Produto Oficial escolhido abaixo. As cópias serão apagadas em seguida.')
            ->modalSubmitActionLabel('Mesclar e Unificar')
            ->form([
                Placeholder::make('info')
                    ->hiddenLabel()
                    ->content(function (HasTable $livewire) {
                        $count = $livewire->getSelectedTableRecords()->count();
                        return new HtmlString('<div class="text-warning-600 font-medium mb-4">Você selecionou <strong>' . $count . '</strong> produtos para mesclar.</div>');
                    }),

                Select::make('master_product_id')
                    ->label('Qual é o Produto Oficial? (Este será mantido)')
                    ->options(function (HasTable $livewire) {
                        return $livewire->getSelectedTableRecords()->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->helperText('O sistema vai varrer todo o ERP buscando referências às cópias e apontá-las para este produto.'),
            ])
            ->action(function (Collection $records, array $data) {
                $masterId = (int) $data['master_product_id'];
                $masterProduct = $records->firstWhere('id', $masterId);

                // Filtra as cópias: tudo que não for o ID oficial
                $duplicates = $records->where('id', '!==', $masterId);

                if ($duplicates->isEmpty()) {
                    Notification::make()->warning()->title('Ação cancelada')->body('Selecione pelo menos dois produtos para mesclar.')->send();
                    return;
                }

                DB::beginTransaction();

                try {
                    $mergedCount = 0;

                    foreach ($duplicates as $duplicate) {
                        $dupId = $duplicate->id;

                        // ==========================================
                        // 1. VENDAS E MAPEAMENTO LEGADO
                        // ==========================================
                        OrderItem::where('product_id', $dupId)->update([
                            'product_id' => $masterId,
                            'product_name' => $masterProduct->name,
                        ]);
                        LegacyProduct::where('current_product_id', $dupId)->update([
                            'current_product_id' => $masterId
                        ]);

                        // ==========================================
                        // 2. CUSTOS E COMPRAS (SUPPLY CHAIN)
                        // ==========================================
                        ProductCostHistory::where('product_id', $dupId)->update(['product_id' => $masterId]);
                        ShoppingListItem::where('product_id', $dupId)->update(['product_id' => $masterId]);
                        DB::table('cost_histories')->where('product_id', $dupId)->update(['product_id' => $masterId]);

                        // ==========================================
                        // 3. ESTOQUE E PERDAS
                        // ==========================================
                        InventoryLoss::where('product_id', $dupId)->update(['product_id' => $masterId]);

                        // Soma os estoques se a cópia também controlava
                        if ($duplicate->track_inventory && $duplicate->current_stock > 0) {
                            $masterProduct->increment('current_stock', $duplicate->current_stock);
                        }

                        // ==========================================
                        // 4. FICHAS TÉCNICAS E PRODUÇÃO (MRP)
                        // ==========================================
                        ProductionOrder::where('product_id', $dupId)->update(['product_id' => $masterId]);

                        // 🚀 Os nomes exatos das colunas descobertas no seu mapa!
                        DB::table('technical_sheets')->where('parent_product_id', $dupId)->update(['parent_product_id' => $masterId]);
                        DB::table('technical_sheets')->where('ingredient_product_id', $dupId)->update(['ingredient_product_id' => $masterId]);

                        // ==========================================
                        // 5. COMBOS E MODIFICADORES
                        // ==========================================
                        DB::table('combo_items')->where('product_id', $dupId)->update(['product_id' => $masterId]);
                        DB::table('combo_groups')->where('product_id', $dupId)->update(['product_id' => $masterId]);

                        // 🚀 Vínculos intrínsecos de modificadores extraídos do mapa
                        DB::table('modifier_options')->where('linked_product_id', $dupId)->update(['linked_product_id' => $masterId]);
                        DB::table('modifier_items')->where('ingredient_product_id', $dupId)->update(['ingredient_product_id' => $masterId]);
                        DB::table('modifier_group_product')->where('product_id', $dupId)->update(['product_id' => $masterId]);
                        DB::table('product_modifier_product')->where('product_id', $dupId)->update(['product_id' => $masterId]);

                        // ==========================================
                        // 6. APAGA A CÓPIA
                        // ==========================================
                        $duplicate->delete();
                        $mergedCount++;
                    }

                    DB::commit();

                    // Dispara atualização para os Caixas e Totens
                    $tenantId = $masterProduct->tenant_id ?? tenant('id') ?? 'central';
                    try { \Illuminate\Support\Facades\Cache::tags(['catalog'])->flush(); } catch (\Exception $e) {}
                    try { broadcast(new \App\Events\CatalogUpdated($tenantId)); } catch (\Exception $e) {}

                    Notification::make()
                        ->title('Mesclagem Profunda Concluída!')
                        ->body("{$mergedCount} cópia(s) e todas as suas dependências operacionais foram unificadas em '{$masterProduct->name}'.")
                        ->success()
                        ->send();

                } catch (\Exception $e) {
                    DB::rollBack();
                    Notification::make()
                        ->title('Erro ao mesclar')
                        ->body('Houve um erro com o banco de dados: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}
