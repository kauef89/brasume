<div wire:poll.60s x-data="{ open: false }" class="relative">

    <button
        x-on:click="open = ! open"
        @click.outside="open = false"
        class="relative flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 dark:hover:bg-white/5 transition-colors focus:outline-none"
    >
        <x-heroicon-o-bell class="w-5 h-5 text-gray-500 dark:text-gray-400" />

        @if($totalAlerts > 0)
            <span class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 text-[9px] font-bold text-white bg-danger-600 rounded-full ring-2 ring-white dark:ring-gray-900">
                {{ $totalAlerts }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        x-cloak
        class="absolute right-0 z-50 w-80 mt-2 bg-white dark:bg-gray-900 rounded-xl shadow-lg ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden"
    >
        <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10 flex justify-between items-center">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Avisos do Sistema</h3>
            @if($totalAlerts > 0)
                <span class="px-2 py-0.5 text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-full">
                    {{ $totalAlerts }} pendências
                </span>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse($alerts as $alert)
                <a href="{{ $alert['url'] }}" class="flex items-start gap-3 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors border-b border-gray-100 dark:border-white/5 last:border-0 group">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center {{ $alert['bg'] }}">
                        <x-dynamic-component :component="$alert['icon']" class="w-4 h-4 {{ $alert['color'] }}" />
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                            {{ $alert['title'] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $alert['message'] }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="p-6 text-center">
                    <x-heroicon-o-check-circle class="w-8 h-8 mx-auto text-success-500 mb-2 opacity-50" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tudo limpo! Nenhuma pendência no ERP.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
