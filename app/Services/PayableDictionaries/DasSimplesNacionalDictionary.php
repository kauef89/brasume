<?php

namespace App\Services\PayableDictionaries;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableDocumentType;
use Carbon\Carbon;

class DasSimplesNacionalDictionary implements PayableDictionaryInterface
{
    public function type(): PayableDocumentType
    {
        return PayableDocumentType::DAS_SIMPLES;
    }

    public function matches(string $text): bool
    {
        return (bool) preg_match('/Documento\s+de\s+Arrecada[cç][aã]o/ui', $text)
            && (bool) preg_match('/Simples\s+Nacional/ui', $text);
    }

    public function extract(string $text): array
    {
        preg_match('/(?:Pagar\s+este\s+documento\s+at[eé]|Pagar\s+at[eé]:)\s*(\d{2}\/\d{2}\/\d{4})/ui', $text, $dueDateMatch);
        preg_match('/Valor\s+Total\s+do\s+Documento\s*([\d\.,]+)/ui', $text, $amountMatch);
        preg_match('/N[úu]mero(?:\s+do\s+Documento)?\s*([\d\.\-]{10,})/ui', $text, $numMatch);

        $competencia = '';
        if (preg_match('/\b(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)\s*\/\s*(20\d{2})\b/ui', $text, $compMatch)) {
            $competencia = ucfirst(mb_strtolower($compMatch[1])) . '/' . $compMatch[2];
        } elseif (preg_match('/\b(\d{2}\/20\d{2})\b/', $text, $compNumMatch)) {
            $competencia = $compNumMatch[1];
        }

        return [
            'category'      => AccountingCategory::ADMINISTRATIVE->value,
            'subcategory'   => AccountingSubcategory::TAXES->value,
            'description'   => trim("DAS Simples Nacional {$competencia}"),
            'due_date'      => isset($dueDateMatch[1]) ? Carbon::createFromFormat('d/m/Y', $dueDateMatch[1])->format('Y-m-d') : null,
            'amount'        => isset($amountMatch[1]) ? (float) str_replace(['.', ','], ['', '.'], $amountMatch[1]) : 0.0,
            'supplier_cnpj' => '00.394.460/0058-87',
            'supplier_name' => 'RECEITA FEDERAL DO BRASIL',
            'observations'  => isset($numMatch[1]) ? "Número DAS: {$numMatch[1]}" : null,
        ];
    }
}