<?php

namespace App\Filament\Resources\Devices\Schemas;


use App\Enums\DeviceType;use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Str;

class DeviceForm
{
    public static function getFormSchema(): array
    {
        return [
                Section::make('Identificação do Terminal')
                    ->description('Cadastre a máquina para gerar a chave de emparelhamento.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome do Dispositivo')
                            ->placeholder('Ex: Caixa Principal, Tablet Cozinha 1')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Select::make('type')
                            ->label('Função da Máquina')
                            ->options(DeviceType::class)
                            ->required()
                            ->native(false)
                            ->columnSpan(1),
                    ]),

                Section::make('Segurança e Status')
                    ->columns(2)
                    ->schema([
                        // 🚀 O UUID é gerado automaticamente e não pode ser editado!
                        TextInput::make('device_uuid')
                            ->label('Chave de Emparelhamento (UUID)')
                            ->default(fn () => (string) Str::uuid())
                            ->disabled()
                            ->dehydrated() // Garante que, mesmo desabilitado, vai para o banco ao criar
                            ->unique(ignoreRecord: true)
                            ->helperText('Use este código para autorizar a máquina na tela de Gateway.')
                            ->columnSpan(2),

                        Toggle::make('is_active')
                            ->label('Dispositivo Ativo')
                            ->default(true)
                            ->helperText('Desative para bloquear imediatamente o acesso desta máquina.')
                            ->columnSpan(1),

                        Placeholder::make('last_active_at')
                            ->label('Última Sincronização')
                            ->content(fn ($record) => $record?->last_active_at ? $record->last_active_at->diffForHumans() : 'Nunca acessado')
                            ->columnSpan(1),
                    ]),
            ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
