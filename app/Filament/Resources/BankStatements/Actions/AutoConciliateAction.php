<?php

namespace App\Filament\Resources\BankStatements\Actions;

use Filament\Actions\Action;
use App\Models\BankStatement;
use App\Models\AccountPayable;
use App\Models\PaymentBatch;
use App\Services\ReconciliationService;
use App\Filament\Resources\BankStatements\Schemas\ConciliationTabsSchema;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\HtmlString;
use Filament\Support\Enums\Alignment;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Blade; // 🚀 Adicionado para renderizar os Heroicons

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
                // 🚀 Corrigido: ::query()->where() para não dar erro no Intelephense
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
                // 🚀 Corrigido: Remoção do typehint `Component` que causava os alertas
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
                                $skipped = session()->get(
                                    "auto_conciliate_skipped",
                                    [],
                                );
                                $skipped[] = $livewire->currentStmtId;
                                session()->put(
                                    "auto_conciliate_skipped",
                                    array_unique($skipped),
                                );
                            }
                            $livewire->replaceMountedAction("auto_conciliate");
                            $action->halt();
                        }),
                ],
            )
            ->form(function ($livewire) {
                $pendingStatements = BankStatement::query()
                    ->where("status", "pending")
                    ->where("amount", "<", 0)
                    ->get();

                if ($pendingStatements->isEmpty()) {
                    session()->forget("auto_conciliate_skipped");
                    $livewire->currentStmtId = null;
                    return [
                        Placeholder::make("empty")
                            ->hiddenLabel()
                            ->content(
                                new HtmlString(
                                    '<div class="text-center text-gray-500 py-12">🎉 Não há mais despesas para conciliar!</div>',
                                ),
                            ),
                    ];
                }

                $service = new ReconciliationService();
                $scoredQueue = [];
                foreach ($pendingStatements as $stmt) {
                    $matches = $service->findMatchesFor($stmt);
                    $scoredQueue[] = [
                        "stmt" => $stmt,
                        "matches" => $matches,
                        "max_score" =>
                            $matches->count() > 0 ? $matches->max("score") : -1,
                    ];
                }

                $skippedIds = session()->get("auto_conciliate_skipped", []);
                usort($scoredQueue, function ($a, $b) use ($skippedIds) {
                    $aIsSkipped = in_array($a["stmt"]->id, $skippedIds);
                    $bIsSkipped = in_array($b["stmt"]->id, $skippedIds);
                    if ($aIsSkipped !== $bIsSkipped) {
                        return $aIsSkipped ? 1 : -1;
                    }
                    if ($b["max_score"] !== $a["max_score"]) {
                        return $b["max_score"] <=> $a["max_score"];
                    }
                    return $a["stmt"]->bank_date <=> $b["stmt"]->bank_date;
                });

                $bestItem = $scoredQueue[0];
                $statement = $bestItem["stmt"];
                $matches = $bestItem["matches"];
                $livewire->currentStmtId = $statement->id;

                $bestCandidateId =
                    count($matches) > 0
                        ? "payable_" . $matches->first()["candidate"]->id
                        : null;

                return [
                    Grid::make(12)->schema([
                        Placeholder::make("bank_transaction")
                            ->hiddenLabel()
                            ->columnSpan(4)
                            ->content(function () use ($statement) {
                                $color =
                                    $statement->amount < 0
                                        ? "text-danger-600"
                                        : "text-success-600";
                                $amount = number_format(
                                    abs((float) $statement->amount),
                                    2,
                                    ",",
                                    ".",
                                );

                                // 🚀 HEROICON NATIVO INJETADO VIA BLADE (Perfeito e Escalável)
                                $cal = Blade::render(
                                    '<x-heroicon-m-calendar class="w-4 h-4 mr-2 text-gray-400 shrink-0"/>',
                                );

                                return new HtmlString("
                                    <div class='flex flex-col h-full p-6 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700'>
                                        <div class='flex items-center text-sm font-semibold text-gray-500 uppercase pb-4 border-b border-gray-200 dark:border-gray-700'>{$cal} {$statement->bank_date->format(
                                    "d/m/Y",
                                )}</div>
                                        <div class='grow mt-4 mb-8'>
                                            <p class='text-lg text-gray-900 dark:text-white font-bold leading-tight'>{$statement->description}</p>
                                            <p class='text-xs text-gray-400 mt-2 uppercase'>FITID: {$statement->fitid}</p>
                                        </div>
                                        <div class='pt-4 border-t border-gray-200 dark:border-gray-700'>
                                            <p class='text-2xl font-bold {$color}'>R$ {$amount}</p>
                                        </div>
                                    </div>
                                ");
                            }),

                        Grid::make(1)
                            ->columnSpan(8)
                            ->schema([
                                Radio::make("selected_match")
                                    ->hiddenLabel()
                                    ->live()
                                    ->afterStateUpdated(
                                        fn($state, Set $set) => filled($state)
                                            ? $set("manual_payable_id", null)
                                            : null,
                                    )
                                    ->visible(count($matches) > 0)
                                    ->options(function () use ($matches) {
                                        $options = [];
                                        foreach ($matches as $match) {
                                            $options[
                                                "payable_" .
                                                    $match["candidate"]->id
                                            ] = " ";
                                        }
                                        return $options;
                                    })
                                    ->descriptions(function () use ($matches) {
                                        $descriptions = [];

                                        // 🚀 TODOS OS HEROICONS NATIVOS DO FILAMENT!
                                        $calI = Blade::render(
                                            '<x-heroicon-m-calendar class="w-4 h-4 mr-1.5 text-gray-400 shrink-0"/>',
                                        );
                                        $usrI = Blade::render(
                                            '<x-heroicon-m-user class="w-4 h-4 mr-1.5 text-gray-400 shrink-0"/>',
                                        );
                                        $curI = Blade::render(
                                            '<x-heroicon-m-currency-dollar class="w-4 h-4 mr-1.5 text-gray-400 shrink-0"/>',
                                        );
                                        $chkI = Blade::render(
                                            '<x-heroicon-m-check class="w-4 h-4 mr-1.5 shrink-0"/>',
                                        );

                                        foreach ($matches as $match) {
                                            $candidate = $match["candidate"];
                                            $val =
                                                "R$ " .
                                                number_format(
                                                    abs(
                                                        (float) $candidate->amount,
                                                    ),
                                                    2,
                                                    ",",
                                                    ".",
                                                );
                                            $date = \Carbon\Carbon::parse(
                                                $candidate->due_date,
                                            )->format("d/m/Y");
                                            $score = $match["score"];
                                            $badgeColor =
                                                $score >= 80
                                                    ? "bg-success-100 text-success-800 dark:bg-success-800 dark:text-success-100"
                                                    : ($score >= 50
                                                        ? "bg-warning-100 text-warning-800 dark:bg-warning-800 dark:text-warning-100"
                                                        : "bg-danger-100 text-danger-800 dark:bg-danger-800 dark:text-danger-100");
                                            $name =
                                                $candidate->supplier->name ??
                                                ($candidate->employee->name ??
                                                    "Fornecedor S/N");

                                            $descriptions[
                                                "payable_" . $candidate->id
                                            ] = new HtmlString("
                                                <div class='flex items-center justify-between w-full gap-4 px-2 py-1'>
                                                    <div class='flex items-center text-sm text-gray-600 dark:text-gray-300 w-28'>{$calI} {$date}</div>
                                                    <div class='flex items-center grow text-left text-gray-800 dark:text-gray-200'>{$usrI} {$name}</div>
                                                    <div class='flex items-center justify-end text-gray-700 dark:text-gray-300 w-32'>{$curI} {$val}</div>
                                                    <div class='w-32 text-right'>
                                                        <span class='inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] uppercase font-bold tracking-wider whitespace-nowrap shrink-0 $badgeColor'>{$chkI} {$score}% Match</span>
                                                    </div>
                                                </div>
                                            ");
                                        }
                                        return $descriptions;
                                    })
                                    ->default($bestCandidateId),

                                Placeholder::make("divider")
                                    ->hiddenLabel()
                                    ->visible(count($matches) > 0)
                                    ->content(
                                        new HtmlString(
                                            '<div class="relative flex items-center py-2"><div class="grow border-t border-gray-200 dark:border-gray-700"></div><span class="shrink-0 mx-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Ou busque manualmente</span><div class="grow border-t border-gray-200 dark:border-gray-700"></div></div>',
                                        ),
                                    ),

                                Select::make("manual_payable_id")
                                    ->hiddenLabel()
                                    ->placeholder(
                                        "🔍 Pesquisar conta ou lote existente...",
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(
                                        fn($state, Set $set) => filled($state)
                                            ? $set("selected_match", null)
                                            : null,
                                    )
                                    ->options(function () {
                                        $payables = AccountPayable::query()
                                            ->where("status", "pending")
                                            ->whereNull("payment_batch_id")
                                            ->get()
                                            ->mapWithKeys(
                                                fn($p) => [
                                                    "payable_" . $p->id =>
                                                        "📄 " .
                                                        ($p->supplier->name ??
                                                            ($p->employee
                                                                ->name ??
                                                                "S/F")) .
                                                        " - R$ " .
                                                        number_format(
                                                            $p->amount,
                                                            2,
                                                            ",",
                                                            ".",
                                                        ),
                                                ],
                                            );
                                        $batches = PaymentBatch::query()
                                            ->where("status", "pending")
                                            ->get()
                                            ->mapWithKeys(
                                                fn($b) => [
                                                    "batch_" . $b->id =>
                                                        "📦 LOTE: " .
                                                        $b->description .
                                                        " - R$ " .
                                                        number_format(
                                                            $b->total_amount,
                                                            2,
                                                            ",",
                                                            ".",
                                                        ),
                                                ],
                                            );
                                        return collect($payables)
                                            ->union($batches)
                                            ->toArray();
                                    })
                                    ->suffixAction(
                                        Action::make("advanced_conciliation")
                                            ->icon("heroicon-m-plus")
                                            ->tooltip("Criar Nova Despesa")
                                            ->modalHeading(
                                                "Nova Despesa Avançada",
                                            )
                                            ->modalWidth("4xl")
                                            ->form([
                                                ConciliationTabsSchema::make(
                                                    $statement,
                                                ),
                                            ])
                                            ->action(function (
                                                array $data,
                                                $livewire,
                                            ) use ($statement) {
                                                ConciliationTabsSchema::processConciliation(
                                                    $data,
                                                    $statement,
                                                );
                                                Notification::make()
                                                    ->title(
                                                        "Conciliado com sucesso!",
                                                    )
                                                    ->success()
                                                    ->send();
                                                $livewire->replaceMountedAction(
                                                    "auto_conciliate",
                                                );
                                            }),
                                    ),

                                Placeholder::make("no_matches")
                                    ->hiddenLabel()
                                    ->visible(count($matches) === 0)
                                    ->content(function () {
                                        $searchIcon = Blade::render(
                                            '<x-heroicon-m-magnifying-glass class="w-12 h-12 mb-2 opacity-20"/>',
                                        );
                                        return new HtmlString(
                                            "<div class=\"flex flex-col items-center justify-center p-8 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 text-sm italic text-center\">{$searchIcon}Nenhuma sugestão disponível para esta transação.<br>Use a busca manual acima.</div>",
                                        );
                                    }),
                            ]),
                    ]),
                    Hidden::make("statement_id")->default(
                        fn() => $statement->id,
                    ),
                ];
            })
            ->action(function (array $data, $livewire, Action $action) {
                $selectedId =
                    $data["manual_payable_id"] ??
                    ($data["selected_match"] ?? null);

                if ($selectedId && !empty($data["statement_id"])) {
                    $statement = BankStatement::query()->find(
                        $data["statement_id"],
                    );

                    ConciliationTabsSchema::processConciliation(
                        ["existing_payable_id" => $selectedId],
                        $statement,
                    );

                    Notification::make()
                        ->title("Conciliado com sucesso!")
                        ->success()
                        ->send();

                    $skipped = session()->get("auto_conciliate_skipped", []);
                    if (
                        ($key = array_search(
                            $data["statement_id"],
                            $skipped,
                        )) !== false
                    ) {
                        unset($skipped[$key]);
                        session()->put(
                            "auto_conciliate_skipped",
                            array_values($skipped),
                        );
                    }
                }

                $livewire->replaceMountedAction("auto_conciliate");
                $action->halt();
            });
    }
}
