<template>
    <div class="flex flex-col w-full h-full overflow-hidden">

        <!-- CABEÇALHO DISCRETO E ALINHADO -->
        <div class="flex items-center gap-3 px-4 py-2.5 bg-surface-3/70 border-b border-surface-border text-[10px] font-bold text-surface-muted uppercase tracking-wider shrink-0 select-none">
            <div class="w-6 flex justify-center shrink-0">
                <div
                    @click="$emit('toggle-all', !isAllSelected)"
                    class="w-3.5 h-3.5 rounded-[4px] border flex items-center justify-center cursor-pointer transition-colors"
                    :class="isAllSelected ? 'bg-primary-500 border-primary-500 text-white' : 'border-surface-border bg-surface-1 hover:border-surface-400'"
                    title="Selecionar Todos"
                >
                    <i v-if="isAllSelected" class="pi pi-check text-[8px] font-black"></i>
                </div>
            </div>

            <div class="w-8 flex justify-center shrink-0">Tipo</div>
            <div class="w-14 shrink-0">Cód</div>
            <div class="flex-1 min-w-[160px]">Detalhes da Operação</div>
            <div class="w-24 shrink-0">Operador</div>
            <div class="w-28 shrink-0">Liquidação</div>
            <div class="w-14 shrink-0 text-center">Hora</div>
            <div class="w-24 shrink-0 text-right">Valor</div>
            <div class="w-16 flex justify-center shrink-0">Sefaz</div>
            <div class="w-8 shrink-0"></div>
        </div>

        <!-- CORPO DA TABELA -->
        <div class="flex-1 overflow-y-auto hide-scrollbar bg-surface-2">
            <div v-if="unifiedHistory.length === 0" class="h-full flex flex-col items-center justify-center text-center opacity-50">
                <i class="pi pi-receipt text-3xl text-surface-muted mb-2"></i>
                <p class="text-xs font-medium text-surface-muted">Nenhuma movimentação registrada no turno.</p>
            </div>

            <div v-else class="flex flex-col">
                <CashierTableRow
                    v-for="item in unifiedHistory"
                    :key="item._uniqueId"
                    :item="item"
                    :is-selected="selectedRows.has(item._uniqueId)"
                    @toggle-selection="$emit('toggle-selection', $event)"
                    @action="$emit('action', $event)"
                />
            </div>
            <div class="h-16 shrink-0 pointer-events-none w-full"></div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import CashierTableRow from './CashierTableRow.vue';

const props = defineProps({
    unifiedHistory: { type: Array, required: true },
    selectedRows: { type: Object, required: true }
});

defineEmits(['toggle-selection', 'toggle-all', 'action']);

const isAllSelected = computed(() => {
    return props.unifiedHistory.length > 0 && props.selectedRows.size === props.unifiedHistory.length;
});
</script>
