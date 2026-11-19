<?php

namespace App\Services\Accounting;

use App\Enums\PayrollReferenceType;
use App\Models\Employee;
use App\Models\EmployeeVacation;
use App\Models\Payroll;
use App\Models\Payslip;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class SciPayslipParserService
{
    /**
     * Analisa o PDF do SCI em memória (Dry Run) e retorna diagnóstico completo
     * de colaboradores vinculados, não encontrados e divergências de rubricas.
     */
    public function analyzePdf(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $fullText = $pdf->getText();

        if (empty(trim($fullText))) {
            $process = new Process(['pdftotext', '-layout', $filePath, '-']);
            $process->run();
            if ($process->isSuccessful()) {
                $fullText = $process->getOutput();
            }
        }

        if (empty(trim($fullText))) {
            throw new Exception("Não foi possível extrair texto deste PDF do SCI.");
        }

        $isVacation = (bool) preg_match('/(?:RECIBO\s+DE\s+(?:PAGAMENTO\s+DE\s+)?F[ÉE]RIAS|AVISO\s+E\s+RECIBO\s+DE\s+F[ÉE]RIAS)/ui', $fullText);
        $is13 = !$isVacation && (bool) preg_match('/(?:Adto\.\s*13º|13º\s*salário|Adiantamento\s+13º|Adiantamento\s+de\s+13º)/ui', $fullText);

        $vacationStart = null;
        if ($isVacation && preg_match('/(?:Gozo(?:\s+das\s+F[ée]rias)?|Per[ií]odo\s+de\s+Gozo|In[ií]cio\s+das\s+F[ée]rias)[:\s]*(\d{2}\/\d{2}\/\d{4})\s*(?:a|at[ée]|-)\s*(\d{2}\/\d{2}\/\d{4})/ui', $fullText, $mGozo)) {
            $vacationStart = Carbon::createFromFormat('d/m/Y', $mGozo[1]);
        }

        $monthNames = 'Janeiro|Fevereiro|Mar[cç]o|Abril|Maio|Junho|Julho|Agosto|Setembro|Outubro|Novembro|Dezembro';
        $meses = [
            'janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'marco' => 3,
            'abril' => 4, 'maio' => 5, 'junho' => 6, 'julho' => 7,
            'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12
        ];

        $mesNum = null;
        $ano = null;

        if (preg_match('/\b(' . $monthNames . ')\s*[\/\-]\s*(20\d{2})\b/ui', $fullText, $mMonth)) {
            $mesNome = mb_strtolower($mMonth[1]);
            $mesNum = $meses[$mesNome] ?? null;
            $ano = (int) $mMonth[2];
        } elseif ($vacationStart) {
            $mesNum = (int) $vacationStart->month;
            $ano = (int) $vacationStart->year;
        } elseif (preg_match('/(?:Compet[êe]ncia|M[êe]s\s+de\s+Refer[êe]ncia)[:\s]*(\d{2})\/(20\d{2})/ui', $fullText, $mNumComp)) {
            $mesNum = (int) $mNumComp[1];
            $ano = (int) $mNumComp[2];
        }

        if (!$mesNum || !$ano) {
            throw new Exception("Não foi possível identificar o mês/ano de referência (ex: Outubro/2026) no arquivo.");
        }

        $refType = match (true) {
            $isVacation             => PayrollReferenceType::VACATION,
            $is13 && $mesNum === 11 => PayrollReferenceType::THIRTEENTH_FIRST,
            $is13                   => PayrollReferenceType::THIRTEENTH_SECOND,
            default                 => PayrollReferenceType::MONTHLY,
        };

        $referencePeriod = $refType->buildPeriod($ano, $mesNum);
        $paymentDate = $refType->defaultPaymentDate($ano, $mesNum, $vacationStart);

        if ($isVacation && preg_match('/Data\s+(?:de|do)\s+Pagamento[:\s]*(\d{2}\/\d{2}\/\d{4})/ui', $fullText, $mPayDate)) {
            $paymentDate = Carbon::createFromFormat('d/m/Y', $mPayDate[1])->format('Y-m-d');
        }

        $existingPayroll = Payroll::withCount('payslips')->where('reference_period', $referencePeriod)->first();

        $processedCpfs = [];
        $matched = [];
        $unmatched = [];
        $pages = $pdf->getPages();

        foreach ($pages as $pageIndex => $page) {
            $pageText = $page->getText();
            $parts = preg_split('/RECIBO\s+DE\s+(?:PAGAMENTO\s+DE\s+)?(?:SAL[ÁA]RIO|F[ÉE]RIAS)/ui', $pageText);
            $receipt = $parts[1] ?? $pageText;

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

            // Bases e valores do rodapé
            preg_match('/Salário base\s*\n\s*([\d.,]+)/ui', $receipt, $mSal);
            preg_match('/Base INSS\s*\n\s*([\d.,]+)/ui', $receipt, $mInss);
            preg_match('/Base FGTS\s*\n\s*([\d.,]+)/ui', $receipt, $mFgts);
            preg_match('/Valor FGTS\s*\n\s*([\d.,]+)/ui', $receipt, $mValFgts);
            preg_match('/Base IRRF\s*\n\s*([\d.,]+)/ui', $receipt, $mIrrf);
            preg_match('/(?:SAL[ÁA]RIO\s+L[ÍI]QUIDO|L[ÍI]QUIDO\s+DE\s+F[ÉE]RIAS|VALOR\s+L[ÍI]QUIDO|TOTAL\s+L[ÍI]QUIDO)\s*R\$\s*([\d.,]+)/ui', $receipt, $mNet);

            $baseSalary = $this->parseMoney($mSal[1] ?? '0');
            $inssAmount = $this->parseMoney($mInss[1] ?? '0');
            $fgtsAmount = $this->parseMoney($mValFgts[1] ?? '0');
            $irrfAmount = $this->parseMoney($mIrrf[1] ?? '0');
            $netSalary  = $this->parseMoney($mNet[1] ?? '0');

            // Extração das rubricas
            $lines = explode("\n", $receipt);
            $isInsideItems = false;
            $gross = 0.0;
            $deductions = 0.0;
            $items = [];

            foreach ($lines as $line) {
                $line = trim($line);

                if (str_contains($line, 'Nome do Colaborador')) {
                    $isInsideItems = true;
                    continue;
                }

                if (preg_match('/(?:SAL[ÁA]RIO\s+L[ÍI]QUIDO|L[ÍI]QUIDO\s+DE\s+F[ÉE]RIAS|VALOR\s+L[ÍI]QUIDO|TOTAL\s+L[ÍI]QUIDO)/ui', $line)) {
                    break;
                }

                if (!$isInsideItems || preg_match('/^[\d.,]+\s+[\d.,]+$/', $line)) {
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
                                  preg_match('/(INSS|IRRF|VALE|FALTA|ATRASO|DESCONTO|CONTRIB|ADIANTAMENTO)/i', $desc);

                    $type = $isDeduction ? 'deduction' : 'earning';

                    if ($type === 'earning') {
                        $gross += $amount;
                    } else {
                        $deductions += $amount;
                    }

                    $items[] = [
                        'code'      => $code,
                        'name'      => $desc,
                        'type'      => $type,
                        'reference' => $this->parseReference($refStr),
                        'amount'    => $amount,
                    ];
                }
            }

            // Busca o colaborador no sistema
            $employee = null;
            $matchedBy = null;
            if ($cleanCpf) {
                $employee = Employee::where('cpf', $cleanCpf)->first();
                if ($employee) $matchedBy = 'CPF';
            }
            if (!$employee && $cleanCode) {
                $employee = Employee::where('accounting_code', $cleanCode)
                    ->orWhere('accounting_code', ltrim($cleanCode, '0'))
                    ->first();
                if ($employee) $matchedBy = 'Matrícula SCI';
            }
            if (!$employee && $employeeName) {
                $employee = Employee::where('name', 'like', "%{$employeeName}%")->first();
                if ($employee) $matchedBy = 'Nome';
            }

            if (!$employee) {
                $unmatched[] = [
                    'page'         => $pageIndex + 1,
                    'pdf_name'     => $employeeName ?: 'Sem nome',
                    'cpf'          => $mCpf[1] ?? 'Não informado',
                    'code'         => $cleanCode ?: '-',
                    'gross_salary' => round($gross, 2),
                    'net_salary'   => $netSalary,
                ];
                continue;
            }

            $warnings = [];
            if (empty($items)) {
                $warnings[] = 'Nenhuma rubrica individual extraída.';
            }
            if ($netSalary <= 0) {
                $warnings[] = 'Salário líquido lido como R$ 0,00.';
            }
            $calculatedNet = round($gross - $deductions, 2);
            if (!empty($items) && abs($calculatedNet - $netSalary) > 0.05) {
                $warnings[] = "Soma das rubricas (R$ " . number_format($calculatedNet, 2, ',', '.') . ") difere do líquido do rodapé (R$ " . number_format($netSalary, 2, ',', '.') . ").";
            }

            $vacationData = null;
            if ($isVacation && preg_match('/(?:Gozo(?:\s+das\s+F[ée]rias)?|Per[ií]odo\s+de\s+Gozo|In[ií]cio\s+das\s+F[ée]rias)[:\s]*(\d{2}\/\d{2}\/\d{4})\s*(?:a|at[ée]|-)\s*(\d{2}\/\d{2}\/\d{4})/ui', $receipt, $mPageGozo)) {
                $aquisitivoDesc = 'Importado via Recibo de Férias SCI';
                if (preg_match('/Per[ií]odo\s+Aquisitivo[:\s]*(\d{2}\/\d{2}\/\d{4}\s*(?:a|at[ée]|-)\s*\d{2}\/\d{2}\/\d{4})/ui', $receipt, $mAquis)) {
                    $aquisitivoDesc = 'Período Aquisitivo: ' . trim($mAquis[1]) . ' (Importado via SCI)';
                }

                $vacationData = [
                    'start_date'  => Carbon::createFromFormat('d/m/Y', $mPageGozo[1])->format('Y-m-d'),
                    'end_date'    => Carbon::createFromFormat('d/m/Y', $mPageGozo[2])->format('Y-m-d'),
                    'period_br'   => "{$mPageGozo[1]} a {$mPageGozo[2]}",
                    'description' => $aquisitivoDesc,
                ];
            }

            $matched[] = [
                'employee_id'      => $employee->id,
                'employee_name'    => $employee->name,
                'matched_by'       => $matchedBy,
                'cpf'              => $mCpf[1] ?? $employee->cpf ?? '-',
                'code'             => $cleanCode ?: '-',
                'base_salary'      => $baseSalary,
                'gross_salary'     => round($gross, 2),
                'total_deductions' => round($deductions, 2),
                'net_salary'       => $netSalary,
                'inss_amount'      => $inssAmount,
                'fgts_amount'      => $fgtsAmount,
                'irrf_amount'      => $irrfAmount,
                'pix_key'          => $employee->pix_key,
                'payment_method'   => $employee->pix_key ? 'pix' : 'transfer',
                'items'            => $items,
                'vacation'         => $vacationData,
                'warnings'         => $warnings,
            ];
        }

        return [
            'reference_period' => $referencePeriod,
            'reference_label'  => PayrollReferenceType::formatPeriod($referencePeriod),
            'reference_type'   => $refType,
            'payment_date'     => $paymentDate,
            'existing_payroll' => $existingPayroll ? [
                'id'             => $existingPayroll->id,
                'status'         => $existingPayroll->status,
                'payslips_count' => $existingPayroll->payslips_count,
            ] : null,
            'total_pages'      => count($pages),
            'matched'          => $matched,
            'unmatched'        => $unmatched,
            'totals'           => [
                'gross'      => round(array_sum(array_column($matched, 'gross_salary')), 2),
                'deductions' => round(array_sum(array_column($matched, 'total_deductions')), 2),
                'net'        => round(array_sum(array_column($matched, 'net_salary')), 2),
                'fgts'       => round(array_sum(array_column($matched, 'fgts_amount')), 2),
                'inss'       => round(array_sum(array_column($matched, 'inss_amount')), 2),
            ],
        ];
    }

    public function processPdf(string $filePath): Payroll
    {
        $analysis = $this->analyzePdf($filePath);

        if (empty($analysis['matched'])) {
            $missingNames = implode(', ', array_column($analysis['unmatched'], 'pdf_name'));
            throw new Exception("Nenhum colaborador do PDF foi localizado no cadastro do sistema" . ($missingNames ? " (Não encontrados: {$missingNames})" : '.'));
        }

        return DB::transaction(function () use ($analysis) {
            $payroll = Payroll::firstOrCreate(
                ['reference_period' => $analysis['reference_period']],
                [
                    'status'           => 'draft',
                    'payment_date'     => $analysis['payment_date'],
                    'total_earnings'   => 0,
                    'total_deductions' => 0,
                    'total_net'        => 0,
                ]
            );

            foreach ($analysis['matched'] as $row) {
                if (!empty($row['vacation'])) {
                    EmployeeVacation::updateOrCreate(
                        [
                            'employee_id' => $row['employee_id'],
                            'start_date'  => $row['vacation']['start_date'],
                        ],
                        [
                            'end_date'    => $row['vacation']['end_date'],
                            'description' => $row['vacation']['description'],
                        ]
                    );
                }

                $payslip = Payslip::updateOrCreate(
                    [
                        'payroll_id'  => $payroll->id,
                        'employee_id' => $row['employee_id'],
                    ],
                    [
                        'base_salary'      => $row['base_salary'],
                        'gross_salary'     => $row['gross_salary'],
                        'total_deductions' => $row['total_deductions'],
                        'net_salary'       => $row['net_salary'],
                        'inss_amount'      => $row['inss_amount'],
                        'fgts_amount'      => $row['fgts_amount'],
                        'irrf_amount'      => $row['irrf_amount'],
                        'status'           => 'pending',
                        'pix_key'          => $row['pix_key'],
                        'payment_method'   => $row['payment_method'],
                    ]
                );

                $payslip->items()->delete();
                foreach ($row['items'] as $item) {
                    $payslip->items()->create($item);
                }
            }

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
            return round((float) $parts[0] + ((float) ($parts[1] ?? 0) / 60), 2);
        }

        $clean = str_replace(['%', ' '], '', $ref);
        return $this->parseMoney($clean);
    }
}