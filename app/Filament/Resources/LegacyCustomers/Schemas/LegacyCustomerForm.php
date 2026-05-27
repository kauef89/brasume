<?php

namespace App\Filament\Resources\LegacyCustomers\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

class LegacyCustomerForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make("Mapeamento de Cliente V1")
                ->schema([
                    TextInput::make("legacy_name")
                        ->label("Nome no CSV (V1)")
                        ->required()
                        ->maxLength(255)
                        ->helperText(
                            'O nome exato como aparece na coluna "Cliente" do CSV.',
                        ),

                    Select::make("customer_id")
                        ->label("Cliente Real (V3)")
                        ->relationship("customer", "name")
                        ->searchable()
                        ->preload()
                        ->helperText(
                            "Selecione o cliente real do sistema atual para vincular o histórico.",
                        ),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
