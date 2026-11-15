<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class LogisticsSchema
{
    public static function make(): array
    {
        return [
            Section::make('Logística e Entregas (Motoboys)')
                ->description('Regras de operação e acerto financeiro para os entregadores.')
                ->icon('heroicon-o-truck')
                ->schema([
                    Toggle::make('courier_keeps_cash')
                        ->label('Motoboy retém o dinheiro recebido nas entregas (Abater da diária)')
                        ->helperText('Quando ATIVO, o dinheiro recebido em espécie dos clientes fica com o motoboy e é descontado do valor a pagar no fechamento. Quando INATIVO, o motoboy presta contas entregando 100% do dinheiro ao caixa e recebe o valor integral da diária/corridas.')
                        ->default(false)
                        ->inline(false)
                        ->columnSpanFull(),

                    Select::make('attention_categories')
                        ->label('Categorias de Atenção (Alerta Amarelo)')
                        ->helperText('Pedidos que contenham itens destas categorias aparecerão com destaque no PWA do Motoboy para evitar derramamentos.')
                        ->multiple()
                        ->options(Category::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ]),
        ];
    }
}
