@php
    // 1. Pegamos o Produto Pai (Ex: O Suco de Manga)
    $product = $this->getOwnerRecord();

    // 2. Usamos o nosso motor para descer as camadas e calcular cada cenário
    $mesa = $product ? \App\Services\Catalog\ProductCostingService::calculateBaseCost($product, 'table') : 0;
    $balcao = $product ? \App\Services\Catalog\ProductCostingService::calculateBaseCost($product, 'counter') : 0;
    $delivery = $product ? \App\Services\Catalog\ProductCostingService::calculateBaseCost($product, 'delivery') : 0;

    $fmt = fn($val) => 'R$ ' . number_format($val, 2, ',', '.');
@endphp

{{-- Rodapé: Usando Flexbox para centralizar os cards --}}
<div class="px-6 py-5 border-t border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-gray-900/50 flex flex-wrap justify-center gap-6">

    <div class="flex items-center gap-4 px-5 py-3 rounded-2xl bg-primary-500/10  dark:bg-primary-500/10">
        <div class="flex items-center justify-center p-3 rounded-full bg-primary-500/20 dark:bg-primary-500/20">
            <x-heroicon-o-building-storefront class="w-6 h-6 " />
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wider ">Mesa</p>
            <p class="text-xl font-medium ">{{ $fmt($mesa) }}</p>
        </div>
    </div>

    <div class="flex items-center gap-4 px-5 py-3 rounded-2xl bg-primary-500/10  dark:bg-primary-500/10">
        <div class="flex items-center justify-center p-3 rounded-full bg-primary-500/20 dark:bg-primary-500/20">
            <x-heroicon-o-shopping-bag class="w-6 h-6 " />
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wider ">Balcão</p>
            <p class="text-xl font-medium ">{{ $fmt($balcao) }}</p>
        </div>
    </div>

    <div class="flex items-center gap-4 px-5 py-3 rounded-2xl bg-primary-500/10  dark:bg-primary-500/10">
        <div class="flex items-center justify-center p-3 rounded-full bg-primary-500/20 dark:bg-primary-500/20">
            <x-heroicon-o-truck class="w-6 h-6 " />
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wider ">Delivery</p>
            <p class="text-xl font-medium ">{{ $fmt($delivery) }}</p>
        </div>
    </div>

</div>
