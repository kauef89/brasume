<template>
    <Drawer
        :visible="visible"
        @update:visible="$emit('update:visible', $context)"
        position="right"
        :modal="false"
        :dismissable="false"
        :pt="{ root: { style: 'z-index: 1100 !important;' } }"
        class="!w-full md:!w-[450px] !bg-gray-50 dark:!bg-gray-900 shadow-xl border-l border-gray-200 dark:border-gray-700 transition-all duration-300 ease-in-out"
        :style="{ right: rightOffset + 'px' }"
    >
        <template #header>
            <div class="font-bold text-2xl text-gray-900 dark:text-white">
                {{ product?.name }}
            </div>
        </template>

        <div
            v-if="product"
            class="flex flex-col gap-6 h-full pb-48 overflow-y-auto px-1 pt-2"
        >
            <div
                v-for="group in ingredientGroups"
                :key="group.id"
                class="flex flex-col gap-3"
            >
                <div>
                    <h3
                        class="font-bold text-lg text-gray-800 dark:text-gray-100 uppercase tracking-wide"
                    >
                        {{ group.display_name || group.name }}
                    </h3>
                    <div
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 flex gap-2 mt-1"
                    >
                        <span
                            v-if="group.min_selections > 0"
                            class="bg-gray-200 dark:bg-gray-800 px-2 py-1 rounded"
                            >Mín: {{ group.min_selections }}</span
                        >
                        <span
                            v-if="group.max_selections > 0"
                            class="bg-gray-200 dark:bg-gray-800 px-2 py-1 rounded"
                            >Máx: {{ group.max_selections }}</span
                        >
                        <span
                            v-if="group.max_free > 0"
                            class="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 px-2 py-1 rounded"
                            >{{ group.max_free }} Gratuitos</span
                        >
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="opt in getOptionsWithJoker(group)"
                        :key="opt.id"
                        :variant="
                            isSelected(group, opt) ? 'filled' : 'outlined'
                        "
                        :severity="
                            isSelected(group, opt)
                                ? opt.isJoker
                                    ? 'warn'
                                    : 'primary'
                                : 'secondary'
                        "
                        class="rounded-xl !px-4 !py-2 transition-all duration-200 border-2"
                        :class="[
                            isSelected(group, opt)
                                ? opt.isJoker
                                    ? 'border-orange-500'
                                    : 'border-primary'
                                : 'border-gray-300 dark:border-gray-600 hover:border-primary/50',
                            {
                                'opacity-40 grayscale cursor-not-allowed':
                                    isDisabled(group, opt),
                            },
                        ]"
                        @click="toggleSelection(group, opt)"
                    >
                        <template #default>
                            <span class="font-semibold">{{ opt.name }}</span>
                            <span
                                v-if="getEffectivePrice(group, opt) > 0"
                                class="ml-2 text-xs font-black bg-white/20 px-1.5 py-0.5 rounded"
                                :class="
                                    isSelected(group, opt)
                                        ? 'text-white'
                                        : 'text-gray-500'
                                "
                            >
                                +{{
                                    formatCurrency(
                                        getEffectivePrice(group, opt),
                                    )
                                }}
                            </span>
                        </template>
                    </Button>
                </div>
            </div>

            <Accordion
                v-if="accordionGroups.length > 0"
                :value="activeAccordionPanel"
                class="flex flex-col gap-2"
            >
                <AccordionPanel
                    v-for="group in accordionGroups"
                    :key="group.id"
                    :value="group.id"
                >
                    <AccordionHeader>
                        <div class="flex flex-col gap-1 text-left">
                            <span
                                class="font-bold text-gray-800 dark:text-gray-100 uppercase"
                                >{{ group.display_name || group.name }}</span
                            >
                            <div
                                class="text-xs font-normal text-gray-500 dark:text-gray-400 flex gap-2"
                            >
                                <span v-if="group.min_selections > 0"
                                    >Mín: {{ group.min_selections }}</span
                                >
                                <span v-if="group.max_selections > 0"
                                    >Máx: {{ group.max_selections }}</span
                                >
                            </div>
                        </div>
                    </AccordionHeader>
                    <AccordionContent>
                        <div
                            v-if="group.allow_quantity"
                            class="flex flex-col gap-2 pt-2"
                        >
                            <div
                                v-for="opt in group.options"
                                :key="opt.id"
                                class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm"
                            >
                                <div class="flex flex-col">
                                    <span
                                        class="font-semibold text-gray-800 dark:text-gray-100"
                                        >{{ opt.name }}</span
                                    >
                                    <span
                                        v-if="Number(opt.price) > 0"
                                        class="text-sm text-blue-600 dark:text-blue-400"
                                        >+ {{ formatCurrency(opt.price) }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-1"
                                >
                                    <Button
                                        icon="pi pi-minus"
                                        variant="text"
                                        size="small"
                                        class="w-8 h-8 p-0"
                                        @click="updateQty(group, opt, -1)"
                                        :disabled="getQty(group, opt) <= 0"
                                    />
                                    <span
                                        class="w-6 text-center font-bold text-gray-900 dark:text-white"
                                        >{{ getQty(group, opt) }}</span
                                    >
                                    <Button
                                        icon="pi pi-plus"
                                        variant="text"
                                        size="small"
                                        class="w-8 h-8 p-0"
                                        @click="updateQty(group, opt, 1)"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex flex-wrap gap-2 pt-2">
                            <Button
                                v-for="opt in group.options"
                                :key="opt.id"
                                :variant="
                                    isSelected(group, opt)
                                        ? 'filled'
                                        : 'outlined'
                                "
                                :severity="
                                    isSelected(group, opt)
                                        ? 'primary'
                                        : 'secondary'
                                "
                                class="rounded-xl !px-4 !py-2 border-2"
                                :class="
                                    isSelected(group, opt)
                                        ? 'border-primary'
                                        : 'border-gray-300 dark:border-gray-600'
                                "
                                @click="toggleSelection(group, opt)"
                            >
                                <span class="font-semibold">{{
                                    opt.name
                                }}</span>
                                <span
                                    v-if="Number(opt.price) > 0"
                                    class="ml-2 text-xs font-black"
                                    >+{{ formatCurrency(opt.price) }}</span
                                >
                            </Button>
                        </div>
                    </AccordionContent>
                </AccordionPanel>
            </Accordion>

            <div class="flex flex-col gap-2 mt-4">
                <label
                    class="font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wide text-sm"
                    >Observação Extra</label
                >
                <Textarea
                    v-model="itemObservation"
                    rows="1"
                    autoResize
                    placeholder="Ex: Carne bem passada, embalar separado..."
                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                />
            </div>
        </div>

        <div
            class="absolute bottom-0 left-0 w-full p-5 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 flex flex-col gap-4 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)]"
        >
            <div class="flex justify-between items-center px-1">
                <span
                    class="text-gray-500 font-semibold uppercase text-sm tracking-wide"
                    >Total do Item</span
                >
                <span
                    class="text-2xl font-black text-blue-600 dark:text-blue-400"
                    >{{ formatCurrency(currentTotal) }}</span
                >
            </div>
            <div class="flex gap-2 w-full mt-auto">
                <Button
                    label="Cancelar"
                    severity="secondary"
                    variant="outlined"
                    class="w-1/3"
                    size="large"
                    @click="closeDrawer"
                />
                <SplitButton
                    :label="isEditMode ? 'Alterar' : 'Adicionar'"
                    icon="pi pi-check"
                    :model="splitButtonItems"
                    @click="confirmAndAdd(false)"
                    :disabled="!isSelectionValid"
                    class="w-2/3 custom-split-btn"
                />
            </div>
        </div>
    </Drawer>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import Drawer from "primevue/drawer";
import Button from "primevue/button";
import SplitButton from "primevue/splitbutton";
import Accordion from "primevue/accordion";
import AccordionPanel from "primevue/accordionpanel";
import AccordionHeader from "primevue/accordionheader";
import AccordionContent from "primevue/accordioncontent";
import Textarea from "primevue/textarea";

// 🚀 IMPORTAÇÕES GLOBAIS
import { formatCurrency } from "@/Utils/formatters";
import { useModifiers } from "@/Composables/useModifiers";

const props = defineProps({
    visible: Boolean,
    product: Object,
    isEditMode: { type: Boolean, default: false },
    rightOffset: { type: Number, default: 400 },
});
const emit = defineEmits(["update:visible", "confirm"]);

// 🚀 INSTANCIANDO O COMPOSABLE (Agora puxando o buildStructuredModifiers!)
const {
    initModifiers,
    getOptionsWithJoker,
    isSelected,
    isDisabled,
    toggleSelection,
    getQty,
    updateQty,
    getEffectivePrice,
    calculateTotal,
    isValid,
    buildModifiersText,
    buildStructuredModifiers, // 🚀 AQUI ESTÁ ELE!
} = useModifiers();

const itemObservation = ref("");
const activeAccordionPanel = ref("0");

// Ordenação Visível
const sortedModifiers = computed(() => {
    if (!props.product?.modifiers) return [];
    const order = { ingredient: 1, additional: 2, add_on: 2, observation: 3 };
    return [...props.product.modifiers].sort(
        (a, b) => (order[a.type] || 99) - (order[b.type] || 99),
    );
});

const ingredientGroups = computed(() =>
    sortedModifiers.value.filter((g) => g.type === "ingredient"),
);
const accordionGroups = computed(() =>
    sortedModifiers.value.filter((g) => g.type !== "ingredient"),
);

const splitButtonItems = computed(() => [
    {
        label: props.isEditMode
            ? "Alterar para Viagem"
            : "Adicionar para Viagem",
        icon: "pi pi-shopping-bag",
        command: () => confirmAndAdd(true),
    },
]);

// Monitora as mudanças de Produto e reseta as seleções
watch(
    () => props.product,
    (newVal) => {
        itemObservation.value = "";
        initModifiers(newVal?.modifiers);
        if (newVal?.modifiers && accordionGroups.value.length > 0) {
            activeAccordionPanel.value = accordionGroups.value[0].id;
        }
    },
    { immediate: true },
);

// Computados Reativos com o Composable
const currentTotal = computed(() =>
    calculateTotal(props.product?.price, props.product?.modifiers),
);
const isSelectionValid = computed(() => isValid(props.product?.modifiers));

const closeDrawer = () => emit("update:visible", false);

// 🚀 AQUI PREPARAMOS E ENVIAMOS OS DADOS PARA O BANCO E ESTOQUE
const confirmAndAdd = (isTakeaway = false) => {
    if (!isSelectionValid.value) return;

    const extraPrice = currentTotal.value - Number(props.product.price);
    let text = buildModifiersText(props.product.modifiers);
    const structured = buildStructuredModifiers(props.product.modifiers); // 🚀 Pegamos a array de IDs!

    if (itemObservation.value.trim()) {
        text += text
            ? ` • Obs: ${itemObservation.value}`
            : `Obs: ${itemObservation.value}`;

        // Adiciona a observação na mesma estrutura para ser gravada na tabela
        structured.push({
            id: null,
            name: `Obs: ${itemObservation.value}`,
            price: 0,
            qty: 1,
            is_joker: true,
        });
    }

    emit("confirm", {
        text: text,
        extraPrice: extraPrice,
        isTakeaway: isTakeaway,
        structuredModifiers: structured, // 🚀 Mandando pro Carrinho (useCart)
    });
};
</script>

<style>
.custom-split-btn {
    display: flex;
}
.custom-split-btn .p-button:first-child {
    flex-grow: 1;
    justify-content: center;
}
</style>
