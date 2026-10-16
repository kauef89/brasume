<?php

namespace App\Filament\Resources\InventoryLosses\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Product;
use App\Models\InventoryLoss;

class InventoryLossForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make('Registro de Perda / Descarte')
                ->description('Informe o produto, o tipo de ocorrência e a quantidade perdida.')
                ->schema([
                    Grid::make(3)->schema([
                        Components\Select::make('product_id')
                            ->label('Produto / Insumo')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $p = Product::find($state);
                                    if ($p) {
                                        $cost = (float) ($p->base_cost ?? 0);
                                        $set('unit_cost', $cost);
                                    }
                                }
                            })
                            ->columnSpan(2),

                        Components\Select::make('loss_type')
                            ->label('Origem / Classificação da Perda')
                            ->options(InventoryLoss::TYPES)
                            ->default(InventoryLoss::TYPE_OPERATIONAL)
                            ->required()
                            ->columnSpan(1),
                    ]),

                    Grid::make(3)->schema([
                        Components\TextInput::make('quantity')
                            ->label('Quantidade Perdida')
                            ->numeric()
                            ->step('0.0001')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $qty = (float) $get('quantity');
                                $unitCost = (float) $get('unit_cost');
                                $set('total_cost', round($qty * $unitCost, 2));
                            })
                            ->columnSpan(1),

                        Components\TextInput::make('unit_cost')
                            ->label('Custo Unitário')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $qty = (float) $get('quantity');
                                $unitCost = (float) $get('unit_cost');
                                $set('total_cost', round($qty * $unitCost, 2));
                            })
                            ->columnSpan(1),

                        Components\TextInput::make('total_cost')
                            ->label('Prejuízo Total')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly()
                            ->columnSpan(1),
                    ]),

                    Grid::make(2)->schema([
                        Components\Select::make('storage_location_id')
                            ->label('Local Físico (Onde ocorreu / Armário)')
                            ->relationship('storageLocation', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Não especificado')
                            ->columnSpan(1),

                        Components\TextInput::make('reason')
                            ->label('Motivo / Resumo')
                            ->placeholder('Ex: Carne queimada, quebra de garrafa, erro de garçom')
                            ->required()
                            ->columnSpan(1),
                    ]),

                    Components\Textarea::make('notes')
                        ->label('Observações Adicionais')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
