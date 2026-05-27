<?php
namespace App\Filament\Resources\LegacyPaymentMethods\Schemas;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class LegacyPaymentMethodForm
{
    public static function build(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make("sefaz_code")->label("Cód Sefaz")->disabled(),
            TextInput::make("name")->label("Nome Lida")->disabled(),
            Select::make("current_payment_method_id")
                ->label("Forma de Pagto ERP")
                ->relationship("currentPaymentMethod", "name")
                ->searchable()
                ->preload(),
        ]);
    }
}
