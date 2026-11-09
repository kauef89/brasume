<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\EmployeeLedger;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Infolists\Components\Grid as InfoGrid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;

class LedgersRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgers';
    protected static ?string $title = 'Conta Corrente (Extrato)';
    protected static ?string $modelLabel = 'Lançamento';
    protected static ?string $pluralModelLabel = 'Extrato Financeiro';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit' => 'Serviço Prestado (+)',
                        'debit'  => 'Pagamento / Desconto (-)',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit'  => 'danger',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable(),

                Tables\Columns\TextColumn::make('order_id')
                    ->label('Pedido Vinculado')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->color(fn ($record) => $record->type === 'credit' ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo Atualizado')
                    ->money('BRL')
                    ->weight('bold')
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'primary'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Lançamento')
                    ->options([
                        'credit' => 'Serviço Prestado / Diária (+)',
                        'debit'  => 'Pagamento / Vale / Consumo (-)',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('De'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),
            ])
            ->headerActions([
                Action::make('add_credit')
                    ->label('Lançar Serviço / Crédito')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('service_type')
                            ->label('Tipo de Serviço')
                            ->options([
                                'diaria' => 'Diária Padrão',
                                'outro'  => 'Outro Serviço / Avulso',
                            ])
                            ->live()
                            ->default('diaria')
                            ->afterStateUpdated(function ($get, $set, $livewire) {
                                if ($get('service_type') === 'diaria') {
                                    $employee = $livewire->getOwnerRecord();
                                    $set('amount', $employee->base_remuneration ?? 0);
                                    $set('description', 'Diária de Trabalho');
                                } else {
                                    $set('amount', null);
                                    $set('description', null);
                                }
                            }),

                        Forms\Components\TextInput::make('description')
                            ->label('Descrição')
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor a Pagar (R$)')
                            ->numeric()
                            ->required()
                            ->prefix('R$'),
                    ])
                    ->action(function (array $data, $livewire) {
                        $employee = $livewire->getOwnerRecord();
                        $latestLedger = $employee->ledgers()->latest('id')->first();
                        $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;

                        $employee->ledgers()->create([
                            'type'        => 'credit',
                            'description' => $data['description'],
                            'amount'      => $data['amount'],
                            'balance'     => $currentBalance + (float) $data['amount'],
                        ]);
                    }),

                Action::make('add_debit')
                    ->label('Lançar Vale / Adiantamento')
                    ->icon('heroicon-m-minus-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('description')
                            ->label('Descrição')
                            ->default('Vale / Adiantamento Manual')
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor (R$)')
                            ->numeric()
                            ->required()
                            ->prefix('R$'),
                    ])
                    ->action(function (array $data, $livewire) {
                        $employee = $livewire->getOwnerRecord();
                        $latestLedger = $employee->ledgers()->latest('id')->first();
                        $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;

                        $employee->ledgers()->create([
                            'type'        => 'debit',
                            'description' => $data['description'],
                            'amount'      => $data['amount'],
                            'balance'     => $currentBalance - (float) $data['amount'],
                        ]);
                    }),
            ])
            ->actions([
                Action::make('view_order')
                    ->label('Ver Pedido')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->visible(fn (EmployeeLedger $record) => !empty($record->order_id))
                    ->modalHeading(fn (EmployeeLedger $record) => "Auditoria do Pedido #{$record->order_id}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->infolist([
                        InfoGrid::make(3)->schema([
                            TextEntry::make('order.created_at')->label('Data/Hora')->dateTime('d/m/Y H:i'),
                            TextEntry::make('order.customer_name')->label('Identificação no Turno'),
                            TextEntry::make('order.total')->label('Valor Total')->money('BRL')->weight('bold'),
                        ]),
                        RepeatableEntry::make('order.items')
                            ->label('Itens do Lanche / Consumo')
                            ->schema([
                                TextEntry::make('product_name')->label('Produto')->weight('bold'),
                                TextEntry::make('quantity')->label('Qtd.'),
                                TextEntry::make('unit_price')->label('Preço Unitário')->money('BRL'),
                                TextEntry::make('total_price')->label('Total Item')->money('BRL')->weight('bold'),
                                TextEntry::make('notes')->label('Observação')->placeholder('-'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}