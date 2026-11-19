<?php

namespace App\Services\PayableDictionaries;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableDocumentType;
use Carbon\Carbon;

class FgtsDigitalDictionary implements PayableDictionaryInterface
{
    public function type(): PayableDocumentType
    {
        return PayableDocumentType::FGTS_DIGITAL;
    }

    public function matches(string $text): bool
    {
        return (bool) preg_match('/(FGTS\s*Digital|Guia\s+do\s+FGTS|FUNDO\s+DE\s+GARANTIA\s+DO\s+TEMPO\s+DE\s+SERVI[CÇ]O)/ui', $text);
    }

    public function extract(string $text): array
    {
        // Janela ampliada para suportar leitura em colunas do Tesseract OCR
        preg_match('/Pagar\s+este\s+documento\s+at[eé][\s\S]{0,140}?(\d{2}\/\d{2}\/\d{4})/ui', $text, $dueDateMatch);

        $amount = 0.0;
        // 1. Tenta capturar formato padrão com vírgula (ex: 645,42)
        if (preg_match('/(?:Total\s+da\s+Guia|Total\s+FGTS|Valor\s+a\s+recolher):?[\s\S]{0,120}?([\d\.]+,\d{2})/ui', $text, $mAmt1)) {
            $amount = $this->parseMoney($mAmt1[1]);
        }
        // 2. Fallback para OCR quando a vírgula é lida como espaço (ex: "645 42")
        elseif (preg_match('/(?:Valor\s+a\s+recolher|Total\s+da\s+Guia|Total\s+FGTS):?[\s\S]{0,120}?\b(\d{1,3}(?:\.\d{3})*)[,\s](\d{2})(?![\/\d:])/ui', $text, $mAmt2)) {
            $integerPart = str_replace('.', '', $mAmt2[1]);
            $amount = (float) "{$integerPart}.{$mAmt2[2]}";
        }

        $pixPayload = null;
        if (preg_match('/(000201\d{6,}[\s\S]{20,260}?6304[A-Fa-f0-9]{4})/i', $text, $pixMatch)) {
            $pixPayload = preg_replace('/\s+/', '', $pixMatch[1]);
        }

        $competencia = '';
        if (preg_match('/\b(\d{2}\/20\d{2})\b/', $text, $compMatch)) {
            $competencia = " {$compMatch[1]}";
        }

        return [
            'category'      => AccountingCategory::PAYROLL->value,
            'subcategory'   => AccountingSubcategory::PAYROLL->value,
            'description'   => trim("Guia FGTS Digital{$competencia}"),
            'due_date'      => isset($dueDateMatch[1]) ? Carbon::createFromFormat('d/m/Y', $dueDateMatch[1])->format('Y-m-d') : null,
            'amount'        => $amount,
            'supplier_cnpj' => '00.360.305/0001-04',
            'supplier_name' => 'CAIXA ECONOMICA FEDERAL (FGTS)',
            'pix_payload'   => $pixPayload,
            'observations'  => $pixPayload ? "PIX Copia e Cola: {$pixPayload}" : null,
        ];
    }

    private function parseMoney(string $val): float
    {
        return (float) str_replace(['.', ','], ['', '.'], trim($val));
    }
}