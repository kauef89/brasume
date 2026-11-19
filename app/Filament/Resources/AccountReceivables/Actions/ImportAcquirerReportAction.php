<?php

namespace App\Filament\Resources\AccountReceivables\Actions;

use App\Services\Financial\Acquirers\AcquirerReconciliationEngine;
use App\Services\Financial\Acquirers\Parsers\SicrediAposParser;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportAcquirerReportAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'import_acquirer_report';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Importar Relatório de Vendas (CSV)')
            ->icon('heroicon-o-receipt-percent')
            ->color('success')
            ->modalHeading('Conciliação de Vendas da Adquirente (com Dry Run)')
            ->modalDescription('Audite todas as linhas do relatório CSV da maquininha (APOS / SiTef / Sicredi) antes de gravar e conciliar.')
            ->modalSubmitActionLabel('Confirmar e Conciliar Relatório')
            ->modalWidth('5xl')
            ->steps([
                Step::make('upload')
                    ->label('1. Selecionar Relatório CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        FileUpload::make('report_file')
                            ->label('Arquivo CSV da Adquirente')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->storeFiles(false)
                            ->required()
                            ->helperText('Formato padrão: delimitador ponto e vírgula (;). Todas as linhas serão auditadas antes da gravação.'),
                    ]),

                Step::make('preview')
                    ->label('2. Auditoria Linha a Linha (Dry Run)')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Placeholder::make('acquirer_dry_run_report')
                            ->hiddenLabel()
                            ->content(function (Get $get) {
                                $rawFile = $get('report_file');
                                $fileEntry = is_array($rawFile) ? reset($rawFile) : $rawFile;

                                if (!$fileEntry instanceof TemporaryUploadedFile) {
                                    return new HtmlString('<p class="text-sm text-gray-500">Selecione um arquivo CSV válido para iniciar a auditoria.</p>');
                                }

                                try {
                                    $parser = new SicrediAposParser();
                                    $audit = $parser->parseCsvWithAudit($fileEntry->getRealPath());

                                    $engine = new AcquirerReconciliationEngine();
                                    $sim = $engine->previewReconcile($audit['transactions']);
                                } catch (\Throwable $e) {
                                    $msg = e($e->getMessage());
                                    return new HtmlString("<div class='p-4 rounded-xl border border-rose-300 bg-rose-50 text-rose-700 text-sm font-semibold'>❌ Falha na leitura do CSV: {$msg}</div>");
                                }

                                $newCount = count($sim['new_sales']);
                                $dupCount = count($sim['already_imported']);
                                $ignoredCount = count($audit['ignored_by_status']);
                                $unknownCount = count($audit['unknown_statuses']);
                                $malformedCount = count($audit['malformed_rows']);
                                $critErrors = $unknownCount + $malformedCount;

                                $grossFmt = number_format($audit['total_gross_approved'], 2, ',', '.');
                                $netFmt = number_format($audit['total_net_approved'], 2, ',', '.');

                                $html = "<div class='space-y-4'>";

                                // Cards de Resumo
                                $html .= "<div class='grid grid-cols-1 md:grid-cols-4 gap-3'>";
                                $html .= "<div class='p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/40'><div class='text-xs text-emerald-700 dark:text-emerald-400'>Vendas Aprovadas Novas</div><div class='text-xl font-bold text-emerald-800 dark:text-emerald-300'>{$newCount}</div><div class='text-[11px] text-emerald-600'>Bruto: R$ {$grossFmt} | Líq: R$ {$netFmt}</div></div>";
                                $html .= "<div class='p-3 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50/70 dark:bg-primary-950/40'><div class='text-xs text-primary-700 dark:text-primary-400'>Previsão de Conciliação</div><div class='text-sm font-bold text-primary-900 dark:text-primary-200 mt-1'>✅ {$sim['will_match']} auto • 🟡 {$sim['will_ambiguous']} dúvida • 🔴 {$sim['will_orphan']} órfãs</div><div class='text-[11px] text-primary-600'>{$dupCount} já importadas antes</div></div>";
                                $html .= "<div class='p-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50/70 dark:bg-amber-950/40'><div class='text-xs text-amber-700 dark:text-amber-400'>Ignoradas (Cancel./Negadas)</div><div class='text-xl font-bold text-amber-800 dark:text-amber-300'>{$ignoredCount}</div><div class='text-[11px] text-amber-600'>Total de linhas lidas: {$audit['total_data_lines']}</div></div>";
                                $html .= "<div class='p-3 rounded-xl border border-rose-200 dark:border-rose-800 bg-rose-50/70 dark:bg-rose-950/40'><div class='text-xs text-rose-700 dark:text-rose-400'>Falhas / Status Desconhecidos</div><div class='text-xl font-bold text-rose-800 dark:text-rose-300'>{$critErrors}</div><div class='text-[11px] text-rose-600'>{$malformedCount} malformadas • {$unknownCount} status novos</div></div>";
                                $html .= "</div>";

                                // 1. Alertas Críticos: Linhas Malformadas ou Status Desconhecidos
                                if ($critErrors > 0) {
                                    $html .= "<div class='p-3 rounded-xl border border-rose-300 dark:border-rose-700 bg-rose-50/80 dark:bg-rose-950/40 space-y-2'>";
                                    $html .= "<div class='text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider'>🚨 Atenção: Linhas com Falha de Leitura ou Status Não Mapeado</div>";
                                    foreach ($audit['unknown_statuses'] as $u) {
                                        $st = e($u['status']);
                                        $nsu = e($u['nsu']);
                                        $val = number_format($u['gross_amount'], 2, ',', '.');
                                        $html .= "<div class='p-2 rounded bg-white/80 dark:bg-gray-900/70 border border-rose-200 text-xs text-rose-700'>
                                            <b>Linha {$u['line']} (Status desconhecido '{$st}'):</b> {$u['time']} | NSU {$nsu} | Terminal {$u['terminal']} | R$ {$val}
                                        </div>";
                                    }
                                    foreach ($audit['malformed_rows'] as $m) {
                                        $reason = e($m['reason']);
                                        $prev = e($m['preview']);
                                        $html .= "<div class='p-2 rounded bg-white/80 dark:bg-gray-900/70 border border-rose-200 text-xs text-rose-700'>
                                            <b>Linha {$m['line']}:</b> {$reason} <div class='font-mono text-[10px] text-gray-500 mt-0.5'>{$prev}</div>
                                        </div>";
                                    }
                                    $html .= "</div>";
                                }

                                // 2. Terminais não cadastrados em Devices
                                if (!empty($sim['unregistered_terminals'])) {
                                    $termBadges = [];
                                    foreach ($sim['unregistered_terminals'] as $tid => $cnt) {
                                        $termBadges[] = '<b>' . e($tid) . "</b> ({$cnt} vendas)";
                                    }
                                    $html .= "<div class='p-3 rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50/80 dark:bg-amber-950/40 text-xs text-amber-800 dark:text-amber-300'>
                                        ⚠️ <b>Maquininhas sem cadastro em Dispositivos (Devices):</b> " . implode(', ', $termBadges) . ". Vincule o código do terminal no cadastro do dispositivo para melhorar a pontuação de conciliação.
                                    </div>";
                                }

                                // 3. Vendas Órfãs detectadas (Passadas na máquina sem pedido/pagamento no PDV)
                                if ($sim['will_orphan'] > 0) {
                                    $orphanSlice = array_slice($sim['orphan_preview'], 0, 20);
                                    $html .= "<div class='rounded-xl border border-rose-200 dark:border-rose-800 overflow-hidden'>";
                                    $html .= "<div class='px-3 py-2 bg-rose-50 dark:bg-rose-950/50 text-xs font-bold uppercase tracking-wider text-rose-800 dark:text-rose-300'>🔴 Vendas Órfãs Detectadas ({$sim['will_orphan']}) — Passadas na Maquininha sem Pagamento Correspondente no PDV</div>";
                                    $html .= "<table class='w-full text-xs text-left divide-y divide-gray-200 dark:divide-gray-700'>";
                                    $html .= "<thead class='bg-gray-50/50 dark:bg-gray-800/50 text-gray-500'><tr><th class='px-3 py-2'>Data/Hora</th><th class='px-3 py-2'>Terminal</th><th class='px-3 py-2'>NSU</th><th class='px-3 py-2'>Modalidade / Bandeira</th><th class='px-3 py-2 text-right'>Valor Bruto</th></tr></thead><tbody class='divide-y divide-gray-200 dark:divide-gray-700'>";
                                    foreach ($orphanSlice as $o) {
                                        $val = number_format($o['gross_amount'], 2, ',', '.');
                                        $html .= "<tr>
                                            <td class='px-3 py-1.5 whitespace-nowrap'>{$o['date']}</td>
                                            <td class='px-3 py-1.5'>" . e($o['terminal']) . " <span class='text-[10px] text-gray-400'>(" . e($o['device_name']) . ")</span></td>
                                            <td class='px-3 py-1.5 font-mono'>" . e($o['nsu']) . "</td>
                                            <td class='px-3 py-1.5'>" . e("{$o['modality']} • {$o['brand']}") . "</td>
                                            <td class='px-3 py-1.5 text-right font-bold text-rose-600'>R$ {$val}</td>
                                        </tr>";
                                    }
                                    $html .= "</tbody></table></div>";
                                }

                                // 4. Linhas ignoradas por status (Canceladas / Negadas)
                                if ($ignoredCount > 0) {
                                    $ignSlice = array_slice($audit['ignored_by_status'], 0, 15);
                                    $html .= "<div class='p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/40 space-y-1'>";
                                    $html .= "<div class='text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider'>Linhas Ignoradas por Status Não Aprovado ({$ignoredCount})</div><ul class='text-[11px] text-gray-600 dark:text-gray-400 list-disc pl-5 space-y-0.5'>";
                                    foreach ($ignSlice as $ig) {
                                        $val = number_format($ig['gross_amount'], 2, ',', '.');
                                        $html .= "<li>Linha {$ig['line']} [<b>" . e($ig['status']) . "</b>]: {$ig['time']} | NSU " . e($ig['nsu']) . " | {$ig['modality']} | R$ {$val}</li>";
                                    }
                                    if ($ignoredCount > count($ignSlice)) {
                                        $restIgn = $ignoredCount - count($ignSlice);
                                        $html .= "<li>... e mais {$restIgn} transação(ões) canceladas/negadas.</li>";
                                    }
                                    $html .= "</ul></div>";
                                }

                                $html .= "</div>";
                                return new HtmlString($html);
                            }),
                    ]),
            ])
            ->action(function (array $data) {
                try {
                    $rawFile = $data['report_file'] ?? null;
                    $fileEntry = is_array($rawFile) ? reset($rawFile) : $rawFile;

                    if (!$fileEntry instanceof TemporaryUploadedFile) {
                        throw new Exception('Arquivo CSV não localizado para processamento.');
                    }

                    $parser = new SicrediAposParser();
                    $audit = $parser->parseCsvWithAudit($fileEntry->getRealPath());
                    $transactions = $audit['transactions'];

                    if (empty($transactions)) {
                        Notification::make()
                            ->title('Nenhuma venda válida encontrada')
                            ->body('O arquivo enviado não possui registros de vendas aprovadas ou autorizadas.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $engine = new AcquirerReconciliationEngine();
                    $results = $engine->reconcile($transactions);

                    $matched   = $results['matched'];
                    $ambiguous = $results['ambiguous'];
                    $orphan    = $results['orphan'];
                    $already   = $results['already_reconciled'];

                    $msgParts = [];
                    if ($matched > 0)   $msgParts[] = "✅ {$matched} venda(s) conciliada(s) automaticamente.";
                    if ($ambiguous > 0) $msgParts[] = "🟡 {$ambiguous} com dúvida (valores duplicados).";
                    if ($orphan > 0)    $msgParts[] = "🔴 {$orphan} órfã(s) (não lançadas no PDV).";
                    if ($already > 0)   $msgParts[] = "ℹ️ {$already} já processada(s) anteriormente.";

                    $notification = Notification::make()
                        ->title('Relatório Processado!')
                        ->body(implode("\n", $msgParts));

                    if ($ambiguous > 0 || $orphan > 0) {
                        $notification->warning()->persistent();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('Falha no processamento')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}