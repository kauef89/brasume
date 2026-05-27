@php
// Pegamos a chave da nota que está dentro do componente da tabela ($this)
$key = $this->invoiceKey ?? 'none';

// Contagem real no banco de dados
$stockCount = \App\Models\ProductCostHistory::where('nfe_key', $key)->count();
$personalCount = \App\Models\PersonalPurchase::where('nfe_key', $key)->count();
@endphp

<div class="fi-ta-tabs-hook">
    <style>
        .fi-ta-tabs-hook .fi-tabs {
            background: transparent !important;
            margin-right: 1rem;
            padding: 10px;
        }

        .fi-ta-tabs-hook .fi-tabs-item {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .fi-ta-actions {
            align-items: center !important;
        }
    </style>

    <x-filament::tabs label="Tabelas de Processamento">
        <x-filament::tabs.item
            alpine-active="activeTab === 'stock'"
            x-on:click="activeTab = 'stock'"
            icon="heroicon-o-archive-box">
            Estoque ({{ $stockCount }})
        </x-filament::tabs.item>

        <x-filament::tabs.item
            alpine-active="activeTab === 'personal'"
            x-on:click="activeTab = 'personal'"
            icon="heroicon-o-shopping-bag">
            Pessoal ({{ $personalCount }})
        </x-filament::tabs.item>
    </x-filament::tabs>
</div>