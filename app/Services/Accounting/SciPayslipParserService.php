<?php

namespace App\Services\Accounting;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Payslip;
use Carbon\Carbon;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\DB;
use Exception;

class SciPayslipParserService
{
    public function processPdf(string $filePath): Payroll
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $fullText = $pdf->getText();

        // 1. Identifica a competência no PDF (ex: "Outubro/2026")
        $monthNames = 'Janeiro|Fevereiro|Mar[cç]o|Abril|Maio|Junho|Julho|Agosto|Setembro|Outubro|Novembro|Dezembro';
        if (!preg_match('/\b(' . $monthNames . ')\s*[\/\-]\s*(20\d{2})\b/ui', $fullText, $mMonth)) {
            throw new Exception("Não foi possível identificar o mês de referência (ex: Outubro/2026) no arquivo.");
        }

        $meses = [
            'janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'marco' => 3,
            'abril' => 4, 'maio' => 5, 'junho' => 6, 'julho' => 7,
            'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12
        ];

        $mesNome = mb_strtolower($mMonth[1]);
        $mesNum = $meses[$mesNome] ?? (int) date('m');
        $ano = (int) $mMonth[2];

        // Competência no padrão YYYY-MM (7 caracteres: "2026-10")
        $referencePeriod = sprintf('%04d-%02d', $ano, $mesNum);

        // Previsão de pagamento no 5º dia útil do mês seguinte
        $paymentDate = Carbon::create($ano, $mesNum, 1)->addMonth()->startOfMonth()->addDays(4)->format('Y-m-d');

        return DB::transaction(function () use ($pdf, $referencePeriod, $paymentDate) {
            $payroll = Payroll::firstOrCreate(
                ['reference_period' => $referencePeriod],
                [
                    'status'           => 'draft',
                    'payment_date'     => $paymentDate,
                    'total_earnings'   => 0,
                    'total_deductions' => 0,
                    'total_net'        => 0
                ]
            );

            $processedCpfs = [];
            $pages = $pdf->getPages();

            foreach ($pages as $page) {
                $pageText = $page->getText();

                // Extrai a primeira via do holerite
                $receipt = explode('RECIBO DE PAGAMENTO DE SALÁRIO', $pageText)[1] ?? $pageText;

                // Identificadores do colaborador
                preg_match('/CPF:\s*([\d.-]+)/ui', $receipt, $mCpf);
                preg_match('/Código\s*\n\s*(\d+)/ui', $receipt, $mCode);
                preg_match('/Nome do Colaborador\s*\n\s*([^\n]+)/ui', $receipt, $mName);

                $cleanCpf = isset($mCpf[1]) ? preg_replace('/\D/', '', $mCpf[1]) : null;
                $cleanCode = isset($mCode[1]) ? trim($mCode[1]) : null;
                $employeeName = isset($mName[1]) ? trim($mName[1]) : null;

                if (!$cleanCpf && !$employeeName) {
                    continue;
                }

                $uniqueKey = $cleanCpf ?: $employeeName;
                if (in_array($uniqueKey, $processedCpfs, true)) {
                    continue;
                }
                $processedCpfs[] = $uniqueKey;

                // Busca o colaborador: 1º por CPF, 2º por Matrícula contábil, 3º por Nome
                $employee = null;
                if ($cleanCpf) {
                    $employee = Employee::where('cpf', $cleanCpf)->first();
                }
                if (!$employee && $cleanCode) {
                    $employee = Employee::where('accounting_code', $cleanCode)
                        ->orWhere('accounting_code', ltrim($cleanCode, '0'))
                        ->first();
                }
                if (!$employee && $employeeName) {
                    $employee = Employee::where('name', 'like', "%{$employeeName}%")->first();
                }

                if (!$employee) {
                    continue;
                }

                // Bases e valores do rodapé
                preg_match('/Salário base\s*\n\s*([\d.,]+)/ui', $receipt, $mSal);
                preg_match('/Base INSS\s*\n\s*([\d.,]+)/ui', $receipt, $mInss);
                preg_match('/Base FGTS\s*\n\s*([\d.,]+)/ui', $receipt, $mFgts);
                preg_match('/Valor FGTS\s*\n\s*([\d.,]+)/ui', $receipt, $mValFgts);
                preg_match('/Base IRRF\s*\n\s*([\d.,]+)/ui', $receipt, $mIrrf);
                preg_match('/SALÁRIO LÍQUIDO\s*R\$\s*([\d.,]+)/ui', $receipt, $mNet);

                $payslip = Payslip::updateOrCreate(
                    [
                        'payroll_id'  => $payroll->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'base_salary'    => $this->parseMoney($mSal[1] ?? '0'),
                        'inss_amount'    => $this->parseMoney($mInss[1] ?? '0'),
                        'fgts_amount'    => $this->parseMoney($mValFgts[1] ?? '0'),
                        'irrf_amount'    => $this->parseMoney($mIrrf[1] ?? '0'),
                        'net_salary'     => $this->parseMoney($mNet[1] ?? '0'),
                        'status'         => 'pending',
                        'pix_key'        => $employee->pix_key,
                        'payment_method' => $employee->pix_key ? 'pix' : 'transfer',
                    ]
                );

                $payslip->items()->delete();

                // Rubricas e eventos
                $lines = explode("\n", $receipt);
                $isInsideItems = false;
                $gross = 0.0;
                $deductions = 0.0;

                foreach ($lines as $line) {
                    $line = trim($line);

                    if (str_contains($line, 'Nome do Colaborador')) {
                        $isInsideItems = true;
                        continue;
                    }

                    if (str_contains($line, 'SALÁRIO LÍQUIDO')) {
                        break;
                    }

                    if (!$isInsideItems) {
                        continue;
                    }

                    if (preg_match('/^[\d.,]+\s+[\d.,]+$/', $line)) {
                        continue;
                    }

                    if (!preg_match('/(\d{1,6})$/', $line, $mCodeEnd)) {
                        continue;
                    }

                    $code = $mCodeEnd[1];
                    $rest = trim(substr($line, 0, -strlen($code)));

                    if (preg_match('/^(?:([\d:.,]+%?)\s+)?([\d.,]+)\s+(.+)$/u', $rest, $mParts)) {
                        $refStr = $mParts[1] ?? '0';
                        $amount = $this->parseMoney($mParts[2]);
                        $desc = trim($mParts[3]);

                        $isDeduction = str_starts_with($code, '9') ||
                                      preg_match('/(INSS|IRRF|VALE|FALTA|ATRASO|DESCONTO|CONTRIB)/i', $desc);

                        $type = $isDeduction ? 'deduction' : 'earning';

                        if ($type === 'earning') {
                            $gross += $amount;
                        } else {
                            $deductions += $amount;
                        }

                        $payslip->items()->create([
                            'code'      => $code,
                            'name'      => $desc,
                            'type'      => $type,
                            'reference' => $this->parseReference($refStr),
                            'amount'    => $amount,
                        ]);
                    }
                }

                $payslip->update([
                    'gross_salary'     => $gross,
                    'total_deductions' => $deductions,
                ]);
            }

            // Recalcula os totais consolidados da folha
            $payroll->update([
                'total_earnings'   => $payroll->payslips()->sum('gross_salary'),
                'total_deductions' => $payroll->payslips()->sum('total_deductions'),
                'total_net'        => $payroll->payslips()->sum('net_salary'),
                'total_inss'       => $payroll->payslips()->sum('inss_amount'),
                'total_fgts'       => $payroll->payslips()->sum('fgts_amount'),
                'total_irrf'       => $payroll->payslips()->sum('irrf_amount'),
            ]);

            return $payroll;
        });
    }

    private function parseMoney(string $value): float
    {
        $clean = str_replace('.', '', trim($value));
        $clean = str_replace(',', '.', $clean);
        return (float) $clean;
    }

    private function parseReference(string $ref): float
    {
        $ref = trim($ref);

        if (str_contains($ref, ':')) {
            $parts = explode(':', $ref);
            return round((float)$parts[0] + ((float)($parts[1] ?? 0) / 60), 2);
        }

        $clean = str_replace(['%', ' '], '', $ref);
        return $this->parseMoney($clean);
    }
}
