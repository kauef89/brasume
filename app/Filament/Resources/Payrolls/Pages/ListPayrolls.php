<?php

namespace App\Filament\Resources\Payrolls\Pages;

use App\Enums\PayrollReferenceType;
use App\Filament\Resources\Payrolls\PayrollResource;
use App\Services\Accounting\SciPayslipParserService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPdf')
                ->label('Importar PDF da Contabilidade')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->modalHeading('Importar Folha / 13º / Férias (SCI)')
                ->modalDescription('Envie o PDF do SCI para auditoria prévia (Dry Run) antes de gravar a competência.')
                ->modalWidth('5xl')
                ->modalSubmitActionLabel('Confirmar e Importar Folha')
                ->steps([
                    Step::make('upload')
                        ->label('1. Selecionar PDF do SCI')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            FileUpload::make('pdf_file')
                                ->label('Arquivo PDF do SCI (Holerites Mensais, 13º Salário ou Recibos de Férias)')
                                ->acceptedFileTypes(['application/pdf'])
                                ->disk('local')
                                ->directory('temp_payroll_imports')
                                ->required(),
                        ]),

                    Step::make('preview')
                        ->label('2. Auditoria & Dry Run')
                        ->icon('heroicon-o-eye')
                        ->schema([
                            Placeholder::make('payroll_dry_run_report')
                                ->hiddenLabel()
                                ->content(function (Get $get, SciPayslipParserService $service) {
                                    $rawFile = $get('pdf_file');
                                    $fileEntry = is_array($rawFile) ? reset($rawFile) : $rawFile;

                                    $path = null;
                                    if ($fileEntry instanceof TemporaryUploadedFile) {
                                        $path = $fileEntry->getRealPath();
                                    } elseif (is_string($fileEntry) && Storage::disk('local')->exists($fileEntry)) {
                                        $path = Storage::disk('local')->path($fileEntry);
                                    }

                                    if (!$path) {
                                        return new HtmlString('<p class="text-sm text-gray-500">Nenhum arquivo localizado para pré-visualização.</p>');
                                    }

                                    try {
                                        $report = $service->analyzePdf($path);
                                    } catch (\Throwable $e) {
                                        $msg = e($e->getMessage());
                                        return new HtmlString("<div class='p-4 rounded-xl border border-rose-300 bg-rose-50 text-rose-700 text-sm font-semibold'>❌ Erro na leitura do PDF: {$msg}</div>");
                                    }

                                    $typeLabel = e($report['reference_type']->getLabel());
                                    $refLabel = e($report['reference_label']);
                                    $payDate = Carbon::parse($report['payment_date'])->format('d/m/Y');
                                    $matchedCount = count($report['matched']);
                                    $unmatchedCount = count($report['unmatched']);
                                    $netFmt = number_format($report['totals']['net'], 2, ',', '.');
                                    $fgtsFmt = number_format($report['totals']['fgts'], 2, ',', '.');

                                    $html = "<div class='space-y-4'>";

                                    if ($report['existing_payroll']) {
                                        $ex = $report['existing_payroll'];
                                        $html .= "<div class='p-3 rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50/80 dark:bg-amber-950/40 text-xs text-amber-800 dark:text-amber-300'>
                                            ⚠️ <b>Competência já existente (#{$ex['id']}):</b> Já possui {$ex['payslips_count']} holerite(s) cadastrado(s). Ao confirmar, os holerites dos colaboradores deste PDF serão atualizados/mesclados nessa competência.
                                        </div>";
                                    }

                                    // Cards de resumo
                                    $html .= "<div class='grid grid-cols-1 md:grid-cols-4 gap-3'>";
                                    $html .= "<div class='p-3 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50/70 dark:bg-primary-950/40'><div class='text-xs text-primary-700 dark:text-primary-400'>Competência / Tipo</div><div class='text-base font-bold text-primary-900 dark:text-primary-200'>{$refLabel}</div><div class='text-[11px] text-primary-600'>{$typeLabel} • Pgto {$payDate}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/40'><div class='text-xs text-emerald-700 dark:text-emerald-400'>Total Líquido</div><div class='text-xl font-bold text-emerald-800 dark:text-emerald-300'>R$ {$netFmt}</div><div class='text-[11px] text-emerald-600'>FGTS: R$ {$fgtsFmt}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800'><div class='text-xs text-gray-500'>Colaboradores Vinculados</div><div class='text-xl font-bold text-gray-900 dark:text-gray-100'>{$matchedCount}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-rose-200 dark:border-rose-800 bg-rose-50/70 dark:bg-rose-950/40'><div class='text-xs text-rose-700 dark:text-rose-400'>Não Encontrados no Cadastro</div><div class='text-xl font-bold text-rose-800 dark:text-rose-300'>{$unmatchedCount}</div></div>";
                                    $html .= "</div>";

                                    // Alerta de colaboradores não encontrados
                                    if ($unmatchedCount > 0) {
                                        $html .= "<div class='p-3 rounded-xl border border-rose-300 dark:border-rose-700 bg-rose-50/80 dark:bg-rose-950/40 space-y-1.5'>";
                                        $html .= "<div class='text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider'>⚠️ Colaboradores no PDF sem Cadastro Correspondente (Serão Ignorados)</div>";
                                        $html .= "<ul class='text-xs text-rose-700 dark:text-rose-300 list-disc pl-5 space-y-1'>";
                                        foreach ($report['unmatched'] as $u) {
                                            $uName = e($u['pdf_name']);
                                            $uCpf = e($u['cpf']);
                                            $uCode = e($u['code']);
                                            $uNet = number_format($u['net_salary'], 2, ',', '.');
                                            $html .= "<li><b>{$uName}</b> — CPF: {$uCpf} | Cód. SCI: {$uCode} | Líquido: R$ {$uNet} (Cadastre ou ajuste o CPF/Código em Colaboradores para importar).</li>";
                                        }
                                        $html .= "</ul></div>";
                                    }

                                    // Tabela de colaboradores vinculados
                                    if ($matchedCount > 0) {
                                        $html .= "<div class='rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden'>";
                                        $html .= "<div class='px-3 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200'>Recibos Prontos para Importação</div>";
                                        $html .= "<table class='w-full text-xs text-left divide-y divide-gray-200 dark:divide-gray-700'>";
                                        $html .= "<thead class='bg-gray-50/50 dark:bg-gray-800/50 text-gray-500'><tr><th class='px-3 py-2'>Colaborador</th><th class='px-3 py-2'>Vínculo</th><th class='px-3 py-2 text-right'>Bruto</th><th class='px-3 py-2 text-right'>Descontos</th><th class='px-3 py-2 text-right'>Líquido</th></tr></thead><tbody class='divide-y divide-gray-200 dark:divide-gray-700'>";

                                        foreach ($report['matched'] as $m) {
                                            $empName = e($m['employee_name']);
                                            $matchInfo = e("{$m['matched_by']} ({$m['cpf']})");
                                            $gross = number_format($m['gross_salary'], 2, ',', '.');
                                            $ded = number_format($m['total_deductions'], 2, ',', '.');
                                            $net = number_format($m['net_salary'], 2, ',', '.');

                                            $extraNotes = '';
                                            if (!empty($m['vacation'])) {
                                                $vacPeriod = e($m['vacation']['period_br']);
                                                $extraNotes .= "<div class='text-[11px] text-amber-600 dark:text-amber-400'>☀️ Férias: {$vacPeriod} (será sincronizado na agenda)</div>";
                                            }
                                            foreach ($m['warnings'] as $w) {
                                                $wEsc = e($w);
                                                $extraNotes .= "<div class='text-[11px] text-rose-600 dark:text-rose-400'>⚠️ {$wEsc}</div>";
                                            }

                                            $html .= "<tr>
                                                <td class='px-3 py-2'><div class='font-semibold text-gray-900 dark:text-gray-100'>{$empName}</div>{$extraNotes}</td>
                                                <td class='px-3 py-2 text-gray-500'>{$matchInfo}</td>
                                                <td class='px-3 py-2 text-right'>R$ {$gross}</td>
                                                <td class='px-3 py-2 text-right text-rose-600'>R$ {$ded}</td>
                                                <td class='px-3 py-2 text-right font-bold text-emerald-600 dark:text-emerald-400'>R$ {$net}</td>
                                            </tr>";
                                        }
                                        $html .= "</tbody></table></div>";
                                    }

                                    $html .= "</div>";
                                    return new HtmlString($html);
                                }),
                        ]),
                ])
                ->action(function (array $data, SciPayslipParserService $service) {
                    $rawFile = $data['pdf_file'] ?? null;
                    $storedPath = is_array($rawFile) ? reset($rawFile) : $rawFile;

                    try {
                        $path = Storage::disk('local')->path($storedPath);
                        $payroll = $service->processPdf($path);

                        Notification::make()
                            ->title('Folha Importada com Sucesso!')
                            ->body('Competência ' . PayrollReferenceType::formatPeriod($payroll->reference_period) . ' gravada.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erro ao importar PDF')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        if (is_string($storedPath) && Storage::disk('local')->exists($storedPath)) {
                            Storage::disk('local')->delete($storedPath);
                        }
                    }
                }),
        ];
    }
}