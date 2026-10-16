<x-filament-panels::page>
    <div class="space-y-6">

        {{-- CARDS DE OPÇÕES DE IMPORTAÇÃO --}}
        <div class="flex row gap-4 p-8">

            {{-- 1. FATURAMENTO CONSOLIDADO --}}
            <div class="flex flex-col justify-between items-center text-center p-8 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <div class="flex flex-col items-center">
                    <div class="flex items-center justify-center w-12 h-12 mb-4 rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-presentation-chart-line" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Faturamento Diário Consolidado</h3>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        Indicado para empresas sem dados fiscais ou registros detalhados. Importa apenas a data e o faturamento bruto diário.
                    </p>
                </div>
                <div class="mt-8 w-full">
                    <x-filament::button
                        wire:click="mountAction('importConsolidated')"
                        color="primary"
                        class="w-full">
                        Iniciar importação
                    </x-filament::button>
                </div>
            </div>

            {{-- 2. HISTÓRICO DE PEDIDOS (V1) --}}
            <div class="flex flex-col justify-between items-center text-center p-8 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <div class="flex flex-col items-center">
                    <div class="flex items-center justify-center w-12 h-12 mb-4 rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-shopping-cart" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Histórico de Pedidos Detalhados</h3>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        Lê relatórios de PDV antigos via CSV. Agrupa itens por pedido/comanda, vincula clientes e mapeia taxas de entrega.
                    </p>
                </div>
                <div class="mt-8 w-full">
                    <x-filament::button
                        wire:click="mountAction('importV1Orders')"
                        color="primary"
                        class="w-full">
                        Iniciar importação
                    </x-filament::button>
                </div>
            </div>

            {{-- 3. DOCUMENTOS FISCAIS (V2) --}}
            <div class="flex flex-col justify-between items-center text-center p-8 bg-white rounded-xl border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <div class="flex flex-col items-center">
                    <div class="flex items-center justify-center w-12 h-12 mb-4 rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-document-check" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Documentos Fiscais Oficiais</h3>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        Processa lotes compactados de notas fiscais oficiais (XML) e constrói a sua base de dados histórica a partir da Sefaz.
                    </p>
                </div>
                <div class="mt-8 w-full">
                    {{-- 🚀 CORRIGIDO AQUI: import_v2_xml --}}
                    <x-filament::button
                        wire:click="mountAction('importV2Xml')"
                        color="primary"
                        class="w-full">
                        Iniciar importação
                    </x-filament::button>
                </div>
            </div>

        </div>

        {{-- HISTÓRICO DE IMPORTAÇÕES (TABELA) --}}
        <div class="pt-8">
            <div class="p-4 bg-gray-50 rounded-t-xl border-t border-x border-gray-200 dark:bg-gray-800/50 dark:border-gray-700">
                <h2 class="text-base font-medium text-gray-900 dark:text-white">Histórico de Lotes Processados</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Visualize todas as cargas injetadas no tenant e desfaça operações se necessário.</p>
            </div>
            {{ $this->table }}
        </div>

    </div>

    {{-- Renderizador de modais nativo do Filament Page --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
