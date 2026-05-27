<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
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
                ])->columns(2),

            Section::make('Segurança e Permissões do PDV')
                ->description('Defina os acessos administrativos e a senha (PIN) utilizada nas operações do Caixa/PDV.')
                ->schema([
                    Select::make('roles')
                        ->label('Cargos / Nível de Acesso')
                        ->relationship('roles', 'name')
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
                        ->label('PIN do PDV (4 a 6 dígitos)')
                        ->password()
                        ->revealable()
                        ->numeric()
                        ->minLength(4)
                        ->maxLength(6)
                        ->helperText('Senha exclusiva para libertar Estornos e descontos na tela do Caixa.')
                        ->unique(ignoreRecord: true),
                ])->columns(2),
        ]);
    }
}
