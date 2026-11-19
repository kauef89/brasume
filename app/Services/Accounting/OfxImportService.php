<?php

namespace App\Services\Accounting;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\BankMovementType;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Models\BankReconciliationRule;
use App\Models\BankStatement;
use App\Models\Supplier;
use App\Services\BankDictionaries\BankDictionaryFactory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class OfxImportService
{
    private ?Collection $suppliersCache = null;
    private ?Collection $rulesCache = null;

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

                // Simula regras de saída (Uso Pessoal, Tarifas, Regras Cadastradas e Títulos a Pagar)
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
                    } elseif ($movementType === BankMovementType::BANK_FEE->value) {
                        $autoAction = 'Auto-lançar Tarifa Bancária';
                        $autoActionStatus = 'auto';
                    } else {
                        $tempStmt = new BankStatement([
                            'bank_account_id'   => $account->id,
                            'bank_date'         => $dateStr,
                            'description'       => $parsedData['clean_description'],
                            'document'          => $parsedData['document'],
                            'counterparty_name' => $counterparty,
                            'movement_type'     => $movementType,
                            'amount'            => $amount,
                            'type'              => 'debit',
                        ]);
                        $matches = $scoringService->findMatchesFor($tempStmt);
                        if ($matches->isNotEmpty() && ($matches->first()['score'] ?? 0) >= 95) {
                            $cand = $matches->first()['candidate'];
                            $autoAction = "Auto-conciliar Título #{$cand->id} ({$cand->description})";
                            $autoActionStatus = 'auto';
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
                    'statement_identifier' => $parsedData['statement_identifier'] ?? null,
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
                } elseif ($matchedRule && $matchedRule->auto_reconcile) {
                    $this->applyReconciliationRule($stmt, $matchedRule);
                    $autoMatchesCount++;
                } elseif (($stmt->movement_type?->value ?? $stmt->movement_type) === BankMovementType::BANK_FEE->value) {
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
                } else {
                    $matches = $scoringService->findMatchesFor($stmt);
                    if ($matches->isNotEmpty() && ($matches->first()['score'] ?? 0) >= 95) {
                        $payable = $matches->first()['candidate'];
                        BankReconciliationService::finalize($stmt, $payable, $payable->supplier_id);
                        $autoMatchesCount++;
                    }
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

        return [
            'imported_count'      => $importedCount,
            'auto_fees_count'     => $autoFeesCount,
            'auto_matches_count'  => $autoMatchesCount,
            'auto_personal_count' => $autoPersonalCount,
            'auto_pix_count'      => $autoPixCount,
            'auto_card_batches'   => $autoCardBatches,
            'duplicates_count'    => count($analysis['duplicates']),
            'malformed_count'     => count($analysis['malformed_blocks']),
        ];
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
        $stripPrefix = fn (?string $s) => preg_replace('/^d[ée]bito:\s*/iu', '', trim((string) $s));

        $bankCandidates = array_values(array_unique(array_filter([
            $normalize($counterparty),
            $normalize($stripPrefix($cleanDesc)),
        ], fn ($v) => strlen($v) >= 6)));

        if (empty($bankCandidates)) {
            return null;
        }

        // Prioriza fornecedores marcados como pessoais em caso de correspondência por prefixo truncado
        $orderedSuppliers = $this->suppliersCache->sortByDesc(fn (Supplier $s) => (int) $s->is_personal);

        foreach ($orderedSuppliers as $supplier) {
            $supNames = array_filter([
                $normalize($supplier->name),
                $normalize($supplier->document_name),
            ], fn ($v) => strlen($v) >= 6);

            foreach ($bankCandidates as $bNorm) {
                foreach ($supNames as $sNorm) {
                    if ($bNorm === $sNorm) {
                        return $supplier;
                    }
                    // O banco costuma truncar nomes no cartão de débito (ex: 16..20 caracteres)
                    if (strlen($bNorm) >= 10 && str_starts_with($sNorm, $bNorm)) {
                        return $supplier;
                    }
                    if (strlen($sNorm) >= 10 && str_starts_with($bNorm, $sNorm)) {
                        return $supplier;
                    }
                }
            }
        }

        return null;
    }

    private function findMatchingRule(string $rawMemo, ?string $counterparty, ?string $cleanDesc): ?BankReconciliationRule
    {
        if ($this->rulesCache === null) {
            $this->rulesCache = BankReconciliationRule::with('supplier')->get();
        }

        if ($this->rulesCache->isEmpty()) {
            return null;
        }

        $haystack = Str::ascii(mb_strtolower("{$rawMemo} {$counterparty} {$cleanDesc}"));

        foreach ($this->rulesCache as $rule) {
            $keyword = Str::ascii(mb_strtolower(trim((string) ($rule->keyword ?? $rule->pattern ?? ''))));
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
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

        $isPersonal = $targetType === 'personal';
        $desc = $stmt->description ?: ($rule->supplier?->name ?? 'Despesa Automática');

        $payable = AccountPayable::create([
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