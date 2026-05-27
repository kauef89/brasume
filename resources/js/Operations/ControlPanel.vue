<template>
    <div
        class="flex flex-col h-screen w-screen bg-gray-100 dark:bg-gray-950 overflow-hidden font-sans text-gray-900 dark:text-gray-100"
    >
        <header
            class="bg-white dark:bg-gray-900 shadow-sm z-10 p-3 px-6 flex justify-between items-center shrink-0"
        >
            <div>
                <h1
                    class="text-xl font-black text-gray-800 dark:text-white leading-tight"
                >
                    Painel de Controle
                </h1>
                <div class="text-xs text-gray-500 font-medium">
                    Supervisão Geral da Operação ativa
                </div>
            </div>

            <div
                class="flex bg-gray-100 dark:bg-gray-800 p-1 rounded-lg border border-gray-200 dark:border-gray-700"
            >
                <button
                    @click="activeTab = 'active'"
                    class="px-6 py-1.5 rounded-md text-sm font-bold transition-all"
                    :class="
                        activeTab === 'active'
                            ? 'bg-white dark:bg-gray-700 shadow text-blue-600 dark:text-blue-400'
                            : 'text-gray-500 hover:text-gray-700'
                    "
                >
                    Pedidos Ativos ({{ activeOrders.length }})
                </button>
                <button
                    @click="activeTab = 'history'"
                    class="px-6 py-1.5 rounded-md text-sm font-bold transition-all"
                    :class="
                        activeTab === 'history'
                            ? 'bg-white dark:bg-gray-700 shadow text-blue-600 dark:text-blue-400'
                            : 'text-gray-500 hover:text-gray-700'
                    "
                >
                    Histórico
                </button>
            </div>
        </header>

        <main class="flex-1 overflow-hidden relative">
            <div v-show="activeTab === 'active'" class="absolute inset-0 flex">
                <OpenTabs :orders="onSiteOrders" @open-menu="openContextMenu" />

                <DeliveryKanban
                    :orders="deliveryOrders"
                    @advance-status="handleAdvanceStatus"
                    @open-menu="openContextMenu"
                />
            </div>

            <div
                v-show="activeTab === 'history'"
                class="absolute inset-0 p-6 overflow-y-auto bg-gray-50 dark:bg-gray-950"
            >
                <div
                    class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-8 text-center text-gray-500 flex flex-col items-center justify-center min-h-[400px]"
                >
                    <i class="pi pi-history text-4xl mb-4 text-gray-400"></i>
                    <h2
                        class="text-lg font-bold text-gray-700 dark:text-gray-200"
                    >
                        Histórico de Pedidos
                    </h2>
                    <p class="text-sm">
                        Os pedidos concluídos ou estornados ficam isolados aqui
                        para não poluir o painel ativo.
                    </p>
                </div>
            </div>
        </main>

        <FloatingMenu />
    </div>

    <OrderContextMenu
        v-model:visible="contextMenuVisible"
        :order="selectedOrderContext"
        @action="handleMenuAction"
    />

    <OrderItemsManager
        v-model:visible="isItemsManagerVisible"
        :order="orderToModify"
        @reprint-item="handleItemReprint"
        @modify-item="handleItemModify"
        @swap-item="handleItemSwap"
        @cancel-item="handleItemCancel"
    />

    <OrderTransformer
        v-model:visible="isTransformerVisible"
        :order="orderToModify"
        @transform="handleTransformation"
    />

    <DeliveryPaymentIntentModal
        v-model:visible="isDeliveryPaymentModalVisible"
        :order-total="orderToUpdatePayment?.total || 0"
        @confirm="handleUpdateDeliveryPayment"
    />
</template>

<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import PosLayout from "../Layouts/PosLayout.vue";
import Button from "primevue/button";

// Componentes do Painel
import OrderCard from "./Components/OrderCard.vue";
import OrderContextMenu from "./Components/OrderContextMenu.vue";
import OrderItemsManager from "./Components/OrderItemsManager.vue";
import OrderTransformer from "./Components/OrderTransformer.vue";
import OpenTabs from "./Components/OpenTabs.vue";
import DeliveryKanban from "./Components/DeliveryKanban.vue";
import DeliveryPaymentIntentModal from "../Pos/Components/OrderEntry/DeliveryPaymentIntentModal.vue";

defineOptions({ layout: PosLayout });

const props = defineProps({
    initialOrders: Array,
    companySettings: Object,
});

// ==========================================
// 1. ESTADOS REATIVOS
// ==========================================
const activeTab = ref("active");
const searchQuery = ref("");
const orders = computed(() => props.initialOrders);

const contextMenuVisible = ref(false);
const selectedOrderContext = ref(null);

const isItemsManagerVisible = ref(false);
const orderToModify = ref(null);

const isTransformerVisible = ref(false);

const isDeliveryPaymentModalVisible = ref(false);
const orderToUpdatePayment = ref(null);

// ==========================================
// 2. COMPUTEDS E FILTROS (O CORAÇÃO DA TELA)
// ==========================================
const activeOrders = computed(() => {
    if (!orders.value) return [];

    const query = (searchQuery.value || "").toLowerCase();

    return orders.value.filter((o) => {
        const safeName = o.customer_name || "";
        const matchesSearch = safeName.toLowerCase().includes(query);
        return (
            matchesSearch && o.status !== "closed" && o.status !== "cancelled"
        );
    });
});

const completedOrders = computed(() => {
    if (!orders.value) return [];
    return orders.value.filter(
        (o) => o.status === "closed" || o.status === "cancelled",
    );
});

// 🚀 AQUI ESTÁ A CORREÇÃO! Filtros que alimentam os componentes:
const onSiteOrders = computed(() => {
    return activeOrders.value.filter(
        (o) => o.type === "table" || o.type === "counter",
    );
});

const deliveryOrders = computed(() => {
    return activeOrders.value.filter((o) => o.type === "delivery");
});

// ==========================================
// 3. EVENTOS DO MENU DE CONTEXTO E KANBAN
// ==========================================
const openContextMenu = (order) => {
    selectedOrderContext.value = order;
    contextMenuVisible.value = true;
};

const handleMenuAction = (action, order) => {
    switch (action) {
        case "checkout":
            router.visit(`/operations/orders/${order.id}/checkout`);
            break;
        case "add_items":
            router.visit(`/pos?append_to_order=${order.id}`);
            break;
        case "modify_order":
            orderToModify.value = order;
            isItemsManagerVisible.value = true;
            break;
        case "transform_order":
            orderToModify.value = order;
            isTransformerVisible.value = true;
            break;
        case "delivery_payment":
            orderToUpdatePayment.value = order;
            isDeliveryPaymentModalVisible.value = true;
            break;
        case "print_partial":
            alert(
                `Enviando impressão de conferência do pedido #${order.id}...`,
            );
            break;
        case "print_delivery":
            alert(
                `Imprimindo relatório de entrega para o motoboy (Pedido #${order.id})...`,
            );
            break;
        case "notify_customer":
            const text = encodeURIComponent(
                `Olá ${order.customer_name}, o seu pedido #${order.id} saiu para entrega!`,
            );
            window.open(`https://wa.me/?text=${text}`, "_blank");
            break;
        case "notify_driver":
            alert(`Notificando motoboy do pedido #${order.id}...`);
            break;
        default:
            console.log("Ação não mapeada:", action);
    }
};

// ==========================================
// 4. COMUNICAÇÃO COM O LARAVEL
// ==========================================

// 🚀 CORRIGIDO: Avança o card no Kanban
const handleAdvanceStatus = (order, newStatus) => {
    router.post(
        `/operations/orders/${order.id}/status`,
        { status: newStatus },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const handleUpdateDeliveryPayment = (intentData) => {
    router.post(
        `/operations/orders/${orderToUpdatePayment.value.id}/update-delivery-payment`,
        intentData,
        {
            preserveScroll: true,
            preserveState: false, // Força o refresh da lista
            onSuccess: () => {
                isDeliveryPaymentModalVisible.value = false;
            },
            onError: () => alert("Erro ao atualizar o pagamento do delivery."),
        },
    );
};

const handleTransformation = (payload) => {
    router.post(`/operations/orders/${payload.order.id}/transform`, payload, {
        onSuccess: () => {
            isTransformerVisible.value = false;
        },
    });
};

// ==========================================
// 5. AÇÕES DO GERENCIADOR DE ITENS
// ==========================================
const handleItemReprint = (item) => {
    alert(`Enviando comando para a impressora: Reimprimir ${item.name}`);
};

const handleItemModify = (item, isCombo) => {
    isItemsManagerVisible.value = false;
    router.visit(
        `/pos?append_to_order=${orderToModify.value.id}&edit_item=${item.id}`,
    );
};

const handleItemSwap = (item) => {
    alert(`Lógica para trocar o item ${item.name} será implementada aqui.`);
};

const handleItemCancel = (payload) => {
    router.post(
        `/operations/orders/${orderToModify.value.id}/cancel-item`,
        {
            item_id: payload.item.id,
            is_loss: payload.isLoss,
            reason: payload.reason,
            pin: payload.password,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => alert("Item estornado com sucesso!"),
            onError: (e) =>
                alert(e.pin || "Ocorreu um erro interno ao estornar."),
        },
    );
};
</script>
