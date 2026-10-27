<?php

namespace App\Filament\Resources\BankStatements\Actions;

use App\Services\Accounting\BankReconciliationService;
use App\Services\Accounting\BankMatchScoringService;
use App\Filament\Resources\BankStatements\Schemas\ConciliationTabsSchema;
use Filament\Actions\Action;
use App\Models\BankStatement;
use App\Models\AccountPayable;
use App\Models\PaymentBatch;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\HtmlString;
use Filament\Support\Enums\Alignment;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Blade;

class AutoConciliateAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return "auto_conciliate";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label("Conciliação Automática")
            ->icon("heroicon-o-sparkles")
            ->color("warning")
            ->hidden(
                fn(): bool => BankStatement::query()
                    ->where("status", "pending")
                    ->where("amount", "<", 0)
                    ->count() === 0,
            )
            ->modalHeading("Conciliação Automática")
            ->modalDescription("Selecione o par perfeito para esta transação.")
            ->modalWidth("5xl")
            ->extraModalWindowAttributes(["class" => "ux-conciliation-modal"])
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalCancelAction(false)
            ->modalSubmitAction(
                fn($action, $livewire) => $action
                    ->label("Conciliar e Avançar")
                    ->visible(fn() => $livewire->currentStmtId !== null),
            )
            ->extraModalFooterActions(
                fn($livewire): array => [
                    Action::make("skip_transaction")
                        ->label("Pular esta transação")
                        ->color("gray")
                        ->icon("heroicon-m-forward")
                        ->visible(fn() => $livewire->currentStmtId !== null)
                        ->action(function ($livewire, Action $action) {
                            if ($livewire->currentStmtId) {
                                $skipped = session()->get("auto_conciliate_skipped", []);
                                $skipped[] = $livewire->currentStmtId;
                                session()->put("auto_conciliate_skipped", array_unique($skipped));
                            }
                            $livewire->replaceMountedAction("auto_conciliate");
                            $action->halt();
                        }),
                ],
            )
            ->form(function ($livewire) {
                $skippedIds = session()->get("auto_conciliate_skipped", []);

                // 🚀 BUSCA INSTANTÂNEA: 1 ÚNICA QUERY INDEXADA
                $statement = BankStatement::query()
                    ->where("status", "pending")
                    ->where("amount", "<", 0)
                    ->when(!empty($skippedIds), fn($q) => $q->whereNotIn("id", $skippedIds))
                    ->orderByDesc("match_score")
                    ->orderBy("bank_date")
                    ->first();

                // Se pulou todos e acabaram, limpa o skip para reiniciar
                if (!$statement && !empty($skippedIds)) {
                    session()->forget("auto_conciliate_skipped");
                    $statement = BankStatement::query()
                        ->where("status", "pending")
                        ->where("amount", "<", 0)
                        ->orderByDesc("match_score")
                        ->orderBy("bank_date")
                        ->first();
                }

                if (!$statement) {
                    session()->forget("auto_conciliate_skipped");
                    $livewire->currentStmtId = null;
                    return [
                        Placeholder::make("empty")
                            ->hiddenLabel()
                            ->content(new HtmlString('<div class="text-center text-gray-500 py-12">🎉 Não há mais despesas para conciliar!</div>')),
                    ];
                }

                $livewire->currentStmtId = $statement->id;

                // 🚀 CALCULA CANDIDATOS APENAS PARA O ITEM QUE ESTÁ NA TELA
                $service = new BankMatchScoringService();
                $matches = $service->findMatchesFor($statement);

                $bestCandidateId = count($matches) > 0 ? "payable_" . $matches->first()["candidate"]->id : null;

                return [
                    Grid::make(12)->schema([
                        Placeholder::make("bank_transaction")
                            ->hiddenLabel()
                            ->columnSpan(4)
                            ->content(fn() => view('components.bank-transaction-card', ['statement' => $statement])),

                        Grid::make(1)
                            ->columnSpan(8)
                            ->schema([
                                Radio::make("selected_match")
                                    ->hiddenLabel()
                                    ->live()
                                    ->afterStateUpdated(
                                        fn($state, Set $set) => filled($state) ? $set("manual_payable_id", null) : null,
                                    )
                                    ->visible(count($matches) > 0)
                                    ->options(function () use ($matches) {
                                        $options = [];
                                        foreach ($matches as $match) {
                                            $options["payable_" . $match["candidate"]->id] = " ";
                                        }
                                        return $options;
                                    })
                                    ->descriptions(function () use ($matches) {
                                        $descriptions = [];
                                        foreach ($matches as $match) {
                                            $descriptions["payable_" . $match["candidate"]->id] = new HtmlString(
                                                view('components.payable-candidate-row', [
                                                    'candidate' => $match["candidate"],
                                                    'score'     => $match["score"]
                                                ])->render()
                                            );
                                        }
                                        return $descriptions;
                                    })
                                    ->default($bestCandidateId),

                                Placeholder::make("divider")
                                    ->hiddenLabel()
                                    ->visible(count($matches) > 0)
                                    ->content(new HtmlString('<div class="relative flex items-center py-2"><div class="grow border-t border-gray-200 dark:border-gray-700"></div><span class="shrink-0 mx-4 text-xs font-medium text-gray-400 uppercase tracking-wider text-center">Ou busque manualmente</span><div class="grow border-t border-gray-200 dark:border-gray-700"></div></div>')),

                                Select::make("manual_payable_id")
                                    ->hiddenLabel()
                                    ->placeholder("🔍 Pesquisar conta ou lote existente...")
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        $payables = AccountPayable::query()
                                            ->with(['supplier', 'employee'])
                                            ->where("status", "pending")
                                            ->whereNull("payment_batch_id")
                                            ->where(function ($q) use ($search) {
                                                $q->where('description', 'like', "%{$search}%")
                                                  ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
                                            })
                                            ->limit(15)
                                            ->get()
                                            ->mapWithKeys(fn($p) => ["payable_" . $p->id => "📄 " . ($p->supplier->name ?? ($p->employee->name ?? "S/F")) . " - R$ " . number_format($p->amount, 2, ",", ".")]);

                                        return $payables->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        if (str_starts_with($value, 'payable_')) {
                                            $p = AccountPayable::find(str_replace('payable_', '', $value));
                                            return $p ? "📄 " . ($p->supplier->name ?? "S/F") . " - R$ " . number_format($p->amount, 2, ",", ".") : null;
                                        }
                                        return null;
                                    })
                                    ->live()
                                    ->afterStateUpdated(
                                        fn($state, Set $set) => filled($state) ? $set("selected_match", null) : null,
                                    )
                                    ->suffixAction(
                                        Action::make("advanced_conciliation")
                                            ->icon("heroicon-m-plus")
                                            ->tooltip("Criar Nova Despesa")
                                            ->modalHeading("Nova Despesa Avançada")
                                            ->modalWidth("4xl")
                                            ->form([
                                                ConciliationTabsSchema::make($statement),
                                            ])
                                            ->action(function (array $data, $livewire) use ($statement) {
                                                ConciliationTabsSchema::processConciliation($data, $statement);
                                                Notification::make()->title("Conciliado com sucesso!")->success()->send();
                                                $livewire->replaceMountedAction("auto_conciliate");
                                            }),
                                    ),

                                Placeholder::make("no_matches")
                                    ->hiddenLabel()
                                    ->visible(count($matches) === 0)
                                    ->content(function () {
                                        $searchIcon = Blade::render('<x-heroicon-m-magnifying-glass class="w-12 h-12 mb-2 opacity-20"/>');
                                        return new HtmlString("<div class=\"flex flex-col items-center justify-center p-8 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 text-sm italic text-center\">{$searchIcon}Nenhuma sugestão disponível para esta transação.<br>Use a busca manual acima.</div>");
                                    }),
                            ]),
                    ]),
                    Hidden::make("statement_id")->default(fn() => $statement->id),
                ];
            })
            ->action(function (array $data, $livewire, Action $action) {
                $selectedId = $data["manual_payable_id"] ?? ($data["selected_match"] ?? null);

                if ($selectedId && !empty($data["statement_id"])) {
                    $statement = BankStatement::query()->find($data["statement_id"]);

                    ConciliationTabsSchema::processConciliation(
                        ["existing_payable_id" => $selectedId],
                        $statement,
                    );

                    Notification::make()->title("Conciliado com sucesso!")->success()->send();

                    $skipped = session()->get("auto_conciliate_skipped", []);
                    if (($key = array_search($data["statement_id"], $skipped)) !== false) {
                        unset($skipped[$key]);
                        session()->put("auto_conciliate_skipped", array_values($skipped));
                    }
                }

                $livewire->replaceMountedAction("auto_conciliate");
                $action->halt();
            });
    }
}
