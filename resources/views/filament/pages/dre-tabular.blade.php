<x-filament-panels::page>
    @php
        $data = $this->getMonthsData();
        $months = $data['months'];
        $total = $data['total'];

        $fmt = fn ($val) => number_format((float) ($val ?? 0), 2, ',', '.');
        $pct = fn ($val, $base) => ($base > 0) ? number_format((($val / $base) * 100), 1, ',', '.') . '%' : '0,0%';

        $monthNames = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
        ];
    @endphp

    <style>
        .dre-viewport {
            max-height: calc(100vh - 16rem);
            overflow: auto;
            position: relative;
            border-radius: 0.75rem;
            width: 100%;
        }

        .dre-grid {
            border-collapse: separate !important;
            border-spacing: 0 !important;
            width: 100%;
            font-size: 0.75rem;
        }

        /* 1. Canto Superior Esquerdo */
        .dre-grid th.corner-cell {
            position: sticky;
            top: 0;
            left: 0;
            z-index: 40;
            width: 440px;
            min-width: 440px;
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            border-right: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        /* 2. Cabeçalho dos Meses */
        .dre-grid thead th {
            position: sticky;
            top: 0;
            z-index: 20;
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            color: #475569;
            white-space: nowrap;
        }

        /* 3. Coluna de Rubricas (Espaçosa e em linha única) */
        .dre-grid tbody td.sticky-rubric {
            position: sticky;
            left: 0;
            z-index: 10;
            width: 440px;
            min-width: 440px;
            max-width: 440px;
            background-color: #ffffff;
            border-right: 2px solid #e2e8f0;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
        }

        .dre-grid tbody td {
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
        }

        .dre-grid thead th.col-total {
            background-color: #eff6ff;
            color: #1e40af;
            border-left: 2px solid #cbd5e1;
        }
        .dre-grid tbody td.col-total {
            background-color: #f8fafc;
            border-left: 2px solid #e2e8f0;
        }

        .dre-row-gross td { background-color: #f8fafc; }
        .dre-row-net td { background-color: #f1f5f9; }
        .dre-row-margin td { background-color: #ecfdf5; color: #065f46; }
        .dre-row-ebitda td { background-color: #fffbeb; color: #92400e; }
        .dre-row-profit td { background-color: #f8fafc; color: #0f172a; }

        /* MODO ESCURO */
        .dark .dre-viewport {
            background-color: #09090b;
            border-color: #27272a;
        }

        .dark .dre-grid th.corner-cell {
            background-color: #18181b !important;
            color: #f4f4f5 !important;
            border-bottom-color: #27272a !important;
            border-right-color: #27272a !important;
        }

        .dark .dre-grid thead th {
            background-color: #18181b !important;
            color: #d4d4d8 !important;
            border-bottom-color: #27272a !important;
        }

        .dark .dre-grid thead th.col-total {
            background-color: #1e1b4b !important;
            color: #a5b4fc !important;
            border-left-color: #312e81 !important;
        }

        .dark .dre-grid tbody td.sticky-rubric {
            background-color: #09090b !important;
            color: #f4f4f5 !important;
            border-right-color: #27272a !important;
            border-bottom-color: #18181b !important;
        }

        .dark .dre-grid tbody td {
            background-color: #09090b;
            color: #e4e4e7;
            border-bottom-color: #18181b;
        }

        .dark .dre-grid tbody td.col-total {
            background-color: #111827 !important;
            border-left-color: #27272a !important;
        }

        .dark .dre-row-gross td {
            background-color: #18181b !important;
            color: #ffffff !important;
        }

        .dark .dre-row-net td {
            background-color: #1c1917 !important;
            color: #fafaf9 !important;
            border-top: 1px solid #292524 !important;
            border-bottom: 1px solid #292524 !important;
        }

        .dark .dre-row-margin td {
            background-color: rgba(6, 78, 59, 0.25) !important;
            color: #34d399 !important;
            border-top: 1px solid rgba(5, 150, 105, 0.3) !important;
            border-bottom: 1px solid rgba(5, 150, 105, 0.3) !important;
        }

        .dark .dre-row-ebitda td {
            background-color: rgba(120, 53, 15, 0.3) !important;
            color: #fbbf24 !important;
            border-top: 2px solid rgba(217, 119, 6, 0.5) !important;
            border-bottom: 2px solid rgba(217, 119, 6, 0.5) !important;
        }

        .dark .dre-row-profit td {
            background-color: #18181b !important;
            color: #ffffff !important;
            border-top: 1px solid #3f3f46 !important;
            border-bottom: 2px solid #52525b !important;
        }

        .dre-grid tbody tr:hover td { filter: brightness(0.96); }
        .dark .dre-grid tbody tr:hover td { filter: brightness(1.2); }
    </style>

    {{-- BARRA DE CONTROLE: ANO E REGIME --}}
    <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm transition-colors">
        <div class="flex items-center gap-3">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Exercício:</span>
            <select
                wire:model.live="year"
                class="text-sm font-semibold text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @foreach (range(now()->year - 2, now()->year + 1) as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mr-2">Regime Contábil:</span>
            <button
                type="button"
                wire:click="$set('regime', 'competencia')"
                class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all {{ $regime === 'competencia' ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                Competência (Apropriação)
            </button>
            <button
                type="button"
                wire:click="$set('regime', 'caixa')"
                class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all {{ $regime === 'caixa' ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                Caixa (Pagamentos Efetivos)
            </button>
        </div>
    </div>

    {{-- VIEWPORT COM COLUNA DE RUBRICAS AMPLA --}}
    <div class="dre-viewport border border-gray-200 dark:border-gray-800 shadow-sm">
        <table class="dre-grid">
            <thead>
                <tr>
                    <th class="corner-cell py-3 px-4 font-bold uppercase tracking-wider text-left">
                        Rubrica Contábil
                    </th>
                    @foreach ($monthNames as $mNum => $mLabel)
                        <th class="py-3 px-2 text-right font-bold uppercase tracking-wider min-w-[95px]">
                            {{ $mLabel }}/{{ substr((string)$year, 2) }}
                        </th>
                    @endforeach
                    <th class="col-total py-3 px-3 text-right font-black uppercase tracking-wider min-w-[125px]">
                        Total {{ $year }}
                    </th>
                </tr>
            </thead>
            <tbody class="font-mono">

                {{-- 1. RECEITA OPERACIONAL BRUTA --}}
                <tr class="dre-row-gross font-bold">
                    <td class="sticky-rubric py-2.5 px-4 font-sans font-bold">
                        (+) RECEITA OPERACIONAL BRUTA
                    </td>
                    @foreach ($months as $m)
                        <td class="py-2.5 px-2 text-right tabular-nums">{{ $fmt($m['revenue']['gross']) }}</td>
                    @endforeach
                    <td class="col-total py-2.5 px-3 text-right font-black tabular-nums">{{ $fmt($total['revenue']['gross']) }}</td>
                </tr>

                {{-- 2. DEDUÇÕES --}}
                <tr>
                    <td class="sticky-rubric py-2 px-4 pl-8 font-sans">
                        (-) Deduções da Receita Bruta
                    </td>
                    @foreach ($months as $m)
                        <td class="py-2 px-2 text-right text-rose-600 dark:text-rose-400 tabular-nums">-{{ $fmt($m['revenue']['deductions']['total']) }}</td>
                    @endforeach
                    <td class="col-total py-2 px-3 text-right font-bold text-rose-600 dark:text-rose-400 tabular-nums">-{{ $fmt($total['revenue']['deductions']['total']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Impostos (Simples Nacional)</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['revenue']['deductions']['taxes']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['revenue']['deductions']['taxes']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Taxas de Cartão / Adquirentes</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['revenue']['deductions']['card_fees']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['revenue']['deductions']['card_fees']) }}</td>
                </tr>

                {{-- 3. RECEITA LÍQUIDA --}}
                <tr class="dre-row-net font-bold">
                    <td class="sticky-rubric py-2 px-4 font-sans font-bold">
                        (=) RECEITA OPERACIONAL LÍQUIDA
                    </td>
                    @foreach ($months as $m)
                        <td class="py-2 px-2 text-right tabular-nums">{{ $fmt($m['revenue']['net']) }}</td>
                    @endforeach
                    <td class="col-total py-2 px-3 text-right font-black tabular-nums">{{ $fmt($total['revenue']['net']) }}</td>
                </tr>

                {{-- 4. CMV --}}
                <tr>
                    <td class="sticky-rubric py-2 px-4 pl-8 font-sans">
                        (-) Custo dos Produtos / CMV
                    </td>
                    @foreach ($months as $m)
                        <td class="py-2 px-2 text-right text-rose-600 dark:text-rose-400 tabular-nums">-{{ $fmt($m['cogs']['total']) }}</td>
                    @endforeach
                    <td class="col-total py-2 px-3 text-right font-bold text-rose-600 dark:text-rose-400 tabular-nums">-{{ $fmt($total['cogs']['total']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Insumos Alimentícios</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['cogs']['ingredients']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['cogs']['ingredients']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Embalagens e Descartáveis</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['cogs']['packaging']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['cogs']['packaging']) }}</td>
                </tr>

                {{-- 5. MARGEM DE CONTRIBUIÇÃO --}}
                <tr class="dre-row-margin font-bold">
                    <td class="sticky-rubric py-2.5 px-4 font-sans font-bold">
                        (=) MARGEM DE CONTRIBUIÇÃO
                    </td>
                    @foreach ($months as $m)
                        <td class="py-2.5 px-2 text-right tabular-nums">
                            {{ $fmt($m['contribution_margin']['amount']) }}
                            <div class="text-[10px] font-sans font-normal opacity-80">({{ $m['contribution_margin']['ratio'] }}%)</div>
                        </td>
                    @endforeach
                    <td class="col-total py-2.5 px-3 text-right font-black tabular-nums">
                        {{ $fmt($total['contribution_margin']['amount']) }}
                        <div class="text-[10px] font-sans font-normal opacity-80">({{ $total['contribution_margin']['ratio'] }}%)</div>
                    </td>
                </tr>

                {{-- 6. DESPESAS OPERACIONAIS FIXAS --}}
                <tr>
                    <td class="sticky-rubric py-2 px-4 pl-8 font-sans">
                        (-) Despesas Operacionais Fixas
                    </td>
                    @foreach ($months as $m)
                        <td class="py-2 px-2 text-right text-rose-600 dark:text-rose-400 tabular-nums">-{{ $fmt($m['expenses']['total_fixed']) }}</td>
                    @endforeach
                    <td class="col-total py-2 px-3 text-right font-bold text-rose-600 dark:text-rose-400 tabular-nums">-{{ $fmt($total['expenses']['total_fixed']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Equipe Operacional (CLT + Diaristas)</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['expenses']['payroll']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['expenses']['payroll']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Utilidades & Ocupação (Luz, Água, Gás, Net)</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['expenses']['utilities']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['expenses']['utilities']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Administrativo, Sistemas & Licenças</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['expenses']['admin']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['expenses']['admin']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Manutenção, Reparos & Limpeza</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['expenses']['maintenance']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['expenses']['maintenance']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Marketing, Tráfego & Promoção</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['expenses']['marketing']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['expenses']['marketing']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1 px-4 pl-12 font-sans">· Gastos a Categorizar</td>
                    @foreach ($months as $m)
                        <td class="py-1 px-2 text-right tabular-nums">-{{ $fmt($m['expenses']['uncategorized']) }}</td>
                    @endforeach
                    <td class="col-total py-1 px-3 text-right tabular-nums">-{{ $fmt($total['expenses']['uncategorized']) }}</td>
                </tr>

                {{-- 7. RESULTADO OPERACIONAL (EBITDA) --}}
                <tr class="dre-row-ebitda font-bold">
                    <td class="sticky-rubric py-3 px-4 font-sans font-bold">
                        (=) RESULTADO OPERACIONAL (EBITDA)
                    </td>
                    @foreach ($months as $m)
                        <td class="py-3 px-2 text-right tabular-nums">
                            {{ $fmt($m['operating_profit']['amount']) }}
                            <div class="text-[10px] font-sans font-normal opacity-80">({{ $m['operating_profit']['margin'] }}%)</div>
                        </td>
                    @endforeach
                    <td class="col-total py-3 px-3 text-right font-black tabular-nums">
                        {{ $fmt($total['operating_profit']['amount']) }}
                        <div class="text-[10px] font-sans font-normal opacity-80">({{ $total['operating_profit']['margin'] }}%)</div>
                    </td>
                </tr>

                {{-- 8. FLUXO DOS SÓCIOS & CAPEX --}}
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1.5 px-4 pl-8 font-sans">(-) Investimentos Imobilizados (CAPEX)</td>
                    @foreach ($months as $m)
                        <td class="py-1.5 px-2 text-right tabular-nums">-{{ $fmt($m['capex']['total']) }}</td>
                    @endforeach
                    <td class="col-total py-1.5 px-3 text-right tabular-nums">-{{ $fmt($total['capex']['total']) }}</td>
                </tr>
                <tr class="text-[11px] text-gray-400 dark:text-gray-500">
                    <td class="sticky-rubric py-1.5 px-4 pl-8 font-sans">(-) Retiradas Sócios (Pró-labore + Pessoal)</td>
                    @foreach ($months as $m)
                        <td class="py-1.5 px-2 text-right tabular-nums">-{{ $fmt($m['personal_expenses']['total']) }}</td>
                    @endforeach
                    <td class="col-total py-1.5 px-3 text-right tabular-nums">-{{ $fmt($total['personal_expenses']['total']) }}</td>
                </tr>

                {{-- 9. LUCRO LÍQUIDO RETIDO --}}
                <tr class="dre-row-profit font-extrabold text-sm">
                    <td class="sticky-rubric py-3 px-4 font-sans font-bold">
                        (=) LUCRO LÍQUIDO FINAL / RETIDO
                    </td>
                    @foreach ($months as $m)
                        <td class="py-3 px-2 text-right tabular-nums {{ $m['retained_profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $fmt($m['retained_profit']) }}
                        </td>
                    @endforeach
                    <td class="col-total py-3 px-3 text-right font-black tabular-nums {{ $total['retained_profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ $fmt($total['retained_profit']) }}
                    </td>
                </tr>

                {{-- 10. META BREAK-EVEN --}}
                <tr class="text-[11px] text-gray-400 dark:text-gray-500 italic">
                    <td class="sticky-rubric py-2 px-4 font-sans">🎯 Meta Break-Even (Ponto de Equilíbrio)</td>
                    @foreach ($months as $m)
                        <td class="py-2 px-2 text-right tabular-nums">{{ $fmt($m['break_even']['target']) }}</td>
                    @endforeach
                    <td class="col-total py-2 px-3 text-right font-medium tabular-nums">{{ $fmt($total['break_even']['target']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-filament-panels::page>