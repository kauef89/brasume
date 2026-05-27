<template>
    <Dialog
        v-model:visible="isVisible"
        modal
        class="w-[800px] max-w-[95vw]"
        :closable="true"
        :draggable="false"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="bg-blue-100 dark:bg-blue-900/30 text-blue-600 p-2.5 rounded-lg"
                >
                    <i class="pi pi-list text-xl"></i>
                </div>
                <div>
                    <h2
                        class="text-xl font-black text-gray-800 dark:text-gray-100"
                    >
                        Modificar Pedido #{{ order?.id }}
                    </h2>
                    <span
                        class="text-sm font-bold text-gray-500 uppercase tracking-widest"
                        >{{ formatName(order?.customer_name) }}</span
                    >
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-6 mt-2">
            <div>
                <h3
                    class="text-sm font-black text-gray-500 uppercase tracking-widest mb-3 border-b border-gray-100 dark:border-gray-800 pb-2"
                >
                    Itens Ativos
                </h3>
                <div class="flex flex-col gap-2">
                    <div
                        v-if="!activeItemsGrouped.length"
                        class="text-center text-gray-400 py-4 text-sm font-medium"
                    >
                        Nenhum item ativo neste pedido.
                    </div>

                    <div
                        v-for="item in activeItemsGrouped"
                        :key="item.id"
                        class="flex flex-col bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-3 rounded-xl shadow-sm hover:border-gray-300 transition-colors"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4
                                    class="font-bold text-gray-800 dark:text-gray-200 text-lg leading-tight"
                                >
                                    {{ item.name }}
                                </h4>
                                <div
                                    class="text-sm text-gray-500 font-medium mt-0.5"
                                >
                                    {{ item.quantity }}x
                                    {{ formatCurrency(item.unit_price) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button
                                    @click="$emit('reprint-item', item)"
                                    class="w-9 h-9 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 transition-colors"
                                    title="Reimprimir Comanda"
                                >
                                    <i class="pi pi-print"></i>
                                </button>
                                <button
                                    @click="
                                        $emit(
                                            'modify-item',
                                            item,
                                            item.comboChildren &&
                                                item.comboChildren.length > 0,
                                        )
                                    "
                                    class="w-9 h-9 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-900/20 transition-colors"
                                    title="Alterar Modificadores"
                                >
                                    <i class="pi pi-sliders-h"></i>
                                </button>
                                <button
                                    @click="$emit('swap-item', item)"
                                    class="w-9 h-9 flex items-center justify-center rounded-lg bg-purple-50 text-purple-600 hover:bg-purple-100 dark:bg-purple-900/20 transition-colors"
                                    title="Trocar por outro Item"
                                >
                                    <i class="pi pi-sync"></i>
                                </button>
                                <button
                                    @click="initiateCancellation(item)"
                                    class="w-9 h-9 flex items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/20 transition-colors"
                                    title="Cancelar / Estornar"
                                >
                                    <i class="pi pi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="
                                item.comboChildren &&
                                item.comboChildren.length > 0
                            "
                            class="flex flex-col gap-1 mt-2 pt-2 border-t border-dashed border-gray-200 dark:border-gray-800 text-[13px] text-gray-600 dark:text-gray-400 pl-2"
                        >
                            <div
                                v-for="child in item.comboChildren"
                                :key="child.id"
                                class="flex items-center justify-between py-1"
                            >
                                <div class="flex items-start gap-2">
                                    <span
                                        class="text-gray-300 dark:text-gray-600 mt-0.5"
                                        >└</span
                                    >
                                    <div class="flex flex-col">
                                        <span
                                            class="font-medium text-gray-700 dark:text-gray-300"
                                            >{{ child.name }}</span
                                        >
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button
                                        @click="$emit('reprint-item', child)"
                                        class="w-7 h-7 flex items-center justify-center rounded-md bg-gray-100 text-gray-600 transition-colors"
                                    >
                                        <i class="pi pi-print text-xs"></i>
                                    </button>
                                    <button
                                        @click="
                                            $emit('modify-item', child, false)
                                        "
                                        class="w-7 h-7 flex items-center justify-center rounded-md bg-blue-50/70 text-blue-500 transition-colors"
                                    >
                                        <i class="pi pi-sliders-h text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>

    <ManagerAuthModal
        v-model:visible="isAuthModalVisible"
        :item-name="itemToProcess?.name"
        @confirm="handleConfirmCancellation"
    />
</template>

<script setup>
import { ref, computed } from "vue";
import Dialog from "primevue/dialog";
import ManagerAuthModal from "@/Components/ManagerAuthModal.vue";
import { formatCurrency, formatName } from "@/Utils/formatters"; // Importando formatadores globais!

const props = defineProps({ visible: Boolean, order: Object });
const emit = defineEmits([
    "update:visible",
    "reprint-item",
    "modify-item",
    "swap-item",
    "cancel-item",
]);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const orderItems = computed(() => props.order?.items || []);
const activeItemsGrouped = computed(() => {
    const allActive = orderItems.value.filter((i) => i.status !== "cancelled");
    const parents = allActive.filter(
        (i) => !i.is_combo_item && !i.parent_item_id,
    );
    const children = allActive.filter(
        (i) => i.is_combo_item || i.parent_item_id,
    );
    return parents.map((parent) => ({
        ...parent,
        comboChildren: children.filter((c) => c.parent_item_id === parent.id),
    }));
});

const isAuthModalVisible = ref(false);
const itemToProcess = ref(null);

const initiateCancellation = (item) => {
    itemToProcess.value = item;
    isAuthModalVisible.value = true;
};

// Recebe os dados de dentro do Modal e emite para a página pai
const handleConfirmCancellation = (authData) => {
    emit("cancel-item", {
        item: itemToProcess.value,
        isLoss: authData.isLoss,
        reason: authData.reason,
        password: authData.password,
    });
};
</script>
