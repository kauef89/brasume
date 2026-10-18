<template>
    <div
        class="p-2.5 bg-surface-1 rounded-xl shadow-sm border border-surface-border cursor-grab active:cursor-grabbing flex flex-col gap-1.5 transition-all relative group select-none"
        :class="[
            isDayOff ? 'opacity-85 border-dashed border-surface-border bg-surface-2/60' : '',
            isOverworked && !isDayOff ? 'ring-2 ring-danger-500/60 bg-danger-500/5' : ''
        ]"
    >
        <!-- BOTÃO DE REMOÇÃO DIRETA -->
        <button
            type="button"
            @click.stop="$emit('remove')"
            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-danger-500 hover:bg-danger-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-md z-10 cursor-pointer"
            title="Remover da escala"
        >
            <i class="pi pi-times text-[9px] font-black"></i>
        </button>

        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-6 h-6 rounded-full bg-surface-3 flex items-center justify-center font-black text-[9px] text-surface-muted shrink-0">
                    {{ schedule.name ? schedule.name.substring(0, 2).toUpperCase() : 'CO' }}
                </div>
                <span class="font-bold text-xs text-surface-text truncate" :title="schedule.name">
                    {{ abbreviateName(schedule.name) }}
                </span>
            </div>

            <!-- TOGGLE DE DIÁRIA EXTRA: Visível apenas se for CLT E dias trabalhados > 5 -->
            <label
                v-if="canHaveExtraShift && !isDayOff"
                class="flex items-center gap-1 cursor-pointer shrink-0"
                title="Ativar Diária Extra (Gera Contas a Pagar)"
            >
                <input
                    type="checkbox"
                    v-model="schedule.is_extra_shift"
                    @change="$emit('change')"
                    class="sr-only peer"
                />
                <div class="w-5 h-3 bg-surface-3 rounded-full peer-checked:bg-warning-500 transition-colors relative after:content-[''] after:absolute after:w-2 after:h-2 after:bg-white after:rounded-full after:top-0.5 after:left-0.5 peer-checked:after:translate-x-2 after:transition-transform"></div>
                <i class="pi pi-dollar text-[9px]" :class="schedule.is_extra_shift ? 'text-warning-500' : 'text-surface-muted'"></i>
            </label>
        </div>

        <!-- AVISOS DE STATUS -->
        <div v-if="isDayOff" class="text-[9px] font-bold text-primary-500 flex items-center gap-1">
            <i class="pi pi-moon text-[8px]"></i> Folga Programada
        </div>
        <div v-else-if="isOverworked" class="text-[9px] font-black text-danger-500 flex items-center gap-1">
            <i class="pi pi-exclamation-triangle text-[8px]"></i> Excedeu 5x2 ({{ daysWorked }}/5 dias)
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { abbreviateName } from '@/Utils/formatters';

const props = defineProps({
    schedule: Object,
    daysWorked: Number,
    isDayOff: {
        type: Boolean,
        default: false
    }
});

defineEmits(['remove', 'change']);

// Verifica se o regime é CLT
const isClt = computed(() => {
    const type = String(props.schedule.employment_type || '').toLowerCase();
    return type.startsWith('clt');
});

// Apenas exibe o toggle se for CLT e já tiver passado de 5 dias de trabalho na semana
const canHaveExtraShift = computed(() => {
    return isClt.value && props.daysWorked > 5;
});

const isOverworked = computed(() => {
    return props.daysWorked > 5;
});
</script>
