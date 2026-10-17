<template>
    <div class="flex-1 flex flex-col h-full relative overflow-hidden w-full" :class="[
        isTeamMode ? 'bg-surface-1' : 'bg-surface-2',
        isTeamMode ? 'pt-42 pb-32' : ''
    ]">

        <!-- CABEÇALHO DESKTOP / POS (Oculto no TeamApp mobile) -->
        <div v-if="!isTeamMode && !isCustomerMode" class="shrink-0 flex flex-col w-full">
            <ProductHeader
                :title="product?.name || ''"
                :total="currentTotal * localQuantity"
                :show-breadcrumbs="true"
                :breadcrumbs="resolvedBreadcrumbs"
                :is-valid="isSelectionValid"
                :is-edit-mode="isEditMode"
                @close="$emit('close')"
                @crumb-click="$emit('crumb-click',$event)"
            />

            <!-- LINHA DE MACRO-ABAS (Ingredientes, Adicionais, Observações) -->
            <div v-if="allTabs.length > 0" class="px-6 py-3 shrink-0 bg-surface-2/60">
                <ModifierTabs :tabs="allTabs" v-model="activeTab" :is-team-mode="isTeamMode" />
            </div>
        </div>

        <!-- TELEPORT PARA O TEAM HEADER (Mobile) -->
        <Teleport to="#team-header-portal-target" v-if="isTeamMode && isMounted">
            <div class="flex flex-col items-center justify-center w-full pt-1 pb-2 animate-in fade-in slide-in-from-top-2">
                <p v-if="product?.description" class="text-[11px] font-medium text-surface-muted text-center leading-snug line-clamp-2 px-4 w-full">
                    {{ product.description }}
                </p>
            </div>
        </Teleport>

        <!-- ÁREA ROLÁVEL DE GRUPOS E ANOTAÇÕES -->
        <div v-if="product" class="flex-1 overflow-y-auto hide-scrollbar flex flex-col relative px-6" :class="isTeamMode ? 'pb-32' : 'pb-6'">
            <div v-if="allTabs.length > 0" class="flex flex-col gap-6">

                <!-- Abas renderizadas aqui apenas em modo mobile (TeamMode) -->
                <ModifierTabs v-if="isTeamMode" :tabs="allTabs" v-model="activeTab" :is-team-mode="isTeamMode" />

                <!-- CONTEÚDO DINÂMICO DA MACRO-ABA ATIVA -->
                <div class="min-h-[200px]" :key="activeTab">

                    <!-- 1. ABA DE OBSERVAÇÕES & INSTRUÇÕES -->
                    <div v-if="activeTab === 'observation'" class="flex flex-col gap-6">
                        <!-- Grupos de observações/remoções (se existirem) -->
                        <div v-for="group in currentTabGroups" :key="group.id" class="flex flex-col">
                            <ModifierOptionsGroup
                                :group="group"
                                :helpers="stateHelpers"
                                :is-team-mode="isTeamMode"
                                :enable-predictive="isPredictiveEnabled"
                            />
                            <div class="h-px bg-surface-border/40 mt-6"></div>
                        </div>

                        <!-- Caixa de Instruções Livres para a Cozinha -->
                        <div class="flex flex-col gap-3" :class="isTeamMode ? 'bg-transparent rounded-none' : 'rounded-2xl'">
                            <label class="font-black text-sm flex items-center gap-1.5 text-surface-text">
                                <i class="pi pi-pencil text-primary-500 text-xs"></i>
                                <span>Instruções para a Cozinha</span>
                            </label>
                            <textarea
                                v-model="itemObservation"
                                placeholder="Ex: Carne bem passada, embalar as batatas em separado..."
                                class="w-full rounded-xl bg-surface-1 p-4 text-surface-text focus:border-primary-500 outline-none transition-all resize-none border border-surface-border/40 text-sm"
                                rows="4"
                            ></textarea>

                            <p v-if="stateHelpers.hasJokerSelected(product?.modifiers) && itemObservation.trim().length < 3" class="text-warning-600 text-xs font-medium flex items-center gap-1 mt-1">
                                <i class="pi pi-exclamation-triangle"></i>
                                Como selecionou "Outro", por favor descreva o ingrediente aqui.
                            </p>
                        </div>
                    </div>

                    <!-- 2. ABA DE INGREDIENTES OU ADICIONAIS -->
                    <div v-else class="flex flex-col gap-6">
                        <div v-for="(group, idx) in currentTabGroups" :key="group.id" class="flex flex-col">
                            <ModifierOptionsGroup
                                :group="group"
                                :helpers="stateHelpers"
                                :is-team-mode="isTeamMode"
                                :enable-predictive="isPredictiveEnabled"
                            />
                            <div v-if="idx < currentTabGroups.length - 1" class="h-px bg-surface-border/40 mt-6"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BARRA DINÂMICA MOBILE (TeamApp) -->
        <ModifierDynamicNav
            v-if="isTeamMode"
            :show-takeaway="!orderType || orderType === 'table'"
            :is-takeaway="isTakeaway"
            :is-edit-mode="isEditMode"
            :is-valid="isSelectionValid"
            :is-adding="isAdding"
            :is-add-success="isAddSuccess"
            @back="$emit('close')"
            @toggle-takeaway="isTakeaway = !isTakeaway"
            @confirm="confirmAndAdd"
        >
            <template #stepper>
                <QtyStepper v-model="localQuantity" :min="1" size="md" />
            </template>
        </ModifierDynamicNav>

        <!-- RODAPÉ DESKTOP / POS -->
        <div v-else class="flex flex-col gap-3 shrink-0 z-20 px-6 pb-6 pt-3 bg-surface-2">


            <div class="flex gap-3 items-center w-full">
                <QtyStepper v-model="localQuantity" :min="1" :max="maxQuantity" size="lg" />

                <div class="flex-1 flex items-center gap-2">
                    <button
                        v-if="!orderType || orderType === 'table'"
                        @click="isTakeaway = !isTakeaway"
                        type="button"
                        class="h-14 px-6 shrink-0 rounded-full flex items-center justify-center gap-2 transition-colors outline-none cursor-pointer font-black text-xs md:text-sm uppercase tracking-widest border-none"
                        :class="isTakeaway ? 'bg-warning-500 text-white shadow-md' : 'bg-surface-3 text-surface-text hover:bg-warning-500/40'"
                    >
                        <i class="pi pi-shopping-bag text-lg"></i>
                        <span>Para Levar</span>
                    </button>

                    <button
                        @click="confirmAndAdd"
                        type="button"
                        :disabled="!isSelectionValid"
                        class="flex-1 h-14 rounded-full font-black text-xl transition-colors cursor-pointer border-none outline-none shadow-md"
                        :class="isSelectionValid ? 'bg-primary-500 text-white hover:bg-primary-600 active:scale-[0.99]' : 'bg-primary-500/50 text-white grayscale cursor-not-allowed'"
                    >
                        {{ isEditMode ? 'Atualizar no Pedido' : 'Adicionar ao Pedido' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useModifiers } from "@/Pages/Shared/Composables/useModifiers";

import ProductHeader from "@/Pages/Shared/Configurators/ProductHeader.vue";
import ModifierTabs from "@/Pages/Shared/Configurators/ModifierTabs.vue";
import ModifierOptionsGroup from "@/Pages/Shared/Configurators/ModifierOptionsGroup.vue";
import QtyStepper from "@/Components/UI/QtyStepper.vue";
import Breadcrumb from "@/Components/UI/Breadcrumb.vue";
import ModifierDynamicNav from "@/Pages/Shared/Configurators/ModifierDynamicNav.vue";

const props = defineProps({
    product: Object,
    orderType: { type: String, default: null },
    isEditMode: { type: Boolean, default: false },
    isTeamMode: { type: Boolean, default: false },
    isCustomerMode: { type: Boolean, default: false },
    breadcrumbs: { type: [Array, Object], default: () => [] }
});

const emit = defineEmits(["confirm", "close", "crumb-click", "update:total"]);
const page = usePage();

const isMounted = ref(false);
onMounted(() => { isMounted.value = true; });

const isPredictiveEnabled = computed(() => {
    const mode = page.props.companySettings?.predictive_modifiers_mode || 'disabled';
    return mode === 'all' || mode === 'waiter';
});

const resolvedBreadcrumbs = computed(() => {
    const catId = props.product?.category_id || props.product?.category?.id;
    const globalCategories = page.props.initialCategories || [];

    const path = [];
    let currentId = catId;
    let depth = 0;

    while (currentId && depth < 10) {
        const cat = globalCategories.find(c => String(c.id) === String(currentId));
        if (!cat) break;
        path.unshift({ label: cat.name, id: cat.id });
        currentId = cat.parent_id && Number(cat.parent_id) !== 0 ? cat.parent_id : null;
        depth++;
    }

    if (path.length > 0) {
        return [{ label: 'Início', id: null }, ...path];
    }

    if (props.breadcrumbs && typeof props.breadcrumbs === 'object' && !Array.isArray(props.breadcrumbs)) {
        const items = props.breadcrumbs.items || [];
        const active = props.breadcrumbs.active;
        const result = [...items];
        if (active && !['início', 'inicio', 'home'].includes(String(active).toLowerCase().trim())) {
            result.push({ label: active, id: null });
        }
        if (result.length > 0) return result;
    }

    const raw = Array.isArray(props.breadcrumbs)
        ? props.breadcrumbs
        : (props.breadcrumbs?.items || []);

    if (raw.length > 0) {
        const cleaned = raw.map(b => typeof b === 'string' ? { label: b, id: null } : b);
        return [{ label: 'Início', id: null }, ...cleaned];
    }

    return [{ label: 'Início', id: null }];
});

const stateHelpers = useModifiers(false);
const itemObservation = ref("");
const activeTab = ref(null);
const localQuantity = ref(1);
const maxQuantity = computed(() => props.product?.cartItem?.maxQty || 99);
const isTakeaway = ref(false);

const isAdding = ref(false);
const isAddSuccess = ref(false);

// 🚀 NORMALIZADOR DE MACRO-TIPOS
const normalizeType = (type) => {
    const t = String(type || '').toLowerCase().trim();
    if (t === 'ingredient' || t === 'ingrediente') return 'ingredient';
    if (t === 'add_on' || t === 'additional' || t === 'adicional' || t === 'addon') return 'add_on';
    if (t === 'observation' || t === 'observacao' || t === 'observação') return 'observation';
    return 'add_on';
};

// 🎯 COMPILADOR DAS MACRO-ABAS (Máximo 3 abas semânticas)
const allTabs = computed(() => {
    const mods = props.product?.modifiers || [];
    const tabs = [];

    const ingredientGroups = mods.filter(g => normalizeType(g.type) === 'ingredient');
    const addonGroups = mods.filter(g => normalizeType(g.type) === 'add_on');
    const observationGroups = mods.filter(g => normalizeType(g.type) === 'observation');

    if (ingredientGroups.length > 0) {
        const hasReq = ingredientGroups.some(g => (g.min_selections || 0) > 0);
        const isComp = ingredientGroups.every(g => stateHelpers.isGroupSatisfied(g));
        tabs.push({
            id: 'ingredient',
            label: 'Ingredientes',
            icon: 'pi-sparkles',
            groupCount: ingredientGroups.length,
            hasRequired: hasReq,
            hasPendingRequired: hasReq && !isComp,
            isComplete: isComp,
            groups: ingredientGroups
        });
    }

    if (addonGroups.length > 0) {
        const hasReq = addonGroups.some(g => (g.min_selections || 0) > 0);
        const isComp = addonGroups.every(g => stateHelpers.isGroupSatisfied(g));
        tabs.push({
            id: 'add_on',
            label: 'Adicionais',
            icon: 'pi-plus-circle',
            groupCount: addonGroups.length,
            hasRequired: hasReq,
            hasPendingRequired: hasReq && !isComp,
            isComplete: isComp,
            groups: addonGroups
        });
    }

    // A aba Observações recebe instruções livres para a cozinha e eventuais opções de remoção
    const hasReq = observationGroups.some(g => (g.min_selections || 0) > 0);
    const isComp = observationGroups.every(g => stateHelpers.isGroupSatisfied(g));
    tabs.push({
        id: 'observation',
        label: 'Observações',
        icon: 'pi-pen-to-square',
        groupCount: observationGroups.length,
        hasRequired: hasReq,
        hasPendingRequired: hasReq && !isComp,
        isComplete: isComp,
        groups: observationGroups
    });

    return tabs;
});

const currentTab = computed(() => allTabs.value.find(t => t.id === activeTab.value) || allTabs.value[0]);
const currentTabGroups = computed(() => currentTab.value?.groups || []);

watch(() => props.product, (newVal) => {
    itemObservation.value = "";
    localQuantity.value = newVal?.cartItem?.qty || 1;
    isTakeaway.value = newVal?.cartItem?.isTakeaway || false;
    let existingModifiers = newVal?.cartItem?.structuredModifiers ? [...newVal.cartItem.structuredModifiers] : [];
    const obsIndex = existingModifiers.findIndex(m => m.is_joker && m.name.startsWith('Obs: '));
    if (obsIndex > -1) {
        itemObservation.value = existingModifiers[obsIndex].name.replace('Obs: ', '');
        existingModifiers.splice(obsIndex, 1);
    }
    stateHelpers.initModifiers(newVal?.modifiers, existingModifiers);
}, { immediate: true });

watch(() => allTabs.value, (newTabs) => {
    if (newTabs.length > 0 && (!activeTab.value || !newTabs.find(t => t.id === activeTab.value))) {
        activeTab.value = newTabs[0].id;
    }
}, { immediate: true });

watch(() => stateHelpers.hasJokerSelected(props.product?.modifiers), (isJokerNowActive) => {
    if (isJokerNowActive) {
        setTimeout(() => {
            activeTab.value = 'observation';
        }, 300);
    }
});

const currentTotal = computed(() => stateHelpers.calculateTotal(props.product?.price, props.product?.modifiers));

watch([currentTotal, localQuantity], ([newTotal, newQty]) => {
    emit("update:total", newTotal * newQty);
}, { immediate: true });

const isSelectionValid = computed(() => stateHelpers.isValid(props.product?.modifiers, itemObservation.value));

const confirmAndAdd = () => {
    if (!isSelectionValid.value) return;

    isAdding.value = true;

    setTimeout(() => {
        isAdding.value = false;
        isAddSuccess.value = true;

        setTimeout(() => {
            const extraPrice = currentTotal.value - Number(props.product.price);
            let text = stateHelpers.buildModifiersText(props.product.modifiers);
            const structured = stateHelpers.buildStructuredModifiers(props.product.modifiers);

            if (itemObservation.value.trim()) {
                text += text ? ` | Obs: ${itemObservation.value}` : `Obs: ${itemObservation.value}`;
                structured.push({ id: null, name: `Obs: ${itemObservation.value}`, price: 0, qty: 1, is_joker: true });
            }

            const finalIsTakeaway = (props.orderType === 'delivery' || props.orderType === 'counter') ? true : isTakeaway.value;

            emit("confirm", { text: text, extraPrice: extraPrice, isTakeaway: finalIsTakeaway, structuredModifiers: structured, qty: localQuantity.value });
        }, 500);
    }, 300);
};
</script>
