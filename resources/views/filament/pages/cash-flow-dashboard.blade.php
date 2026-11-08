<x-filament-panels::page>
    <div class="flex flex-col gap-6">

        {{-- BARRA DE CONTROLE DA JANELA --}}
        <div class="flex items-center justify-between p-2 bg-white dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm">
            <div class="flex items-center gap-2 px-3">
                <x-heroicon-m-arrow-trending-up class="w-5 h-5 text-success-500" />
                <span class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Projeção de Liquidez</span>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Janela:</span>
                <div class="flex rounded-lg bg-gray-100 dark:bg-gray-950 p-1 ring-1 ring-gray-950/5 dark:ring-white/5">
                    <button wire:click="setProjectionDays(30)" class="px-4 py-1.5 text-xs font-semibold rounded-md transition {{ $projectionDays === 30 ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">D+30 Dias</button>
                    <button wire:click="setProjectionDays(60)" class="px-4 py-1.5 text-xs font-semibold rounded-md transition {{ $projectionDays === 60 ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white' }}">D+60 Dias</button>
                </div>
            </div>
        </div>

        {{-- 5 CARDS COM ÍCONES --}}
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

        {{-- AGENDA DIÁRIA DE LIQUIDEZ --}}
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

    </div>
</x-filament-panels::page>
