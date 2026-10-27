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
use App\Services\Accounting\BankReconciliationService;
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
            // Só aparece se houver pendências (evita processamento desnecessário)
            ->visible(fn () => BankStatement::where('status', 'pending')->where('amount', '<', 0)->exists())
            ->modalHeading('Conciliação Rápida em Lote')
            ->modalDescription('A nossa inteligência varreu as suas pendências e encontrou estes vínculos prováveis. Desmarque os que não fizerem sentido, ou aprove todos de uma só vez.')
            ->modalSubmitActionLabel('Aprovar e Conciliar')
            ->modalWidth('6xl')
            ->schema(function () {
                $options = [];
                $service = new BankMatchScoringService();

                // Busca todas as saídas pendentes
                $pendingStatements = BankStatement::where('status', 'pending')
                    ->where('amount', '<', 0)
                    ->get();

                foreach ($pendingStatements as $stmt) {
                    $matches = $service->findMatchesFor($stmt);

                    if ($matches->isNotEmpty()) {
                        $bestMatch = $matches->first();

                        // Só mostra sugestões com uma confiança decente (>= 50%)
                        if ($bestMatch['score'] >= 50) {
                            $payable = $bestMatch['candidate'];
                            $score = $bestMatch['score'];

                            // 1. Dados do Extrato (Banco)
                            $stmtDate = $stmt->bank_date->format('d/m/Y');
                            $stmtName = $stmt->counterparty_name ?? $stmt->description;
                            $stmtAmt = Number::format(abs((float)$stmt->amount), maxPrecision: 2, locale: 'pt_BR');

                            // 2. Dados do Sistema (AccountPayable)
                            $payableDate = \Carbon\Carbon::parse($payable->due_date)->format('d/m/Y');
                            $payableName = $payable->supplier->name ?? ($payable->employee->name ?? 'Fornecedor S/N');
                            $payableAmt = Number::format(abs((float)$payable->amount), maxPrecision: 2, locale: 'pt_BR');

                            $badgeColor = $score >= 80 ? 'text-success-700 bg-success-100' : 'text-warning-700 bg-warning-100';

                            // 🚀 O SEU LAYOUT DE FLEXBOX APLICADO AQUI!
                            $label = "
                                <div style='display: flex; width: 100%; gap: 1rem; align-items: center;' class='text-sm py-1.5 border-b border-gray-100 dark:border-gray-800 border-dashed'>
                                    <div style='flex: 2; min-width: 0;' class='text-gray-500 font-medium'>
                                        {$stmtDate}
                                    </div>
                                    <div style='flex: 4; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' class='font-semibold' title='{$stmtName}'>
                                        {$stmtName}
                                    </div>
                                    <div style='flex: 2; min-width: 0;' class='text-danger-600 font-medium'>
                                        R$ {$stmtAmt}
                                    </div>
                                    <div style='flex: 4; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' class='font-medium' title='{$payableName}'>
                                        {$payableName} <span class='ml-1 px-1.5 py-0.5 rounded text-[9px] uppercase tracking-wider font-medium {$badgeColor}'>{$score}% Match</span>
                                    </div>
                                    <div style='flex: 2; min-width: 0;' class='text-gray-500 font-medium'>
                                        {$payableDate}
                                    </div>
                                    <div style='flex: 2; text-align: right; min-width: 0;' class='font-medium text-gray-900 dark:text-gray-100'>
                                        R$ {$payableAmt}
                                    </div>
                                </div>
                            ";

                            $options[$stmt->id . '::' . $payable->id] = new HtmlString($label);
                        }
                    }
                }

                // Se a IA não achar absolutamente nada:
                if (empty($options)) {
                    $sadIcon = Blade::render('<x-heroicon-o-face-frown class="w-12 h-12 mb-2 opacity-30"/>');
                    return [
                        Placeholder::make('no_matches')
                            ->hiddenLabel()
                            ->content(new HtmlString("
                                <div class='flex flex-col items-center justify-center p-8 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 text-sm italic text-center'>
                                    {$sadIcon}
                                    A nossa inteligência não encontrou correspondências seguras para as pendências atuais.<br>Tente conciliar manualmente usando as outras opções.
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
                                        .smart-match-list { width: 100% !important; }
                                        .smart-match-list > div { width: 100% !important; display: block !important; }
                                        .smart-match-list label.fi-checkbox-list-option { width: 100% !important; display: flex !important; max-width: none !important; align-items: center; }
                                        .smart-match-list .fi-checkbox-list-option-label { width: 100% !important; flex: 1 1 auto !important; max-width: none !important; }
                                        .fi-fo-checkbox-list .fi-fo-checkbox-list-option .fi-fo-checkbox-list-option-text { width: 100%;}
                                    </style>

                                    <div style='display: flex; width: 100%; gap: 1rem; padding-bottom: 0.5rem; padding-left: 2.25rem;' class='text-xs font-medium uppercase tracking-wider text-gray-500 border-b border-gray-300 dark:border-gray-700'>
                                        <div style='flex: 2;'>Data (Banco)</div>
                                        <div style='flex: 4;'>Contraparte</div>
                                        <div style='flex: 2;'>Valor (Banco)</div>
                                        <div style='flex: 4;'>Fornecedor (Sistema)</div>
                                        <div style='flex: 2;'>Vencimento</div>
                                        <div style='flex: 2; text-align: right;'>Valor (Sistema)</div>
                                    </div>
                                ")),

                            CheckboxList::make('approved_matches')
                                ->hiddenLabel()
                                ->options($options)
                                ->default(array_keys($options)) // Deixa todas marcadas por padrão!
                                ->bulkToggleable()
                                ->columns(1)
                                ->extraAttributes([
                                    'class' => 'smart-match-list max-h-[60vh] overflow-y-auto pr-2',
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
                    if (!$statement) continue;

                    // O motor perfeito que criámos anteriormente
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
