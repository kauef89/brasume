<template>
    <div class="flex-1 flex flex-col h-full relative overflow-hidden w-full bg-surface-2 pt-28">

        <div v-if="product?.description" class="px-6 pt-2 pb-4 shrink-0">
            <p class="text-sm font-medium text-surface-muted leading-relaxed">
                {{ product.description }}
            </p>
        </div>

        <div v-if="product" class="flex-1 overflow-y-auto hide-scrollbar flex flex-col relative px-6 pb-32">
            <div v-if="allTabs.length > 0" class="flex flex-col gap-6">

                <!-- Macro-Abas (Ingredientes, Adicionais, Observações) -->
                <ModifierTabs :tabs="allTabs" v-model="activeTab" :is-customer-mode="true" />

                <div class="min-h-[200px]" :key="activeTab">

                    <!-- 1. ABA DE OBSERVAÇÕES & INSTRUÇÕES -->
                    <div v-if="activeTab === 'observation'" class="flex flex-col gap-6">
                        <div v-for="group in currentTabGroups" :key="group.id" class="flex flex-col">
                            <ModifierOptionsGroup
                                :group="group"
                                :helpers="stateHelpers"
                                :is-customer-mode="true"
                                :enable-predictive="isPredictiveEnabled"
                            />
                            <div class="h-px bg-surface-border/40 mt-6"></div>
                        </div>

                        <div class="flex flex-col gap-3 rounded-2xl">
                            <label class="font-black text-sm flex items-center gap-1.5 text-surface-text">
                                <i class="pi pi-pencil text-primary-500 text-xs"></i>
                                <span>Instruções para a Cozinha</span>
                            </label>
                            <textarea
                                v-model="itemObservation"
                                placeholder="Ex: Carne bem passada, caprichar no molho..."
                                class="w-full rounded-xl bg-surface-1 p-4 text-surface-text focus:border-primary-500 outline-none transition-all resize-none border border-surface-border/40 text-sm"
                                rows="4"
                            ></textarea>
                        </div>
                    </div>

                    <!-- 2. ABA DE INGREDIENTES OU ADICIONAIS -->
                    <div v-else class="flex flex-col gap-6">
                        <div v-for="(group, idx) in currentTabGroups" :key="group.id" class="flex flex-col">
                            <ModifierOptionsGroup
                                :group="group"
                                :helpers="stateHelpers"
                                :is-customer-mode="true"
                                :enable-predictive="isPredictiveEnabled"
                            />
                            <div v-if="idx < currentTabGroups.length - 1" class="h-px bg-surface-border/40 mt-6"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <ModifierDynamicNav
            :is-valid="isSelectionValid"
            :is-adding="isAdding"
            :is-add-success="isAddSuccess"
            :is-edit-mode="isEditMode"
            :show-takeaway="false"
            @back="$emit('close')"
            @confirm="confirmAndAdd"
        >
            <template #stepper>
                <QtyStepper v-model="localQuantity" :min="1" :max="maxQuantity" size="md" variant="glass" />
            </template>
        </ModifierDynamicNav>
    </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useModifiers } from "@/Pages/Shared/Composables/useModifiers";

import ModifierTabs from "@/Pages/Shared/Configurators/ModifierTabs.vue";
import ModifierOptionsGroup from "@/Pages/Shared/Configurators/ModifierOptionsGroup.vue";
import QtyStepper from "@/Components/UI/QtyStepper.vue";
import ModifierDynamicNav from "@/Pages/Shared/Configurators/ModifierDynamicNav.vue";

const props = defineProps({
    product: Object,
    isEditMode: { type: Boolean, default: false },
    breadcrumbs: { type: Array, default: () => [] }
});

const emit = defineEmits(["confirm", "close", "crumb-click", "update:total"]);
const page = usePage();

const isPredictiveEnabled = computed(() => {
    const mode = page.props.companySettings?.predictive_modifiers_mode || 'disabled';
    return mode === 'all' || mode === 'customer';
});

const stateHelpers = useModifiers(true);
const itemObservation = ref("");
const activeTab = ref(null);
const localQuantity = ref(1);
const maxQuantity = computed(() => props.product?.cartItem?.maxQty || 99);
const isAdding = ref(false);
const isAddSuccess = ref(false);

const normalizeType = (type) => {
    const t = String(type || '').toLowerCase().trim();
    if (t === 'ingredient' || t === 'ingrediente') return 'ingredient';
    if (t === 'add_on' || t === 'additional' || t === 'adicional' || t === 'addon') return 'add_on';
    if (t === 'observation' || t === 'observacao' || t === 'observação') return 'observation';
    return 'add_on';
};

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
    let existingModifiers = newVal?.cartItem?.structuredModifiers ? [...newVal.cartItem.structuredModifiers] : [];
    const obsIndex = existingModifiers.findIndex(m => m.is_joker && m.name.startsWith('Obs: '));
    if (obsIndex > -1) {
        itemObservation.value = existingModifiers[obsIndex].name.replace('Obs: ', '');
        existingModifiers.splice(obsIndex, 1);
    }
    stateHelpers.initModifiers(newVal?.modifiers, existingModifiers);
}, { immediate: true });

watch(() => allTabs.value, (newTabs) => {
    if (newTabs.length > 0 && (!activeTab.value || !newTabs.find(t => t.id === activeTab.value))) activeTab.value = newTabs[0].id;
}, { immediate: true });

watch(() => stateHelpers.hasJokerSelected(props.product?.modifiers), (isJokerNowActive) => {
    if (isJokerNowActive) setTimeout(() => { activeTab.value = 'observation'; }, 300);
});

const currentTotal = computed(() => stateHelpers.calculateTotal(props.product?.price, props.product?.modifiers));
const isSelectionValid = computed(() => stateHelpers.isValid(props.product?.modifiers, itemObservation.value));

watch([currentTotal, localQuantity], () => {
    emit('update:total', currentTotal.value * localQuantity.value);
}, { immediate: true });

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
            emit("confirm", { text: text, extraPrice: extraPrice, isTakeaway: false, structuredModifiers: structured, qty: localQuantity.value });
        }, 500);
    }, 300);
};
</script>
