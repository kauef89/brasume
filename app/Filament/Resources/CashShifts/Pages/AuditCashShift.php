<?php

namespace App\Filament\Resources\CashShifts\Pages;

use App\Filament\Resources\CashShifts\CashShiftResource;
use App\Models\AcquirerTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Accounting\PixBankReconciliationService;
use App\Services\RestaurantOps\ShiftAuditService;
use App\Services\RestaurantOps\ShiftCashierService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
    public bool $showCandidateModal = false;
    public ?string $candidateTargetType = null;
    public ?int $candidateTargetId = null;
    public array $candidateTargetInfo = [];
    public array $candidateTransactions = [];
    public bool $showAllWindowCandidates = false;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->managerNotes = $this->record->notes;

        if (($this->record->status instanceof \BackedEnum ? $this->record->status->value : $this->record->status) !== 'open') {
            $diag = app(ShiftAuditService::class)->diagnose(
                $this->record,
                (float) ($this->record->closing_balance_actual ?? 0),
                $this->record->terminal_settlements ?? []
            );

            $electronicDiff = round((float) ($diag['electronic_difference'] ?? 0), 2);
            $cashDiff = round((float) ($diag['cash_difference'] ?? 0), 2);
            $hasBreakage = abs($cashDiff) >= 0.05 || abs($electronicDiff) >= 0.05;

            if (
                abs((float) ($this->record->apos_breakage_amount ?? 0) - $electronicDiff) >= 0.01
                || (bool) $this->record->has_breakage !== $hasBreakage
            ) {
                $this->record->updateQuietly([
                    'apos_breakage_amount' => $electronicDiff,
                    'has_breakage'         => $hasBreakage,
                ]);
            }
        }
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
        $this->record->update(['notes' => trim((string) $this->managerNotes)]);

        Notification::make()->title('Parecer Salvo com Sucesso!')->success()->send();
    }

    public function homologarAuditoria(): void
    {
        $shift = $this->record;
        $diag = app(ShiftAuditService::class)->diagnose($shift, (float) $shift->closing_balance_actual, $shift->terminal_settlements ?? []);
        $hasRealBreakage = abs($diag['cash_difference']) >= 0.05 || abs($diag['electronic_difference']) >= 0.05;

        if ($hasRealBreakage && empty(trim((string) $this->managerNotes))) {
            Notification::make()
                ->title('Parecer Obrigatório')
                ->body('Existe uma divergência não zerada (R$ ' . number_format($diag['electronic_difference'], 2, ',', '.') . '). Registre a justificativa no Parecer do Gestor antes de homologar.')
                ->warning()
                ->send();
            return;
        }

        if (!empty($this->managerNotes) && $this->managerNotes !== $shift->notes) {
            $shift->update(['notes' => trim($this->managerNotes)]);
        }

        $newStatus = $hasRealBreakage ? \App\Enums\ShiftStatus::HOMOLOGATED : \App\Enums\ShiftStatus::RECONCILED;
        $operatorName = auth()->user()?->name ?? 'Gestor Administrativo';

        $shift->update([
            'status'               => $newStatus,
            'apos_breakage_amount' => $diag['electronic_difference'],
            'has_breakage'         => $hasRealBreakage,
        ]);

        Notification::make()
            ->title("Turno #{$shift->id} Homologado!")
            ->body("A auditoria foi finalizada como '{$newStatus->getLabel()}' por {$operatorName}.")
            ->success()
            ->send();
    }

    public function retificarFormaPagamento(int $paymentId, int $novoMetodoId): void
    {
        $operatorName = auth()->user()?->name ?? 'Gestor Administrativo';
        app(ShiftAuditService::class)->applyCorrection($this->record, $paymentId, $novoMetodoId, $operatorName);
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

        $this->suggestedCombinations = app(\App\Services\Catalog\ProductCombinationMatcher::class)
            ->findMatches($this->retroactiveTargetAmount);

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

        $order = app(\App\Services\Orders\Actions\CreateRetroactiveOrderAction::class)->execute(
            $acqTx,
            $this->record,
            $this->manualItems,
            $this->retroactiveCustomerName,
            $this->retroactiveNotes
        );

        $this->showRetroactiveModal = false;
        $this->recalcularTurno();

        $fiscalDetail = match ($order->fiscal_status) {
            'authorized' => ' NFC-e autorizada na SEFAZ.',
            'skipped'    => ' Emissão fiscal dispensada pela forma de pagamento.',
            'pending'    => ' NFC-e pendente: item avulso sem produto/NCM vinculado.',
            'failed'     => " Falha na NFC-e: {$order->fiscal_error}",
            default      => '',
        };

        $notif = Notification::make()
            ->title('Venda Avulsa Criada e Conciliada!')
            ->body("Pedido #{$order->id} gerado para NSU {$acqTx->nsu}.{$fiscalDetail}");

        in_array($order->fiscal_status, ['pending', 'failed'], true) ? $notif->warning() : $notif->success();
        $notif->send();
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
            'status'                => \App\Enums\AcquirerMatchStatus::IGNORED,
            'reconciliation_source' => 'manual',
            'reconciled_by_name'    => auth()->user()?->name ?? 'Administrador',
            'reconciled_at'         => now(),
            'notes'                 => 'Ignorado na auditoria pelo gestor ' . (auth()->user()?->name ?? 'Administrador'),
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

        $operatorName = auth()->user()?->name ?? 'Gestor';
        app(ShiftCashierService::class)->recordTransaction([
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

    public function abrirModalCandidatosPagamento(int $paymentId): void
    {
        $payment = Payment::with('order')->find($paymentId);
        if (!$payment) return;

        $this->candidateTargetType = 'payment';
        $this->candidateTargetId = $payment->id;
        $this->showAllWindowCandidates = false;
        $this->candidateTargetInfo = [
            'order_id'      => $payment->order_id,
            'customer_id'   => $payment->order?->customer_id,
            'customer_name' => $payment->order?->customer_name ?? 'Consumidor Final',
            'channel'       => $payment->order?->type ?? 'counter',
            'method_name'   => $payment->method,
            'amount'        => (float) $payment->amount,
            'time'          => $payment->created_at?->format('d/m H:i') ?? '--:--',
        ];

        $this->carregarCandidatosParaAlvo();
        $this->showCandidateModal = true;
    }

    public function abrirModalCandidatosPedido(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order) return;

        $this->candidateTargetType = 'order';
        $this->candidateTargetId = $order->id;
        $this->showAllWindowCandidates = false;
        $this->candidateTargetInfo = [
            'order_id'      => $order->id,
            'customer_id'   => $order->customer_id,
            'customer_name' => $order->customer_name ?? 'Consumidor Final',
            'channel'       => $order->type ?? 'counter',
            'method_name'   => 'Sem Pagamento',
            'amount'        => (float) $order->total,
            'time'          => $order->created_at?->format('d/m H:i') ?? '--:--',
        ];

        $this->carregarCandidatosParaAlvo();
        $this->showCandidateModal = true;
    }

    public function alternarFiltroCandidatos(): void
    {
        $this->showAllWindowCandidates = !$this->showAllWindowCandidates;
        $this->carregarCandidatosParaAlvo();
    }

    protected function carregarCandidatosParaAlvo(): void
    {
        $this->candidateTransactions = app(PixBankReconciliationService::class)->findCandidatesForTarget(
            $this->record,
            (float) ($this->candidateTargetInfo['amount'] ?? 0),
            $this->candidateTargetInfo['customer_id'] ?? null,
            (string) ($this->candidateTargetInfo['customer_name'] ?? ''),
            $this->showAllWindowCandidates
        );
    }

    public function vincularCandidatoAoAlvo(string $source, int $sourceId): void
    {
        if (!$this->candidateTargetId || !$this->candidateTargetType) return;

        $service = app(PixBankReconciliationService::class);
        $operatorName = auth()->user()?->name ?? 'Gestor';

        if ($this->candidateTargetType === 'payment') {
            $payment = Payment::with('order')->find($this->candidateTargetId);
            if (!$payment) return;
            $msg = $service->linkCandidateToPayment($payment, $source, $sourceId, $operatorName);
        } else {
            $order = Order::find($this->candidateTargetId);
            if (!$order) return;
            $msg = $service->linkCandidateToOrder($this->record, $order, $source, $sourceId, $operatorName);
        }

        $this->showCandidateModal = false;
        $this->recalcularTurno();

        Notification::make()
            ->title('Conciliação Realizada com Sucesso!')
            ->body($msg)
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
                    'payment_status' => $remainingPaid >= ($orderTotal - 0.05) ? 'paid' : ($remainingPaid > 0 ? 'partial' : 'pending'),
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
        $expectedCash = (float) app(ShiftCashierService::class)->calculateExpectedCash($this->record);
        $diag = app(ShiftAuditService::class)->diagnose(
            $this->record,
            (float) $this->record->closing_balance_actual,
            $this->record->terminal_settlements ?? []
        );

        $this->record->closing_balance_expected = $expectedCash;
        $this->record->apos_breakage_amount = $diag['electronic_difference'];
        $this->record->has_breakage = abs($diag['cash_difference']) >= 0.05 || abs($diag['electronic_difference']) >= 0.05;
        $this->record->save();
    }

    protected function getViewData(): array
    {
        $shift = $this->record;
        $settlements = $shift->terminal_settlements ?? [];
        $diag = app(ShiftAuditService::class)->diagnose($shift, (float) $shift->closing_balance_actual, $settlements);

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

        $rawAcqTransactions = DB::table('acquirer_transactions')
            ->where(function ($q) use ($paymentIds, $orderIds) {
                $q->whereIn('payment_id', $paymentIds)->orWhereIn('order_id', $orderIds);
            })
            ->get();

        $acqTransactions = $rawAcqTransactions->keyBy(fn ($tx) => $tx->payment_id ?: "order_{$tx->order_id}");
        $acqTransactionsByPayment = $rawAcqTransactions->filter(fn ($tx) => !empty($tx->payment_id))->groupBy('payment_id');

        $directPixPayments = $payments->filter(function ($p) {
            $engine = $p->paymentMethod?->engine_type;
            $engineVal = $engine instanceof \BackedEnum ? $engine->value : (string) $engine;
            $name = mb_strtolower($p->method ?? '');

            return in_array($engineVal, ['pix_static', 'bank_api'])
                || str_contains($name, 'chave')
                || (str_contains($name, 'pix') && !str_contains($name, 'máq') && !str_contains($name, 'maq'));
        });

        $pixData = app(PixBankReconciliationService::class)->matchShiftDirectPix($shift, $directPixPayments);
        $bankStatements = $pixData['bankStatements'];
        $pixMatches = $pixData['pixMatches'];

        $availableMethods = PaymentMethod::all();
        $creditMethod = $availableMethods->first(fn ($m) => str_contains(mb_strtolower($m->name), 'crédito') || str_contains(mb_strtolower($m->name), 'credito'));
        $debitMethod = $availableMethods->first(fn ($m) => str_contains(mb_strtolower($m->name), 'débito') || str_contains(mb_strtolower($m->name), 'debito'));
        $pixMaqMethod = $availableMethods->first(fn ($m) => ($m->engine_type?->value ?? (string) $m->engine_type) === 'pix_machine');
        $chavePixMethod = $availableMethods->first(fn ($m) => ($m->engine_type?->value ?? (string) $m->engine_type) === 'pix_static' || str_contains(mb_strtolower($m->name), 'chave'));

        $closedAt = $shift->closed_at ?? now();
        $orphanAcq = AcquirerTransaction::query()
            ->whereBetween('transaction_date', [$shift->opened_at->copy()->subMinutes(30), $closedAt->copy()->addMinutes(30)])
            ->where('status', \App\Enums\AcquirerMatchStatus::ORPHAN)
            ->get();

        $documentalConflicts = [];
        $paymentCountByOrder = $payments->groupBy('order_id');

        foreach ($payments as $p) {
            $acq = $p->acquirerTransaction ?? $acqTransactions->get($p->id) ?? $acqTransactions->get("order_{$p->order_id}");
            $pMethodName = mb_strtolower($p->method ?? '');
            $isPixMaq = str_contains($pMethodName, 'máq') || str_contains($pMethodName, 'maq') || $p->paymentMethod?->engine_type === \App\Enums\PaymentEngineType::PIX_MACHINE;

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

        $unpaidOrders = Order::with('items')
            ->where('cash_shift_id', $shift->id)
            ->where('total', '>', 1.00)
            ->whereDoesntHave('payments', fn ($q) => $q->where('is_paid', true)->where('amount', '>', 0))
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'shift'                    => $shift,
            'diag'                     => $diag,
            'payments'                 => $payments,
            'acqTransactions'          => $acqTransactions,
            'acqTransactionsByPayment' => $acqTransactionsByPayment,
            'documentalConflicts'      => $documentalConflicts,
            'orphanAcq'                => $orphanAcq,
            'unpaidOrders'             => $unpaidOrders,
            'hasSettlementsDeclared'   => $hasSettlementsDeclared,
            'hasAcquirerImported'      => $hasAcquirerImported,
            'bankStatements'           => $bankStatements,
            'pixMatches'               => $pixMatches,
            'pixSystemTotal'           => $pixData['pixSystemTotal'],
            'pixStatementTotal'        => $pixData['pixStatementTotal'],
            'pixDiff'                  => $pixData['pixDiff'],
            'directPixPayments'        => $directPixPayments,
            'availableMethods'         => $availableMethods,
            'creditMethod'             => $creditMethod,
            'debitMethod'              => $debitMethod,
            'chavePixMethod'           => $chavePixMethod,
            'pixMaqMethod'             => $pixMaqMethod,
            'paymentCountByOrder'      => $paymentCountByOrder,
        ];
    }
}