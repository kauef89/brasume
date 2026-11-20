<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\AcquirerMatchStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentEngineType;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\AcquirerTransaction;
use App\Models\BankStatement;
use App\Models\CashShift;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Accounting\PixBankReconciliationService;
use App\Services\Financial\Acquirers\AcquirerReconciliationEngine;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Histórico de Pedidos & Conciliação Documental';
    protected static string|\BackedEnum|null $icon = 'heroicon-o-shopping-bag';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    /**
     * Avalia o estado documental completo de um pedido do cliente.
     */
    public static function evaluateOrderAudit(Order $order): array
    {
        $payments = $order->payments
            ->where('is_paid', true)
            ->where('status', '!=', 'cancelled');

        if ($payments->isEmpty()) {
            return [
                'status'  => 'unpaid',
                'label'   => 'Sem Baixa no PDV',
                'color'   => 'danger',
                'details' => 'Pedido sem pagamento liquidado',
            ];
        }

        $unreconciledPayments = [];
        $details = [];

        foreach ($payments as $p) {
            $engine = $p->paymentMethod?->engine_type;
            $engineVal = $engine instanceof \BackedEnum ? $engine->value : (string) $engine;

            $needsCardProof = in_array($engineVal, [
                PaymentEngineType::POS_MANUAL->value,
                PaymentEngineType::SMART_POS->value,
                PaymentEngineType::TEF->value,
                PaymentEngineType::PIX_MACHINE->value,
            ], true);

            $needsBankProof = in_array($engineVal, [
                PaymentEngineType::PIX_STATIC->value,
                PaymentEngineType::BANK_API->value,
            ], true);

            if ($needsCardProof) {
                $acq = $p->acquirerTransaction
                    ?? AcquirerTransaction::where('payment_id', $p->id)
                        ->where('status', AcquirerMatchStatus::MATCHED->value)
                        ->first();

                if ($acq) {
                    $details[] = "✅ {$p->method}: NSU {$acq->nsu} (TID {$acq->terminal_code})";
                } else {
                    $unreconciledPayments[] = $p;
                    $details[] = "⚠️ {$p->method} R$ " . number_format((float) $p->amount, 2, ',', '.') . " sem filipeta";
                }
            } elseif ($needsBankProof) {
                $stmt = BankStatement::where('conciliable_type', Payment::class)
                    ->where('conciliable_id', $p->id)
                    ->where('status', 'reconciled')
                    ->first();

                if ($stmt) {
                    $payer = $stmt->counterparty_name ?: $stmt->description;
                    $details[] = "✅ Pix Extrato: {$payer}";
                } else {
                    $unreconciledPayments[] = $p;
                    $details[] = "⚠️ {$p->method} R$ " . number_format((float) $p->amount, 2, ',', '.') . " sem extrato";
                }
            } else {
                $details[] = "💵 {$p->method} (Conferência Interna)";
            }
        }

        if (!empty($unreconciledPayments)) {
            return [
                'status'       => 'pending_proof',
                'label'        => 'Sem Comprovante (' . count($unreconciledPayments) . ')',
                'color'        => 'warning',
                'details'      => implode(' | ', $details),
                'unreconciled' => $unreconciledPayments,
            ];
        }

        return [
            'status'  => 'conciliated',
            'label'   => 'Conciliado',
            'color'   => 'success',
            'details' => implode(' | ', $details),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'items.product',
                'payments.paymentMethod',
                'payments.acquirerTransaction',
            ]))
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Pedido #')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Modalidade')
                    ->badge(),

                Tables\Columns\TextColumn::make('items_summary')
                    ->label('Consumo')
                    ->state(function (Order $record): string {
                        $items = $record->items->take(2)->map(function ($i) {
                            $name = $i->product?->name ?? $i->name ?? 'Item';
                            return "{$i->quantity}x {$name}";
                        })->implode(', ');

                        $extra = $record->items->count() > 2 ? ' +' . ($record->items->count() - 2) : '';
                        return ($items ?: '—') . $extra;
                    })
                    ->wrap()
                    ->size('xs'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pdv_methods')
                    ->label('Forma no PDV')
                    ->state(function (Order $record): string {
                        $paid = $record->payments->where('is_paid', true)->where('status', '!=', 'cancelled');
                        if ($paid->isEmpty()) {
                            return 'Pendente / Sem Pagamento';
                        }
                        return $paid->map(fn ($p) => "{$p->method} (R$ " . number_format((float) $p->amount, 2, ',', '.') . ")")->implode(' + ');
                    })
                    ->size('xs'),

                Tables\Columns\TextColumn::make('audit_status')
                    ->label('Conciliação Documental')
                    ->badge()
                    ->state(fn (Order $record): string => static::evaluateOrderAudit($record)['label'])
                    ->color(fn (Order $record): string => static::evaluateOrderAudit($record)['color'])
                    ->description(fn (Order $record): string => static::evaluateOrderAudit($record)['details']),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status Pedido')
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('only_unreconciled')
                    ->label('Somente Sem Comprovante / Sem Baixa')
                    ->query(function (Builder $query): Builder {
                        return $query
                            ->whereNotIn('status', OrderStatus::cancelledValues())
                            ->where(function (Builder $q) {
                                $q->whereNotIn('payment_status', ['paid', 'settled_internal'])
                                  ->orWhereHas('payments', function (Builder $pq) {
                                      $pq->where('is_paid', true)
                                         ->whereNull('acquirer_transaction_id')
                                         ->whereHas('paymentMethod', fn ($mq) => $mq->whereIn('engine_type', [
                                             PaymentEngineType::POS_MANUAL->value,
                                             PaymentEngineType::SMART_POS->value,
                                             PaymentEngineType::TEF->value,
                                             PaymentEngineType::PIX_MACHINE->value,
                                             PaymentEngineType::PIX_STATIC->value,
                                             PaymentEngineType::BANK_API->value,
                                         ]));
                                  });
                            });
                    }),
            ])
            ->recordActions([
                // 🔗 CONCILIAR DIRETO COM TRANSAÇÃO ÓRFÃ (MAQUININHA OU EXTRATO PIX)
                Actions\Action::make('conciliar_pedido')
                    ->label('Conciliar')
                    ->icon('heroicon-m-link')
                    ->color('warning')
                    ->button()
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->visible(function (Order $record): bool {
                        $statusVal = $record->status instanceof \BackedEnum ? $record->status->value : (string) $record->status;
                        if (in_array($statusVal, OrderStatus::cancelledValues(), true)) {
                            return false;
                        }
                        return in_array(static::evaluateOrderAudit($record)['status'], ['pending_proof', 'unpaid'], true);
                    })
                    ->modalHeading(fn (Order $record) => "Conciliar Documentos - Pedido #{$record->id}")
                    ->modalDescription('Selecione o comprovante órfão da maquininha ou do extrato bancário correspondente a este pedido.')
                    ->modalSubmitActionLabel('Vincular e Conciliar')
                    ->form(function (Order $record) {
                        $audit = static::evaluateOrderAudit($record);
                        $targetPayment = $audit['unreconciled'][0] ?? $record->payments->where('is_paid', true)->first();
                        $targetAmount = $targetPayment ? (float) $targetPayment->amount : (float) $record->total;

                        $dateStart = ($record->created_at ?? now())->copy()->subHours(6);
                        $dateEnd   = ($record->delivered_at ?? $record->closed_at ?? $record->created_at ?? now())->copy()->addHours(12);

                        $options = [];
                        $descriptions = [];

                        // 1. Busca transações órfãs / ambíguas na Maquininha (POS/TEF/Pix Máquina)
                        $acqCandidates = AcquirerTransaction::with('device')
                            ->whereIn('status', [AcquirerMatchStatus::ORPHAN->value, AcquirerMatchStatus::AMBIGUOUS->value])
                            ->whereBetween('transaction_date', [$dateStart, $dateEnd])
                            ->orderByRaw('ABS(gross_amount - ?) ASC', [$targetAmount])
                            ->orderBy('transaction_date', 'asc')
                            ->limit(25)
                            ->get();

                        foreach ($acqCandidates as $tx) {
                            $key = "acquirer::{$tx->id}";
                            $isExact = abs((float) $tx->gross_amount - $targetAmount) < 0.02;
                            $badge = $isExact ? '🎯 VALOR EXATO' : '⚠️ VALOR DIVERGENTE';
                            $amtFmt = number_format((float) $tx->gross_amount, 2, ',', '.');
                            $timeFmt = $tx->transaction_date?->format('d/m H:i:s') ?? '--:--';
                            $devName = $tx->device?->name ?? $tx->terminal_code;

                            $options[$key] = "💳 Maquininha • R$ {$amtFmt} • {$tx->modality} {$tx->brand} ({$badge})";
                            $descriptions[$key] = new HtmlString(
                                "<span class='text-xs text-gray-600 dark:text-gray-300'>Horário: <strong>{$timeFmt}</strong> | NSU: <code>{$tx->nsu}</code> | Terminal: {$devName}</span>"
                            );
                        }

                        // 2. Busca créditos órfãos no Extrato Bancário (Chave Pix / API) usando bank_date
                        $bankDateStart = ($record->created_at ?? now())->copy()->subDays(1)->toDateString();
                        $bankDateEnd   = ($record->delivered_at ?? $record->closed_at ?? $record->created_at ?? now())->copy()->addDays(2)->toDateString();

                        $bankCandidates = BankStatement::query()
                            ->where('type', 'credit')
                            ->where('status', 'pending')
                            ->whereBetween('bank_date', [$bankDateStart, $bankDateEnd])
                            ->orderByRaw('ABS(amount - ?) ASC', [$targetAmount])
                            ->orderBy('bank_date', 'asc')
                            ->limit(25)
                            ->get();

                        foreach ($bankCandidates as $stmt) {
                            $key = "bank::{$stmt->id}";
                            $isExact = abs((float) $stmt->amount - $targetAmount) < 0.02;
                            $badge = $isExact ? '🎯 VALOR EXATO' : '⚠️ VALOR DIVERGENTE';
                            $amtFmt = number_format((float) $stmt->amount, 2, ',', '.');
                            $payer = e($stmt->counterparty_name ?: $stmt->description);
                            $doc = $stmt->document ? " • Doc/CPF: {$stmt->document}" : '';
                            $bankDateFmt = $stmt->bank_date?->format('d/m/Y') ?? '--/--/----';

                            $options[$key] = "💠 Extrato Bancário #{$stmt->id} • R$ {$amtFmt} • {$payer} ({$badge})";
                            $descriptions[$key] = new HtmlString(
                                "<span class='text-xs text-gray-600 dark:text-gray-300'>Data Extrato: <strong>{$bankDateFmt}</strong>{$doc}</span>"
                            );
                        }

                        if (empty($options)) {
                            return [
                                Placeholder::make('empty_notice')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        "<div class='p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-900 dark:text-amber-200'>
                                            Nenhum comprovante órfão (maquininha ou extrato bancário) foi encontrado na janela de <strong>{$dateStart->format('d/m H:i')}</strong> até <strong>{$dateEnd->format('d/m H:i')}</strong>.
                                            Verifique se o relatório CSV/OFX já foi importado ou se o lançamento ainda está vinculado a outro pedido.
                                        </div>"
                                    )),
                            ];
                        }

                        $pdvSummary = $targetPayment
                            ? "{$targetPayment->method} de R$ " . number_format((float) $targetPayment->amount, 2, ',', '.')
                            : "Pedido sem baixa (Total R$ " . number_format((float) $record->total, 2, ',', '.') . ")";

                        return [
                            Placeholder::make('order_context')
                                ->hiddenLabel()
                                ->content(new HtmlString(
                                    "<div class='p-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-xs'>
                                        <strong>Pedido #{$record->id}</strong> • Criado em {$record->created_at?->format('d/m/Y H:i')}
                                        " . ($record->delivered_at ? " • Entregue em {$record->delivered_at->format('H:i')}" : '') . "<br>
                                        <strong>Alvo no PDV:</strong> {$pdvSummary}
                                    </div>"
                                )),

                            Radio::make('candidate_key')
                                ->label('Comprovantes Órfãos Disponíveis:')
                                ->options($options)
                                ->descriptions($descriptions)
                                ->required(),
                        ];
                    })
                    ->action(function (array $data, Order $record): void {
                        if (empty($data['candidate_key'])) {
                            return;
                        }

                        [$source, $sourceId] = explode('::', $data['candidate_key']);
                        $sourceId = (int) $sourceId;
                        $operatorName = auth()->user()?->name ?? 'Gestor';
                        $recService = app(PixBankReconciliationService::class);

                        $audit = static::evaluateOrderAudit($record);
                        $targetPayment = $audit['unreconciled'][0] ?? $record->payments->where('is_paid', true)->first();

                        if ($targetPayment) {
                            $msg = $recService->linkCandidateToPayment($targetPayment, $source, $sourceId, $operatorName);
                        } else {
                            $shift = $record->cashShift
                                ?? CashShift::where('opened_at', '<=', $record->created_at)->orderByDesc('opened_at')->first()
                                ?? CashShift::latest('id')->first();

                            $msg = $recService->linkCandidateToOrder($shift, $record, $source, $sourceId, $operatorName);
                        }

                        Notification::make()
                            ->title("Pedido #{$record->id} Conciliado!")
                            ->body($msg)
                            ->success()
                            ->send();
                    }),

                // ↩️ DESFAZER VÍNCULO DOCUMENTAL DO PEDIDO (CASO TENHA SIDO ASSOCIADO ERRADO)
                Actions\Action::make('desfazer_conciliacao')
                    ->label('Desfazer Vínculo')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->outlined()
                    ->requiresConfirmation()
                    ->modalHeading(fn (Order $record) => "Desfazer Vínculo Documental - Pedido #{$record->id}")
                    ->modalDescription('A filipeta da maquininha ou extrato vinculado a este pedido voltará para Órfão/Pendente para que você possa associá-lo ao cliente correto.')
                    ->visible(function (Order $record): bool {
                        $paymentIds = $record->payments->pluck('id')->all();
                        if (empty($paymentIds)) return false;

                        $hasAcq = AcquirerTransaction::whereIn('payment_id', $paymentIds)
                            ->where('status', AcquirerMatchStatus::MATCHED->value)
                            ->exists();

                        $hasStmt = BankStatement::where('conciliable_type', Payment::class)
                            ->whereIn('conciliable_id', $paymentIds)
                            ->where('status', 'reconciled')
                            ->exists();

                        return $hasAcq || $hasStmt;
                    })
                    ->action(function (Order $record): void {
                        $paymentIds = $record->payments->pluck('id')->all();
                        $engine = app(AcquirerReconciliationEngine::class);

                        $acqs = AcquirerTransaction::whereIn('payment_id', $paymentIds)
                            ->where('status', AcquirerMatchStatus::MATCHED->value)
                            ->get();

                        foreach ($acqs as $acq) {
                            $engine->unlink($acq);
                        }

                        BankStatement::where('conciliable_type', Payment::class)
                            ->whereIn('conciliable_id', $paymentIds)
                            ->where('status', 'reconciled')
                            ->update([
                                'status'                => 'pending',
                                'conciliable_type'      => null,
                                'conciliable_id'        => null,
                                'reconciled_by_name'    => null,
                                'reconciled_at'         => null,
                            ]);

                        Notification::make()
                            ->title("Vínculo do Pedido #{$record->id} Desfeito!")
                            ->body('Os comprovantes voltaram para órfãos e já podem ser vinculados ao pedido correto.')
                            ->warning()
                            ->send();
                    }),

                Actions\Action::make('ver_pedido')
                    ->label('Ver Pedido')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }
}
