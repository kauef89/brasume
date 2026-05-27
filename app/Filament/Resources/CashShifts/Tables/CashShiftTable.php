<?php

namespace App\Filament\Resources\CashShifts\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\CashShift;
use App\Models\Transaction;

class CashShiftTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Turno #')->sortable(),
                Tables\Columns\TextColumn::make('bankAccount.name')->label('Gaveta')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('opener.name')->label('Operador'),
                Tables\Columns\TextColumn::make('opened_at')->label('Abertura')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('opening_balance')->label('Fundo de Troco')->money('BRL'),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Aberto',
                        'closed' => 'Fechado',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'open',
                        'gray' => 'closed',
                    ]),
                    
                Tables\Columns\TextColumn::make('difference')
                    ->label('Diferença')
                    ->money('BRL')
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray'))
                    ->visible(fn ($record) => $record && $record->status === 'closed'),
            ])
            ->defaultSort('opened_at', 'desc')
            ->actions([
                
                // ==========================================
                // 1. SUPRIMENTO (Entrada de Dinheiro)
                // ==========================================
                \Filament\Actions\Action::make('suprimento')
                    ->label('Suprimento')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (CashShift $record) => $record->status === 'open')
                    ->form([
                        TextInput::make('amount')->label('Valor (R$)')->numeric()->prefix('R$')->required(),
                        TextInput::make('description')->label('Motivo')->default('Troco extra / Suprimento')->required(),
                    ])
                    ->action(function (CashShift $record, array $data) {
                        Transaction::create([
                            'type' => 'in',
                            'amount' => $data['amount'],
                            'description' => $data['description'],
                            'bank_account_id' => $record->bank_account_id,
                            'transaction_date' => now(),
                            'operator_name' => auth()->user()->name ?? 'Sistema',
                        ]);
                        Notification::make()->title('Suprimento Registrado!')->success()->send();
                    }),

                // ==========================================
                // 2. SANGRIA (Retirada de Dinheiro)
                // ==========================================
                \Filament\Actions\Action::make('sangria')
                    ->label('Sangria')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn (CashShift $record) => $record->status === 'open')
                    ->form([
                        TextInput::make('amount')->label('Valor (R$)')->numeric()->prefix('R$')->required(),
                        TextInput::make('description')->label('Motivo')->default('Retirada de excesso / Sangria')->required(),
                    ])
                    ->action(function (CashShift $record, array $data) {
                        Transaction::create([
                            'type' => 'out',
                            'amount' => $data['amount'],
                            'description' => $data['description'],
                            'bank_account_id' => $record->bank_account_id,
                            'transaction_date' => now(),
                            'operator_name' => auth()->user()->name ?? 'Sistema',
                        ]);
                        Notification::make()->title('Sangria Registrada!')->success()->send();
                    }),

                // ==========================================
                // 3. FECHAR CAIXA (Inteligente)
                // ==========================================
                \Filament\Actions\Action::make('fechar_caixa')
                    ->label('Fechar Caixa')
                    ->icon('heroicon-m-lock-closed')
                    ->color('danger')
                    ->visible(fn (CashShift $record) => $record->status === 'open')
                    ->form(function (CashShift $record) {
                        
                        // MATEMÁTICA REAL DO CAIXA!
                        // Pega todas as entradas (Vendas em Dinheiro e Suprimentos) desde que o caixa abriu
                        $entradas = Transaction::where('bank_account_id', $record->bank_account_id)
                            ->where('transaction_date', '>=', $record->opened_at)
                            ->where('type', 'in')
                            ->sum('amount');
                            
                        // Pega todas as saídas (Sangrias e Despesas) desde que o caixa abriu
                        $saidas = Transaction::where('bank_account_id', $record->bank_account_id)
                            ->where('transaction_date', '>=', $record->opened_at)
                            ->where('type', 'out')
                            ->sum('amount');

                        // O que o sistema espera que esteja na gaveta de dinheiro:
                        $expected = $record->opening_balance + $entradas - $saidas;
                        
                        return [
                            TextInput::make('closing_balance_expected')
                                ->label('Valor Esperado (Fundo + Vendas + Suprimentos - Sangrias)')
                                ->numeric()
                                ->prefix('R$')
                                ->default($expected)
                                ->readOnly()
                                ->extraInputAttributes(['style' => 'font-weight: bold; background-color: #f3f4f6;']),
                                
                            TextInput::make('closing_balance_actual')
                                ->label('Valor Físico Contado na Gaveta')
                                ->numeric()
                                ->prefix('R$')
                                ->required()
                                ->autofocus(),
                                
                            Textarea::make('notes')
                                ->label('Justificativa (Obrigatório se houver diferença)'),
                        ];
                    })
                    ->action(function (CashShift $record, array $data): void {
                        $record->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                            'closed_by' => auth()->id(),
                            'closing_balance_expected' => $data['closing_balance_expected'],
                            'closing_balance_actual' => $data['closing_balance_actual'],
                            'notes' => $data['notes'],
                        ]);
                        
                        Notification::make()->title('Caixa Fechado com sucesso!')->success()->send();
                    }),

                \Filament\Actions\ViewAction::make()->iconButton(),
            ]);
    }
}