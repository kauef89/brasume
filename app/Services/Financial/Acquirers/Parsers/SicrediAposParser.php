<?php

namespace App\Services\Financial\Acquirers\Parsers;

use App\Services\Financial\Acquirers\AcquirerTransactionDTO;
use Carbon\Carbon;
use Exception;

class SicrediAposParser
{
    /**
     * @return AcquirerTransactionDTO[]
     */
        public function parseCsv(string $filePath): array
    {
        $transactions = [];

        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new Exception("Não foi possível ler o arquivo CSV.");
        }

        // Lê e descarta o cabeçalho
        $header = fgetcsv($file, 10000, ';');

        while (($rawRow = fgetcsv($file, 10000, ';')) !== false) {
            if (empty(trim($rawRow[0] ?? ''))) continue;

            // Converte todos os campos de Latin1 (ISO-8859-1 / Windows-1252) para UTF-8 limpo
            $row = array_map(function ($val) {
                if ($val === null || $val === '') return '';
                return mb_convert_encoding(trim($val), 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
            }, $rawRow);

            // Formatação de Valores: "R$ 107,00" -> 107.00
            $parseAmount = function ($val) {
                $clean = preg_replace('/[^0-9,-]/', '', $val);
                return (float) str_replace(',', '.', $clean);
            };

            // Status da Venda (ignora canceladas/negadas)
            $status = mb_strtolower(trim($row[13] ?? ''));
            if (!in_array($status, ['aprovada', 'autorizada'])) {
                continue;
            }

            $dateStr = trim($row[0]) . ' ' . trim($row[1]);
            $expectedDateStr = trim($row[21] ?? '');

            // Normalização de Modalidade e Bandeira com acentuação correta
            $modality = mb_convert_case(trim($row[4] ?? ''), MB_CASE_TITLE, 'UTF-8');
            $brand = mb_convert_case(trim($row[7] ?? ''), MB_CASE_TITLE, 'UTF-8');

            $transactions[] = new \App\Services\Financial\Acquirers\AcquirerTransactionDTO(
                acquirerName: 'sicredi',
                terminalCode: trim($row[14] ?? ''),
                transactionDate: \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $dateStr),
                modality: $modality,
                brand: $brand,
                grossAmount: $parseAmount($row[9] ?? 0),
                feeAmount: $parseAmount($row[11] ?? 0),
                netAmount: $parseAmount($row[12] ?? 0),
                authorizationCode: trim($row[2] ?? ''),
                nsu: trim($row[15] ?? ''),
                expectedSettlementDate: empty($expectedDateStr) || $expectedDateStr === '-'
                    ? \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $dateStr)->addDay()
                    : \Carbon\Carbon::createFromFormat('d/m/Y', $expectedDateStr)
            );
        }

        fclose($file);
        return $transactions;
    }
}
