<?php

namespace App\Services\Financial\Acquirers\Parsers;

use App\Services\Accounting\BankingCalendarService;
use App\Services\Financial\Acquirers\AcquirerTransactionDTO;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;

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

        $rawHeader = fgetcsv($file, 10000, ';');
        if (!$rawHeader || count($rawHeader) < 15) {
            fclose($file);
            throw new Exception('Cabeçalho inválido ou delimitador diferente de ponto e vírgula (;). Verifique se o arquivo é o Relatório Detalhado/Simplificado de Vendas da adquirente.');
        }

        $header = array_map(function ($val) {
            $utf8 = mb_convert_encoding(trim((string) $val), 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
            return Str::ascii(mb_strtolower($utf8));
        }, $rawHeader);

        // Mapeamento dinâmico por cabeçalho com fallback para o layout padrão Sicredi APOS (22 colunas)
        $idxDate       = $this->findColumnIndex($header, ['data da venda', 'data'], 0);
        $idxTime       = $this->findColumnIndex($header, ['hora da venda', 'hora'], 1);
        $idxAuth       = $this->findColumnIndex($header, ['cod. de autorizacao', 'autorizacao'], 2);
        $idxModality   = $this->findColumnIndex($header, ['modalidade'], 4);
        $idxProduct    = $this->findColumnIndex($header, ['produto'], 5);
        $idxBrand      = $this->findColumnIndex($header, ['bandeira'], 7);
        $idxGross      = $this->findColumnIndex($header, ['valor bruto'], 9);
        $idxFee        = $this->findColumnIndex($header, ['valor taxa', 'taxa (mdr)', 'mdr'], 10);
        $idxNet        = $this->findColumnIndex($header, ['valor liquido'], 11);
        $idxStatus     = $this->findColumnIndex($header, ['status'], 13);
        $idxTerminal   = $this->findColumnIndex($header, ['numero do terminal', 'terminal'], 14);
        $idxNsu        = $this->findColumnIndex($header, ['comprovante da venda', 'comprovante', 'nsu'], 15);
        $idxSettlement = $this->findColumnIndex($header, ['previsao de pagamento', 'data prevista', 'data liquidacao'], -1);

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
            $datePart = trim($row[$idxDate] ?? '');
            $timePart = trim($row[$idxTime] ?? '');
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

            $grossAmount = $this->parseAmount($row[$idxGross] ?? '0');
            $feeAmount   = $this->parseAmount($row[$idxFee] ?? '0');
            $rawNetStr   = trim($row[$idxNet] ?? '');
            $netAmount   = ($rawNetStr === '' || $rawNetStr === '-')
                ? round($grossAmount - $feeAmount, 2)
                : $this->parseAmount($rawNetStr);

            // Garante consistência caso taxa venha zerada mas líquido venha preenchido (ou vice-versa)
            if ($netAmount <= 0 && $grossAmount > 0) {
                $netAmount = round($grossAmount - $feeAmount, 2);
            } elseif ($feeAmount <= 0 && $grossAmount > $netAmount) {
                $feeAmount = round($grossAmount - $netAmount, 2);
            }

            $nsu      = trim($row[$idxNsu] ?? '');
            $terminal = trim($row[$idxTerminal] ?? '');

            $rawModality = trim($row[$idxModality] ?? '');
            $rawProduct  = trim($row[$idxProduct] ?? '');
            $rawBrand    = trim($row[$idxBrand] ?? '');

            $isPix = str_contains(mb_strtolower($rawModality), 'qrcode')
                || str_contains(mb_strtolower($rawProduct), 'pix')
                || str_contains(mb_strtolower($rawBrand), 'pix');

            $modality = $isPix
                ? 'Pix'
                : mb_convert_case($rawModality ?: $rawProduct, MB_CASE_TITLE, 'UTF-8');
            $brand = $isPix
                ? 'Pix'
                : mb_convert_case($rawBrand, MB_CASE_TITLE, 'UTF-8');

            $rawStatus  = trim($row[$idxStatus] ?? '');
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
                    'reason'  => "Venda aprovada com valor bruto zerado ou negativo ('" . ($row[$idxGross] ?? '') . "').",
                    'preview' => mb_substr(implode(' ; ', array_slice($row, 0, 16)), 0, 180),
                ];
                continue;
            }

            // Data prevista de liquidação:
            // 1) Se o CSV trouxer coluna explícita de previsão de pagamento válida (d/m/Y), usa ela;
            // 2) PIX liquida em D+0;
            // 3) Cartões seguem próximo dia útil bancário (D+1).
            $expectedDate = null;
            if ($idxSettlement >= 0 && isset($row[$idxSettlement])) {
                $expStr = trim($row[$idxSettlement]);
                if ($expStr !== '' && $expStr !== '-' && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $expStr)) {
                    try {
                        $expectedDate = Carbon::createFromFormat('d/m/Y', $expStr)->startOfDay();
                    } catch (\Throwable $e) {
                        $expectedDate = null;
                    }
                }
            }

            if (!$expectedDate) {
                if ($isPix) {
                    $expectedDate = $txDate->copy()->startOfDay();
                } elseif (class_exists(BankingCalendarService::class)) {
                    $expectedDate = app(BankingCalendarService::class)->nextBankingBusinessDay(\Illuminate\Support\Carbon::instance($txDate));
                } else {
                    $expectedDate = $txDate->copy()->addDay()->startOfDay();
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
                authorizationCode: trim($row[$idxAuth] ?? ''),
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

    private function findColumnIndex(array $normalizedHeader, array $needles, int $fallback): int
    {
        foreach ($normalizedHeader as $idx => $colName) {
            foreach ($needles as $needle) {
                if ($colName === $needle || str_contains($colName, $needle)) {
                    return (int) $idx;
                }
            }
        }

        return $fallback;
    }

    private function parseAmount(string $val): float
    {
        $trimmed = trim($val);
        if ($trimmed === '' || $trimmed === '-') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9,-]/', '', $trimmed);
        if ($clean === '' || $clean === '-') {
            return 0.0;
        }

        return round((float) str_replace(',', '.', $clean), 2);
    }
}
