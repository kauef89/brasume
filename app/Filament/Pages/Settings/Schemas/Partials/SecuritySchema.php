<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;

class SecuritySchema
{
    public static function make(): array
    {
        return [
            // ==========================================
            // 🛑 CONTROLE DE PERDAS OPERACIONAIS
            // ==========================================
            Section::make("Segurança e Controle de Perdas")
                ->description("Defina como o PDV deve lidar com ações críticas (estornos, cancelamentos, descontos). Ações liberadas com senha de gestor ficam gravadas no relatório de Auditoria.")
                ->schema([
                    ToggleButtons::make('pos_security_mode')
                        ->label('Nível de Segurança Operacional')
                        ->options([
                            'free' => new HtmlString('
                                <div class="flex flex-col items-center justify-center text-center py-3 w-full">
                                    ' . Blade::render('<x-heroicon-o-lock-open class="w-8 h-8 mb-2"/>') . '
                                    <span class="font-black text-sm">Modo Livre</span>
                                    <span class="text-[11px] font-medium mt-1 leading-snug">Nenhuma ação exige senha.<br>Ideal para donos operando.</span>
                                </div>
                            '),
                            'standard' => new HtmlString('
                                <div class="flex flex-col items-center justify-center text-center py-3 w-full">
                                    ' . Blade::render('<x-heroicon-o-shield-check class="w-8 h-8 mb-2"/>') . '
                                    <span class="font-black text-sm">Modo Padrão</span>
                                    <span class="text-[11px] font-medium mt-1 leading-snug">Senha apenas para perdas<br>(Estornos, Sangria).</span>
                                </div>
                            '),
                            'strict' => new HtmlString('
                                <div class="flex flex-col items-center justify-center text-center py-3 w-full">
                                    ' . Blade::render('<x-heroicon-o-shield-exclamation class="w-8 h-8 mb-2"/>') . '
                                    <span class="font-black text-sm">Modo Estrito</span>
                                    <span class="text-[11px] font-medium mt-1 leading-snug">Exige senha para quase todas<br>as ações críticas do PDV.</span>
                                </div>
                            '),
                            'custom' => new HtmlString('
                                <div class="flex flex-col items-center justify-center text-center py-3 w-full">
                                    ' . Blade::render('<x-heroicon-o-cog-6-tooth class="w-8 h-8 mb-2"/>') . '
                                    <span class="font-black text-sm">Personalizado</span>
                                    <span class="text-[11px] font-medium mt-1 leading-snug">Escolha manualmente as<br>ações que deseja bloquear.</span>
                                </div>
                            '),
                        ])
                        ->colors([
                            'free'     => 'success',
                            'standard' => 'warning',
                            'strict'   => 'danger',
                            'custom'   => 'primary',
                        ])
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                        ->default('standard')
                        ->live()
                        ->columnSpanFull(),

                    CheckboxList::make('pos_secure_actions')
                        ->label('Exigir autorização superior nestas ações:')
                        ->options([
                            'cancel_item'          => 'Cancelar / Estornar um Item',
                            'cancel_order'         => 'Cancelar / Estornar Pedido Completo',
                            'edit_item'            => 'Editar um Item já enviado',
                            'apply_discount'       => 'Aplicar Desconto Manual',
                            'cash_out'             => 'Realizar Sangria de Caixa',
                            'transfer_table'       => 'Transferir Produtos entre Mesas',
                            'close_shift_override' => 'Autorizar Diferença no Caixa no Fechamento',
                        ])
                        ->visible(fn(Get $get) => $get('pos_security_mode') === 'custom')
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            // ==========================================
            // 🛍️ 1. ACESSO DO CONSUMIDOR (GUEST & LOGINS)
            // ==========================================
            Section::make("1. Acesso e Identificação do Cliente")
                ->description("Configure os métodos de entrada e o modo visitante do cardápio digital.")
                ->schema([
                    Toggle::make('customer_allow_guest')
                        ->label('Permitir Modo Visitante (Guest Mode)')
                        ->helperText('Permite comprar informando apenas Nome e WhatsApp sem criar senha.')
                        ->default(true)
                        ->live()
                        ->columnSpanFull(),

                    Toggle::make('guest_restricted_payments')
                        ->label('Exigir Pagamento Antecipado para Visitantes (Pix)')
                        ->helperText('Protege a loja contra trotes e pedidos falsos.')
                        ->default(true)
                        ->visible(fn(Get $get) => $get('customer_allow_guest') === true)
                        ->columnSpanFull(),

                    CheckboxList::make('customer_auth_methods')
                        ->label('Integrações de Login Rápido')
                        ->options([
                            'google'   => 'Google (Login Social)',
                            'facebook' => 'Facebook (Login Social)',
                            'whatsapp' => 'WhatsApp (Autenticação via OTP)',
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            // ==========================================
            // 📱 2. LOGIN DA EQUIPE (MOBILE)
            // ==========================================
            Section::make("2. Acesso da Equipe (App Mobile)")
                ->description("Como os garçons e operadores de salão entram no sistema pelo telemóvel.")
                ->schema([
                    Radio::make('team_auth_methods')
                        ->label('Integração Principal de Acesso Rápido')
                        ->options([
                            'google' => 'Google Workspace / Corporativo',
                            'pin'    => 'PIN Numérico de 4 a 6 dígitos',
                        ])
                        ->helperText('O sistema exigirá o e-mail e senha tradicionais caso nenhuma opção seja escolhida ou ocorra uma falha de conexão.')
                        ->columns(2)
                        ->columnSpanFull(),
                ]),

            // ==========================================
            // 🖥️ 3. LOGIN DO CAIXA
            // ==========================================
            Section::make("3. Acesso do Caixa Principal (Desktop PosApp)")
                ->description("Segurança para o terminal financeiro da loja.")
                ->schema([
                    CheckboxList::make('cashier_auth_methods')
                        ->label('Integrações de Login Rápido Habilitadas')
                        ->options([
                            'google'  => 'Google (Login Social)',
                            'qr_code' => 'Emparelhamento via QR Code na câmara',
                            'remote'  => 'Liberação Remota via notificação Push para Gestor',
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            // ==========================================
            // 🍳 4. LOGIN DO KDS
            // ==========================================
            Section::make("4. Acesso aos Painéis de Produção (KDS / Bar)")
                ->description("Segurança para os ecrãs de preparo espalhados pela loja.")
                ->schema([
                    CheckboxList::make('kds_auth_methods')
                        ->label('Integrações de Login Rápido Habilitadas')
                        ->options([
                            'google'  => 'Google (Login Social)',
                            'pin'     => 'PIN Numérico',
                            'qr_code' => 'Emparelhamento via QR Code na câmara',
                            'remote'  => 'Liberação Remota via notificação Push para Gestor',
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),

            // ==========================================
            // 👔 5. LOGIN DE GESTÃO (ADMIN) E 2FA
            // ==========================================
            Section::make("5. Acesso ao Painel de Gestão (Retaguarda)")
                ->description("Máxima segurança para a diretoria, com acesso a relatórios e configurações do ERP.")
                ->schema([
                    CheckboxList::make('management_auth_methods')
                        ->label('Métodos de Entrada Autorizados')
                        ->options([
                            'native' => 'Usuário e Senha Tradicional',
                            'google' => 'Google SSO (Single Sign-On)',
                        ])
                        ->default(['native', 'google'])
                        ->disableOptionWhen(fn (string $value): bool => $value === 'native')
                        ->columns(2)
                        ->columnSpanFull(),

                    Toggle::make('require_2fa')
                        ->label('Exigir Autenticação de Dois Fatores (2FA) para Gestores')
                        ->helperText('Obriga os administradores a confirmarem a identidade num segundo dispositivo.')
                        ->live()
                        ->columnSpanFull(),

                    Radio::make('two_factor_method')
                        ->label('Tecnologia de Validação do 2FA')
                        ->options([
                            'google_auth'  => 'App Autenticador (Google Auth, Authy, etc.)',
                            'whatsapp_otp' => 'Código Numérico via WhatsApp',
                        ])
                        ->visible(fn(Get $get) => $get('require_2fa') === true)
                        ->live()
                        ->columns(2)
                        ->columnSpanFull(),
                ]),

            // ==========================================
            // ⚙️ 6. SEÇÕES DINÂMICAS DO 2FA
            // ==========================================
            Section::make("Configuração: Autenticador (TOTP)")
                ->description("O sistema forçará os gestores a emparelharem as suas contas com o App Authenticator.")
                ->icon('heroicon-o-device-phone-mobile')
                ->visible(fn(Get $get) => $get('require_2fa') === true && $get('two_factor_method') === 'google_auth')
                ->schema([
                    TextInput::make('totp_issuer_name')
                        ->label('Nome da Empresa no App (Ex: Brasume Gestão)')
                        ->placeholder('Brasume ERP')
                        ->columnSpanFull(),
                ]),

            Section::make("Configuração: WhatsApp OTP")
                ->description("Configure a API de mensagens que disparará os códigos numéricos.")
                ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                ->visible(fn(Get $get) => $get('require_2fa') === true && $get('two_factor_method') === 'whatsapp_otp')
                ->schema([
                    TextInput::make('wa_provider_token')
                        ->label('Token de Acesso da API Oficial')
                        ->password()
                        ->revealable(),

                    TextInput::make('wa_provider_url')
                        ->label('URL do Servidor Base (Endpoint)')
                        ->url()
                        ->placeholder('https://api.whatsapp.com/v1/messages'),
                ])->columns(2),

            // ==========================================
            // 📱 7. SEÇÃO: QR CODE DE ATENDIMENTO
            // ==========================================
            Section::make('Acesso Rápido para a Equipe (QR Code)')
                ->description('Imprima o QR Code abaixo e cole na retaguarda da loja para que a equipe entre na aplicação imediatamente.')
                ->icon('heroicon-o-qr-code')
                ->schema([
                    ViewField::make('team_qr_code')
                        ->label('')
                        ->dehydrated(false)
                        ->view('filament.components.team-qr-code')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
