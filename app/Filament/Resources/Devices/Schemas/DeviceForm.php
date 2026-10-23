<?php

namespace App\Filament\Resources\Devices\Schemas;

use App\Enums\DeviceType;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;
use App\Models\User;

class DeviceForm
{
    public static function getFormSchema(): array
    {
        $isPaymentTerminal = fn (Get $get): bool => in_array($get('type'), [
            DeviceType::SMART_POS->value,
            DeviceType::POS_PINPAD->value,
            DeviceType::SMART_POS,
            DeviceType::POS_PINPAD,
        ]);

        $isAppTerminal = fn (Get $get): bool => in_array($get('type'), [
            DeviceType::POS->value,
            DeviceType::KDS->value,
            DeviceType::TOTEM->value,
            DeviceType::WAITER->value,
            DeviceType::POS,
            DeviceType::KDS,
            DeviceType::TOTEM,
            DeviceType::WAITER,
        ]);

        return [
            // =========================================================================
            // 1. IDENTIFICAÇÃO GERAL
            // =========================================================================
            Section::make('Identificação do Dispositivo')
                ->description('Defina a função do equipamento e o seu nome operacional.')
                ->columns(3)
                ->schema([
                    Select::make('type')
                        ->label('Tipo de Dispositivo')
                        ->options(DeviceType::class)
                        ->required()
                        ->live()
                        ->native(false)
                        ->columnSpan(1),

                    TextInput::make('name')
                        ->label('Nome Amigável')
                        ->placeholder(fn (Get $get) => $isPaymentTerminal($get) ? 'Ex: POS Delivery 1, POS Caixa Principal' : 'Ex: Caixa Balcão, Tablet Cozinha')
                        ->helperText('Identificador visual para relatórios e fechamentos de turno.')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),

                    Toggle::make('is_active')
                        ->label('Equipamento Ativo')
                        ->default(true)
                        ->helperText('Se desativado, o terminal é desconsiderado pelo sistema.')
                        ->columnSpan(1),
                ]),

            // =========================================================================
            // 2. EXCLUSIVO MAQUININHAS / TEF: DADOS DA ADQUIRENTE
            // =========================================================================
            Section::make('Configuração da Maquininha / Adquirente')
                ->description('Dados para cruzamento automático com o relatório de vendas (CSV/SiTef).')
                ->columns(3)
                ->visible($isPaymentTerminal)
                ->schema([
                    TextInput::make('terminal_code')
                        ->label('Número do Terminal (Código da Maquininha)')
                        ->placeholder('Ex: APTE0CD9, APT0NV48, APT0O8MX')
                        ->helperText('Código lógico impresso na filipeta e presente no relatório da adquirente.')
                        ->required($isPaymentTerminal)
                        ->maxLength(60)
                        ->columnSpan(1),

                    Select::make('assignment_scope')
                        ->label('Destinação Operacional')
                        ->options([
                            'counter'  => 'Balcão / Caixa Fixo',
                            'delivery' => 'Entregas / Motoboys',
                            'waiter'   => 'Salão / Garçom',
                            'floating' => 'Flutuante / Reserva',
                        ])
                        ->default('counter')
                        ->required($isPaymentTerminal)
                        ->native(false)
                        ->columnSpan(1),

                    Select::make('acquirer_name')
                        ->label('Rede / Adquirente')
                        ->options([
                            'sicredi' => 'Sicredi (APOS SiTef)',
                            'stone'   => 'Stone',
                            'cielo'   => 'Cielo',
                            'rede'    => 'Rede',
                            'pagbank' => 'PagBank',
                            'other'   => 'Outra',
                        ])
                        ->default('sicredi')
                        ->required($isPaymentTerminal)
                        ->native(false)
                        ->columnSpan(1),

                    Select::make('default_user_id')
                        ->label('Operador / Entregador Padrão (Opcional)')
                        ->options(fn () => User::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->placeholder('Nenhum colaborador fixo')
                        ->helperText('Facilita a pré-seleção ao despachar o pedido para entrega.')
                        ->columnSpanFull(),
                ]),

            // =========================================================================
            // 3. EXCLUSIVO TELAS / APLICAÇÕES: EMPARELHAMENTO E SEGURANÇA
            // =========================================================================
            Section::make('Segurança e Emparelhamento de Tela')
                ->description('Credenciais de autorização para telas que executam a aplicação (PWA/PDV).')
                ->columns(2)
                ->visible($isAppTerminal)
                ->schema([
                    TextInput::make('device_uuid')
                        ->label('Chave de Emparelhamento (UUID)')
                        ->default(fn () => (string) Str::uuid())
                        ->disabled()
                        ->dehydrated()
                        ->unique(ignoreRecord: true)
                        ->helperText('Insira este código na tela inicial do dispositivo para autorizá-lo.')
                        ->columnSpan(1),

                    Placeholder::make('last_active_at')
                        ->label('Última Atividade Registrada')
                        ->content(fn ($record) => $record?->last_active_at ? $record->last_active_at->diffForHumans() : 'Sem registro de conexão')
                        ->columnSpan(1),
                ]),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
