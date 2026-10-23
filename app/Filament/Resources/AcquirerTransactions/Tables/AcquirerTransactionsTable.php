<?php

namespace App\Filament\Resources\AcquirerTransactions\Tables;

use App\Enums\AcquirerMatchStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\AcquirerTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Financial\Acquirers\AcquirerReconciliationEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class AcquirerTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('terminal_code')
                    ->label('Maquininha')
                    ->searchable()
                    ->description(fn (AcquirerTransaction $r) => $r->device?->name ?? 'Não identificada')
                    ->badge()
                    ->color(fn (AcquirerTransaction $r) => match ($r->device?->assignment_scope) {
                        'counter'  => 'success',
                        'delivery' => 'warning',
                        'waiter'   => 'info',
                        default    => 'gray',
                    }),

                TextColumn::make('modality')
                    ->label('Modalidade / Bandeira')
                    ->formatStateUsing(fn (AcquirerTransaction $r) => strtoupper("{$r->modality} {$r->brand}"))
                    ->badge()
                    ->color('primary'),

                TextColumn::make('gross_amount')
                    ->label('Valor Bruto')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('fee_amount')
                    ->label('Taxa MDR')
                    ->money('BRL')
                    ->color('danger'),

                TextColumn::make('net_amount')
                    ->label('Valor Líquido')
                    ->money('BRL')
                    ->color('success')
                    ->description(fn (AcquirerTransaction $r) => $r->expected_settlement_date ? 'Prev: ' . $r->expected_settlement_date->format('d/m/Y') : null),

                TextColumn::make('nsu')
                    ->label('NSU')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('NSU copiado!')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('order_id')
                    ->label('Pedido')
                    ->formatStateUsing(fn (?int $state) => $state ? "#{$state}" : '-')
                    ->url(fn (AcquirerTransaction $r) => $r->order_id ? OrderResource::getUrl('edit', ['record' => $r->order_id]) : null)
                    ->openUrlInNewTab()
                    ->color('info')
                    ->weight('bold'),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status da Venda')
                    ->options(AcquirerMatchStatus::class),

                SelectFilter::make('terminal_code')
                    ->label('Maquininha')
                    ->options(fn () => AcquirerTransaction::distinct()->pluck('terminal_code', 'terminal_code')->toArray()),
            ])
            ->actions([
                // 🚀 AÇÃO 1: RESOLVER DÚVIDA / AMBIGUIDADE
                Action::make('resolve_ambiguity')
                    ->label('Resolver Dúvida')
                    ->icon('heroicon-m-arrows-pointing-in')
                    ->color('warning')
                    ->button()
                    ->outlined()
                    ->slideOver()
                    ->visible(fn (AcquirerTransaction $r) => $r->status === AcquirerMatchStatus::AMBIGUOUS)
                    ->modalHeading('Resolver Conflito de Pedidos')
                    ->modalDescription('Mais de um pedido foi encontrado com valor e horário semelhantes. Indique qual comanda corresponde a esta filipeta.')
                    ->modalSubmitActionLabel('Confirmar Vínculo')
                    ->form(function (AcquirerTransaction $record) {
                        $engine = new AcquirerReconciliationEngine();
                        $candidates = $engine->findCandidatesFor($record);

                        $options = [];
                        $descriptions = [];

                        foreach ($candidates as $cand) {
                            $order = $cand['order'];
                            $payment = $cand['payment'];
                            $score = $cand['score'];

                            $key = "{$order->id}::{$payment->id}";
                            $time = $order->created_at->format('H:i');
                            $customer = $order->customer_name ?: ($order->customer?->name ?? 'Consumidor Balcão');
                            $typeLabel = match ($order->type) {
                                'delivery' => '🛵 Delivery',
                                'table'    => "🍽️ Mesa {$order->table_number}",
                                default    => '🏪 Balcão',
                            };

                            $options[$key] = "Pedido #{$order->id} &mdash; {$typeLabel} &mdash; R$ " . number_format($payment->amount, 2, ',', '.');
                            $descriptions[$key] = new HtmlString("
                                <div style='font-size: 11px; line-height: 1.4; color: #6b7280;'>
                                    <strong>Horário:</strong> {$time} | <strong>Cliente:</strong> {$customer} |
                                    <span style='color: #059669; font-weight: bold;'>Confiança: {$score}%</span>
                                </div>
                            ");
                        }

                        return [
                            Placeholder::make('tx_info')
                                ->hiddenLabel()
                                ->content(new HtmlString("
                                    <div style='background: #fefce8; border: 1px solid #fef08a; padding: 12px; border-radius: 8px; margin-bottom: 12px;'>
                                        <strong>Transação da Maquininha:</strong> NSU {$record->nsu} | " . strtoupper("{$record->modality} {$record->brand}") . "<br>
                                        <strong>Valor Bruto:</strong> R$ " . number_format($record->gross_amount, 2, ',', '.') . " às {$record->transaction_date->format('H:i:s')}
                                    </div>
                                ")),

                            Radio::make('selected_candidate')
                                ->label('Selecione o pedido correspondente:')
                                ->options($options)
                                ->descriptions($descriptions)
                                ->required(),
                        ];
                    })
                    ->action(function (array $data, AcquirerTransaction $record) {
                        [$orderId, $paymentId] = explode('::', $data['selected_candidate']);
                        $order = Order::findOrFail($orderId);
                        $payment = Payment::find($paymentId);

                        $engine = new AcquirerReconciliationEngine();
                        $engine->linkManually($record, $order, $payment);

                        Notification::make()
                            ->title('Transação Vinculada com Sucesso!')
                            ->success()
                            ->send();
                    }),

                // 🚀 AÇÃO 2: VINCULAR MANUALMENTE
                Action::make('link_manual')
                    ->label('Vincular Pedido')
                    ->icon('heroicon-m-link')
                    ->color('primary')
                    ->slideOver()
                    ->visible(fn (AcquirerTransaction $r) => in_array($r->status, [AcquirerMatchStatus::ORPHAN, AcquirerMatchStatus::AMBIGUOUS]))
                    ->modalHeading('Vínculo Manual de Pedido')
                    ->modalDescription('Pesquise um pedido pendente ou com valor aproximado para associar a esta transação.')
                    ->modalSubmitActionLabel('Associar')
                    ->form(fn (AcquirerTransaction $record) => [
                        Select::make('order_id')
                            ->label('Pedido no Sistema')
                            ->placeholder('Digite o número do pedido ou nome do cliente...')
                            ->searchable()
                            ->preload()
                            ->options(function () use ($record) {
                                return Order::query()
                                    ->whereDate('created_at', $record->transaction_date->toDateString())
                                    ->latest('id')
                                    ->limit(40)
                                    ->get()
                                    ->mapWithKeys(fn (Order $o) => [
                                        $o->id => "#{$o->id} - " . ($o->customer_name ?: 'Balcão') . " - R$ " . number_format($o->total, 2, ',', '.') . " ({$o->created_at->format('H:i')})"
                                    ])
                                    ->toArray();
                            })
                            ->required(),
                    ])
                    ->action(function (array $data, AcquirerTransaction $record) {
                        $order = Order::findOrFail($data['order_id']);
                        $payment = $order->payments()->first();

                        $engine = new AcquirerReconciliationEngine();
                        $engine->linkManually($record, $order, $payment);

                        Notification::make()
                            ->title('Vínculo Manual Realizado!')
                            ->success()
                            ->send();
                    }),

                // 🚀 AÇÃO 3: LANÇAR VENDA AVULSA
                Action::make('create_retroactive')
                    ->label('Lançar Venda Avulsa')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AcquirerTransaction $r) => $r->status === AcquirerMatchStatus::ORPHAN)
                    ->modalHeading('Registrar Venda Avulsa Retroativa')
                    ->modalDescription('Essa ação criará um pedido de balcão concluído com o valor exato da maquininha, salvando o faturamento do turno e equilibrando a DRE.')
                    ->modalSubmitActionLabel('Criar e Conciliar')
                    ->form([
                        Textarea::make('observation')
                            ->label('Observação Interna')
                            ->placeholder('Ex: Venda rápida de balcão não registrada no momento do atendimento.'),
                    ])
                    ->action(function (array $data, AcquirerTransaction $record) {
                        $engine = new AcquirerReconciliationEngine();
                        $order = $engine->createRetroactiveOrder($record, $data['observation'] ?? null);

                        Notification::make()
                            ->title("Pedido #{$order->id} Criado e Conciliado!")
                            ->success()
                            ->send();
                    }),

                // 🚀 AÇÃO 4: IGNORAR TRANSAÇÃO
                Action::make('ignore')
                    ->label('Ignorar')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (AcquirerTransaction $r) => !in_array($r->status, [AcquirerMatchStatus::MATCHED, AcquirerMatchStatus::IGNORED]))
                    ->modalHeading('Ignorar Transação')
                    ->modalDescription('Use esta opção apenas para testes de maquininha ou operações que não devem ser contabilizadas no ERP.')
                    ->action(function (AcquirerTransaction $record) {
                        $engine = new AcquirerReconciliationEngine();
                        $engine->ignore($record);

                        Notification::make()
                            ->title('Transação marcada como ignorada.')
                            ->info()
                            ->send();
                    }),
            ]);
    }
}
