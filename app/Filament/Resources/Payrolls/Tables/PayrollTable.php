<?php

namespace App\Filament\Resources\Payrolls\Tables;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reference_period', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference_period')
                    ->label('Competência')
                    ->date('m/Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payslips_count')
                    ->label('Colaboradores')
                    ->counts('payslips')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total_earnings')
                    ->label('Total Bruto')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_deductions')
                    ->label('Deduções (INSS/IRRF)')
                    ->money('BRL')
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_net')
                    ->label('Total Líquido')
                    ->money('BRL')
                    ->weight('black')
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_fgts')
                    ->label('FGTS Total')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft'    => 'warning',
                        'approved' => 'primary',
                        'paid'     => 'success',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft'    => 'Rascunho',
                        'approved' => 'Aprovada',
                        'paid'     => 'Paga',
                        default    => $state,
                    }),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ]);
    }
}
