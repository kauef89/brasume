<div class="space-y-6">
    @php
        // O Saldo atual é o 'balance' do primeiro item (mais recente)
        $currentBalance = $statement->first()['balance'] ?? 0;
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            Saldo Atual (Conta Corrente)
        </x-slot>

        <p class="text-4xl font-medium tracking-tight {{ $currentBalance >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
            R$ {{ number_format($currentBalance, 2, ',', '.') }}
        </p>
    </x-filament::section>

    <div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-ta-content overflow-x-auto">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                <thead class="divide-y divide-gray-200 dark:divide-white/5">
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Data do Lançamento</span>
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Descrição</span>
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-right sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Valor</span>
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-right sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Saldo Histórico</span>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-white/5 whitespace-nowrap">
                    @forelse($statement as $item)
                        <tr class="fi-ta-row hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="fi-ta-cell p-0">
                                <div class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                    {{ \Carbon\Carbon::parse($item['date'])->format('d/m/Y \à\s H:i') }}
                                </div>
                            </td>

                            <td class="fi-ta-cell p-0">
                                <div class="px-3 py-4 text-sm text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                    {{ $item['description'] }}
                                </div>
                            </td>

                            <td class="fi-ta-cell p-0 text-right">
                                <div class="px-3 py-4 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                    <x-filament::badge :color="$item['type'] === 'credit' ? 'success' : 'danger'">
                                        {{ $item['type'] === 'credit' ? '+' : '-' }} R$ {{ number_format($item['amount'], 2, ',', '.') }}
                                    </x-filament::badge>
                                </div>
                            </td>

                            <td class="fi-ta-cell p-0 text-right">
                                <div class="px-3 py-4 text-sm font-medium {{ $item['balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }} sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                    R$ {{ number_format($item['balance'], 2, ',', '.') }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <div class="flex items-center justify-center p-6 text-sm text-gray-500 dark:text-gray-400">
                                    Nenhum lançamento encontrado no extrato deste colaborador.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
