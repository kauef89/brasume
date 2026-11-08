<x-filament-panels::page>
    <div class="flex flex-col gap-6">

        {{-- BARRA DE FILTROS DE PERÍODO E REGIME --}}
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 p-2 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            <div class="flex items-center gap-2 px-3">
                <x-heroicon-m-scale class="w-5 h-5 text-warning-500" />
                <span class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">CMV & Engenharia de Cardápio</span>
            </div>

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
        </div>

        {{-- 4 CARDS SUPERIORES --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
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
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Custos apurados considerando massa, óleo, embalagem e explosão real de recheios e modificadores.</p>
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

    </div>
</x-filament-panels::page>
