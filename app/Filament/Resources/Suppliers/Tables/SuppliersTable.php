<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use App\Models\Supplier;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name")
                    ->label("Fornecedor (Nome/Fantasia)")
                    ->searchable()
                    ->weight("bold"),
                Tables\Columns\TextColumn::make("document_name")
                    ->label("Razão Social (Fiscal)")
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make("document")
                    ->label("CNPJ/CPF")
                    ->searchable(),

                Tables\Columns\TextColumn::make("type")
                    ->label("Tipo")
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "physical" => "Físico",
                            "virtual" => "Virtual",
                            default => $state,
                        },
                    )
                    ->colors([
                        "success" => "virtual",
                        "warning" => "physical",
                    ]),

                Tables\Columns\TextColumn::make("accounting_category")
                    ->label("Categoria Contábil")
                    ->formatStateUsing(
                        fn(
                            ?string $state,
                        ): ?string => \App\Services\AccountingService::getCategories()[
                            $state
                        ] ?? $state,
                    )
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make("is_active")
                    ->label("Ativo")
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("type")
                    ->label("Filtrar por Tipo")
                    ->options([
                        "physical" => "Físico",
                        "virtual" => "Virtual",
                    ]),

                Tables\Filters\SelectFilter::make("accounting_category")
                    ->label("Filtrar por Categoria")
                    ->options(\App\Services\AccountingService::getCategories()),
            ])
            ->actions([
                // Importação absoluta anti-bugs
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
