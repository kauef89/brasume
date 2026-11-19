<?php

namespace App\Filament\Resources\BankStatements\Actions;

use App\Models\BankAccount;
use App\Services\Accounting\OfxImportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

class ImportOfxAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'import_ofx';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Importar OFX')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalHeading('Importar Extrato Bancário (OFX com Dry Run)')
            ->modalDescription('Selecione a conta e os arquivos OFX para auditoria prévia antes de gravar no extrato.')
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Confirmar e Importar Transações')
            ->steps([
                Step::make('upload')
                    ->label('1. Conta & Arquivos OFX')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        Select::make('bank_account_id')
                            ->label('Conta Bancária de Destino')
                            ->options(BankAccount::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        FileUpload::make('ofx_file')
                            ->label('Arquivo(s) OFX')
                            ->multiple()
                            ->storeFiles(false)
                            ->required()
                            ->helperText('Você pode selecionar múltiplos arquivos .OFX de uma vez. Nenhum lançamento será gravado antes da conferência.'),
                    ]),

                Step::make('preview')
                    ->label('2. Auditoria & Dry Run')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Placeholder::make('ofx_dry_run_report')
                            ->hiddenLabel()
                            ->content(function (Get $get, OfxImportService $service) {
                                $accountId = $get('bank_account_id');
                                $account = $accountId ? BankAccount::find($accountId) : null;
                                $files = array_values((array) ($get('ofx_file') ?? []));

                                if (!$account || empty($files)) {
                                    return new HtmlString('<p class="text-sm text-gray-500">Selecione a conta bancária e ao menos um arquivo OFX para analisar.</p>');
                                }

                                $report = $service->analyzeFiles($files, $account);
                                $newCount = count($report['new_transactions']);
                                $dupCount = count($report['duplicates']);
                                $errCount = count($report['malformed_blocks']);
                                $unclassCount = count($report['unclassified_memos']);
                                $inFmt = number_format($report['total_inflow'], 2, ',', '.');
                                $outFmt = number_format($report['total_outflow'], 2, ',', '.');

                                $html = "<div class='space-y-4'>";

                                // Cards de Resumo
                                $html .= "<div class='grid grid-cols-1 md:grid-cols-4 gap-3'>";
                                $html .= "<div class='p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/40'><div class='text-xs text-emerald-700 dark:text-emerald-400'>Novas Transações</div><div class='text-xl font-bold text-emerald-800 dark:text-emerald-300'>{$newCount}</div><div class='text-[11px] text-emerald-600'>+R$ {$inFmt} / -R$ {$outFmt}</div></div>";
                                $html .= "<div class='p-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50/70 dark:bg-amber-950/40'><div class='text-xs text-amber-700 dark:text-amber-400'>Duplicadas (FITID)</div><div class='text-xl font-bold text-amber-800 dark:text-amber-300'>{$dupCount}</div><div class='text-[11px] text-amber-600'>Ignoradas com segurança</div></div>";
                                $html .= "<div class='p-3 rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-950/40'><div class='text-xs text-sky-700 dark:text-sky-400'>Históricos Genéricos</div><div class='text-xl font-bold text-sky-800 dark:text-sky-300'>{$unclassCount}</div><div class='text-[11px] text-sky-600'>Sem regra específica no dicionário</div></div>";
                                $html .= "<div class='p-3 rounded-xl border border-rose-200 dark:border-rose-800 bg-rose-50/70 dark:bg-rose-950/40'><div class='text-xs text-rose-700 dark:text-rose-400'>Blocos Inválidos</div><div class='text-xl font-bold text-rose-800 dark:text-rose-300'>{$errCount}</div><div class='text-[11px] text-rose-600'>Falhas de leitura no XML/OFX</div></div>";
                                $html .= "</div>";

                                // Alerta de blocos malformados
                                if ($errCount > 0) {
                                    $html .= "<div class='p-3 rounded-xl border border-rose-300 dark:border-rose-700 bg-rose-50/80 dark:bg-rose-950/40 space-y-2'>";
                                    $html .= "<div class='text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider'>❌ Blocos OFX Ignorados por Falha de Leitura</div>";
                                    foreach ($report['malformed_blocks'] as $err) {
                                        $f = e($err['file']);
                                        $r = e($err['reason']);
                                        $s = e($err['snippet']);
                                        $html .= "<div class='p-2 rounded bg-white/80 dark:bg-gray-900/70 border border-rose-200 dark:border-rose-800 text-xs'>
                                            <div class='font-bold text-rose-700 dark:text-rose-400'>{$f} (Bloco #{$err['block']}): <span class='font-medium'>{$r}</span></div>
                                            <div class='text-[11px] font-mono text-gray-500 break-all mt-0.5'>{$s}</div>
                                        </div>";
                                    }
                                    $html .= "</div>";
                                }

                                // Tabela de Novas Transações (limitada visualmente às primeiras 40 para manter o modal leve)
                                if ($newCount > 0) {
                                    $previewSlice = array_slice($report['new_transactions'], 0, 40);
                                    $extraRows = $newCount - count($previewSlice);

                                    $html .= "<div class='rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden'>";
                                    $html .= "<div class='px-3 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200'>Prévia das Novas Transações ({$newCount})</div>";
                                    $html .= "<table class='w-full text-xs text-left divide-y divide-gray-200 dark:divide-gray-700'>";
                                    $html .= "<thead class='bg-gray-50/50 dark:bg-gray-800/50 text-gray-500'><tr><th class='px-3 py-2'>Data</th><th class='px-3 py-2'>Descrição / Dicionário</th><th class='px-3 py-2'>Tipo</th><th class='px-3 py-2'>Ação Automática</th><th class='px-3 py-2 text-right'>Valor</th></tr></thead><tbody class='divide-y divide-gray-200 dark:divide-gray-700'>";

                                    foreach ($previewSlice as $tx) {
                                        $d = Carbon::parse($tx['bank_date'])->format('d/m/Y');
                                        $desc = e($tx['clean_description']);
                                        $rawMemo = e($tx['raw_memo']);
                                        $mType = e($tx['movement_type']);
                                        $synthBadge = $tx['synthetic_fitid']
                                            ? "<span class='ml-1 px-1.5 py-0.5 text-[10px] rounded bg-amber-100 text-amber-800'>FITID Sintético</span>"
                                            : '';
                                        $auto = $tx['auto_action']
                                            ? "<span class='text-emerald-600 dark:text-emerald-400 font-semibold'>⚡ " . e($tx['auto_action']) . "</span>"
                                            : "<span class='text-gray-400'>Fila de Conciliação</span>";
                                        $amtClass = $tx['amount'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
                                        $amtFmt = ($tx['amount'] >= 0 ? '+' : '-') . ' R$ ' . number_format(abs($tx['amount']), 2, ',', '.');

                                        $html .= "<tr>
                                            <td class='px-3 py-2 font-medium whitespace-nowrap'>{$d}</td>
                                            <td class='px-3 py-2'><div class='font-semibold text-gray-900 dark:text-gray-100'>{$desc}{$synthBadge}</div><div class='text-[10px] text-gray-400 font-mono truncate max-w-md'>{$rawMemo}</div></td>
                                            <td class='px-3 py-2'><span class='px-1.5 py-0.5 rounded text-[10px] bg-gray-100 dark:bg-gray-800'>{$mType}</span></td>
                                            <td class='px-3 py-2 text-[11px]'>{$auto}</td>
                                            <td class='px-3 py-2 text-right font-bold whitespace-nowrap {$amtClass}'>{$amtFmt}</td>
                                        </tr>";
                                    }

                                    $html .= "</tbody></table>";
                                    if ($extraRows > 0) {
                                        $html .= "<div class='px-3 py-2 bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 text-center'>... e mais {$extraRows} transação(ões) prontas para importação.</div>";
                                    }
                                    $html .= "</div>";
                                }

                                // Históricos não reconhecidos pelo dicionário do banco
                                if ($unclassCount > 0) {
                                    $sampleUnclass = array_slice($report['unclassified_memos'], 0, 10);
                                    $html .= "<div class='p-3 rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20 space-y-1'>";
                                    $html .= "<div class='text-xs font-bold text-sky-800 dark:text-sky-300 uppercase tracking-wider'>ℹ️ Históricos sem Regra Específica no Dicionário ({$unclassCount}) — Entrarão como 'other'</div><ul class='text-[11px] text-sky-700 dark:text-sky-300 list-disc pl-5 space-y-0.5 font-mono'>";
                                    foreach ($sampleUnclass as $u) {
                                        $d = Carbon::parse($u['bank_date'])->format('d/m');
                                        $v = number_format($u['amount'], 2, ',', '.');
                                        $m = e($u['raw_memo']);
                                        $html .= "<li>[{$d}] R$ {$v} — {$m}</li>";
                                    }
                                    $html .= "</ul></div>";
                                }

                                $html .= "</div>";
                                return new HtmlString($html);
                            }),
                    ]),
            ])
            ->action(function (array $data, OfxImportService $service) {
                $account = BankAccount::find($data['bank_account_id'] ?? null);
                $uploadedFiles = array_values((array) ($data['ofx_file'] ?? []));

                if (!$account || empty($uploadedFiles)) {
                    Notification::make()->title('Dados incompletos')->danger()->send();
                    return;
                }

                $res = $service->importFiles($uploadedFiles, $account);
                $importedCount = $res['imported_count'];

                if ($importedCount === 0) {
                    Notification::make()
                        ->title('Nenhuma transação nova!')
                        ->body('Todas as transações válidas já existiam no banco de dados.')
                        ->info()
                        ->send();
                    return;
                }

                $parts = ["Foram adicionadas {$importedCount} transações."];
                $autoBits = [];
                if ($res['auto_card_batches'] > 0)   $autoBits[] = "{$res['auto_card_batches']} lotes de cartão";
                if ($res['auto_pix_count'] > 0)      $autoBits[] = "{$res['auto_pix_count']} recebimentos Pix de pedidos";
                if ($res['auto_matches_count'] > 0)  $autoBits[] = "{$res['auto_matches_count']} pagamentos exatos";
                if ($res['auto_personal_count'] > 0) $autoBits[] = "{$res['auto_personal_count']} despesas de sócios";
                if ($res['auto_fees_count'] > 0)     $autoBits[] = "{$res['auto_fees_count']} taxas bancárias";

                if (!empty($autoBits)) {
                    $parts[] = 'Auto-conciliados: ' . implode(', ', $autoBits) . '.';
                }

                Notification::make()
                    ->title('Importação OFX Concluída!')
                    ->body(implode(' ', $parts))
                    ->success()
                    ->send();
            });
    }
}