<?php

namespace App\Filament\Resources\AccountPayables\Pages;

use App\Filament\Resources\AccountPayables\AccountPayableResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\RecurringExpense;
use App\Models\AccountPayable;
use Carbon\Carbon;
use Filament\Schemas\Components\Tabs\Tab;

class ListAccountPayables extends ListRecords
{
    protected static string $resource = AccountPayableResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'tabela';
    }

    public function getTabs(): array
    {
        return [
            'tabela' => Tab::make('Visão de Tabela')
                ->icon('heroicon-m-table-cells'),

            'calendario' => Tab::make('Visão de Calendário')
                ->icon('heroicon-m-calendar-days'),
        ];
    }

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $abaAtual = $this->activeTab ?? $this->getDefaultActiveTab();

        if ($abaAtual === 'calendario') {
            return parent::getTableQuery()->whereRaw('1 = 0');
        }

        return parent::getTableQuery();
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        $abaAtual = $this->activeTab ?? $this->getDefaultActiveTab();

        if ($abaAtual === 'calendario') {
            echo "<style>
                /* 1. Oculta tudo que pertence à tabela E o empty-state */
                .fi-ta-table,
                .fi-ta-search-field,
                .fi-ta-filters-dropdown,
                .fi-ta-actions,
                .fi-ta-empty-state {
                    display: none !important;
                }

                /* 2. Zera o esqueleto/cartão da tabela */
                .fi-ta-ctn.fi-ta-ctn-with-footer.fi-ta-ctn-with-header,
                .fi-ta-ctn {
                    background: transparent !important;
                    padding: 0 !important;
                    box-shadow: unset !important;
                    border: none !important;
                }

                /* 3. Limpa o toolbar do cabeçalho */
                .fi-ta-header-toolbar {
                    padding: 0 !important;
                    border: 0 !important;
                }

                .fi-ta { background: transparent !important; box-shadow: none !important; border: none !important; }

                /* 4. Layout 100% fluido do calendário */
                .fi-wi { height: calc(100vh - 250px) !important; margin-top: 0 !important; }
                .fi-wi > div { height: 100% !important; }
                .fc { height: 100% !important; background: white; border-radius: 0.75rem; padding: 1rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); }
                .dark .fc { background: #18181b; border: 1px solid #27272a; }

                /* 5. TIPOGRAFIA DO CALENDÁRIO */
                .fc {
                    font-family: 'SF Compact Rounded', ui-rounded, sans-serif !important;
                }

                /* Reverte o cabeçalho (Dom, Seg, Ter...) para a fonte normal */
                .fc-col-header-cell-cushion {
                    font-family: 'Stack Sans Text', system-ui, sans-serif !important;
                    font-size: 0.875rem !important;
                }

                /* 6. REDUÇÃO DO TAMANHO DA FONTE (-2px) */
                .fc-event-main, .fc-event-title, .fc-daygrid-day-number {
                    font-size: 0.75rem !important;
                    line-height: 1.2 !important;
                }
            </style>";
        } else {
            echo "<style>
                /* Ocultamos o Widget quando a aba Tabela está ativa */
                .fi-wi { display: none !important; }
            </style>";
        }

        return null;
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Resources\AccountPayables\Widgets\AccountPayablesCalendarWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('gerar_recorrentes')
                ->label('Lançamento Rápido (Mês)')
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->modalHeading('Gerar Contas Recorrentes')
                ->modalDescription('Selecione o mês e preencha os valores das contas variáveis. O sistema não duplicará contas já geradas.')
                ->modalWidth('4xl')
                ->fillForm(fn (): array => [
                    'reference_month' => now()->month,
                    'reference_year' => now()->year,
                    'accounts_to_generate' => $this->getAccountsToGenerateData(now()->month, now()->year),
                ])
                ->form([
                    Section::make('Período de Referência')
                        ->schema([
                            Select::make('reference_month')
                                ->label('Mês')
                                ->options([
                                    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                                    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                                    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                                ])
                                ->live()
                                ->afterStateUpdated(fn (Set $set, Get $get) => $set('accounts_to_generate', $this->getAccountsToGenerateData($get('reference_month'), $get('reference_year'))))
                                ->required(),

                            TextInput::make('reference_year')
                                ->label('Ano')
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(fn (Set $set, Get $get) => $set('accounts_to_generate', $this->getAccountsToGenerateData($get('reference_month'), $get('reference_year'))))
                                ->required(),
                        ])->columns(2),

                    Repeater::make('accounts_to_generate')
                        ->label('Contas Encontradas')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(4)
                        ->columnSpanFull()
                        ->schema([
                            Hidden::make('fixed_account_id'),
                            TextInput::make('name')->label('Conta')->readOnly()->columnSpan(2),
                            TextInput::make('due_date')->label('Vencimento')->readOnly()->columnSpan(1),
                            TextInput::make('amount')
                                ->label('Valor (R$)')
                                ->numeric()
                                ->prefix('R$')
                                ->required()
                                ->columnSpan(1),
                        ])
                ])
                ->action(function (array $data) {
                    $gerados = 0;
                    foreach ($data['accounts_to_generate'] as $item) {
                        $recurringExpense = RecurringExpense::find($item['fixed_account_id']);
                        if (!$recurringExpense) continue;
                        $dueDate = Carbon::createFromFormat('d/m/Y', $item['due_date'])->format('Y-m-d');

                        $exists = AccountPayable::where('fixed_account_id', $recurringExpense->id)
                            ->whereMonth('due_date', clone Carbon::parse($dueDate)->month)
                            ->whereYear('due_date', clone Carbon::parse($dueDate)->year)
                            ->exists();

                        if (!$exists) {
                            AccountPayable::create([
                                'supplier_id' => $recurringExpense->supplier_id,
                                'fixed_account_id' => $recurringExpense->id,
                                'description' => $recurringExpense->name . ' - Ref ' . str_pad($data['reference_month'], 2, '0', STR_PAD_LEFT) . '/' . $data['reference_year'],
                                'amount' => $item['amount'],
                                'due_date' => $dueDate,
                                'status' => 'pending',
                                'accounting_category' => $recurringExpense->accounting_category,
                                'accounting_subcategory' => $recurringExpense->accounting_subcategory,
                                'contract_number' => $recurringExpense->contract_number,
                                'is_auto_debit' => $recurringExpense->is_auto_debit,
                                'account_holder' => $recurringExpense->account_holder,
                                'paid_by' => $recurringExpense->paid_by,
                            ]);
                            $gerados++;
                        }
                    }

                    Notification::make()
                        ->title("Sucesso!")
                        ->body("{$gerados} contas fixas geradas para o mês.")
                        ->success()
                        ->send();
                })
        ];
    }

    protected function getAccountsToGenerateData($month, $year): array
    {
        if (!$month || !$year) return [];
        $activeAccounts = RecurringExpense::where('is_active', true)->get();
        $items = [];

        foreach ($activeAccounts as $account) {
            $creationDate = $account->created_at;
            $requestDate = Carbon::createFromDate($year, $month, 1);

            if ($requestDate->startOfMonth()->lessThan($creationDate->copy()->startOfMonth())) {
                continue;
            }
            $alreadyExists = AccountPayable::where('fixed_account_id', $account->id)
                ->whereMonth('due_date', $month)
                ->whereYear('due_date', $year)
                ->exists();
            if (!$alreadyExists) {
                $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
                $diaVencimento = $account->due_day > $daysInMonth ? $daysInMonth : $account->due_day;

                $items[] = [
                    'fixed_account_id' => $account->id,
                    'name' => $account->name,
                    'due_date' => Carbon::createFromDate($year, $month, $diaVencimento)->format('d/m/Y'),
                    'amount' => $account->value_type === 'fixed' ? $account->default_amount : null,
                ];
            }
        }

        return $items;
    }
}
