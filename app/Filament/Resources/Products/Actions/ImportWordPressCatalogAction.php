<?php

namespace App\Filament\Resources\Products\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;

class ImportWordPressCatalogAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'import_wp_catalog';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Auditoria & Importação V1')
            ->icon('heroicon-o-cloud-arrow-down')
            ->color('info')
            ->modalHeading('Revisão de Importação Inteligente')
            ->modalDescription('Encontramos itens novos e itens existentes que precisam de dados fiscais. Selecione o que deseja sincronizar.')
            ->modalSubmitActionLabel('Sincronizar Selecionados')
            ->modalWidth('4xl')
            ->form(fn () => $this->getApprovalForm())
            ->action(fn (array $data) => $this->importSelected($data));
    }

    protected function getApprovalForm(): array
    {
        try {
            $wp = DB::connection('wordpress');
            // Busca os nomes e IDs dos produtos do WP
            $wpProducts = $wp->table('posts')
                ->where('post_type', 'nativa_produto')
                ->where('post_status', 'publish')
                ->pluck('post_title', 'ID')
                ->toArray();
        } catch (\Exception $e) {
            return [
                Placeholder::make('error')
                    ->hiddenLabel()
                    ->content(new HtmlString('<div class="text-danger-600 font-medium"><strong>Erro Real:</strong> ' . $e->getMessage() . '</div>'))
            ];
        }

        // Puxa todos os produtos atuais para mapeamento de 100% de match
        $existingProducts = Product::all()->keyBy(function ($product) {
            return strtolower(trim($product->name));
        });

        $pending = [];
        $newCount = 0;
        $updateCount = 0;

        foreach ($wpProducts as $id => $title) {
            $normalizedTitle = strtolower(trim($title));

            if (!$existingProducts->has($normalizedTitle)) {
                // Cenário 1: Produto não existe (Novo)
                $pending["new_{$id}"] = "🆕 Novo: {$title}";
                $newCount++;
            } else {
                // Cenário 2: Produto existe. Vamos ver se falta NCM ou CFOP
                $existing = $existingProducts->get($normalizedTitle);

                if (empty($existing->fiscal_ncm) || empty($existing->fiscal_cfop)) {
                    $pending["update_{$id}"] = "🔄 Atualizar Fiscal: {$title}";
                    $updateCount++;
                }
            }
        }

        if (empty($pending)) {
            return [
                Placeholder::make('empty')
                    ->hiddenLabel()
                    ->content(new HtmlString('<div class="text-center text-gray-500 py-4">Tudo perfeitamente sincronizado! Não há produtos novos nem pendências fiscais da V1.</div>'))
            ];
        }

        return [
            Placeholder::make('info')
                ->hiddenLabel()
                ->content(new HtmlString("
                    <div class='flex gap-4 mb-4'>
                        <span class='badge bg-success-100 text-success-700 px-2 py-1 rounded-md text-sm font-medium'>🆕 {$newCount} Novos</span>
                        <span class='badge bg-warning-100 text-warning-700 px-2 py-1 rounded-md text-sm font-medium'>🔄 {$updateCount} Atualizações Fiscais</span>
                    </div>
                ")),

            CheckboxList::make('selected_products')
                ->hiddenLabel()
                ->options($pending)
                ->default(array_keys($pending)) // Vem tudo marcado por padrão
                ->bulkToggleable()
                ->searchable()
                ->columns(2), // 2 colunas acomodam melhor os prefixos grandes
        ];
    }

    protected function importSelected(array $data): void
    {
        $selectedKeys = $data['selected_products'] ?? [];

        if (empty($selectedKeys)) {
            return;
        }

        $wp = DB::connection('wordpress');

        $defaultCategory = Category::firstOrCreate(
            ['name' => 'Cardápio Importado V1'],
            ['type' => 'external', 'is_active' => true]
        );

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($selectedKeys as $key) {
            // Separa a ação ('new' ou 'update') do ID do WordPress
            $parts = explode('_', $key, 2);
            if (count($parts) !== 2) continue;

            $action = $parts[0];
            $wpId = $parts[1];

            $wpProd = $wp->table('posts')->where('ID', $wpId)->first();
            if (!$wpProd) continue;

            $name = trim($wpProd->post_title);

            // Puxa todos os metadados dinamicamente para este ID aprovado
            $metas = $wp->table('postmeta')
                ->where('post_id', $wpId)
                ->pluck('meta_value', 'meta_key')
                ->toArray();

            if ($action === 'new') {
                // ==========================================
                // CRIAÇÃO DE NOVO PRODUTO
                // ==========================================
                $price = isset($metas['produto_preco']) ? (float) $metas['produto_preco'] : 0.0;
                $promoPrice = isset($metas['produto_preco_promocional']) ? (float) $metas['produto_preco_promocional'] : 0.0;
                $status = isset($metas['produto_disponibilidade']) && $metas['produto_disponibilidade'] === 'disponivel' ? 'available' : 'paused';

                Product::create([
                    'name' => $name,
                    'category_id' => $defaultCategory->id,
                    'type' => 'resale',
                    'status' => $status,
                    'dine_in_price' => $price,
                    'delivery_price' => $price,
                    'promo_price' => $promoPrice,
                    'base_cost' => isset($metas['nativa_base_cost']) ? (float) $metas['nativa_base_cost'] : 0.0,
                    'show_on_menu' => isset($metas['nativa_exibir_mesa']) ? (bool) $metas['nativa_exibir_mesa'] : true,
                    'show_on_delivery' => isset($metas['nativa_exibir_delivery']) ? (bool) $metas['nativa_exibir_delivery'] : true,
                    'requires_preparation' => isset($metas['nativa_requires_preparation']) ? (bool) $metas['nativa_requires_preparation'] : true,
                    'is_age_restricted' => isset($metas['nativa_apenas_maiores']) ? (bool) $metas['nativa_apenas_maiores'] : false,
                    'loyalty_reward' => isset($metas['nativa_is_loyalty_reward']) ? (bool) $metas['nativa_is_loyalty_reward'] : false,
                    'track_inventory' => isset($metas['nativa_stock_controlled']) ? (bool) $metas['nativa_stock_controlled'] : false,
                    'current_stock' => isset($metas['nativa_estoque_qtd']) ? (float) $metas['nativa_estoque_qtd'] : 0,
                    'min_stock' => isset($metas['nativa_stock_min']) ? (float) $metas['nativa_stock_min'] : 0,
                    'unit_of_measure' => 'UN',
                    'fiscal_ncm' => $metas['nativa_prod_ncm'] ?? null,
                    'fiscal_cest' => $metas['nativa_prod_cest'] ?? null,
                    'fiscal_cfop' => $metas['nativa_prod_cfop'] ?? null,
                    'fiscal_origin' => $metas['nativa_prod_origem'] ?? null,
                    'fiscal_csosn' => $metas['nativa_prod_csosn'] ?? null,
                    'is_monofasico' => isset($metas['nativa_prod_is_monofasico']) ? (bool) $metas['nativa_prod_is_monofasico'] : false,
                ]);
                $createdCount++;

            } elseif ($action === 'update') {
                // ==========================================
                // ATUALIZAÇÃO FISCAL DE PRODUTO EXISTENTE
                // ==========================================
                $existingProduct = Product::where('name', $name)->first();

                if ($existingProduct) {
                    $updatePayload = [];

                    // Injeta a informação apenas se ela estiver vazia no sistema novo E preenchida no antigo
                    if (empty($existingProduct->fiscal_ncm) && !empty($metas['nativa_prod_ncm'])) {
                        $updatePayload['fiscal_ncm'] = $metas['nativa_prod_ncm'];
                    }
                    if (empty($existingProduct->fiscal_cest) && !empty($metas['nativa_prod_cest'])) {
                        $updatePayload['fiscal_cest'] = $metas['nativa_prod_cest'];
                    }
                    if (empty($existingProduct->fiscal_cfop) && !empty($metas['nativa_prod_cfop'])) {
                        $updatePayload['fiscal_cfop'] = $metas['nativa_prod_cfop'];
                    }
                    if (empty($existingProduct->fiscal_origin) && !empty($metas['nativa_prod_origem'])) {
                        $updatePayload['fiscal_origin'] = $metas['nativa_prod_origem'];
                    }
                    if (empty($existingProduct->fiscal_csosn) && !empty($metas['nativa_prod_csosn'])) {
                        $updatePayload['fiscal_csosn'] = $metas['nativa_prod_csosn'];
                    }

                    // O monofásico é um booleano, atualizamos se estiver ativo no WP e desligado no Novo
                    if (!$existingProduct->is_monofasico && !empty($metas['nativa_prod_is_monofasico'])) {
                        $updatePayload['is_monofasico'] = (bool) $metas['nativa_prod_is_monofasico'];
                    }

                    if (!empty($updatePayload)) {
                        $existingProduct->update($updatePayload);
                        $updatedCount++;
                    }
                }
            }
        }

        Notification::make()
            ->title("Sincronização Concluída!")
            ->body("{$createdCount} novos produtos importados e {$updatedCount} cadastros fiscais atualizados com sucesso.")
            ->success()
            ->send();
    }
}
