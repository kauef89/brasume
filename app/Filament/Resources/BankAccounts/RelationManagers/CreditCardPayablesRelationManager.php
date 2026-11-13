<?php

namespace App\Filament\Resources\BankAccounts\RelationManagers;

use App\Enums\AccountingCategory;
use App\Enums\BankAccountType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CreditCardPayablesRelationManager extends RelationManager
{
    protected static string $relationship = 'accountPayables';
    protected static ?string $title = 'Compras Importadas na Fatura';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $type = $ownerRecord->type instanceof \BackedEnum ? $ownerRecord->type->value : (string) $ownerRecord->type;
        return $type === BankAccountType::CREDIT_CARD->value;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultGroup('due_date')
            ->groups([
                Group::make('due_date')
                    ->label('Fatura (Vencimento)')
                    ->date('d/m/Y')
                    ->collapsible(),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('creditCard.last_four_digits')
                    ->label('Cartão')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state) => $state ? "•••• {$state}" : 'Geral'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Estabelecimento / Compra')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('accounting_category')
                    ->label('Categoria DRE')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof AccountingCategory) {
                            return $state->getLabel();
                        }
                        return AccountingCategory::tryFrom((string) $state)?->getLabel() ?? (string) $state;
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->weight('bold')
                    ->summarize(Sum::make()->money('BRL')->label('Total Fatura')),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'paid' ? 'Fechada/Paga' : 'Aberta')
                    ->color(fn (string $state) => $state === 'paid' ? 'success' : 'warning'),
            ])
            ->paginated([15, 25, 50])
            ->defaultPaginationPageOption(25)
            ->defaultSort('due_date', 'desc');
    }
}