<?php

namespace App\Filament\Pages\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use App\Services\Analytics\LegacyDataCapabilityService;
use Carbon\Carbon;

class BillingFiltersSchema
{
    public static function getFormSchema(): array
    {
        $capabilities = LegacyDataCapabilityService::analyze();
        $initialMinDate = $capabilities['absolute_min_date'] ?? '2020-01-01';
        $initialMaxDate = $capabilities['absolute_max_date'] ?? now()->toDateString();

        // 🚀 O MOTOR DE AGRUPAMENTO AUTOMÁTICO
        $autoGroup = function ($start, $end, Set $set) {
            if (!$start || !$end) return;

            $days = Carbon::parse($start)->diffInDays(Carbon::parse($end));

            if ($days <= 60) {
                $set('time_grouping', 'day');
            } elseif ($days <= 700) {
                $set('time_grouping', 'month');
            } else {
                $set('time_grouping', 'year');
            }
        };

        return [
            Section::make('Recorte Temporal')
                ->description('Selecione um período rápido ou escolha as datas manualmente.')
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    ToggleButtons::make('quick_range')
                        ->hiddenLabel()
                        ->options([
                            'today' => 'Hoje',
                            'yesterday' => 'Ontem',
                            'this_week' => 'Esta Semana',
                            'last_week' => 'Sem. Passada',
                            'this_month' => 'Este Mês',
                            'last_month' => 'Mês Passado',
                            'this_year' => 'Este Ano',
                            'last_year' => 'Ano Passado',
                            'custom' => 'Personalizado',
                        ])
                        ->default('this_month')
                        ->inline()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) use ($autoGroup) {
                            if ($state === 'custom') return;

                            $cap = LegacyDataCapabilityService::analyze();
                            $minDate = $cap['absolute_min_date'] ?? '2020-01-01';
                            $maxDate = $cap['absolute_max_date'] ?? now()->toDateString();

                            $now = Carbon::now();
                            $start = $now->toDateString();
                            $end = $now->toDateString();

                            match ($state) {
                                'today' => [$start = $now->toDateString(), $end = $now->toDateString()],
                                'yesterday' => [$start = $now->copy()->subDay()->toDateString(), $end = $now->copy()->subDay()->toDateString()],
                                'this_week' => [$start = $now->startOfWeek()->toDateString(), $end = $now->endOfWeek()->toDateString()],
                                'last_week' => [$start = $now->copy()->subWeek()->startOfWeek()->toDateString(), $end = $now->copy()->subWeek()->endOfWeek()->toDateString()],
                                'this_month' => [$start = $now->startOfMonth()->toDateString(), $end = $now->endOfMonth()->toDateString()],
                                'last_month' => [$start = $now->copy()->subMonth()->startOfMonth()->toDateString(), $end = $now->copy()->subMonth()->endOfMonth()->toDateString()],
                                'this_year' => [$start = $now->startOfYear()->toDateString(), $end = $now->endOfYear()->toDateString()],
                                'last_year' => [$start = $now->copy()->subYear()->startOfYear()->toDateString(), $end = $now->copy()->subYear()->endOfYear()->toDateString()],
                            };

                            $adjusted = false;
                            $message = "";

                            if ($end > $maxDate) {
                                $end = $maxDate;
                                $adjusted = true;
                                $message = "Os dados mais recentes vão até " . Carbon::parse($maxDate)->format('d/m/Y') . ".";
                            }

                            if ($start < $minDate) {
                                $start = $minDate;
                                $adjusted = true;
                                $message = "O histórico mais antigo começa em " . Carbon::parse($minDate)->format('d/m/Y') . ".";
                            }

                            if ($start > $end) {
                                $start = $end;
                            }

                            $set('startDate', $start);
                            $set('endDate', $end);

                            // Dispara a reatividade do agrupamento
                            $autoGroup($start, $end, $set);

                            if ($adjusted) {
                                Notification::make()
                                    ->title('Período Ajustado Automaticamente')
                                    ->body($message)
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->columnSpanFull(),

                    Grid::make(2)
                        ->schema([
                            DatePicker::make('startDate')
                                ->label('Data Inicial')
                                ->minDate($initialMinDate)
                                ->maxDate($initialMaxDate)
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, $state) use ($autoGroup) {
                                    $set('quick_range', 'custom');

                                    $cap = LegacyDataCapabilityService::analyze();
                                    $minDate = $cap['absolute_min_date'] ?? '2020-01-01';

                                    if ($state < $minDate) {
                                        $set('startDate', $minDate);
                                        $state = $minDate;
                                        Notification::make()->title('Ajustado')->body('A data inicial foi limitada ao primeiro registro disponível.')->warning()->send();
                                    }

                                    // Dispara a reatividade
                                    $autoGroup($state, $get('endDate'), $set);
                                })
                                ->required(),

                            DatePicker::make('endDate')
                                ->label('Data Final')
                                ->minDate(fn (Get $get) => $get('startDate') ?? $initialMinDate)
                                ->maxDate($initialMaxDate)
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, $state) use ($autoGroup) {
                                    $set('quick_range', 'custom');

                                    $cap = LegacyDataCapabilityService::analyze();
                                    $maxDate = $cap['absolute_max_date'] ?? now()->toDateString();

                                    if ($state > $maxDate) {
                                        $set('endDate', $maxDate);
                                        $state = $maxDate;
                                        Notification::make()->title('Ajustado')->body('A data final foi limitada ao último registro disponível.')->warning()->send();
                                    }

                                    // Dispara a reatividade
                                    $autoGroup($get('startDate'), $state, $set);
                                })
                                ->required(),
                        ]),
                ]),

            Section::make('Estratificações Avançadas')
                ->description('Ao usar estes filtros, a época da "caderneta" (V0) será ocultada dos cálculos.')
                ->icon('heroicon-o-funnel')
                ->schema([
                    ToggleButtons::make('time_grouping')
                        ->label('Agrupar Gráficos Por')
                        ->options([
                            'day' => 'Dia',
                            'week' => 'Semana',
                            'month' => 'Mês',
                            'year' => 'Ano', // 🚀 NOVA OPÇÃO
                        ])
                        ->default('day')
                        ->grouped()
                        ->colors([
                            'day' => 'primary',
                            'week' => 'info',
                            'month' => 'success',
                            'year' => 'warning',
                        ])
                        ->columnSpanFull(),

                    ToggleButtons::make('order_types')
                        ->label('Canais de Atendimento')
                        ->multiple()
                        ->inline()
                        ->options([
                            'delivery' => 'Delivery',
                            'counter' => 'Balcão / Retirada',
                            'table' => 'Consumo Local (Mesas)',
                        ])
                        ->columnSpanFull(),

                    ToggleButtons::make('payment_methods')
                        ->label('Formas de Pagamento')
                        ->multiple()
                        ->inline()
                        ->options(fn () => \App\Models\PaymentMethod::pluck('name', 'id')->toArray())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
