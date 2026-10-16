<template>
    <component
        :is="mode === 'inline' ? 'div' : AdaptiveModal"
        v-model:visible="isVisible"
        class="flex flex-col h-full w-full"
        :class="mode === 'inline' ? 'p-6' : ''"
    >
        <!-- CABEÇALHO -->
        <div class="flex flex-col items-center justify-center gap-3 shrink-0 mb-4 md:mb-6">
            <div class="w-14 h-14 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 flex items-center justify-center">
                <i class="pi pi-arrows-alt text-2xl"></i>
            </div>
            <div class="flex flex-col items-center text-center">
                <h2 class="text-xl font-black text-surface-text leading-tight">Transformar #{{ $formatNumber(order?.id) }}</h2>
                <span class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">Atual: {{ currentTypeLabel }}</span>
            </div>
        </div>

        <!-- CORPO DO FORMULÁRIO -->
        <div class="flex flex-col gap-4 flex-1 overflow-y-auto hide-scrollbar">
            <p class="text-sm text-stone-600 dark:text-stone-400 shrink-0 text-center">
                Para onde deseja transferir este pedido?
            </p>

            <div class="flex flex-col gap-3 shrink-0">
                <SelectableCard
                    v-if="order?.type !== 'table'"
                    @select="selectTarget('table')"
                    :is-selected="targetType === 'table'"
                    color="primary"
                    icon="pi-table"
                    title="Transferir para Mesa"
                    subtitle="Solicita a seleção da mesa no mapa e remove a taxa de entrega."
                />

                <SelectableCard
                    v-if="order?.type !== 'counter'"
                    @select="selectTarget('counter')"
                    :is-selected="targetType === 'counter'"
                    color="purple"
                    icon="pi-shopping-bag"
                    title="Mudar para Balcão/Comanda"
                    subtitle="Transforma em pedido de retirada e remove taxa de entrega."
                />

                <SelectableCard
                    v-if="order?.type !== 'delivery'"
                    @select="selectTarget('delivery')"
                    :is-selected="targetType === 'delivery'"
                    color="red"
                    icon="pi-motorcycle"
                    title="Converter para Delivery"
                    subtitle="Habilita entrega e abre o preenchimento de endereço e taxa."
                />
            </div>

            <div v-if="targetType === 'table'" class="mt-2 animate-fade-in flex-1 flex flex-col min-h-[250px]">
                <div class="text-xs font-black text-stone-500 uppercase tracking-widest mb-2 px-1 text-center">Selecione a nova Mesa</div>
                <div class="flex-1 bg-surface-2/50 border border-surface-border/50 rounded-2xl p-2 overflow-y-auto hide-scrollbar shadow-inner">
                    <RoutingTable
                        v-model="selectedTable"
                        v-model:identifier="customerName"
                        :table-areas="tableAreas"
                    />
                </div>
            </div>

            <div v-if="targetType === 'counter'" class="mt-2 animate-fade-in shrink-0">
                <label class="block text-xs font-medium text-stone-500 uppercase tracking-widest mb-2 text-center">Nome / Número da Comanda</label>
                <input
                    type="text"
                    v-model="targetIdentifier"
                    placeholder="Ex: João 45"
                    class="w-full p-3 rounded-xl border border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-900 focus:ring-2 focus:ring-purple-500 outline-none transition-all font-medium text-lg text-center"
                />
            </div>
        </div>

        <!-- RODAPÉ -->
        <div class="flex gap-2 w-full shrink-0 mt-auto pt-6 border-t border-surface-border/50">
            <Button label="Cancelar" variant="text" severity="secondary" @click="handleCancel" class="flex-1 font-medium" />
            <Button label="Confirmar" severity="help" class="flex-1 font-black shadow-md" :disabled="!canSubmit" @click="confirmTransformation" />
        </div>
    </component>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import Button from "primevue/button";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";
import SelectableCard from "@/Components/UI/SelectableCard.vue";
import RoutingTable from "@/Pages/Pos/Components/Cart/RoutingTable.vue";

const props = defineProps({
    visible: Boolean,
    order: Object,
    tableAreas: { type: Array, default: () => [] },
    mode: { type: String, default: 'modal' }
});

const emit = defineEmits(["update:visible", "transform", "cancel"]);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const targetType = ref("");
const targetIdentifier = ref("");
const selectedTable = ref(null);
const customerName = ref("");

const resetState = () => {
    targetType.value = "";
    targetIdentifier.value = "";
    selectedTable.value = null;
    customerName.value = "";
};

watch(() => props.order, resetState);
watch(isVisible, (val) => { if (!val) resetState(); });

const currentTypeLabel = computed(() => {
    const types = { table: "Mesa", counter: "Balcão", delivery: "Delivery" };
    return props.order ? types[props.order.type] : "";
});

const selectTarget = (type) => {
    targetType.value = type;
    targetIdentifier.value = "";
    selectedTable.value = null;
    customerName.value = "";
};

const canSubmit = computed(() => {
    if (!targetType.value) return false;
    if (targetType.value === "table" && !selectedTable.value?.id && !selectedTable.value?.name) return false;
    if (targetType.value === "counter" && targetIdentifier.value.trim() === "") return false;
    return true;
});

const handleCancel = () => {
    emit("cancel");
    if (props.mode !== 'inline') isVisible.value = false;
};

const confirmTransformation = () => {
    const finalIdentifier = targetType.value === "table"
        ? (selectedTable.value?.name || "")
        : targetIdentifier.value;

    emit("transform", {
        order: props.order,
        new_type: targetType.value,
        newType: targetType.value,
        type: targetType.value,
        table_id: selectedTable.value?.id || null,
        dining_table_id: selectedTable.value?.id || null,
        table_number: selectedTable.value?.name || null,
        identifier: finalIdentifier,
        customer_name: targetType.value === "table" ? customerName.value : targetIdentifier.value
    });

    if (props.mode !== 'inline') isVisible.value = false;
};
</script>
