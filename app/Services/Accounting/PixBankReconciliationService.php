<?php

namespace App\Services\Accounting;

use App\Enums\AcquirerMatchStatus;
use App\Models\AcquirerTransaction;
use App\Models\BankStatement;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PixBankReconciliationService
{
    public function __construct(
        protected BankingCalendarService $calendar
    ) {}

    /**
     * Calcula afinidade entre o pagador do Pix (CPF/CNPJ ou nome) e o cliente do pedido,
     * consultando primeiro customers.usual_payers e depois o histórico de conciliações.
     */
    public function calculatePayerCustomerAffinity(
        ?string $payerDocument,
        ?string $payerName,
        ?int $customerId,
        ?string $customerName
    ): int {
        if (empty($payerDocument) && empty($payerName)) {
            return 0;
        }

        if (!empty($customerId)) {
            $cust = Customer::find($customerId);
            if ($cust && $cust->matchesUsualPayer($payerName, $payerDocument)) {
                return 180;
            }
        } elseif (!empty($customerName)) {
            $matchingCusts = Customer::where('name', 'like', '%' . trim($customerName) . '%')
                ->whereNotNull('usual_payers')
                ->limit(5)
                ->get();

            foreach ($matchingCusts as $mc) {
                if ($mc->matchesUsualPayer($payerName, $payerDocument)) {
                    return 180;
                }
            }
        }

        $query = DB::table('bank_statements as bs')
            ->join('payments as p', function ($join) {
                $join->on('bs.conciliable_id', '=', 'p.id')
                    ->where('bs.conciliable_type', '=', Payment::class);
            })
            ->join('orders as o', 'p.order_id', '=', 'o.id')
            ->where('bs.status', 'reconciled')
            ->whereNull('bs.deleted_at');

        $query->where(function ($q) use ($payerDocument, $payerName) {
            if (!empty($payerDocument)) {
                $q->where('bs.document', $payerDocument);
            }
            if (!empty($payerName)) {
                $q->orWhere('bs.counterparty_name', $payerName);
            }
        });

        $query->where(function ($q) use ($customerId, $customerName) {
            if (!empty($customerId)) {
                $q->where('o.customer_id', $customerId);
            }
            if (!empty($customerName)) {
                $q->orWhere('o.customer_name', 'like', '%' . trim($customerName) . '%');
            }
        });

        $pastLinksCount = $query->count();

        return $pastLinksCount > 0 ? min(160, $pastLinksCount * 90) : 0;
    }

    /**
     * Cruza os pagamentos Pix Direto (Chave/API) de um turno com os lançamentos bancários
     * usando estritamente a janela bancária útil do turno [min_date, max_date].
     */
    public function matchShiftDirectPix(CashShift $shift, Collection $directPixPayments): array
    {
        if (!$shift->opened_at) {
            return [
                'bankStatements'    => collect(),
                'pixMatches'        => collect(),
                'usedStatementIds'  => [],
                'pixSystemTotal'    => (float) $directPixPayments->sum('amount'),
                'pixStatementTotal' => 0.0,
                'pixDiff'           => round(-(float) $directPixPayments->sum('amount'), 2),
                'window'            => null,
            ];
        }

        $window = $this->calendar->getSettlementWindow($shift->opened_at, $shift->closed_at);

        $bankStatements = DB::table('bank_statements')
            ->whereBetween('bank_date', [$window['min_date'], $window['max_date']])
            ->where(function ($q) {
                $q->where('amount', '>', 0)
                    ->orWhere('type', 'credit');
            })
            ->where(function ($q) {
                $q->where('movement_type', 'pix_in')
                    ->orWhereNull('movement_type')
                    ->orWhereNotIn('movement_type', ['card_in', 'loan_in', 'loan_out', 'transfer']);
            })
            ->whereNull('deleted_at')
            ->orderBy('bank_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $pixSystemTotal = (float) $directPixPayments->sum('amount');
        $pixMatches = collect();
        $usedStatementIds = [];
        $shiftDate = $shift->opened_at->toDateString();

        if ($bankStatements->isNotEmpty()) {
            // 1. Vínculos diretos já persistidos
            foreach ($directPixPayments as $p) {
                $directLinked = $bankStatements->first(function ($stmt) use ($p, $usedStatementIds) {
                    if (in_array($stmt->id, $usedStatementIds, true)) {
                        return false;
                    }
                    return ($stmt->conciliable_type === Payment::class && (int) $stmt->conciliable_id === (int) $p->id)
                        || ($stmt->conciliable_type === Order::class && (int) $stmt->conciliable_id === (int) $p->order_id);
                });

                if ($directLinked) {
                    $usedStatementIds[] = $directLinked->id;
                    $pixMatches->put($p->id, $directLinked);
                }
            }

            // 2. Pareamento em memória apenas quando há afinidade ou nome compatível (sem falsos positivos)
            foreach ($directPixPayments as $p) {
                if ($pixMatches->has($p->id)) {
                    continue;
                }

                $custTokens = collect(explode(' ', Str::ascii(mb_strtolower($p->order?->customer_name ?? ''))))
                    ->filter(fn ($w) => mb_strlen($w) >= 3)
                    ->values();

                $valueCandidates = $bankStatements->filter(function ($stmt) use ($p, $usedStatementIds) {
                    if (in_array($stmt->id, $usedStatementIds, true)) {
                        return false;
                    }
                    if ($stmt->status === 'reconciled' && !empty($stmt->conciliable_id)) {
                        return false;
                    }
                    return abs((float) $stmt->amount - (float) $p->amount) < 0.02;
                });

                $scoredBest = $valueCandidates->map(function ($stmt) use ($custTokens, $shiftDate, $p) {
                    $score = $this->calculatePayerCustomerAffinity(
                        $stmt->document ?? null,
                        $stmt->counterparty_name ?? null,
                        $p->order?->customer_id,
                        $p->order?->customer_name
                    );
                    $stmtText = Str::ascii(mb_strtolower(($stmt->counterparty_name ?? '') . ' ' . ($stmt->description ?? '')));
                    foreach ($custTokens as $token) {
                        if (str_contains($stmtText, $token)) {
                            $score += 50;
                        }
                    }
                    if ($stmt->bank_date === $shiftDate) {
                        $score += 10;
                    }
                    return ['stmt' => $stmt, 'score' => $score];
                })->sortByDesc('score')->first();

                if ($scoredBest && $scoredBest['score'] >= 50) {
                    $usedStatementIds[] = $scoredBest['stmt']->id;
                    $pixMatches->put($p->id, $scoredBest['stmt']);
                }
            }
        }

        $pixStatementTotal = (float) $bankStatements->whereIn('id', $usedStatementIds)->sum('amount');
        $pixDiff = round($pixStatementTotal - $pixSystemTotal, 2);

        return [
            'bankStatements'    => $bankStatements,
            'pixMatches'        => $pixMatches,
            'usedStatementIds'  => $usedStatementIds,
            'pixSystemTotal'    => $pixSystemTotal,
            'pixStatementTotal' => $pixStatementTotal,
            'pixDiff'           => $pixDiff,
            'window'            => $window,
        ];
    }

    /**
     * Reconcilia automaticamente pagamentos Chave Pix pendentes desde uma data base
     * respeitando estritamente o próximo dia útil bancário (Seg-Qui: D+1; Sex/Sáb/Dom/Feriado: próximo dia útil).
     */
    public function reconcilePendingPixSince(string $since): array
    {
        $pixPayments = Payment::with(['order.customer', 'cashShift', 'paymentMethod'])
            ->where('created_at', '>=', $since)
            ->where('is_paid', true)
            ->where(function ($q) {
                $q->where('payment_method_id', 13)
                    ->orWhere('method', 'like', '%Chave%')
                    ->orWhereHas('paymentMethod', fn ($pm) => $pm->whereIn('engine_type', ['pix_static', 'bank_api']));
            })
            ->orderBy('created_at', 'asc')
            ->get();

        $reconciled = 0;
        $ambiguous = 0;

        foreach ($pixPayments as $p) {
            $alreadyLinked = BankStatement::where('conciliable_type', Payment::class)
                ->where('conciliable_id', $p->id)
                ->exists();
            if ($alreadyLinked) {
                continue;
            }

            $window = $p->cashShift?->opened_at
                ? $this->calendar->getSettlementWindow($p->cashShift->opened_at, $p->cashShift->closed_at)
                : $this->calendar->getSettlementWindow($p->created_at);

            $startDate = $window['min_date'];
            $endDate   = $window['max_date'];

            $candidates = BankStatement::where('status', 'pending')
                ->where('amount', '>', 0)
                ->whereBetween('bank_date', [$startDate, $endDate])
                ->whereRaw('ABS(amount - ?) < 0.02', [(float) $p->amount])
                ->orderBy('bank_date', 'asc')
                ->get();

            if ($candidates->isEmpty()) {
                continue;
            }

            $competingPaymentsCount = $pixPayments->filter(function ($other) use ($p, $startDate, $endDate) {
                if ($other->id === $p->id) {
                    return false;
                }
                if (abs((float) $other->amount - (float) $p->amount) >= 0.02) {
                    return false;
                }
                $d = $other->created_at->toDateString();
                return $d >= $startDate && $d <= $endDate;
            })->count();

            $custTokens = collect(explode(' ', Str::ascii(mb_strtolower($p->order?->customer_name ?? ''))))
                ->filter(fn ($w) => mb_strlen($w) >= 3)
                ->values();

            $customerObj = $p->order?->customer;
            $matchingCustomersByName = collect();
            if (!$customerObj && !empty($p->order?->customer_name)) {
                $matchingCustomersByName = Customer::where('name', 'like', '%' . trim($p->order->customer_name) . '%')
                    ->whereNotNull('usual_payers')
                    ->get();
            }

            $scored = $candidates->map(function ($stmt) use ($custTokens, $customerObj, $matchingCustomersByName, $p, $candidates, $competingPaymentsCount) {
                $payerName = $stmt->counterparty_name ?: $stmt->description;
                $payerDoc  = $stmt->document;
                $score = 0;

                $isUsualPayer = false;
                if ($customerObj && $customerObj->matchesUsualPayer($payerName, $payerDoc)) {
                    $isUsualPayer = true;
                } else {
                    foreach ($matchingCustomersByName as $mc) {
                        if ($mc->matchesUsualPayer($payerName, $payerDoc)) {
                            $isUsualPayer = true;
                            break;
                        }
                    }
                }
                if ($isUsualPayer) {
                    $score += 180;
                }

                $stmtText = Str::ascii(mb_strtolower($payerName . ' ' . ($stmt->description ?? '')));
                $nameHits = 0;
                foreach ($custTokens as $token) {
                    if (str_contains($stmtText, $token)) {
                        $nameHits++;
                    }
                }
                $score += ($nameHits * 70);

                if ($candidates->count() === 1 && $competingPaymentsCount === 0) {
                    $score += 50;
                }

                if ($stmt->bank_date->toDateString() === $p->created_at->toDateString()) {
                    $score += 5;
                }

                return ['stmt' => $stmt, 'score' => $score];
            })->sortByDesc('score')->values();

            $best = $scored->first();

            if ($best && $best['score'] >= 50) {
                /** @var BankStatement $bestStmt */
                $bestStmt = $best['stmt'];

                DB::transaction(function () use ($bestStmt, $p) {
                    $payerDisplay = $bestStmt->counterparty_name ?: ($bestStmt->description ?: 'Cliente PIX');
                    $orderLabel = $p->order_id ? "Pedido #{$p->order_id}" : "Pagamento #{$p->id}";

                    $tx = \App\Models\Transaction::create([
                        'bank_account_id'  => $bestStmt->bank_account_id,
                        'type'             => 'in',
                        'amount'           => abs((float) $bestStmt->amount),
                        'description'      => "Recebimento PIX {$orderLabel} ({$payerDisplay})",
                        'transaction_date' => clone $bestStmt->bank_date,
                    ]);

                    $bestStmt->update([
                        'status'                => 'reconciled',
                        'reconciliation_source' => 'auto',
                        'reconciled_by_name'    => 'Motor Inteligente',
                        'reconciled_at'         => now(),
                        'conciliable_type'      => Payment::class,
                        'conciliable_id'        => $p->id,
                        'transaction_id'        => $tx->id,
                    ]);
                });

                if ($customerObj) {
                    $customerObj->recordUsualPayer($bestStmt->counterparty_name ?: $bestStmt->description, $bestStmt->document);
                } elseif ($matchingCustomersByName->isNotEmpty()) {
                    foreach ($matchingCustomersByName as $mc) {
                        $mc->recordUsualPayer($bestStmt->counterparty_name ?: $bestStmt->description, $bestStmt->document);
                    }
                }

                $reconciled++;
            } else {
                $ambiguous++;
            }
        }

        return [
            'reconciled' => $reconciled,
            'ambiguous'  => $ambiguous,
        ];
    }

    /**
     * Simula em memória o pareamento de entradas PIX (pix_in) de um lote OFX antes da gravação.
     * Retorna mapa [stmt_temp_key => ['payment_id' => int, 'order_id' => ?int, 'customer_name' => string]].
     */
    public function previewPixMatchesForStatements(array $pixStatements): array
    {
        if (empty($pixStatements)) {
            return [];
        }

        $dates = array_column($pixStatements, 'bank_date');
        $minDate = Carbon::parse(min($dates))->subDays(5)->toDateString();
        $maxDate = Carbon::parse(max($dates))->addDays(2)->toDateString();

        $pixPayments = Payment::with(['order.customer', 'cashShift', 'paymentMethod'])
            ->whereBetween('created_at', [$minDate . ' 00:00:00', $maxDate . ' 23:59:59'])
            ->where('is_paid', true)
            ->where(function ($q) {
                $q->where('payment_method_id', 13)
                    ->orWhere('method', 'like', '%Chave%')
                    ->orWhereHas('paymentMethod', fn ($pm) => $pm->whereIn('engine_type', ['pix_static', 'bank_api']));
            })
            ->orderBy('created_at', 'asc')
            ->get();

        $alreadyLinkedPaymentIds = BankStatement::where('conciliable_type', Payment::class)
            ->whereIn('conciliable_id', $pixPayments->pluck('id'))
            ->pluck('conciliable_id')
            ->flip()
            ->toArray();

        $matches = [];
        $usedStmtKeys = [];

        foreach ($pixPayments as $p) {
            if (isset($alreadyLinkedPaymentIds[$p->id])) {
                continue;
            }

            $window = $p->cashShift?->opened_at
                ? $this->calendar->getSettlementWindow($p->cashShift->opened_at, $p->cashShift->closed_at)
                : $this->calendar->getSettlementWindow($p->created_at);

            $startDate = $window['min_date'];
            $endDate   = $window['max_date'];

            $candidates = collect($pixStatements)->filter(function ($s) use ($p, $startDate, $endDate, $usedStmtKeys) {
                if (isset($usedStmtKeys[$s['fitid']])) {
                    return false;
                }
                if ($s['bank_date'] < $startDate || $s['bank_date'] > $endDate) {
                    return false;
                }
                return abs((float) $s['amount'] - (float) $p->amount) < 0.02;
            })->values();

            if ($candidates->isEmpty()) {
                continue;
            }

            $competingPaymentsCount = $pixPayments->filter(function ($other) use ($p, $startDate, $endDate) {
                if ($other->id === $p->id || abs((float) $other->amount - (float) $p->amount) >= 0.02) {
                    return false;
                }
                $d = $other->created_at->toDateString();
                return $d >= $startDate && $d <= $endDate;
            })->count();

            $custTokens = collect(explode(' ', Str::ascii(mb_strtolower($p->order?->customer_name ?? ''))))
                ->filter(fn ($w) => mb_strlen($w) >= 3)
                ->values();

            $customerObj = $p->order?->customer;
            $matchingCustomersByName = collect();
            if (!$customerObj && !empty($p->order?->customer_name)) {
                $matchingCustomersByName = Customer::where('name', 'like', '%' . trim($p->order->customer_name) . '%')
                    ->whereNotNull('usual_payers')
                    ->get();
            }

            $scored = $candidates->map(function ($s) use ($custTokens, $customerObj, $matchingCustomersByName, $p, $candidates, $competingPaymentsCount) {
                $payerName = $s['counterparty_name'] ?: $s['clean_description'];
                $payerDoc  = $s['document'];
                $score = 0;

                $isUsualPayer = false;
                if ($customerObj && $customerObj->matchesUsualPayer($payerName, $payerDoc)) {
                    $isUsualPayer = true;
                } else {
                    foreach ($matchingCustomersByName as $mc) {
                        if ($mc->matchesUsualPayer($payerName, $payerDoc)) {
                            $isUsualPayer = true;
                            break;
                        }
                    }
                }
                if ($isUsualPayer) {
                    $score += 180;
                }

                $stmtText = Str::ascii(mb_strtolower($payerName . ' ' . ($s['clean_description'] ?? '')));
                foreach ($custTokens as $token) {
                    if (str_contains($stmtText, $token)) {
                        $score += 70;
                    }
                }

                if ($candidates->count() === 1 && $competingPaymentsCount === 0) {
                    $score += 50;
                }

                if ($s['bank_date'] === $p->created_at->toDateString()) {
                    $score += 5;
                }

                return ['stmt' => $s, 'score' => $score];
            })->sortByDesc('score')->values();

            $best = $scored->first();
            if ($best && $best['score'] >= 50) {
                $fitid = $best['stmt']['fitid'];
                $usedStmtKeys[$fitid] = true;
                $matches[$fitid] = [
                    'payment_id'    => $p->id,
                    'order_id'      => $p->order_id,
                    'customer_name' => $p->order?->customer_name ?? 'Cliente PDV',
                ];
            }
        }

        return $matches;
    }

    /**
     * Busca e ranqueia transações candidatas (Maquininha + Extrato Pix) para conciliação manual de uma linha.
     */
    public function findCandidatesForTarget(
        CashShift $shift,
        float $targetAmount,
        ?int $customerId,
        string $customerName,
        bool $widerWindow = false
    ): array {
        $custTokens = collect(explode(' ', Str::ascii(mb_strtolower($customerName))))
            ->filter(fn ($w) => mb_strlen($w) >= 3)
            ->values();

        $window = $shift->opened_at
            ? $this->calendar->getSettlementWindow($shift->opened_at, $shift->closed_at)
            : ['min_date' => now()->toDateString(), 'max_date' => now()->addDay()->toDateString()];

        $minBankDate = $widerWindow
            ? Carbon::parse($window['min_date'])->subDay()->toDateString()
            : $window['min_date'];
        $maxBankDate = $widerWindow
            ? Carbon::parse($window['max_date'])->addDays(3)->toDateString()
            : $window['max_date'];

        $closedAt = $shift->closed_at ?? now();
        $candidates = [];

        // 1. Transações Órfãs da Maquininha na janela do turno
        $orphanAcq = AcquirerTransaction::query()
            ->whereBetween('transaction_date', [$shift->opened_at->copy()->subHours(2), $closedAt->copy()->addHours(2)])
            ->whereIn('status', [AcquirerMatchStatus::ORPHAN, AcquirerMatchStatus::AMBIGUOUS])
            ->orderBy('transaction_date', 'asc')
            ->get();

        foreach ($orphanAcq as $tx) {
            $amt = (float) ($tx->gross_amount ?: ($tx->amount ?? 0));
            $diff = round($amt - $targetAmount, 2);
            $absDiff = abs($diff);

            if (!$widerWindow && $absDiff > 35.00) {
                continue;
            }

            $score = 100 - min(70, $absDiff * 2);
            if ($absDiff < 0.02) {
                $score += 50;
            }

            $candidates[] = [
                'source'     => 'acquirer',
                'source_lbl' => '💳 Maquininha',
                'id'         => $tx->id,
                'date_time'  => $tx->transaction_date?->format('d/m H:i:s') ?? '--:--',
                'payer'      => "{$tx->modality} {$tx->brand}",
                'details'    => "NSU: {$tx->nsu} • TID: {$tx->terminal_code}",
                'amount'     => $amt,
                'diff'       => $diff,
                'score'      => $score,
            ];
        }

        // 2. Entradas Pix / Transferências Bancárias no Extrato
        $bankStmts = DB::table('bank_statements')
            ->whereBetween('bank_date', [$minBankDate, $maxBankDate])
            ->where(function ($q) {
                $q->where('amount', '>', 0)
                    ->orWhere('type', 'credit');
            })
            ->where(function ($q) {
                $q->whereNull('movement_type')
                    ->orWhereNotIn('movement_type', ['card_in', 'loan_in', 'loan_out']);
            })
            ->where(function ($q) {
                $q->where('status', 'pending')
                    ->orWhereNull('conciliable_id');
            })
            ->whereNull('deleted_at')
            ->orderBy('bank_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($bankStmts as $stmt) {
            $amt = round(abs((float) $stmt->amount), 2);
            $diff = round($amt - $targetAmount, 2);
            $absDiff = abs($diff);

            $payer = $stmt->counterparty_name ?: ($stmt->description ?: 'Crédito Bancário');
            $stmtText = Str::ascii(mb_strtolower($payer . ' ' . ($stmt->description ?? '')));
            $nameHits = 0;
            foreach ($custTokens as $token) {
                if (str_contains($stmtText, $token)) {
                    $nameHits++;
                }
            }

            $affinityBonus = $this->calculatePayerCustomerAffinity(
                $stmt->document ?? null,
                $stmt->counterparty_name ?? null,
                $customerId,
                $customerName
            );

            if (!$widerWindow && $nameHits === 0 && $affinityBonus === 0 && $absDiff > 35.00) {
                continue;
            }

            $bankDateObj = Carbon::parse($stmt->bank_date);
            $dayName = mb_convert_case($bankDateObj->locale('pt_BR')->translatedFormat('D'), MB_CASE_TITLE, 'UTF-8');

            $score = 90 + ($nameHits * 60) + $affinityBonus - min(70, $absDiff * 2);
            if ($absDiff < 0.02) {
                $score += 50;
            }

            $candidates[] = [
                'source'     => 'bank_pix',
                'source_lbl' => '🏦 Extrato Pix',
                'id'         => $stmt->id,
                'date_time'  => $bankDateObj->format('d/m/Y') . " ({$dayName})",
                'payer'      => $payer,
                'details'    => "Extrato Doc #{$stmt->id}"
                    . ($stmt->document ? " • CPF/CNPJ: {$stmt->document}" : '')
                    . ($affinityBonus > 0 ? " • 🔗 Pagador recorrente deste cliente" : ''),
                'amount'     => $amt,
                'diff'       => $diff,
                'score'      => $score,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates;
    }

    /**
     * Vincula um candidato (Maquininha ou Extrato Pix) a um Pagamento existente.
     */
    public function linkCandidateToPayment(Payment $payment, string $source, int $sourceId, string $operatorName): string
    {
        if ($source === 'acquirer') {
            $acqTx = AcquirerTransaction::findOrFail($sourceId);
            $receivedAmount = (float) ($acqTx->gross_amount ?: ($acqTx->amount ?? 0));
            $oldAmount = (float) $payment->amount;
            $diff = round($receivedAmount - $oldAmount, 2);

            $modality = mb_strtolower($acqTx->modality ?? '');
            if (str_contains($modality, 'pix') || str_contains($modality, 'qrcode')) {
                $method = PaymentMethod::where('engine_type', 'pix_machine')->first();
            } elseif (str_contains($modality, 'créd') || str_contains($modality, 'cred')) {
                $method = PaymentMethod::where('name', 'like', '%Crédito%')->first();
            } else {
                $method = PaymentMethod::where('name', 'like', '%Débito%')->first();
            }

            DB::transaction(function () use ($payment, $acqTx, $method, $receivedAmount, $oldAmount, $diff, $operatorName) {
                $payment->update([
                    'payment_method_id'       => $method?->id ?? $payment->payment_method_id,
                    'method'                  => $method?->name ?? $payment->method,
                    'amount'                  => $receivedAmount,
                    'acquirer_transaction_id' => $acqTx->id,
                ]);

                $diffNote = abs($diff) >= 0.01
                    ? " [Auditoria: PDV R$ " . number_format($oldAmount, 2, ',', '.') . ", cobrado na máquina R$ " . number_format($receivedAmount, 2, ',', '.') . "]"
                    : '';

                if ($payment->order && $diffNote) {
                    $payment->order->update([
                        'notes' => trim(($payment->order->notes ? $payment->order->notes . ' | ' : '') . ltrim($diffNote)),
                    ]);
                }

                $acqTx->update([
                    'order_id'              => $payment->order_id,
                    'payment_id'            => $payment->id,
                    'status'                => AcquirerMatchStatus::MATCHED,
                    'reconciliation_source' => 'manual',
                    'reconciled_by_name'    => $operatorName,
                    'reconciled_at'         => now(),
                    'notes'                 => "Conciliado manualmente com Pagamento #{$payment->id} (Pedido #{$payment->order_id}).{$diffNote}",
                ]);
            });

            return "NSU {$acqTx->nsu} associado ao Pedido #{$payment->order_id}.";
        }

        $stmt = BankStatement::findOrFail($sourceId);
        $receivedAmount = round(abs((float) $stmt->amount), 2);
        $oldAmount = (float) $payment->amount;
        $diff = round($receivedAmount - $oldAmount, 2);

        $chavePixMethod = PaymentMethod::where('engine_type', 'pix_static')->first()
            ?? PaymentMethod::where('name', 'like', '%Chave%')->first()
            ?? PaymentMethod::where('name', 'like', '%Pix%')->first();

        DB::transaction(function () use ($payment, $stmt, $chavePixMethod, $receivedAmount, $oldAmount, $diff, $operatorName) {
            $payment->update([
                'payment_method_id'       => $chavePixMethod?->id ?? $payment->payment_method_id,
                'method'                  => $chavePixMethod?->name ?? 'Chave Pix',
                'amount'                  => $receivedAmount,
                'acquirer_transaction_id' => null,
            ]);

            $diffNote = abs($diff) >= 0.01
                ? " [Auditoria Pix: PDV R$ " . number_format($oldAmount, 2, ',', '.') . ", recebido no extrato R$ " . number_format($receivedAmount, 2, ',', '.') . "]"
                : '';

            if ($payment->order && $diffNote) {
                $payment->order->update([
                    'notes' => trim(($payment->order->notes ? $payment->order->notes . ' | ' : '') . ltrim($diffNote)),
                ]);
            }

            $stmt->update([
                'status'                => 'reconciled',
                'reconciliation_source' => 'manual',
                'reconciled_by_name'    => $operatorName,
                'reconciled_at'         => now(),
                'conciliable_type'      => Payment::class,
                'conciliable_id'        => $payment->id,
            ]);

            if ($payment->order?->customer_id) {
                Customer::find($payment->order->customer_id)
                    ?->recordUsualPayer($stmt->counterparty_name ?: $stmt->description, $stmt->document);
            }
        });

        return "Crédito de {$stmt->description} associado ao Pedido #{$payment->order_id}.";
    }

    /**
     * Vincula um candidato (Maquininha ou Extrato Pix) a um Pedido sem pagamento registrado.
     */
    public function linkCandidateToOrder(CashShift $shift, Order $order, string $source, int $sourceId, string $operatorName): string
    {
        if ($source === 'acquirer') {
            $acqTx = AcquirerTransaction::findOrFail($sourceId);
            $receivedAmount = (float) ($acqTx->gross_amount ?: ($acqTx->amount ?? 0));
            $orderTotal = (float) $order->total;
            $diff = round($receivedAmount - $orderTotal, 2);

            DB::transaction(function () use ($shift, $acqTx, $order, $receivedAmount, $orderTotal, $diff, $operatorName) {
                $diffNote = '';
                if (abs($diff) >= 0.01) {
                    $tipo = $diff > 0 ? 'a MAIOR' : 'a MENOR';
                    $diffNote = " [Auditoria: Pedido R$ " . number_format($orderTotal, 2, ',', '.') . ", cobrado {$tipo} R$ " . number_format(abs($diff), 2, ',', '.') . "]";
                }

                $order->update([
                    'status'         => 'closed',
                    'payment_status' => 'paid',
                    'cash_shift_id'  => $shift->id,
                    'notes'          => trim(($order->notes ? $order->notes . ' | ' : '') . "Conciliado na auditoria (NSU {$acqTx->nsu}){$diffNote}"),
                ]);
                $order->items()->where('status', 'cancelled')->update(['status' => 'active']);

                $modality = mb_strtolower($acqTx->modality ?? '');
                if (str_contains($modality, 'pix') || str_contains($modality, 'qrcode')) {
                    $method = PaymentMethod::where('engine_type', 'pix_machine')->first();
                } elseif (str_contains($modality, 'créd') || str_contains($modality, 'cred')) {
                    $method = PaymentMethod::where('name', 'like', '%Crédito%')->first();
                } else {
                    $method = PaymentMethod::where('name', 'like', '%Débito%')->first();
                }
                $method = $method ?? PaymentMethod::first();

                $payment = $order->payments()->where('is_paid', true)->first();
                if (!$payment) {
                    $payment = $order->payments()->create([
                        'cash_shift_id'           => $shift->id,
                        'payment_method_id'       => $method?->id,
                        'method'                  => $method?->name ?? ($acqTx->modality ?: 'Cartão/Pix Máq'),
                        'amount'                  => $receivedAmount,
                        'status'                  => 'completed',
                        'is_paid'                 => true,
                        'acquirer_transaction_id' => $acqTx->id,
                        'created_at'              => $acqTx->transaction_date ?? $order->created_at,
                    ]);
                } else {
                    $payment->update([
                        'cash_shift_id'           => $shift->id,
                        'acquirer_transaction_id' => $acqTx->id,
                    ]);
                }

                $acqTx->update([
                    'order_id'              => $order->id,
                    'payment_id'            => $payment->id,
                    'status'                => AcquirerMatchStatus::MATCHED,
                    'reconciliation_source' => 'manual',
                    'reconciled_by_name'    => $operatorName,
                    'reconciled_at'         => now(),
                    'notes'                 => "Conciliado manualmente com Pedido #{$order->id} na auditoria.{$diffNote}",
                ]);
            });

            return "Transação NSU {$acqTx->nsu} vinculada ao Pedido #{$order->id}.";
        }

        $stmt = BankStatement::findOrFail($sourceId);
        $receivedAmount = round(abs((float) $stmt->amount), 2);
        $orderTotal = (float) $order->total;
        $diff = round($receivedAmount - $orderTotal, 2);

        DB::transaction(function () use ($shift, $stmt, $order, $receivedAmount, $orderTotal, $diff, $operatorName) {
            $diffNote = '';
            if (abs($diff) >= 0.01) {
                $tipo = $diff > 0 ? 'a MAIOR' : 'a MENOR';
                $diffNote = " [Auditoria Pix: Pedido R$ " . number_format($orderTotal, 2, ',', '.') . ", recebido {$tipo} R$ " . number_format(abs($diff), 2, ',', '.') . "]";
            }

            $order->update([
                'status'         => 'closed',
                'payment_status' => 'paid',
                'cash_shift_id'  => $shift->id,
                'notes'          => trim(($order->notes ? $order->notes . ' | ' : '') . "Pix conciliado no extrato (Doc #{$stmt->id} - {$stmt->description}){$diffNote}"),
            ]);
            $order->items()->where('status', 'cancelled')->update(['status' => 'active']);

            $chavePixMethod = PaymentMethod::where('engine_type', 'pix_static')->first()
                ?? PaymentMethod::where('name', 'like', '%Chave%')->first()
                ?? PaymentMethod::where('name', 'like', '%Pix%')->first();

            $payment = $order->payments()->where('is_paid', true)->first();
            if (!$payment) {
                $payment = $order->payments()->create([
                    'cash_shift_id'     => $shift->id,
                    'payment_method_id' => $chavePixMethod?->id,
                    'method'            => $chavePixMethod?->name ?? 'Chave Pix',
                    'amount'            => $receivedAmount,
                    'status'            => 'completed',
                    'is_paid'           => true,
                    'created_at'        => $order->created_at,
                ]);
            }

            $stmt->update([
                'status'                => 'reconciled',
                'reconciliation_source' => 'manual',
                'reconciled_by_name'    => $operatorName,
                'reconciled_at'         => now(),
                'conciliable_type'      => Payment::class,
                'conciliable_id'        => $payment->id,
            ]);

            if ($order->customer_id) {
                Customer::find($order->customer_id)
                    ?->recordUsualPayer($stmt->counterparty_name ?: $stmt->description, $stmt->document);
            }
        });

        return "Crédito de {$stmt->description} associado ao Pedido #{$order->id}.";
    }

    /**
     * Concilia um pagamento com uma transação de maquininha/extrato de valor divergente,
     * registrando a diferença como Perda por Quebra de Caixa, Perda por Imposto Indevido ou Gorjeta.
     */
    public function reconcileWithDiscrepancyAdjustment(
        Payment $payment,
        string $source,
        int $sourceId,
        string $adjustmentType, // 'cash_breakage' | 'tax_loss' | 'tip'
        string $operatorName,
        ?int $employeeId = null,
        string $payoutMode = 'ledger_credit'
    ): string {
        $payment->loadMissing(['order', 'paymentMethod']);
        $order = $payment->order;
        $oldAmount = round((float) $payment->amount, 2);

        if ($source === 'acquirer') {
            $acqTx = AcquirerTransaction::findOrFail($sourceId);
            $receivedAmount = round((float) ($acqTx->gross_amount ?: ($acqTx->amount ?? 0)), 2);
        } else {
            $stmt = BankStatement::findOrFail($sourceId);
            $receivedAmount = round(abs((float) $stmt->amount), 2);
        }

        $diff = round($receivedAmount - $oldAmount, 2);
        $absDiff = abs($diff);

        if ($absDiff < 0.01) {
            return $this->linkCandidateToPayment($payment, $source, $sourceId, $operatorName);
        }

        return DB::transaction(function () use (
            $payment, $order, $source, $sourceId, $receivedAmount, $oldAmount,
            $diff, $absDiff, $adjustmentType, $operatorName, $employeeId, $payoutMode
        ) {
            // 1. Garante método de pagamento do tipo 'loss' (Perda / Quebra de Caixa)
            $lossMethod = PaymentMethod::where('engine_type', \App\Enums\PaymentEngineType::LOSS->value)->first();
            if (!$lossMethod) {
                $lossMethod = PaymentMethod::create([
                    'name'                 => 'Perda / Quebra de Caixa',
                    'engine_type'          => \App\Enums\PaymentEngineType::LOSS->value,
                    'is_active'            => true,
                    'show_on_pos'          => true,
                    'auto_fiscal_emission' => false,
                ]);
            }

            // 2. Caso A: Máquina < PDV (ex: PDV R$ 55,00 x Máquina R$ 49,00 => Quebra de Caixa de R$ 6,00)
            if ($diff < 0) {
                $payment->update([
                    'amount' => $receivedAmount,
                ]);

                if ($payment->transaction) {
                    $payment->transaction->update([
                        'amount' => $receivedAmount,
                    ]);
                }

                // Cria lançamento complementar de Perda no pedido para fechar o saldo contábil
                $order?->payments()->create([
                    'payment_method_id' => $lossMethod->id,
                    'cash_shift_id'     => $payment->cash_shift_id,
                    'cashier_id'        => auth()->id(),
                    'method'            => $lossMethod->name,
                    'amount'            => $absDiff,
                    'status'            => 'completed',
                    'is_paid'           => true,
                    'created_at'        => $payment->created_at,
                ]);

                // Registra na tabela oficial de Perdas (inventory_losses) sem tocar no estoque físico
                $lossRecord = new \App\Models\InventoryLoss([
                    'product_id'  => null,
                    'order_id'    => $order?->id,
                    'cash_shift_id' => $payment->cash_shift_id,
                    'user_id'     => auth()->id(),
                    'loss_type'   => \App\Enums\InventoryLossType::CASH_BREAKAGE,
                    'quantity'    => 1,
                    'unit_cost'   => $absDiff,
                    'total_cost'  => $absDiff,
                    'reason'      => "Quebra de caixa Pedido #{$order?->id} (PDV R$ " . number_format($oldAmount, 2, ',', '.') . " × Recebido R$ " . number_format($receivedAmount, 2, ',', '.') . ")",
                    'notes'       => "Falha de operador identificada na auditoria por {$operatorName}. Cupom fiscal emitido a maior.",
                ]);
                $lossRecord->skipInventoryDeduction = true;
                $lossRecord->save();

                if ($order) {
                    $order->recalculatePaymentStatus();
                    $order->updateQuietly([
                        'notes' => trim(($order->notes ? $order->notes . ' | ' : '') . "Auditoria: R$ " . number_format($absDiff, 2, ',', '.') . " registrado como Perda por Quebra de Caixa."),
                    ]);
                }

                $summary = "Conciliado R$ " . number_format($receivedAmount, 2, ',', '.') . " e registrado R$ " . number_format($absDiff, 2, ',', '.') . " como Perda por Quebra de Caixa.";
            } else {
                // 3. Caso B: Máquina > PDV (ex: PDV R$ 49,00 x Máquina R$ 55,00)
                $payment->update([
                    'amount' => $receivedAmount,
                ]);

                if ($payment->transaction) {
                    $payment->transaction->update([
                        'amount' => $receivedAmount,
                    ]);
                }

                if ($adjustmentType === 'tip' && $order) {
                    $newTip = round((float) ($order->tip_amount ?? 0) + $absDiff, 2);
                    $tipEmp = $employeeId ? \App\Models\Employee::find($employeeId) : null;

                    if ($payoutMode === 'ledger_credit' && $tipEmp) {
                        $latestLedger = $tipEmp->ledgers()->latest('id')->first();
                        $curBal = $latestLedger ? (float) $latestLedger->balance : 0.0;
                        $tipEmp->ledgers()->create([
                            'order_id'    => $order->id,
                            'type'        => 'credit',
                            'description' => "Gorjeta (Auditoria) - Pedido #{$order->id}",
                            'amount'      => $absDiff,
                            'balance'     => round($curBal + $absDiff, 2),
                        ]);
                    } elseif ($payoutMode === 'cash_drawer' && $payment->cash_shift_id) {
                        \App\Models\Transaction::create([
                            'bank_account_id'  => 1,
                            'cash_shift_id'    => $payment->cash_shift_id,
                            'order_id'         => $order->id,
                            'type'             => 'out',
                            'category'         => 'tip_payout',
                            'amount'           => $absDiff,
                            'description'      => "Repasse Gorjeta (Auditoria) - Pedido #{$order->id}" . ($tipEmp ? " ({$tipEmp->name})" : ''),
                            'operator_name'    => $operatorName,
                            'transaction_date' => now(),
                        ]);
                    }

                    $order->updateQuietly([
                        'tip_amount'      => $newTip,
                        'tip_employee_id' => $employeeId ?: $order->tip_employee_id,
                        'tip_payout_mode' => $payoutMode,
                        'tip_settled_at'  => now(),
                        'notes'           => trim(($order->notes ? $order->notes . ' | ' : '') . "Auditoria: +R$ " . number_format($absDiff, 2, ',', '.') . " classificado como Gorjeta."),
                    ]);

                    $summary = "Conciliado R$ " . number_format($receivedAmount, 2, ',', '.') . " com +R$ " . number_format($absDiff, 2, ',', '.') . " registrado como Gorjeta.";
                } else {
                    // Perda por Imposto Indevido / Divergência a Maior
                    $lossRecord = new \App\Models\InventoryLoss([
                        'product_id'    => null,
                        'order_id'      => $order?->id,
                        'cash_shift_id' => $payment->cash_shift_id,
                        'user_id'       => auth()->id(),
                        'loss_type'     => \App\Enums\InventoryLossType::TAX_LOSS,
                        'quantity'      => 1,
                        'unit_cost'     => $absDiff,
                        'total_cost'    => $absDiff,
                        'reason'        => "Perda por Imposto Indevido Pedido #{$order?->id} (PDV R$ " . number_format($oldAmount, 2, ',', '.') . " × Recebido R$ " . number_format($receivedAmount, 2, ',', '.') . ")",
                        'notes'         => "Diferença a maior identificada na auditoria por {$operatorName}.",
                    ]);
                    $lossRecord->skipInventoryDeduction = true;
                    $lossRecord->save();

                    if ($order) {
                        $order->updateQuietly([
                            'notes' => trim(($order->notes ? $order->notes . ' | ' : '') . "Auditoria: R$ " . number_format($absDiff, 2, ',', '.') . " registrado como Perda por Imposto Indevido."),
                        ]);
                    }

                    $summary = "Conciliado R$ " . number_format($receivedAmount, 2, ',', '.') . " e registrado R$ " . number_format($absDiff, 2, ',', '.') . " como Perda por Imposto Indevido.";
                }
            }

            // 4. Vincula formalmente o comprovante documental (criando AccountReceivable no valor real da máquina!)
            if ($source === 'acquirer') {
                $acqTx = AcquirerTransaction::findOrFail($sourceId);
                $modality = mb_strtolower($acqTx->modality ?? '');
                if (str_contains($modality, 'pix') || str_contains($modality, 'qrcode')) {
                    $method = PaymentMethod::where('engine_type', 'pix_machine')->first();
                } elseif (str_contains($modality, 'créd') || str_contains($modality, 'cred')) {
                    $method = PaymentMethod::where('name', 'like', '%Crédito%')->first();
                } else {
                    $method = PaymentMethod::where('name', 'like', '%Débito%')->first();
                }

                if ($method) {
                    $payment->update([
                        'payment_method_id' => $method->id,
                        'method'            => $method->name,
                    ]);
                }

                app(\App\Services\Financial\Acquirers\AcquirerReconciliationEngine::class)
                    ->linkSingle($acqTx, $order, $payment->fresh(['paymentMethod', 'transaction']));
            } else {
                $stmt = BankStatement::findOrFail($sourceId);
                $stmt->update([
                    'status'                => 'reconciled',
                    'reconciliation_source' => 'manual',
                    'reconciled_by_name'    => $operatorName,
                    'reconciled_at'         => now(),
                    'conciliable_type'      => Payment::class,
                    'conciliable_id'        => $payment->id,
                ]);
            }

            return $summary;
        });
    }
}
