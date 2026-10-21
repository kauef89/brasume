<?php

namespace App\Filament\Resources\CompanyAssets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use App\Models\Supplier;

class CompanyAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Identificação do Bem Patrimonial')
                ->description('Cadastre equipamentos, instalações e ativos permanentes da empresa.')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')
                            ->label('Nome do Ativo / Equipamento')
                            ->placeholder('Ex: Fachada em ACM com Letreiro LED')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        TextInput::make('asset_tag')
                            ->label('Plaqueta / Tombamento')
                            ->placeholder('Ex: PAT-00124')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                    ]),

                    Grid::make(3)->schema([
                        TextInput::make('location')
                            ->label('Localização Física')
                            ->placeholder('Ex: Fachada Externa, Linha de Fritura, Salão')
                            ->maxLength(100)
                            ->columnSpan(1),

                        Select::make('status')
                            ->label('Situação do Bem')
                            ->options([
                                'active'         => 'Ativo / Em Operação',
                                'in_maintenance' => 'Em Manutenção',
                                'written_off'    => 'Baixado / Desincorporado',
                            ])
                            ->default('active')
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('warranty_until')
                            ->label('Garantia Até')
                            ->columnSpan(1),
                    ]),

                    Textarea::make('description')
                        ->label('Especificações Técnicas / Observações')
                        ->placeholder('Dimensões, número de série do fabricante, voltagem, detalhes contratuais...')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Dados Financeiros e Documento de Origem')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('acquisition_cost')
                            ->label('Custo de Aquisição')
                            ->prefix('R$')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('acquisition_date')
                            ->label('Data de Aquisição')
                            ->default(now())
                            ->columnSpan(1),

                        Select::make('supplier_id')
                            ->label('Fornecedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                    ]),

                    TextInput::make('nfe_key')
                        ->label('Chave de Acesso da NF-e')
                        ->placeholder('44 dígitos da nota fiscal')
                        ->maxLength(44)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
