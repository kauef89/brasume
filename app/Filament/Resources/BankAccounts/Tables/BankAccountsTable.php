<?php

namespace App\Filament\Resources\BankAccounts\Tables;


use App\Enums\BankAccountType;use Filament\Tables\Table;
use Filament\Tables;
use App\Support\BankList; // A nossa classe que traduz os bancos

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            // Nome personalizado da conta (ex: Sicredi Principal)
            Tables\Columns\TextColumn::make('name')
                ->label('Nome da Conta')
                ->searchable()
                ->sortable(),

            // Coluna da Instituição Financeira
            Tables\Columns\TextColumn::make('bank_code')
                ->label('Banco')
                ->searchable()
                ->sortable()
                ->formatStateUsing(fn (?string $state): ?string => BankList::all()[$state] ?? $state)
                ->description(fn ($record) => $record->type !== 'vault'
                    ? "Ag: {$record->agency} / Cc: {$record->account_number}"
                    : null
                ),

            // Tipo de Conta com Badge colorida
            Tables\Columns\TextColumn::make('type')->label('Tipo')->badge()
                            ->label('Tipo')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'vault' => 'Cofre',

                Tables\Columns\IconColumn::make('is_personal')
                    ->label('Uso Pessoal')
                    ->boolean()
                    ->trueIcon('heroicon-m-user')
                    ->falseIcon('heroicon-m-building-storefront')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                                'checking' => 'Corrente',
                                'savings' => 'Poupança',
                                'credit_card' => 'Cartão de Crédito', // 🚀
                                default => $state,
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'vault' => 'warning',
                                'checking' => 'info',
                                'savings' => 'success',
                                'credit_card' => 'danger', // 🚀 Vermelho/Danger pois é conta de passivo!
                                default => 'gray',
                            }),

            // Saldo Inicial formatado em Reais
            Tables\Columns\TextColumn::make('initial_balance')
                ->label('Saldo Inicial')
                ->money('BRL')
                ->sortable(),

            // Switch de Ativo/Inativo diretamente na listagem
            Tables\Columns\ToggleColumn::make('is_active')
                ->label('Ativa?'),

        ])
        ->filters([
            // Filtro rápido por tipo de conta
            Tables\Filters\SelectFilter::make('type')
                ->label('Filtrar por Tipo')
                ->options(BankAccountType::class),
        ])
        ->actions([
                    \Filament\Actions\ViewAction::make(), // 🚀 NOVO BOTÃO DE VER
                    \Filament\Actions\EditAction::make(),
                ])
        ->bulkActions([
            \Filament\Actions\BulkActionGroup::make([
                \Filament\Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }
}
