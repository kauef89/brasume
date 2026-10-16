@php
    $showWidget = isset($marginData) && !is_null($marginData);

    if ($showWidget) {
        $fmt = fn($val) => 'R$ ' . number_format($val, 2, ',', '.');

        $fmtUnitCost = function($val) use ($record) {
            $decimals = ($val > 0 && $val < 1) ? 5 : 2;
            $str = number_format((float) $val, $decimals, ',', '.');
            $str = rtrim(rtrim($str, '0'), ',');
            if ($str === '') $str = '0';
            return 'R$ ' . $str . ' / ' . ($record->unit_of_measure ?? 'UN');
        };

        $fmtYield = function($val) {
            $str = number_format((float) $val, 4, ',', '.');
            $str = rtrim(rtrim($str, '0'), ',');
            if ($str === '') $str = '0';
            return $str;
        };

        $isMenu = ($marginData['sell_price'] ?? 0) > 0;

        if ($isMenu) {
            $total = $marginData['sell_price'];
            $mPct = $marginData['margin_percentage'] ?? 0;
            $mColor = $mPct >= 30 ? 'success' : ($mPct >= 15 ? 'warning' : 'danger');
            $icon = $mPct >= 15 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down';

            // 🚀 VOLTAMOS AO FUNDO REATIVO ORIGINAL (MUITO MELHOR PARA A EXPERIÊNCIA VISUAL)
            $styles = [
                'success' => [
                    'card' => 'bg-white ring-success-500/20 dark:bg-gray-900 dark:ring-success-500/20 hover:bg-success-500/20',
                    'icon_wrapper' => 'bg-success-500/20 dark:bg-success-500/20',
                    'icon' => 'text-success-600 dark:text-success-400',
                    'text_muted' => 'text-success-600/80 dark:text-success-400/80',
                    'text_bold' => 'text-success-700 dark:text-success-300',
                    'border' => 'border-success-500/20',
                ],
                'warning' => [
                    'card' => 'bg-white ring-warning-500/20 dark:bg-gray-900 dark:ring-warning-500/20 hover:bg-warning-500/20',
                    'icon_wrapper' => 'bg-warning-500/20 dark:bg-warning-500/20',
                    'icon' => 'text-warning-600 dark:text-warning-400',
                    'text_muted' => 'text-warning-600/80 dark:text-warning-400/80',
                    'text_bold' => 'text-warning-700 dark:text-warning-300',
                    'border' => 'border-warning-500/20',
                ],
                'danger' => [
                    'card' => 'bg-white ring-danger-500/20 dark:bg-gray-900 dark:ring-danger-500/20 hover:bg-danger-500/20',
                    'icon_wrapper' => 'bg-danger-500/20 dark:bg-danger-500/20',
                    'icon' => 'text-danger-600 dark:text-danger-400',
                    'text_muted' => 'text-danger-600/80 dark:text-danger-400/80',
                    'text_bold' => 'text-danger-700 dark:text-danger-300',
                    'border' => 'border-danger-500/20',
                ],
            ];
            $c = $styles[$mColor];
        }
    }
@endphp

{{-- 🚀 ROOT DO LIVEWIRE: EXATAMENTE UM ELEMENTO RAIZ --}}
<div class="fi-wi-widget-bypass col-span-full mb-6 w-full" style="{{ !$showWidget ? 'display: none;' : '' }}">

    {{-- O estilo fica aqui dentro para não quebrar o motor do Livewire --}}
    <style>
        .fi-wi-widget-bypass {
            grid-column: 1 / -1 !important;
        }
        div:has(> .fi-wi-widget-bypass) {
            display: block !important;
            width: 100% !important;
        }
    </style>

    @if($showWidget)
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 w-full">

            @if($isMenu)
                {{-- CARD 1: CMV --}}
                <div class="flex items-center justify-between p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="flex items-center justify-center p-3 rounded-full bg-primary-500/10 dark:bg-primary-500/20 shrink-0">
                            <x-heroicon-o-beaker class="w-5 h-5 text-primary-smart" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Custo Médio (CMV)</p>
                            <p class="text-xl font-black text-gray-900 dark:text-white truncate">{{ $fmt($marginData['base_cost']) }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col justify-center text-right gap-1 ml-2 pl-4 border-l border-gray-100 dark:border-gray-800 shrink-0">
                        <div class="leading-none">
                            <span class="text-[9px] font-medium uppercase tracking-wider text-gray-400">Mesa</span><br>
                            <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300">{{ $fmt($marginData['cost_table']) }}</span>
                        </div>
                        <div class="leading-none mt-1">
                            <span class="text-[9px] font-medium uppercase tracking-wider text-gray-400">Delivery</span><br>
                            <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300">{{ $fmt($marginData['cost_delivery']) }}</span>
                        </div>
                    </div>
                </div>

                {{-- CARD 2: CUSTOS VARIÁVEIS --}}
                <div class="flex items-center justify-between p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="flex items-center justify-center p-3 rounded-full bg-primary-500/10 dark:bg-primary-500/20 shrink-0">
                            <x-heroicon-o-receipt-percent class="w-5 h-5 text-primary-smart" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Custos Variáveis</p>
                            <p class="text-xl font-black text-gray-900 dark:text-white truncate">{{ $fmt($marginData['total_variables']) }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col justify-center text-right gap-1 ml-2 pl-4 border-l border-gray-100 dark:border-gray-800 shrink-0">
                        <div class="leading-none">
                            <span class="text-[9px] font-medium uppercase tracking-wider text-gray-400">Impostos</span><br>
                            <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300">{{ $fmt($marginData['simples_amount'] + $marginData['icms_amount']) }}</span>
                        </div>
                        <div class="leading-none mt-1">
                            <span class="text-[9px] font-medium uppercase tracking-wider text-gray-400">Cartão</span><br>
                            <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300">{{ $fmt($marginData['card_fee_amount']) }}</span>
                        </div>
                    </div>
                </div>

                {{-- CARD 3: LUCRATIVIDADE (COM COR REATIVA!) --}}
                <div class="flex items-center justify-between p-4 rounded-xl ring-1 transition shadow-sm {{ $c['card'] }}">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="flex items-center justify-center p-3 rounded-full shrink-0 {{ $c['icon_wrapper'] }}">
                            <x-dynamic-component :component="$icon" class="w-5 h-5 {{ $c['icon'] }}" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider {{ $c['text_muted'] }}">Lucratividade</p>
                            <p class="text-xl font-black truncate {{ $c['text_bold'] }}">{{ number_format($mPct, 2, ',', '.') }}%</p>
                        </div>
                    </div>
                    <div class="flex flex-col justify-center text-right gap-1 ml-2 pl-4 border-l shrink-0 {{ $c['border'] }}">
                        <div class="leading-none">
                            <span class="text-[9px] font-medium uppercase tracking-wider {{ $c['text_muted'] }}">M. Contribuição</span><br>
                            <span class="text-[11px] font-medium {{ $c['text_bold'] }}">{{ $fmt($marginData['contribution_margin']) }}</span>
                        </div>
                        <div class="leading-none mt-1">
                            <span class="text-[9px] font-medium uppercase tracking-wider {{ $c['text_muted'] }}">Preço Venda</span><br>
                            <span class="text-[11px] font-medium {{ $c['text_bold'] }}">{{ $fmt($marginData['sell_price']) }}</span>
                        </div>
                    </div>
                </div>

            @else
                {{-- LAYOUT INSUMO/PREPARADO --}}
                <div class="flex items-center gap-4 p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center justify-center p-3 rounded-full bg-primary-500/10 dark:bg-primary-500/20 shrink-0">
                        <x-heroicon-o-shopping-cart class="w-5 h-5 text-primary-smart" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Custo Total Receita</p>
                        <p class="text-xl font-black text-gray-900 dark:text-white truncate">{{ $fmt($marginData['base_cost'] * ($record->recipe_yield ?? 1)) }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center justify-center p-3 rounded-full bg-primary-500/10 dark:bg-primary-500/20 shrink-0">
                        <x-heroicon-o-scale class="w-5 h-5 text-primary-smart" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Rendimento</p>
                        <p class="text-xl font-black text-gray-900 dark:text-white truncate">{{ $fmtYield($record->recipe_yield ?? 1) }} {{ $record->unit_of_measure }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center justify-center p-3 rounded-full bg-primary-500/10 dark:bg-primary-500/20 shrink-0">
                        <x-heroicon-o-calculator class="w-5 h-5 text-primary-smart" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Custo Unitário</p>
                        <p class="text-xl font-black text-gray-900 dark:text-white truncate">{{ $fmtUnitCost($marginData['base_cost']) }}</p>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
