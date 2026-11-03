<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\Payslip;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;

class PayslipsRelationManager extends RelationManager
{
    protected static string $relationship = 'payslips';
    protected static ?string $title = 'Histórico de Holerites';
    protected static ?string $modelLabel = 'Holerite';
    protected static ?string $pluralModelLabel = 'Holerites';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('payroll.reference_period')
                    ->label('Competência')
                    ->date('m/Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Salário Base')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('gross_salary')
                    ->label('Total Bruto')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_deductions')
                    ->label('Descontos')
                    ->money('BRL')
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('net_salary')
                    ->label('Líquido a Pagar')
                    ->money('BRL')
                    ->weight('black')
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fgts_amount')
                    ->label('FGTS')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('inss_amount')
                    ->label('INSS')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'paid'      => 'success',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Pendente',
                        'paid'      => 'Pago',
                        'cancelled' => 'Cancelado',
                        default     => $state,
                    }),
            ])
            ->actions([
                Action::make('view_items')
                    ->label('Ver Rubricas')
                    ->icon('heroicon-o-list-bullet')
                    ->color('primary')
                    ->modalHeading(fn (Payslip $record) => 'Detalhamento do Holerite - ' . ($record->payroll?->reference_period ? \Carbon\Carbon::parse($record->payroll->reference_period)->format('m/Y') : ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->infolist([
                        Grid::make(3)->schema([
                            TextEntry::make('gross_salary')->label('Total Bruto')->money('BRL'),
                            TextEntry::make('total_deductions')->label('Descontos')->money('BRL')->color('danger'),
                            TextEntry::make('net_salary')->label('Líquido')->money('BRL')->weight('bold')->color('success'),
                        ]),
                        RepeatableEntry::make('items')
                            ->label('Eventos / Rubricas')
                            ->schema([
                                TextEntry::make('code')->label('Cód.')->weight('bold'),
                                TextEntry::make('name')->label('Descrição'),
                                TextEntry::make('type')
                                    ->label('Tipo')
                                    ->badge()
                                    ->colors([
                                        'success' => 'earning',
                                        'danger'  => 'deduction',
                                    ])
                                    ->formatStateUsing(fn ($state) => $state === 'earning' ? 'Provento' : 'Desconto'),
                                TextEntry::make('reference')->label('Referência'),
                                TextEntry::make('amount')->label('Valor')->money('BRL'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}
