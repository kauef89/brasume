<?php

namespace App\Filament\Resources\AccountPayables\Pages;

use Filament\Support\Enums\Width;

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
    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

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
            echo <<<'HTML'
            <style>
                .fi-ta-table, .fi-ta-search-field, .fi-ta-filters-dropdown, .fi-ta-actions, .fi-ta-empty-state { display: none !important; }
                .fi-ta-ctn.fi-ta-ctn-with-footer.fi-ta-ctn-with-header, .fi-ta-ctn { background: transparent !important; padding: 0 !important; box-shadow: unset !important; border: none !important; }
                .fi-ta-header-toolbar { padding: 0 !important; border: 0 !important; }
                .fi-ta { background: transparent !important; box-shadow: none !important; border: none !important; }
                .fi-wi { height: calc(100vh - 250px) !important; margin-top: 0 !important; }
                .fi-wi > div { height: 100% !important; }
                .fc { height: 100% !important; background: white; border-radius: 0.75rem; padding: 1rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); font-family: 'SF Compact Rounded', ui-rounded, sans-serif !important; }
                .dark .fc { background: #18181b; border: 1px solid #27272a; }
                .fc-col-header-cell-cushion { font-family: 'Stack Sans Text', system-ui, sans-serif !important; font-size: 0.875rem !important; }
                .fc-event-main, .fc-event-title, .fc-daygrid-day-number { font-size: 0.75rem !important; line-height: 1.2 !important; }

                /* Tooltip Flutuante com Padding Generoso */
                #fc-custom-tooltip {
                    position: fixed;
                    z-index: 999999;
                    pointer-events: none;
                    transition: opacity 0.15s ease-out, transform 0.15s ease-out;
                    opacity: 0;
                    transform: scale(0.97);
                    background: #ffffff;
                    color: #18181b;
                    border: 1px solid #e4e4e7;
                    border-radius: 0.875rem;
                    box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.18), 0 8px 16px -6px rgba(0, 0, 0, 0.08);
                    width: 325px;
                    padding: 1.125rem 1.25rem;
                }
                .dark #fc-custom-tooltip {
                    background: #18181b;
                    color: #f4f4f5;
                    border-color: #27272a;
                    box-shadow: 0 18px 40px -4px rgba(0, 0, 0, 0.65);
                }
                #fc-custom-tooltip.visible {
                    opacity: 1;
                    transform: scale(1);
                }
                .fc-tt-break {
                    word-break: break-word;
                    overflow-wrap: anywhere;
                }
            </style>

            <div id="fc-custom-tooltip"></div>

            <script>
            (function() {
                if (window.__fcTooltipEngineInitialized) return;
                window.__fcTooltipEngineInitialized = true;

                const tooltip = document.getElementById('fc-custom-tooltip');

                function getEventData(target) {
                    const eventEl = target.closest('.fc-event');
                    if (!eventEl || eventEl.classList.contains('event-daily-summary')) return null;

                    const href = eventEl.getAttribute('href') || eventEl.querySelector('a')?.getAttribute('href') || '';
                    const match = href.match(/contas-a-pagar\/(\d+)\/edit/);
                    if (!match) return null;

                    const fcEl = document.querySelector('.fc');
                    if (!fcEl) return null;

                    const alpineEl = fcEl.closest('[x-data]');
                    const alpineData = window.Alpine ? window.Alpine.$data(alpineEl) : null;
                    const calendar = alpineData?.calendar;
                    if (!calendar) return null;

                    const event = calendar.getEventById('payable-' + match[1]);
                    return event ? event.extendedProps : null;
                }

                // Trunca sequências numéricas longas (ex: Chave NF-e 44 dígitos ou boletos)
                function sanitizeFiscalDescription(text) {
                    if (!text) return '';

                    // 1. Abrevia chaves de acesso ou números com mais de 9 dígitos consecutivos: 4226...8921
                    let formatted = text.replace(/(\d{4})\d{5,}(\d{4})/g, '...');

                    // 2. Normaliza "NF: 0000123" para "NF 123"
                    formatted = formatted.replace(/\b(NF(?:-e)?[:\s]*)0+(\d+)\b/gi, '');

                    // 3. Limite de comprimento geral para manter o card proporcional
                    if (formatted.length > 58) {
                        formatted = formatted.substring(0, 55) + '...';
                    }

                    return formatted;
                }

                function renderTooltip(props) {
                    let badgeClass = 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30';
                    if (props.statusColor === 'success') {
                        badgeClass = 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30';
                    } else if (props.statusColor === 'danger') {
                        badgeClass = 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30';
                    }

                    const cleanDescription = sanitizeFiscalDescription(props.description);

                    let details = '';
                    if (cleanDescription) {
                        details += `
                            <div class="text-xs text-zinc-600 dark:text-zinc-300 flex items-start gap-1.5 fc-tt-break">
                                <span class="shrink-0 text-zinc-400 dark:text-zinc-500">📄</span>
                                <span class="font-medium">${cleanDescription}</span>
                            </div>
                        `;
                    }

                    let dateInfo = `Vence em <b>${props.dueDate}</b>`;
                    if (props.paymentDate) {
                        dateInfo += ` · <span class="text-emerald-600 dark:text-emerald-400 font-medium">Pago em ${props.paymentDate}</span>`;
                    }
                    details += `
                        <div class="text-xs text-zinc-600 dark:text-zinc-300 flex items-center gap-1.5">
                            <span class="shrink-0 text-zinc-400 dark:text-zinc-500">📅</span>
                            <span>${dateInfo}</span>
                        </div>
                    `;

                    if (props.category) {
                        details += `
                            <div class="text-xs text-zinc-600 dark:text-zinc-300 flex items-center gap-1.5 truncate">
                                <span class="shrink-0 text-zinc-400 dark:text-zinc-500">🏷️</span>
                                <span>${props.category}</span>
                            </div>
                        `;
                    }
                    if (props.bankAccount) {
                        details += `
                            <div class="text-xs text-zinc-600 dark:text-zinc-300 flex items-center gap-1.5 truncate">
                                <span class="shrink-0 text-zinc-400 dark:text-zinc-500">🏦</span>
                                <span>Conta: <b>${props.bankAccount}</b></span>
                            </div>
                        `;
                    }

                    tooltip.innerHTML = `
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/80 pb-2.5">
                                <div class="overflow-hidden">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">${props.creditorType}</span>
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 leading-snug truncate">${props.creditor}</h4>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full shrink-0 ${badgeClass}">${props.statusLabel}</span>
                            </div>

                            <div class="flex items-baseline justify-between py-0.5">
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Valor Total</span>
                                <span class="text-lg font-extrabold text-zinc-900 dark:text-white tracking-tight">${props.amount}</span>
                            </div>

                            <div class="space-y-2 pt-2 border-t border-zinc-100 dark:border-zinc-800/80">${details}</div>

                            <div class="pt-1.5 text-[10px] text-zinc-400 dark:text-zinc-500 text-right font-medium">
                                Clique para editar ↗
                            </div>
                        </div>
                    `;
                }

                function position(e) {
                    const pad = 16;
                    const rect = tooltip.getBoundingClientRect();
                    let x = e.clientX + pad;
                    let y = e.clientY + pad;

                    if (x + rect.width > window.innerWidth - pad) {
                        x = e.clientX - rect.width - pad;
                    }
                    if (y + rect.height > window.innerHeight - pad) {
                        y = e.clientY - rect.height - pad;
                    }

                    tooltip.style.left = Math.max(pad, x) + 'px';
                    tooltip.style.top = Math.max(pad, y) + 'px';
                }

                document.addEventListener('mouseover', function(e) {
                    const props = getEventData(e.target);
                    if (props) {
                        renderTooltip(props);
                        position(e);
                        tooltip.classList.add('visible');
                    }
                });

                document.addEventListener('mousemove', function(e) {
                    if (tooltip.classList.contains('visible')) {
                        position(e);
                    }
                });

                document.addEventListener('mouseout', function(e) {
                    if (e.target.closest('.fc-event')) {
                        tooltip.classList.remove('visible');
                    }
                });
            })();
            </script>
HTML;
        } else {
            echo "<style>.fi-wi { display: none !important; }</style>";
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
