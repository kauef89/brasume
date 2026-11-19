<?php

namespace App\Services\PayableDictionaries;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableDocumentType;
use Carbon\Carbon;

class MunicipalFireFeeDictionary implements PayableDictionaryInterface
{
    public function type(): PayableDocumentType
    {
        return PayableDocumentType::MUNICIPAL_TAX;
    }

    public function matches(string $text): bool
    {
        return (bool) preg_match('/CORPO\s+DE\s+BOMBEIROS/ui', $text)
            && (bool) preg_match('/BALNE[ÁA]RIO\s+BARRA\s+DO\s+SUL/ui', $text);
    }

    public function extract(string $text): array
    {
        $barcodeData = GenericBoletoDictionary::decodeBoletoBarcode($text);

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

        $obsParts = [];
        if (preg_match('/Protocolo:\s*([A-Z0-9\-]+)/i', $text, $protoMatch)) {
            $obsParts[] = "Protocolo CBMSC: {$protoMatch[1]}";
        }
        if (!empty($barcodeData['linha_digitavel'])) {
            $obsParts[] = "Linha Digitável: {$barcodeData['linha_digitavel']}";
        }

        return [
            'category'      => AccountingCategory::ADMINISTRATIVE->value,
            'subcategory'   => AccountingSubcategory::TAXES->value,
            'description'   => 'Taxa de Bombeiros / Município de Balneário Barra do Sul',
            'due_date'      => $dueDate,
            'amount'        => $amount,
            'supplier_cnpj' => '95.954.509/0001-80',
            'supplier_name' => 'MUNICIPIO DE BALNEARIO BARRA DO SUL',
            'observations'  => !empty($obsParts) ? implode(' | ', $obsParts) : null,
        ];
    }
}