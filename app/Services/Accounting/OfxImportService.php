<?php

namespace App\Services\Accounting;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\Supplier;
use App\Services\BankDictionaries\BankDictionaryFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class OfxImportService
{
    /**
     * Analisa um ou mais arquivos OFX em memória (Dry Run) sem gravar no banco.
     */
    public function analyzeFiles(array $fileEntries, BankAccount $account): array
    {
        $dictionary = BankDictionaryFactory::make($account->bank_code);
        $scoringService = new BankMatchScoringService();

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

            // Normaliza encoding (bancos brasileiros frequentemente exportam OFX em Windows-1252 / ISO-8859-1)
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

                // Valida data
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

                // Valida valor (suporta tanto -120.50 quanto -120,50 sem truncar centavos)
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

                // Garante FITID mesmo quando o banco omite a tag (evitando perda silenciosa)
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
                $movementType = $parsedData['movement_type'] ?? 'other';

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

                // Simula qual regra automática atuará sobre a linha
                $autoAction = null;
                $matchedSupplier = null;
                if (!empty($parsedData['document'])) {
                    $matchedSupplier = Supplier::where('document', $parsedData['document'])->first();
                }
                if (!$matchedSupplier && $counterparty) {
                    $matchedSupplier = Supplier::where('name', $counterparty)
                        ->orWhere('document_name', $counterparty)
                        ->first();
                }

                if ($matchedSupplier && $matchedSupplier->is_personal && $amount < 0) {
                    $autoAction = "Auto-conciliar Uso Pessoal ({$matchedSupplier->name})";
                } elseif ($movementType === 'bank_fee' && $amount < 0) {
                    $autoAction = 'Auto-lançar Tarifa Bancária';
                } elseif ($amount < 0) {
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
                    }
                }

                if ($movementType === 'other') {
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

            $matchedSupplier = null;
            if ($stmt->document) {
                $matchedSupplier = Supplier::where('document', $stmt->document)->first();
            }
            if (!$matchedSupplier && $stmt->counterparty_name) {
                $matchedSupplier = Supplier::where('name', $stmt->counterparty_name)
                    ->orWhere('document_name', $stmt->counterparty_name)
                    ->first();
            }

            if ($matchedSupplier && $matchedSupplier->is_personal && (float) $stmt->amount < 0) {
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
            } elseif (($stmt->movement_type?->value ?? $stmt->movement_type) === 'bank_fee' && (float) $stmt->amount < 0) {
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
            } elseif ((float) $stmt->amount < 0) {
                $matches = $scoringService->findMatchesFor($stmt);
                if ($matches->isNotEmpty() && ($matches->first()['score'] ?? 0) >= 95) {
                    $payable = $matches->first()['candidate'];
                    BankReconciliationService::finalize($stmt, $payable, $payable->supplier_id);
                    $autoMatchesCount++;
                }
            }
        }

        $autoPixCount = 0;
        $autoCardBatches = 0;

        if ($importedCount > 0) {
            $pixRes = app(PixBankReconciliationService::class)
                ->reconcilePendingPixSince(now()->subDays(12)->toDateString());
            $autoPixCount = (int) ($pixRes['reconciled'] ?? 0);

            $cardRes = app(CardSettlementReconciliationService::class)
                ->reconcilePendingCardSettlementsSince(now()->subDays(12)->toDateString());
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