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
                \Filament\Tables\Filters\Filter::make('transaction_date_filter')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Vendas de'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('transaction_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('transaction_date', '<=', $date));
                    }),

                SelectFilter::make('status')
                    ->label('Status da Venda')
                    ->options(AcquirerMatchStatus::class),

                SelectFilter::make('terminal_code')
                    ->label('Maquininha')
                    ->options(fn () => AcquirerTransaction::distinct()->pluck('terminal_code', 'terminal_code')->toArray()),
            ])
            ->actions([
                // 🚀 AÇÃO 1: RESOLVER DÚVIDA / AMBIGUIDADE (COM COMPOSIÇÃO DE SPLIT PAYMENTS)
                Action::make('resolve_ambiguity')
                    ->label('Resolver Dúvida')
                    ->icon('heroicon-m-arrows-pointing-in')
                    ->color('warning')
                    ->button()
                    ->outlined()
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->visible(fn (AcquirerTransaction $r) => $r->status === AcquirerMatchStatus::AMBIGUOUS)
                    ->modalHeading('Resolver Conflito de Pedidos')
                    ->modalDescription('Compare os dados da filipeta com os pedidos candidatos encontrados no horário para confirmar a associação.')
                    ->modalSubmitActionLabel('Confirmar Associação')
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

                            $time = $order->created_at ? $order->created_at->format('H:i:s') : '--:--';
                            $customer = $order->customer_name ?: ($order->customer?->name ?? 'Consumidor Balcão');
                            $typeLabel = match ($order->type) {
                                'delivery' => '🛵 Delivery',
                                'table'    => "🍽️ Mesa {$order->table_number}",
                                default    => '🏪 Balcão',
                            };

                            // Desvio temporal
                            $diffSec = $order->created_at ? $record->transaction_date->diffInSeconds($order->created_at, false) : 0;
                            $diffMin = round(abs($diffSec) / 60);

                            if (abs($diffSec) < 60) {
                                $timeDiffText = "Mesmo minuto (" . abs($diffSec) . "s de diferença)";
                            } elseif ($diffSec > 0) {
                                $timeDiffText = "Pedido lançado {$diffMin} min antes da maquininha";
                            } else {
                                $timeDiffText = "Pedido lançado {$diffMin} min após a maquininha";
                            }

                            // Análise de Pagamentos Divididos (Split Payments)
                            $orderPayments = $order->payments ?? collect();
                            $hasSplit = $orderPayments->count() > 1;

                            $paymentsHtml = '';
                            if ($hasSplit) {
                                $pills = [];
                                foreach ($orderPayments as $op) {
                                    $isThisOne = $op->id === $payment->id;
                                    $opName = $op->method ?: ($op->paymentMethod?->name ?? 'Pagamento');
                                    $opVal = number_format($op->amount, 2, ',', '.');

                                    if ($isThisOne) {
                                        $pills[] = "<span class='fi-pill-active'>👉 <strong>R$ {$opVal}</strong> no {$opName} (Esta Filipeta)</span>";
                                    } else {
                                        $pills[] = "<span class='fi-pill-other'>R$ {$opVal} em {$opName}</span>";
                                    }
                                }

                                $totalFormatted = number_format($order->total, 2, ',', '.');
                                $paymentsHtml = "
                                    <div class='fi-split-box'>
                                        <div class='fi-split-header'>
                                            <span>💳 <strong>Divisão da Conta:</strong> Total R$ {$totalFormatted} em {$orderPayments->count()} pagamentos:</span>
                                        </div>
                                        <div class='fi-split-pills'>
                                            " . implode(' ', $pills) . "
                                        </div>
                                    </div>
                                ";
                            } else {
                                $pmName = $payment->method ?: ($payment->paymentMethod?->name ?? 'Não identificado');
                                $isCash = str_contains(mb_strtolower($pmName), 'dinheiro') || str_contains(mb_strtolower($pmName), 'cash');

                                $badge = $isCash
                                    ? "<span class='fi-badge-cash'>⚠️ Lançado como {$pmName} no PDV (Possível troca de tecla)</span>"
                                    : "<span class='fi-badge-card'>✅ Lançado como {$pmName}</span>";

                                $paymentsHtml = "<div class='fi-single-pay'><strong>Forma Registrada:</strong> {$badge}</div>";
                            }

                            // Resumo de Itens Consumidos
                            $itemsSummary = $order->items->take(3)->map(function ($i) {
                                $name = $i->product?->name
                                    ?? $i->product_name
                                    ?? $i->name
                                    ?? $i->title
                                    ?? ($i->product_id ? "Produto #{$i->product_id}" : 'Item');
                                return "{$i->quantity}x {$name}";
                            })->join(', ');

                            if ($order->items->count() > 3) {
                                $itemsSummary .= ' + ' . ($order->items->count() - 3) . ' item(ns)';
                            }
                            if (empty($itemsSummary)) {
                                $itemsSummary = 'Nenhum item discriminado';
                            }

                            // Título da Opção com indicação clara se é Parcela ou Total
                            $valTitle = $hasSplit
                                ? "Parcela: R$ " . number_format($payment->amount, 2, ',', '.') . " (Total Comanda: R$ " . number_format($order->total, 2, ',', '.') . ")"
                                : "Total: R$ " . number_format($order->total, 2, ',', '.');

                            $options[$key] = "Pedido #{$order->id} • {$typeLabel} • {$valTitle}";

                            $descriptions[$key] = new HtmlString("
                                <div class='fi-cand-box'>
                                    <div class='fi-cand-header'>
                                        <span><strong class='fi-cand-title'>Horário:</strong> {$time} <em class='fi-cand-muted'>({$timeDiffText})</em></span>
                                        <span class='fi-cand-score'>Confiança: {$score}%</span>
                                    </div>
                                    
                                    <div class='fi-cand-row'>
                                        <strong class='fi-cand-title'>Cliente:</strong> {$customer}
                                    </div>

                                    {$paymentsHtml}

                                    <div class='fi-cand-muted' style='margin-top: 6px;'>
                                        <strong class='fi-cand-sub'>Consumo:</strong> {$itemsSummary}
                                    </div>
                                </div>
                            ");
                        }

                        return [
                            Placeholder::make('tx_info')
                                ->hiddenLabel()
                                ->content(static::renderTransactionContextCard($record)),

                            Radio::make('selected_candidate')
                                ->label('Selecione a comanda correspondente:')
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
                    ->modalWidth('2xl')
                    ->visible(fn (AcquirerTransaction $r) => in_array($r->status, [AcquirerMatchStatus::ORPHAN, AcquirerMatchStatus::AMBIGUOUS]))
                    ->modalHeading('Vínculo Manual de Pedido')
                    ->modalDescription('Pesquise a comanda correspondente mantendo as informações da maquininha como referência.')
                    ->modalSubmitActionLabel('Associar Pedido')
                    ->form(fn (AcquirerTransaction $record) => [
                        Placeholder::make('tx_info')
                            ->hiddenLabel()
                            ->content(static::renderTransactionContextCard($record)),

                        Select::make('order_id')
                            ->label('Pedido no Sistema')
                            ->placeholder('Digite o número da comanda, mesa ou nome do cliente...')
                            ->searchable()
                            ->preload()
                            ->options(function () use ($record) {
                                return Order::query()
                                    ->whereDate('created_at', $record->transaction_date->toDateString())
                                    ->latest('id')
                                    ->limit(40)
                                    ->get()
                                    ->mapWithKeys(function (Order $o) {
                                        $splitText = $o->payments->count() > 1 ? " ({$o->payments->count()} pagamentos)" : "";
                                        return [
                                            $o->id => "#{$o->id} • " . ($o->customer_name ?: 'Balcão') . " • Total R$ " . number_format($o->total, 2, ',', '.') . "{$splitText} ({$o->created_at->format('H:i')})"
                                        ];
                                    })
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

    /**
     * Card HTML padronizado com CSS integrado a Light e Dark Mode.
     */
    public static function renderTransactionContextCard(AcquirerTransaction $record): HtmlString
    {
        $deviceName = $record->device?->name ?? 'Terminal não cadastrado';
        $tid = $record->terminal_code;
        $scope = match ($record->device?->assignment_scope) {
            'counter'  => 'Balcão / Caixa',
            'delivery' => 'Delivery',
            'waiter'   => 'Salão / Garçom',
            default    => 'Terminal Geral',
        };

        $gross = number_format($record->gross_amount, 2, ',', '.');
        $fee = number_format($record->fee_amount, 2, ',', '.');
        $net = number_format($record->net_amount, 2, ',', '.');
        $feePercent = $record->gross_amount > 0 ? number_format(($record->fee_amount / $record->gross_amount) * 100, 2, ',', '.') : '0,00';

        $dateFormatted = $record->transaction_date ? $record->transaction_date->format('d/m/Y') : '--/--/----';
        $timeFormatted = $record->transaction_date ? $record->transaction_date->format('H:i:s') : '--:--:--';
        $prevFormatted = $record->expected_settlement_date ? $record->expected_settlement_date->format('d/m/Y') : 'Não informada';

        $modalityBrand = strtoupper("{$record->modality} {$record->brand}");
        $statusLabel = method_exists($record->status, 'getLabel') ? $record->status->getLabel() : ($record->status->name ?? (string) $record->status);

        return new HtmlString("
            <style>
                .fi-acq-card {
                    background-color: #fffbeb;
                    border: 1px solid #fde68a;
                    border-radius: 1rem;
                    padding: 1rem;
                    margin-bottom: 1rem;
                    font-size: 0.75rem;
                }
                .fi-acq-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    padding-bottom: 0.625rem;
                    border-bottom: 1px solid #fef3c7;
                }
                .fi-acq-title {
                    font-size: 0.875rem;
                    font-weight: 800;
                    color: #92400e;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .fi-acq-tid {
                    background-color: #fef3c7;
                    color: #b45309;
                    padding: 0.125rem 0.375rem;
                    border-radius: 0.25rem;
                    font-family: monospace;
                    font-size: 0.6875rem;
                    font-weight: bold;
                }
                .fi-acq-scope {
                    font-size: 0.6875rem;
                    color: #b45309;
                    margin-top: 0.125rem;
                    font-weight: 500;
                }
                .fi-acq-status {
                    background-color: #fef08a;
                    color: #854d0e;
                    padding: 0.125rem 0.5rem;
                    border-radius: 0.5rem;
                    font-weight: 800;
                    font-size: 0.625rem;
                    text-transform: uppercase;
                }
                .fi-acq-metrics {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 0.5rem;
                    background-color: #ffffff;
                    padding: 0.75rem;
                    border-radius: 0.75rem;
                    border: 1px solid #fef3c7;
                    margin: 0.625rem 0;
                }
                .fi-acq-metric-label {
                    font-size: 0.625rem;
                    font-weight: bold;
                    color: #6b7280;
                    text-transform: uppercase;
                    display: block;
                }
                .fi-acq-metric-gross {
                    font-size: 1rem;
                    font-weight: 900;
                    color: #111827;
                }
                .fi-acq-metric-fee {
                    font-size: 0.8125rem;
                    font-weight: bold;
                    color: #dc2626;
                    margin-top: 0.125rem;
                }
                .fi-acq-metric-net {
                    font-size: 0.8125rem;
                    font-weight: bold;
                    color: #16a34a;
                    margin-top: 0.125rem;
                }
                .fi-acq-audit {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(105px, 1fr));
                    gap: 0.5rem;
                    font-size: 0.6875rem;
                    color: #4b5563;
                    padding-top: 0.25rem;
                }
                .fi-acq-strong {
                    color: #111827;
                }

                /* Candidatos */
                .fi-cand-box {
                    background-color: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 0.75rem;
                    padding: 0.75rem;
                    margin-top: 0.375rem;
                    font-size: 0.6875rem;
                    line-height: 1.4;
                    color: #334155;
                }
                .fi-cand-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.375rem;
                }
                .fi-cand-title {
                    color: #0f172a;
                }
                .fi-cand-muted {
                    color: #64748b;
                }
                .fi-cand-sub {
                    color: #334155;
                }
                .fi-cand-score {
                    background-color: #dcfce7;
                    color: #15803d;
                    border: 1px solid #86efac;
                    border-radius: 0.25rem;
                    padding: 0.0625rem 0.375rem;
                    font-size: 0.625rem;
                    font-weight: 800;
                }
                .fi-cand-row {
                    margin-bottom: 0.375rem;
                }
                .fi-single-pay {
                    margin-bottom: 0.375rem;
                    font-size: 0.6875rem;
                }
                .fi-badge-cash {
                    display: inline-block;
                    background-color: #fee2e2;
                    color: #991b1b;
                    border: 1px solid #f87171;
                    border-radius: 0.25rem;
                    padding: 0.0625rem 0.375rem;
                    font-size: 0.625rem;
                    font-weight: bold;
                }
                .fi-badge-card {
                    display: inline-block;
                    background-color: #dcfce7;
                    color: #166534;
                    border: 1px solid #86efac;
                    border-radius: 0.25rem;
                    padding: 0.0625rem 0.375rem;
                    font-size: 0.625rem;
                    font-weight: bold;
                }

                /* Split Payments Box */
                .fi-split-box {
                    background-color: #f1f5f9;
                    border: 1px solid #cbd5e1;
                    border-radius: 0.5rem;
                    padding: 0.5rem;
                    margin: 0.375rem 0;
                }
                .fi-split-header {
                    font-size: 0.6875rem;
                    color: #334155;
                    margin-bottom: 0.25rem;
                }
                .fi-split-pills {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.25rem;
                }
                .fi-pill-active {
                    background-color: #fef08a;
                    color: #854d0e;
                    border: 1px solid #eab308;
                    border-radius: 0.25rem;
                    padding: 0.0625rem 0.375rem;
                    font-size: 0.625rem;
                    font-weight: 800;
                }
                .fi-pill-other {
                    background-color: #ffffff;
                    color: #64748b;
                    border: 1px solid #cbd5e1;
                    border-radius: 0.25rem;
                    padding: 0.0625rem 0.375rem;
                    font-size: 0.625rem;
                }

                /* ======================================================== */
                /* 🌙 DARK MODE OVERRIDES */
                /* ======================================================== */
                .dark .fi-acq-card {
                    background-color: rgba(69, 26, 3, 0.3) !important;
                    border-color: rgba(180, 83, 9, 0.4) !important;
                }
                .dark .fi-acq-header {
                    border-bottom-color: rgba(180, 83, 9, 0.3) !important;
                }
                .dark .fi-acq-title {
                    color: #fcd34d !important;
                }
                .dark .fi-acq-tid {
                    background-color: rgba(180, 83, 9, 0.3) !important;
                    color: #fef08a !important;
                }
                .dark .fi-acq-scope {
                    color: #fde68a !important;
                }
                .dark .fi-acq-status {
                    background-color: rgba(120, 53, 15, 0.6) !important;
                    color: #fef08a !important;
                }
                .dark .fi-acq-metrics {
                    background-color: #111827 !important;
                    border-color: #374151 !important;
                }
                .dark .fi-acq-metric-label {
                    color: #9ca3af !important;
                }
                .dark .fi-acq-metric-gross {
                    color: #f9fafb !important;
                }
                .dark .fi-acq-metric-fee {
                    color: #f87171 !important;
                }
                .dark .fi-acq-metric-net {
                    color: #4ade80 !important;
                }
                .dark .fi-acq-audit {
                    color: #9ca3af !important;
                }
                .dark .fi-acq-strong {
                    color: #f3f4f6 !important;
                }

                .dark .fi-cand-box {
                    background-color: #1f2937 !important;
                    border-color: #374151 !important;
                    color: #d1d5db !important;
                }
                .dark .fi-cand-title {
                    color: #f9fafb !important;
                }
                .dark .fi-cand-muted {
                    color: #9ca3af !important;
                }
                .dark .fi-cand-sub {
                    color: #e5e7eb !important;
                }
                .dark .fi-cand-score {
                    background-color: rgba(6, 78, 59, 0.6) !important;
                    color: #86efac !important;
                    border-color: #059669 !important;
                }
                .dark .fi-badge-cash {
                    background-color: rgba(127, 29, 29, 0.5) !important;
                    color: #fca5a5 !important;
                    border-color: #dc2626 !important;
                }
                .dark .fi-badge-card {
                    background-color: rgba(6, 78, 59, 0.5) !important;
                    color: #86efac !important;
                    border-color: #10b981 !important;
                }

                .dark .fi-split-box {
                    background-color: #111827 !important;
                    border-color: #374151 !important;
                }
                .dark .fi-split-header {
                    color: #e5e7eb !important;
                }
                .dark .fi-pill-active {
                    background-color: rgba(161, 98, 7, 0.4) !important;
                    color: #fef08a !important;
                    border-color: #ca8a04 !important;
                }
                .dark .fi-pill-other {
                    background-color: #1f2937 !important;
                    color: #9ca3af !important;
                    border-color: #4b5563 !important;
                }
            </style>

            <div class='fi-acq-card'>
                <div class='fi-acq-header'>
                    <div>
                        <div class='fi-acq-title'>
                            <span>💳 {$deviceName}</span>
                            <span class='fi-acq-tid'>TID: {$tid}</span>
                        </div>
                        <div class='fi-acq-scope'>
                            Escopo: <strong>{$scope}</strong> &nbsp;|&nbsp; <strong>{$modalityBrand}</strong>
                        </div>
                    </div>
                    <div>
                        <span class='fi-acq-status'>
                            {$statusLabel}
                        </span>
                    </div>
                </div>

                <div class='fi-acq-metrics'>
                    <div>
                        <span class='fi-acq-metric-label'>Valor Cobrado</span>
                        <div class='fi-acq-metric-gross'>R$ {$gross}</div>
                    </div>
                    <div>
                        <span class='fi-acq-metric-label'>Taxa MDR ({$feePercent}%)</span>
                        <div class='fi-acq-metric-fee'>- R$ {$fee}</div>
                    </div>
                    <div>
                        <span class='fi-acq-metric-label'>Líquido Previsto</span>
                        <div class='fi-acq-metric-net'>R$ {$net}</div>
                    </div>
                </div>

                <div class='fi-acq-audit'>
                    <div><strong class='fi-acq-strong'>Data:</strong> {$dateFormatted}</div>
                    <div><strong class='fi-acq-strong'>Horário:</strong> {$timeFormatted}</div>
                    <div><strong class='fi-acq-strong'>NSU:</strong> <span style='font-family: monospace;'>{$record->nsu}</span></div>
                    <div><strong class='fi-acq-strong'>Aut:</strong> <span style='font-family: monospace;'>{$record->authorization_code}</span></div>
                    <div><strong class='fi-acq-strong'>Repasse:</strong> {$prevFormatted}</div>
                </div>
            </div>
        ");
    }
}