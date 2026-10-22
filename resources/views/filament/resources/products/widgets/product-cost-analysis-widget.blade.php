@php
    $show = isset($analysis) && !empty($analysis);
@endphp

<div class="fi-wi-widget-bypass col-span-full mb-6 w-full" style="{{ !$show ? 'display: none;' : '' }}">
    @if($show)
        @php
            $fmt = fn($val) => 'R$ ' . number_format($val, 2, ',', '.');
            $fmtPct = fn($val) => number_format((float) $val, 1, ',', '.') . '%';

            // Tendência de CMV
            $cmvRising = $analysis['cmv_status'] === 'rising';
            $cmvFalling = $analysis['cmv_status'] === 'falling';

            // Oscilação de Margem
            $marginGain = $analysis['margin_status'] === 'gaining';
        @endphp

        <div class="w-full space-y-4">
            {{-- CABEÇALHO DO COCKPIT --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-m-chart-bar-square class="w-5 h-5 text-gray-400" />
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                        Termômetro de Custo & Sensibilidade
                    </h3>
                </div>
                <div class="flex items-center gap-1 text-xs bg-gray-100 dark:bg-white/5 p-1 rounded-lg">
                    <button wire:click="setDays(30)" type="button"
                            class="px-2.5 py-1 font-semibold rounded-md transition {{ $selectedDays === 30 ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900' }}">
                        30 dias
                    </button>
                    <button wire:click="setDays(60)" type="button"
                            class="px-2.5 py-1 font-semibold rounded-md transition {{ $selectedDays === 60 ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900' }}">
                        60 dias
                    </button>
                    <button wire:click="setDays(90)" type="button"
                            class="px-2.5 py-1 font-semibold rounded-md transition {{ $selectedDays === 90 ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900' }}">
                        90 dias
                    </button>
                </div>
            </div>

            {{-- GRID DE 3 CARDS INDICADORES --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                {{-- CARD 1: TENDÊNCIA DE CMV --}}
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Tendência de CMV</span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-xl font-black text-gray-900 dark:text-white">{{ $fmt($analysis['current_cmv']) }}</span>
                            <span class="text-xs font-black {{ $cmvRising ? 'text-rose-500' : ($cmvFalling ? 'text-emerald-500' : 'text-gray-400') }}">
                                {{ $analysis['cmv_diff_pct'] > 0 ? '+' : '' }}{{ $fmtPct($analysis['cmv_diff_pct']) }}
                            </span>
                        </div>
                        <span class="text-[11px] text-gray-500">
                            {{ $cmvRising ? 'Ficando mais caro (pressão de insumos)' : ($cmvFalling ? 'Ficando mais barato (ganho de compra)' : 'Estável no período') }}
                        </span>
                    </div>
                    <div class="p-3 rounded-full {{ $cmvRising ? 'bg-rose-500/10 text-rose-500' : ($cmvFalling ? 'bg-emerald-500/10 text-emerald-500' : 'bg-gray-100 text-gray-400') }}">
                        <x-dynamic-component :component="$cmvRising ? 'heroicon-o-arrow-trending-up' : ($cmvFalling ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-minus')" class="w-6 h-6" />
                    </div>
                </div>

                {{-- CARD 2: OSCILAÇÃO DE MARGEM DE CONTRIBUIÇÃO --}}
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Oscilação de Margem</span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-xl font-black text-gray-900 dark:text-white">{{ $fmtPct($analysis['current_margin']) }}</span>
                            <span class="text-xs font-black {{ $marginGain ? 'text-emerald-500' : 'text-rose-500' }}">
                                {{ $analysis['margin_diff_pp'] > 0 ? '+' : '' }}{{ number_format($analysis['margin_diff_pp'], 1, ',', '.') }} p.p.
                            </span>
                        </div>
                        <span class="text-[11px] text-gray-500">
                            {{ $marginGain ? 'Expansão de rentabilidade' : 'Compressão da margem real' }}
                        </span>
                    </div>
                    <div class="p-3 rounded-full {{ $marginGain ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500' }}">
                        <x-dynamic-component :component="$marginGain ? 'heroicon-o-shield-check' : 'heroicon-o-shield-exclamation'" class="w-6 h-6" />
                    </div>
                </div>

                {{-- CARD 3: OFENSORES & ALERTAS --}}
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Ofensores & Alertas</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ count($analysis['alerts']) > 0 ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20' : 'bg-emerald-500/10 text-emerald-500' }}">
                                {{ count($analysis['alerts']) }} ponto(s)
                            </span>
                        </div>
                        <div class="mt-2 space-y-1">
                            @forelse(array_slice($analysis['alerts'], 0, 2) as $alert)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-gray-700 dark:text-gray-300 truncate max-w-[150px]">{{ $alert['name'] }}</span>
                                    <span class="font-black text-[11px] {{ $alert['severity'] === 'danger' ? 'text-rose-500' : ($alert['severity'] === 'warning' ? 'text-amber-500' : 'text-primary-500') }}">
                                        {{ $alert['badge'] }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400">Nenhum pico inflacionário recente nos insumos.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>

            {{-- DETALHAMENTO DO TOP 5 INSUMOS NO CMV --}}
            @if(!empty($analysis['top_ingredients']))
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-3">
                        Composição do Custo & Participação no Prato (Top 5 Insumos)
                    </span>
                    <div class="space-y-3">
                        @foreach($analysis['top_ingredients'] as $ing)
                            <div>
                                <div class="flex justify-between items-center text-xs mb-1">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">
                                        {{ $ing['name'] }} <span class="text-gray-400 font-normal">({{ $ing['quantity'] }} {{ $ing['uom'] }})</span>
                                    </span>
                                    <div class="flex items-center gap-3">
                                        @if($ing['inflation_pct'] != 0)
                                            <span class="text-[10px] font-bold {{ $ing['inflation_pct'] > 0 ? 'text-rose-500' : 'text-emerald-500' }}">
                                                NF-e: {{ $ing['inflation_pct'] > 0 ? '+' : '' }}{{ $fmtPct($ing['inflation_pct']) }}
                                            </span>
                                        @endif
                                        <span class="font-bold text-gray-900 dark:text-white">{{ $fmt($ing['total_cost']) }}</span>
                                        <span class="text-gray-400 text-[10px] w-12 text-right font-medium">({{ $fmtPct($ing['share_pct']) }})</span>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-white/5 h-1.5 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $ing['share_pct'] >= 40 ? 'bg-primary-500' : ($ing['share_pct'] >= 20 ? 'bg-amber-500' : 'bg-gray-400') }}"
                                         style="width: {{ min($ing['share_pct'], 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
