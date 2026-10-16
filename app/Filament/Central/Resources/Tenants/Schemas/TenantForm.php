<?php

namespace App\Filament\Central\Resources\Tenants\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Validation\Rules\Unique;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Dados da Lanchonete')
                    ->description('Informações principais e de acesso.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('id')
                                ->label('Identificador (ID do Banco)')
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn(Unique $rule) => $rule->withoutTrashed()
                                )
                                ->disabledOn('edit')
                                ->helperText('Deixe em branco para gerar um UUID, ou digite um slug (ex: lanchedoze).'),

                            TextInput::make('name')
                                ->label('Nome da Lanchonete / Razão Social')
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('email')
                                ->label('E-mail do Responsável')
                                ->email()
                                ->required(),

                            TextInput::make('phone')
                                ->label('WhatsApp / Telefone')
                                ->mask('(99) 99999-9999')
                                ->stripCharacters(['(', ')', '-', ' ']),
                        ]),

                        Grid::make(2)->schema([
                        TextInput::make('custom_domain')
                            ->label('Domínio de Acesso')
                            ->placeholder('Ex: lanchedoze.com.br')
                            ->helperText('O domínio que o cliente usará para acessar.')
                            ->required()
                            ->dehydrated(false) // Continua falso para não quebrar o Eloquent do Tenant
                            ->formatStateUsing(fn ($record) => $record?->domains->first()?->domain) // 🚀 CARREGA O DOMÍNIO NA EDIÇÃO!
                            ->columnSpan(1),

                            Toggle::make('is_active')
                                ->label('Conta Ativa')
                                ->helperText('Desative para suspender o acesso da lanchonete ao sistema.')
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(1),
                        ]),
                    ]),
            ]);
    }
}
