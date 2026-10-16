<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class FinancialSchema
{
    public static function make(): array
    {
        return [
            Section::make("Integração Sicredi (PIX)")
                ->description("Configuração para geração de PIX Dinâmico com baixa automática via Webhook.")
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Toggle::make("sicredi_is_active")
                        ->label("Ativar Integração Sicredi")
                        ->live()
                        ->default(false)
                        ->columnSpanFull(),

                    // 🚀 VISIBILITY DIRETO NO GRID GARANTE REATIVIDADE PERFEITA!
                    Grid::make(2)
                        ->visible(fn(Get $get) => (bool) $get("sicredi_is_active"))
                        ->schema([
                            TextInput::make("sicredi_chave_pix")
                                ->label("Chave PIX")
                                ->columnSpan(1),

                            TextInput::make("sicredi_client_id")
                                ->label("Client ID")
                                ->columnSpan(1),

                            TextInput::make("sicredi_client_secret")
                                ->label("Client Secret")
                                ->password()
                                ->revealable()
                                ->columnSpan(2),

                            FileUpload::make("sicredi_cert_path")
                                ->label("Certificado (.pem / .crt)")
                                ->disk('local')
                                ->directory("certificates/sicredi")
                                ->columnSpan(1),

                            FileUpload::make("sicredi_key_path")
                                ->label("Chave Privada (.key)")
                                ->disk('local')
                                ->directory("certificates/sicredi")
                                ->columnSpan(1),

                            // 🚀 REGRAS DE NEGÓCIO DA OPERAÇÃO
                            Section::make("Canais de Cobrança PIX Dinâmico")
                                ->description("Defina em quais telas a equipe poderá gerar QR Codes do Sicredi diretamente para os clientes.")
                                ->schema([
                                    Toggle::make("sicredi_enable_driver_pix")
                                        ->label("Motoboy (Entregas)")
                                        ->helperText("Exibe o botão 'Receber com PIX' no painel do entregador para pedidos com saldo a cobrar.")
                                        ->default(true),

                                    Toggle::make("sicredi_enable_pos_pix")
                                        ->label("Caixa (PDV / Balcão)")
                                        ->helperText("Abre o modal de QR Code dinâmico quando a forma PIX Sicredi for selecionada no caixa.")
                                        ->default(true),

                                    Toggle::make("sicredi_enable_waiter_pix")
                                        ->label("Atendente (Mesas / Salão)")
                                        ->helperText("Exibe a ação 'Cobrar com PIX' no menu rápido de comandas e mesas.")
                                        ->default(true),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),

                            Placeholder::make('dica_teste')
                                ->hiddenLabel()
                                ->content(new HtmlString('<div class="flex items-center gap-2 p-3 bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400 rounded-xl border border-warning-200 dark:border-warning-800 text-sm"><i class="heroicon-m-exclamation-triangle w-5 h-5"></i><span><b>Atenção:</b> Salve as configurações antes de realizar os testes.</span></div>'))
                                ->columnSpanFull(),

                            Actions::make([
                                Action::make('testar_handshake')
                                    ->label('Testar Conexão')
                                    ->icon('heroicon-m-signal')
                                    ->color('success')
                                    ->action(function () {
                                        try {
                                            $gateway = new \App\Services\Financial\Gateways\SicrediPixGateway();
                                            $result = $gateway->testHandshake();
                                            if ($result['success']) Notification::make()->title('Conexão Estabelecida!')->body(new HtmlString("{$result['message']}<br><b>Token:</b> {$result['token_preview']}"))->success()->send();
                                            else Notification::make()->title('Falha')->body($result['message'])->danger()->send();
                                        } catch (\Exception $e) { Notification::make()->title('Erro')->body($e->getMessage())->danger()->send(); }
                                    }),

                                Action::make('testar_pix')
                                    ->label('Gerar PIX Teste')
                                    ->icon('heroicon-m-qr-code')
                                    ->color('info')
                                    ->action(function () {
                                        try {
                                            $gateway = new \App\Services\Financial\Gateways\SicrediPixGateway();
                                            $result = $gateway->generateTestPix();
                                            if ($result['success']) Notification::make()->title('PIX Gerado!')->body(new HtmlString("<b>TXID:</b> {$result['txid']}<br><span class='text-xs font-mono'>{$result['copia_e_cola']}</span>"))->success()->duration(20000)->send();
                                            else Notification::make()->title('Falha')->body($result['message'])->danger()->send();
                                        } catch (\Exception $e) { Notification::make()->title('Erro')->body($e->getMessage())->danger()->send(); }
                                    }),
                            ])
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'mt-2 flex gap-3']),
                        ]),
                ]),
        ];
    }
}
