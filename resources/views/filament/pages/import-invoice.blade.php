<x-filament-panels::page>
    {{-- Renderiza o Formulário Principal (Cabeçalho da Nota e Itens Pendentes) --}}
    {{ $this->form }}

    {{-- 🚀 Renderiza a Action Nativa do Filament (Resolve a modalidade de aviso sozinha) --}}
    <div class="flex justify-end mt-4">
        {{ $this->finishImportAction }}
    </div>

    {{-- Divisor Elegante --}}
    <x-filament::section class="mt-8">
        <x-slot name="heading">
            Itens Processados
        </x-slot>
        <x-slot name="description">
            Confira abaixo o histórico de tudo o que já foi direcionado para o Estoque ou Uso Pessoal a partir desta nota.
        </x-slot>

        {{-- Abas (Tabs) do Alpine.js --}}
        <div x-data="{ activeTab: 'stock' }">
            {{-- Navegação das Abas --}}
            <x-filament::tabs class="mb-4">
                <x-filament::tabs.item
                    alpine-active="activeTab === 'stock'"
                    x-on:click="activeTab = 'stock'"
                    icon="heroicon-o-archive-box"
                    badge="{{ $stockCount ?? 0 }}"
                >
                    Estoque Comercial
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    alpine-active="activeTab === 'personal'"
                    x-on:click="activeTab = 'personal'"
                    icon="heroicon-o-user"
                    badge="{{ $personalCount ?? 0 }}"
                >
                    Uso Pessoal
                </x-filament::tabs.item>
            </x-filament::tabs>

            {{-- Conteúdo das Abas (Componentes Livewire) --}}
            <div x-show="activeTab === 'stock'" x-cloak>
                @livewire(\App\Livewire\ProcessedStockTable::class, ['invoiceKey' => $data['invoice_key'] ?? 'none'])
            </div>

            <div x-show="activeTab === 'personal'" x-cloak>
                @livewire(\App\Livewire\ProcessedPersonalTable::class, ['invoiceKey' => $data['invoice_key'] ?? 'none'])
            </div>
        </div>
    </x-filament::section>

    {{-- Registra o Modal de Aviso --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
