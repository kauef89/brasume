<?php

namespace App\Filament\Resources\CashShifts\Pages;

use App\Filament\Resources\CashShifts\CashShiftResource;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\AcquirerTransaction;
use App\Services\RestaurantOps\ShiftAuditService;
use App\Services\RestaurantOps\ShiftCashierService;
use App\Services\Financial\Acquirers\AcquirerReconciliationEngine;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditCashShift extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CashShiftResource::class;

    protected string $view = 'filament.resources.cash-shifts.pages.audit-cash-shift';

    public ?string $managerNotes = null;
    public ?float $sangriaAmount = null;
    public ?string $sangriaReason = null;
    public bool $showRetroactiveModal = false;
    public ?int $selectedAcqTxId = null;
    public ?float $retroactiveTargetAmount = 0.0;
    public ?string $retroactiveTxInfo = '';
    public array $suggestedCombinations = [];
    public array $manualItems = [];
    public ?int $selectedProductIdToAdd = null;
    public ?string $retroactiveCustomerName = null;
    public ?string $retroactiveNotes = null;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->managerNotes = $this->record->notes;
    }

    public function getTitle(): string
    {
        $dateStr = $this->record->opened_at
            ? mb_convert_case($this->record->opened_at->locale('pt_BR')->translatedFormat('D, d/m/Y'), MB_CASE_TITLE, 'UTF-8')
            : 'Data N/D';

        $period = $this->record->opened_at?->format('H:i') . ' às ' . ($this->record->closed_at?->format('H:i') ?? 'em aberto');

        return "Auditoria & Conciliação Documental - Turno #{$this->record->id} ({$dateStr} • {$period})";
    }

    public function getBreadcrumbs(): array
    {
        $dateStr = $this->record->opened_at
            ? $this->record->opened_at->format('d/m/Y')
            : "#{$this->record->id}";

        return [
            CashShiftResource::getUrl('index') => 'Turnos de Caixa',
            "#" => "Auditoria #{$this->record->id} ({$dateStr})",
        ];
    }

    public function saveNotes(): void
    {
        $this->record->update([
            'notes' => trim($this->managerNotes),
        ]);

        Notification::make()
            ->title('Parecer Salvo com Sucesso!')
            ->success()
            ->send();
    }

    public function retificarFormaPagamento(int $paymentId, int $novoMetodoId): void
    {
        $auditService = app(ShiftAuditService::class);
        $operatorName = auth()->user()?->name ?? 'Gestor Administrativo';

        $auditService->applyCorrection($this->record, $paymentId, $novoMetodoId, $operatorName);
        $this->recalcularTurno();

        Notification::make()
            ->title('Forma de Pagamento Retificada!')
            ->body('A modalidade foi corrigida e a quebra recalculada.')
            ->success()
            ->send();
    }

    public function abrirModalVendaAvulsa(int $acquirerTxId): void
    {
        $acqTx = AcquirerTransaction::find($acquirerTxId);
        if (!$acqTx) return;

        $this->selectedAcqTxId = $acqTx->id;
        $this->retroactiveTargetAmount = (float) ($acqTx->gross_amount ?: ($acqTx->amount ?? 0));
        $this->retroactiveTxInfo = "{$acqTx->modality} {$acqTx->brand} • NSU: {$acqTx->nsu} • TID: {$acqTx->terminal_code}";
        $this->retroactiveCustomerName = null;
        $this->retroactiveNotes = null;
        $this->manualItems = [];

        $matcher = app(\App\Services\Catalog\ProductCombinationMatcher::class);
        $this->suggestedCombinations = $matcher->findMatches($this->retroactiveTargetAmount);

        $this->showRetroactiveModal = true;
    }

    public function aplicarSugestao(int $index): void
    {
        if (isset($this->suggestedCombinations[$index])) {
            $this->manualItems = $this->suggestedCombinations[$index]['items'];
        }
    }

    public function adicionarProdutoManual(): void
    {
        if (!$this->selectedProductIdToAdd) return;
        $product = \App\Models\Product::find($this->selectedProductIdToAdd);
        if (!$product) return;

        $price = (float) ($product->dine_in_price ?: ($product->delivery_price ?: $product->promo_price ?: 0));

        foreach ($this->manualItems as &$item) {
            if ($item['product_id'] === $product->id) {
                $item['quantity']++;
                $this->selectedProductIdToAdd = null;
                return;
            }
        }

        $this->manualItems[] = [
            'product_id' => $product->id,
            'name'       => $product->name,
            'kitchen_id' => $product->kitchen_id,
            'quantity'   => 1,
            'unit_price' => $price,
        ];

        $this->selectedProductIdToAdd = null;
    }

    public function alterarQtdItem(int $index, int $delta): void
    {
        if (!isset($this->manualItems[$index])) return;
        $this->manualItems[$index]['quantity'] += $delta;
        if ($this->manualItems[$index]['quantity'] <= 0) {
            unset($this->manualItems[$index]);
            $this->manualItems = array_values($this->manualItems);
        }
    }

    public function confirmarVendaAvulsa(): void
    {
        if (!$this->selectedAcqTxId) return;
        $acqTx = AcquirerTransaction::find($this->selectedAcqTxId);
        if (!$acqTx) return;

        $action = app(\App\Services\Orders\Actions\CreateRetroactiveOrderAction::class);
        $order = $action->execute(
            $acqTx,
            $this->record,
            $this->manualItems,
            $this->retroactiveCustomerName,
            $this->retroactiveNotes
        );

        $this->showRetroactiveModal = false;
        $this->recalcularTurno();

        Notification::make()
            ->title('Venda Avulsa Criada e Conciliada!')
            ->body("Pedido #{$order->id} gerado com sucesso para a transação NSU {$acqTx->nsu}.")
            ->success()
            ->send();
    }

    public function criarVendaDiversaRapida(): void
    {
        $this->manualItems = [];
        $this->confirmarVendaAvulsa();
    }

    public function ignorarTransacaoMaquininha(int $acquirerTxId): void
    {
        $acqTx = AcquirerTransaction::find($acquirerTxId);
        if (!$acqTx) return;

        $acqTx->update([
            'status' => \App\Enums\AcquirerMatchStatus::IGNORED,
            'notes'  => 'Ignorado na auditoria pelo gestor ' . (auth()->user()?->name ?? 'Administrador'),
        ]);

        $this->recalcularTurno();

        Notification::make()
            ->title('Transação Desconsiderada')
            ->body("A transação NSU {$acqTx->nsu} foi marcada como ignorada.")
            ->warning()
            ->send();
    }

    public function registrarSangriaEsquecida(): void
    {
        if (!$this->sangriaAmount || $this->sangriaAmount <= 0) {
            Notification::make()->title('Informe um valor válido')->danger()->send();
            return;
        }

        $cashierService = app(ShiftCashierService::class);
        $operatorName = auth()->user()?->name ?? 'Gestor';

        $cashierService->recordTransaction([
            'type'        => 'out',
            'amount'      => $this->sangriaAmount,
            'description' => $this->sangriaReason ?: 'Ajuste de Auditoria - Saída não registrada no caixa',
        ], $operatorName, $this->record->bank_account_id, $this->record->id);

        $this->sangriaAmount = null;
        $this->sangriaReason = null;

        $this->recalcularTurno();

        Notification::make()
            ->title('Sangria Registrada com Sucesso!')
            ->body('O saldo esperado do cofre foi recalculado.')
            ->success()
            ->send();
    }

    public function vincularPedidoExistente(int $acquirerTxId, int $orderId): void
    {
        $acqTx = AcquirerTransaction::find($acquirerTxId);
        $order = \App\Models\Order::find($orderId);
        if (!$acqTx || !$order) return;

        DB::transaction(function () use ($acqTx, $order) {
            if ($order->status === 'cancelled') {
                $order->update([
                    'status'         => 'closed',
                    'payment_status' => 'paid',
                    'cash_shift_id'  => $this->record->id
                ]);
                $order->items()->update(['status' => 'completed']);
            }

            $payment = $order->payments()->first();
            if (!$payment) {
                $method = PaymentMethod::where('engine_type', 'pix_machine')->first() ?? PaymentMethod::first();
                $payment = $order->payments()->create([
                    'cash_shift_id'     => $this->record->id,
                    'payment_method_id' => $method?->id,
                    'method'            => $method?->name ?? 'Pix Máq',
                    'amount'            => $acqTx->amount,
                    'is_paid'           => true,
                ]);
            }

            $acqTx->update([
                'order_id'   => $order->id,
                'payment_id' => $payment->id,
                'status'     => \App\Enums\AcquirerMatchStatus::MATCHED,
                'notes'      => "Conciliado manualmente com Pedido #{$order->id} na auditoria."
            ]);
        });

        $this->recalcularTurno();

        Notification::make()
            ->title('Pedido Vinculado com Sucesso!')
            ->body("A transação da maquininha foi associada ao Pedido #{$order->id}.")
            ->success()
            ->send();
    }

    public function removerPagamentoDuplicado(int $paymentId): void
    {
        $payment = Payment::with('order')->findOrFail($paymentId);
        $order = $payment->order;

        DB::transaction(function () use ($payment, $order) {
            \App\Models\Transaction::where('payment_id', $payment->id)->delete();
            $payment->delete();

            if ($order) {
                $remainingPaid = (float) $order->payments()->sum('amount');
                $orderTotal = (float) $order->total;
                $order->update([
                    'payment_status' => $remainingPaid >= ($orderTotal - 0.05) ? 'paid' : ($remainingPaid > 0 ? 'partial' : 'pending')
                ]);
            }
        });

        $this->recalcularTurno();

        Notification::make()
            ->title('Pagamento Duplicado Removido!')
            ->body('O lançamento excedente foi estornado e a auditoria recalculada.')
            ->success()
            ->send();
    }

    protected function recalcularTurno(): void
    {
        $this->record->refresh();
        $auditService = app(ShiftAuditService::class);

        $cashIn = DB::table('payments')
            ->leftJoin('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->where('payments.cash_shift_id', $this->record->id)
            ->where(function($q) {
                $q->where('payment_methods.engine_type', 'money')
                  ->orWhere(function($sub) {
                      $sub->whereNull('payments.payment_method_id')
                          ->where('payments.method', 'like', '%dinheiro%');
                  });
            })
            ->sum('payments.amount');

        $suprimentos = DB::table('transactions')
            ->where('cash_shift_id', $this->record->id)
            ->where('type', 'in')
            ->sum('amount');

        $sangrias = DB::table('transactions')
            ->where('cash_shift_id', $this->record->id)
            ->where('type', 'out')
            ->sum('amount');

        $this->record->closing_balance_expected = (float)$this->record->opening_balance + (float)$cashIn + (float)$suprimentos - (float)$sangrias;

        $actualCash = (float) $this->record->closing_balance_actual;
        $settlements = $this->record->terminal_settlements ?? [];

        $diag = $auditService->diagnose($this->record, $actualCash, $settlements);

        $this->record->apos_breakage_amount = $diag['electronic_difference'];
        $this->record->has_breakage = abs($diag['cash_difference']) >= 0.05 || abs($diag['electronic_difference']) >= 0.05;
        $this->record->save();
    }

    protected function getViewData(): array
    {
        $shift = $this->record;
        $auditService = app(ShiftAuditService::class);
        $actualCash = (float) $shift->closing_balance_actual;
        $settlements = $shift->terminal_settlements ?? [];

        $diag = $auditService->diagnose($shift, $actualCash, $settlements);

        $hasSettlementsDeclared = !empty($settlements);
        $shiftDate = $shift->opened_at ? $shift->opened_at->toDateString() : null;
        $nextDate = $shiftDate ? Carbon::parse($shiftDate)->addDay()->toDateString() : null;

        $hasAcquirerImported = $shiftDate 
            ? DB::table('acquirer_transactions')->whereBetween('transaction_date', [$shiftDate, $nextDate])->exists()
            : false;

        $payments = Payment::with(['order', 'paymentMethod', 'acquirerTransaction'])
            ->where('cash_shift_id', $shift->id)
            ->orderBy('id', 'asc')
            ->get();

        $orderIds = $payments->pluck('order_id')->filter()->unique();
        $paymentIds = $payments->pluck('id')->filter()->unique();

        $acqTransactions = DB::table('acquirer_transactions')
            ->where(function($q) use ($paymentIds, $orderIds) {
                $q->whereIn('payment_id', $paymentIds)
                  ->orWhereIn('order_id', $orderIds);
            })
            ->get()
            ->keyBy(fn($tx) => $tx->payment_id ?: "order_{$tx->order_id}");

        // 🚀 CONCILIAÇÃO BANCÁRIA INTELIGENTE: Consulta bank_date e created_at no turno noturno
        $bankStatements = collect();
        if ($shiftDate) {
            $bankStatements = DB::table('bank_statements')
                ->where(function($q) use ($shiftDate, $nextDate) {
                    $q->whereBetween('bank_date', [$shiftDate, $nextDate])
                      ->orWhereBetween(DB::raw('DATE(created_at)'), [$shiftDate, $nextDate]);
                })
                ->where(function($q) {
                    $q->where('amount', '>', 0)
                      ->orWhere('movement_type', 'credit');
                })
                ->whereNull('deleted_at')
                ->get();
        }

        // 🚀 MAPEAMENTO 1x1 NO EXTRATO (Sem consumir o mesmo Pix duas vezes)
        $pixMatches = collect();
        $usedStatementIds = [];

        // 🚀 CÁLCULO RESTRITO DE PIX DIRETO (CHAVE / API)
        $directPixPayments = $payments->filter(function($p) {
            $engine = $p->paymentMethod?->engine_type;
            $engineVal = $engine instanceof \BackedEnum ? $engine->value : (string) $engine;
            $name = mb_strtolower($p->method ?? '');
            
            return in_array($engineVal, ['pix_static', 'bank_api']) 
                || str_contains($name, 'chave') 
                || (str_contains($name, 'pix') && !str_contains($name, 'máq') && !str_contains($name, 'maq'));
        });

        $pixSystemTotal = (float) $directPixPayments->sum('amount');
        $pixMatches = collect();
        $usedStatementIds = [];

        if ($bankStatements->isNotEmpty()) {
            foreach ($directPixPayments as $p) {
                $match = $bankStatements->first(function($stmt) use ($p, $usedStatementIds) {
                    if (in_array($stmt->id, $usedStatementIds)) return false;
                    return abs((float)$stmt->amount - (float)$p->amount) < 0.02;
                });

                if ($match) {
                    $usedStatementIds[] = $match->id;
                    $pixMatches->put($p->id, $match);
                }
            }
        }

        $pixStatementTotal = (float) $bankStatements->whereIn('id', $usedStatementIds)->sum('amount');
        $pixDiff = round($pixStatementTotal - $pixSystemTotal, 2);

        $availableMethods = PaymentMethod::all();
        $creditMethod = $availableMethods->first(fn($m) => str_contains(mb_strtolower($m->name), 'crédito') || str_contains(mb_strtolower($m->name), 'credito'));
        $debitMethod  = $availableMethods->first(fn($m) => str_contains(mb_strtolower($m->name), 'débito') || str_contains(mb_strtolower($m->name), 'debito'));
        $pixMaqMethod = $availableMethods->first(fn($m) => ($m->engine_type?->value ?? (string)$m->engine_type) === 'pix_machine');
        $chavePixMethod = $availableMethods->first(fn($m) => ($m->engine_type?->value ?? (string)$m->engine_type) === 'pix_static' || str_contains(mb_strtolower($m->name), 'chave'));

        $closedAt = $shift->closed_at ?? now();
        $orphanAcq = AcquirerTransaction::query()
            ->whereBetween('transaction_date', [$shift->opened_at->copy()->subMinutes(30), $closedAt->copy()->addMinutes(30)])
            ->where('status', \App\Enums\AcquirerMatchStatus::ORPHAN)
            ->get();

        // 🚀 CONFLITOS DOCUMENTAIS & SUGESTÕES AUTOMÁTICAS
        $documentalConflicts = [];
        $paymentCountByOrder = $payments->groupBy('order_id');

        foreach ($payments as $p) {
            $acq = $p->acquirerTransaction ?? $acqTransactions->get($p->id) ?? $acqTransactions->get("order_{$p->order_id}");
            $pMethodName = mb_strtolower($p->method ?? '');
            $isPixMaq = str_contains($pMethodName, 'máq') || str_contains($pMethodName, 'maq') || $p->paymentMethod?->engine_type === \App\Enums\PaymentEngineType::PIX_MACHINE;

            // 1. Caso Paola: Marcado como Pix Máq, não está na máquina, mas consta no Extrato Bancário
            if (!$acq && $isPixMaq && $pixMatches->has($p->id)) {
                $stmt = $pixMatches->get($p->id);
                $documentalConflicts[$p->id] = [
                    'payment_id'            => $p->id,
                    'order_id'              => $p->order_id,
                    'customer_name'         => $p->order?->customer_name ?? 'Consumidor',
                    'amount'                => (float) $p->amount,
                    'current_method_name'   => $p->method,
                    'acquirer_proof'        => "Não consta na maquininha • Localizado no Extrato Bancário (Titular: {$stmt->description})",
                    'suggested_method_id'   => $chavePixMethod?->id,
                    'suggested_method_name' => $chavePixMethod?->name ?? 'Chave Pix',
                ];
                continue;
            }

            // 2. Transações de maquininha com divergência de modalidade (ex: Crédito lançado como Débito)
            if ($acq) {
                $statusVal = $acq->status instanceof \BackedEnum ? $acq->status->value : $acq->status;
                if ($statusVal === 'matched') {
                    $acqMod = mb_strtolower($acq->modality ?? '');
                    $isConflict = false;
                    $suggestedMethod = null;

                    if (str_contains($acqMod, 'créd') && str_contains($pMethodName, 'déb')) {
                        $isConflict = true;
                        $suggestedMethod = $creditMethod;
                    } elseif (str_contains($acqMod, 'déb') && str_contains($pMethodName, 'créd')) {
                        $isConflict = true;
                        $suggestedMethod = $debitMethod;
                    }

                    if ($isConflict && $suggestedMethod && $suggestedMethod->id !== $p->payment_method_id) {
                        $documentalConflicts[$p->id] = [
                            'payment_id'            => $p->id,
                            'order_id'              => $p->order_id,
                            'customer_name'         => $p->order?->customer_name ?? 'Consumidor',
                            'amount'                => (float) $p->amount,
                            'current_method_name'   => $p->method,
                            'acquirer_proof'        => "{$acq->modality} {$acq->brand} (NSU: {$acq->nsu})",
                            'suggested_method_id'   => $suggestedMethod->id,
                            'suggested_method_name' => $suggestedMethod->name,
                        ];
                    }
                }
            }
        }

        // Busca pedidos do turno cancelados que batem com transações órfãs (ex: Diego #2282)
        $potentialOrphanMatches = collect();
        if ($orphanAcq->isNotEmpty()) {
            $orphanAmounts = $orphanAcq->pluck('amount')->unique();
            $potentialOrders = \App\Models\Order::where('cash_shift_id', $shift->id)
                ->whereIn('total', $orphanAmounts)
                ->get()
                ->keyBy(fn($o) => (string) number_format((float) $o->total, 2));

            foreach ($orphanAcq as $tx) {
                $key = (string) number_format((float) $tx->amount, 2);
                if ($potentialOrders->has($key)) {
                    $potentialOrphanMatches->put($tx->id, $potentialOrders->get($key));
                }
            }
        }

        return [
            'shift'                  => $shift,
            'diag'                   => $diag,
            'payments'               => $payments,
            'acqTransactions'        => $acqTransactions,
            'documentalConflicts'    => $documentalConflicts,
            'orphanAcq'              => $orphanAcq,
            'potentialOrphanMatches' => $potentialOrphanMatches,
            'hasSettlementsDeclared' => $hasSettlementsDeclared,
            'hasAcquirerImported'    => $hasAcquirerImported,
            'bankStatements'         => $bankStatements,
            'pixMatches'             => $pixMatches,
            'pixSystemTotal'         => $pixSystemTotal,
            'pixStatementTotal'      => $pixStatementTotal,
            'pixDiff'                => $pixDiff,
            'directPixPayments'      => $directPixPayments,
            'availableMethods'       => $availableMethods,
            'creditMethod'           => $creditMethod,
            'debitMethod'            => $debitMethod,
            'chavePixMethod'         => $chavePixMethod,
            'pixMaqMethod'           => $pixMaqMethod,
            'paymentCountByOrder'    => $paymentCountByOrder,
        ];
    }
}
