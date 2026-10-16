<?php

namespace App\Services\Printing;

use Illuminate\Support\Str;

class ReceiptFormatter
{
    public const COLS_80MM_A = 48;
    public const COLS_80MM_B = 64;
    public const COLS_58MM_A = 32;
    public const COLS_58MM_B = 42;

    /**
     * Resolve o número de colunas por largura de papel e tipo de fonte
     */
    public static function resolveColumns(int|string $columnsOrFont = 48, int $paperWidth = 80): int
    {
        if (is_string($columnsOrFont)) {
            $font = strtoupper(trim($columnsOrFont));
            if ($paperWidth === 58) {
                return $font === 'B' ? self::COLS_58MM_B : self::COLS_58MM_A;
            }
            return $font === 'B' ? self::COLS_80MM_B : self::COLS_80MM_A;
        }

        return $columnsOrFont;
    }

    /**
     * Gera divisor de linha ocupando 100% da largura útil
     */
    public static function divider(int|string $columnsOrFont = 48, string $char = '-', int $paperWidth = 80): string
    {
        return str_repeat($char, self::resolveColumns($columnsOrFont, $paperWidth));
    }

    /**
     * Alinha colunas monospace (tamanho 0 = preenchimento automático do restante da linha)
     */
    public static function columns(
        array $texts,
        array $sizes,
        array $aligns = [],
        int|string $columnsOrFont = 48,
        int $paperWidth = 80
    ): string {
        $paperColumns = self::resolveColumns($columnsOrFont, $paperWidth);
        $result = '';

        // Calcula a coluna expansível (tamanho 0)
        $expandIndex = array_search(0, $sizes, true);
        if ($expandIndex !== false) {
            $fixedTotal = array_sum($sizes);
            $sizes[$expandIndex] = max(0, $paperColumns - $fixedTotal);
        }

        foreach ($texts as $index => $text) {
            $size = $sizes[$index] ?? 0;
            $align = $aligns[$index] ?? 'L';

            $cleanText = Str::ascii((string) $text);
            $textStr = substr($cleanText, 0, $size);

            if ($align === 'R') {
                $result .= str_pad($textStr, $size, ' ', STR_PAD_LEFT);
            } elseif ($align === 'C') {
                $result .= str_pad($textStr, $size, ' ', STR_PAD_BOTH);
            } else {
                $result .= str_pad($textStr, $size, ' ', STR_PAD_RIGHT);
            }
        }

        return $result;
    }
}
