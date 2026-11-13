<?php

namespace App\Filament\Resources\BankAccounts\RelationManagers;

use App\Enums\BankAccountType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CreditCardsRelationManager extends RelationManager
{
    protected static string $relationship = 'creditCards';
    protected static ?string $title = 'Cartões Físicos e Virtuais Vinculados';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $type = $ownerRecord->type instanceof \BackedEnum ? $ownerRecord->type->value : (string) $ownerRecord->type;
        return $type === BankAccountType::CREDIT_CARD->value;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Identificação do Cartão')
                ->required()
                ->placeholder('Ex: Sicredi Visa Empresarial (Final 7114)'),

            TextInput::make('last_four_digits')
                ->label('4 Últimos Dígitos')
                ->maxLength(4)
                ->required()
                ->placeholder('7114'),

            Select::make('card_type')
                ->label('Modalidade')
                ->options([
                    'physical' => 'Cartão Físico',
                    'virtual'  => 'Cartão Virtual',
                ])
                ->default('physical')
                ->required(),

            TextInput::make('holder_name')
                ->label('Portador / Titular')
                ->placeholder('KAUE FRIEDRICH'),

            Toggle::make('is_active')
                ->label('Ativo')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('last_four_digits')
                    ->label('Final')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state) => $state ? "•••• {$state}" : '-'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Identificação')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('card_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'virtual' ? 'Virtual' : 'Físico')
                    ->color(fn (?string $state) => $state === 'virtual' ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('holder_name')
                    ->label('Portador'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Adicionar Cartão'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}