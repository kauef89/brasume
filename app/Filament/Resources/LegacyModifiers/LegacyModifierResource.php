<?php

namespace App\Filament\Resources\LegacyModifiers;

use App\Models\LegacyModifier;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction; // 🚀 Corrigido para Tables\Actions
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\LegacyModifiers\Pages;
use Illuminate\Database\Eloquent\Builder;

class LegacyModifierResource extends Resource
{
    protected static ?string $model = LegacyModifier::class;
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema Legado';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = "Modificadores Antigos";
    protected static ?string $modelLabel = "Modificador Legado";

    public static function getNavigationBadge(): ?string
    {
        // Conta pendentes que não têm nem opção nem produto mapeado
        $count = static::getModel()::whereNull("modifier_option_id")
            ->whereNull("current_product_id")
            ->where("is_ignored", false)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome Original')->disabled(),

            // 🚀 A DUPLA POSSIBILIDADE!
            Radio::make('mapping_type')
                ->label('O que este item era na verdade?')
                ->options([
                    'option' => 'Era um Modificador/Sabor',
                    'product' => 'Era um Produto / Combo Disfarçado',
                ])
                ->default('option')
                ->live()
                ->afterStateHydrated(function ($component, $state, $record) {
                    // Puxa o status correto ao abrir o modal de edição
                    if ($record?->current_product_id) {
                        $component->state('product');
                    } else {
                        $component->state('option');
                    }
                })
                ->dehydrated(false), // Não salva este campo "virtual" no banco

            Select::make('modifier_option_id')
                ->label('Opção Mapeada (Cardápio V3)')
                ->relationship('currentOption', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get) => $get('mapping_type') === 'option'),

                Select::make('current_product_id')
                                ->label('Produto Mapeado (Cardápio V3)')
                                ->relationship(
                                    name: 'currentProduct',
                                    titleAttribute: 'name',
                                    // 🚀 O FILTRO ESTRITO NAS CATEGORIAS: Verifica a relação 'category' do Produto
                                    modifyQueryUsing: fn (Builder $query) => $query->whereHas('category', fn (Builder $q) => $q->where('type', 'external'))
                                )
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get) => $get('mapping_type') === 'product'),

            Toggle::make('is_ignored')->label('Ignorar no BI')->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\LegacyModifiers\Tables\LegacyModifiersTable::build($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLegacyModifiers::route('/'),
        ];
    }
}
