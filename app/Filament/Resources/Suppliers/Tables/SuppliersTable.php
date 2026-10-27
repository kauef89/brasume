<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use App\Models\Supplier;
use App\Filament\Resources\Suppliers\Actions\MergeSuppliersBulkAction;
use Illuminate\Database\Eloquent\Builder;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name")
                    ->label("Fornecedor (Nome/Fantasia)")
                    ->searchable()
                    ->weight("bold")
                    ->sortable(),

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

                Tables\Columns\TextColumn::make("branches_count")
                    ->label("Filiais")
                    ->counts("branches")
                    ->badge()
                    ->color("gray")
                    ->sortable(),

                Tables\Columns\ToggleColumn::make("is_trusted")
                    ->label("Confiável")
                    ->tooltip("Manifesta Ciência da Emissão automaticamente na SEFAZ e dá entrada no estoque sem operador se todos os itens estiverem automatizados.")
                    ->afterStateUpdated(function (Supplier $record, bool $state) {
                        if ($record->branches()->exists()) {
                            $record->branches()->update(['is_trusted' => $state]);
                        }
                    }),

                Tables\Columns\ToggleColumn::make("is_active")
                    ->label("Ativo"),
            ])
            ->filters([
                Tables\Filters\Filter::make("only_matrix")
                    ->label("Apenas Matrizes")
                    ->default(true)
                    ->query(fn (Builder $query): Builder => $query->whereNull("parent_id")),

                Tables\Filters\SelectFilter::make("type")
                    ->label("Filtrar por Tipo")
                    ->options([
                        "physical" => "Físico",
                        "virtual" => "Virtual",
                    ]),
            ])
            ->actions([])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    MergeSuppliersBulkAction::make(),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
