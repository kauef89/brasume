<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use App\Enums\BankAccountType;
use App\Support\BankList;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome da Conta')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bank_code')
                    ->label('Banco')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): ?string => BankList::all()[$state] ?? $state)
                    ->description(function ($record): ?string {
                        $isVault = $record->type === BankAccountType::VAULT || $record->type === BankAccountType::VAULT->value;

                        return !$isVault && ($record->agency || $record->account_number)
                            ? "Ag: {$record->agency} / Cc: {$record->account_number}"
                            : null;
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_personal')
                    ->label('Uso Pessoal')
                    ->boolean()
                    ->trueIcon('heroicon-m-user')
                    ->falseIcon('heroicon-m-building-storefront')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('initial_balance')
                    ->label('Saldo Inicial')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativa?'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filtrar por Tipo')
                    ->options(BankAccountType::class),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
