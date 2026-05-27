<?php

namespace App\Filament\Resources\PersonalPurchases\Schemas;

use Filament\Schemas\Schema;

class PersonalPurchaseForm
{
    public static function getFormSchema(): array
    {
        return [
                //
            ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
