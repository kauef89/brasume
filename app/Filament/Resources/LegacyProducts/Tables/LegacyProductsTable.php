<?php

namespace App\Filament\Resources\LegacyProducts\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

// Models
use App\Models\Product;
use App\Models\Category;
use App\Models\LegacyProduct;

// Actions & Schemas
use App\Filament\Resources\LegacyProducts\Actions\BatchResolveProductsAction;
use App\Filament\Resources\LegacyProducts\Schemas\LegacyProductForm;

class LegacyProductsTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("legacy_code")->label("Código")->searchable(),

                TextColumn::make("name")
                    ->label("Nome Legado")
                    ->searchable()
                    ->limit(30),

                TextColumn::make("mapped_status")
                    ->label("Status de Mapeamento")
                    ->badge()
                    ->color(
                        fn($record) => match (true) {
                            $record->is_ignored => "danger",
                            $record->current_product_id !== null => "success",
                            default => "warning",
                        },
                    )
                    ->state(
                        fn($record) => match (true) {
                            $record->is_ignored => "Ignorado",
                            $record->current_product_id !== null => $record->currentProduct?->name,
                            default => "Pendente",
                        },
                    ),
            ])
            // 🚀 ORDENAÇÃO DEFAULT: Joga os itens pendentes para o topo!
            ->defaultSort('current_product_id', 'asc')
            ->filters([
                SelectFilter::make("status")
                    ->label("Situação")
                    ->options([
                        "pending" => "Pendentes",
                        "mapped" => "Mapeados",
                        "ignored" => "Ignorados",
                    ])
                    ->query(
                        fn($query, array $data) => match ($data["value"]) {
                            "pending" => $query->whereNull("current_product_id")->where("is_ignored", false),
                            "mapped" => $query->whereNotNull("current_product_id"),
                            "ignored" => $query->where("is_ignored", true),
                            default => $query,
                        },
                    ),
            ])
            ->headerActions([
                BatchResolveProductsAction::make(),
            ])
            ->actions([
                ActionGroup::make([

                    // 1. Associar Produto
                    EditAction::make()
                        ->label('Associar Produto (V3)')
                        ->icon('heroicon-o-link')
                        ->color('primary')
                        ->after(
                            fn($record) => LegacyProductForm::goToNextRecord($record),
                        ),

                    // 2. Migrar como Descontinuado
                    Action::make('migrate_individual_discontinued')
                        ->label('Migrar como Descontinuado (BI)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('warning')
                        ->form([
                            Select::make('category_id')
                                ->label('Categoria de Agrupamento')
                                ->options(Category::where('type', 'external')->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (LegacyProduct $record, array $data) {
                            $ghostProduct = Product::create([
                                'name' => $record->name . ' (Descontinuado)',
                                'category_id' => $data['category_id'],
                                'status' => 'archived',
                                'show_on_menu' => false,
                                'show_on_delivery' => false,
                                'type' => 'resale',
                                'unit_measure' => 'un',
                            ]);

                            $record->update([
                                'current_product_id' => $ghostProduct->id,
                                'is_ignored' => false,
                            ]);

                            Notification::make()
                                ->title("Item descontinuado criado e vinculado!")
                                ->success()
                                ->send();
                        }),

                    // 3. Ignorar
                    Action::make('ignore_individual')
                        ->label('Ignorar (Excluir do BI)')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Ignorar Produto Legado')
                        ->modalDescription('Tem certeza que deseja ignorar este item? Ele não fará parte do faturamento histórico.')
                        ->action(function (LegacyProduct $record) {
                            $record->update([
                                'is_ignored' => true,
                                'current_product_id' => null,
                            ]);

                            Notification::make()
                                ->title("Item ignorado com sucesso!")
                                ->success()
                                ->send();
                        }),

                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Ações disponíveis')
            ])
            ->bulkActions([
                BulkActionGroup::make([

                    // 1. Vincular Múltiplos
                    BulkAction::make('map_to_single_product')
                        ->label('Vincular ao mesmo Produto (V3)')
                        ->icon('heroicon-o-link')
                        ->color('primary')
                        ->modalHeading('Vincular Vários Itens')
                        ->modalDescription('Escolha o produto do ERP atual (V3) que será associado a todos os registos legados selecionados.')
                        ->form([
                            Select::make('current_product_id')
                                ->label('Produto Final (V3)')
                                ->options(Product::pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $productId = $data['current_product_id'];
                            $count = 0;

                            foreach ($records as $record) {
                                $record->update([
                                    'current_product_id' => $productId,
                                    'is_ignored' => false,
                                ]);
                                $count++;
                            }

                            Notification::make()
                                ->title("{$count} itens vinculados com sucesso!")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // 2. Migrar Múltiplos como Descontinuado
                    BulkAction::make('migrate_as_discontinued')
                        ->label('Migrar como Descontinuado (BI)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Migrar para o Histórico de Faturação')
                        ->modalDescription('Estes itens serão criados no sistema atual como produtos "Fantasmas" para o BI. Indique a qual categoria este faturamento deve ser somado.')
                        ->form([
                            Select::make('category_id')
                                ->label('Categoria de Agrupamento')
                                ->options(Category::where('type', 'external')->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $categoryId = $data['category_id'];
                            $count = 0;

                            foreach ($records as $record) {
                                $ghostProduct = Product::create([
                                    'name' => $record->name . ' (Descontinuado)',
                                    'category_id' => $categoryId,
                                    'status' => 'archived',
                                    'show_on_menu' => false,
                                    'show_on_delivery' => false,
                                    'type' => 'resale',
                                    'unit_measure' => 'un',
                                ]);

                                $record->update([
                                    'current_product_id' => $ghostProduct->id,
                                    'is_ignored' => false,
                                ]);

                                $count++;
                            }

                            Notification::make()
                                ->title("{$count} itens descontinuados migrados para a V3!")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // 3. Ignorar Múltiplos
                    BulkAction::make('ignore_selected')
                        ->label('Ignorar (Excluir do BI)')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Ignorar Produtos Legados')
                        ->modalDescription('Atenção: Ao ignorar estes itens, as vendas associadas a eles NÃO farão parte do faturamento histórico.')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update([
                                    'is_ignored' => true,
                                    'current_product_id' => null,
                                ]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} itens ignorados!")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
