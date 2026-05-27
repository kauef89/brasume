<?php
namespace App\Services\BankDictionaries;

interface BankDictionaryInterface
{
    /**
     * Recebe a tag <MEMO> do OFX e devolve um array com os dados mastigados.
     */
    public function parseMemo(string $memo): array;
}
