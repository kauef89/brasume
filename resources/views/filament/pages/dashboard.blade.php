<x-filament-panels::page>
    @php
        $b = $briefing;
        $be = $b['break_even'];
        $sales = $b['sales'];
        $team = $b['team'];
        $treasury = $b['treasury'];
        $rec = $b['reconciliation'];
        $prod = $b['production'];
        $chk = $b['checklists'];
        $radar = $b['radar'];

        $fmt = fn ($val) => 'R$ ' . number_format((float) $val, 2, ',', '.');
        $todayStr = \Carbon\Carbon::today()->toDateString();
        $yesterdayStr = \Carbon\Carbon::yesterday()->toDateString();
        $tomorrowStr = \Carbon\Carbon::tomorrow()->toDateString();
    @endphp

    <div class="flex flex-col gap-6">

        {{-- 1. BARRA SUPERIOR DE CONTROLE DO DIA --}}
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 p-3 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400 ring-1 ring-primary-500/20">
                    <x-heroicon-o-calendar-days class="w-5 h-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-gray-950 dark:text-white">
                            {{ $b['date']['weekday'] }}, {{ $b['date']['formatted'] }}
                        </h2>
                        @if($b['date']['is_today'])
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400 ring-1 ring-inset ring-success-600/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-success-500 animate-pulse"></span>
                                Ao Vivo
                            </span>
                        @elseif($b['date']['is_future'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-400 ring-1 ring-inset ring-info-600/20">
                                Planejamento
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if($treasury['open_shift'])
                            Turno #{{ $treasury['open_shift']->id }} aberto desde {{ \Carbon\Carbon::parse($treasury['open_shift']->opened_at)->format('H:i') }}
                        @else
                            Nenhum turno de caixa aberto no momento
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-lg bg-gray-100 dark:bg-gray-950 p-1 ring-1 ring-gray-950/5 dark:ring-white/5">
                    <button wire:click="setDatePreset('yesterday')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedDate === $yesterdayStr ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">
                        Ontem
                    </button>
                    <button wire:click="setDatePreset('today')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedDate === $todayStr ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">
                        Hoje
                    </button>
                    <button wire:click="setDatePreset('tomorrow')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedDate === $tomorrowStr ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">
                        Amanhã
                    </button>
                </div>

                <input
                    type="date"
                    wire:model.live="selectedDate"
                    class="rounded-lg border-0 bg-gray-100 dark:bg-gray-950 py-1.5 px-3 text-xs font-semibold text-gray-900 dark:text-white ring-1 ring-inset ring-gray-950/10 dark:ring-white/10 focus:ring-2 focus:ring-primary-500"
                />

                <a href="/pos" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-primary-600 hover:bg-primary-500 text-white shadow-sm transition">
                    <x-heroicon-m-computer-desktop class="w-4 h-4" />
                    Abrir PDV
                </a>
            </div>
        </div>

        {{-- 2. PAINEL DE BREAK-EVEN DINÂMICO DO DIA (COM FREELANCERS ESCALADOS) --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 p-5 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                            Meta de Break-Even Operacional do Dia
                        </span>
                        @if($be['reached'])
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300">
                                <x-heroicon-m-check-badge class="w-3.5 h-3.5" />
                                Ponto de Equilíbrio Atingido!
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Custo fixo diário ({{ $fmt($be['daily_fixed']) }}) + Freelancers escalados hoje ({{ $fmt($be['freelancers_cost']) }}) ÷ Margem de Contribuição ({{ $be['cm_ratio_pct'] }}%{{ $be['is_cm_fallback'] ? ' est.' : '' }})
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <div>
                        <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Faturado no Dia</span>
                        <span class="text-xl font-black text-gray-950 dark:text-white">{{ $fmt($sales['gross']) }}</span>
                        <span class="block text-[11px] text-gray-500">{{ $sales['count'] }} pedido(s) • Ticket {{ $fmt($sales['avg_ticket']) }}</span>
                    </div>

                    <div class="h-8 w-px bg-gray-200 dark:bg-gray-800 hidden sm:block"></div>

                    <div>
                        <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Meta Break-Even</span>
                        <span class="text-xl font-black text-primary-600 dark:text-primary-400">{{ $fmt($be['target']) }}</span>
                        <span class="block text-[11px] {{ $be['reached'] ? 'text-success-600 dark:text-success-400 font-semibold' : 'text-warning-600 dark:text-warning-400' }}">
                            @if($be['reached'])
                                +{{ $fmt($be['surplus']) }} acima da meta
                            @else
                                Faltam {{ $fmt($be['remaining']) }}
                            @endif
                        </span>
                    </div>

                    <div class="h-8 w-px bg-gray-200 dark:bg-gray-800 hidden sm:block"></div>

                    <div>
                        <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Resultado Líquido Est.</span>
                        <span class="text-xl font-black {{ $be['estimated_net_profit'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ $be['estimated_net_profit'] >= 0 ? '+' : '' }}{{ $fmt($be['estimated_net_profit']) }}
                        </span>
                        @if($b['date']['is_today'] && $sales['projected'] > $sales['gross'])
                            <span class="block text-[11px] text-gray-500">Ritmo projeta {{ $fmt($sales['projected']) }}</span>
                        @else
                            <span class="block text-[11px] text-gray-500">Após CMV, taxas, fixo e diárias</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Barra de Progresso --}}
            <div class="w-full h-3 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden p-0.5 ring-1 ring-inset ring-gray-950/5 dark:ring-white/5">
                <div
                    class="h-full rounded-full transition-all duration-500 {{ $be['reached'] ? 'bg-success-500' : ($be['progress'] >= 70 ? 'bg-primary-500' : 'bg-warning-500') }}"
                    style="width: {{ $be['progress'] }}%"
                ></div>
            </div>
            <div class="flex justify-between items-center mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                <span>0%</span>
                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $be['progress'] }}% da meta de equilíbrio alcançada</span>
                <span>100% ({{ $fmt($be['target']) }})</span>
            </div>
        </div>

        {{-- 3. KPIs FINANCEIROS & CONCILIAÇÕES DO DIA (4 CARDS) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            {{-- Contas a Pagar Hoje --}}
            <a href="{{ \App\Filament\Resources\AccountPayables\AccountPayableResource::getUrl('index') }}" class="group rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm hover:ring-danger-500/40 transition flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Contas a Pagar (Dia)</span>
                    <div class="p-2 rounded-lg bg-danger-50 dark:bg-danger-500/10 text-danger-600 dark:text-danger-400">
                        <x-heroicon-m-arrow-up-right class="w-4 h-4" />
                    </div>
                </div>
                <div class="my-2">
                    <span class="text-2xl font-black text-gray-950 dark:text-white">{{ $fmt($treasury['payables_today_sum']) }}</span>
                    <span class="text-xs text-gray-500 ml-1">({{ $treasury['payables_today']->count() }} títulos)</span>
                </div>
                <div class="text-xs flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                    @if($treasury['overdue_payables_count'] > 0)
                        <span class="font-semibold text-danger-600 dark:text-danger-400">
                            {{ $treasury['overdue_payables_count'] }} vencida(s): {{ $fmt($treasury['overdue_payables_sum']) }}
                        </span>
                    @else
                        <span class="text-success-600 dark:text-success-400 font-medium">Nenhuma conta vencida</span>
                    @endif
                    <span class="text-gray-400 group-hover:text-primary-500 transition">Ver &rarr;</span>
                </div>
            </a>

            {{-- Contas a Receber Hoje --}}
            <a href="{{ \App\Filament\Resources\AccountReceivables\AccountReceivableResource::getUrl('index') }}" class="group rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm hover:ring-success-500/40 transition flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Contas a Receber (Dia)</span>
                    <div class="p-2 rounded-lg bg-success-50 dark:bg-success-500/10 text-success-600 dark:text-success-400">
                        <x-heroicon-m-arrow-down-left class="w-4 h-4" />
                    </div>
                </div>
                <div class="my-2">
                    <span class="text-2xl font-black text-gray-950 dark:text-white">{{ $fmt($treasury['receivables_today_sum']) }}</span>
                    <span class="text-xs text-gray-500 ml-1">({{ $treasury['receivables_today']->count() }} previstos)</span>
                </div>
                <div class="text-xs flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                    @if($treasury['overdue_rec_count'] > 0)
                        <span class="font-semibold text-warning-600 dark:text-warning-400">
                            {{ $treasury['overdue_rec_count'] }} em atraso: {{ $fmt($treasury['overdue_rec_sum']) }}
                        </span>
                    @else
                        <span class="text-gray-500">Recebíveis em dia</span>
                    @endif
                    <span class="text-gray-400 group-hover:text-primary-500 transition">Ver &rarr;</span>
                </div>
            </a>

            {{-- Conciliações Pendentes --}}
            <a href="{{ \App\Filament\Resources\BankStatements\BankStatementResource::getUrl('index') }}" class="group rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm hover:ring-warning-500/40 transition flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Conciliações Pendentes</span>
                    <div class="p-2 rounded-lg bg-warning-50 dark:bg-warning-500/10 text-warning-600 dark:text-warning-400">
                        <x-heroicon-m-arrows-right-left class="w-4 h-4" />
                    </div>
                </div>
                <div class="my-2 flex items-baseline gap-3">
                    <div>
                        <span class="text-2xl font-black text-gray-950 dark:text-white">{{ $rec['bank_pending_count'] }}</span>
                        <span class="text-xs text-gray-500">OFX</span>
                    </div>
                    <span class="text-gray-300 dark:text-gray-700">|</span>
                    <div>
                        <span class="text-2xl font-black text-gray-950 dark:text-white">{{ $rec['acquirer_pending_count'] }}</span>
                        <span class="text-xs text-gray-500">Maquininha</span>
                    </div>
                </div>
                <div class="text-xs flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                    <span class="text-gray-500 truncate">
                        Cartões órfãos: {{ $fmt($rec['acquirer_pending_amount']) }}
                    </span>
                    <span class="text-gray-400 group-hover:text-primary-500 transition">Conciliar &rarr;</span>
                </div>
            </a>

            {{-- Saldo Bancário Consolidado --}}
            <a href="{{ \App\Filament\Resources\BankAccounts\BankAccountResource::getUrl('index') }}" class="group rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm hover:ring-primary-500/40 transition flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Liquidez em Contas</span>
                    <div class="p-2 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400">
                        <x-heroicon-m-building-library class="w-4 h-4" />
                    </div>
                </div>
                <div class="my-2">
                    <span class="text-2xl font-black {{ $treasury['bank_balance'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $fmt($treasury['bank_balance']) }}
                    </span>
                </div>
                <div class="text-xs flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                    <span class="text-gray-500">Saldo líquido previsto hoje: {{ $fmt($treasury['receivables_today_sum'] - $treasury['payables_today_sum']) }}</span>
                    <span class="text-gray-400 group-hover:text-primary-500 transition">Contas &rarr;</span>
                </div>
            </a>
        </div>

        {{-- 4. GRADE OPERACIONAL: ESCALA DO DIA + PRODUÇÃO + CHECKLISTS --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- COLUNA 1: ESCALA DE TRABALHO DO DIA --}}
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-users class="w-5 h-5 text-primary-500" />
                        <div>
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">Escala do Dia</h3>
                            <p class="text-[11px] text-gray-500">
                                {{ $team['total_scheduled'] }} escalado(s) ({{ $team['clt_count'] }} fixo • {{ $team['freelancer_count'] }} freela)
                                @if($team['days_off_count'] > 0)
                                    • {{ $team['days_off_count'] }} folga(s)
                                @endif
                            </p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                        Freelas: {{ $fmt($team['freelancer_cost']) }}
                    </span>
                </div>

                <div class="p-4 flex-1 divide-y divide-gray-100 dark:divide-gray-800 max-h-96 overflow-y-auto">
                    @forelse($team['members'] as $member)
                        <div class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $member['name'] }}</span>
                                    @if($member['is_extra_shift'])
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-300">EXTRA</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                    {{ $member['role_label'] }} • <span class="font-medium">{{ $member['slot_name'] }}</span>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                @if($member['is_freelancer'])
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-bold bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                        {{ $fmt($member['daily_cost']) }}
                                    </span>
                                    <span class="block text-[10px] text-gray-400">{{ $member['employment'] }}</span>
                                @else
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-semibold bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-400">
                                        {{ $member['employment'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Nenhum colaborador escalado para esta data.</p>
                            <a href="{{ \App\Filament\Resources\Employees\EmployeeResource::getUrl('index') }}" class="inline-block mt-2 text-xs font-semibold text-primary-600 hover:underline">
                                Gerenciar equipe &rarr;
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- COLUNA 2: PRODUÇÕES AGENDADAS (COZINHA / WIP) --}}
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-fire class="w-5 h-5 text-warning-500" />
                        <div>
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">Produções Agendadas</h3>
                            <p class="text-[11px] text-gray-500">
                                {{ $prod['pending_count'] }} pendente(s) • {{ $prod['done_count'] }} concluída(s)
                            </p>
                        </div>
                    </div>
                    <a href="{{ \App\Filament\Resources\ProductionOrders\ProductionOrderResource::getUrl('index') }}" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                        Ver todas &rarr;
                    </a>
                </div>

                <div class="p-4 flex-1 divide-y divide-gray-100 dark:divide-gray-800 max-h-96 overflow-y-auto">
                    @forelse($prod['orders'] as $po)
                        @php
                            $poStatus = $po->status instanceof \App\Enums\ProductionOrderStatus
                                ? $po->status
                                : \App\Enums\ProductionOrderStatus::tryFrom((string) $po->status);
                            $isOverdue = $po->due_date && $po->due_date->lt(\Carbon\Carbon::today()) && !in_array($po->status, ['completed', 'cancelled']);
                        @endphp
                        <div class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white truncate">
                                        {{ $po->product?->name ?? "Lote #{$po->id}" }}
                                    </span>
                                    @if($isOverdue)
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300">ATRASADA</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-gray-500">
                                    Meta: <strong>{{ number_format((float) $po->planned_qty, 2, ',', '.') }} {{ strtolower($po->product?->unit_of_measure ?? 'un') }}</strong>
                                    @if($po->actual_qty > 0)
                                        • Realizado: {{ number_format((float) $po->actual_qty, 2, ',', '.') }}
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                {{ $poStatus?->getLabel() ?? $po->status }}
                            </span>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Nenhuma ordem de produção agendada para este dia.</p>
                            <a href="{{ \App\Filament\Resources\ProductionOrders\ProductionOrderResource::getUrl('create') }}" class="inline-block mt-2 text-xs font-semibold text-primary-600 hover:underline">
                                + Agendar lote de produção
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- COLUNA 3: TAREFAS & CHECKLISTS AGENDADOS --}}
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-success-500" />
                        <div>
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">Tarefas & Checklists</h3>
                            <p class="text-[11px] text-gray-500">
                                {{ $chk['done_tasks'] }}/{{ $chk['total_tasks'] }} concluídas ({{ $chk['progress_pct'] }}%)
                                @if($chk['issue_tasks'] > 0)
                                    • <span class="text-danger-600 font-semibold">{{ $chk['issue_tasks'] }} ocorrência(s)</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <a href="{{ \App\Filament\Resources\Checklists\ChecklistResource::getUrl('index') }}" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                        Rotinas &rarr;
                    </a>
                </div>

                <div class="p-4 flex-1 divide-y divide-gray-100 dark:divide-gray-800 max-h-96 overflow-y-auto">
                    @forelse($chk['items'] as $cl)
                        @php
                            $pct = $cl['total'] > 0 ? round(($cl['done'] / $cl['total']) * 100) : 0;
                            $triggerLabel = match ($cl['trigger_type']) {
                                'shift_start' => 'Abertura',
                                'shift_end'   => 'Fechamento',
                                'scheduled'   => $cl['trigger_time'] ? "Às {$cl['trigger_time']}" : 'Agendado',
                                default       => 'Rotina',
                            };
                        @endphp
                        <div class="py-2.5 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $cl['title'] }}</span>
                                <span class="text-[11px] font-semibold {{ $pct === 100 ? 'text-success-600 dark:text-success-400' : 'text-gray-500' }}">
                                    {{ $cl['done'] }}/{{ $cl['total'] }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-gray-500 mb-1.5">
                                <span>{{ $triggerLabel }}{{ $cl['target_role'] ? " • " . ucfirst($cl['target_role']) : '' }}</span>
                                @if($cl['issues'] > 0)
                                    <span class="text-danger-600 font-semibold">{{ $cl['issues'] }} alerta(s)</span>
                                @endif
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $pct === 100 ? 'bg-success-500' : 'bg-primary-500' }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Nenhuma rotina programada para este dia da semana.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 5. DETALHAMENTO DE TESOURARIA DO DIA + RADAR ENTERPRISE --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Vencimentos do Dia (Pagar e Receber lado a lado em 2 colunas) --}}
            <div class="lg:col-span-2 rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm p-4">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white mb-3 flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-primary-500" />
                    Agenda Financeira de {{ $b['date']['formatted'] }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Saídas Previstas --}}
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-950/60 p-3 ring-1 ring-gray-950/5 dark:ring-white/5">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-danger-600 dark:text-danger-400">Saídas / Pagamentos</span>
                            <span class="text-xs font-black text-danger-600 dark:text-danger-400">{{ $fmt($treasury['payables_today_sum']) }}</span>
                        </div>
                        <div class="divide-y divide-gray-200/70 dark:divide-gray-800 max-h-56 overflow-y-auto">
                            @forelse($treasury['payables_today'] as $pay)
                                <div class="py-2 first:pt-0 last:pb-0 flex items-center justify-between gap-2 text-xs">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-white truncate">{{ $pay->description }}</p>
                                        <p class="text-[11px] text-gray-500 truncate">{{ $pay->supplier?->name ?? ($pay->employee?->name ?? 'Fornecedor') }}</p>
                                    </div>
                                    <span class="font-bold text-gray-900 dark:text-white shrink-0">{{ $fmt($pay->amount) }}</span>
                                </div>
                            @empty
                                <p class="py-4 text-center text-xs text-gray-500">Nenhuma conta a pagar vence nesta data.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Entradas Previstas --}}
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-950/60 p-3 ring-1 ring-gray-950/5 dark:ring-white/5">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-success-600 dark:text-success-400">Entradas / Recebíveis</span>
                            <span class="text-xs font-black text-success-600 dark:text-success-400">{{ $fmt($treasury['receivables_today_sum']) }}</span>
                        </div>
                        <div class="divide-y divide-gray-200/70 dark:divide-gray-800 max-h-56 overflow-y-auto">
                            @forelse($treasury['receivables_today'] as $recItem)
                                <div class="py-2 first:pt-0 last:pb-0 flex items-center justify-between gap-2 text-xs">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-white truncate">{{ $recItem->description ?: "Recebível #{$recItem->id}" }}</p>
                                        <p class="text-[11px] text-gray-500 truncate">{{ $recItem->customer?->name ?? ($recItem->bankAccount?->name ?? 'Liquidação') }}</p>
                                    </div>
                                    <span class="font-bold text-success-600 dark:text-success-400 shrink-0">{{ $fmt($recItem->net_amount) }}</span>
                                </div>
                            @empty
                                <p class="py-4 text-center text-xs text-gray-500">Nenhum recebível agendado para esta data.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Radar Enterprise (Estoque, Fiscal & Compliance) --}}
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm p-4 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-950 dark:text-white mb-3 flex items-center gap-2">
                        <x-heroicon-o-shield-exclamation class="w-5 h-5 text-warning-500" />
                        Radar Operacional & Riscos
                    </h3>

                    <div class="space-y-2.5">
                        <a href="{{ \App\Filament\Resources\ShoppingListItems\ShoppingListItemResource::getUrl('index') }}" class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 dark:bg-gray-950/60 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Lista de Compras Pendente</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $radar['critical_shopping'] > 0 ? 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' : 'bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200' }}">
                                {{ $radar['pending_shopping'] }} {{ $radar['critical_shopping'] > 0 ? "({$radar['critical_shopping']} crítico)" : '' }}
                            </span>
                        </a>

                        <a href="{{ \App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource::getUrl('index') }}" class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 dark:bg-gray-950/60 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">NF-es Aguardando Importação</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $radar['pending_invoices'] > 0 ? 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300' : 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300' }}">
                                {{ $radar['pending_invoices'] }}
                            </span>
                        </a>

                        <a href="{{ \App\Filament\Resources\GoodsReceipts\GoodsReceiptResource::getUrl('index') }}" class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 dark:bg-gray-950/60 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Romaneios Aguardando NF-e</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                                {{ $radar['pending_receipts'] }}
                            </span>
                        </a>

                        <a href="{{ \App\Filament\Resources\CompliancePermits\CompliancePermitResource::getUrl('index') }}" class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 dark:bg-gray-950/60 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Alvarás & Licenças (30 dias)</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $radar['expiring_permits']->count() > 0 ? 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' : 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300' }}">
                                {{ $radar['expiring_permits']->count() > 0 ? $radar['expiring_permits']->count() . ' alerta(s)' : 'Em dia' }}
                            </span>
                        </a>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs">
                    <a href="{{ \App\Filament\Pages\FinancialHealthDashboard::getUrl() }}" class="font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                        Abrir DRE & Saúde Financeira &rarr;
                    </a>
                    @if(class_exists(\App\Filament\Pages\BillingDashboard::class) && \Illuminate\Support\Facades\Route::has(\App\Filament\Pages\BillingDashboard::getRouteName()))
                        <a href="{{ \App\Filament\Pages\BillingDashboard::getUrl() }}" class="font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white">
                            Faturamento &rarr;
                        </a>
                    @elseif(class_exists(\App\Filament\Pages\CashFlowDashboard::class) && \Illuminate\Support\Facades\Route::has(\App\Filament\Pages\CashFlowDashboard::getRouteName()))
                        <a href="{{ \App\Filament\Pages\CashFlowDashboard::getUrl() }}" class="font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white">
                            Fluxo de Caixa &rarr;
                        </a>
                    @endif
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
