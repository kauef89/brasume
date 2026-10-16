<?php

namespace App\Filament\Resources\Printers\Schemas;


use App\Enums\PrinterType;use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components\Utilities\Get;

class PrinterForm
{
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Identificação da Impressora")
                ->description("Dê um apelido amigável e defina a finalidade deste equipamento.")
                ->schema([
                    FormComponents\TextInput::make("name")
                        ->label("Apelido da Impressora")
                        ->placeholder("Ex: Frente de Caixa, Cozinha Quente...")
                        ->required()
                        ->columnSpan(2),

                    FormComponents\Toggle::make("is_cashier")
                        ->label("É a Impressora do Caixa?")
                        ->helperText("Usada para Fechamentos, NFC-e e Relatórios do Motoboy.")
                        ->default(false)
                        ->inline(false)
                        ->columnSpan(1),

                    FormComponents\Select::make("type")
                        ->label("Tipo de Equipamento")
                        ->options(PrinterType::class)
                        ->default('receipt')
                        ->live()
                        ->required(),

                    FormComponents\Select::make("paper_width")
                        ->label("Largura da Bobina")
                        ->options([
                            58 => '58mm (Pequena - Bobina Amarela)',
                            80 => '80mm (Padrão - NFC-e)',
                        ])
                        ->default(58) // 🚀 Focado na realidade das lanchonetes!
                        ->required()
                        ->visible(fn (Get $get) => $get('type') === 'receipt'),

                    FormComponents\Toggle::make("is_active")
                        ->label("Ativa?")
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(3),

            SchemaComponents\Section::make("Configuração da Bridge (Impressão Local)")
                ->description("Como o agente local (Bridge) vai encontrar esta impressora instalada?")
                ->schema([
                    FormComponents\Select::make("connection_type")
                        ->label("Interface de Conexão")
                        ->options([
                            'usb'       => 'USB / Driver do SO (Padrão)',
                            'bluetooth' => 'Bluetooth',
                            'network'   => 'Rede Local (Cabo/Wi-Fi)',
                        ])
                        ->default('usb')
                        ->live()
                        ->required()
                        ->columnSpanFull(),

                    // O campo mágico que muda a label e placeholder dependendo do tipo!
                    FormComponents\TextInput::make("os_name")
                        ->label(fn (Get $get) => match ($get('connection_type')) {
                            'bluetooth' => 'Endereço MAC do Bluetooth',
                            'network'   => 'Endereço IP Local',
                            default     => 'Nome no Sistema Operacional (ou Porta COM)'
                        })
                        ->placeholder(fn (Get $get) => match ($get('connection_type')) {
                            'bluetooth' => 'Ex: 00:11:22:33:44:55',
                            'network'   => 'Ex: 192.168.0.100',
                            default     => 'Ex: EPSON TM-T20X Receipt ou COM3'
                        })
                        ->helperText(fn (Get $get) => match ($get('connection_type')) {
                            'bluetooth' => 'Endereço físico do dispositivo pareado no Windows/Linux.',
                            'network'   => 'IP local de rede. A Bridge enviará os dados diretamente para ele.',
                            default     => 'Nome exato como aparece em "Dispositivos e Impressoras" no Windows.'
                        })
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
