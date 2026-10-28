<?php

namespace App\Filament\Resources\BankStatements\Actions;

use App\Services\Accounting\BankMatchScoringService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use App\Models\BankStatement;
use App\Filament\Resources\BankStatements\Schemas\ConciliationTabsSchema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Blade;

class AutoConciliateBatchAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'auto_conciliate_batch';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Automação')
            ->color('warning')
            ->icon('heroicon-m-bolt')
            ->button()
            ->visible(fn () => BankStatement::where('status', 'pending')->where('amount', '<', 0)->exists())
            ->modalHeading('Conciliação Rápida em Lote')
            ->modalDescription('A nossa inteligência varreu as suas pendências e encontrou estes vínculos prováveis. Desmarque os que não fizerem sentido, ou aprove todos de uma só vez.')
            ->modalSubmitActionLabel('Aprovar e Conciliar')
            ->modalWidth('6xl')
            ->schema(function () {
                $service = new BankMatchScoringService();

                // Busca as saídas pendentes
                $pendingStatements = BankStatement::where('status', 'pending')
                    ->where('amount', '<', 0)
                    ->get();

                $queue = [];

                foreach ($pendingStatements as $stmt) {
                    $matches = $service->findMatchesFor($stmt);

                    if ($matches->isNotEmpty()) {
                        $bestMatch = $matches->first();

                        // Exibe correspondências com confiança mínima de 50%
                        if ($bestMatch['score'] >= 50) {
                            $queue[] = [
                                'stmt'    => $stmt,
                                'payable' => $bestMatch['candidate'],
                                'score'   => (int) $bestMatch['score'],
                            ];
                        }
                    }
                }

                // 🚀 ORDENAÇÃO PELO SCORE DECRESCENTE (Melhores matches primeiro)
                usort($queue, fn($a, $b) => $b['score'] <=> $a['score']);

                $options = [];

                foreach ($queue as $item) {
                    $stmt = $item['stmt'];
                    $payable = $item['payable'];
                    $score = $item['score'];

                    $stmtDate = $stmt->bank_date ? $stmt->bank_date->format('d/m/Y') : '-';
                    $stmtName = $stmt->counterparty_name ?? $stmt->description ?? '-';
                    $stmtAmt = Number::format(abs((float) $stmt->amount), maxPrecision: 2, locale: 'pt_BR');

                    $payableDate = $payable->due_date ? \Carbon\Carbon::parse($payable->due_date)->format('d/m/Y') : '-';
                    $payableName = $payable->supplier->name ?? ($payable->supplier->document_name ?? ($payable->employee->name ?? 'Fornecedor S/N'));
                    $payableAmt = Number::format(abs((float) $payable->amount), maxPrecision: 2, locale: 'pt_BR');

                    // Badge de Confiança
                    $badgeClass = match (true) {
                        $score >= 95 => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700/50',
                        $score >= 80 => 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300 border border-green-300 dark:border-green-700/50',
                        $score >= 65 => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300 border border-amber-300 dark:border-amber-700/50',
                        default      => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
                    };

                    // Linha em CSS Grid sem perigo de overflow horizontal
                    $label = "
                        <div class='grid grid-cols-[85px_minmax(0,1.2fr)_100px_minmax(0,1.2fr)_85px_100px_80px] gap-2.5 items-center w-full text-xs py-2 border-b border-gray-100 dark:border-gray-800/80'>
                            <div class='text-gray-500 dark:text-gray-400 font-medium whitespace-nowrap'>
                                {$stmtDate}
                            </div>
                            <div class='font-semibold text-gray-900 dark:text-white truncate' title='{$stmtName}'>
                                {$stmtName}
                            </div>
                            <div class='text-right font-semibold text-gray-800 dark:text-gray-200 tabular-nums'>
                                R$ {$stmtAmt}
                            </div>
                            <div class='font-medium text-gray-800 dark:text-gray-200 truncate' title='{$payableName}'>
                                {$payableName}
                            </div>
                            <div class='text-center text-gray-500 dark:text-gray-400 font-medium whitespace-nowrap'>
                                {$payableDate}
                            </div>
                            <div class='text-right font-medium text-gray-800 dark:text-gray-200 tabular-nums'>
                                R$ {$payableAmt}
                            </div>
                            <div class='text-center'>
                                <span class='inline-block px-2 py-0.5 rounded text-[10px] font-bold tracking-tight {$badgeClass}'>
                                    {$score}%
                                </span>
                            </div>
                        </div>
                    ";

                    $options[$stmt->id . '::' . $payable->id] = new HtmlString($label);
                }

                if (empty($options)) {
                    $sadIcon = Blade::render('<x-heroicon-o-face-frown class="w-12 h-12 mb-2 opacity-30"/>');
                    return [
                        Placeholder::make('no_matches')
                            ->hiddenLabel()
                            ->content(new HtmlString("
                                <div class='flex flex-col items-center justify-center p-8 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 text-sm italic text-center'>
                                    {$sadIcon}
                                    A nossa inteligência não encontrou correspondências com confiança suficiente nas pendências atuais.<br>Tente conciliar individualmente usando as outras opções.
                                </div>
                            "))
                    ];
                }

                return [
                    Grid::make(1)->schema([
                        Group::make([
                            Placeholder::make('table_header')
                                ->hiddenLabel()
                                ->content(new HtmlString("
                                    <style>
                                        .smart-match-wrapper {
                                            overflow-x: hidden !important;
                                            width: 100% !important;
                                        }
                                        .smart-match-list {
                                            width: 100% !important;
                                            overflow-x: hidden !important;
                                        }
                                        .smart-match-list .fi-fo-checkbox-list-option {
                                            width: 100% !important;
                                            max-width: 100% !important;
                                            display: flex !important;
                                            align-items: center !important;
                                            margin-bottom: 0 !important;
                                        }
                                        .smart-match-list .fi-fo-checkbox-list-option-text {
                                            width: 100% !important;
                                            min-width: 0 !important;
                                            flex: 1 1 0% !important;
                                        }
                                        .smart-match-list .fi-fo-checkbox-list-option-label {
                                            width: 100% !important;
                                            min-width: 0 !important;
                                            display: block !important;
                                        }
                                    </style>

                                    <div class='smart-match-wrapper'>
                                        <div class='pl-8 grid grid-cols-[85px_minmax(0,1.2fr)_100px_minmax(0,1.2fr)_85px_100px_80px] gap-2.5 items-center w-full text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 pb-2 border-b-2 border-gray-200 dark:border-gray-700 select-none'>
                                            <div>Data (Banco)</div>
                                            <div>Contraparte</div>
                                            <div class='text-right'>Valor (Banco)</div>
                                            <div>Fornecedor</div>
                                            <div class='text-center'>Vencimento</div>
                                            <div class='text-right'>Valor (Sistema)</div>
                                            <div class='text-center'>Score</div>
                                        </div>
                                    </div>
                                ")),

                            CheckboxList::make('approved_matches')
                                ->hiddenLabel()
                                ->options($options)
                                ->default(array_keys($options))
                                ->bulkToggleable()
                                ->columns(1)
                                ->extraAttributes([
                                    'class' => 'smart-match-list max-h-[58vh] overflow-y-auto overflow-x-hidden pr-2',
                                ]),
                        ])->columnSpanFull()
                    ])
                ];
            })
            ->action(function (array $data) {
                $approvedMatches = $data['approved_matches'] ?? [];

                if (empty($approvedMatches)) {
                    Notification::make()->title('Nenhuma ação realizada.')->warning()->send();
                    return;
                }

                $count = 0;

                foreach ($approvedMatches as $matchKey) {
                    [$stmtId, $payableId] = explode('::', $matchKey);

                    $statement = BankStatement::find($stmtId);
                    if (!$statement || $statement->status !== 'pending') continue;

                    ConciliationTabsSchema::processConciliation(
                        ['existing_payable_id' => 'payable_' . $payableId],
                        $statement
                    );

                    $count++;
                }

                Notification::make()
                    ->title("{$count} transações auto-conciliadas com sucesso!")
                    ->success()
                    ->send();
            });
    }
}
