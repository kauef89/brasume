<?php

namespace App\Services\Financial\Acquirers\Parsers;

use App\Services\Financial\Acquirers\AcquirerTransactionDTO;
use Carbon\Carbon;
use Exception;

class SicrediAposParser
{
    /**
     * Mantém compatibilidade direta retornando apenas os DTOs aprovados.
     *
     * @return AcquirerTransactionDTO[]
     */
    public function parseCsv(string $filePath): array
    {
        $audit = $this->parseCsvWithAudit($filePath);
        return $audit['transactions'];
    }

    /**
     * Lê o CSV linha a linha e retorna diagnóstico completo:
     * transações aprovadas, linhas ignoradas por status (canceladas/negadas),
     * status desconhecidos e linhas malformadas com número exato da linha.
     */
    public function parseCsvWithAudit(string $filePath): array
    {
        $file = @fopen($filePath, 'r');
        if (!$file) {
            throw new Exception('Não foi possível abrir o arquivo CSV para leitura.');
        }

        $header = fgetcsv($file, 10000, ';');
        if (!$header || count($header) < 15) {
            fclose($file);
            throw new Exception('Cabeçalho inválido ou delimitador diferente de ponto e vírgula (;). Verifique se o arquivo é o Relatório Detalhado de Vendas da adquirente.');
        }

        $transactions = [];
        $ignoredByStatus = [];
        $unknownStatuses = [];
        $malformedRows = [];
        $summaryRowsSkipped = 0;
        $totalDataLines = 0;

        $lineNumber = 1; // Linha 1 = cabeçalho

        while (($rawRow = fgetcsv($file, 10000, ';')) !== false) {
            $lineNumber++;

            // Ignora linhas totalmente em branco
            $joined = trim(implode('', array_map(fn ($v) => trim((string) $v), $rawRow)));
            if ($joined === '') {
                continue;
            }

            $totalDataLines++;

            $row = array_map(function ($val) {
                if ($val === null || $val === '') {
                    return '';
                }
                return mb_convert_encoding(trim((string) $val), 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
            }, $rawRow);

            $col0Lower = mb_strtolower($row[0] ?? '');
            if (str_starts_with($col0Lower, 'total') || str_starts_with($col0Lower, 'resumo') || str_starts_with($col0Lower, 'quantidade')) {
                $summaryRowsSkipped++;
                continue;
            }

            if (count($row) < 16) {
                $malformedRows[] = [
                    'line'    => $lineNumber,
                    'reason'  => 'Número insuficiente de colunas (' . count($row) . ' encontradas, mínimo 16).',
                    'preview' => mb_substr(implode(' ; ', $row), 0, 180),
                ];
                continue;
            }

            // Valida data e hora
            $datePart = trim($row[0] ?? '');
            $timePart = trim($row[1] ?? '');
            $dateTimeStr = trim("{$datePart} {$timePart}");

            $txDate = null;
            foreach (['d/m/Y H:i:s', 'd/m/Y H:i'] as $fmt) {
                try {
                    $candidate = Carbon::createFromFormat($fmt, $dateTimeStr);
                    if ($candidate && $candidate->year >= 2000) {
                        $txDate = $candidate;
                        break;
                    }
                } catch (\Throwable $e) {}
            }

            if (!$txDate) {
                $malformedRows[] = [
                    'line'    => $lineNumber,
                    'reason'  => "Data/Hora inválida na coluna A/B ('{$dateTimeStr}').",
                    'preview' => mb_substr(implode(' ; ', array_slice($row, 0, 16)), 0, 180),
                ];
                continue;
            }

            $grossAmount = $this->parseAmount($row[9] ?? '0');
            $feeAmount   = $this->parseAmount($row[11] ?? '0');
            $netAmount   = $this->parseAmount($row[12] ?? '0');
            $nsu         = trim($row[15] ?? '');
            $terminal    = trim($row[14] ?? '');
            $modality    = mb_convert_case(trim($row[4] ?? ''), MB_CASE_TITLE, 'UTF-8');
            $brand       = mb_convert_case(trim($row[7] ?? ''), MB_CASE_TITLE, 'UTF-8');

            $rawStatus = trim($row[13] ?? '');
            $statusNorm = mb_strtolower($rawStatus);

            $knownRejected = ['cancelada', 'negada', 'estornada', 'desfeita', 'recusada', 'nao aprovada', 'não aprovada'];
            $knownApproved = ['aprovada', 'autorizada', 'confirmada'];

            if (in_array($statusNorm, $knownRejected, true)) {
                $ignoredByStatus[] = [
                    'line'         => $lineNumber,
                    'status'       => $rawStatus,
                    'time'         => $txDate->format('d/m/Y H:i:s'),
                    'nsu'          => $nsu,
                    'terminal'     => $terminal,
                    'modality'     => $modality,
                    'gross_amount' => $grossAmount,
                ];
                continue;
            }

            if (!in_array($statusNorm, $knownApproved, true)) {
                $unknownStatuses[] = [
                    'line'         => $lineNumber,
                    'status'       => $rawStatus ?: '(vazio)',
                    'time'         => $txDate->format('d/m/Y H:i:s'),
                    'nsu'          => $nsu,
                    'terminal'     => $terminal,
                    'gross_amount' => $grossAmount,
                ];
                continue;
            }

            if ($grossAmount <= 0) {
                $malformedRows[] = [
                    'line'    => $lineNumber,
                    'reason'  => "Venda aprovada com valor bruto zerado ou negativo ('" . ($row[9] ?? '') . "').",
                    'preview' => mb_substr(implode(' ; ', array_slice($row, 0, 16)), 0, 180),
                ];
                continue;
            }

            $expectedDateStr = trim($row[21] ?? '');
            $expectedDate = $txDate->copy()->addDay();
            if ($expectedDateStr !== '' && $expectedDateStr !== '-') {
                try {
                    $parsedExp = Carbon::createFromFormat('d/m/Y', $expectedDateStr);
                    if ($parsedExp) {
                        $expectedDate = $parsedExp;
                    }
                } catch (\Throwable $e) {
                    $malformedRows[] = [
                        'line'    => $lineNumber,
                        'reason'  => "Data prevista de liquidação inválida ('{$expectedDateStr}').",
                        'preview' => mb_substr(implode(' ; ', array_slice($row, 0, 22)), 0, 180),
                    ];
                    continue;
                }
            }

            $transactions[] = new AcquirerTransactionDTO(
                acquirerName: 'sicredi',
                terminalCode: $terminal,
                transactionDate: $txDate,
                modality: $modality,
                brand: $brand,
                grossAmount: $grossAmount,
                feeAmount: $feeAmount,
                netAmount: $netAmount,
                authorizationCode: trim($row[2] ?? ''),
                nsu: $nsu,
                expectedSettlementDate: $expectedDate
            );
        }

        fclose($file);

        return [
            'total_data_lines'     => $totalDataLines,
            'summary_rows_skipped' => $summaryRowsSkipped,
            'transactions'         => $transactions,
            'ignored_by_status'    => $ignoredByStatus,
            'unknown_statuses'     => $unknownStatuses,
            'malformed_rows'       => $malformedRows,
            'total_gross_approved' => round(array_sum(array_map(fn ($t) => $t->grossAmount, $transactions)), 2),
            'total_net_approved'   => round(array_sum(array_map(fn ($t) => $t->netAmount, $transactions)), 2),
        ];
    }

    private function parseAmount(string $val): float
    {
        $clean = preg_replace('/[^0-9,-]/', '', $val);
        return round((float) str_replace(',', '.', $clean), 2);
    }
}