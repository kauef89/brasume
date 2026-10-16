<?php

namespace App\Filament\Pages\ImportInvoice\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Group;

class ImportInvoiceForm
{
    public static function schema(): array
    {
        return [
            self::getHiddenFields(),
            SummarySection::make(),
            ItemsSection::make(),
        ];
    }

    private static function getHiddenFields(): Group
    {
        return Group::make([
            Hidden::make("invoice_key"),
            Hidden::make("received_invoice_id"),
            Hidden::make("xml_total_amount"),
            Hidden::make("financial_already_launched"),
            Hidden::make("xml_installments"),
            Hidden::make("xml_supplier_cnpj"),
            Hidden::make("xml_supplier_name"),
            Hidden::make("factor_source")->default("manual"),
        ]);
    }
}
