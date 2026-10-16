<template>
    <div class="flex-1 flex flex-col h-full relative overflow-hidden w-full bg-surface-1/30 pt-40 md:pt-44">

        <!-- TELEPORT PARA O CUSTOMER HEADER -->
        <Teleport to="#customer-header-portal-target" v-if="isMounted && !showInnerModifiers">
            <div class="flex flex-col items-center justify-center w-full gap-2 pt-1 pb-2 px-4 animate-in fade-in slide-in-from-top-2">
                <p v-if="product?.description" class="text-[11px] font-medium text-surface-muted text-center leading-snug line-clamp-2 px-4 w-full">
                    {{ product.description }}
                </p>
                <ProgressBar
                    v-if="comboProgressMax > 0"
                    :current="comboProgressCurrent"
                    :max="comboProgressMax"
                    mode="wizard"
                />
            </div>
        </Teleport>

        <!-- Container do Wizard -->
        <div v-if="product && currentGroup" class="flex-1 min-h-0 flex flex-col overflow-hidden relative px-4 gap-4 mt-2">
            <!-- 1. Grid de Seleção da Etapa -->
            <div v-show="!showMobileSummary" class="flex-1 min-h-0 flex flex-col overflow-hidden min-w-0 bg-transparent">
                <ComboStepGrid
                    v-if="currentGroup"
                    :group="currentGroup"
                    :current-step="currentStepIndex"
                    :all-groups="comboGroupsList"
                    :can-add-more="canAddMore"
                    :helpers="comboHelpers"
                    :selections-count="getSelectionsCountForGroup(currentGroup?.id)"
                    @add="addCatalogItemToTray"
                    class="w-full h-full"
                />
            </div>

            <!-- 2. Resumo Lateral / Gaveta com Slots Faltantes -->
            <div v-show="showMobileSummary" class="w-full shrink-0 flex flex-col overflow-hidden min-h-0 animate-in slide-in-from-right-4 duration-300 bg-transparent">
                <ComboSidebar
                    :all-selections="globalSelectionsArray"
                    :groups="comboGroupsList"
                    :helpers="comboHelpers"
                    :is-first-step="currentStepIndex === 0"
                    :is-last-step="isLastStep"
                    :is-valid="isComboComplete"
                    :is-team-mode="isTeamMode"
                    :is-customer-mode="isCustomerMode"
                    @edit="openModifiers($event, true)"
                    @remove="removeSelection"
                    @cancel="handleBackOrCancel"
                    @next="handleComboConfirmTrigger"
                    @select-step="handleStepSelect"
                    class="w-full h-full"
                />
            </div>
        </div>

        <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-8 text-surface-muted gap-3">
            <i class="pi pi-spinner pi-spin text-3xl text-primary-500"></i>
            <p class="text-sm font-bold">Carregando opções do combo...</p>
        </div>

        <!-- Barra Inferior -->
        <ComboDynamicNav
            :hide="showInnerModifiers"
            :show-summary="showMobileSummary"
            :progress="totalSelectedItems"
            :total-items="totalSelectedItems"
            :has-items="totalSelectedItems > 0"
            :is-last-step="isLastStep"
            :is-valid="isComboComplete"
            :is-adding="isAdding"
            :is-add-success="isAddSuccess"
            :can-go-back="canGoBack"
            @back="handleMobileBack"
            @toggle-summary="showMobileSummary = true"
            @next="handleComboConfirmTrigger"
            @cancel="handleClearCombo"
        />

        <!-- Modificadores Internos do Subitem -->
        <Transition name="page-fade">
            <div v-if="showInnerModifiers" class="absolute inset-0 z-50 flex flex-col bg-surface-1">
                <CustomerProductModifiers
                    :product="modifyingProduct"
                    :is-edit-mode="isEditingComboItem"
                    :breadcrumbs="[...resolvedBreadcrumbs, (product?.name || 'Combo')]"
                    @confirm="handleModifierConfirm"
                    @close="showInnerModifiers = false"
                />
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { computed, watch, onUnmounted, ref, onMounted } from 'vue';

import ProgressBar from "@/Components/UI/ProgressBar.vue";
import ComboStepGrid from "@/Pages/Shared/Configurators/ComboStepGrid.vue";
import ComboSidebar from "@/Pages/Shared/Configurators/ComboSidebar.vue";
import CustomerProductModifiers from "./CustomerProductModifiers.vue";
import ComboDynamicNav from "@/Pages/Shared/Configurators/ComboDynamicNav.vue";

import { useComboWizard } from "@/Pages/Shared/Composables/useComboWizard";
import { dynamicIslandTypeOverride } from "@/Pages/Shared/Composables/useDynamicIsland";
import { validateProductModifiers } from "@/Pages/Shared/Composables/useCart";

const props = defineProps({
    product: Object,
    breadcrumbs: { type: Array, default: () => [] },
    isEditMode: { type: Boolean, default: false },
    globalProductsList: { type: Array, default: () => [] }
});

const emit = defineEmits(['close', 'crumb-click', 'confirm-combo', 'update:total', 'update:inner-product']);

const isMounted = ref(false);
onMounted(() => { isMounted.value = true; });

const isTeamMode = computed(() => false);
const isCustomerMode = computed(() => true);

const comboGroupsList = computed(() => {
    return props.product?.combo_groups || props.product?.comboGroups || props.product?.groups || [];
});

const resolvedBreadcrumbs = computed(() => {
    if (props.breadcrumbs && props.breadcrumbs.length > 0) return props.breadcrumbs;
    if (props.product?.category?.name) return ['Início', props.product.category.name];
    return ['Início'];
});

const {
    currentStepIndex, showMobileSummary, showInnerModifiers, modifyingProduct, isAdding, isAddSuccess,
    currentGroup, isLastStep, canAddMore, globalSelectionsArray, comboHelpers, isCurrentStepValid,
    comboProgressMax, comboProgressCurrent, wizardTotal, addCatalogItemToTray, openModifiers,
    handleModifierConfirm, removeSelection, handleMobileBack, handleBackOrCancel, nextStep, handleClearCombo,
    isEditingComboItem, canGoBack, getSelectionsCountForGroup
} = useComboWizard(props, emit);

// 🛡️ VALIDAÇÃO ESTRITA: O combo só é completo se TODOS os grupos e modificadores estiverem satisfeitos
const isComboComplete = computed(() => {
    // Se ainda estiver com a tela de modificadores de um item aberta, nunca está pronto
    if (showInnerModifiers.value) return false;

    const groups = comboGroupsList.value;
    if (!groups || groups.length === 0) return false;

    const selections = globalSelectionsArray.value || [];

    // 1. Verifica se CADA grupo atingiu a quantidade mínima exigida
    for (const group of groups) {
        const minChoices = Number(group.min_choices ?? 1);
        const gId = group.id ?? group.group_id;

        const selectionsInGroup = selections.filter(sel =>
            String(sel.groupId ?? sel.group_id) === String(gId)
        );

        const totalQtyInGroup = selectionsInGroup.reduce((sum, s) => sum + Number(s.qty || 1), 0);
        if (totalQtyInGroup < minChoices) {
            return false;
        }
    }

    // 2. Verifica se CADA subitem selecionado cumpriu suas escolhas obrigatórias
    for (const sel of selections) {
        const itemProd = sel.item?.product || sel.item || {};
        const check = validateProductModifiers(itemProd, sel.structuredModifiers);
        if (!check.valid) {
            return false; // Subitem incompleto
        }
    }

    return true;
});

const handleComboConfirmTrigger = () => {
    if (!isComboComplete.value) {
        // Se ainda não estiver completo, avança para o próximo passo pendente
        nextStep();
        return;
    }
    nextStep();
};

const handleStepSelect = (step) => {
    const groups = comboGroupsList.value;
    const targetIndex = groups.findIndex(g => String(g.id ?? g.group_id) === String(step.groupId));
    if (targetIndex !== -1) {
        currentStepIndex.value = targetIndex;
        showMobileSummary.value = false;
    }
};

watch([showInnerModifiers, modifyingProduct], ([isShowing, modProd]) => {
    emit('update:inner-product', isShowing && modProd ? modProd : null);
}, { immediate: true });

watch(wizardTotal, (newTotal) => {
    emit('update:total', newTotal);
}, { immediate: true });

const totalSelectedItems = computed(() => {
    return globalSelectionsArray.value?.length || 0;
});

watch(showInnerModifiers, (isShowing) => {
    dynamicIslandTypeOverride.value = isShowing ? 'modifier' : null;
});

onUnmounted(() => {
    emit('update:inner-product', null);
    dynamicIslandTypeOverride.value = null;
});
</script>

<style scoped>
.page-fade-enter-active, .page-fade-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.page-fade-enter-from, .page-fade-leave-to { opacity: 0; transform: translateY(10px); }
</style>
