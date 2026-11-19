<?php
namespace App\Services\PayableDictionaries;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableDocumentType;
use Carbon\Carbon;

class DareScDictionary implements PayableDictionaryInterface
{
    public function type(): PayableDocumentType
    {
        return PayableDocumentType::DARE_SC;
    }

    public function matches(string $text): bool
    {
        return str_contains($text, 'DARE-SC') && str_contains($text, 'Receitas Estaduais');
    }

    public function extract(string $text): array
    {
        preg_match('/Pagar este documento até.*?(\d{2}\/\d{2}\/\d{4})/s', $text, $dueDateMatch);
        preg_match('/Total a pagar\s*([\d\.,]+)/i', $text, $amountMatch);
        preg_match('/Número S@T\s*(\d+)/i', $text, $satMatch);
        preg_match('/Informações adicionais\s*(.*?)\s*(?:Principal|Pagar este documento)/si', $text, $descMatch);

        $desc = isset($descMatch[1]) ? trim(preg_replace('/\s+/', ' ', $descMatch[1])) : 'DARE-SC Substituição Tributária / ICMS';
        if (strlen($desc) > 250) $desc = substr($desc, 0, 250);

        return [
            'category'      => AccountingCategory::ADMINISTRATIVE->value,
            'subcategory'   => AccountingSubcategory::TAXES->value,
            'description'   => $desc,
            'due_date'      => isset($dueDateMatch[1]) ? Carbon::createFromFormat('d/m/Y', $dueDateMatch[1])->format('Y-m-d') : null,
            'amount'        => isset($amountMatch[1]) ? (float) str_replace(['.', ','], ['', '.'], $amountMatch[1]) : 0.0,
            'supplier_cnpj' => '82.951.310/0001-56',
            'supplier_name' => 'SEFAZ - ESTADO DE SANTA CATARINA',
            'observations'  => isset($satMatch[1]) ? "S@T: {$satMatch[1]}" : null,
        ];
    }
}