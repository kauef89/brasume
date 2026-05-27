<?php

namespace App\Filament\Resources\Employees\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class EmployeeTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('employment_type')
                    ->label('Vínculo')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'clt_44' => 'CLT 44h',
                        'clt_40' => 'CLT 40h',
                        'freelance_fixed' => 'Freelancer Fixo',
                        'freelance_eventual' => 'Freelancer Eventual',
                        default => $state
                    })->badge(),

                TextColumn::make('work_days')
                    ->label('Escala')
                    ->visible(fn ($record) => $record?->employment_type === 'freelance_fixed')
                    ->formatStateUsing(fn ($state) => count($state ?? []) . ' dias/semana')
                    ->color('primary'),

                TextColumn::make('base_remuneration')
                    ->label('Remuneração')
                    ->money('BRL'),
            ])
            ->filters([
                // Adicione filtros aqui no futuro, se precisar
            ])
            ->actions([
                ViewAction::make()->iconButton(), // Botão para abrir as nossas Abas de Extrato!
                EditAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}