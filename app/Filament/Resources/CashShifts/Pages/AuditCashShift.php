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

    public function criarVendaAvulsa(int $acquirerTxId): void
    {
        $acqTx = AcquirerTransaction::find($acquirerTxId);
        if (!$acqTx) return;

        /** @var AcquirerReconciliationEngine $engine */
        $engine = app(AcquirerReconciliationEngine::class);
        $engine->createRetroactiveOrder($acqTx, "Venda avulsa gerada na auditoria do Turno #{$this->record->id}");

        $this->recalcularTurno();

        Notification::make()
            ->title('Venda Avulsa Criada!')
            ->body("Pedido gerado e conciliado com a maquininha (NSU {$acqTx->nsu}).")
            ->success()
            ->send();
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

        // Identifica se há fita informada ou arquivo importado da adquirente
        $hasSettlementsDeclared = !empty($settlements);
        
        $shiftDate = $shift->opened_at ? $shift->opened_at->toDateString() : null;
        $hasAcquirerImported = $shiftDate 
            ? DB::table('acquirer_transactions')->whereDate('transaction_date', $shiftDate)->exists()
            : false;

        // Pagamentos do turno
        $payments = Payment::with(['order', 'paymentMethod', 'acquirerTransaction'])
            ->where('cash_shift_id', $shift->id)
            ->orderBy('id', 'asc')
            ->get();

        $orderIds = $payments->pluck('order_id')->filter()->unique();
        $paymentIds = $payments->pluck('id')->filter()->unique();

        // Transações de maquininha vinculadas
        $acqTransactions = DB::table('acquirer_transactions')
            ->where(function($q) use ($paymentIds, $orderIds) {
                $q->whereIn('payment_id', $paymentIds)
                  ->orWhereIn('order_id', $orderIds);
            })
            ->get()
            ->keyBy(fn($tx) => $tx->payment_id ?: "order_{$tx->order_id}");

        // CONCILIAÇÃO BANCÁRIA: Busca extratos bancários de crédito para checar Pix
        $bankStatements = collect();
        if ($shiftDate) {
            $bankStatements = DB::table('bank_statements')
                ->whereDate('created_at', $shiftDate)
                ->where(function($q) { $q->where('amount', '>', 0)->orWhere('movement_type', 'credit'); })
                ->get();
        }

        // Mapeia matches de Pix no Extrato
        $pixMatches = collect();
        foreach ($payments as $p) {
            $pName = mb_strtolower($p->method ?? '');
            $isPix = str_contains($pName, 'pix');

            if ($isPix && $bankStatements->isNotEmpty()) {
                $match = $bankStatements->first(function($stmt) use ($p) {
                    return abs((float)$stmt->amount - (float)$p->amount) < 0.02;
                });
                if ($match) {
                    $pixMatches->put($p->id, $match);
                }
            }
        }

        // Métodos de pagamento do sistema
        $availableMethods = PaymentMethod::all();
        $creditMethod = $availableMethods->first(fn($m) => str_contains(mb_strtolower($m->name), 'crédito') || str_contains(mb_strtolower($m->name), 'credito'));
        $debitMethod  = $availableMethods->first(fn($m) => str_contains(mb_strtolower($m->name), 'débito') || str_contains(mb_strtolower($m->name), 'debito'));
        $pixMaqMethod = $availableMethods->first(fn($m) => ($m->engine_type?->value ?? (string)$m->engine_type) === 'pix_machine');

        // Transações ÓRFÃS da maquininha no período do turno
        $closedAt = $shift->closed_at ?? now();
        $orphanAcq = AcquirerTransaction::query()
            ->whereBetween('transaction_date', [$shift->opened_at->copy()->subMinutes(30), $closedAt->copy()->addMinutes(30)])
            ->where('status', \App\Enums\AcquirerMatchStatus::ORPHAN)
            ->get();

        // Conflitos documentais
        $documentalConflicts = [];
        foreach ($payments as $p) {
            $acq = $p->acquirerTransaction ?? $acqTransactions->get($p->id) ?? $acqTransactions->get("order_{$p->order_id}");
            if (!$acq) continue;

            $statusVal = $acq->status instanceof \BackedEnum ? $acq->status->value : $acq->status;
            if ($statusVal !== 'matched') continue;

            $pMethodName = mb_strtolower($p->method ?? '');
            $acqMod = mb_strtolower($acq->modality ?? '');

            $isCash = str_contains($pMethodName, 'dinheiro') || str_contains($pMethodName, 'cash');
            $isPix = str_contains($pMethodName, 'chave') || (str_contains($pMethodName, 'pix') && !str_contains($pMethodName, 'máq') && !str_contains($pMethodName, 'maq'));

            $isConflict = false;
            $suggestedMethod = null;

            if ($isCash || $isPix) {
                $isConflict = true;
                if (str_contains($acqMod, 'créd') || str_contains($acqMod, 'cred')) $suggestedMethod = $creditMethod;
                elseif (str_contains($acqMod, 'déb') || str_contains($acqMod, 'deb')) $suggestedMethod = $debitMethod;
                elseif (str_contains($acqMod, 'pix')) $suggestedMethod = $pixMaqMethod;
            } elseif (str_contains($acqMod, 'créd') && str_contains($pMethodName, 'déb')) {
                $isConflict = true;
                $suggestedMethod = $creditMethod;
            } elseif (str_contains($acqMod, 'déb') && str_contains($pMethodName, 'créd')) {
                $isConflict = true;
                $suggestedMethod = $debitMethod;
            }

            if ($isConflict && $suggestedMethod && $suggestedMethod->id !== $p->payment_method_id) {
                $documentalConflicts[] = [
                    'payment_id'             => $p->id,
                    'order_id'               => $p->order_id,
                    'customer_name'          => $p->order?->customer_name ?? 'Consumidor',
                    'amount'                 => (float) $p->amount,
                    'current_method_name'    => $p->method,
                    'acquirer_proof'         => "{$acq->modality} {$acq->brand} (NSU: {$acq->nsu})",
                    'suggested_method_id'   => $suggestedMethod->id,
                    'suggested_method_name' => $suggestedMethod->name,
                ];
            }
        }

        return [
            'shift'                  => $shift,
            'diag'                   => $diag,
            'payments'               => $payments,
            'acqTransactions'        => $acqTransactions,
            'documentalConflicts'    => $documentalConflicts,
            'orphanAcq'              => $orphanAcq,
            'hasSettlementsDeclared' => $hasSettlementsDeclared,
            'hasAcquirerImported'    => $hasAcquirerImported,
            'pixMatches'             => $pixMatches,
            'availableMethods'       => $availableMethods,
            'creditMethod'           => $creditMethod,
            'debitMethod'            => $debitMethod,
        ];
    }
}