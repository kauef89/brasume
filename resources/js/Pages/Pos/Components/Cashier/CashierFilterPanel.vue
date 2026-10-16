<template>
    <Popover ref="popoverRef" class="w-64 p-0 overflow-hidden rounded-2xl border-surface-border shadow-2xl bg-surface-1">
        <div class="p-4 border-b border-surface-border bg-surface-2 flex items-center justify-between">
            <span class="font-black text-sm uppercase tracking-widest text-surface-muted">Filtros do Extrato</span>
        </div>

        <!-- 🚀 Filtros de Tipo de Registro (O que mostrar) -->
        <div class="p-4 flex flex-col gap-4 bg-surface-1 border-b border-surface-border/50">
            <span class="micro-label mb-1">O que exibir na lista</span>

            <label class="flex items-center gap-3 cursor-pointer group" @click.prevent="$emit('update:showOrders', !showOrders)">
                <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors" :class="showOrders ? 'bg-primary-500 border-primary-500 text-white' : 'border-surface-border bg-surface-2 group-hover:border-primary-400'">
                    <i v-if="showOrders" class="pi pi-check text-[10px] font-medium"></i>
                </div>
                <div class="w-8 h-8 rounded-lg bg-surface-2 text-surface-muted flex items-center justify-center group-hover:bg-primary-500/10 group-hover:text-primary-500 transition-colors"><i class="pi fa-receipt text-sm"></i></div>
                <span class="font-medium text-sm text-surface-text">Pedidos</span>
            </label>

            <label class="flex items-center gap-3 cursor-pointer group" @click.prevent="$emit('update:showTransactions', !showTransactions)">
                <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors" :class="showTransactions ? 'bg-purple-500 border-purple-500 text-white' : 'border-surface-border bg-surface-2 group-hover:border-purple-400'">
                    <i v-if="showTransactions" class="pi pi-check text-[10px] font-medium"></i>
                </div>
                <div class="w-8 h-8 rounded-lg bg-surface-2 text-surface-muted flex items-center justify-center group-hover:bg-purple-500/10 group-hover:text-purple-500 transition-colors"><i class="pi pi-sort-alt text-sm"></i></div>
                <span class="font-medium text-sm text-surface-text">Movimentações</span>
            </label>
        </div>

        <!-- Filtros de Tipo de Pedido (Mesa/Balcão/Delivery) -->
        <div class="p-4 flex flex-col gap-4 bg-surface-1" :class="{'opacity-50 grayscale pointer-events-none': !showOrders}">
            <span class="micro-label mb-1">Tipos de Pedido</span>

            <label class="flex items-center gap-3 cursor-pointer group" @click.prevent="$emit('toggle-type', 'table')">
                <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors" :class="activeTypes.includes('table') ? 'bg-primary-500 border-primary-500 text-white' : 'border-surface-border bg-surface-2 group-hover:border-primary-400'">
                    <i v-if="activeTypes.includes('table')" class="pi pi-check text-[10px] font-medium"></i>
                </div>
                <div class="w-8 h-8 rounded-lg bg-primary-500/10 text-primary-500 flex items-center justify-center"><i class="pi pi-table text-sm"></i></div>
                <span class="font-medium text-sm text-surface-text">Mesas</span>
            </label>

            <label class="flex items-center gap-3 cursor-pointer group" @click.prevent="$emit('toggle-type', 'counter')">
                <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors" :class="activeTypes.includes('counter') ? 'bg-purple-500 border-purple-500 text-white' : 'border-surface-border bg-surface-2 group-hover:border-purple-400'">
                    <i v-if="activeTypes.includes('counter')" class="pi pi-check text-[10px] font-medium"></i>
                </div>
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center"><i class="pi pi-shopping-bag text-sm"></i></div>
                <span class="font-medium text-sm text-surface-text">Balcão</span>
            </label>

            <label class="flex items-center gap-3 cursor-pointer group" @click.prevent="$emit('toggle-type', 'delivery')">
                <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors" :class="activeTypes.includes('delivery') ? 'bg-red-500 border-red-500 text-white' : 'border-surface-border bg-surface-2 group-hover:border-red-400'">
                    <i v-if="activeTypes.includes('delivery')" class="pi pi-check text-[10px] font-medium"></i>
                </div>
                <div class="w-8 h-8 rounded-lg bg-red-500/10 text-red-500 flex items-center justify-center"><i class="pi pi-motorcycle text-sm"></i></div>
                <span class="font-medium text-sm text-surface-text">Delivery</span>
            </label>
        </div>
    </Popover>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    activeTypes: { type: Array, required: true },
    showOrders: { type: Boolean, required: true },
    showTransactions: { type: Boolean, required: true }
});
defineEmits(['toggle-type', 'update:showOrders', 'update:showTransactions']);

const popoverRef = ref(null);

const toggle = (event) => {
    popoverRef.value.toggle(event);
};

defineExpose({ toggle });
</script>
