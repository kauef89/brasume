<?php

namespace App\Services\PayableDictionaries;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableDocumentType;
use Carbon\Carbon;

class GenericBoletoDictionary implements PayableDictionaryInterface
{
    public function type(): PayableDocumentType
    {
        return PayableDocumentType::GENERIC_BOLETO;
    }

    public function matches(string $text): bool
    {
        return self::decodeBoletoBarcode($text) !== null
            || (str_contains($text, 'Autenticação Mecânica') && str_contains($text, 'Nosso Número'));
    }

    public function extract(string $text): array
    {
        $barcodeData = self::decodeBoletoBarcode($text);

        $dueDate = $barcodeData['due_date'] ?? null;
        if (!$dueDate && preg_match('/Vencimento\b.{0,120}?(\d{2}\/\d{2}\/\d{4})/si', $text, $dueDateMatch)) {
            $dueDate = Carbon::createFromFormat('d/m/Y', $dueDateMatch[1])->format('Y-m-d');
        }

        $amount = $barcodeData['amount'] ?? 0.0;
        if ($amount <= 0) {
            if (preg_match('/\(=\)\s*Valor\s+(?:do\s+Documento|Cobrado).{0,80}?([\d\.]+,\d{2})/si', $text, $amountMatch)) {
                $amount = (float) str_replace(['.', ','], ['', '.'], $amountMatch[1]);
            } elseif (preg_match('/Valor\s+do\s+Documento.{0,80}?([\d\.]+,\d{2})/si', $text, $amountMatch2)) {
                $amount = (float) str_replace(['.', ','], ['', '.'], $amountMatch2[1]);
            }
        }

        $supplierName = null;
        $supplierCnpj = null;
        if (preg_match('/Benefici[áa]rio\b.{0,80}?\n\s*([A-Z0-9\s\.\-&\/]{4,60}?)\s+(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/ui', $text, $benMatch)) {
            $supplierName = trim(preg_replace('/\s+/', ' ', $benMatch[1]));
            $supplierCnpj = $benMatch[2];
        }

        $linha = $barcodeData['linha_digitavel'] ?? null;

        return [
            'category'      => AccountingCategory::ADMINISTRATIVE->value,
            'subcategory'   => AccountingSubcategory::GENERAL->value,
            'description'   => $supplierName ? "Boleto - {$supplierName}" : 'Boleto Bancário',
            'due_date'      => $dueDate,
            'amount'        => $amount,
            'supplier_cnpj' => $supplierCnpj,
            'supplier_name' => $supplierName,
            'observations'  => $linha ? "Linha Digitável: {$linha}" : null,
        ];
    }

    /**
     * Decodifica a linha digitável bancária (47 dígitos) extraindo fator FEBRABAN e valor.
     */
    public static function decodeBoletoBarcode(string $text): ?array
    {
        if (!preg_match('/(\d{5}\.?\d{5})\s*(\d{5}\.?\d{6})\s*(\d{5}\.?\d{6})\s*(\d)\s*(\d{14})/', $text, $m)) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $m[0]);
        if (strlen($digits) !== 47) {
            return null;
        }

        $tail = $m[5]; // FFFFVVVVVVVVVV
        $factor = (int) substr($tail, 0, 4);
        $cents = (int) substr($tail, 4, 10);

        $dueDate = null;
        if ($factor >= 1000) {
            // Ciclo FEBRABAN vigente a partir de 22/02/2025 (fator 1000 = 2025-02-22)
            $candidate = Carbon::create(2025, 2, 22)->addDays($factor - 1000);
            if ($candidate->year > now()->year + 5) {
                $candidate = Carbon::create(1997, 10, 7)->addDays($factor);
            }
            $dueDate = $candidate->format('Y-m-d');
        } elseif ($factor > 0) {
            $dueDate = Carbon::create(1997, 10, 7)->addDays($factor)->format('Y-m-d');
        }

        return [
            'linha_digitavel' => $digits,
            'due_date'        => $dueDate,
            'amount'          => round($cents / 100, 2),
        ];
    }
}