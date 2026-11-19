<?php

namespace App\Services\PayableDictionaries;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableDocumentType;
use Carbon\Carbon;

class DarfInssDictionary implements PayableDictionaryInterface
{
    public function type(): PayableDocumentType
    {
        return PayableDocumentType::DARF_INSS;
    }

    public function matches(string $text): bool
    {
        $isDarf = (bool) preg_match('/Documento\s+de\s+Arrecada[cç][aã]o\s+de\s+Receitas\s+Federais/ui', $text)
            || (bool) preg_match('/Receita\s+Federal/ui', $text);

        $isInss = (bool) preg_match('/(SEGURADO|INSS|CONTRIB\s+PREV|\b1082\b|\b1099\b)/ui', $text);

        return $isDarf && $isInss;
    }

    public function extract(string $text): array
    {
        preg_match('/(?:Pagar\s+(?:este\s+documento\s+)?at[eé]|Data\s+de\s+Vencimento):?.{0,35}?(\d{2}\/\d{2}\/\d{4})/sui', $text, $dueDateMatch);

        $amount = 0.0;
        if (preg_match('/Valor\s+Total\s+do\s+Documento.{0,35}?([\d\.]+,\d{2})/sui', $text, $mAmt1)) {
            $amount = $this->parseMoney($mAmt1[1]);
        } elseif (preg_match('/Totais.{0,45}?([\d\.]+,\d{2})/sui', $text, $mAmt2)) {
            $amount = $this->parseMoney($mAmt2[1]);
        } elseif (preg_match('/Valor:.{0,25}?([\d\.]+,\d{2})/sui', $text, $mAmt3)) {
            $amount = $this->parseMoney($mAmt3[1]);
        }

        $competencia = '';
        if (preg_match('/PA:\s*(\d{2}\/20\d{2})/ui', $text, $compMatch)) {
            $competencia = $compMatch[1];
        } elseif (preg_match('/\b(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)\s*\/\s*(20\d{2})\b/ui', $text, $compMonth)) {
            $competencia = ucfirst(mb_strtolower($compMonth[1])) . '/' . $compMonth[2];
        }

        preg_match('/N[úu]mero(?:\s+do\s+Documento)?:?\s*([\d\.\-]{10,})/ui', $text, $numMatch);

        return [
            'category'      => AccountingCategory::PAYROLL->value,
            'subcategory'   => AccountingSubcategory::PAYROLL->value,
            'description'   => trim("Guia DARF/INSS Previdenciário {$competencia}"),
            'due_date'      => isset($dueDateMatch[1]) ? Carbon::createFromFormat('d/m/Y', $dueDateMatch[1])->format('Y-m-d') : null,
            'amount'        => $amount,
            'supplier_cnpj' => '00.394.460/0058-87',
            'supplier_name' => 'RECEITA FEDERAL DO BRASIL',
            'observations'  => isset($numMatch[1]) ? "Número DARF: {$numMatch[1]}" : null,
        ];
    }

    private function parseMoney(string $val): float
    {
        return (float) str_replace(['.', ','], ['', '.'], trim($val));
    }
}