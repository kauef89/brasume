<template>
    <div class="bg-surface-1 border border-surface-border rounded-2xl overflow-hidden shadow-sm">

        <!-- Cabeçalho do Fornecedor -->
        <div class="bg-surface-2 p-4 border-b border-surface-border flex justify-between items-center">
            <h3 class="font-black text-lg text-primary-smart flex items-center">
                <i class="pi pi-truck mr-2"></i> {{ supplier.supplier_name }}
            </h3>
            <span class="text-sm font-bold text-surface-muted bg-surface-3 px-3 py-1 rounded-full">
                Total Est.: {{ $formatCurrency(supplier.estimated_total) }}
            </span>
        </div>

        <!-- Lista de Itens -->
        <div class="divide-y divide-surface-border/50">
            <div v-for="item in supplier.items" :key="item.id || item.shopping_list_id" class="p-4 flex items-center justify-between hover:bg-surface-2/50 transition-colors">

                <div class="flex-1 min-w-0 pr-4">
                    <h4 class="font-bold text-surface-text text-sm truncate">{{ item.product_name }}</h4>
                    <p class="text-xs text-surface-muted mt-0.5 flex flex-wrap items-center gap-2">
                        <span>Custo Un.: {{ $formatCurrency(item.last_cost || 0) }}</span>
                        <span v-if="item.is_pack" class="bg-surface-3 px-2 py-0.5 rounded text-[10px] font-bold text-primary-smart">
                            {{ item.conversion_factor }} {{ item.uom }} por {{ item.purchase_uom }}
                        </span>
                        <span v-if="item.urgency === 'critical' || item.urgency_level === 'critical'" class="text-danger-500 font-black uppercase text-[10px] flex items-center gap-1">
                            <i class="pi pi-exclamation-triangle"></i> Ruptura
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <!-- Se for fardo/caixa, exibe stepper em caixas -->
                    <div v-if="item.is_pack" class="flex flex-col items-end">
                        <div class="flex items-center gap-1.5 bg-surface-2 px-2.5 py-1.5 rounded-xl border border-surface-border/50">
                            <input
                                type="number"
                                v-model.number="item.suggested_packs"
                                @input="updateUnitsFromPacks(item)"
                                class="w-14 text-center bg-transparent border-none outline-none font-black text-sm text-surface-text"
                                min="1"
                            >
                            <span class="text-[10px] font-black text-surface-muted uppercase">{{ item.purchase_uom }}</span>
                        </div>
                        <span class="text-[10px] text-surface-muted mt-0.5">({{ item.suggested_qty }} {{ item.uom }})</span>
                    </div>

                    <!-- Se for unidade simples (kg, l, un avulsa) -->
                    <div v-else class="flex items-center gap-1.5 bg-surface-2 px-2.5 py-1.5 rounded-xl border border-surface-border/50">
                        <input
                            type="number"
                            v-model.number="item.suggested_qty"
                            class="w-16 text-center bg-transparent border-none outline-none font-black text-sm text-surface-text"
                            step="0.1"
                            min="0.1"
                        >
                        <span class="text-[10px] font-black text-surface-muted uppercase">{{ item.uom || 'UN' }}</span>
                    </div>

                    <button
                        @click="emit('removeItem', item.shopping_list_id || item.id)"
                        class="w-8 h-8 rounded-full bg-danger-500/10 text-danger-500 hover:bg-danger-500 hover:text-white transition-colors flex items-center justify-center outline-none cursor-pointer border-none"
                        title="Remover item da lista"
                    >
                        <i class="pi pi-trash text-xs"></i>
                    </button>
                </div>

            </div>
        </div>

        <!-- Rodapé de Ação -->
        <div class="p-4 bg-surface-2/40 border-t border-surface-border/50 flex justify-end">
            <button
                @click="emit('approveOrder', supplierId, supplier.items)"
                class="bg-primary-500 hover:bg-primary-600 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-md flex items-center gap-2 outline-none cursor-pointer border-none text-sm active:scale-95"
            >
                <i class="pi pi-check-circle"></i> Aprovar Pedido
            </button>
        </div>

    </div>
</template>

<script setup>
const props = defineProps({
    supplierId: {
        type: [String, Number],
        required: true
    },
    supplier: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['removeItem', 'approveOrder']);

const updateUnitsFromPacks = (item) => {
    const packs = Number(item.suggested_packs || 0);
    const factor = Number(item.conversion_factor || 1);
    item.suggested_qty = Math.round(packs * factor * 1000) / 1000;
};

const formatMoney = (value) => {
    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
