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

                    if (props.settlementMode) {
                        details += `
                            <div class="text-xs text-zinc-600 dark:text-zinc-300 flex items-center gap-1.5 truncate">
                                <span class="shrink-0 text-zinc-400 dark:text-zinc-500">🏦</span>
                                <span>Liquidação: <b>${props.settlementMode}</b></span>
                            </div>
                        `;
                    }
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

            Actions\ActionGroup::make([
                Actions\Action::make('malote_pix_cnab240')
                ->label('Malote PIX (CNAB 240)')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->modalHeading('Gerar Malote Diário PIX — Sicredi CNAB 240')
                ->modalDescription('Consolida pagamentos PIX pendentes de Fornecedores e Colaboradores (serviços extras/diárias fora da folha), bloqueando títulos DDA/Débito Automático.')
                ->modalWidth('5xl')
                ->modalSubmitActionLabel('Gerar e Baixar Arquivo .REM')
                ->steps([
                    \Filament\Schemas\Components\Wizard\Step::make('config')
                        ->label('1. Data & Seleção de Títulos')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([
                            \Filament\Schemas\Components\Grid::make(3)->schema([
                                \Filament\Forms\Components\DatePicker::make('payment_date')
                                    ->label('Data de Pagamento (Agendamento)')
                                    ->default(now())
                                    ->required(),

                                \Filament\Forms\Components\DatePicker::make('due_until')
                                    ->label('Vencimentos até')
                                    ->default(now())
                                    ->live()
                                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                        $ids = AccountPayable::where('status', 'pending')
                                            ->whereNull('payment_batch_id')
                                            ->whereIn('settlement_mode', [
                                                \App\Enums\PayableSettlementMode::PIX_REMITTANCE->value,
                                                \App\Enums\PayableSettlementMode::MANUAL->value,
                                            ])
                                            ->when($state, fn ($q) => $q->whereDate('due_date', '<=', $state))
                                            ->pluck('id')
                                            ->map(fn ($id) => (string) $id)
                                            ->toArray();
                                        $set('payable_ids', $ids);
                                    })
                                    ->required(),

                                \Filament\Forms\Components\Toggle::make('include_manual')
                                    ->label('Incluir títulos marcados como "Manual"?')
                                    ->default(true)
                                    ->live()
                                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                                        $modes = [\App\Enums\PayableSettlementMode::PIX_REMITTANCE->value];
                                        if ($state) {
                                            $modes[] = \App\Enums\PayableSettlementMode::MANUAL->value;
                                        }
                                        $dueUntil = $get('due_until');
                                        $ids = AccountPayable::where('status', 'pending')
                                            ->whereNull('payment_batch_id')
                                            ->whereIn('settlement_mode', $modes)
                                            ->when($dueUntil, fn ($q) => $q->whereDate('due_date', '<=', $dueUntil))
                                            ->pluck('id')
                                            ->map(fn ($id) => (string) $id)
                                            ->toArray();
                                        $set('payable_ids', $ids);
                                    })
                                    ->inline(false),
                            ]),

                            \Filament\Forms\Components\CheckboxList::make('payable_ids')
                                ->label('Títulos Elegíveis (Fornecedores & Colaboradores Fora da Folha)')
                                ->helperText('Títulos marcados como DDA / Débito Automático são ocultados automaticamente para evitar pagamento duplo.')
                                ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                    $dueUntil = $get('due_until') ?? now()->toDateString();
                                    $modes = [\App\Enums\PayableSettlementMode::PIX_REMITTANCE->value];
                                    if ($get('include_manual') !== false) {
                                        $modes[] = \App\Enums\PayableSettlementMode::MANUAL->value;
                                    }

                                    return AccountPayable::with(['supplier', 'employee'])
                                        ->where('status', 'pending')
                                        ->whereNull('payment_batch_id')
                                        ->whereIn('settlement_mode', $modes)
                                        ->whereDate('due_date', '<=', $dueUntil)
                                        ->orderBy('due_date')
                                        ->get()
                                        ->mapWithKeys(function (AccountPayable $p) {
                                            $resolved = \App\Services\BankDictionaries\SicrediCnab240PixDictionary::resolvePayee($p);
                                            $due = $p->due_date?->format('d/m/Y') ?? '-';
                                            $val = number_format((float) $p->amount, 2, ',', '.');
                                            $pixInfo = $resolved['valid']
                                                ? "✅ PIX ({$resolved['pix']['type_label']}): {$resolved['pix']['key_value']}"
                                                : "⚠️ {$resolved['error_reason']}";
                                            $label = "[{$due}] {$resolved['beneficiary_name']} ({$resolved['beneficiary_type']}) — R$ {$val} | {$p->description} [{$pixInfo}]";
                                            return [(string) $p->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->default(function () {
                                    return AccountPayable::where('status', 'pending')
                                        ->whereNull('payment_batch_id')
                                        ->whereIn('settlement_mode', [
                                            \App\Enums\PayableSettlementMode::PIX_REMITTANCE->value,
                                            \App\Enums\PayableSettlementMode::MANUAL->value,
                                        ])
                                        ->whereDate('due_date', '<=', now()->toDateString())
                                        ->pluck('id')
                                        ->map(fn ($id) => (string) $id)
                                        ->toArray();
                                })
                                ->columns(1),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('preview')
                        ->label('2. Auditoria & Dry Run CNAB 240')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            \Filament\Forms\Components\Placeholder::make('pix_cnab_preview')
                                ->hiddenLabel()
                                ->content(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                    $selectedIds = array_map('intval', (array) ($get('payable_ids') ?? []));
                                    $dueUntil = $get('due_until') ?? now()->toDateString();

                                    $selectedPayables = AccountPayable::with(['supplier', 'employee'])
                                        ->whereIn('id', $selectedIds)
                                        ->get();

                                    $preview = \App\Services\Financial\SicrediCnab240Service::previewPixBatch($selectedPayables);

                                    // Também lista títulos DDA/Débito Automático do mesmo período que foram protegidos automaticamente
                                    $ddaProtected = AccountPayable::with(['supplier', 'employee'])
                                        ->where('status', 'pending')
                                        ->whereNull('payment_batch_id')
                                        ->whereIn('settlement_mode', [
                                            \App\Enums\PayableSettlementMode::DDA_BOLETO->value,
                                            \App\Enums\PayableSettlementMode::AUTO_DEBIT->value,
                                        ])
                                        ->whereDate('due_date', '<=', $dueUntil)
                                        ->get();

                                    $readyCount = count($preview['ready']);
                                    $missingCount = count($preview['missing_pix']);
                                    $ddaCount = $ddaProtected->count();
                                    $totalFmt = number_format($preview['total_amount'], 2, ',', '.');

                                    $html = "<div class='space-y-4'>";

                                    $html .= "<div class='grid grid-cols-1 md:grid-cols-4 gap-3'>";
                                    $html .= "<div class='p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/40'><div class='text-xs text-emerald-700 dark:text-emerald-400'>Aptos no Malote PIX</div><div class='text-xl font-bold text-emerald-800 dark:text-emerald-300'>{$readyCount}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50/70 dark:bg-primary-950/40'><div class='text-xs text-primary-700 dark:text-primary-400'>Valor Total Remessa</div><div class='text-xl font-bold text-primary-800 dark:text-primary-300'>R$ {$totalFmt}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-950/40'><div class='text-xs text-sky-700 dark:text-sky-400'>Protegidos (DDA / Déb. Auto)</div><div class='text-xl font-bold text-sky-800 dark:text-sky-300'>{$ddaCount}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-rose-200 dark:border-rose-800 bg-rose-50/70 dark:bg-rose-950/40'><div class='text-xs text-rose-700 dark:text-rose-400'>Sem Chave PIX Válida</div><div class='text-xl font-bold text-rose-800 dark:text-rose-300'>{$missingCount}</div></div>";
                                    $html .= "</div>";

                                    if ($readyCount > 0) {
                                        $html .= "<div class='rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden'>";
                                        $html .= "<div class='px-3 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200'>Pagamentos Incluídos no Arquivo .REM (Serviço 20 / Forma 45)</div>";
                                        $html .= "<table class='w-full text-xs text-left divide-y divide-gray-200 dark:divide-gray-700'>";
                                        $html .= "<thead class='bg-gray-50/50 dark:bg-gray-800/50 text-gray-500'><tr><th class='px-3 py-2'>Favorecido</th><th class='px-3 py-2'>Tipo Chave CNAB</th><th class='px-3 py-2'>Chave PIX</th><th class='px-3 py-2'>Vencimento</th><th class='px-3 py-2 text-right'>Valor</th></tr></thead><tbody class='divide-y divide-gray-200 dark:divide-gray-700'>";
                                        foreach ($preview['ready'] as $r) {
                                            $name = e($r['beneficiary_name']);
                                            $bType = e($r['beneficiary_type']);
                                            $kType = e($r['pix']['type_label']);
                                            $kVal = e($r['pix']['key_value']);
                                            $due = $r['due_date'] ? Carbon::parse($r['due_date'])->format('d/m/Y') : '-';
                                            $val = number_format($r['amount'], 2, ',', '.');
                                            $html .= "<tr>
                                                <td class='px-3 py-2'><div class='font-semibold text-gray-900 dark:text-gray-100'>{$name}</div><div class='text-[10px] text-gray-500'>{$bType} • AP #{$r['id']}</div></td>
                                                <td class='px-3 py-2 text-primary-600 dark:text-primary-400 font-medium'>{$kType}</td>
                                                <td class='px-3 py-2 font-mono text-gray-700 dark:text-gray-300'>{$kVal}</td>
                                                <td class='px-3 py-2'>{$due}</td>
                                                <td class='px-3 py-2 text-right font-bold text-emerald-600 dark:text-emerald-400'>R$ {$val}</td>
                                            </tr>";
                                        }
                                        $html .= "</tbody></table></div>";
                                    }

                                    if ($missingCount > 0) {
                                        $html .= "<div class='p-3 rounded-xl border border-rose-300 dark:border-rose-700 bg-rose-50/70 dark:bg-rose-950/30 space-y-1'>";
                                        $html .= "<div class='text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider'>⚠️ Ignorados por Falta/Erro de Chave PIX (Cadastre a chave antes de incluir)</div><ul class='text-xs text-rose-700 dark:text-rose-300 list-disc pl-5 space-y-1'>";
                                        foreach ($preview['missing_pix'] as $m) {
                                            $name = e($m['beneficiary_name']);
                                            $reason = e($m['error_reason']);
                                            $val = number_format($m['amount'], 2, ',', '.');
                                            $html .= "<li><b>{$name}</b> (AP #{$m['id']} — R$ {$val}): {$reason}</li>";
                                        }
                                        $html .= "</ul></div>";
                                    }

                                    if ($ddaCount > 0) {
                                        $html .= "<div class='p-3 rounded-xl border border-sky-300 dark:border-sky-700 bg-sky-50/70 dark:bg-sky-950/30 space-y-1'>";
                                        $html .= "<div class='text-xs font-bold text-sky-800 dark:text-sky-300 uppercase tracking-wider'>🏦 Títulos DDA / Débito Automático Protegidos (Não enviados no Malote PIX)</div><ul class='text-xs text-sky-700 dark:text-sky-300 list-disc pl-5 space-y-1'>";
                                        foreach ($ddaProtected as $dda) {
                                            $name = e($dda->supplier?->name ?? $dda->description);
                                            $val = number_format((float) $dda->amount, 2, ',', '.');
                                            $mLabel = e($dda->settlement_mode?->getLabel() ?? 'DDA');
                                            $html .= "<li><b>{$name}</b> — R$ {$val} ({$mLabel})</li>";
                                        }
                                        $html .= "</ul></div>";
                                    }

                                    $html .= "</div>";
                                    return new \Illuminate\Support\HtmlString($html);
                                }),
                        ]),
                ])
                ->action(function (array $data) {
                    $sicredi = \App\Models\BankAccount::bankInstitutions()->where('bank_code', '748')->first();

                    if (!$sicredi || empty($sicredi->account_number)) {
                        Notification::make()
                            ->title('Conta Sicredi PJ não configurada')
                            ->body('Configure a agência, conta e convênio Sicredi em Bancos antes de gerar o malote.')
                            ->danger()
                            ->send();
                        return null;
                    }

                    $selectedIds = array_map('intval', (array) ($data['payable_ids'] ?? []));
                    if (empty($selectedIds)) {
                        Notification::make()
                            ->title('Nenhum título selecionado')
                            ->warning()
                            ->send();
                        return null;
                    }

                    $payables = AccountPayable::with(['supplier', 'employee'])
                        ->whereIn('id', $selectedIds)
                        ->get();

                    try {
                        $paymentDate = Carbon::parse($data['payment_date'] ?? now());
                        $service = new \App\Services\Financial\SicrediCnab240Service($sicredi, $paymentDate);
                        $result = $service->generatePixBatch($payables, 'MALOTE PIX DIARIO');

                        Notification::make()
                            ->title('Malote PIX CNAB 240 Gerado!')
                            ->body("Lote #{$result['batch_id']} criado com R$ " . number_format($result['total_amount'], 2, ',', '.') . " em {$result['count']} pagamento(s).")
                            ->success()
                            ->send();

                        return response()->streamDownload(function () use ($result) {
                            echo $result['content'];
                        }, $result['filename'], [
                            'Content-Type' => 'text/plain; charset=UTF-8',
                        ]);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Falha ao gerar Malote PIX')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return null;
                    }
                }),

            Actions\Action::make('importar_lote_pdf')
                ->label('Importar Lote (PDFs)')
                ->icon('heroicon-o-document-arrow-up')
                ->color('primary')
                ->modalHeading('Importar Boletos e Guias (com Dry Run)')
                ->modalDescription('Envie os PDFs para análise prévia. Nenhum registro será gravado antes da sua confirmação.')
                ->modalWidth('5xl')
                ->modalSubmitActionLabel('Confirmar e Gravar Lote')
                ->steps([
                    \Filament\Schemas\Components\Wizard\Step::make('upload')
                        ->label('1. Selecionar PDFs')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            \Filament\Forms\Components\FileUpload::make('pdfs')
                                ->label('Documentos PDF (Guias, Tributos, Honorários e Boletos)')
                                ->acceptedFileTypes(['application/pdf'])
                                ->multiple()
                                ->disk('public')
                                ->directory('payables_documents')
                                ->required()
                                ->helperText('Você pode selecionar vários arquivos simultaneamente. O sistema analisará todos antes de gravar.'),
                        ]),

                    \Filament\Schemas\Components\Wizard\Step::make('preview')
                        ->label('2. Pré-visualização (Dry Run)')
                        ->icon('heroicon-o-eye')
                        ->schema([
                            \Filament\Forms\Components\Placeholder::make('dry_run_report')
                                ->hiddenLabel()
                                ->content(function (\Filament\Schemas\Components\Utilities\Get $get, \App\Services\PayableDictionaries\PayableDocumentScannerService $scanner) {
                                    $files = array_values((array) ($get('pdfs') ?? []));
                                    if (empty($files)) {
                                        return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Nenhum arquivo enviado para análise.</p>');
                                    }

                                    $report = $scanner->dryRunBatch($files, 'public');
                                    $readyCount = count($report['ready']);
                                    $dupCount = count($report['duplicates']);
                                    $errCount = count($report['errors']);
                                    $totalFmt = number_format($report['total_amount'], 2, ',', '.');

                                    $html = "<div class='space-y-4'>";

                                    // Cards de resumo
                                    $html .= "<div class='grid grid-cols-1 md:grid-cols-4 gap-3'>";
                                    $html .= "<div class='p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/40'><div class='text-xs font-medium text-emerald-700 dark:text-emerald-400'>Prontos p/ Gravar</div><div class='text-xl font-bold text-emerald-800 dark:text-emerald-300'>{$readyCount}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50/70 dark:bg-primary-950/40'><div class='text-xs font-medium text-primary-700 dark:text-primary-400'>Valor Total do Lote</div><div class='text-xl font-bold text-primary-800 dark:text-primary-300'>R$ {$totalFmt}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50/70 dark:bg-amber-950/40'><div class='text-xs font-medium text-amber-700 dark:text-amber-400'>Duplicados (Ignorados)</div><div class='text-xl font-bold text-amber-800 dark:text-amber-300'>{$dupCount}</div></div>";
                                    $html .= "<div class='p-3 rounded-xl border border-rose-200 dark:border-rose-800 bg-rose-50/70 dark:bg-rose-950/40'><div class='text-xs font-medium text-rose-700 dark:text-rose-400'>Falhas / Não Lidos</div><div class='text-xl font-bold text-rose-800 dark:text-rose-300'>{$errCount}</div></div>";
                                    $html .= "</div>";

                                    // Tabela de Prontos
                                    if ($readyCount > 0) {
                                        $html .= "<div class='rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden'>";
                                        $html .= "<div class='px-3 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200'>Documentos Identificados (Serão Gravados)</div>";
                                        $html .= "<table class='w-full text-xs text-left divide-y divide-gray-200 dark:divide-gray-700'>";
                                        $html .= "<thead class='bg-gray-50/50 dark:bg-gray-800/50 text-gray-500'><tr><th class='px-3 py-2'>Arquivo / Tipo</th><th class='px-3 py-2'>Fornecedor</th><th class='px-3 py-2'>Descrição / DRE</th><th class='px-3 py-2'>Vencimento</th><th class='px-3 py-2 text-right'>Valor</th></tr></thead><tbody class='divide-y divide-gray-200 dark:divide-gray-700'>";

                                        foreach ($report['ready'] as $item) {
                                            $fName = e($item['filename']);
                                            $tLabel = e($item['type_label']);
                                            $supName = e($item['supplier_resolved_name']);
                                            $supBadge = $item['supplier_is_new']
                                                ? "<span class='ml-1 px-1.5 py-0.5 text-[10px] rounded bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300'>Novo CNPJ</span>"
                                                : '';
                                            $desc = e($item['description']);
                                            $dre = e("{$item['category_label']} › {$item['subcategory_label']}");
                                            $due = \Carbon\Carbon::parse($item['due_date'])->format('d/m/Y');
                                            $val = number_format($item['amount'], 2, ',', '.');

                                            $html .= "<tr>
                                                <td class='px-3 py-2'><div class='font-semibold text-gray-900 dark:text-gray-100'>{$fName}</div><div class='text-[11px] text-primary-600 dark:text-primary-400'>{$tLabel}</div></td>
                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>{$supName}{$supBadge}</td>
                                                <td class='px-3 py-2'><div class='text-gray-900 dark:text-gray-100'>{$desc}</div><div class='text-[10px] text-gray-500'>{$dre}</div></td>
                                                <td class='px-3 py-2 font-medium text-gray-800 dark:text-gray-200'>{$due}</td>
                                                <td class='px-3 py-2 text-right font-bold text-emerald-600 dark:text-emerald-400'>R$ {$val}</td>
                                            </tr>";
                                        }
                                        $html .= "</tbody></table></div>";
                                    }

                                    // Duplicados
                                    if ($dupCount > 0) {
                                        $html .= "<div class='p-3 rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50/60 dark:bg-amber-950/30 space-y-1'>";
                                        $html .= "<div class='text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider'>Já Cadastrados (Serão Ignorados)</div><ul class='text-xs text-amber-700 dark:text-amber-400 list-disc pl-5 space-y-1'>";
                                        foreach ($report['duplicates'] as $dup) {
                                            $fName = e($dup['filename']);
                                            $due = \Carbon\Carbon::parse($dup['due_date'])->format('d/m/Y');
                                            $val = number_format($dup['amount'], 2, ',', '.');
                                            $html .= "<li><b>{$fName}</b> — R$ {$val} (Venc. {$due}) já existe no título #{$dup['existing_id']}.</li>";
                                        }
                                        $html .= "</ul></div>";
                                    }

                                    // Erros / Não identificados
                                    if ($errCount > 0) {
                                        $html .= "<div class='p-3 rounded-xl border border-rose-300 dark:border-rose-700 bg-rose-50/70 dark:bg-rose-950/30 space-y-2'>";
                                        $html .= "<div class='text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider'>Arquivos Não Identificados / Com Falha de Leitura</div>";
                                        foreach ($report['errors'] as $err) {
                                            $fName = e($err['filename']);
                                            $reason = e($err['reason']);
                                            $preview = e($err['text_preview'] ?: '(Nenhum texto extraído do PDF)');
                                            $html .= "<div class='p-2.5 rounded-lg bg-white/80 dark:bg-gray-900/70 border border-rose-200 dark:border-rose-800 text-xs space-y-1'>
                                                <div class='font-bold text-rose-700 dark:text-rose-400'>❌ {$fName}: <span class='font-medium'>{$reason}</span></div>
                                                <div class='text-[11px] text-gray-500 font-mono break-all'>Trecho lido: {$preview}</div>
                                            </div>";
                                        }
                                        $html .= "</div>";
                                    }

                                    $html .= "</div>";
                                    return new \Illuminate\Support\HtmlString($html);
                                }),
                        ]),
                ])
                ->action(function (array $data, \App\Services\PayableDictionaries\PayableDocumentScannerService $scanner) {
                    $files = array_values((array) ($data['pdfs'] ?? []));
                    $report = $scanner->dryRunBatch($files, 'public');
                    $success = 0;
                    $importedLabels = [];

                    foreach ($report['ready'] as $item) {
                        $supplierId = $item['supplier_id'];
                        if (!$supplierId && !empty($item['supplier_cnpj'])) {
                            $cleanCnpj = preg_replace('/[^0-9]/', '', $item['supplier_cnpj']);
                            $supplier = \App\Models\Supplier::firstOrCreate(
                                ['document' => $cleanCnpj],
                                [
                                    'name'                   => $item['supplier_resolved_name'] ?? 'Fornecedor Importado',
                                    'is_active'              => true,
                                    'is_trusted'             => true,
                                    'accounting_category'    => $item['category'] ?? 'administrative',
                                    'accounting_subcategory' => $item['subcategory'] ?? 'general',
                                ]
                            );
                            $supplierId = $supplier->id;
                        }

                        \App\Models\AccountPayable::create([
                            'supplier_id'            => $supplierId,
                            'description'            => $item['description'],
                            'amount'                 => $item['amount'],
                            'due_date'               => $item['due_date'],
                            'status'                 => \App\Enums\PaymentStatus::PENDING->value,
                            'accounting_category'    => $item['category'],
                            'accounting_subcategory' => $item['subcategory'],
                            'document_path'          => $item['stored_path'],
                            'observations'           => $item['observations'],
                        ]);

                        $success++;
                        $importedLabels[] = $item['type_label'];
                    }

                    // Remove do disco arquivos que foram ignorados por duplicidade ou erro para não deixar lixo
                    foreach (array_merge($report['duplicates'], $report['errors']) as $unused) {
                        if (!empty($unused['stored_path']) && \Illuminate\Support\Facades\Storage::disk('public')->exists($unused['stored_path'])) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($unused['stored_path']);
                        }
                    }

                    if ($success > 0) {
                        \Filament\Notifications\Notification::make()
                            ->title("Lote Importado ({$success} título(s))")
                            ->body('Tipos gravados: ' . implode(', ', array_unique($importedLabels)))
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Nenhum título novo foi gravado')
                            ->body('Todos os arquivos eram duplicados ou não puderam ser identificados.')
                            ->warning()
                            ->send();
                    }
                }),

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
                                'settlement_mode' => $recurringExpense->settlement_mode?->value ?? ($recurringExpense->is_auto_debit ? \App\Enums\PayableSettlementMode::AUTO_DEBIT->value : \App\Enums\PayableSettlementMode::MANUAL->value),
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
            ])
                ->label('Ferramentas')
                ->icon('heroicon-m-wrench-screwdriver')
                ->button()
                ->color('gray'),
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
