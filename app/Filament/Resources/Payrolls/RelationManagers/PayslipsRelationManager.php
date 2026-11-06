<?php

namespace App\Filament\Resources\Payrolls\RelationManagers;

use App\Filament\Resources\Payrolls\Actions\ExportSicrediCnabBulkAction;
use App\Models\Payslip;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Table;

class PayslipsRelationManager extends RelationManager
{
    protected static string $relationship = 'payslips';
    protected static ?string $title = 'Recibos Individuais dos Colaboradores';
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
            ->defaultSort('gross_salary', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('employee.job_title')
                    ->label('Cargo / Função')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Salário Base')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('gross_salary')
                    ->label('Bruto')
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

                Tables\Columns\TextColumn::make('bank_account_display')
                    ->label('Conta Sicredi')
                    ->state(fn (Payslip $record) => ($record->employee?->bank_agency && $record->employee?->bank_account)
                        ? "Ag: {$record->employee->bank_agency} / Cc: {$record->employee->bank_account}-{$record->employee->bank_account_digit}"
                        : null
                    )
                    ->placeholder('Não cadastrada')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('employee.pix_key')
                    ->label('Chave PIX')
                    ->copyable()
                    ->copyMessage('Chave PIX copiada!')
                    ->placeholder('Não cadastrada')
                    ->toggleable(),

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
                    ->modalHeading(fn (Payslip $record) => 'Contracheque: ' . ($record->employee?->name ?? 'Colaborador'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->infolist([
                        Grid::make(3)->schema([
                            TextEntry::make('gross_salary')->label('Total Bruto')->money('BRL'),
                            TextEntry::make('total_deductions')->label('Descontos')->money('BRL')->color('danger'),
                            TextEntry::make('net_salary')->label('Líquido')->money('BRL')->weight('bold')->color('success'),
                        ]),
                        RepeatableEntry::make('items')
                            ->label('Eventos / Rubricas Extraídas do SCI')
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportSicrediCnabBulkAction::make(),
                ]),
            ]);
    }
}
