<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset; // 🚀 Importado
use Filament\Schemas\Components\Grid;     // 🚀 Importado
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Utilities\Set;
use Carbon\Carbon;
use App\Models\PaymentMethod;

// Importações da Gaveta de Filtros
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Dashboard\Actions\FilterAction;

class BillingDashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-currency-dollar";
    protected static ?string $navigationLabel = "Faturamento";
    protected static ?string $title = "Painel de Faturamento";
    protected static ?string $slug = "faturamento";
    protected static string|\UnitEnum|null $navigationGroup = "Relatórios Gerenciais";

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('Configurações de Exibição')
                ->icon('heroicon-m-funnel')
                ->slideOver() // Mantém a gaveta lateral limpa e elegante
                ->form([

                    // 🚀 GRUPAMENTO 1: PERÍODO
                    Fieldset::make('Período de Análise')
                        ->schema([
                            ToggleButtons::make('preset')
                                ->hiddenLabel()
                                ->options([
                                    'today' => 'Hoje',
                                    'yesterday' => 'Ontem',
                                    'this_week' => 'Esta Semana',
                                    'this_month' => 'Este Mês',
                                    'this_year' => 'Este Ano',
                                    'custom' => 'Personalizado',
                                ])
                                ->default('this_month')
                                ->inline() // 🚀 Espalha os botões horizontalmente
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state === 'custom') return;

                                    $now = Carbon::now();
                                    $dates = match ($state) {
                                        'today' => [$now->copy(), $now->copy()],
                                        'yesterday' => [$now->copy()->subDay(), $now->copy()->subDay()],
                                        'this_week' => [$now->copy()->startOfWeek(), $now->copy()],
                                        'this_month' => [$now->copy()->startOfMonth(), $now->copy()],
                                        'this_year' => [$now->copy()->startOfYear(), $now->copy()],
                                        default => [null, null],
                                    };

                                    if ($dates[0] && $dates[1]) {
                                        $set('startDate', $dates[0]->toDateString());
                                        $set('endDate', $dates[1]->toDateString());
                                    }
                                })
                                ->columnSpanFull(),

                                DatePicker::make('startDate')
                                        ->label('Data Inicial')
                                        ->maxDate(now()),

                                    DatePicker::make('endDate')
                                        ->label('Data Final')
                                        ->maxDate(now()),
                        ]),

                    // 🚀 GRUPAMENTO 2: SEGMENTAÇÃO MÚLTIPLA
                    Fieldset::make('Filtros e Segmentação')
                        ->schema([
                            ToggleButtons::make('order_type')
                                ->label('Tipos de Pedido')
                                ->options([
                                    'delivery' => 'Delivery',
                                    'table' => 'Mesa',
                                    'counter' => 'Balcão',
                                    'null_type' => 'Sem Tipo',
                                ])
                                ->multiple() // 🚀 Permite multi-seleção
                                ->default(['delivery', 'table', 'counter', 'null_type']) // 🚀 Todos selecionados por padrão
                                ->inline()
                                ->columnSpanFull(),

                            ToggleButtons::make('payment_method')
            ->label('Formas de Pagamento')
            ->options(fn () =>
                // Pega as formas reais do banco + injeta o coringa no final do array
                PaymentMethod::pluck('name', 'id')
                    ->put('null_payment', 'Sem Forma') // 🚀 O Coringa dos Pagamentos!
                    ->toArray()
            )
            ->multiple()
            ->default(fn () =>
                // Todos os IDs reais + o id do coringa por padrão
                array_merge(PaymentMethod::pluck('id')->toArray(), ['null_payment'])
            )
            ->inline()
            ->columnSpanFull(),
    ]),

                    // 🚀 GRUPAMENTO 3: COMPARAÇÃO E ESTRUTURAÇÃO
                    Fieldset::make('Métricas e Gráfico')
                        ->schema([
                            ToggleButtons::make('compare_with')
                                ->label('Comparar Crescimento')
                                ->options([
                                    'none' => 'Sem Comparação',
                                    'previous_period' => 'Período Anterior',
                                    'previous_year' => 'Ano Passado',
                                ])
                                ->default('none')
                                ->inline()
                                ->columnSpanFull(),

                            ToggleButtons::make('stratify_by')
                                ->label('Estratificar Gráfico')
                                ->options([
                                    'none' => 'Geral (Cor Única)',
                                    'type' => 'Por Tipo',
                                    'payment' => 'Por Pagamento',
                                ])
                                ->default('none')
                                ->inline()
                                ->columnSpanFull(),
                        ]),
                ]),
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\BillingStatsWidget::class,
            \App\Filament\Widgets\BillingChartWidget::class,
        ];
    }
}
