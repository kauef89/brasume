<x-filament-panels::page>

    {{-- SECÇÃO 1: STATUS DA CONEXÃO --}}
    <x-filament::section>
        <x-slot name="heading">
            Status da Comunicação
        </x-slot>
        <x-slot name="description">
            Monitorização em tempo real do motor de impressão instalado no seu computador.
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Versão da Bridge:</span>
                <div class="mt-1 flex items-center gap-2">
                    @if($bridgeVersion !== 'Nunca conectada')
                        <div class="h-3 w-3 rounded-full bg-success-500 animate-pulse"></div>
                    @else
                        <div class="h-3 w-3 rounded-full bg-danger-500"></div>
                    @endif
                    <p class="text-lg font-medium">{{ $bridgeVersion }}</p>
                </div>
            </div>

            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Última Conexão:</span>
                <p class="mt-1 text-lg font-medium">{{ $lastConnection }}</p>
            </div>

            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Token de Segurança:</span>
                <p class="mt-1 text-sm font-mono bg-gray-100 dark:bg-gray-800 p-2 rounded truncate text-gray-600 dark:text-gray-300" title="{{ $bridgeToken }}">
                    {{ $bridgeToken }}
                </p>
            </div>
        </div>
    </x-filament::section>

    {{-- SECÇÃO 2: AÇÕES E DOWNLOADS --}}
    <x-filament::section>
        <x-slot name="heading">
            Gerenciamento do Aplicativo
        </x-slot>
        <x-slot name="description">
            Baixe o motor de impressão para o seu sistema ou inicie a conexão caso já o tenha instalado.
        </x-slot>

        <div class="flex flex-col md:flex-row gap-4 mt-6 items-center">

            <x-filament::button
                tag="a"
                href="{{ $deepLink }}"
                color="success"
                icon="heroicon-o-signal"
                size="lg"
            >
                Ativar Impressora Automaticamente
            </x-filament::button>

            <div class="flex-1"></div>

            {{-- 🚀 RESOLUÇÃO DO ERRO DE MEMÓRIA: Downloads nativos via HTML5 sem processamento do Livewire --}}
            <x-filament::button
                tag="a"
                href="{{ $windowsDownloadUrl }}"
                download="BrasumePrint-Setup.exe"
                icon="heroicon-m-computer-desktop"
                color="info"
            >
                Baixar Windows (.exe)
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ $macDownloadUrl }}"
                download="BrasumePrint-Mac.zip"
                icon="heroicon-m-command-line"
                color="gray"
            >
                Baixar Mac (.zip)
            </x-filament::button>

            <x-filament::button
                wire:click="testPrint"
                icon="heroicon-o-printer"
                color="warning"
            >
                Imprimir Teste (USB)
            </x-filament::button>

        </div>
    </x-filament::section>

</x-filament-panels::page>
