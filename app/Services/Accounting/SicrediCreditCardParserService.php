<?php

namespace App\Services\Accounting;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Models\Supplier;
use Carbon\Carbon;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class SicrediCreditCardParserService
{
    public function parseFile(string $filePath, string $extension, BankAccount $bankAccount): array
    {
        $ext = strtolower(trim($extension));

        if ($ext === 'pdf') {
            return $this->parsePdf($filePath, $bankAccount);
        }

        return $this->parseXls($filePath, $bankAccount);
    }

    public function parseXls(string $filePath, BankAccount $bankAccount): array
    {
        if (!class_exists(IOFactory::class)) {
            throw new Exception('A biblioteca PhpSpreadsheet não está instalada.');
        }

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $metadata = [
            'due_date'     => null,
            'total_amount' => 0.0,
            'status'       => 'Fechada',
        ];

        $transactions = [];
        $currentCardId = null;
        $currentLastFour = null;
        $cardsByDigits = $bankAccount->creditCards()->get()->keyBy('last_four_digits');
        $seqCounter = 0;

        foreach ($rows as $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            $col1 = trim((string) ($row[1] ?? ''));
            $col2 = trim((string) ($row[2] ?? ''));

            if ($col0 === '' && $col1 === '' && $col2 === '') {
                continue;
            }

            if (str_contains($col0, 'Data de Vencimento') && preg_match('/(\d{2}\/\d{2}\/\d{4})/', $col2, $mDate)) {
                $metadata['due_date'] = Carbon::createFromFormat('d/m/Y', $mDate[1])->toDateString();
                continue;
            }

            if (str_contains($col0, '(=) Total desta fatura') && $col2 !== '') {
                $metadata['total_amount'] = $this->parseMoney($col2);
                continue;
            }

            if (str_starts_with($col0, 'Situação') && $col2 !== '') {
                $metadata['status'] = $col2;
                continue;
            }

            if (str_starts_with($col0, 'Cartão:')) {
                if (preg_match('/\.(\d{4})\s*-/', $col0, $matches)) {
                    $currentLastFour = $matches[1];
                    $currentCardId = $this->resolveCardId($bankAccount, $cardsByDigits, $currentLastFour, $col0);
                } else {
                    $currentLastFour = null;
                    $currentCardId = null;
                }
                continue;
            }

            if (str_starts_with($col0, 'Total R$') || str_contains($col1, 'Pag Fat Deb Cc')) {
                continue;
            }

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $col0)) {
                $description = preg_replace('/\s+/', ' ', $col1);
                $installment = trim($col2);
                $amount = $this->parseMoney((string) ($row[3] ?? '0'));

                if ($amount <= 0 || str_contains(mb_strtolower($description), 'pag fat')) {
                    continue;
                }

                $seqCounter++;
                $txDate = Carbon::createFromFormat('d/m/Y', $col0)->toDateString();
                $classification = $this->classifyPurchase($description);

                $transactions[] = [
                    'transaction_date'       => $txDate,
                    'description'            => $description,
                    'installment'            => $installment,
                    'amount'                 => $amount,
                    'credit_card_id'         => $currentCardId,
                    'card_last_four'         => $currentLastFour,
                    'supplier_id'            => $classification['supplier_id'],
                    'accounting_category'    => $classification['category'],
                    'accounting_subcategory' => $classification['subcategory'],
                    'unique_hash'            => md5("sicredi_cc|{$metadata['due_date']}|{$currentLastFour}|{$txDate}|{$description}|{$installment}|{$amount}|{$seqCounter}"),
                ];
            }
        }

        if (empty($metadata['due_date'])) {
            throw new Exception('Não foi possível identificar a Data de Vencimento na fatura.');
        }

        if ($metadata['total_amount'] <= 0 && !empty($transactions)) {
            $metadata['total_amount'] = round(array_sum(array_column($transactions, 'amount')), 2);
        }

        return [
            'metadata'     => $metadata,
            'transactions' => $transactions,
        ];
    }

    public function parsePdf(string $filePath, BankAccount $bankAccount): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        $metadata = [
            'due_date'     => null,
            'total_amount' => 0.0,
            'status'       => 'Fechada',
        ];

        if (preg_match('/Data de Vencimento:?\s*(\d{2}\/\d{2}\/\d{4})/ui', $text, $mDue)) {
            $metadata['due_date'] = Carbon::createFromFormat('d/m/Y', $mDue[1])->toDateString();
        }

        if (preg_match('/\(=\)\s*Total desta fatura\s*\(R\$\):?\s*([\d.,]+)/ui', $text, $mTot)) {
            $metadata['total_amount'] = $this->parseMoney($mTot[1]);
        }

        if (preg_match('/Situação:?\s*(Fechada|Aberta|Parcial)/ui', $text, $mStatus)) {
            $metadata['status'] = trim($mStatus[1]);
        }

        $transactions = [];
        $currentCardId = null;
        $currentLastFour = null;
        $cardsByDigits = $bankAccount->creditCards()->get()->keyBy('last_four_digits');
        $seqCounter = 0;

        foreach (explode("\n", $text) as $rawLine) {
            $line = trim(preg_replace('/\s+/', ' ', $rawLine));
            if ($line === '') {
                continue;
            }

            if (str_contains($line, 'Cartão:') && preg_match('/\.(\d{4})\s*-/', $line, $mCard)) {
                $currentLastFour = $mCard[1];
                $currentCardId = $this->resolveCardId($bankAccount, $cardsByDigits, $currentLastFour, $line);
                continue;
            }

            if (str_contains(mb_strtolower($line), 'pag fat deb cc') || str_starts_with($line, 'Total R$')) {
                continue;
            }

            // Padrão linha PDF: DD/MM/AAAA DESCRICAO [01/03] 123,45
            if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+(?:(\d{2}\/\d{2})\s+)?(-?[\d.]+,\d{2})$/', $line, $mRow)) {
                $description = trim($mRow[2]);
                $installment = trim($mRow[3] ?? '01/01');
                $amount = $this->parseMoney($mRow[4]);

                if ($amount <= 0) {
                    continue;
                }

                $seqCounter++;
                $txDate = Carbon::createFromFormat('d/m/Y', $mRow[1])->toDateString();
                $classification = $this->classifyPurchase($description);

                $transactions[] = [
                    'transaction_date'       => $txDate,
                    'description'            => $description,
                    'installment'            => $installment,
                    'amount'                 => $amount,
                    'credit_card_id'         => $currentCardId,
                    'card_last_four'         => $currentLastFour,
                    'supplier_id'            => $classification['supplier_id'],
                    'accounting_category'    => $classification['category'],
                    'accounting_subcategory' => $classification['subcategory'],
                    'unique_hash'            => md5("sicredi_cc|{$metadata['due_date']}|{$currentLastFour}|{$txDate}|{$description}|{$installment}|{$amount}|{$seqCounter}"),
                ];
            }
        }

        if (empty($metadata['due_date'])) {
            throw new Exception('Não foi possível identificar a Data de Vencimento no PDF da fatura.');
        }

        if ($metadata['total_amount'] <= 0 && !empty($transactions)) {
            $metadata['total_amount'] = round(array_sum(array_column($transactions, 'amount')), 2);
        }

        return [
            'metadata'     => $metadata,
            'transactions' => $transactions,
        ];
    }

    private function resolveCardId(BankAccount $bankAccount, $cardsByDigits, string $lastFour, string $headerLine): int
    {
        if ($cardsByDigits->has($lastFour)) {
            return (int) $cardsByDigits->get($lastFour)->id;
        }

        preg_match('/-\s*(.+)$/', $headerLine, $mHolder);
        $holder = isset($mHolder[1]) ? trim($mHolder[1]) : 'TITULAR';

        $card = CreditCard::create([
            'bank_account_id'  => $bankAccount->id,
            'name'             => "Cartão Sicredi (Final {$lastFour})",
            'last_four_digits' => $lastFour,
            'card_type'        => 'physical',
            'holder_name'      => $holder,
            'is_active'        => true,
        ]);

        $cardsByDigits->put($lastFour, $card);
        return (int) $card->id;
    }

    private function parseMoney(string $value): float
    {
        $clean = str_replace(['R$', ' ', '.'], '', trim($value));
        $clean = str_replace(',', '.', $clean);
        return (float) $clean;
    }

    private function classifyPurchase(string $description): array
    {
        $desc = mb_strtolower($description);

        $supplierId = null;
        if (str_contains($desc, 'komprao') || str_contains($desc, 'koch')) {
            $supplierId = Supplier::where('name', 'like', '%Komprão%')->orWhere('name', 'like', '%Koch%')->value('id');
        } elseif (str_contains($desc, 'mercadolivre') || str_contains($desc, 'mercado livre')) {
            $supplierId = Supplier::where('name', 'like', '%Mercado Livre%')->value('id');
        } elseif (str_contains($desc, 'shopee')) {
            $supplierId = Supplier::where('name', 'like', '%Shopee%')->value('id');
        } elseif (str_contains($desc, 'frigelar')) {
            $supplierId = Supplier::where('name', 'like', '%Frigelar%')->value('id');
        }

        if (str_contains($desc, 'komprao') || str_contains($desc, 'koch') || str_contains($desc, 'conservas') || str_contains($desc, 'sdb comercio') || str_contains($desc, 'supermercado') || str_contains($desc, 'atacad')) {
            return [
                'supplier_id' => $supplierId,
                'category'    => AccountingCategory::INGREDIENTS->value,
                'subcategory' => AccountingSubcategory::GENERAL->value,
            ];
        }

        if (str_contains($desc, 'bagbrasil') || str_contains($desc, 'embalag') || str_contains($desc, 'sacola')) {
            return [
                'supplier_id' => $supplierId,
                'category'    => AccountingCategory::PACKAGING->value,
                'subcategory' => AccountingSubcategory::BAGS->value,
            ];
        }

        if (str_contains($desc, 'provin') || str_contains($desc, 'sst') || str_contains($desc, 'medicina')) {
            return [
                'supplier_id' => $supplierId,
                'category'    => AccountingCategory::PAYROLL->value,
                'subcategory' => AccountingSubcategory::HEALTH->value,
            ];
        }

        if (str_contains($desc, 'frigelar') || str_contains($desc, 'manutenc') || str_contains($desc, 'pecas')) {
            return [
                'supplier_id' => $supplierId,
                'category'    => AccountingCategory::MAINTENANCE->value,
                'subcategory' => AccountingSubcategory::EQUIPMENT->value,
            ];
        }

        if (str_contains($desc, 'taxa') || str_contains($desc, 'anuidade') || str_contains($desc, 'iof') || str_contains($desc, 'juros')) {
            return [
                'supplier_id' => $supplierId,
                'category'    => AccountingCategory::ADMINISTRATIVE->value,
                'subcategory' => AccountingSubcategory::BANK_FEES->value,
            ];
        }

        if (str_contains($desc, 'shopee')) {
            return [
                'supplier_id' => $supplierId,
                'category'    => AccountingCategory::PACKAGING->value,
                'subcategory' => AccountingSubcategory::DISPOSABLES->value,
            ];
        }

        return [
            'supplier_id' => $supplierId,
            'category'    => AccountingCategory::ADMINISTRATIVE->value,
            'subcategory' => AccountingSubcategory::GENERAL->value,
        ];
    }
}