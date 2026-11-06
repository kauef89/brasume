<?php

namespace App\Services\Accounting;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;

class AccountingService
{
    public static function getCategories(): array
    {
        $categories = [];
        foreach (AccountingCategory::cases() as $case) {
            $categories[$case->value] = $case->getLabel();
        }
        return $categories;
    }

    public static function getSubcategories(AccountingCategory|string|null $category): array
    {
        return AccountingSubcategory::forCategory($category);
    }
}