<template>
    <Drawer
        :visible="visible"
        @update:visible="$emit('update:visible', $context)"
        position="right"
        :modal="false"
        :dismissable="false"
        :pt="{ root: { style: 'z-index: 1200 !important;' } }"
        class="!w-full md:!w-[450px] !bg-gray-100 dark:!bg-gray-950 shadow-2xl border-l border-gray-200 dark:border-gray-800 transition-all duration-300"
        style="right: 400px"
    >
        <template #header>
            <div class="flex flex-col gap-1">
                <span
                    class="text-xs font-black uppercase text-orange-500 tracking-wider"
                    >Montando Combo</span
                >
                <h2 class="font-bold text-2xl text-gray-900 dark:text-white">
                    {{ comboProduct?.name }}
                </h2>
            </div>
        </template>

        <div
            v-if="comboProduct && currentGroup"
            class="flex flex-col h-full pb-36 px-1"
        >
            <div
                class="mb-4 bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-800 flex flex-col gap-1 shadow-sm"
            >
                <div
                    class="flex items-center justify-between text-xs font-bold text-gray-500"
                >
                    <span
                        >PASSO {{ currentStepIndex + 1 }} DE
                        {{ comboProduct.combo_groups.length }}</span
                    >
                    <span class="text-primary uppercase">{{
                        currentGroup.name
                    }}</span>
                </div>
                <div
                    class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                >
                    Selecione
                    {{
                        currentGroup.min_choices === currentGroup.max_choices
                            ? currentGroup.min_choices
                            : `de ${currentGroup.min_choices} a ${currentGroup.max_choices}`
                    }}
                    opções.
                </div>
            </div>

            <div
                class="flex-1 overflow-y-auto hide-scrollbar pb-4 flex flex-col gap-6"
            >
                <div class="flex flex-col gap-2">
                    <h4
                        class="text-xs font-black text-gray-400 uppercase tracking-wider"
                    >
                        Opções Disponíveis
                    </h4>
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="item in currentGroup.items"
                            :key="item.id"
                            class="p-3 bg-white dark:bg-gray-900 rounded-xl border-2 transition-all flex flex-col shadow-sm group"
                            :class="[
                                !canAddMore && currentGroup.max_choices > 1
                                    ? 'opacity-50 grayscale cursor-not-allowed border-gray-100'
                                    : 'cursor-pointer border-gray-200 dark:border-gray-800 hover:border-blue-400',
                                expandedCatalogItemId === item.id
                                    ? 'border-blue-500 ring-2 ring-blue-500/20'
                                    : '',
                            ]"
                            @click="expandCatalogItem(item)"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span
                                        class="font-bold text-gray-800 dark:text-gray-100 text-[15px] group-hover:text-blue-600 transition-colors"
                                        >{{ item.product_name }}</span
                                    >
                                    <span
                                        v-if="item.additional_price > 0"
                                        class="text-sm text-green-600 font-semibold"
                                    >
                                        +
                                        {{
                                            formatCurrency(
                                                item.additional_price,
                                            )
                                        }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i
                                        v-if="hasModifiers(item)"
                                        class="pi pi-sliders-h text-gray-400 text-sm"
                                        title="Customizável"
                                    ></i>
                                    <i
                                        class="pi pi-chevron-down text-gray-300 dark:text-gray-600 transition-transform"
                                        :class="{
                                            'rotate-180 text-blue-500':
                                                expandedCatalogItemId ===
                                                item.id,
                                        }"
                                    ></i>
                                </div>
                            </div>

                            <div
                                v-if="expandedCatalogItemId === item.id"
                                class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 flex gap-2"
                                @click.stop
                            >
                                <Button
                                    v-if="hasModifiers(item)"
                                    label="Modificar"
                                    icon="pi pi-sliders-h"
                                    severity="secondary"
                                    variant="outlined"
                                    class="flex-1"
                                    @click="addCatalogItemToTray(item, true)"
                                />
                                <Button
                                    label="Adicionar"
                                    icon="pi pi-plus"
                                    class="flex-1"
                                    @click="addCatalogItemToTray(item, false)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="currentSelections.length > 0"
                    class="flex flex-col gap-2 mt-4 pt-4 border-t-2 border-dashed border-gray-200 dark:border-gray-800"
                >
                    <h4
                        class="text-xs font-black text-blue-600 dark:text-blue-400 uppercase tracking-wider"
                    >
                        Escolhidos ({{ currentSelections.length }} /
                        {{ currentGroup.max_choices }})
                    </h4>
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="sel in currentSelections"
                            :key="sel.instanceId"
                            class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 rounded-xl flex justify-between items-center shadow-sm"
                        >
                            <div class="flex flex-col pr-2">
                                <span
                                    class="font-bold text-blue-900 dark:text-blue-100"
                                    >{{ sel.item.product_name }}</span
                                >
                                <span
                                    v-if="
                                        sel.item.additional_price > 0 ||
                                        sel.extraPrice > 0
                                    "
                                    class="text-xs font-semibold text-green-600 dark:text-green-400"
                                >
                                    +
                                    {{
                                        formatCurrency(
                                            Number(sel.item.additional_price) +
                                                Number(sel.extraPrice),
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="sel.modifiersText"
                                    class="text-xs text-blue-700/80 dark:text-blue-300/80 italic mt-0.5 leading-tight"
                                >
                                    {{ sel.modifiersText }}
                                </span>
                            </div>
                            <div
                                class="flex gap-1 shrink-0 bg-white dark:bg-gray-900 rounded-lg p-0.5 border border-blue-100 dark:border-blue-900"
                            >
                                <Button
                                    v-if="hasModifiers(sel.item)"
                                    icon="pi pi-pencil"
                                    variant="text"
                                    size="small"
                                    class="w-8 h-8 p-0 text-blue-600"
                                    title="Editar Adicionais"
                                    @click="openModifiers(sel)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    variant="text"
                                    size="small"
                                    class="w-8 h-8 p-0"
                                    title="Remover"
                                    @click="removeSelection(sel.instanceId)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="absolute bottom-0 left-0 w-full p-5 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 flex flex-col gap-4 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)]"
        >
            <div class="flex justify-between items-center px-1">
                <span
                    class="text-gray-500 font-semibold text-sm uppercase tracking-wide"
                    >Preço Parcial</span
                >
                <span
                    class="text-2xl font-black text-blue-600 dark:text-blue-400"
                    >{{ formatCurrency(wizardTotal) }}</span
                >
            </div>
            <div class="flex gap-2">
                <Button
                    :label="currentStepIndex === 0 ? 'Cancelar' : 'Voltar'"
                    severity="secondary"
                    variant="outlined"
                    class="flex-1"
                    size="large"
                    @click="handleBackOrCancel"
                />
                <Button
                    :label="isLastStep ? 'Finalizar Combo' : 'Próximo Passo'"
                    icon="pi pi-arrow-right"
                    class="flex-1"
                    size="large"
                    :disabled="!isCurrentStepValid"
                    @click="nextStep"
                />
            </div>
        </div>
    </Drawer>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import Drawer from "primevue/drawer";
import Button from "primevue/button";
import { formatCurrency } from "@/Utils/formatters";

const props = defineProps({
    visible: Boolean,
    comboProduct: Object,
    globalProductsList: Array,
});

const emit = defineEmits([
    "update:visible",
    "open-item-modifiers",
    "confirm-combo",
]);

const currentStepIndex = ref(0);
const selections = ref({});
const activeInstanceId = ref(null);
const expandedCatalogItemId = ref(null); // 🚀 Controla qual card está aberto

const currentGroup = computed(
    () => props.comboProduct?.combo_groups[currentStepIndex.value] || null,
);
const isLastStep = computed(
    () =>
        currentStepIndex.value === props.comboProduct?.combo_groups.length - 1,
);
const currentSelections = computed(() =>
    currentGroup.value ? selections.value[currentGroup.value.id] || [] : [],
);

const canAddMore = computed(() => {
    if (!currentGroup.value) return false;
    return currentSelections.value.length < currentGroup.value.max_choices;
});

watch(
    () => props.comboProduct,
    (newVal) => {
        currentStepIndex.value = 0;
        selections.value = {};
        if (newVal?.combo_groups) {
            newVal.combo_groups.forEach((g) => {
                selections.value[g.id] = [];
            });
        }
    },
    { immediate: true },
);

const getFullProductInfo = (comboItem) =>
    props.globalProductsList.find((p) => p.id === comboItem.product_id);
const hasModifiers = (comboItem) => {
    const fullProd = getFullProductInfo(comboItem);
    return fullProd && fullProd.modifiers && fullProd.modifiers.length > 0;
};

// 🚀 Apenas expande o Card
const expandCatalogItem = (comboItem) => {
    if (!canAddMore.value && currentGroup.value.max_choices > 1) return; // Bloqueia clique se já encheu a cota
    expandedCatalogItemId.value =
        expandedCatalogItemId.value === comboItem.id ? null : comboItem.id;
};

// 🚀 Envia para a Bandeja
const addCatalogItemToTray = (comboItem, openModDrawer = false) => {
    if (!canAddMore.value && currentGroup.value.max_choices > 1) return;

    const newSelection = {
        instanceId: Date.now() + Math.random(),
        item: comboItem,
        modifiersText: "",
        extraPrice: 0,
    };

    if (currentGroup.value.max_choices === 1) {
        selections.value[currentGroup.value.id] = [newSelection];
    } else {
        selections.value[currentGroup.value.id].push(newSelection);
    }

    expandedCatalogItemId.value = null; // Fecha o card após adicionar

    if (openModDrawer && hasModifiers(comboItem)) {
        openModifiers(newSelection);
    }
};

const openModifiers = (selectionInstance) => {
    activeInstanceId.value = selectionInstance.instanceId;
    const fullProdInfo = getFullProductInfo(selectionInstance.item);
    emit("open-item-modifiers", fullProdInfo);
};

const removeSelection = (instanceId) => {
    const groupArray = selections.value[currentGroup.value.id];
    const idx = groupArray.findIndex((s) => s.instanceId === instanceId);
    if (idx > -1) groupArray.splice(idx, 1);
};

const injectModifiersIntoCurrentSelection = (modifiersData) => {
    if (!currentGroup.value) return;
    const groupArray = selections.value[currentGroup.value.id];
    const target = groupArray.find(
        (s) => s.instanceId === activeInstanceId.value,
    );
    if (target) {
        target.modifiersText = modifiersData.text;
        target.extraPrice = modifiersData.extraPrice;
        target.structuredModifiers = modifiersData.structuredModifiers; // 🚀 AQUI! Guarda os IDs no item do combo
    }
};

defineExpose({ injectModifiersIntoCurrentSelection });

const isCurrentStepValid = computed(() => {
    if (!currentGroup.value) return false;
    return currentSelections.value.length >= currentGroup.value.min_choices;
});

const wizardTotal = computed(() => {
    if (!props.comboProduct) return 0;
    let total = Number(props.comboProduct.price);
    Object.values(selections.value).forEach((groupArray) => {
        groupArray.forEach((sel) => {
            total +=
                Number(sel.item.additional_price || 0) +
                Number(sel.extraPrice || 0);
        });
    });
    return total;
});

const handleBackOrCancel = () => {
    if (currentStepIndex.value > 0) currentStepIndex.value--;
    else emit("update:visible", false);
};

const nextStep = () => {
    if (!isCurrentStepValid.value) return;

    if (!isLastStep.value) {
        currentStepIndex.value++;
        expandedCatalogItemId.value = null; // Reseta a expansão ao mudar de passo
    } else {
        // 🚀 CRIA UMA ESTRUTURA RICA (ARRAY) PARA O CARRINHO LER A INDENTAÇÃO E SALVAR NO ESTOQUE!
        let structuredComboItems = [];

        Object.keys(selections.value).forEach((groupId) => {
            const groupArray = selections.value[groupId];
            groupArray.forEach((sel) => {
                structuredComboItems.push({
                    product_id: sel.item.product_id, // 🚀 Bom garantir o envio do produto base também
                    name: sel.item.product_name,
                    modifiersText: sel.modifiersText,
                    structuredModifiers: sel.structuredModifiers || [], // 🚀 AQUI! Repassa os IDs pra frente!
                });
            });
        });

        emit("confirm-combo", {
            structuredItems: structuredComboItems,
            extraPrice: wizardTotal.value - Number(props.comboProduct.price),
        });
    }
};
</script>

<style scoped>
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
