<?php
namespace App\Services\PayableDictionaries;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableDocumentType;
use Carbon\Carbon;

class AccountingFeeDictionary implements PayableDictionaryInterface
{
    public function type(): PayableDocumentType
    {
        return PayableDocumentType::ACCOUNTING_FEE;
    }

    public function matches(string $text): bool
    {
        return str_contains($text, 'Recibo de serviços faturados') && str_contains($text, 'GOUDARD & SILVA');
    }

    public function extract(string $text): array
    {
        preg_match('/Vencimento:\s*(\d{2}\/\d{2}\/\d{4})/i', $text, $dueDateMatch);
        preg_match('/Total\s*([\d\.,]+)/i', $text, $amountMatch);
        preg_match('/Referência:\s*(.*?)\s/i', $text, $compMatch);

        $ref = isset($compMatch[1]) ? " Ref {$compMatch[1]}" : '';

        return [
            'category'      => AccountingCategory::ADMINISTRATIVE->value,
            'subcategory'   => AccountingSubcategory::ACCOUNTING->value,
            'description'   => trim("Honorários Contábeis{$ref}"),
            'due_date'      => isset($dueDateMatch[1]) ? Carbon::createFromFormat('d/m/Y', $dueDateMatch[1])->format('Y-m-d') : null,
            'amount'        => isset($amountMatch[1]) ? (float) str_replace(['.', ','], ['', '.'], $amountMatch[1]) : 0.0,
            'supplier_cnpj' => '05.068.034/0001-31',
            'supplier_name' => 'GOUDARD & SILVA LTDA',
        ];
    }
}