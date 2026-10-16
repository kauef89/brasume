<template>
    <div class="bg-surface-1 p-4 rounded-xl border-l-4 border-y border-r border-surface-border shadow-sm transition-colors flex flex-col"
         :class="[timerColorClass, (isGlobalView || readonly) ? 'cursor-default' : 'cursor-grab active:cursor-grabbing hover:border-r-primary-400']">

        <div class="flex justify-between items-start mb-2 gap-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-surface-muted flex gap-2 items-center truncate">
                <span class="truncate" :title="formattedTitle">{{ formattedTitle }}</span>

                <span v-if="isGlobalView && kitchenName" class="bg-primary-500/10 text-primary-600 px-1.5 py-0.5 rounded shrink-0">
                    {{ kitchenName }}
                </span>
            </span>
            <span class="text-[10px] bg-surface-2 text-surface-muted px-2 py-0.5 rounded-md font-medium border border-surface-border/50 shrink-0">
                #{{ $formatNumber(item.order?.id) }}
            </span>
        </div>

        <h4 class="font-black text-surface-text text-lg leading-tight">{{ item.quantity }}x {{ item.product_name }}</h4>

        <div v-if="item.notes || parsedModifiersLines.length > 0" class="mt-2 text-xs font-medium text-warning-700 dark:text-warning-400 bg-warning-500/10 p-2 rounded-lg border border-warning-500/20 flex flex-col gap-1">
            <div v-if="item.notes"><i class="pi fa-exclamation-triangle text-[10px]"></i> Obs: {{ item.notes }}</div>
            <div v-for="(line, idx) in parsedModifiersLines" :key="idx" class="flex items-center gap-1">
                <i class="pi pi-info-circle text-[10px]"></i> {{ line }}
            </div>
        </div>

        <div class="flex items-center mt-4 pt-3 border-t border-surface-border/50"
             :class="item.production_status === 'pending' ? 'justify-end' : 'justify-between'">

            <span v-if="item.production_status !== 'pending'" class="text-xs font-medium text-surface-muted flex items-center gap-1" title="Tempo nesta fase">
                <i class="pi pi-hourglass text-[10px]"></i> {{ phaseTime }} min
            </span>

            <span class="text-xs font-black flex items-center gap-1" :class="timerTextColorClass" title="Tempo total do pedido">
                <i class="pi pi-clock text-[10px]"></i> {{ totalTime }} min
            </span>
        </div>

        <!-- 🚀 Botões de ação ocultos na Visão Global ou no modo Readonly -->
        <div v-if="!isGlobalView && !readonly" class="flex items-center gap-2 mt-3 pt-3 border-t border-surface-border/50">
            <button @click.stop="$emit('print', item)" class="flex-1 py-2 bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text rounded-lg text-[10px] font-black uppercase tracking-widest transition-colors outline-none cursor-pointer flex items-center justify-center gap-1.5 shadow-sm border border-surface-border">
                <i class="pi pi-print"></i> Imprimir
            </button>
            <button @click.stop="$emit('loss', item)" class="flex-1 py-2 bg-orange-500/10 hover:bg-orange-500/20 text-orange-600 dark:text-orange-400 rounded-lg text-[10px] font-black uppercase tracking-widest transition-colors outline-none cursor-pointer flex items-center justify-center gap-1.5 shadow-sm border border-orange-500/20">
                <i class="pi pi-arrow-down-right"></i> Perda
            </button>
            <button @click.stop="$emit('cancel', item)" class="flex-1 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 rounded-lg text-[10px] font-black uppercase tracking-widest transition-colors outline-none cursor-pointer flex items-center justify-center gap-1.5 shadow-sm border border-red-500/20">
                <i class="pi fa-circle-xmark"></i> Cancelar
            </button>
        </div>
    </div>
</template>

<script setup>
import { toRefs, computed } from "vue";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useKdsItemTimer } from "@/Pages/Kds/Composables/useKdsItemTimer";

const props = defineProps({
    item: Object,
    isGlobalView: Boolean,
    kitchenName: String,
    readonly: { type: Boolean, default: false },
    currentTime: Date
});

defineEmits(['print', 'loss', 'cancel']);

const { item, currentTime } = toRefs(props);
const { getOrderTitle, formatModifiersList } = useOrderFormatting();
const formattedTitle = computed(() => getOrderTitle(item.value.order));
const parsedModifiersLines = computed(() => formatModifiersList(item.value));
const { totalTime, phaseTime, timerColorClass, timerTextColorClass } = useKdsItemTimer(item, currentTime);
</script>
