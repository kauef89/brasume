<template>
    <div
        class="bg-surface-1 p-5 rounded-2xl border transition-all flex flex-col shadow-sm"
        :class="order.status === 'in_progress' ? 'border-primary-500' : 'border-surface-border'"
    >
        <div class="flex justify-between items-start mb-4">
            <span
                class="text-[10px] font-bold uppercase tracking-widest px-2 py-1 rounded"
                :class="order.status === 'in_progress' ? 'bg-primary-500/10 text-primary-600' : 'bg-surface-3 text-surface-muted'"
            >
                {{ order.status === 'in_progress' ? 'Em Andamento' : 'A Fazer' }}
            </span>
            <span class="text-xs font-bold text-surface-muted">
                Prazo: {{ formattedDueDate }}
            </span>
        </div>

        <h3 class="font-black text-lg text-surface-text leading-tight mb-1">
            {{ order.product?.name }}
        </h3>

        <div class="flex items-center gap-2 mb-6">
            <span class="text-sm text-surface-muted font-medium">Meta:</span>
            <span class="text-lg font-black text-amber-500">
                {{ order.planned_qty }} {{ order.product?.unit_of_measure }}
            </span>
        </div>

        <!-- Ações Rápidas -->
        <div class="mt-auto grid grid-cols-2 gap-2">
            <button
                v-if="order.status === 'pending'"
                @click="emit('updateStatus', { id: order.id, status: 'in_progress' })"
                class="bg-surface-3 hover:bg-surface-4 text-surface-text font-bold py-2 rounded-xl transition-colors outline-none border-none cursor-pointer"
            >
                Iniciar Preparo
            </button>

            <button
                v-if="order.status === 'in_progress'"
                @click="emit('finishOrder', order)"
                class="col-span-2 bg-green-500 hover:bg-green-600 text-white font-bold py-2 rounded-xl transition-colors shadow-md flex items-center justify-center gap-2 outline-none border-none cursor-pointer"
            >
                <i class="pi pi-check"></i> Concluir Lote
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['updateStatus', 'finishOrder']);

const formattedDueDate = computed(() => {
    if (!props.order?.due_date) return '';
    const [year, month, day] = props.order.due_date.split('T')[0].split('-');
    return `${day}/${month}/${year}`;
});
</script>
