<x-filament-panels::page>
    <div class="flex flex-col gap-6">

        {{-- BARRA DE CONTROLE: VISÃO, PERÍODO E REGIME --}}
        <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            
            {{-- SELETOR DE MÓDULO (DRE vs CMV) --}}
            <div class="flex rounded-lg bg-gray-100 dark:bg-gray-900 p-1">
                <button wire:click="setActiveView('dre')" class="px-4 py-2 text-xs font-bold rounded-md transition flex items-center gap-2 {{ $activeView === 'dre' ? 'bg-white dark:bg-gray-800 text-primary-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                    <x-heroicon-o-document-chart-bar class="w-4 h-4" />
                    DRE & Break-Even
                </button>
                <button wire:click="setActiveView('cmv')" class="px-4 py-2 text-xs font-bold rounded-md transition flex items-center gap-2 {{ $activeView === 'cmv' ? 'bg-white dark:bg-gray-800 text-primary-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                    <x-heroicon-o-scale class="w-4 h-4" />
                    CMV & Desperdício
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex rounded-lg bg-gray-100 dark:bg-gray-900 p-1">
                    <button wire:click="setPeriod('this_month')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedPeriod === 'this_month' ? 'bg-white dark:bg-gray-800 text-primary-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        Mês Atual
                    </button>
                    <button wire:click="setPeriod('last_month')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedPeriod === 'last_month' ? 'bg-white dark:bg-gray-800 text-primary-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        Mês Anterior
                    </button>
                    <button wire:click="setPeriod('quarter')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedPeriod === 'quarter' ? 'bg-white dark:bg-gray-800 text-primary-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        90 Dias
                    </button>
                    <button wire:click="setPeriod('this_year')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedPeriod === 'this_year' ? 'bg-white dark:bg-gray-800 text-primary-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        Ano {{ date('Y') }}
                    </button>
                </div>

                <div class="flex rounded-lg bg-gray-100 dark:bg-gray-900 p-1">
                    <button wire:click="setRegime('competencia')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedRegime === 'competencia' ? 'bg-white dark:bg-gray-800 text-primary-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        Competência
                    </button>
                    <button wire:click="setRegime('caixa')" class="px-3 py-1.5 text-xs font-medium rounded-md transition {{ $selectedRegime === 'caixa' ? 'bg-white dark:bg-gray-800 text-primary-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                        Caixa
                    </button>
                </div>
            </div>
        </div>

        @if($activeView === 'dre')
            {{-- VIEW 1: DRE & BREAK-EVEN (KPIS + RÉGUA + TABELA) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Faturamento Bruto</span>
                    <div class="mt-1 text-2xl font-black text-gray-900 dark:text-white">
                        R$ {{ number_format($report['revenue']['gross'], 2, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Líquido: R$ {{ number_format($report['revenue']['net'], 2, ',', '.') }}
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Margem de Contribuição</span>
                    <div class="mt-1 text-2xl font-black {{ $report['contribution_margin']['amount'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                        R$ {{ number_format($report['contribution_margin']['amount'], 2, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs font-semibold text-primary-600">
                        {{ $report['contribution_margin']['ratio'] }}% da Rec. Líquida
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Meta Break-Even</span>
                    <div class="mt-1 text-2xl font-black text-gray-900 dark:text-white">
                        R$ {{ number_format($report['break_even']['target'], 2, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Meta diária: R$ {{ number_format($report['break_even']['daily'], 2, ',', '.') }}
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">EBITDA Operacional</span>
                    <div class="mt-1 text-2xl font-black {{ $report['operating_profit']['amount'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                        R$ {{ number_format($report['operating_profit']['amount'], 2, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Margem: {{ $report['operating_profit']['margin'] }}%
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Retiradas Sócios</span>
                    <div class="mt-1 text-2xl font-black text-amber-600">
                        R$ {{ number_format($report['personal_expenses']['total'], 2, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Retido: <b class="{{ $report['retained_profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">R$ {{ number_format($report['retained_profit'], 2, ',', '.') }}</b>
                    </div>
                </div>
            </div>

            {{-- RÉGUA DO BREAK-EVEN --}}
            <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-bold text-gray-900 dark:text-white">🎯 Progresso do Ponto de Equilíbrio</span>
                        @if($report['break_even']['reached'])
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-success-50 dark:bg-success-500/10 text-success-600 dark:text-success-400 border border-success-200">
                                PONTO ATINGIDO! OPERANDO NO LUCRO
                            </span>
                        @else
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-200">
                                FALTAM R$ {{ number_format(abs($report['break_even']['difference']), 2, ',', '.') }}
                            </span>
                        @endif
                    </div>
                    <span class="text-sm font-black text-gray-700 dark:text-gray-300">
                        {{ $report['break_even']['progress'] }}%
                    </span>
                </div>
                <div class="w-full h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 {{ $report['break_even']['reached'] ? 'bg-success-500' : 'bg-primary-500' }}"
                         style="width: {{ $report['break_even']['progress'] }}%">
                    </div>
                </div>
            </div>

            {{-- TABELA DO DRE --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                        DRE Gerencial Canônico
                    </h3>
                    <span class="text-xs text-gray-400 font-medium font-mono">
                        {{ $report['period']['start'] }} a {{ $report['period']['end'] }} &bull; {{ strtoupper($report['period']['regime']) }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700/60 font-mono text-sm">
                    <div class="flex items-center justify-between px-5 py-3 bg-gray-50/50 dark:bg-gray-900/30">
                        <span class="font-bold text-gray-900 dark:text-white font-sans">1. RECEITA OPERACIONAL BRUTA</span>
                        <div class="flex items-center gap-6">
                            <span class="text-xs text-gray-400">100,0%</span>
                            <span class="font-bold text-gray-900 dark:text-white w-32 text-right">
                                R$ {{ number_format($report['revenue']['gross'], 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-2 text-danger-600 text-xs">
                        <span class="font-sans pl-4">(-) Deduções da Venda (DAS Simples + Taxas Cartão)</span>
                        <div class="flex items-center gap-6">
                            <span class="text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format(($report['revenue']['deductions']['total'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="font-medium w-32 text-right">- R$ {{ number_format($report['revenue']['deductions']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-2.5 bg-gray-50/30 dark:bg-gray-900/20 font-semibold">
                        <span class="font-sans pl-2 text-gray-800 dark:text-gray-200">(=) RECEITA OPERACIONAL LÍQUIDA</span>
                        <div class="flex items-center gap-6">
                            <span class="text-xs text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format(($report['revenue']['net'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="text-gray-900 dark:text-white w-32 text-right">R$ {{ number_format($report['revenue']['net'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-2.5 text-danger-600 text-xs">
                        <span class="font-sans pl-4">(-) CMV Real (Insumos & Embalagens)</span>
                        <div class="flex items-center gap-6">
                            <span class="text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format(($report['cogs']['total'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="font-medium w-32 text-right">- R$ {{ number_format($report['cogs']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-3 bg-primary-50/20 dark:bg-primary-950/20 font-bold">
                        <span class="font-sans pl-2 text-primary-700 dark:text-primary-300">(=) MARGEM DE CONTRIBUIÇÃO BRUTA</span>
                        <div class="flex items-center gap-6">
                            <span class="text-xs font-semibold text-primary-600">{{ $report['contribution_margin']['ratio'] }}%</span>
                            <span class="text-primary-700 dark:text-primary-300 w-32 text-right">R$ {{ number_format($report['contribution_margin']['amount'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-2 text-danger-600 text-xs">
                        <span class="font-sans pl-4">(-) Despesas Fixas (Estrutura, Ocupação, Sistemas)</span>
                        <div class="flex items-center gap-6">
                            <span class="text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format((($report['expenses']['utilities'] + $report['expenses']['admin'] + $report['expenses']['maintenance']) / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="font-medium w-32 text-right">- R$ {{ number_format($report['expenses']['utilities'] + $report['expenses']['admin'] + $report['expenses']['maintenance'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-2 text-danger-600 text-xs">
                        <span class="font-sans pl-4">(-) Pessoal & Equipe</span>
                        <div class="flex items-center gap-6">
                            <span class="text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format(($report['expenses']['payroll'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="font-medium w-32 text-right">- R$ {{ number_format($report['expenses']['payroll'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($report['expenses']['uncategorized'] > 0)
                    <div class="flex items-center justify-between px-5 py-2 text-amber-700 bg-amber-50/40 dark:bg-amber-950/20 text-xs">
                        <span class="font-sans pl-4 font-bold">(!) Despesas Pendentes de Categorização</span>
                        <div class="flex items-center gap-6">
                            <span class="text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format(($report['expenses']['uncategorized'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="font-semibold w-32 text-right">- R$ {{ number_format($report['expenses']['uncategorized'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center justify-between px-5 py-3 bg-gray-50/60 dark:bg-gray-900/40 font-bold">
                        <span class="font-sans pl-2 text-gray-900 dark:text-white">(=) EBITDA OPERACIONAL</span>
                        <div class="flex items-center gap-6">
                            <span class="text-xs font-semibold {{ $report['operating_profit']['amount'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ $report['operating_profit']['margin'] }}%</span>
                            <span class="{{ $report['operating_profit']['amount'] >= 0 ? 'text-success-600' : 'text-danger-600' }} w-32 text-right">R$ {{ number_format($report['operating_profit']['amount'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($report['capex']['total'] > 0)
                    <div class="flex items-center justify-between px-5 py-2 text-gray-500 text-xs">
                        <span class="font-sans pl-4">(-) Investimentos / CAPEX (Ativos Imobilizados)</span>
                        <div class="flex items-center gap-6">
                            <span class="text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format(($report['capex']['total'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="font-medium w-32 text-right">- R$ {{ number_format($report['capex']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center justify-between px-5 py-2 text-amber-600 text-xs">
                        <span class="font-sans pl-4">(-) Retiradas Sócios (Pessoal)</span>
                        <div class="flex items-center gap-6">
                            <span class="text-gray-400">{{ $report['revenue']['gross'] > 0 ? number_format(($report['personal_expenses']['total'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="font-medium w-32 text-right">- R$ {{ number_format($report['personal_expenses']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-4 bg-gray-100/60 dark:bg-gray-900/80 font-black text-base">
                        <span class="font-sans pl-2 text-gray-900 dark:text-white">(=) RESULTADO LÍQUIDO RETIDO</span>
                        <div class="flex items-center gap-6">
                            <span class="text-xs font-bold text-gray-500">{{ $report['revenue']['gross'] > 0 ? number_format(($report['retained_profit'] / $report['revenue']['gross']) * 100, 1) : 0 }}%</span>
                            <span class="{{ $report['retained_profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }} w-32 text-right">R$ {{ number_format($report['retained_profit'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

        @else
            {{-- VIEW 2: AUDITORIA DE CMV & ENGENHARIA DE CARDÁPIO --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">CMV Real (Desembolso)</span>
                    <div class="mt-1 text-2xl font-black text-gray-900 dark:text-white">
                        R$ {{ number_format($cmv['cogs']['actual']['total'], 2, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs font-bold text-danger-600">
                        {{ $cmv['cogs']['actual']['ratio'] }}% da Rec. Líquida
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">CMV Teórico (Fichas)</span>
                    <div class="mt-1 text-2xl font-black text-gray-900 dark:text-white">
                        R$ {{ number_format($cmv['cogs']['theoretical']['total'], 2, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs font-bold text-primary-600">
                        {{ $cmv['cogs']['theoretical']['ratio'] }}% esperado
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Fuga de Margem / Perda</span>
                    <div class="mt-1 text-2xl font-black {{ $cmv['cogs']['variance']['nominal'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                        R$ {{ number_format(abs($cmv['cogs']['variance']['nominal']), 2, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs font-semibold {{ $cmv['cogs']['variance']['is_waste_alert'] ? 'text-danger-600' : 'text-gray-500' }}">
                        {{ $cmv['cogs']['variance']['ratio_on_net'] }}% da receita
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Eficiência da Cozinha</span>
                    <div class="mt-1 text-2xl font-black {{ $cmv['cogs']['variance']['is_waste_alert'] ? 'text-amber-600' : 'text-success-600' }}">
                        {{ $cmv['cogs']['variance']['is_waste_alert'] ? 'Atenção ⚠️' : 'Excelente 🟢' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ $cmv['cogs']['variance']['is_waste_alert'] ? 'Desperdício acima de 5%' : 'Consumo dentro do padrão' }}
                    </div>
                </div>
            </div>

            {{-- TABELA: TOP ITENS OFENSORES DO CMV TEÓRICO --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                            Principais Itens de Custo (Top Ofensores do Cardápio)
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Produtos com maior peso nominal de custo na operação da pastelaria.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/50 dark:bg-gray-900/50 text-[11px] uppercase tracking-wider text-gray-500 font-semibold border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">Produto</th>
                                <th class="px-5 py-3 text-right">Qtd Vendida</th>
                                <th class="px-5 py-3 text-right">Faturamento</th>
                                <th class="px-5 py-3 text-right">Custo Unit. (Ficha)</th>
                                <th class="px-5 py-3 text-right">Custo Total</th>
                                <th class="px-5 py-3 text-right">Margem Unit.</th>
                                <th class="px-5 py-3 text-center">Classificação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 font-mono text-xs">
                            @forelse($cmv['menu_engineering']['top_cost'] as $item)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/30">
                                    <td class="px-5 py-3 font-bold font-sans text-gray-900 dark:text-white">
                                        {{ $item['name'] }}
                                    </td>
                                    <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($item['qty_sold'], 0, ',', '.') }} un
                                    </td>
                                    <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">
                                        R$ {{ number_format($item['total_revenue'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">
                                        R$ {{ number_format($item['unit_cost'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-bold text-danger-600">
                                        R$ {{ number_format($item['total_cost'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold {{ $item['margin_ratio'] >= 50 ? 'text-success-600' : 'text-amber-600' }}">
                                        {{ $item['margin_ratio'] }}%
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        @if($item['quadrant'] === 'star')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-success-50 dark:bg-success-500/10 text-success-600 border border-success-200">★ ESTRELA</span>
                                        @elseif($item['quadrant'] === 'plowhorse')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-600 border border-amber-200">🐴 BURRO DE CARGA</span>
                                        @elseif($item['quadrant'] === 'puzzle')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-500/10 text-blue-600 border border-blue-200">🧩 QUEBRA-CABEÇA</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 border border-gray-300">🐕 CÃO</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-8 text-center text-gray-400 font-sans">
                                        Nenhuma venda com ficha técnica registrada no período selecionado.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
