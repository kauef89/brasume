<x-filament-panels::page>
    <div class="flex flex-col gap-6">

        {{-- BARRA DE CONTROLE SUPERIOR --}}
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 p-2 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            
            {{-- SELETOR DE ABAS --}}
            <div class="flex rounded-lg bg-gray-100 dark:bg-gray-950 p-1 ring-1 ring-gray-950/5 dark:ring-white/5">
                <button wire:click="setActiveView('dre')" class="flex-1 lg:flex-none px-4 py-2 text-xs font-semibold rounded-md transition flex items-center justify-center gap-2 {{ $activeView === 'dre' ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">
                    <x-heroicon-m-chart-bar class="w-4 h-4 text-primary-500" />
                    DRE & Break-Even
                </button>
                <button wire:click="setActiveView('cmv')" class="flex-1 lg:flex-none px-4 py-2 text-xs font-semibold rounded-md transition flex items-center justify-center gap-2 {{ $activeView === 'cmv' ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">
                    <x-heroicon-m-scale class="w-4 h-4 text-warning-500" />
                    CMV & Desperdício
                </button>
                <button wire:click="setActiveView('projection')" class="flex-1 lg:flex-none px-4 py-2 text-xs font-semibold rounded-md transition flex items-center justify-center gap-2 {{ $activeView === 'projection' ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">
                    <x-heroicon-m-arrow-trending-up class="w-4 h-4 text-success-500" />
                    Fluxo & Liquidez
                </button>
            </div>

            @if($activeView !== 'projection')
                {{-- FILTROS DE PERÍODO E REGIME --}}
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex rounded-lg bg-gray-100 dark:bg-gray-950 p-1 ring-1 ring-gray-950/5 dark:ring-white/5">
                        <button wire:click="setPeriod('this_month')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedPeriod === 'this_month' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">Mês Atual</button>
                        <button wire:click="setPeriod('last_month')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedPeriod === 'last_month' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">Mês Anterior</button>
                        <button wire:click="setPeriod('quarter')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedPeriod === 'quarter' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">Trimestre</button>
                        <button wire:click="setPeriod('this_year')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedPeriod === 'this_year' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">Ano {{ date('Y') }}</button>
                    </div>

                    <div class="flex rounded-lg bg-gray-100 dark:bg-gray-950 p-1 ring-1 ring-gray-950/5 dark:ring-white/5">
                        <button wire:click="setRegime('competencia')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedRegime === 'competencia' ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">Competência</button>
                        <button wire:click="setRegime('caixa')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedRegime === 'caixa' ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">Caixa</button>
                    </div>
                </div>
            @else
                {{-- HORIZONTE DA PROJEÇÃO --}}
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Janela:</span>
                    <div class="flex rounded-lg bg-gray-100 dark:bg-gray-950 p-1 ring-1 ring-gray-950/5 dark:ring-white/5">
                        <button wire:click="setProjectionDays(30)" class="px-4 py-1.5 text-xs font-semibold rounded-md transition {{ $projectionDays === 30 ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">D+30 Dias</button>
                        <button wire:click="setProjectionDays(60)" class="px-4 py-1.5 text-xs font-semibold rounded-md transition {{ $projectionDays === 60 ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">D+60 Dias</button>
                    </div>
                </div>
            @endif
        </div>

        @if($activeView === 'dre')
            {{-- VIEW 1: DRE & BREAK-EVEN --}}

            {{-- LINHA ÚNICA DE 5 CARDS COM ÍCONES --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                {{-- Card 1 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Faturamento Bruto</span>
                        <div class="p-1.5 rounded-lg bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 ring-1 ring-gray-950/5 dark:ring-white/10 shrink-0">
                            <x-heroicon-m-banknotes class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                            R$ {{ number_format($report['revenue']['gross'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Líquido: R$ {{ number_format($report['revenue']['net'], 2, ',', '.') }}
                        </div>
                    </div>
                </div>

                {{-- Card 2 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Margem Contrib.</span>
                        <div class="p-1.5 rounded-lg bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 ring-1 ring-primary-500/20 shrink-0">
                            <x-heroicon-m-receipt-percent class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                            R$ {{ number_format($report['contribution_margin']['amount'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs font-semibold text-primary-600 dark:text-primary-400 truncate">
                            {{ $report['contribution_margin']['ratio'] }}% da Rec. Líquida
                        </div>
                    </div>
                </div>

                {{-- Card 3 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">EBITDA Operacional</span>
                        <div class="p-1.5 rounded-lg bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 ring-1 ring-gray-950/5 dark:ring-white/10 shrink-0">
                            <x-heroicon-m-chart-pie class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight {{ $report['operating_profit']['amount'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                            R$ {{ number_format($report['operating_profit']['amount'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Margem: {{ $report['operating_profit']['margin'] }}%
                        </div>
                    </div>
                </div>

                {{-- Card 4 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Meta Break-Even</span>
                        <div class="p-1.5 rounded-lg bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 ring-1 ring-gray-950/5 dark:ring-white/10 shrink-0">
                            <x-heroicon-m-flag class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                            R$ {{ number_format($report['break_even']['target'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Diária: R$ {{ number_format($report['break_even']['daily'], 2, ',', '.') }}
                        </div>
                    </div>
                </div>

                {{-- Card 5 --}}
                <div class="col-span-2 lg:col-span-1 flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Lucro Retido Real</span>
                        <div class="p-1.5 rounded-lg {{ $report['retained_profit'] >= 0 ? 'bg-success-50 dark:bg-success-950/50 text-success-600 dark:text-success-400 ring-1 ring-success-500/20' : 'bg-danger-50 dark:bg-danger-950/50 text-danger-600 dark:text-danger-400 ring-1 ring-danger-500/20' }} shrink-0">
                            <x-heroicon-m-wallet class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight {{ $report['retained_profit'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            R$ {{ number_format($report['retained_profit'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Após sócios e CAPEX
                        </div>
                    </div>
                </div>
            </div>

            {{-- RÉGUA DO BREAK-EVEN --}}
            <div class="flex flex-col rounded-xl bg-white dark:bg-gray-900 p-5 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">Progresso do Ponto de Equilíbrio</span>
                        @if($report['break_even']['reached'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-success-50 dark:bg-success-950/50 text-success-700 dark:text-success-400 ring-1 ring-inset ring-success-600/20">Atingido! Operando no Lucro</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-warning-50 dark:bg-warning-950/50 text-warning-700 dark:text-warning-400 ring-1 ring-inset ring-warning-600/20">Faltam R$ {{ number_format(abs($report['break_even']['difference']), 2, ',', '.') }}</span>
                        @endif
                    </div>
                    <span class="text-sm font-bold font-mono text-gray-950 dark:text-white">{{ $report['break_even']['progress'] }}%</span>
                </div>
                <div class="w-full h-2 rounded-full bg-gray-100 dark:bg-gray-950 overflow-hidden ring-1 ring-gray-950/5 dark:ring-white/5">
                    <div class="h-full rounded-full transition-all duration-500 {{ $report['break_even']['reached'] ? 'bg-success-500' : 'bg-primary-500' }}" style="width: {{ $report['break_even']['progress'] }}%"></div>
                </div>
            </div>

            {{-- TABELA DO DRE --}}
            <div class="flex flex-col rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 dark:border-white/10 px-6 py-4 bg-gray-50/50 dark:bg-gray-950/40 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white uppercase tracking-wider">Demonstrativo de Resultado do Exercício (DRE)</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Visão contábil e estrutural do faturamento, deduções e margens.</p>
                    </div>
                    <span class="text-xs font-mono text-gray-400 dark:text-gray-500 font-medium">{{ $report['period']['start'] }} &mdash; {{ $report['period']['end'] }}</span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-white/5 text-sm">
                    {{-- 1. Receita Bruta --}}
                    <div class="flex items-center justify-between px-6 py-3.5 hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                        <span class="font-bold text-gray-950 dark:text-white">1. Receita Operacional Bruta</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-xs text-gray-400 dark:text-gray-500">100.0%</span>
                            <span class="w-36 text-right font-mono font-bold text-gray-950 dark:text-white">R$ {{ number_format($report['revenue']['gross'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Deduções --}}
                    <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/30 dark:bg-gray-950/20 text-xs">
                        <span class="text-gray-600 dark:text-gray-400 pl-4 flex items-center gap-1.5"><x-heroicon-m-minus-small class="w-4 h-4 text-gray-400"/> Deduções de Venda (DAS Simples + Taxas Cartão)</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-gray-400 dark:text-gray-500">{{ $report['revenue']['gross'] > 0 ? number_format(($report['revenue']['deductions']['total'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono font-medium text-gray-600 dark:text-gray-400">- R$ {{ number_format($report['revenue']['deductions']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- 2. Receita Líquida --}}
                    <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition font-semibold">
                        <span class="text-gray-900 dark:text-gray-200">2. Receita Operacional Líquida</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-xs text-gray-400 dark:text-gray-500">{{ $report['revenue']['gross'] > 0 ? number_format(($report['revenue']['net'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">R$ {{ number_format($report['revenue']['net'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- CMV --}}
                    <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/30 dark:bg-gray-950/20 text-xs">
                        <span class="text-gray-600 dark:text-gray-400 pl-4 flex items-center gap-1.5"><x-heroicon-m-minus-small class="w-4 h-4 text-danger-400"/> Custo da Mercadoria Vendida (CMV Real)</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-gray-400 dark:text-gray-500">{{ $report['revenue']['gross'] > 0 ? number_format(($report['cogs']['total'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono font-medium text-danger-600 dark:text-danger-400">- R$ {{ number_format($report['cogs']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- 3. Margem de Contribuição --}}
                    <div class="flex items-center justify-between px-6 py-3.5 bg-primary-50/30 dark:bg-primary-950/20 transition">
                        <span class="font-bold text-primary-900 dark:text-primary-300">3. Margem de Contribuição</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono font-bold text-primary-600 dark:text-primary-400 text-xs">{{ $report['contribution_margin']['ratio'] }}%</span>
                            <span class="w-36 text-right font-mono font-bold text-primary-700 dark:text-primary-300">R$ {{ number_format($report['contribution_margin']['amount'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Despesas Operacionais --}}
                    <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/30 dark:bg-gray-950/20 text-xs">
                        <span class="text-gray-600 dark:text-gray-400 pl-4 flex items-center gap-1.5"><x-heroicon-m-minus-small class="w-4 h-4 text-gray-400"/> Estrutura, Ocupação & Sistemas (Infra/Adm)</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-gray-400 dark:text-gray-500">{{ $report['revenue']['gross'] > 0 ? number_format((($report['expenses']['utilities'] + $report['expenses']['admin'] + $report['expenses']['maintenance']) / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono font-medium text-gray-600 dark:text-gray-400">- R$ {{ number_format($report['expenses']['utilities'] + $report['expenses']['admin'] + $report['expenses']['maintenance'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/30 dark:bg-gray-950/20 text-xs">
                        <span class="text-gray-600 dark:text-gray-400 pl-4 flex items-center gap-1.5"><x-heroicon-m-minus-small class="w-4 h-4 text-gray-400"/> Equipe, Pessoal & Freelancers</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-gray-400 dark:text-gray-500">{{ $report['revenue']['gross'] > 0 ? number_format(($report['expenses']['payroll'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono font-medium text-gray-600 dark:text-gray-400">- R$ {{ number_format($report['expenses']['payroll'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($report['expenses']['uncategorized'] > 0)
                    <div class="flex items-center justify-between px-6 py-2.5 bg-warning-50/40 dark:bg-warning-950/20 text-xs">
                        <span class="text-warning-800 dark:text-warning-400 pl-4 flex items-center gap-1.5 font-semibold"><x-heroicon-m-exclamation-triangle class="w-4 h-4 text-warning-500"/> Contas a Classificar (Auditoria)</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-warning-600/70 dark:text-warning-400/70">{{ $report['revenue']['gross'] > 0 ? number_format(($report['expenses']['uncategorized'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono font-semibold text-warning-800 dark:text-warning-400">- R$ {{ number_format($report['expenses']['uncategorized'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                    @endif

                    {{-- 4. EBITDA --}}
                    <div class="flex items-center justify-between px-6 py-3.5 hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition font-bold">
                        <span class="text-gray-950 dark:text-white">4. EBITDA (Resultado Operacional)</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-xs font-semibold {{ $report['operating_profit']['amount'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">{{ $report['operating_profit']['margin'] }}%</span>
                            <span class="w-36 text-right font-mono font-bold {{ $report['operating_profit']['amount'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">R$ {{ number_format($report['operating_profit']['amount'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Fora do Operacional --}}
                    @if($report['capex']['total'] > 0)
                    <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/30 dark:bg-gray-950/20 text-xs">
                        <span class="text-gray-500 dark:text-gray-400 pl-4 flex items-center gap-1.5"><x-heroicon-m-minus-small class="w-4 h-4 text-gray-400"/> Investimentos em Ativos / Fachada (CAPEX)</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-gray-400 dark:text-gray-500">{{ $report['revenue']['gross'] > 0 ? number_format(($report['capex']['total'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono font-medium text-gray-500 dark:text-gray-400">- R$ {{ number_format($report['capex']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/30 dark:bg-gray-950/20 text-xs">
                        <span class="text-gray-500 dark:text-gray-400 pl-4 flex items-center gap-1.5"><x-heroicon-m-minus-small class="w-4 h-4 text-gray-400"/> Retiradas dos Sócios (Segregação Pessoal)</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-gray-400 dark:text-gray-500">{{ $report['revenue']['gross'] > 0 ? number_format(($report['personal_expenses']['total'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono font-medium text-gray-500 dark:text-gray-400">- R$ {{ number_format($report['personal_expenses']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Resultado Líquido Final --}}
                    <div class="flex items-center justify-between px-6 py-4 bg-gray-100/70 dark:bg-gray-950 border-t border-gray-200 dark:border-white/10 font-bold">
                        <span class="text-sm font-bold uppercase tracking-tight text-gray-950 dark:text-white">Resultado Líquido Retido no Caixa</span>
                        <div class="flex items-center gap-x-8 shrink-0">
                            <span class="w-16 text-right font-mono text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format(($report['retained_profit'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="w-36 text-right font-mono text-base font-extrabold {{ $report['retained_profit'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">R$ {{ number_format($report['retained_profit'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

        @elseif($activeView === 'cmv')
            {{-- VIEW 2: CMV & DESPERDÍCIO --}}

            {{-- LINHA ÚNICA DE 4 CARDS COM ÍCONES --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Card 1 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">CMV Real (Compras)</span>
                        <div class="p-1.5 rounded-lg bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 ring-1 ring-gray-950/5 dark:ring-white/10 shrink-0">
                            <x-heroicon-m-shopping-cart class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                            R$ {{ number_format($cmv['cogs']['actual']['total'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs font-semibold text-danger-600 dark:text-danger-400 truncate">
                            {{ $cmv['cogs']['actual']['ratio'] }}% da Rec. Líquida
                        </div>
                    </div>
                </div>

                {{-- Card 2 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">CMV Teórico (Consumo)</span>
                        <div class="p-1.5 rounded-lg bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 ring-1 ring-gray-950/5 dark:ring-white/10 shrink-0">
                            <x-heroicon-m-clipboard-document-list class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                            R$ {{ number_format($cmv['cogs']['theoretical']['total'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs font-semibold text-primary-600 dark:text-primary-400 truncate">
                            {{ $cmv['cogs']['theoretical']['ratio'] }}% esperado
                        </div>
                    </div>
                </div>

                {{-- Card 3 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Desvio / Fuga</span>
                        <div class="p-1.5 rounded-lg bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 ring-1 ring-gray-950/5 dark:ring-white/10 shrink-0">
                            <x-heroicon-m-arrow-trending-down class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight {{ $cmv['cogs']['variance']['nominal'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                            R$ {{ number_format(abs($cmv['cogs']['variance']['nominal']), 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $cmv['cogs']['variance']['ratio_on_net'] }}% da receita
                        </div>
                    </div>
                </div>

                {{-- Card 4 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Status Operacional</span>
                        <div class="p-1.5 rounded-lg {{ $cmv['cogs']['variance']['is_waste_alert'] ? 'bg-warning-50 dark:bg-warning-950/50 text-warning-600 dark:text-warning-400 ring-1 ring-warning-500/20' : 'bg-success-50 dark:bg-success-950/50 text-success-600 dark:text-success-400 ring-1 ring-success-500/20' }} shrink-0">
                            <x-heroicon-m-shield-check class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-lg font-bold tracking-tight {{ $cmv['cogs']['variance']['is_waste_alert'] ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}">
                            {{ $cmv['cogs']['variance']['is_waste_alert'] ? 'Atenção ⚠️' : 'Eficiente 🟢' }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $cmv['cogs']['variance']['is_waste_alert'] ? 'Desperdício acima de 5%' : 'Consumo sob controle' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABELA: ENGENHARIA DE CARDÁPIO --}}
            <div class="flex flex-col rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 dark:border-white/10 px-6 py-4 bg-gray-50/50 dark:bg-gray-950/40">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white uppercase tracking-wider">Engenharia de Cardápio (Top Ofensores)</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Custos apurados considerando massa, óleo, embalagem e explosão real de recheios/modificadores.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left divide-y divide-gray-100 dark:divide-white/5">
                        <thead class="bg-gray-50/50 dark:bg-gray-950/30">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Produto</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Volume</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Faturamento</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Custo Unitário</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Custo Total</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Margem</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-center">Classificação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5 text-sm">
                            @forelse($cmv['menu_engineering']['top_cost'] as $item)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                                    <td class="px-6 py-3.5 font-medium text-gray-950 dark:text-white">{{ $item['name'] }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs text-gray-600 dark:text-gray-400">{{ number_format($item['qty_sold'], 0, ',', '.') }} un</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs text-gray-600 dark:text-gray-400">R$ {{ number_format($item['total_revenue'], 2, ',', '.') }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs text-gray-600 dark:text-gray-400">R$ {{ number_format($item['unit_cost'], 2, ',', '.') }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs font-bold text-danger-600 dark:text-danger-400">R$ {{ number_format($item['total_cost'], 2, ',', '.') }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs font-semibold {{ $item['margin_ratio'] >= 50 ? 'text-success-600 dark:text-success-400' : 'text-warning-600 dark:text-warning-400' }}">{{ $item['margin_ratio'] }}%</td>
                                    <td class="px-6 py-3.5 text-center">
                                        @if($item['quadrant'] === 'star')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-success-50 dark:bg-success-950/50 text-success-700 dark:text-success-400 ring-1 ring-inset ring-success-600/20">Estrela</span>
                                        @elseif($item['quadrant'] === 'plowhorse')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-warning-50 dark:bg-warning-950/50 text-warning-700 dark:text-warning-400 ring-1 ring-inset ring-warning-600/20">Burro de Carga</span>
                                        @elseif($item['quadrant'] === 'puzzle')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-primary-50 dark:bg-primary-950/50 text-primary-700 dark:text-primary-400 ring-1 ring-inset ring-primary-600/20">Quebra-Cabeça</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 ring-1 ring-inset ring-gray-500/20">Cão</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-600">Nenhuma venda registrada no período selecionado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @else
            {{-- VIEW 3: FLUXO DE CAIXA PROJETADO --}}

            {{-- LINHA ÚNICA DE 5 CARDS COM ÍCONES --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                {{-- Card 1 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Saldo Inicial</span>
                        <div class="p-1.5 rounded-lg bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 ring-1 ring-gray-950/5 dark:ring-white/10 shrink-0">
                            <x-heroicon-m-building-library class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                            R$ {{ number_format($projection['summary']['starting_balance'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Sicredi + Cofre
                        </div>
                    </div>
                </div>

                {{-- Card 2 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">(+) Entradas</span>
                        <div class="p-1.5 rounded-lg bg-success-50 dark:bg-success-950/50 text-success-600 dark:text-success-400 ring-1 ring-success-500/20 shrink-0">
                            <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight text-success-600 dark:text-success-400">
                            + R$ {{ number_format($projection['summary']['total_inflows'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Recebíveis em D+{{ $projectionDays }}
                        </div>
                    </div>
                </div>

                {{-- Card 3 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">(-) Saídas</span>
                        <div class="p-1.5 rounded-lg bg-danger-50 dark:bg-danger-950/50 text-danger-600 dark:text-danger-400 ring-1 ring-danger-500/20 shrink-0">
                            <x-heroicon-m-arrow-up-tray class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight text-danger-600 dark:text-danger-400">
                            - R$ {{ number_format($projection['summary']['total_outflows'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Contas + Recorrentes
                        </div>
                    </div>
                </div>

                {{-- Card 4 --}}
                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Ponto Mais Baixo</span>
                        <div class="p-1.5 rounded-lg bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 ring-1 ring-gray-950/5 dark:ring-white/10 shrink-0">
                            <x-heroicon-m-shield-exclamation class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight {{ $projection['summary']['lowest_balance']['amount'] >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                            R$ {{ number_format($projection['summary']['lowest_balance']['amount'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Em {{ $projection['summary']['lowest_balance']['date'] }}
                        </div>
                    </div>
                </div>

                {{-- Card 5 --}}
                <div class="col-span-2 lg:col-span-1 flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Saldo Final</span>
                        <div class="p-1.5 rounded-lg bg-success-50 dark:bg-success-950/50 text-success-600 dark:text-success-400 ring-1 ring-success-500/20 shrink-0">
                            <x-heroicon-m-sparkles class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xl font-bold tracking-tight {{ $projection['summary']['final_balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            R$ {{ number_format($projection['summary']['final_balance'], 2, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                            Em {{ $projection['meta']['end_date'] }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- AGENDA DIÁRIA --}}
            <div class="flex flex-col rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 dark:border-white/10 px-6 py-4 bg-gray-50/50 dark:bg-gray-950/40 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white uppercase tracking-wider">Agenda Diária de Liquidez</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Previsão diária de saldo com base nos vencimentos e recebíveis das operadoras.</p>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[500px]">
                    <table class="w-full text-left divide-y divide-gray-100 dark:divide-white/5">
                        <thead class="sticky top-0 bg-gray-50 dark:bg-gray-950 border-b border-gray-100 dark:border-white/10 backdrop-blur-md">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Data</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Dia</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Entradas (R$)</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Saídas (R$)</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Resultado do Dia</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Saldo Projetado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5 text-sm">
                            @foreach($projection['timeline'] as $day)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition {{ $day['projected_balance'] < 0 ? 'bg-danger-50/30 dark:bg-danger-950/20' : '' }}">
                                    <td class="px-6 py-3.5 font-medium text-gray-950 dark:text-white">{{ $day['date'] }}</td>
                                    <td class="px-6 py-3.5 text-xs uppercase font-medium text-gray-500 dark:text-gray-400">{{ $day['weekday'] }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs font-medium {{ $day['inflow'] > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-600' }}">{{ $day['inflow'] > 0 ? '+ ' . number_format($day['inflow'], 2, ',', '.') : '-' }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs font-medium {{ $day['outflow'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400 dark:text-gray-600' }}">{{ $day['outflow'] > 0 ? '- ' . number_format($day['outflow'], 2, ',', '.') : '-' }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs font-semibold {{ $day['net_day'] > 0 ? 'text-success-600 dark:text-success-400' : ($day['net_day'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400 dark:text-gray-600') }}">{{ $day['net_day'] != 0 ? number_format($day['net_day'], 2, ',', '.') : '-' }}</td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs font-bold {{ $day['projected_balance'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">R$ {{ number_format($day['projected_balance'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
