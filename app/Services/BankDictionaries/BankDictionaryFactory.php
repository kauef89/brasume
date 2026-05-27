<?php namespace App\Services\BankDictionaries;

class BankDictionaryFactory
{
    public static function make(?string $bankCode): BankDictionaryInterface
    {
        return match ($bankCode) {
            "748" => new SicrediDictionary(),
            // No futuro: '001' => new BancoDoBrasilDictionary(),
            // No futuro: '341' => new ItauDictionary(),

            // Dicionário genérico que só devolve a string original se o banco não for mapeado ainda
            default => new class implements BankDictionaryInterface {
                public function parseMemo(string $memo): array
                {
                    return [
                        "document" => null,
                        "name" => null,
                        "clean_description" => trim($memo),
                    ];
                }
            },
        };
    }
}
