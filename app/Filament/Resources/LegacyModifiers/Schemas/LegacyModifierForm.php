<?php

namespace App\Filament\Resources\LegacyModifiers\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

class LegacyModifierForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make('Mapeamento de Modificador Legado')
                ->description('Associe este registro legado a uma Opção de Modificador do cardápio atual ou a um Produto equivalente.')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')
                            ->label('Nome Legado')
                            ->disabled()
                            ->columnSpan(2),

                        TextInput::make('legacy_code')
                            ->label('Código Legado')
                            ->disabled()
                            ->placeholder('N/A')
                            ->columnSpan(1),
                    ]),

                    Grid::make(2)->schema([
                        Select::make('modifier_option_id')
                            ->label('Opção no Cardápio Atual')
                            ->options(function () {
                                return DB::table('modifier_options')
                                    ->join('modifier_groups', 'modifier_options.modifier_group_id', '=', 'modifier_groups.id')
                                    ->select(
                                        'modifier_options.id',
                                        'modifier_options.name as option_name',
                                        'modifier_groups.name as group_name'
                                    )
                                    ->whereNull('modifier_groups.deleted_at')
                                    ->orderBy('modifier_groups.name')
                                    ->orderBy('modifier_options.name')
                                    ->get()
                                    ->mapWithKeys(fn ($row) => [
                                        $row->id => "{$row->group_name} ➔ {$row->option_name}"
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Selecione uma opção correspondente')
                            ->helperText('Vincula este termo a um adicional ou ingrediente do sistema novo.')
                            ->columnSpan(1),

                        Select::make('current_product_id')
                            ->label('Produto Equivalente (Opcional)')
                            ->options(fn () => Product::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->placeholder('Nenhum produto direto')
                            ->helperText('Preencha se este item legado virou um produto de venda isolado.')
                            ->columnSpan(1),
                    ]),

                    Section::make('Regras de Importação')->schema([
                        Toggle::make('is_ignored')
                            ->label('Ignorar na Importação?')
                            ->helperText('Se ativado, registros de vendas históricas com este modificador não tentarão vincular opções ou produtos.')
                            ->default(false),
                    ]),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
