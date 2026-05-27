{{-- Este arquivo apenas envelopa as tabelas e gerencia o estado da aba ativa --}}
<div x-data="{ activeTab: 'stock' }" class="mt-8 pt-4 border-t border-gray-200 dark:border-white/10" wire:ignore>

    {{-- Tabela de Estoque --}}
    <div x-show="activeTab === 'stock'">
        @livewire('processed-stock-table', ['invoiceKey' => $this->data['invoice_key'] ?? 'none'], key('stock-table'))
    </div>

    {{-- Tabela de Despesas --}}
    <div x-show="activeTab === 'personal'" x-cloak>
        @livewire('processed-personal-table', ['invoiceKey' => $this->data['invoice_key'] ?? 'none'], key('personal-table'))
    </div>

</div>