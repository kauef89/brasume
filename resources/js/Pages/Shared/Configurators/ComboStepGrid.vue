<template>
    <div class="flex-1 min-h-0 flex flex-col gap-4 h-full w-full overflow-hidden">

        <!-- 🚀 CABEÇALHO FIXO DO PASSO: Título, Feedback e Progresso da Etapa -->
        <div class="flex flex-col gap-3 shrink-0">
            <div class="flex items-start justify-between gap-3">
                <div class="flex flex-col">
                    <h3 class="font-black text-xl text-surface-text tracking-tight leading-snug">
                        {{ group?.name || group?.instruction || 'Escolha uma opção' }}
                    </h3>
                    <p class="text-sm font-medium text-surface-muted mt-0.5">
                        {{ feedbackText }}
                    </p>
                </div>

                <!-- Badge Numérico de Progresso do Passo -->
                <div
                    v-if="maxChoices > 1"
                    class="shrink-0 flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black tracking-wider shadow-sm transition-all"
                    :class="isStepComplete ? 'bg-success-500/15 text-success-500' : 'bg-primary-500/15 text-primary-smart'"
                >
                    <i v-if="isStepComplete" class="pi pi-check text-[10px]"></i>
                    <span>{{ selectionsCount }} / {{ maxChoices }}</span>
                </div>
            </div>

            <!-- 📊 BARRA DE PROGRESSO DA ETAPA -->
            <div v-if="maxChoices > 1" class="pt-1">
                <ProgressBar
                    :current="selectionsCount"
                    :max="maxChoices"
                    mode="quantity"
                />
            </div>
        </div>

        <!-- 📜 ÁREA DE ROLAGEM DEDICADA: Grid de até 4 Colunas no Desktop -->
        <div
            class="flex-1 min-h-0 overflow-y-auto overscroll-contain touch-pan-y hide-scrollbar pb-4 pr-1"
            :class="productCardLayout === 'square'
                ? 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 auto-rows-max'
                : 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5 auto-rows-max'"
        >
            <ComboItemCard
                v-for="item in (group?.items || [])"
                :key="item.id"
                :item="item"
                :disabled="!canAddMore && maxChoices > 1"
                :has-modifiers="helpers?.hasModifiers(item)"
                :layout="productCardLayout"
                class="shrink-0"
                @add="$emit('add',$event, true)"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import ComboItemCard from "@/Pages/Shared/Configurators/ComboItemCard.vue";
import ProgressBar from "@/Components/UI/ProgressBar.vue";
import { usePosSettings } from "@/Pages/Pos/Composables/usePosSettings";

const { productCardLayout } = usePosSettings();

const props = defineProps({
    group: { type: Object, default: () => ({}) },
    allGroups: { type: Array, default: () => [] },
    currentStep: { type: Number, default: 0 },
    canAddMore: { type: Boolean, default: true },
    helpers: { type: Object, default: () => ({}) },
    selectionsCount: { type: Number, default: 0 }
});

defineEmits(["add"]);

const maxChoices = computed(() => Number(props.group?.max_choices) || 1);
const minChoices = computed(() => Number(props.group?.min_choices) || 1);

const remainingChoices = computed(() => Math.max(0, maxChoices.value - (props.selectionsCount || 0)));
const isStepComplete = computed(() => (props.selectionsCount || 0) >= maxChoices.value);

const feedbackText = computed(() => {
    if (!props.group || Object.keys(props.group).length === 0) return "";

    if (maxChoices.value === 1) {
        return (props.selectionsCount || 0) >= 1
            ? "Opção selecionada! Você pode trocar clicando em outro item."
            : "Selecione 1 opção para avançar.";
    }

    if (isStepComplete.value) {
        return "Passo completo! Você já pode avançar para a próxima etapa.";
    }

    if ((props.selectionsCount || 0) > 0) {
        return `Você escolheu ${props.selectionsCount}. Falta${remainingChoices.value > 1 ? "m" : ""} ${remainingChoices.value} ${remainingChoices.value === 1 ? "opção" : "opções"}.`;
    }

    if (minChoices.value === maxChoices.value) {
        return `Escolha ${maxChoices.value} opções para este passo.`;
    }

    return `Selecione de ${minChoices.value} a ${maxChoices.value} opções.`;
});
</script>
