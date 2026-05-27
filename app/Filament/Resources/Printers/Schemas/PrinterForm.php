<?php

namespace App\Filament\Resources\Printers\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;

class PrinterForm
{
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Configuração da Impressora")
                ->schema([
                    FormComponents\TextInput::make("name")
                        ->label("Nome da Impressora (Ex: Cozinha Quente)")
                        ->required()
                        ->columnSpan(2),
                    FormComponents\TextInput::make("ip_address")
                        ->label("Endereço IP")
                        ->ipv4()
                        ->placeholder("192.168.1.100"),
                    FormComponents\TextInput::make("port")
                        ->label("Porta")
                        ->numeric()
                        ->default(9100),
                    FormComponents\Toggle::make("is_active")
                        ->label("Ativa?")
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(3),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
