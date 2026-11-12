<?php

namespace App\Filament\Resources\Products\Actions;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Catalog\ProductCostingService;
use App\Services\Catalog\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SmartStatusAction
{
    public static function make(string $name = 'manage_status'): Action
    {
        return Action::make($name)
            ->label(fn (Product $record) => self::getActionLabel($record))
            ->icon(fn (Product $record) => self::getActionIcon($record))
            ->color(fn (Product $record) => self::getActionColor($record))
            ->tooltip(fn (Product $record) => self::getActionLabel($record))
            ->modalHeading(fn (Product $record) => "Gerenciar Disponibilidade: {$record->name}")
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Confirmar Alteração')
            ->form(fn (Product $record) => self::buildForm($record))
            ->action(fn (Product $record, array $data, $livewire) => self::execute($record, $data, $livewire));
    }

    private static function getStatusValue(Product $record): string
    {
        return $record->status instanceof \BackedEnum
            ? (string) $record->status->value
            : (string) ($record->status ?? ProductStatus::AVAILABLE->value);
    }

    private static function getActionLabel(Product $record): string
    {
        return match (self::getStatusValue($record)) {
            ProductStatus::ARCHIVED->value => 'Desarquivar / Reativar',
            ProductStatus::PAUSED->value   => 'Gerenciar Pausa / Arquivar',
            default                        => 'Pausar / Arquivar',
        };
    }

    private static function getActionIcon(Product $record): string
    {
        return match (self::getStatusValue($record)) {
            ProductStatus::ARCHIVED->value => 'heroicon-o-arrow-uturn-up',
            ProductStatus::PAUSED->value   => 'heroicon-o-play-circle',
            default                        => 'heroicon-o-archive-box-x-mark',
        };
    }

    private static function getActionColor(Product $record): string
    {
        return match (self::getStatusValue($record)) {
            ProductStatus::ARCHIVED->value => 'success',
            ProductStatus::PAUSED->value   => 'warning',
            default                        => 'danger',
        };
    }

    private static function buildForm(Product $record): array
    {
        $currentStatus = self::getStatusValue($record);
        $deps = self::getDependencies($record->id);
        $totalDeps = count($deps['recipes']) + count($deps['modifiers']);
        $hasStock = (float) $record->current_stock > 0;

        if ($currentStatus === ProductStatus::ARCHIVED->value) {
            return [
                Radio::make('target_status')
                    ->label('Novo Estado do Item')
                    ->options([ProductStatus::AVAILABLE->value => 'Reativar Item (Tornar Disponível)'])
                    ->default(ProductStatus::AVAILABLE->value)
                    ->required(),
                Placeholder::make('unarchive_notice')
                    ->hiddenLabel()
                    ->content(new HtmlString("
                        <div class='p-3 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 text-xs'>
                            O item voltará a ficar <strong>Disponível</strong>. Linhas de fichas técnicas que foram suspensas precisam ser reativadas manualmente nas respectivas receitas.
                        </div>
                    ")),
            ];
        }

        $options = match ($currentStatus) {
            ProductStatus::PAUSED->value => [
                ProductStatus::AVAILABLE->value => 'Retomar Operação (Tornar Disponível)',
                ProductStatus::ARCHIVED->value  => 'Arquivar Definitivamente (Inativar Item e Receitas)',
            ],
            default => [
                ProductStatus::PAUSED->value   => 'Pausar Temporariamente (Lote, Validade ou Falta Pontual)',
                ProductStatus::ARCHIVED->value => 'Arquivar Definitivamente (Inativar Item e Receitas)',
            ],
        };

        $defaultChoice = $currentStatus === ProductStatus::PAUSED->value ? ProductStatus::AVAILABLE->value : ProductStatus::ARCHIVED->value;

        $schema = [
            Radio::make('target_status')
                ->label('Novo Estado do Item')
                ->options($options)
                ->default($defaultChoice)
                ->live()
                ->required(),
        ];

        // Se houver saldo físico E for Pausar/Arquivar, mostra as opções de trato do estoque
        if ($hasStock) {
            $stockFmt = number_format((float) $record->current_stock, 3, ',', '.');
            $uom = strtoupper($record->unit_of_measure ?? '');

            $schema[] = Placeholder::make('stock_warning')
                ->hiddenLabel()
                ->visible(fn (Get $get) => in_array($get('target_status'), [ProductStatus::PAUSED->value, ProductStatus::ARCHIVED->value], true))
                ->content(new HtmlString("
                    <div class='p-3 rounded-t-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-xs border-b-0'>
                        <strong>Saldo Físico Detectado:</strong> Ainda constam <strong>{$stockFmt} {$uom}</strong> deste item em estoque. O que deseja fazer?
                    </div>
                "));

            $schema[] = Radio::make('stock_action')
                ->hiddenLabel()
                ->options(function (Get $get) {
                    $base = [
                        'keep' => 'Manter o saldo no estoque (Não deduzir do Kardex)',
                        'zero' => 'Eliminar saldo fantasma (Registrar baixa de ajuste para 0)',
                    ];
                    if ($get('replacement_id') && $get('target_status') === ProductStatus::ARCHIVED->value) {
                        $base['transfer'] = 'Transferir esse saldo (Kardex) para o novo insumo substituto selecionado';
                    }
                    return $base;
                })
                ->default('keep')
                ->visible(fn (Get $get) => in_array($get('target_status'), [ProductStatus::PAUSED->value, ProductStatus::ARCHIVED->value], true))
                ->extraAttributes(['class' => 'p-3 bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-800 rounded-b-lg mt-0']);
        }

        $impactHtml = '';
        if ($totalDeps === 0) {
            $impactHtml = "<p class='text-xs text-gray-500'>Nenhuma ficha técnica ou modificador ativo depende deste item no momento.</p>";
        } else {
            $impactHtml .= "<div class='p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 text-xs space-y-2'>";
            $impactHtml .= "<div><strong>Impacto na Cadeia de Produção ({$totalDeps} vínculos):</strong><br>Ao arquivar sem substituto, este item será <strong>inativado automaticamente</strong> nas receitas abaixo:</div>";

            if (count($deps['recipes']) > 0) {
                $impactHtml .= "<div><span class='font-bold uppercase text-[10px]'>Fichas Técnicas:</span><ul class='list-disc pl-4 mt-0.5'>";
                foreach (array_slice($deps['recipes'], 0, 4) as $r) $impactHtml .= "<li>{$r}</li>";
                if (count($deps['recipes']) > 4) $impactHtml .= "<li><em>...e mais " . (count($deps['recipes']) - 4) . " receita(s)</em></li>";
                $impactHtml .= "</ul></div>";
            }

            if (count($deps['modifiers']) > 0) {
                $impactHtml .= "<div><span class='font-bold uppercase text-[10px]'>Modificadores / Recheios:</span><ul class='list-disc pl-4 mt-0.5'>";
                foreach (array_slice($deps['modifiers'], 0, 4) as $m) $impactHtml .= "<li>{$m}</li>";
                if (count($deps['modifiers']) > 4) $impactHtml .= "<li><em>...e mais " . (count($deps['modifiers']) - 4) . " opção(ões)</em></li>";
                $impactHtml .= "</ul></div>";
            }
            $impactHtml .= "</div>";
        }

        $schema[] = Placeholder::make('impact_warning')
            ->hiddenLabel()
            ->visible(fn (Get $get) => $get('target_status') === ProductStatus::ARCHIVED->value)
            ->content(new HtmlString($impactHtml));

        if ($totalDeps > 0) {
            $typeVal = $record->type instanceof \BackedEnum ? (string) $record->type->value : (string) $record->type;

            $schema[] = Select::make('replacement_id')
                ->label('Substituir nas receitas e modificadores por (Opcional)')
                ->placeholder('Não substituir — apenas inativar nas receitas')
                ->helperText('Exibe apenas itens disponíveis com a mesma unidade de medida (' . strtoupper($record->unit_of_measure ?? '') . ').')
                ->visible(fn (Get $get) => $get('target_status') === ProductStatus::ARCHIVED->value)
                ->options(function () use ($record, $typeVal) {
                    return Product::query()
                        ->where('id', '!=', $record->id)
                        ->where('type', $typeVal)
                        ->where('unit_of_measure', $record->unit_of_measure)
                        ->where('status', ProductStatus::AVAILABLE->value)
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->live()
                ->searchable()
                ->preload();
        }

        return $schema;
    }

    private static function execute(Product $record, array $data, $livewire): void
    {
        $targetStatus  = $data['target_status'] ?? ProductStatus::ARCHIVED->value;
        $replacementId = $data['replacement_id'] ?? null;
        $stockAction   = $data['stock_action'] ?? 'keep';
        $inventoryService = app(InventoryService::class);
        $userId = auth()->id();

        DB::beginTransaction();
        try {
            // 1. Trato de Estoque Residual (antes de arquivar)
            if ($record->current_stock > 0 && in_array($targetStatus, [ProductStatus::PAUSED->value, ProductStatus::ARCHIVED->value])) {
                $qtyToMove = (float) $record->current_stock;
                
                if ($stockAction === 'zero') {
                    $reason = $targetStatus === ProductStatus::ARCHIVED->value ? 'Baixa por Arquivamento (Fantasma)' : 'Baixa por Pausa/Avaria';
                    $inventoryService->adjustStock($record, 0, $reason, $userId);
                } 
                elseif ($stockAction === 'transfer' && $replacementId) {
                    $repProduct = Product::find($replacementId);
                    if ($repProduct) {
                        // Zera o atual
                        $inventoryService->adjustStock($record, 0, "Transferência para Substituto #{$replacementId}", $userId);
                        // Adiciona no substituto (como ajuste para não simular compra)
                        $newStock = (float) $repProduct->current_stock + $qtyToMove;
                        $inventoryService->adjustStock($repProduct, $newStock, "Transferência do Insumo Arquivado #{$record->id}", $userId);
                    }
                }
            }

            // 2. Substituição em Cadeia
            if ($targetStatus === ProductStatus::ARCHIVED->value) {
                if ($replacementId) {
                    $affectedSheets = DB::table('technical_sheets')->where('ingredient_product_id', $record->id)->get();
                    foreach ($affectedSheets as $sheet) {
                        if (DB::table('technical_sheets')->where('parent_product_id', $sheet->parent_product_id)->where('ingredient_product_id', $replacementId)->exists()) {
                            DB::table('technical_sheets')->where('id', $sheet->id)->delete();
                        } else {
                            DB::table('technical_sheets')->where('id', $sheet->id)->update(['ingredient_product_id' => $replacementId, 'updated_at' => now()]);
                        }
                    }
                    DB::table('modifier_options')->where('linked_product_id', $record->id)->update(['linked_product_id' => $replacementId, 'updated_at' => now()]);
                    DB::table('modifier_options')->where('removes_ingredient_id', $record->id)->update(['removes_ingredient_id' => $replacementId, 'updated_at' => now()]);
                } else {
                    DB::table('technical_sheets')->where('ingredient_product_id', $record->id)->update(['is_active' => false, 'updated_at' => now()]);
                    DB::table('modifier_options')->where('linked_product_id', $record->id)->update(['is_active' => false, 'updated_at' => now()]);
                }
            }

            // 3. Atualiza o Status Master
            $record->updateQuietly([
                'status' => $targetStatus,
            ]);

            ProductCostingService::clearCache();
            DB::commit();

            if ($targetStatus === ProductStatus::ARCHIVED->value && $replacementId) {
                $msg = $stockAction === 'transfer' ? ' e saldo transferido' : '';
                Notification::make()->success()->title("Arquivado e Substituído")->body("Receitas migradadas{$msg}.")->send();
            } elseif ($targetStatus === ProductStatus::ARCHIVED->value) {
                $msg = $stockAction === 'zero' ? ' (Estoque zerado)' : '';
                Notification::make()->warning()->title("Arquivado{$msg}")->body('Receitas suspensas.')->send();
            } elseif ($targetStatus === ProductStatus::PAUSED->value) {
                $msg = $stockAction === 'zero' ? ' (Estoque zerado)' : '';
                Notification::make()->warning()->title("Pausado{$msg}")->body('Disponibilidade temporariamente suspensa.')->send();
            } else {
                Notification::make()->success()->title('Reativado')->body('Item disponível novamente.')->send();
            }

            if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                $livewire->dispatch('update-margin-widget');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Notification::make()->danger()->title('Erro')->body($e->getMessage())->send();
        }
    }

    private static function getDependencies(int $productId): array
    {
        $recipes = DB::table('technical_sheets')
            ->join('products as parent', 'parent.id', '=', 'technical_sheets.parent_product_id')
            ->where('technical_sheets.ingredient_product_id', $productId)
            ->whereNull('parent.deleted_at')
            ->pluck('parent.name')->toArray();
        $adds = DB::table('modifier_options')->where('linked_product_id', $productId)->pluck('name')->toArray();
        $rems = DB::table('modifier_options')->where('removes_ingredient_id', $productId)->pluck('name')->toArray();
        return [
            'recipes'   => array_unique($recipes),
            'modifiers' => array_unique(array_merge($adds, $rems)),
        ];
    }
}