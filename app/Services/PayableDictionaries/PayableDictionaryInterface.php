<?php
namespace App\Services\PayableDictionaries;

use App\Enums\PayableDocumentType;

interface PayableDictionaryInterface
{
    public function type(): PayableDocumentType;
    public function matches(string $text): bool;
    public function extract(string $text): array;
}