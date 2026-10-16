<template>
    <div class="flex-1 flex flex-col h-full relative overflow-hidden w-full" :class="(isTeamMode || isCustomerMode) ? 'bg-surface-1 pt-42 pb-32' : 'bg-surface-2'">

        <!-- CABEÇALHO DESKTOP / POS (Oculto nos apps mobile) -->
        <ProductHeader
            v-if="!isTeamMode && !isCustomerMode"
            :show-breadcrumbs="true"
            subtitle="Montando Combo"
            :title="product?.name || ''"
            :total="wizardTotal"
            :is-valid="isCurrentStepValid"
            :breadcrumbs="resolvedBreadcrumbs"
            :is-edit-mode="isEditMode"
            :progress-current="comboProgressCurrent"
            :progress-max="comboProgressMax"
            :hide-back-btn="true"
            :confirm-label="isLastStep ? (isEditMode ? 'Salvar Combo' : 'Concluir Combo') : 'Próxima Etapa'"
            :confirm-icon="isLastStep ? 'pi pi-check' : 'pi pi-arrow-right'"
            @close="handleBackOrCancel"
            @confirm="nextStep"
            @crumb-click="$emit('close'); $emit('crumb-click', $event)"
            class="shrink-0"
        />

        <!-- 🚀 O TELEPORT PARA O TEAM/CUSTOMER HEADER (Progresso + Descrição) -->
        <Teleport :to="isTeamMode ? '#team-header-portal-target' : '#customer-header-portal-target'" v-if="(isTeamMode || isCustomerMode) && isMounted && !showInnerModifiers">
            <div class="flex flex-col items-center justify-center w-full gap-2 pt-1 pb-2 animate-in fade-in slide-in-from-top-2">
                <ProgressBar
                    v-if="comboProgressMax > 0"
                    :current="comboProgressCurrent"
                    :max="comboProgressMax"
                    mode="wizard"
                />
                <p v-if="product?.description" class="text-[11px] font-medium text-surface-muted text-center leading-snug line-clamp-2 px-4 w-full">
                    {{ product.description }}
                </p>
            </div>
        </Teleport>

        <div v-if="product && currentGroup" class="flex-1 flex flex-col md:flex-row overflow-hidden relative" :class="(isTeamMode || isCustomerMode) ? 'px-4 gap-4' : 'p-6 gap-1'">
            <div v-show="!(isTeamMode || isCustomerMode) || !showMobileSummary" class="flex-1 flex flex-col overflow-hidden min-h-0 min-w-0" :class="(isTeamMode || isCustomerMode) ? 'bg-surface-1' : 'bg-surface-3 rounded-3xl rounded-r-lg p-4'">
                <ComboStepGrid
                    :group="currentGroup"
                    :current-step="currentStepIndex"
                    :all-groups="product?.combo_groups || []"
                    :can-add-more="canAddMore"
                    :helpers="comboHelpers"
                    :selections-count="getSelectionsCountForGroup(currentGroup.id)"
                    @add="addCatalogItemToTray"
                    class="w-full h-full"
                />
            </div>

            <!-- Resumo Lateral no Desktop (Sidebar) -->
            <div v-show="!(isTeamMode || isCustomerMode) || showMobileSummary" class="w-full md:w-[320px] lg:w-[360px] shrink-0 flex flex-col overflow-hidden min-h-0 animate-in slide-in-from-right-4 duration-300" :class="(isTeamMode || isCustomerMode) ? 'bg-surface-1 border-t border-surface-border pt-2' : 'bg-surface-3 rounded-3xl rounded-l-lg'">
                <ComboSidebar
                    :all-selections="globalSelectionsArray"
                    :groups="product?.combo_groups || []"
                    :helpers="comboHelpers"
                    :is-first-step="currentStepIndex === 0"
                    :is-last-step="isLastStep"
                    :is-valid="isCurrentStepValid"
                    :is-team-mode="isTeamMode"
                    :is-customer-mode="isCustomerMode"
                    @edit="openModifiers($event, true)"
                    @remove="removeSelection"
                    @cancel="handleBackOrCancel"
                    @next="nextStep"
                    @select-step="handleStepSelect"
                    class="w-full h-full"
                />
            </div>
        </div>

        <ComboDynamicNav
            v-if="isTeamMode || isCustomerMode"
            :hide="showInnerModifiers"
            :show-summary="showMobileSummary"
            :progress="comboProgressCurrent"
            :is-last-step="isLastStep"
            :is-valid="isCurrentStepValid"
            :is-adding="isAdding"
            :is-add-success="isAddSuccess"
            :can-go-back="canGoBack"
            @back="handleMobileBack"
            @toggle-summary="showMobileSummary = true"
            @next="nextStep"
            @cancel="handleClearCombo"
        />

        <Transition name="page-fade">
            <div v-if="showInnerModifiers" class="absolute inset-0 z-50 flex flex-col" :class="(isTeamMode || isCustomerMode) ? 'bg-surface-1' : 'bg-surface-2 rounded-[2rem]'">
                <ProductModifiers
                    :product="modifyingProduct"
                    :is-edit-mode="isEditingComboItem"
                    :is-team-mode="isTeamMode"
                    :is-customer-mode="isCustomerMode"
                    :breadcrumbs="[...resolvedBreadcrumbs, product?.name]"
                    @confirm="handleModifierConfirm"
                    @close="showInnerModifiers = false"
                />
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
const page = usePage();

import { computed, watch, onUnmounted, ref, onMounted } from 'vue';

import ProductHeader from "@/Pages/Shared/Configurators/ProductHeader.vue";
import ComboStepGrid from "@/Pages/Shared/Configurators/ComboStepGrid.vue";
import ComboSidebar from "@/Pages/Shared/Configurators/ComboSidebar.vue";
import ProductModifiers from "@/Pages/Shared/Configurators/ProductModifiers.vue";
import ComboDynamicNav from "@/Pages/Shared/Configurators/ComboDynamicNav.vue";
import ProgressBar from "@/Components/UI/ProgressBar.vue";

import { useComboWizard } from "@/Pages/Shared/Composables/useComboWizard";
import { dynamicIslandTypeOverride } from "@/Pages/Shared/Composables/useDynamicIsland";

const props = defineProps({
    product: Object,
    orderType: String,
    breadcrumbs: { type: [Array, Object], default: () => [] },
    isEditMode: { type: Boolean, default: false },
    isTeamMode: { type: Boolean, default: false },
    isCustomerMode: { type: Boolean, default: false },
    globalProductsList: { type: Array, default: () => [] }
});

const emit = defineEmits(['close', 'crumb-click', 'confirm-combo', 'update:total']);

const isMounted = ref(false);
onMounted(() => { isMounted.value = true; });

const resolvedBreadcrumbs = computed(() => {
    // 1. Tenta resolver a árvore a partir do category_id do produto
    const catId = props.product?.category_id || props.product?.category?.id;
    const globalCategories = page?.props?.initialCategories || page?.props?.categories || [];

    const path = [];
    let currentId = catId;
    let depth = 0;

    while (currentId && depth < 10) {
        const cat = globalCategories.find(c => String(c.id) === String(currentId));
        if (!cat) break;
        path.unshift({ label: cat.name || cat.label, id: cat.id });
        currentId = cat.parent_id && Number(cat.parent_id) !== 0 ? cat.parent_id : null;
        depth++;
    }

    if (path.length > 0) {
        return [{ label: 'Início', id: null }, ...path];
    }

    // 2. Fallback: Se props.breadcrumbs for o objeto do useCatalogBreadcrumbs ({ items, active })
    if (props.breadcrumbs && typeof props.breadcrumbs === 'object' && !Array.isArray(props.breadcrumbs)) {
        const items = props.breadcrumbs.items || [];
        const active = props.breadcrumbs.active;
        const result = [...items];
        if (active && active !== 'Início') {
            result.push({ label: active, id: null });
        }
        if (result.length > 0) return result;
    }

    // 3. Fallback: Array regular
    const raw = Array.isArray(props.breadcrumbs)
        ? props.breadcrumbs
        : (props.breadcrumbs?.items || []);

    if (raw.length > 0) {
        const cleaned = raw.map(b => typeof b === 'string' ? { label: b, id: null } : b);
        return [{ label: 'Início', id: null }, ...cleaned];
    }

    return [{ label: 'Início', id: null }];
});

const {
    currentStepIndex, showMobileSummary, showInnerModifiers, modifyingProduct, isAdding, isAddSuccess,
    currentGroup, isLastStep, canAddMore, globalSelectionsArray, comboHelpers, isCurrentStepValid,
    comboProgressMax, comboProgressCurrent, wizardTotal, addCatalogItemToTray, openModifiers,
    handleModifierConfirm, removeSelection, handleMobileBack, handleBackOrCancel, nextStep, handleClearCombo,
    isEditingComboItem, canGoBack, getSelectionsCountForGroup
} = useComboWizard(props, emit);

// 🚀 NAVEGAÇÃO DIRETA PARA O PASSO AO CLICAR NO SLOT FALTANTE
const handleStepSelect = (step) => {
    const groups = props.product?.combo_groups || [];
    const targetIndex = groups.findIndex(g => String(g.id ?? g.group_id) === String(step.groupId));
    if (targetIndex !== -1) {
        currentStepIndex.value = targetIndex;
        showMobileSummary.value = false;
    }
};

watch(wizardTotal, (newTotal) => {
    emit('update:total', newTotal);
}, { immediate: true });

watch(showInnerModifiers, (isShowing) => {
    dynamicIslandTypeOverride.value = isShowing ? 'modifier' : null;
});

onUnmounted(() => {
    dynamicIslandTypeOverride.value = null;
});
</script>

<style scoped>
.page-fade-enter-active, .page-fade-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.page-fade-enter-from, .page-fade-leave-to { opacity: 0; transform: translateY(10px); }
</style>
