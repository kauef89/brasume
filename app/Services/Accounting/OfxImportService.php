<?php

namespace App\Services\Accounting;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\BankMovementType;
use App\Enums\PayableSettlementMode;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Models\BankReconciliationRule;
use App\Models\BankStatement;
use App\Models\LoanContract;
use App\Models\LoanInstallment;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use App\Services\BankDictionaries\BankDictionaryFactory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class OfxImportService
{
    private ?Collection $suppliersCache = null;
    private ?Collection $rulesCache = null;
    private ?Collection $recurringCache = null;
    private ?Collection $loansCache = null;
    private array $recurringAvgCache = [];
    private array $claimedRecurringMonthKeys = [];

    /**
     * Analisa um ou mais arquivos OFX em memória (Dry Run) sem gravar no banco.
     */
    public function analyzeFiles(array $fileEntries, BankAccount $account): array
    {
        $dictionary = BankDictionaryFactory::make($account->bank_code);
        $scoringService = new BankMatchScoringService();
        $cardService = new CardSettlementReconciliationService();
        $pixService = app(PixBankReconciliationService::class);

        $newTransactions = [];
        $duplicates = [];
        $malformedBlocks = [];
        $unclassifiedMemos = [];
        $filesSummary = [];
        $seenFitidsInBatch = [];
        $claimedPayableIds = [];

        $totalInflow = 0.0;
        $totalOutflow = 0.0;

        foreach ($fileEntries as $entry) {
            $resolved = $this->resolveFileEntry($entry);
            if (!$resolved) {
                continue;
            }

            $rawContent = @file_get_contents($resolved['path']);
            if ($rawContent === false || trim($rawContent) === '') {
                $malformedBlocks[] = [
                    'file'    => $resolved['filename'],
                    'block'   => 0,
                    'reason'  => 'Arquivo vazio ou ilegível.',
                    'snippet' => '',
                ];
                continue;
            }

            if (!mb_check_encoding($rawContent, 'UTF-8')) {
                $rawContent = mb_convert_encoding($rawContent, 'UTF-8', 'Windows-1252, ISO-8859-1');
            }

            $blocks = explode('<STMTTRN>', $rawContent);
            array_shift($blocks);

            $fileNewCount = 0;
            $fileDupCount = 0;
            $fileErrCount = 0;

            if (empty($blocks)) {
                $malformedBlocks[] = [
                    'file'    => $resolved['filename'],
                    'block'   => 0,
                    'reason'  => 'Nenhuma tag <STMTTRN> encontrada no arquivo (verifique se é um extrato OFX válido).',
                    'snippet' => mb_substr(trim(preg_replace('/\s+/', ' ', $rawContent)), 0, 180),
                ];
                $fileErrCount++;
            }

            foreach ($blocks as $idx => $txn) {
                $blockNum = $idx + 1;

                preg_match('/<DTPOSTED>([^<\r\n]+)/i', $txn, $dRaw);
                preg_match('/<TRNAMT>([^<\r\n]+)/i', $txn, $amtRaw);
                preg_match('/<FITID>([^<\r\n]+)/i', $txn, $fidRaw);
                preg_match('/<MEMO>([^<\r\n]+)/i', $txn, $memRaw);
                preg_match('/<NAME>([^<\r\n]+)/i', $txn, $nameRaw);
                preg_match('/<CHECKNUM>([^<\r\n]+)/i', $txn, $chkRaw);
                preg_match('/<REFNUM>([^<\r\n]+)/i', $txn, $refRaw);

                $snippet = mb_substr(trim((string) preg_replace('/\s+/', ' ', $txn)), 0, 220);

                $dateDigits = preg_replace('/\D/', '', trim((string) ($dRaw[1] ?? '')));
                if (strlen($dateDigits) < 8) {
                    $malformedBlocks[] = [
                        'file'    => $resolved['filename'],
                        'block'   => $blockNum,
                        'reason'  => 'Tag <DTPOSTED> ausente ou inválida.',
                        'snippet' => $snippet,
                    ];
                    $fileErrCount++;
                    continue;
                }

                try {
                    $dateObj = Carbon::createFromFormat('Ymd', substr($dateDigits, 0, 8));
                    if (!$dateObj) {
                        throw new \Exception('Data inválida');
                    }
                    $dateStr = $dateObj->format('Y-m-d');
                } catch (\Throwable $e) {
                    $malformedBlocks[] = [
                        'file'    => $resolved['filename'],
                        'block'   => $blockNum,
                        'reason'  => "Data <DTPOSTED> não pôde ser convertida ('" . trim((string) ($dRaw[1] ?? '')) . "').",
                        'snippet' => $snippet,
                    ];
                    $fileErrCount++;
                    continue;
                }

                $rawAmtStr = trim((string) ($amtRaw[1] ?? ''));
                if ($rawAmtStr === '' || !preg_match('/^-?\d+(?:[.,]\d+)?$/', $rawAmtStr)) {
                    $malformedBlocks[] = [
                        'file'    => $resolved['filename'],
                        'block'   => $blockNum,
                        'reason'  => "Valor <TRNAMT> ausente ou malformado ('{$rawAmtStr}').",
                        'snippet' => $snippet,
                    ];
                    $fileErrCount++;
                    continue;
                }

                $amount = $this->parseOfxAmount($rawAmtStr);

                $memoText = trim((string) ($memRaw[1] ?? ''));
                $nameText = trim((string) ($nameRaw[1] ?? ''));
                $rawMemo = $memoText !== '' ? $memoText : ($nameText !== '' ? $nameText : 'S/D');

                $fitid = trim((string) ($fidRaw[1] ?? ''));
                $syntheticFitid = false;
                if ($fitid === '') {
                    $altRef = trim((string) ($refRaw[1] ?? $chkRaw[1] ?? ''));
                    $fitid = $altRef !== ''
                        ? "REF-{$account->id}-{$dateStr}-{$altRef}"
                        : 'SYNTH-' . md5("{$account->id}|{$dateStr}|{$amount}|{$rawMemo}|{$blockNum}");
                    $syntheticFitid = true;
                }

                $parsedData = $dictionary->parseMemo($rawMemo);
                $counterparty = !empty($parsedData['name']) ? $parsedData['name'] : null;
                $movementType = $parsedData['movement_type'] ?? BankMovementType::OTHER->value;
                $stmtIdentifier = $parsedData['statement_identifier'] ?? null;

                $isDbDuplicate = BankStatement::where('fitid', $fitid)->exists();
                $isBatchDuplicate = isset($seenFitidsInBatch[$fitid]);
                $seenFitidsInBatch[$fitid] = true;

                if ($isDbDuplicate || $isBatchDuplicate) {
                    $duplicates[] = [
                        'file'        => $resolved['filename'],
                        'fitid'       => $fitid,
                        'bank_date'   => $dateStr,
                        'amount'      => $amount,
                        'description' => $parsedData['clean_description'],
                        'source'      => $isDbDuplicate ? 'Já no banco de dados' : 'Repetido no lote',
                    ];
                    $fileDupCount++;
                    continue;
                }

                // Simula regras de saída (Uso Pessoal, Regras, Títulos Exatos, Recorrentes Auto-Débito e Tarifas)
                $autoAction = null;
                $autoActionStatus = 'queue'; // 'auto' | 'pending_external' | 'queue'

                if ($amount < 0) {
                    $matchedSupplier = $this->findMatchingSupplier($parsedData['document'], $counterparty, $parsedData['clean_description']);
                    $matchedRule = $this->findMatchingRule($rawMemo, $counterparty, $parsedData['clean_description']);

                    if ($matchedSupplier && $matchedSupplier->is_personal) {
                        $autoAction = "Auto-conciliar Uso Pessoal ({$matchedSupplier->name})";
                        $autoActionStatus = 'auto';
                    } elseif ($matchedRule && $matchedRule->auto_reconcile) {
                        $ruleLabel = $matchedRule->target_type?->getLabel() ?? 'Regra Automática';
                        $autoAction = "Auto-conciliar via Regra ({$ruleLabel})";
                        $autoActionStatus = 'auto';
                    } elseif (($loanEval = $this->evaluateLoanInstallmentMatch($movementType, $stmtIdentifier, $parsedData['clean_description'], $dateStr, $amount, $claimedPayableIds))['status'] !== 'none') {
                        if ($loanEval['status'] === 'matched') {
                            if (!empty($loanEval['payable_id'])) {
                                $claimedPayableIds[] = $loanEval['payable_id'];
                            }
                            $autoAction = $loanEval['action_label'];
                            $autoActionStatus = 'auto';
                        } else {
                            $autoAction = $loanEval['action_label'];
                            $autoActionStatus = 'pending_external';
                        }
                    } else {
                        $tempStmt = new BankStatement([
                            'bank_account_id'      => $account->id,
                            'bank_date'            => $dateStr,
                            'description'          => $parsedData['clean_description'],
                            'document'             => $parsedData['document'],
                            'statement_identifier' => $stmtIdentifier,
                            'counterparty_name'    => $counterparty,
                            'movement_type'        => $movementType,
                            'amount'               => $amount,
                            'type'                 => 'debit',
                        ]);

                        $matches = $scoringService->findMatchesFor($tempStmt)
                            ->filter(fn ($m) => !in_array($m['candidate']->id, $claimedPayableIds, true))
                            ->values();

                        if ($matches->isNotEmpty() && ($matches->first()['score'] ?? 0) >= 95) {
                            $cand = $matches->first()['candidate'];
                            $claimedPayableIds[] = $cand->id;
                            $candDesc = $cand->description ?? ("Holerite #" . $cand->id); $autoAction = "Auto-conciliar #{$cand->id} ({$candDesc})";
                            $autoActionStatus = 'auto';
                        } else {
                            $recEval = $this->evaluateRecurringExpenseMatch([
                                'bank_date'            => $dateStr,
                                'amount'               => $amount,
                                'raw_memo'             => $rawMemo,
                                'clean_description'    => $parsedData['clean_description'],
                                'counterparty_name'    => $counterparty,
                                'statement_identifier' => $stmtIdentifier,
                            ], $matchedSupplier, $claimedPayableIds);

                            if ($recEval['status'] === 'matched') {
                                if (!empty($recEval['existing_payable'])) {
                                    $claimedPayableIds[] = $recEval['existing_payable']->id;
                                }
                                $autoAction = $recEval['action_label'];
                                $autoActionStatus = 'auto';
                            } elseif ($recEval['status'] === 'ambiguous') {
                                $autoAction = $recEval['action_label'];
                                $autoActionStatus = 'pending_external';
                            } elseif ($movementType === BankMovementType::BANK_FEE->value) {
                                $autoAction = 'Auto-lançar Tarifa Bancária';
                                $autoActionStatus = 'auto';
                            }
                        }
                    }
                } elseif ($movementType === BankMovementType::CARD_IN->value) {
                    $tempStmt = new BankStatement([
                        'bank_account_id'   => $account->id,
                        'bank_date'         => $dateStr,
                        'description'       => $parsedData['clean_description'],
                        'counterparty_name' => $counterparty,
                        'movement_type'     => $movementType,
                        'amount'            => $amount,
                        'type'              => 'credit',
                    ]);
                    $cardEval = $cardService->matchSettlementGroupForStatement($tempStmt);
                    if ($cardEval && $cardEval['matched']) {
                        $cnt = $cardEval['group']->count();
                        $autoAction = "Auto-conciliar Lote Cartão ({$cnt} " . ($cnt === 1 ? 'venda' : 'vendas') . ")";
                        $autoActionStatus = 'auto';
                    } elseif ($cardEval && !empty($cardEval['reason'])) {
                        $autoAction = $cardEval['reason'];
                        $autoActionStatus = 'pending_external';
                    }
                }

                if ($movementType === BankMovementType::OTHER->value) {
                    $unclassifiedMemos[] = [
                        'file'      => $resolved['filename'],
                        'bank_date' => $dateStr,
                        'amount'    => $amount,
                        'raw_memo'  => $rawMemo,
                    ];
                }

                if ($amount >= 0) {
                    $totalInflow += $amount;
                } else {
                    $totalOutflow += abs($amount);
                }

                $newTransactions[] = [
                    'file'                 => $resolved['filename'],
                    'fitid'                => $fitid,
                    'synthetic_fitid'      => $syntheticFitid,
                    'bank_date'            => $dateStr,
                    'raw_memo'             => $rawMemo,
                    'clean_description'    => $parsedData['clean_description'],
                    'document'             => $parsedData['document'],
                    'statement_identifier' => $stmtIdentifier,
                    'movement_type'        => $movementType,
                    'counterparty_name'    => $counterparty,
                    'amount'               => $amount,
                    'type'                 => $amount >= 0 ? 'credit' : 'debit',
                    'auto_action'          => $autoAction,
                    'auto_action_status'   => $autoActionStatus,
                ];
                $fileNewCount++;
            }

            $filesSummary[] = [
                'filename'   => $resolved['filename'],
                'total'      => count($blocks),
                'new'        => $fileNewCount,
                'duplicates' => $fileDupCount,
                'errors'     => $fileErrCount,
            ];
        }

        // 2ª Passada: Simula pareamento em lote para entradas PIX de clientes (pix_in)
        $pixCandidates = array_values(array_filter(
            $newTransactions,
            fn ($t) => $t['amount'] > 0 && in_array($t['movement_type'], [BankMovementType::PIX_IN->value, BankMovementType::OTHER->value], true)
        ));

        if (!empty($pixCandidates)) {
            $pixMatches = $pixService->previewPixMatchesForStatements($pixCandidates);
            foreach ($newTransactions as &$tx) {
                if (isset($pixMatches[$tx['fitid']])) {
                    $m = $pixMatches[$tx['fitid']];
                    $orderPart = $m['order_id'] ? "Pedido #{$m['order_id']}" : "Pgto #{$m['payment_id']}";
                    $tx['auto_action'] = "Auto-conciliar PIX {$orderPart} ({$m['customer_name']})";
                    $tx['auto_action_status'] = 'auto';
                }
            }
            unset($tx);
        }

        return [
            'files_summary'      => $filesSummary,
            'new_transactions'   => $newTransactions,
            'duplicates'         => $duplicates,
            'malformed_blocks'   => $malformedBlocks,
            'unclassified_memos' => $unclassifiedMemos,
            'total_inflow'       => round($totalInflow, 2),
            'total_outflow'      => round($totalOutflow, 2),
        ];
    }

    /**
     * Persiste as transações analisadas no banco e dispara conciliações automáticas.
     */
    public function importFiles(array $fileEntries, BankAccount $account): array
    {
        $analysis = $this->analyzeFiles($fileEntries, $account);

        $importedCount = 0;
        $autoFeesCount = 0;
        $autoMatchesCount = 0;
        $autoRecurringCount = 0;
        $autoPersonalCount = 0;
        $importedDates = [];

        $scoringService = new BankMatchScoringService();

        foreach ($analysis['new_transactions'] as $row) {
            $stmt = BankStatement::firstOrCreate(
                ['fitid' => $row['fitid']],
                [
                    'bank_account_id'      => $account->id,
                    'bank_date'            => $row['bank_date'],
                    'description'          => $row['clean_description'],
                    'document'             => $row['document'],
                    'statement_identifier' => $row['statement_identifier'],
                    'movement_type'        => $row['movement_type'],
                    'counterparty_name'    => $row['counterparty_name'],
                    'amount'               => $row['amount'],
                    'type'                 => $row['type'],
                    'status'               => 'pending',
                ]
            );

            if (!$stmt->wasRecentlyCreated) {
                continue;
            }

            $importedCount++;
            $importedDates[] = $row['bank_date'];

            if ((float) $stmt->amount < 0) {
                $matchedSupplier = $this->findMatchingSupplier($stmt->document, $stmt->counterparty_name, $stmt->description);
                $matchedRule = $this->findMatchingRule($row['raw_memo'], $stmt->counterparty_name, $stmt->description);

                if ($matchedSupplier && $matchedSupplier->is_personal) {
                    $desc = 'Uso Pessoal: ' . ($stmt->description ?: $stmt->counterparty_name);
                    $payable = AccountPayable::create([
                        'description'            => $desc,
                        'supplier_id'            => $matchedSupplier->id,
                        'amount'                 => abs((float) $stmt->amount),
                        'due_date'               => $stmt->bank_date,
                        'is_personal'            => true,
                        'accounting_category'    => AccountingCategory::PARTNERS->value,
                        'accounting_subcategory' => AccountingSubcategory::PERSONAL->value,
                    ]);

                    BankReconciliationService::finalize($stmt, $payable, $matchedSupplier->id, $desc);
                    $autoPersonalCount++;
                    continue;
                }

                if ($matchedRule && $matchedRule->auto_reconcile) {
                    $this->applyReconciliationRule($stmt, $matchedRule);
                    $autoMatchesCount++;
                    continue;
                }

                $loanEval = $this->evaluateLoanInstallmentMatch(
                    $stmt->movement_type?->value ?? (string) $stmt->movement_type,
                    $stmt->statement_identifier,
                    (string) $stmt->description,
                    $stmt->bank_date->toDateString(),
                    (float) $stmt->amount
                );
                if ($loanEval['status'] === 'matched' && !empty($loanEval['installment'])) {
                    $this->settleLoanInstallmentStatement($stmt, $loanEval['installment']);
                    $autoMatchesCount++;
                    continue;
                }

                $matches = $scoringService->findMatchesFor($stmt);
                if ($matches->isNotEmpty() && ($matches->first()['score'] ?? 0) >= 95) {
                    $payable = $matches->first()['candidate'];
                    BankReconciliationService::finalize($stmt, $payable, $payable instanceof \App\Models\AccountPayable ? $payable->supplier_id : null);
                    $autoMatchesCount++;
                    continue;
                }

                $recEval = $this->evaluateRecurringExpenseMatch([
                    'bank_date'            => $row['bank_date'],
                    'amount'               => $row['amount'],
                    'raw_memo'             => $row['raw_memo'],
                    'clean_description'    => $stmt->description,
                    'counterparty_name'    => $stmt->counterparty_name,
                    'statement_identifier' => $stmt->statement_identifier,
                ], $matchedSupplier);

                if ($recEval['status'] === 'matched') {
                    $this->settleRecurringExpenseStatement($stmt, $recEval['recurring'], $recEval['existing_payable']);
                    $autoRecurringCount++;
                    continue;
                }

                if (($stmt->movement_type?->value ?? $stmt->movement_type) === BankMovementType::BANK_FEE->value) {
                    $desc = $stmt->description ?: 'Tarifa Bancária';
                    $payable = AccountPayable::create([
                        'description'            => $desc,
                        'amount'                 => abs((float) $stmt->amount),
                        'due_date'               => $stmt->bank_date,
                        'accounting_category'    => AccountingCategory::ADMINISTRATIVE->value,
                        'accounting_subcategory' => AccountingSubcategory::BANK_FEES->value,
                    ]);

                    BankReconciliationService::finalize($stmt, $payable, null, $desc);
                    $autoFeesCount++;
                }
            }
        }

        $autoPixCount = 0;
        $autoCardBatches = 0;

        if ($importedCount > 0 && !empty($importedDates)) {
            $earliestDate = Carbon::parse(min($importedDates))->subDays(2)->toDateString();

            $pixRes = app(PixBankReconciliationService::class)
                ->reconcilePendingPixSince($earliestDate);
            $autoPixCount = (int) ($pixRes['reconciled'] ?? 0);

            $cardRes = app(CardSettlementReconciliationService::class)
                ->reconcilePendingCardSettlementsSince($earliestDate);
            $autoCardBatches = (int) ($cardRes['batches'] ?? 0);
        }

        // Reavalia também lançamentos duplicados deste OFX que ainda estejam 'pending' no banco
        $dupFitids = array_values(array_unique(array_filter(array_column($analysis['duplicates'], 'fitid'))));
        if (!empty($dupFitids)) {
            $reproc = $this->reprocessPending($dupFitids, $account->id, $fileEntries);
            $autoFeesCount      += $reproc['auto_fees_count'];
            $autoMatchesCount   += $reproc['auto_matches_count'];
            $autoRecurringCount += $reproc['auto_recurring_count'];
            $autoPersonalCount  += $reproc['auto_personal_count'];
            $autoPixCount       += $reproc['auto_pix_count'];
            $autoCardBatches    += $reproc['auto_card_batches'];
        }

        $summary = [
            'account_id'           => $account->id,
            'account_name'         => $account->name,
            'imported_count'       => $importedCount,
            'auto_fees_count'      => $autoFeesCount,
            'auto_matches_count'   => $autoMatchesCount + $autoRecurringCount,
            'auto_recurring_count' => $autoRecurringCount,
            'auto_personal_count'  => $autoPersonalCount,
            'auto_pix_count'       => $autoPixCount,
            'auto_card_batches'    => $autoCardBatches,
            'duplicates_count'     => count($analysis['duplicates']),
            'malformed_count'      => count($analysis['malformed_blocks']),
        ];

        \Illuminate\Support\Facades\Log::channel('financial')->info('Importação OFX concluída', $summary);
        unset($summary['account_id'], $summary['account_name']);

        return $summary;
    }

    /**
     * Reavalia lançamentos já existentes com status 'pending' sem criar duplicatas.
     */
    public function reprocessPending(?array $fitids = null, ?int $bankAccountId = null, array $rawFileEntries = []): array
    {
        $parsedMetaByFitid = [];
        if (!empty($rawFileEntries)) {
            $bankAcc = $bankAccountId ? BankAccount::find($bankAccountId) : null;
            $dict = BankDictionaryFactory::make($bankAcc?->bank_code);
            foreach ($rawFileEntries as $entry) {
                $resolved = $this->resolveFileEntry($entry);
                if (!$resolved) continue;
                $rawContent = @file_get_contents($resolved['path']);
                if (!$rawContent) continue;
                if (!mb_check_encoding($rawContent, 'UTF-8')) {
                    $rawContent = mb_convert_encoding($rawContent, 'UTF-8', 'Windows-1252, ISO-8859-1');
                }
                $blocks = explode('<STMTTRN>', $rawContent);
                array_shift($blocks);
                foreach ($blocks as $txn) {
                    preg_match('/<FITID>([^<\r\n]+)/i', $txn, $fidRaw);
                    preg_match('/<MEMO>([^<\r\n]+)/i', $txn, $memRaw);
                    preg_match('/<NAME>([^<\r\n]+)/i', $txn, $nameRaw);
                    $f = trim((string) ($fidRaw[1] ?? ''));
                    $m = trim((string) ($memRaw[1] ?? $nameRaw[1] ?? ''));
                    if ($f !== '' && $m !== '') {
                        $parsedMetaByFitid[$f] = $dict->parseMemo($m);
                    }
                }
            }
        }

        $pending = BankStatement::with('bankAccount')
            ->where('status', 'pending')
            ->when(!empty($fitids), fn ($q) => $q->whereIn('fitid', $fitids))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->orderBy('bank_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $autoFeesCount = 0;
        $autoMatchesCount = 0;
        $autoRecurringCount = 0;
        $autoPersonalCount = 0;
        $claimedPayableIds = [];
        $dates = [];

        $scoringService = new BankMatchScoringService();

        foreach ($pending as $stmt) {
            $dates[] = $stmt->bank_date->toDateString();

            // Atualiza metadados caso o OFX reenviado traga statement_identifier/tipo mais preciso
            if (isset($parsedMetaByFitid[$stmt->fitid])) {
                $meta = $parsedMetaByFitid[$stmt->fitid];
                $updates = [];
                if (empty($stmt->statement_identifier) && !empty($meta['statement_identifier'])) {
                    $updates['statement_identifier'] = $meta['statement_identifier'];
                    $updates['description'] = $meta['clean_description'];
                }
                if (($stmt->movement_type?->value ?? (string) $stmt->movement_type) === BankMovementType::OTHER->value && ($meta['movement_type'] ?? 'other') !== 'other') {
                    $updates['movement_type'] = $meta['movement_type'];
                    $updates['description'] = $meta['clean_description'];
                    $updates['counterparty_name'] = $stmt->counterparty_name ?: ($meta['name'] ?? null);
                }
                if (!empty($updates)) {
                    $stmt->update($updates);
                    $stmt->refresh();
                }
            } else {
                $currentType = $stmt->movement_type?->value ?? (string) $stmt->movement_type;
                if ($currentType === BankMovementType::OTHER->value && !empty($stmt->description)) {
                    $dict = BankDictionaryFactory::make($stmt->bankAccount?->bank_code);
                    $reparsed = $dict->parseMemo($stmt->description);
                    if (($reparsed['movement_type'] ?? 'other') !== BankMovementType::OTHER->value) {
                        $stmt->update([
                            'description'          => $reparsed['clean_description'],
                            'document'             => $stmt->document ?: ($reparsed['document'] ?? null),
                            'statement_identifier' => $stmt->statement_identifier ?: ($reparsed['statement_identifier'] ?? null),
                            'counterparty_name'    => $stmt->counterparty_name ?: ($reparsed['name'] ?? null),
                            'movement_type'        => $reparsed['movement_type'],
                        ]);
                        $stmt->refresh();
                    }
                }
            }

            if ((float) $stmt->amount >= 0) {
                continue;
            }

            $matchedSupplier = $this->findMatchingSupplier($stmt->document, $stmt->counterparty_name, $stmt->description);
            $matchedRule = $this->findMatchingRule((string) $stmt->description, $stmt->counterparty_name, $stmt->description);

            if ($matchedSupplier && $matchedSupplier->is_personal) {
                $desc = 'Uso Pessoal: ' . ($stmt->description ?: $stmt->counterparty_name);
                $payable = AccountPayable::create([
                    'description'            => $desc,
                    'supplier_id'            => $matchedSupplier->id,
                    'amount'                 => abs((float) $stmt->amount),
                    'due_date'               => $stmt->bank_date,
                    'is_personal'            => true,
                    'accounting_category'    => AccountingCategory::PARTNERS->value,
                    'accounting_subcategory' => AccountingSubcategory::PERSONAL->value,
                ]);

                BankReconciliationService::finalize($stmt, $payable, $matchedSupplier->id, $desc);
                $autoPersonalCount++;
                continue;
            }

            if ($matchedRule && $matchedRule->auto_reconcile) {
                $this->applyReconciliationRule($stmt, $matchedRule);
                $autoMatchesCount++;
                continue;
            }

            $loanEval = $this->evaluateLoanInstallmentMatch(
                $stmt->movement_type?->value ?? (string) $stmt->movement_type,
                $stmt->statement_identifier,
                (string) $stmt->description,
                $stmt->bank_date->toDateString(),
                (float) $stmt->amount,
                $claimedPayableIds
            );
            if ($loanEval['status'] === 'matched' && !empty($loanEval['installment'])) {
                if (!empty($loanEval['payable_id'])) {
                    $claimedPayableIds[] = $loanEval['payable_id'];
                }
                $this->settleLoanInstallmentStatement($stmt, $loanEval['installment']);
                $autoMatchesCount++;
                continue;
            }

            $matches = $scoringService->findMatchesFor($stmt)
                ->filter(fn ($m) => !in_array($m['candidate']->id, $claimedPayableIds, true))
                ->values();

            if ($matches->isNotEmpty() && ($matches->first()['score'] ?? 0) >= 95) {
                $payable = $matches->first()['candidate'];
                $claimedPayableIds[] = $payable->id;
                BankReconciliationService::finalize($stmt, $payable, $payable instanceof \App\Models\AccountPayable ? $payable->supplier_id : null);
                $autoMatchesCount++;
                continue;
            }

            $recEval = $this->evaluateRecurringExpenseMatch([
                'bank_date'            => $stmt->bank_date->toDateString(),
                'amount'               => $stmt->amount,
                'raw_memo'             => (string) $stmt->description,
                'clean_description'    => $stmt->description,
                'counterparty_name'    => $stmt->counterparty_name,
                'statement_identifier' => $stmt->statement_identifier,
            ], $matchedSupplier, $claimedPayableIds);

            if ($recEval['status'] === 'matched') {
                if (!empty($recEval['existing_payable'])) {
                    $claimedPayableIds[] = $recEval['existing_payable']->id;
                }
                $this->settleRecurringExpenseStatement($stmt, $recEval['recurring'], $recEval['existing_payable']);
                $autoRecurringCount++;
                continue;
            }

            if (($stmt->movement_type?->value ?? $stmt->movement_type) === BankMovementType::BANK_FEE->value) {
                $desc = $stmt->description ?: 'Tarifa Bancária';
                $payable = AccountPayable::create([
                    'description'            => $desc,
                    'amount'                 => abs((float) $stmt->amount),
                    'due_date'               => $stmt->bank_date,
                    'accounting_category'    => AccountingCategory::ADMINISTRATIVE->value,
                    'accounting_subcategory' => AccountingSubcategory::BANK_FEES->value,
                ]);

                BankReconciliationService::finalize($stmt, $payable, null, $desc);
                $autoFeesCount++;
            }
        }

        $autoPixCount = 0;
        $autoCardBatches = 0;

        if (!empty($dates)) {
            $earliestDate = Carbon::parse(min($dates))->subDays(2)->toDateString();

            $pixRes = app(PixBankReconciliationService::class)
                ->reconcilePendingPixSince($earliestDate);
            $autoPixCount = (int) ($pixRes['reconciled'] ?? 0);

            $cardRes = app(CardSettlementReconciliationService::class)
                ->reconcilePendingCardSettlementsSince($earliestDate);
            $autoCardBatches = (int) ($cardRes['batches'] ?? 0);
        }

        return [
            'auto_fees_count'      => $autoFeesCount,
            'auto_matches_count'   => $autoMatchesCount,
            'auto_recurring_count' => $autoRecurringCount,
            'auto_personal_count'  => $autoPersonalCount,
            'auto_pix_count'       => $autoPixCount,
            'auto_card_batches'    => $autoCardBatches,
        ];
    }

    /**
     * Avalia se um débito bancário corresponde a uma Despesa Recorrente em Débito Automático
     * (ou conta variável pré-gerada com R$ 0,00), diferenciando múltiplas UCs pelo contract_number / ID.
     */
    private function evaluateRecurringExpenseMatch(array $txRow, ?Supplier $matchedSupplier, array $excludePayableIds = []): array
    {
        if ($this->recurringCache === null) {
            $this->recurringCache = RecurringExpense::with('supplier')
                ->where('is_active', true)
                ->get();
        }

        if ($this->recurringCache->isEmpty()) {
            return ['status' => 'none'];
        }

        $bankDate = Carbon::parse($txRow['bank_date']);
        $absAmount = round(abs((float) $txRow['amount']), 2);
        $rawStmtId = (string) ($txRow['statement_identifier'] ?? '');
        if ($rawStmtId === '' && preg_match('/\b(?:ID|UC)\s*[:\-]?\s*([A-Z0-9\-\/]{4,25})/i', (string) ($txRow['clean_description'] ?? ''), $mFallbackId)) {
            $rawStmtId = $mFallbackId[1];
        }
        $stmtIdNorm = preg_replace('/[^a-z0-9]/i', '', $rawStmtId);

        $autoDebitRecs = $this->recurringCache->filter(function (RecurringExpense $r) {
            $modeVal = $r->settlement_mode instanceof PayableSettlementMode
                ? $r->settlement_mode->value
                : (string) $r->settlement_mode;
            return $modeVal === PayableSettlementMode::AUTO_DEBIT->value || (bool) $r->is_auto_debit;
        })->values();

        $matchedRec = null;

        // 1. Prioridade Máxima: Correspondência exata por Cód. Cliente / Contrato (UC) vs ID do extrato
        if (strlen($stmtIdNorm) >= 4 && $autoDebitRecs->isNotEmpty()) {
            $pool = $autoDebitRecs;
            if ($matchedSupplier) {
                $familyIds = array_map('intval', !empty($matchedSupplier->family_ids) ? $matchedSupplier->family_ids : [$matchedSupplier->id]);
                $supPool = $autoDebitRecs->filter(fn ($r) => in_array((int) $r->supplier_id, $familyIds, true))->values();
                if ($supPool->isNotEmpty()) {
                    $pool = $supPool;
                }
            }

            $contractMatches = $pool->filter(function (RecurringExpense $r) use ($stmtIdNorm) {
                $recCode = preg_replace('/[^a-z0-9]/i', '', (string) $r->contract_number);
                if (strlen($recCode) < 4) {
                    return false;
                }
                return $recCode === $stmtIdNorm
                    || str_ends_with($recCode, $stmtIdNorm)
                    || str_ends_with($stmtIdNorm, $recCode);
            })->values();

            if ($contractMatches->count() === 1) {
                $matchedRec = $contractMatches->first();
            }
        }

        // 2. Correspondência por Fornecedor em Débito Automático
        if (!$matchedRec && $matchedSupplier && $autoDebitRecs->isNotEmpty()) {
            $familyIds = array_map('intval', !empty($matchedSupplier->family_ids) ? $matchedSupplier->family_ids : [$matchedSupplier->id]);
            $supRecs = $autoDebitRecs->filter(fn ($r) => in_array((int) $r->supplier_id, $familyIds, true))->values();

            if ($supRecs->count() === 1) {
                $matchedRec = $supRecs->first();
            } elseif ($supRecs->count() > 1) {
                $ym = $bankDate->format('Y-m');

                // Filtra contratos que ainda não foram reivindicados nem pagos neste mês (YYYY-MM)
                $unclaimedSupRecs = $supRecs->filter(function (RecurringExpense $r) use ($ym, $bankDate) {
                    $claimKey = "{$r->id}_{$ym}";
                    if (isset($this->claimedRecurringMonthKeys[$claimKey])) {
                        return false;
                    }
                    $alreadyPaidInMonth = AccountPayable::where('recurring_expense_id', $r->id)
                        ->where('status', 'paid')
                        ->whereBetween('due_date', [
                            $bankDate->copy()->startOfMonth()->toDateString(),
                            $bankDate->copy()->endOfMonth()->toDateString(),
                        ])
                        ->exists();
                    return !$alreadyPaidInMonth;
                })->values();

                $pool = $unclaimedSupRecs->isNotEmpty() ? $unclaimedSupRecs : $supRecs;

                if ($pool->count() === 1) {
                    $matchedRec = $pool->first();
                } else {
                    // Tenta desempatar pelo dia de vencimento (±3 dias)
                    $dayMatches = $pool->filter(function (RecurringExpense $r) use ($bankDate) {
                        if (empty($r->due_day)) return false;
                        return abs((int) $r->due_day - (int) $bankDate->day) <= 3;
                    })->values();

                    if ($dayMatches->count() === 1) {
                        $matchedRec = $dayMatches->first();
                    } else {
                        // Desempate por perfil histórico de consumo (ex: Água Casa ~R$ 130 vs Água Lanchonete ~R$ 360)
                        $scoredByHistory = $pool->map(function (RecurringExpense $r) use ($absAmount) {
                            if (!array_key_exists($r->id, $this->recurringAvgCache)) {
                                $avg = (float) AccountPayable::where('recurring_expense_id', $r->id)
                                    ->where('amount', '>', 0)
                                    ->avg('amount');
                                $this->recurringAvgCache[$r->id] = $avg > 0 ? $avg : (float) ($r->default_amount ?? 0);
                            }
                            $refAmt = $this->recurringAvgCache[$r->id];
                            $relDiff = $refAmt > 0 ? abs($absAmount - $refAmt) / $refAmt : 999.0;
                            return ['rec' => $r, 'ref' => $refAmt, 'rel_diff' => $relDiff];
                        })->filter(fn ($item) => $item['ref'] > 0)
                          ->sortBy('rel_diff')
                          ->values();

                        if ($scoredByHistory->count() >= 2) {
                            $best = $scoredByHistory[0];
                            $second = $scoredByHistory[1];
                            if ($best['rel_diff'] < ($second['rel_diff'] * 0.75)) {
                                $matchedRec = $best['rec'];
                            }
                        }

                        if (!$matchedRec) {
                            return [
                                'status'       => 'ambiguous',
                                'action_label' => "{$supRecs->count()} contratos Débito Automático p/ {$matchedSupplier->name} — preencha o Cód. Contrato/UC",
                            ];
                        }
                    }
                }
            }
        }

        // 3. Correspondência pelo Nome da Despesa Recorrente (quando o OFX não traz CNPJ)
        if (!$matchedRec && !$matchedSupplier && $autoDebitRecs->isNotEmpty()) {
            $haystack = preg_replace(
                '/[^a-z0-9]/',
                '',
                Str::ascii(mb_strtolower(($txRow['counterparty_name'] ?? '') . ' ' . ($txRow['clean_description'] ?? '')))
            );

            if (strlen($haystack) >= 5) {
                $nameMatches = $autoDebitRecs->filter(function (RecurringExpense $r) use ($haystack) {
                    $recName = preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower((string) $r->name)));
                    return strlen($recName) >= 5 && str_contains($haystack, $recName);
                })->values();

                if ($nameMatches->count() === 1) {
                    $matchedRec = $nameMatches->first();
                }
            }
        }

        // 4. Fallback para Recorrente Variável pré-gerada com R$ 0,00 (ex.: telefonia/internet paga via PIX)
        if (!$matchedRec && $matchedSupplier) {
            $familyIds = array_map('intval', !empty($matchedSupplier->family_ids) ? $matchedSupplier->family_ids : [$matchedSupplier->id]);
            $varRecs = $this->recurringCache->filter(function (RecurringExpense $r) use ($familyIds) {
                return in_array((int) $r->supplier_id, $familyIds, true)
                    && ($r->value_type?->value ?? (string) $r->value_type) === 'variable';
            })->values();

            if ($varRecs->count() === 1) {
                $candidateVar = $varRecs->first();
                $zeroPayable = $this->findPendingRecurringPayable($candidateVar->id, $bankDate, $excludePayableIds, true);
                if ($zeroPayable) {
                    $matchedRec = $candidateVar;
                }
            }
        }

        if (!$matchedRec) {
            return ['status' => 'none'];
        }

        $this->claimedRecurringMonthKeys["{$matchedRec->id}_" . $bankDate->format('Y-m')] = true;

        $existingPayable = $this->findPendingRecurringPayable($matchedRec->id, $bankDate, $excludePayableIds, false);

        if ($existingPayable) {
            $wasZero = (float) $existingPayable->amount <= 0.01;
            $amtFmt = number_format($absAmount, 2, ',', '.');
            $detail = $wasZero
                ? "Título #{$existingPayable->id} atualizado p/ R$ {$amtFmt}"
                : "Título #{$existingPayable->id}";

            return [
                'status'           => 'matched',
                'recurring'        => $matchedRec,
                'existing_payable' => $existingPayable,
                'action_label'     => "Auto-conciliar Recorrente: {$matchedRec->name} ({$detail})",
            ];
        }

        return [
            'status'           => 'matched',
            'recurring'        => $matchedRec,
            'existing_payable' => null,
            'action_label'     => "Auto-lançar e Conciliar Recorrente: {$matchedRec->name}",
        ];
    }

    private function findPendingRecurringPayable(int $recurringExpenseId, Carbon $bankDate, array $excludeIds = [], bool $onlyZeroAmount = false): ?AccountPayable
    {
        $minDate = $bankDate->copy()->subDays(20)->toDateString();
        $maxDate = $bankDate->copy()->addDays(15)->toDateString();

        return AccountPayable::where('recurring_expense_id', $recurringExpenseId)
            ->where('status', 'pending')
            ->whereBetween('due_date', [$minDate, $maxDate])
            ->when(!empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->when($onlyZeroAmount, fn ($q) => $q->where('amount', '<=', 0.01))
            ->orderByRaw('ABS(DATEDIFF(due_date, ?)) ASC', [$bankDate->toDateString()])
            ->first();
    }

    private function settleRecurringExpenseStatement(BankStatement $stmt, RecurringExpense $rec, ?AccountPayable $preMatchedPayable = null): void
    {
        DB::transaction(function () use ($stmt, $rec, $preMatchedPayable) {
            $absAmount = round(abs((float) $stmt->amount), 2);
            $bankDate = Carbon::parse($stmt->bank_date);

            $payable = null;
            if ($preMatchedPayable) {
                $payable = AccountPayable::where('id', $preMatchedPayable->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();
            }

            if (!$payable) {
                $payable = $this->findPendingRecurringPayable($rec->id, $bankDate, [], false);
            }

            if ($payable) {
                $oldAmount = (float) $payable->amount;
                $obs = $payable->observations;
                if (abs($oldAmount - $absAmount) >= 0.01) {
                    $note = '[' . $bankDate->format('d/m/Y') . '] Valor atualizado automaticamente via débito bancário (anterior: R$ '
                        . number_format($oldAmount, 2, ',', '.') . ').';
                    $obs = trim(implode("\n", array_filter([$obs, $note])));
                }

                $payable->update([
                    'amount'          => $absAmount,
                    'settlement_mode' => PayableSettlementMode::AUTO_DEBIT->value,
                    'observations'    => $obs,
                ]);
            } else {
                $idSuffix = $stmt->statement_identifier ? " (ID {$stmt->statement_identifier})" : '';
                $desc = "{$rec->name}{$idSuffix} - " . $bankDate->translatedFormat('F/Y');

                $payable = AccountPayable::create([
                    'recurring_expense_id'   => $rec->id,
                    'supplier_id'            => $rec->supplier_id,
                    'employee_id'            => $rec->employee_id,
                    'description'            => $desc,
                    'amount'                 => $absAmount,
                    'due_date'               => $bankDate->toDateString(),
                    'status'                 => 'pending',
                    'settlement_mode'        => PayableSettlementMode::AUTO_DEBIT->value,
                    'is_personal'            => (bool) $rec->is_personal,
                    'accounting_category'    => $rec->accounting_category ?? $rec->supplier?->accounting_category ?? AccountingCategory::UTILITIES->value,
                    'accounting_subcategory' => $rec->accounting_subcategory ?? $rec->supplier?->accounting_subcategory,
                    'account_holder'         => $rec->account_holder ?? 'company',
                    'paid_by'                => $rec->paid_by ?? 'company',
                    'observations'           => 'Lançado e conciliado automaticamente via Débito Automático no extrato OFX.',
                ]);
            }

            BankReconciliationService::finalize($stmt, $payable, $rec->supplier_id, $payable->description);
            $rec->updateQuietly(['last_generated_at' => now()]);
        });
    }

    /**
     * Busca Fornecedor por CNPJ/CPF normalizado (apenas dígitos) ou correspondência canônica
     * tolerante a truncamento de maquininhas de débito (ex: "FARMACIA PRECO PO").
     */
    private function findMatchingSupplier(?string $doc, ?string $counterparty, ?string $cleanDesc): ?Supplier
    {
        if ($this->suppliersCache === null) {
            $this->suppliersCache = Supplier::where('is_active', true)->get();
        }

        $cleanDoc = preg_replace('/\D/', '', (string) $doc);
        if (strlen($cleanDoc) >= 11) {
            $byDoc = $this->suppliersCache->first(function (Supplier $s) use ($cleanDoc) {
                $supDoc = preg_replace('/\D/', '', (string) $s->document);
                if ($supDoc === '') return false;
                if ($supDoc === $cleanDoc) return true;
                return strlen($cleanDoc) === 14 && strlen($supDoc) === 14 && substr($cleanDoc, 0, 8) === substr($supDoc, 0, 8);
            });
            if ($byDoc) {
                return $byDoc;
            }
        }

        $normalize = fn (?string $s) => preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower(trim((string) $s))));
        $stripPrefix = fn (?string $s) => preg_replace('/^(?:d[ée]bito:|conv[êe]nio\s*\/\s*fatura\s*-\s*)/iu', '', trim((string) $s));

        $bankCandidates = array_values(array_unique(array_filter([
            $normalize($counterparty),
            $normalize($stripPrefix($cleanDesc)),
        ], fn ($v) => strlen($v) >= 5)));

        if (empty($bankCandidates)) {
            return null;
        }

        $orderedSuppliers = $this->suppliersCache->sortByDesc(fn (Supplier $s) => (int) $s->is_personal);

        foreach ($orderedSuppliers as $supplier) {
            $supNames = array_filter([
                $normalize($supplier->name),
                $normalize($supplier->document_name),
            ], fn ($v) => strlen($v) >= 5);

            foreach ($bankCandidates as $bNorm) {
                foreach ($supNames as $sNorm) {
                    if ($bNorm === $sNorm) {
                        return $supplier;
                    }
                    if (strlen($bNorm) >= 8 && str_starts_with($sNorm, $bNorm)) {
                        return $supplier;
                    }
                    if (strlen($sNorm) >= 8 && str_starts_with($bNorm, $sNorm)) {
                        return $supplier;
                    }
                }
            }
        }

        return null;
    }

    private function evaluateLoanInstallmentMatch(
        string $movementType,
        ?string $stmtIdentifier,
        string $cleanDesc,
        string $bankDateStr,
        float $amount,
        array $excludePayableIds = []
    ): array {
        $contractCode = strtoupper(trim((string) $stmtIdentifier));
        if ($contractCode === '' && preg_match('/\b(C[0-9]{5,15})\b/i', $cleanDesc, $mCode)) {
            $contractCode = strtoupper($mCode[1]);
        }

        if ($movementType !== BankMovementType::LOAN_OUT->value && $contractCode === '') {
            return ['status' => 'none'];
        }

        if ($this->loansCache === null) {
            $this->loansCache = LoanContract::with(['installments.accountPayable'])
                ->where('status', 'active')
                ->get();
        }

        $contract = null;
        if ($contractCode !== '') {
            $contract = $this->loansCache->first(fn (LoanContract $c) => strtoupper(trim((string) $c->contract_number)) === $contractCode);
        }
        if (!$contract && $this->loansCache->count() === 1) {
            $contract = $this->loansCache->first();
        }

        if (!$contract) {
            $label = $contractCode !== ''
                ? "Cadastre o Contrato {$contractCode} em Financeiro → Empréstimos & Giro"
                : "Cadastre o Contrato em Financeiro → Empréstimos & Giro";
            return [
                'status'       => 'missing_contract',
                'action_label' => $label,
            ];
        }

        $bankDate = Carbon::parse($bankDateStr);
        $absAmount = round(abs($amount), 2);

        $installment = $contract->installments
            ->filter(function (LoanInstallment $inst) use ($excludePayableIds) {
                if ($inst->status === 'paid') return false;
                if ($inst->account_payable_id && in_array($inst->account_payable_id, $excludePayableIds, true)) return false;
                return true;
            })
            ->sortBy(function (LoanInstallment $inst) use ($bankDate, $absAmount) {
                $amtDiff = abs((float) $inst->total_amount - $absAmount) < 0.05 ? 0 : 1000;
                $dayDiff = $inst->due_date ? abs($inst->due_date->diffInDays($bankDate)) : 999;
                return $amtDiff + $dayDiff;
            })
            ->first();

        if (!$installment) {
            return ['status' => 'none'];
        }

        $dayDiff = $installment->due_date ? abs($installment->due_date->diffInDays($bankDate)) : 999;
        if ($dayDiff > 25 && abs((float) $installment->total_amount - $absAmount) >= 0.05) {
            return ['status' => 'none'];
        }

        $prinFmt = number_format((float) $installment->principal_portion, 2, ',', '.');
        $intFmt = number_format((float) $installment->interest_portion, 2, ',', '.');

        return [
            'status'       => 'matched',
            'contract'     => $contract,
            'installment'  => $installment,
            'payable_id'   => $installment->account_payable_id,
            'action_label' => "Auto-conciliar Parcela {$installment->installment_number}/{$contract->installments_count} ({$contract->contract_number} • Amort. R$ {$prinFmt} + Juros R$ {$intFmt})",
        ];
    }

    private function settleLoanInstallmentStatement(BankStatement $stmt, LoanInstallment $installment): void
    {
        DB::transaction(function () use ($stmt, $installment) {
            $lockedInst = LoanInstallment::where('id', $installment->id)->lockForUpdate()->first();
            if (!$lockedInst || $lockedInst->status === 'paid') {
                return;
            }

            $payable = $lockedInst->accountPayable;
            if (!$payable) {
                $contract = $lockedInst->contract;
                $payable = AccountPayable::create([
                    'loan_installment_id'    => $lockedInst->id,
                    'description'            => "Parcela {$lockedInst->installment_number}/{$contract->installments_count} - {$contract->description} ({$contract->contract_number})",
                    'installment'            => "{$lockedInst->installment_number}/{$contract->installments_count}",
                    'amount'                 => abs((float) $stmt->amount),
                    'due_date'               => $stmt->bank_date,
                    'status'                 => 'pending',
                    'accounting_category'    => AccountingCategory::FINANCIAL_EXPENSES->value,
                    'accounting_subcategory' => AccountingSubcategory::LOAN_INTEREST->value,
                ]);
                $lockedInst->updateQuietly(['account_payable_id' => $payable->id]);
            } elseif (abs((float) $payable->amount - abs((float) $stmt->amount)) >= 0.01) {
                $payable->update(['amount' => abs((float) $stmt->amount)]);
            }

            BankReconciliationService::finalize($stmt, $payable, null, $payable->description);
        });
    }

    private function findMatchingRule(string $rawMemo, ?string $counterparty, ?string $cleanDesc): ?BankReconciliationRule
    {
        if ($this->rulesCache === null) {
            // Prioriza termos mais específicos (mais longos) e regras pessoais
            $this->rulesCache = BankReconciliationRule::with('supplier')
                ->orderByRaw('LENGTH(COALESCE(search_term, "")) DESC')
                ->get();
        }

        if ($this->rulesCache->isEmpty()) {
            return null;
        }

        $norm = fn (?string $s) => trim((string) preg_replace('/\s+/', ' ', Str::ascii(mb_strtolower((string) $s))));
        $haystack = $norm("{$rawMemo} {$counterparty} {$cleanDesc}");
        $counterNorm = $norm($counterparty);
        $descNorm = $norm($cleanDesc);

        foreach ($this->rulesCache as $rule) {
            $term = $norm($rule->search_term ?? $rule->keyword ?? $rule->pattern ?? '');
            if ($term === '') {
                continue;
            }

            $matchType = (string) ($rule->match_type ?? 'contains');
            $matched = match ($matchType) {
                'exact'       => $counterNorm === $term || $descNorm === $term,
                'starts_with' => str_starts_with($counterNorm, $term) || str_starts_with($descNorm, $term),
                default       => str_contains($haystack, $term),
            };

            if ($matched) {
                return $rule;
            }
        }

        return null;
    }

    private function applyReconciliationRule(BankStatement $stmt, BankReconciliationRule $rule): void
    {
        $targetType = $rule->target_type?->value ?? (string) $rule->target_type;

        if ($targetType === 'transfer' && $rule->target_account_id) {
            $targetAccount = BankAccount::find($rule->target_account_id);
            if ($targetAccount) {
                BankReconciliationService::finalize($stmt, $targetAccount, null, $stmt->description);
                return;
            }
        }

        $isPersonal = $targetType === 'personal'
            || $rule->accounting_category === AccountingCategory::PARTNERS->value
            || $rule->accounting_subcategory === AccountingSubcategory::PERSONAL->value
            || (bool) $rule->supplier?->is_personal;

        // Se for recorrência de fornecedor único (ex: TIM), vincula à recorrência cadastrada se existir
        $recurringId = null;
        if ($targetType === 'recurring' && $rule->supplier_id) {
            $recs = RecurringExpense::where('supplier_id', $rule->supplier_id)->where('is_active', true)->get();
            if ($recs->count() === 1) {
                $recurringId = $recs->first()->id;
            }
        }

        $desc = $stmt->description ?: ($rule->supplier?->name ?? 'Despesa Automática');

        $payable = AccountPayable::create([
            'recurring_expense_id'   => $recurringId,
            'description'            => $desc,
            'supplier_id'            => $rule->supplier_id,
            'amount'                 => abs((float) $stmt->amount),
            'due_date'               => $stmt->bank_date,
            'is_personal'            => $isPersonal,
            'accounting_category'    => $isPersonal
                ? AccountingCategory::PARTNERS->value
                : ($rule->accounting_category ?? $rule->supplier?->accounting_category ?? AccountingCategory::ADMINISTRATIVE->value),
            'accounting_subcategory' => $isPersonal
                ? AccountingSubcategory::PERSONAL->value
                : ($rule->accounting_subcategory ?? $rule->supplier?->accounting_subcategory ?? AccountingSubcategory::GENERAL->value),
        ]);

        BankReconciliationService::finalize($stmt, $payable, $rule->supplier_id, $desc);
    }

    private function parseOfxAmount(string $raw): float
    {
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        return round((float) $raw, 2);
    }

    private function resolveFileEntry(mixed $entry): ?array
    {
        if ($entry instanceof TemporaryUploadedFile) {
            return [
                'filename' => $entry->getClientOriginalName(),
                'path'     => $entry->getRealPath(),
            ];
        }

        if (is_string($entry) && $entry !== '') {
            if (file_exists($entry)) {
                return [
                    'filename' => basename($entry),
                    'path'     => $entry,
                ];
            }
            if (Storage::disk('local')->exists($entry)) {
                return [
                    'filename' => basename($entry),
                    'path'     => Storage::disk('local')->path($entry),
                ];
            }
        }

        return null;
    }
}