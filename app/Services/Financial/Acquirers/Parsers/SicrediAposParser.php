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

        // O CSV do Sicredi costuma vir em Latin1 (ISO-8859-1) com separador ';'
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new Exception("Não foi possível ler o arquivo CSV.");
        }

        // Lê e ignora o cabeçalho
        $header = fgetcsv($file, 10000, ';');

        while (($row = fgetcsv($file, 10000, ';')) !== false) {
            // Ignora linhas vazias
            if (empty(trim($row[0]))) continue;

            // Formatação de Valores: "R$ 107,00" -> 107.00
            $parseAmount = function ($val) {
                $clean = preg_replace('/[^0-9,-]/', '', $val);
                return (float) str_replace(',', '.', $clean);
            };

            // Status da Venda (Ignora canceladas/negadas)
            $status = mb_strtolower(trim($row[13] ?? ''));
            if (!in_array($status, ['aprovada', 'autorizada'])) {
                continue;
            }

            $dateStr = trim($row[0]) . ' ' . trim($row[1]);
            $expectedDateStr = trim($row[21]);

            $transactions[] = new AcquirerTransactionDTO(
                acquirerName: 'sicredi',
                terminalCode: trim($row[14]),
                transactionDate: Carbon::createFromFormat('d/m/Y H:i:s', $dateStr),
                modality: mb_strtolower(trim($row[4])),
                brand: mb_strtolower(trim($row[7])),
                grossAmount: $parseAmount($row[9]),
                feeAmount: $parseAmount($row[11]),
                netAmount: $parseAmount($row[12]),
                authorizationCode: trim($row[2]),
                nsu: trim($row[15]),
                expectedSettlementDate: empty($expectedDateStr) || $expectedDateStr === '-'
                    ? Carbon::createFromFormat('d/m/Y H:i:s', $dateStr)->addDay()
                    : Carbon::createFromFormat('d/m/Y', $expectedDateStr)
            );
        }

        fclose($file);
        return $transactions;
    }
}
