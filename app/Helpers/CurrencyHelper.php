<?php

namespace App\Helpers;

class CurrencyHelper
{
    /**
     * Formata um valor monetário para até 5 casas decimais,
     * omitindo os zeros à direita, mas garantindo no mínimo 2 casas (padrão de moeda).
     */
    public static function formatDynamic($value): string
    {
        // 1. Formata com 5 casas primeiro
        $formatted = rtrim(number_format((float) $value, 5, ',', '.'), '0');
        
        // 2. Se apagou tudo e sobrou a vírgula (ex: 12,), adiciona '00'
        if (str_ends_with($formatted, ',')) {
            $formatted .= '00';
        } 
        // 3. Se apagou e sobrou só uma casa (ex: 12,5), adiciona um '0' para ficar '12,50'
        elseif (preg_match('/\,\d$/', $formatted)) {
            $formatted .= '0';
        }
        
        return $formatted;
    }
}