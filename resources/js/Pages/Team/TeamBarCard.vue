<template>
    <div class="bg-surface-2 p-4 rounded-[1.5rem] flex flex-col gap-2 transition-all">

        <!-- ROW 1: Contexto e Tempo -->
        <div class="flex items-center justify-between gap-2 border-b border-surface-border/50 pb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-surface-muted truncate flex-1">
                {{ formattedTitle }}
            </span>
            <span class="text-[10px] bg-surface-3 text-surface-muted px-2 py-0.5 rounded font-bold shrink-0">
                #{{ $formatNumber(item.order?.id) }}
            </span>
            <span class="text-[11px] font-black flex items-center gap-1 shrink-0" :class="timerTextColorClass">
                <i class="pi pi-clock text-[10px]"></i> {{ totalTime }}m
            </span>
        </div>

        <!-- ROW 2: O Pedido -->
        <div class="flex flex-col py-1">
            <h4 class="font-black text-surface-text text-base leading-tight">
                <span>{{ item.quantity }}</span><span class="text-surface-muted">×</span> {{ item.product_name || item.name }}
            </h4>

            <div v-if="item.notes || (item.modifiers && item.modifiers.length > 0)" class="text-[11px] font-medium text-surface-muted mt-1 flex flex-col gap-0.5">
                <div v-if="item.notes" class="text-warning-500"><i class="pi fa-exclamation-triangle"></i> Obs: {{ item.notes }}</div>
                <div v-for="mod in item.modifiers" :key="mod.id">
                    + {{ mod.quantity > 1 ? mod.quantity+'x ' : '' }}{{ mod.name }}
                </div>
            </div>
        </div>

        <!-- ROW 3: Ações (Botões e Slider Dinâmico) -->
        <div class="flex items-center gap-2 pt-2 border-t border-surface-border/50">

            <template v-if="isDelivered">
                <div class="w-[200px] h-10 bg-success-500/10 text-success-600 rounded-xl flex items-center justify-center gap-2 font-black text-xs border border-success-500/20">
                    <i class="pi pi-check-circle"></i> Finalizado
                </div>
            </template>

            <template v-else-if="isSomeoneElse">
                <div class="w-[200px] h-10 bg-surface-2 text-surface-muted rounded-xl flex items-center justify-center gap-2 font-black text-[10px] uppercase tracking-widest border border-surface-border">
                    <i class="pi pi-user"></i> Prep: {{ operatorName }}
                </div>
            </template>

            <template v-else-if="isPreparing">
                <SlideToConfirm
                    key="slider-complete"
                    class="w-[200px] !h-10"
                    color="success"
                    text="Finalizar Item"
                    success-text="Enviando!"
                    :is-loading="isCompleting"
                    :is-success="isSuccess"
                    @confirm="handleConfirm('complete')"
                />
            </template>

            <template v-else>
                <SlideToConfirm
                    key="slider-take"
                    class="w-[200px] !h-10"
                    color="primary"
                    text="Pegar Item"
                    success-text="Peguei!"
                    :is-loading="isCompleting"
                    :is-success="isSuccess"
                    @confirm="handleConfirm('take')"
                />
            </template>

            <!-- 🚀 ESPAÇADOR MÁGICO: Empurra os ícones para a direita -->
            <div class="flex-1"></div>

            <IconButton icon="pi-print" color="surface" class="!w-10 !h-10 shrink-0" @click="$emit('print', item)" />
            <IconButton v-if="!isDelivered" icon="pi-trash" color="danger" class="!w-10 !h-10 shrink-0" @click="$emit('cancel', item)" />
        </div>
    </div>
</template>

<script setup>
import { ref, toRefs, computed, watch } from "vue";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useKdsItemTimer } from "@/Pages/Kds/Composables/useKdsItemTimer";
import SlideToConfirm from "@/Components/UI/SlideToConfirm.vue";
import IconButton from "@/Components/UI/IconButton.vue";

const props = defineProps({ item: Object, currentTime: Date, currentUserId: [Number, String] });
const emit = defineEmits(['print', 'cancel', 'take', 'complete']);

const { item, currentTime } = toRefs(props);
const { getOrderTitle } = useOrderFormatting();
const formattedTitle = computed(() => getOrderTitle(item.value.order));
const { totalTime, timerTextColorClass } = useKdsItemTimer(item, currentTime);

const itemStatus = computed(() => item.value.production_status || item.value.status || 'pending');
const isPreparing = computed(() => ['preparing', 'in_progress'].includes(itemStatus.value));
const isDelivered = computed(() => ['delivered', 'ready'].includes(itemStatus.value));

const operatorId = computed(() => item.value.operator_id || item.value.prepared_by_id);
const operatorName = computed(() => item.value.operator_name || item.value.prepared_by_name || 'Colega');
const isSomeoneElse = computed(() => isPreparing.value && operatorId.value && Number(operatorId.value) !== Number(props.currentUserId));

// 🚀 SONDA DE REATIVIDADE: Avisa no console quando o status do item for alterado pelo banco!
watch(() => itemStatus.value, (newStatus) => {
    console.log(`📍 [SONDA CARD] O item #${item.value.id} mudou de status para: ${newStatus}`);
    console.log(`📍 [SONDA CARD] Variáveis reativas -> isPreparing: ${isPreparing.value} | isSomeoneElse: ${isSomeoneElse.value}`);
});

const isCompleting = ref(false);
const isSuccess = ref(false);

const handleConfirm = (actionType) => {
    console.log(`📍 [SONDA SLIDER] Ação detectada: ${actionType}`);
    isCompleting.value = true;

    setTimeout(() => {
        isCompleting.value = false;
        isSuccess.value = true;

        setTimeout(() => {
            isSuccess.value = false;
            if (actionType === 'complete') {
                emit('complete', props.item);
            } else {
                emit('take', props.item);
            }
        }, 400);
    }, 250);
};
</script>
