<template>
    <div
        class="bg-surface-1 p-5 rounded-[1.5rem] border-2 border-surface-border shadow-sm flex flex-col gap-3 transition-all duration-300 select-none"
        :class="[timerColorClass, isCompleting ? 'scale-95 opacity-50' : '']"
    >
        <!-- CABEÇALHO: MESA / COMANDA & NÚMERO DO PEDIDO -->
        <div class="flex justify-between items-start gap-2">
            <div class="flex flex-col min-w-0">
                <span class="text-[11px] font-black uppercase tracking-widest text-surface-muted truncate">
                    {{ formattedTitle }}
                </span>
                <h4 class="font-black text-surface-text text-xl md:text-2xl leading-tight mt-1">
                    <span class="text-primary-500">{{ item.quantity }} x</span> {{ item.product_name }}
                </h4>
            </div>
            <span class="text-[10px] bg-surface-2 text-surface-muted px-2 py-1 rounded-md font-medium border border-surface-border/50 shrink-0 shadow-inner">
                #{{ $formatNumber(item.order?.id) }}
            </span>
        </div>

        <!-- 🚀 BLOCO DE MODIFICADORES (ADICIONAIS / INGREDIENTES COM QUANTIDADE) -->
        <div v-if="parsedModifiers.length > 0" class="flex flex-wrap gap-1.5 mt-0.5">
            <div
                v-for="mod in parsedModifiers"
                :key="mod.id"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-surface-2 border border-surface-border text-surface-text shadow-xs"
            >
                <!-- Badge de Quantidade em Destaque -->
                <span
                    v-if="mod.qty > 1"
                    class="px-1.5 py-0.5 rounded-md bg-primary-500 text-white font-black text-[10px] leading-none tracking-tight shrink-0"
                >
                    {{ mod.qty }}x
                </span>
                <i v-else class="pi pi-plus text-[9px] text-primary-500 shrink-0"></i>

                <span class="truncate leading-tight">{{ mod.name }}</span>
            </div>
        </div>

        <!-- ⚠️ BLOCO DE OBSERVAÇÕES / INSTRUÇÕES ESPECIAIS -->
        <div
            v-if="item.notes"
            class="flex items-start gap-2 text-xs font-semibold text-warning-600 dark:text-warning-400 bg-warning-500/10 px-3 py-2 rounded-xl border border-warning-500/20 mt-0.5"
        >
            <i class="pi pi-exclamation-triangle text-xs mt-0.5 shrink-0"></i>
            <span class="leading-tight">Obs: {{ item.notes }}</span>
        </div>

        <!-- TEMPO EM FILA E AÇÕES RÁPIDAS -->
        <div class="flex items-center justify-between mt-2 pt-1">
            <span class="text-sm font-black flex items-center gap-1" :class="timerTextColorClass" title="Tempo na fila">
                <i class="pi pi-clock text-[11px]"></i> {{ totalTime }} min
            </span>

            <div class="flex gap-2">
                <button
                    @click.stop="$emit('print', item)"
                    type="button"
                    class="w-10 h-10 bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text rounded-full flex items-center justify-center transition-colors outline-none border border-surface-border shadow-sm cursor-pointer"
                    title="Imprimir Via do Bar"
                >
                    <i class="pi pi-print text-sm"></i>
                </button>
                <button
                    @click.stop="$emit('loss', item)"
                    type="button"
                    class="w-10 h-10 bg-orange-500/10 hover:bg-orange-500/20 text-orange-600 dark:text-orange-400 rounded-full flex items-center justify-center transition-colors outline-none border border-orange-500/20 shadow-sm cursor-pointer"
                    title="Registrar Perda"
                >
                    <i class="pi pi-arrow-down-right text-sm"></i>
                </button>
                <button
                    @click.stop="$emit('cancel', item)"
                    type="button"
                    class="w-10 h-10 bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center transition-colors outline-none border border-red-500/20 shadow-sm cursor-pointer"
                    title="Cancelar Item"
                >
                    <i class="pi pi-times-circle text-sm"></i>
                </button>
            </div>
        </div>

        <!-- SLIDE TO CONFIRM -->
        <div class="mt-4 pt-4 border-t border-surface-border/50">
            <SlideToConfirm
                text="Entregar"
                success-text="Entregue!"
                :is-loading="isCompleting"
                :is-success="isSuccess"
                @confirm="handleConfirm"
                class="!h-14"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, toRefs, computed } from "vue";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useKdsItemTimer } from "@/Pages/Kds/Composables/useKdsItemTimer";
import SlideToConfirm from "@/Components/UI/SlideToConfirm.vue";

const props = defineProps({
    item: { type: Object, required: true },
    currentTime: { type: Date, default: () => new Date() }
});

const emit = defineEmits(['print', 'loss', 'cancel', 'complete']);

const { item, currentTime } = toRefs(props);
const { getOrderTitle } = useOrderFormatting();
const formattedTitle = computed(() => getOrderTitle(item.value?.order));
const { totalTime, timerColorClass, timerTextColorClass } = useKdsItemTimer(item, currentTime);

const isCompleting = ref(false);
const isSuccess = ref(false);

// 🚀 PARSER INTELIGENTE DE MODIFICADORES (Suporta `modifiers`, `structured_modifiers` e trata quantidades)
const parsedModifiers = computed(() => {
    const raw = item.value?.modifiers || item.value?.structured_modifiers || item.value?.structuredModifiers || [];
    if (!Array.isArray(raw)) return [];

    const parentQty = Number(item.value?.quantity || item.value?.qty || 1);

    return raw
        .map(m => {
            let totalModQty = Number(m.quantity ?? m.qty ?? 1);
            let unitQty = totalModQty;
            
            // Isola a quantidade unitária do modificador para evitar confusão na cozinha
            if (parentQty > 1 && totalModQty >= parentQty && (totalModQty % parentQty === 0)) {
                unitQty = totalModQty / parentQty;
            }

            const name = (m.name || m.modifier_name || m.option_name || '').trim();
            return {
                id: m.id || `${name}_${unitQty}`,
                name,
                qty: unitQty
            };
        })
        .filter(m => m.name && m.name !== '\u200B');
});

const handleConfirm = () => {
    isCompleting.value = true;

    // Feedback visual verde antes de concluir e remover da esteira
    setTimeout(() => {
        isCompleting.value = false;
        isSuccess.value = true;

        setTimeout(() => {
            emit('complete', props.item);
        }, 400);
    }, 250);
};
</script>
