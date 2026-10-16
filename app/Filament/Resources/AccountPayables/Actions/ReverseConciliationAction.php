<?php

namespace App\Filament\Resources\AccountPayables\Actions;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\BankStatement;
use App\Models\Transaction;
use App\Models\AccountPayable;
use App\Services\Accounting\BankMatchScoringService; // 🚀 Import do Cérebro!
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;

class ReverseConciliationAction
{
    public static function configure($action)
    {
        return $action
            ->label('Buscar no Extrato')
            ->icon('heroicon-o-sparkles')
            ->color('warning')
            ->visible(fn (?AccountPayable $record) => $record && $record->status === 'pending')
            ->modalHeading('Conciliar com Transação Bancária')
            ->modalDescription('O sistema encontrou estas transações órfãs no banco com valores parecidos. Qual delas pagou esta conta?')
            ->modalWidth('5xl')
            ->extraModalWindowAttributes(["class" => "ux-conciliation-modal"])
            ->form(function (?AccountPayable $record) {
                if (!$record) return [];

                // 1. Busca todos os extratos num raio de 60 dias (para garantir que apanha atrasos longos)
                $statements = BankStatement::where('status', 'pending')
                    ->where('amount', '<', 0)
                    ->whereBetween('bank_date', [
                        $record->due_date->copy()->subDays(15),
                        $record->due_date->copy()->addDays(45)
                    ])
                    ->get();

                // 2. Chama o Cérebro de Conciliação!
                $service = new BankMatchScoringService();
                $scoredQueue = [];

                foreach ($statements as $stmt) {
                    $score = $service->calculateScore($stmt, $record);

                    // Se a pontuação for boa OU o valor bater perfeitamente ao cêntimo (como salvaguarda)
                    if ($score >= 30 || abs(abs($stmt->amount) - abs($record->amount)) < 0.05) {
                        $scoredQueue[] = [
                            'stmt' => $stmt,
                            'score' => $score
                        ];
                    }
                }

                // 3. Ordena os melhores e limita ao TOP 3 para não estourar o modal!
                usort($scoredQueue, function ($a, $b) use ($record) {
                    if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
                    return abs($a['stmt']->bank_date->diffInDays($record->due_date)) <=> abs($b['stmt']->bank_date->diffInDays($record->due_date));
                });

                $topMatches = array_slice($scoredQueue, 0, 3); // 🚀 LIMITADO A 3!

                $options = [];
                $descriptions = [];

                foreach ($topMatches as $match) {
                    $options[$match['stmt']->id] = " ";
                    $descriptions[$match['stmt']->id] = new HtmlString(
                        view('components.bank-statement-row', [
                            'statement' => $match['stmt'],
                            'score' => $match['score']
                        ])->render()
                    );
                }

                return [
                    Grid::make(12)->schema([
                        Placeholder::make('payable_info')
                            ->hiddenLabel()
                            ->columnSpan(4)
                            ->content(fn() => new HtmlString(
                                view('components.account-payable-card', ['payable' => $record])->render()
                            )),

                        Grid::make(1)
                            ->columnSpan(8)
                            ->schema([
                                Radio::make('bank_statement_id')
                                    ->hiddenLabel()
                                    ->options($options)
                                    ->descriptions($descriptions)
                                    ->visible(count($options) > 0)
                                    ->live()
                                    // Se selecionar uma sugestão, limpa o select manual
                                    ->afterStateUpdated(fn($state, Set $set) => filled($state) ? $set("manual_bank_statement_id", null) : null),

                                Placeholder::make("no_matches")
                                    ->hiddenLabel()
                                    ->visible(count($options) === 0)
                                    ->content(function () {
                                        $searchIcon = Blade::render('<x-heroicon-m-magnifying-glass class="w-12 h-12 mb-2 opacity-20"/>');
                                        return new HtmlString("<div class=\"flex flex-col items-center justify-center p-6 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 text-sm italic text-center\">{$searchIcon}A IA não encontrou sugestões com alta probabilidade.</div>");
                                    }),

                                // 🚀 O DIVISOR
                                Placeholder::make("divider")
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="relative flex items-center py-2"><div class="grow border-t border-gray-200 dark:border-gray-700"></div><span class="shrink-0 mx-4 text-[10px] font-medium text-gray-400 uppercase tracking-wider text-center">Ou busque manualmente</span><div class="grow border-t border-gray-200 dark:border-gray-700"></div></div>')),

                                // 🚀 O SELECT PARA A BUSCA MANUAL (NOVO!)
                                Select::make('manual_bank_statement_id')
                                    ->hiddenLabel()
                                    ->placeholder("🔍 Pesquisar qualquer transação no extrato...")
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    // Se selecionar algo manual, limpa os botões de rádio
                                    ->afterStateUpdated(fn($state, Set $set) => filled($state) ? $set("bank_statement_id", null) : null)
                                    ->options(function () {
                                        return BankStatement::where('status', 'pending')
                                            ->where('amount', '<', 0)
                                            ->orderBy('bank_date', 'desc')
                                            ->limit(100) // Traz as 100 últimas para o dropdown ser rápido
                                            ->get()
                                            ->mapWithKeys(function ($s) {
                                                $date = $s->bank_date->format('d/m/Y');
                                                $val = number_format(abs($s->amount), 2, ',', '.');
                                                $desc = $s->counterparty_name ?: $s->description;
                                                return [$s->id => "{$date} | R$ {$val} | {$desc}"];
                                            });
                                    })
                            ]),
                    ])
                ];
            })
            ->action(function (AccountPayable $record, array $data, $livewire) {
                // 🚀 Pega o ID selecionado (seja da sugestão ou do manual)
                $selectedId = $data['manual_bank_statement_id'] ?? ($data['bank_statement_id'] ?? null);

                if (!$selectedId) {
                    Notification::make()->title('Selecione uma transação!')->warning()->send();
                    return;
                }

                $stmt = BankStatement::find($selectedId);
                if (!$stmt) return;

                $record->update([
                    'status' => 'paid',
                    'payment_date' => $stmt->bank_date,
                ]);

                $transaction = Transaction::create([
                    "type" => $stmt->type,
                    "amount" => abs((float) $stmt->amount),
                    "description" => $record->description,
                    "transaction_date" => clone $stmt->bank_date,
                    "supplier_id" => $record->supplier_id,
                ]);

                $stmt->update([
                    'status' => 'reconciled',
                    'conciliable_id' => $record->id,
                    'conciliable_type' => AccountPayable::class,
                    'transaction_id' => $transaction->id,
                ]);

                Notification::make()->title('Sincronizado com o Banco!')->success()->send();

                if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                    $livewire->redirect(
                        \App\Filament\Resources\AccountPayables\AccountPayableResource::getUrl('edit', ['record' => $record])
                    );
                }
            });
    }
}
