<template>
    <div class="flex flex-col h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans relative">

        <!-- 🚀 1. CABEÇALHO MOBILE / GARÇOM -->
        <header class="bg-surface-2 border-b border-surface-border px-4 py-3 shrink-0 flex flex-col gap-2">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <button @click="emit('close')" class="w-9 h-9 rounded-full bg-surface-3 hover:bg-surface-border text-surface-text flex items-center justify-center transition-colors shrink-0">
                        <i class="pi pi-arrow-left text-sm font-bold"></i>
                    </button>
                    <div class="flex flex-col min-w-0">
                        <h1 class="text-lg font-black text-surface-text truncate leading-tight">
                            {{ formattedOrderTitle }}
                        </h1>
                        <span class="text-[11px] font-bold text-surface-muted">
                            Pedido #{{ $formatNumber(order?.id) }} • {{ translatedType }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    <span v-if="isPaid" class="bg-success-500/20 text-success-600 dark:text-success-400 px-2.5 py-1 rounded-lg font-black text-[10px] uppercase tracking-wider flex items-center gap-1">
                        <i class="pi pi-check-circle text-[9px]"></i> Pago
                    </span>
                    <span v-else class="bg-warning-500/10 text-warning-600 dark:text-warning-400 px-2.5 py-1 rounded-lg font-black text-[10px] uppercase tracking-wider">
                        Aberto
                    </span>
                </div>
            </div>

            <!-- CHIPS DE ATALHO RÁPIDO DO GARÇOM -->
            <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar pt-1">
                <button
                    v-if="companySettings?.sicredi_is_active && companySettings?.sicredi_enable_waiter_pix && !isPaid"
                    @click="isPixModalOpen = true"
                    class="h-8 px-3 rounded-full bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-black flex items-center gap-1.5 shrink-0 transition-colors"
                >
                    <i class="pi pi-qrcode text-[10px]"></i>
                    <span>Cobrar via PIX</span>
                </button>
                <button
                    v-if="order?.type === 'table'"
                    @click="handleOpenTransferAll"
                    class="h-8 px-3 rounded-full bg-surface-3 hover:bg-surface-border text-surface-text text-xs font-bold flex items-center gap-1.5 shrink-0 transition-colors"
                >
                    <i class="pi pi-sync text-[10px] text-primary-500"></i>
                    <span>Transferir Mesa</span>
                </button>

                <button
                    v-if="order?.type === 'table'"
                    @click="handlePrintConference"
                    :disabled="isPrintingConference"
                    class="h-8 px-3 rounded-full bg-surface-3 hover:bg-surface-border text-surface-text text-xs font-bold flex items-center gap-1.5 shrink-0 transition-colors"
                >
                    <i class="pi" :class="isPrintingConference ? 'pi-spinner animate-spin text-primary-500' : 'pi-print text-surface-muted'"></i>
                    <span>Imprimir Parcial</span>
                </button>

                <button
                    v-if="order?.type === 'table'"
                    @click="isTransformerVisible = true"
                    class="h-8 px-3 rounded-full bg-surface-3 hover:bg-surface-border text-surface-text text-xs font-bold flex items-center gap-1.5 shrink-0 transition-colors"
                >
                    <i class="pi pi-arrow-right-arrow-left text-[10px] text-purple-500"></i>
                    <span>Mudar Tipo</span>
                </button>
            </div>
        </header>

        <!-- 🚀 2. LISTA DE ITENS DA MESA -->
        <main class="flex-1 min-h-0 overflow-y-auto bg-surface-1">
            <CheckoutItemsList
                mode="details"
                :display-items="activeItemsGrouped"
                :selected-ids="[]"
                :is-all-selected="false"
                :order-payments="order?.payments || []"
                @action="handleItemAction"
            />
        </main>

        <!-- 🚀 3. BARRA DE AÇÃO FIXA INFERIOR (POLEGAR DO GARÇOM) -->
        <footer class="bg-surface-2 border-t border-surface-border p-3 shrink-0 flex items-center justify-between gap-3 shadow-lg">
            <div class="flex flex-col pl-1">
                <span class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Total</span>
                <span class="text-xl font-black text-primary-smart leading-none">
                    {{ $formatCurrency(order?.total || 0) }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="openOrderOptions = true"
                    class="h-12 w-12 rounded-xl bg-surface-3 text-surface-muted hover:text-surface-text flex items-center justify-center transition-colors border border-surface-border"
                    title="Mais Opções"
                >
                    <i class="pi pi-ellipsis-v text-sm"></i>
                </button>

                <button
                    @click="handleAddItems"
                    :disabled="order?.status === 'closed' || order?.status === 'cancelled'"
                    class="h-12 px-5 rounded-xl bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-black font-black text-xs uppercase tracking-wider flex items-center gap-2 shadow-md transition-all disabled:opacity-50"
                >
                    <i class="pi pi-plus text-xs font-black"></i>
                    <span>Lançar Itens</span>
                </button>
            </div>
        </footer>

        <!-- 🚀 4. BOTTOM-SHEET DE OPÇÕES ADICIONAIS DO GARÇOM -->
        <AdaptiveModal v-model:visible="openOrderOptions">
            <div class="p-6 flex flex-col gap-3">
                <h3 class="text-base font-black text-surface-text mb-1">Ações na Mesa</h3>

                <button
                    @click="handlePrintConference(); openOrderOptions = false;"
                    class="w-full h-12 px-4 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-text font-bold text-xs uppercase tracking-wider flex items-center gap-3 transition-colors border border-surface-border"
                >
                    <i class="pi pi-print text-primary-500"></i>
                    <span>Imprimir Pré-Conta (Conferência)</span>
                </button>

                <button
                    v-if="order?.type === 'table'"
                    @click="handleOpenTransferAll(); openOrderOptions = false;"
                    class="w-full h-12 px-4 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-text font-bold text-xs uppercase tracking-wider flex items-center gap-3 transition-colors border border-surface-border"
                >
                    <i class="pi pi-sync text-purple-500"></i>
                    <span>Transferir Todos os Itens de Mesa</span>
                </button>

                <button
                    @click="triggerSecureAction(order, 'cancel_order'); openOrderOptions = false;"
                    class="w-full h-12 px-4 rounded-xl bg-danger-500/10 text-danger-500 hover:bg-danger-500/20 font-bold text-xs uppercase tracking-wider flex items-center gap-3 transition-colors mt-2"
                >
                    <i class="pi pi-times-circle"></i>
                    <span>Cancelar Pedido Inteiro</span>
                </button>
            </div>
        </AdaptiveModal>

        <!-- 🚀 5. MODAIS OPERACIONAIS COMPARTILHADOS -->
        <TransferItemsModal
            v-model:visible="isTransferModalVisible"
            :source-order="order"
            :item-ids="transferItemIds"
            :table-areas="tableAreas"
            @confirm="handleExecuteTransfer"
        />

        <OrderTransformer
            v-model:visible="isTransformerVisible"
            :order="order"
            :table-areas="tableAreas"
            @transform="handleTransformation"
        />

        <OperationReasonModal
            v-model:visible="isReasonModalVisible"
            mode="modal"
            :item-name="authActionType === 'cancel_order' ? `Pedido #${$formatNumber(itemToProcess?.id)}` : itemToProcess?.name"
            :action-type="authActionType"
            :requires-pin="authNeedsPin"
            :item-to-process="itemToProcess"
            @cancel="isReasonModalVisible = false"
            @confirm="handleConfirmAuth"
        />
            <OrderPixModal
            v-if="order"
            v-model:visible="isPixModalOpen"
            :order="order"
        />
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { formatCurrency, formatNumber } from "@/Utils/formatters";

import CheckoutItemsList from "@/Pages/Pos/Components/Checkout/CheckoutItemsList.vue";
import TransferItemsModal from "@/Pages/Shared/Order/TransferItemsModal.vue";
import OrderTransformer from "@/Pages/Shared/Order/OrderTransformer.vue";
import OperationReasonModal from "@/Pages/Pos/Components/Checkout/OperationReasonModal.vue";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";
import OrderPixModal from "@/Pages/Shared/OrderPixModal.vue";

const isPixModalOpen = ref(false);

import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useItemActions } from "@/Pages/Shared/Composables/useItemActions";
import { useSecureAction } from "@/Composables/useSecureAction";

const props = defineProps({
    order: { type: Object, required: true },
    tableAreas: { type: Array, default: () => [] },
    companySettings: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["close", "add-items"]);

const toast = useToast();
const { getOrderTitle, groupActiveItems } = useOrderFormatting();

const formattedOrderTitle = computed(() => getOrderTitle(props.order));
const translatedType = computed(() => {
    const t = { table: "Mesa", counter: "Balcão", delivery: "Delivery" };
    return t[props.order?.type] || "Mesa";
});
const isPaid = computed(() => props.order?.payment_status === "paid");
const activeItemsGrouped = computed(() => groupActiveItems(props.order?.items));

// Estados de modais
const openOrderOptions = ref(false);
const isTransferModalVisible = ref(false);
const isTransformerVisible = ref(false);
const transferItemIds = ref([]);
const isPrintingConference = ref(false);

// Ações nos itens (cancelar, duplicar, trocar)
const dummyRef = ref(true);
const { handleItemDuplicate, handleItemModify, handleItemSwap, handleItemCancel } = useItemActions(
    computed(() => props.order),
    dummyRef,
    'team.app'
);

const { isReasonModalVisible, itemToProcess, authActionType, authNeedsPin, initiateSecureAction } = useSecureAction(
    props.companySettings
);

const handleItemAction = (payload) => {
    const { type, item } = payload;
    if (type === "duplicate") handleItemDuplicate(item);
    else if (type === "transfer") {
        transferItemIds.value = [item.id];
        isTransferModalVisible.value = true;
    } else if (type === "edit") {
        triggerSecureAction(item, "edit");
    } else if (type === "cancel") {
        triggerSecureAction(item, "cancel");
    }
};

const triggerSecureAction = (item, type) => {
    initiateSecureAction(item, type);
};

const handleConfirmAuth = (authData) => {
    if (authActionType.value === "edit") {
        handleItemModify({ item: itemToProcess.value, authData });
    } else if (authActionType.value === "swap") {
        handleItemSwap({ item: itemToProcess.value, authData });
    } else if (authActionType.value === "cancel_order") {
        router.post(route('operations.orders.cancel', itemToProcess.value.id),
            {
                reason: authData.reason,
            },
            {
                preserveState: true,
                preserveScroll: true,
                is_loss: authData.isLoss,
                lost_items: authData.lostItems,
                pin: authData.password,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isReasonModalVisible.value = false;
                    toast.add({ severity: "success", summary: "Cancelado", detail: "Pedido cancelado.", life: 3000 });
                    emit("close");
                },
                onError: (err) => {
                    toast.add({ severity: "error", summary: "Erro", detail: err.pin || "Erro ao cancelar.", life: 4000 });
                },
            }
        );
        return;
    } else {
        handleItemCancel({
            item: itemToProcess.value,
            isLoss: authData.isLoss,
            reason: authData.reason,
            password: authData.password,
        });
    }

    isReasonModalVisible.value = false;
};

// Navegação para o cardápio
const handleAddItems = () => {
    localStorage.setItem("pos_draft_type", props.order?.type || "table");
    localStorage.setItem("pos_return_url", "/team");
    localStorage.setItem("pos_return_order_id", props.order?.id);
    emit("add-items", props.order);
};

// Transferências
const handleOpenTransferAll = () => {
    transferItemIds.value = activeItemsGrouped.value.map((i) => i.id);
    isTransferModalVisible.value = true;
};

const handleExecuteTransfer = (payload) => {
    router.post(route("operations.transfer-items"), payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isTransferModalVisible.value = false;
            void(0)
        },
    });
};

const handleTransformation = (payload) => {
    router.post(route("operations.orders.transform", props.order?.id), payload, {
        onSuccess: () => {
            isTransformerVisible.value = false;
            void(0)
        },
    });
};

// Impressão da parcial
const handlePrintConference = () => {
    if (isPrintingConference.value) return;
    isPrintingConference.value = true;

    router.post(
        route("operations.print-conference", props.order?.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isPrintingConference.value = false;
            },
            onSuccess: () => {
                void(0)
            },
        }
    );
};
</script>
