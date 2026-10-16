<template>
    <div class="bg-surface-1 border rounded-2xl p-4 flex flex-col gap-3 transition-colors shadow-sm"
         :class="statusColorClass.border">

        <div class="flex justify-between items-start">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center shadow-sm" :class="statusColorClass.bg">
                    <i class="pi pi-print text-lg"></i>
                </div>
                <div>
                    <h3 class="font-black text-surface-text leading-tight">{{ printer.name }}</h3>
                    <span class="text-[10px] font-medium uppercase tracking-widest text-surface-muted">{{ printer.is_cashier ? 'Caixa Principal' : 'Cozinha/Produção' }}</span>
                </div>
            </div>
            <div class="flex flex-col items-end">
                <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md border" :class="statusColorClass.badge">
                    {{ statusText }}
                </span>
            </div>
        </div>

        <div v-if="printer.fallback_printer_id" class="bg-warning-500/10 border border-warning-500/20 p-2 rounded-lg flex items-center gap-2 text-warning-700 dark:text-warning-400 text-xs font-medium">
            <i class="pi pi-arrow-right-arrow-left"></i>
            Desviando para outra impressora
        </div>

        <div v-if="printer.last_error" class="text-xs text-danger-500 italic line-clamp-1" :title="printer.last_error">
            Último erro: {{ printer.last_error }}
        </div>

        <div class="mt-auto pt-2 border-t border-surface-border">
            <button @click="$emit('configure-fallback', printer)" class="w-full py-2 bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text rounded-lg text-xs font-black uppercase tracking-widest transition-colors outline-none cursor-pointer">
                Desviar Rota (Contingência)
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ printer: Object });
defineEmits(['configure-fallback']);

const statusColorClass = computed(() => {
    if (props.printer.fallback_printer_id) return { border: 'border-warning-500/50', bg: 'bg-warning-500/10 text-warning-600', badge: 'bg-warning-500/10 text-warning-600 border-warning-500/20' };
    if (props.printer.last_status === 'failed') return { border: 'border-danger-500/50', bg: 'bg-danger-500/10 text-danger-600', badge: 'bg-danger-500/10 text-danger-600 border-danger-500/20' };
    return { border: 'border-surface-border', bg: 'bg-success-500/10 text-success-600', badge: 'bg-success-500/10 text-success-600 border-success-500/20' };
});

const statusText = computed(() => {
    if (props.printer.fallback_printer_id) return 'Em Contingência';
    if (props.printer.last_status === 'failed') return 'Com Erro';
    return 'Online';
});
</script>
