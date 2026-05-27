<?php

namespace App\Filament\Central\Resources\Tenants\Schemas;

// 1. MUDE A IMPORTAÇÃO AQUI TAMBÉM:
use Filament\Schemas\Schema;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\Rules\Unique;

class TenantForm
{
    // 2. ATUALIZE A ASSINATURA:
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Dados da Lanchonete')
                    ->description('Informações principais e de acesso.')
                    ->schema([
                        TextInput::make('id')
                            ->label('Identificador (Subdomínio)')
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn(Unique $rule) => $rule->withoutTrashed()
                            )
                            ->disabledOn('edit')
                            ->helperText('Ex: nativa (Isto criará nativa.brasume.com.br)'),

                        TextInput::make('name')
                            ->label('Nome da Lanchonete / Razão Social')
                            ->required(),
                    ])->columns(2),
            ]);
    }
}
