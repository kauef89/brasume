<?php

namespace App\Filament\Resources\AccountPayables\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use App\Filament\Resources\AccountPayables\Actions\ReverseConciliationAction; // 🚀 O NOVO IMPORT

class AccountPayablesTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->defaultGroup('due_date')
            ->groups([
                Group::make('due_date')
                    ->label('Vencimento')
                    ->date(),
                Group::make('status')
                    ->label('Status'),
                Group::make('supplier.name')
                    ->label('Fornecedor'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->status === 'pending' && $record->due_date < now() ? 'danger' : null),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('beneficiary_display')
                    ->label('Favorecido')
                    ->state(fn ($record) => $record->supplier?->name ?? $record->employee?->name ?? $record->supplier_name ?? '-')
                    ->description(fn ($record) => $record->employee_id ? 'Colaborador' : ($record->payment_batch_id ? "Lote CNAB #{$record->payment_batch_id}" : null))
                    ->searchable(query: fn ($query, string $search) => $query
                        ->whereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('employee', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ),

                Tables\Columns\TextColumn::make('settlement_mode')
                    ->label('Modalidade')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->defaultSort('due_date', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options(\App\Enums\PaymentStatus::class),
                SelectFilter::make('settlement_mode')
                    ->label('Modalidade Bancária')
                    ->options(\App\Enums\PayableSettlementMode::class),
                TernaryFilter::make('is_late')
                    ->label('Atrasadas')
                    ->queries(
                        true: fn ($query) => $query->where('status', 'pending')->whereDate('due_date', '<', now()),
                        false: fn ($query) => $query->whereDate('due_date', '>=', now()),
                    )
            ])
            ->actions([
                Action::make('pay')
                    ->label('Baixar')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária / Cofre (Saída)')
                            ->options(fn () => \App\Models\BankAccount::liquidTreasury()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        DatePicker::make('payment_date')
                            ->label('Data em que foi pago')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'paid',
                            'payment_date' => $data['payment_date'],
                            'bank_account_id' => $data['bank_account_id'],
                        ]);

                        \App\Models\Transaction::create([
                            'bank_account_id' => $data['bank_account_id'],
                            'type'            => 'out',
                            'amount'          => $record->amount,
                            'description'     => $record->description . ($record->supplier ? ' (' . $record->supplier->name . ')' : ''),
                            'transaction_date'=> $data['payment_date'],
                            'supplier_id'     => $record->supplier_id,
                            'operator_name'   => auth()->user()?->name ?? 'Sistema',
                        ]);
                    }),

                Action::make('partial_settle')
                    ->label('Abater Parcial')
                    ->icon('heroicon-m-scissors')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'pending' && (float) $record->amount > 0.01)
                    ->modalHeading(fn ($record) => 'Abatimento Parcial: ' . $record->description)
                    ->modalDescription(fn ($record) => 'Saldo atual em aberto: R$ ' . number_format((float) $record->amount, 2, ',', '.'))
                    ->form([
                        \Filament\Forms\Components\TextInput::make('paid_amount')
                            ->label('Valor do Abatimento (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(fn ($record) => round((float) $record->amount - 0.01, 2)),
                        \Filament\Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária / Cofre (Saída)')
                            ->options(fn () => \App\Models\BankAccount::liquidTreasury()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        DatePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->default(now())
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('note')
                            ->label('Observação / Motivo')
                            ->placeholder('Ex: Vale adiantado via PIX'),
                    ])
                    ->action(function ($record, array $data) {
                        $paid = (float) $data['paid_amount'];
                        $service = app(\App\Services\Financial\AccountPayableSettlementService::class);

                        $res = $service->settle(
                            payable: $record,
                            paidAmount: $paid,
                            bankAccountId: (int) $data['bank_account_id'],
                            transactionId: null,
                            paymentDate: $data['payment_date'],
                            customNote: !empty($data['note'])
                                ? "[" . \Carbon\Carbon::parse($data['payment_date'])->format('d/m/Y') . "] Abatimento parcial de R$ " . number_format($paid, 2, ',', '.') . " ({$data['note']})."
                                : null
                        );

                        \App\Models\Transaction::create([
                            'bank_account_id'  => $data['bank_account_id'],
                            'employee_id'      => $record->employee_id,
                            'supplier_id'      => $record->supplier_id,
                            'type'             => 'out',
                            'category'         => 'payable_settlement',
                            'amount'           => $paid,
                            'description'      => $res['settled_payable']->description,
                            'transaction_date' => $data['payment_date'],
                            'operator_name'    => auth()->user()?->name ?? 'Sistema',
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Abatimento registrado!')
                            ->body('Novo saldo pendente no título #' . $record->id . ': R$ ' . number_format($res['remaining_amount'], 2, ',', '.'))
                            ->success()
                            ->send();
                    }),

                // 🚀 A MÁGICA DRY: Uma única linha resolve todo o modal!
                ReverseConciliationAction::configure(Action::make('conciliar_reverso')),

                EditAction::make(),
            ]);
    }
}
