<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificação Principal')
                ->description(new HtmlString('<strong>Importante:</strong> Como o sistema utiliza Login via Google, o E-mail inserido aqui deve ser exatamente o mesmo da conta Google do utilizador.'))
                ->schema([
                    TextInput::make('name')
                        ->label('Nome de Exibição')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('E-mail (Conta Google)')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    // 🚀 PONTE DE RH: Captura o parâmetro 'employee_id' da URL automaticamente
                    Select::make('employee_id')
                        ->label('Vincular a Colaborador (RH)')
                        ->options(\App\Models\Employee::where('is_active', true)->pluck('name', 'id'))
                        ->relationship('employee', 'name')
                        ->searchable()
                        ->preload()
                        ->default(request()->query('employee_id'))
                        ->helperText('Vincule o acesso deste usuário ao seu perfil financeiro de funcionário.'),

                    Toggle::make('is_active')
                        ->label('Acesso Ativo')
                        ->default(true)
                        ->helperText('Desative para bloquear o acesso do usuário ao sistema.'),
                ])->columns(2),

            Section::make('Segurança e Permissões do PDV')
                ->description('Defina os acessos administrativos e a senha (PIN) utilizada nas operações do Caixa/PDV.')
                ->schema([
                    Select::make('roles')
                        ->label('Cargos / Nível de Acesso')
                        ->relationship('roles', 'name', fn ($query) => $query->where('guard_name', ['web', 'team']))
                        ->getOptionLabelFromRecordUsing(fn ($record) => match ($record->name) {
                            'admin'   => '👑 Administrador (Acesso Total)',
                            'manager' => '📊 Gerente / Gestão',
                            'pos'     => '🖥️ Operador de Caixa (POS)',
                            'kds'     => '🍳 Cozinha / Produção (KDS)',
                            'waiter'  => '📱 Garçom / Atendimento',
                            'driver'  => '🛵 Entregador / Motoboy',
                            default   => ucfirst($record->name),
                        })
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->columnSpanFull(),

                    TextInput::make('password')
                        ->label('Senha Tradicional (Fallback)')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->helperText('Se o login for apenas pelo Google, digite uma senha qualquer (ex: 12345678) apenas para registo.')
                        ->maxLength(255),

                    TextInput::make('pin')
                        ->label('PIN do PDV')
                        ->password()
                        ->revealable()
                        ->regex('/^[0-9]+$/')
                        ->length(4)
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Senha exclusiva para libertar Estornos e descontos na tela do Caixa.'),
                ])->columns(2),

            // ==========================================
            // 🔒 GESTÃO DO 2FA
            // ==========================================
            Section::make('Autenticação de Dois Fatores (2FA)')
                ->description('Gerencie o status de segurança adicional desta conta.')
                ->hidden(fn (string $context): bool => $context === 'create') // Só aparece ao editar um utilizador já existente
                ->schema([
                    Placeholder::make('2fa_status')
                        ->label('Status do 2FA')
                        ->content(fn ($record) => $record?->two_factor_secret
                            ? new HtmlString('<span class="text-success-600 font-bold flex items-center gap-2"><x-heroicon-o-shield-check class="w-5 h-5"/> Ativo e Configurado</span>')
                            : new HtmlString('<span class="text-warning-600 font-bold flex items-center gap-2"><x-heroicon-o-shield-exclamation class="w-5 h-5"/> Pendente / Não Configurado</span>')
                        ),

                    Checkbox::make('reset_2fa')
                        ->label('Desvincular e Resetar 2FA')
                        ->helperText('Marque esta opção e guarde as alterações se o utilizador perdeu o acesso ao telemóvel e precisa de gerar um novo QR Code no próximo login.')
                        ->dehydrated(false) // Não envia esta variável falsa para a base de dados
                        ->visible(fn ($record) => $record?->two_factor_secret !== null),
                ])->columns(2),
        ]);
    }
}
