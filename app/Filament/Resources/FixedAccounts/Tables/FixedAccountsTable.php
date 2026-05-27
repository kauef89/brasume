<?php

namespace App\Filament\Resources\FixedAccounts\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;

class FixedAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("name")->label("Conta")->searchable(),
                TextColumn::make("supplier.name")->label("Fornecedor"),
                TextColumn::make("due_day")->label("Vence Dia")->sortable(),
                TextColumn::make("value_type")
                    ->label("Tipo")
                    ->badge()
                    ->color(
                        fn(string $state): string => match ($state) {
                            "fixed" => "success",
                            "variable" => "warning",
                        },
                    )
                    ->formatStateUsing(
                        fn($state) => $state === "fixed" ? "Fixo" : "Variável",
                    ),

                IconColumn::make("is_auto_debit")
                    ->label("Débito Aut.")
                    ->boolean(),

                ToggleColumn::make("is_active")->label("Ativa"),
            ])
            ->filters([
                SelectFilter::make("value_type")
                    ->label("Tipo de Conta")
                    ->options(["fixed" => "Fixa", "variable" => "Variável"]),
                TrashedFilter::make(),
            ])
            ->actions([EditAction::make()->iconButton()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
