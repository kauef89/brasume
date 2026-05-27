<?php
namespace App\Filament\Resources\LegacyPaymentMethods\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;

class LegacyPaymentMethodsTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("sefaz_code")->label("Cód"),
                TextColumn::make("name")->label("Nome Sefaz"),
                TextColumn::make("currentPaymentMethod.name")
                    ->label("Mapeado Para")
                    ->badge()
                    ->color("success")
                    ->placeholder("Pendente"),
            ])
            ->recordActions([EditAction::make()->label("Mapear")]);
    }
}
