<template>
    <div class="flex items-center gap-4 px-6 py-2 bg-surface-3 border-b border-surface-border hover:bg-primary-500/10 transition-colors group"
         :class="{'bg-primary-500/5': isSelected}">

        <div class="w-8 shrink-0 flex items-center justify-center">
            <Checkbox :modelValue="isSelected" @update:modelValue="$emit('toggle-select', job.id)" :binary="true" />
        </div>

        <div class="w-16 shrink-0 text-xs font-medium text-surface-text flex items-center">
            {{ job.created_at }}
        </div>

        <div class="w-48 shrink-0 font-medium text-sm text-surface-text truncate flex items-center">
            {{ job.printer_name }}
        </div>

        <div class="flex-1 min-w-[150px] font-medium text-sm text-surface-text truncate flex items-center">
            {{ jobTypeTranslate(job.job_type) }}
        </div>

        <div class="w-24 shrink-0 text-center text-sm font-black text-surface-text flex items-center justify-center">
            {{ job.order_id ? '#' + job.order_id : '-' }}
        </div>

        <div class="w-28 shrink-0 flex items-center justify-center">
            <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-md border" :class="statusColorClass">
                {{ statusText }}
            </span>
        </div>

        <div class="w-20 shrink-0 flex items-center justify-center">
            <button @click="$emit('retry', job.id)" class="w-8 h-8 rounded-lg bg-surface-2 border border-transparent hover:border-surface-border text-surface-muted hover:text-primary-smart hover:bg-primary-500/10 transition-colors flex items-center justify-center outline-none cursor-pointer" title="Reimprimir">
                <i class="pi pi-refresh text-xs font-medium"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import Checkbox from 'primevue/checkbox';

const props = defineProps({ job: Object, isSelected: Boolean });
defineEmits(['toggle-select', 'retry']);

const jobTypeTranslate = (type) => {
    const map = {
        'kitchen_ticket': 'Comanda de Produção',
        'customer_receipt': 'Recibo do Cliente',
        'conference_bill': 'Conferência de Mesa',
        'delivery_dispatch': 'Via de Motoboy',
        'freelancer_receipt': 'Vale de Freelancer'
    };
    return map[type] || type;
};

const statusColorClass = computed(() => {
    if (props.job.status === 'success') return 'bg-success-500/10 text-success-600 border-success-500/20';
    if (props.job.status === 'failed') return 'bg-danger-500/10 text-danger-600 border-danger-500/20';
    return 'bg-warning-500/10 text-warning-600 border-warning-500/20';
});

const statusText = computed(() => {
    if (props.job.status === 'success') return 'Sucesso';
    if (props.job.status === 'failed') return 'Falhou';
    return 'Pendente';
});
</script>
