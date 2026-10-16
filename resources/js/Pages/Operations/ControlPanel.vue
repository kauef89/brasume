<template>
    <ConfirmDialog :style="{ width: '400px' }" :breakpoints="{ '575px': '90vw' }" />

    <Teleport v-if="isMounted && isActive && !focusedOrder" to="#dynamic-island-portal">
        <div class="flex items-center gap-3 h-10 md:h-12 shrink-0">
            <SegmentedControl
                v-model="activeTab"
                :options="tabOptions"
            />

            <ExpandableSearch
                v-model="searchQuery"
                placeholder="Buscar pedido, cliente, mesa, CPF..."
            />

            <!-- 🚀 BOTÃO "BAIXAR ROTA" (RAIO) -->
            <PosTeleportButton
                v-if="activeTab === 'active' && hasDispatchedOrders"
                @click="confirmBulkComplete"
                icon="pi-bolt"
                color="warning"
                :active="true"
                :badge="dispatchedOrdersCount"
                badge-severity="warning"
                label="Baixar Entregas Concluídas"
                class="animate-in fade-in slide-in-from-right-2"
            />
        </div>
    </Teleport>

    <div class="flex flex-col h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans p-2 relative">

        <div v-if="focusedOrder" class="flex-1 min-h-0 w-full animate-in fade-in zoom-in-95 duration-200">
            <OrderDetails
                :order="focusedOrder"
                :table-areas="tableAreas"
                :company-settings="companySettings"
                :couriers="$page.props.couriers || []"
                @close="focusedOrderId = null"
                @go-back="focusedOrderId = null"
            />
        </div>

        <template v-else>
            <!-- ABA 1: PEDIDOS ATIVOS -->
            <div v-show="activeTab === 'active'" class="flex-1 grid grid-cols-1 xl:grid-cols-4 gap-2 min-h-0 w-full">

                <div class="relative flex flex-col min-h-0 transition-all duration-300"
                     :class="isTabsExpanded ? 'xl:col-span-2' : 'xl:col-span-1'">

                    <OpenTabs
                        :orders="onSiteOrders"
                        :table-areas="tableAreas"
                        :is-expanded="isTabsExpanded"
                        @open-menu="openContextMenu"
                        @create-order="handleCreateOrderOnTable"
                    />

                    <button @click="isTabsExpanded = !isTabsExpanded"
                            class="hidden xl:flex absolute top-1/2 -right-3 md:-right-3 translate-x-1/2 -translate-y-1/2 w-8 h-8 hover:bg-primary-500/20 rounded-full items-center justify-center text-surface-muted hover:text-primary-smart transition-colors z-20 outline-none cursor-pointer">
                        <i class="pi font-black text-sm" :class="isTabsExpanded ? 'pi-chevron-left' : 'pi-chevron-right'"></i>
                    </button>
                </div>

                <div class="flex flex-col min-h-0 min-w-0 transition-all rounded-2xl duration-300"
                     :class="isTabsExpanded ? 'xl:col-span-2' : 'xl:col-span-3'">
                    <DeliveryKanban :orders="deliveryOrders" @advance-status="handleAdvanceStatus" @open-menu="openContextMenu" />
                </div>

            </div>

            <!-- ABA 2: HISTÓRICO DE PEDIDOS -->
            <div v-show="activeTab === 'history'" class="flex-1 flex flex-col min-h-0 min-w-0 bg-surface-2 rounded-[2rem] p-6 md:p-8 animate-in fade-in duration-300 overflow-y-auto hide-scrollbar">

                <div v-if="historyOrders.length === 0" class="h-full flex items-center justify-center">
                    <EmptyState
                        icon="pi-search"
                        title="Nenhum pedido encontrado"
                        subtitle="Tente buscar por outro termo ou certifique-se de que há pedidos no histórico."
                    />
                </div>

                <div v-else class="flex flex-col gap-3">
                    <div v-for="order in historyOrders" :key="order.id"
                         @click="openContextMenu(order)"
                         class="flex items-center justify-between p-4 bg-surface-3 rounded-2xl border border-surface-border/40 hover:border-primary-500/50 transition-all cursor-pointer shadow-sm">

                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-surface-2 flex items-center justify-center font-black text-xs">
                                #{{ order.daily_code || $formatNumber(order.id) }}
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-black text-surface-text">{{ order.customer_name || 'Cliente Avulso' }}</span>
                                <span class="text-[10px] font-bold text-surface-muted uppercase tracking-wider">
                                    {{ order.type === 'delivery' ? 'Entrega' : (order.type === 'table' ? `Mesa ${order.table_number || ''}` : 'Balcão') }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-6">
                            <span class="text-[10px] font-black uppercase px-3 py-1 rounded-full tracking-wider"
                                  :class="order.status === 'cancelled' ? 'bg-danger-500/10 text-danger-500 border border-danger-500/20' : 'bg-success-smart/10 text-success-smart border border-success-smart/20'">
                                {{ order.status === 'cancelled' ? 'Cancelado' : 'Concluído' }}
                            </span>
                            <span class="font-black text-sm text-surface-text">{{ $formatCurrency(Number(order.total || 0)) }}</span>
                        </div>

                    </div>
                </div>

            </div>

            <button @click="handleNewOrder" class="absolute bottom-8 right-8 bg-primary-500 hover:bg-primary-600 text-white px-6 py-4 rounded-full shadow-[0_10px_40px_-10px_rgba(0,0,0,0.5)] transition-all hover:-translate-y-1 flex items-center gap-3 z-50 outline-none cursor-pointer group border-none">
                <i class="pi pi-plus font-black text-xl"></i>
                <span class="font-black tracking-widest uppercase text-sm mt-0.5">Novo Pedido</span>
                <kbd class="ml-2 hidden md:flex items-center justify-center min-w-[24px] h-6 px-1.5 rounded-lg bg-white/20 text-white text-[11px] font-black group-hover:bg-white/30">N</kbd>
            </button>
        </template>

        <TransferItemsModal v-model:visible="isTransferModalVisible" :source-order="transferSourceOrder" :item-ids="transferItemIds" :table-areas="tableAreas" @confirm="handleExecuteTransfer" />

        <OrderContextMenu
            v-model:visible="contextMenuVisible"
            :order="selectedOrderContext"
            :company-settings="companySettings"
            @action="customHandleMenuAction"
            @duplicate-item="handleItemDuplicate"
            @modify-item="handleItemModifyWithTabSwitch"
            @swap-item="handleItemSwapWithTabSwitch"
            @cancel-item="handleItemCancel"
            @transfer-items="handleTransferTrigger"
            @reprint-item="handleItemReprint"
        />

        <OrderTransformer v-model:visible="isTransformerVisible" :order="orderToModify" @transform="handleTransformation" />

        <PaymentIntentModal
            v-model:visible="isPaymentModalVisible"
            :order="orderToUpdatePayment"
            :customer="orderToUpdatePayment?.customer"
            :order-total="Number(orderToUpdatePayment?.total || 0)"
            :existing-intent="orderToUpdatePayment?.payment_intent_status"
            :existing-methods="orderToUpdatePayment?.payment_intent_methods"
            :payment-methods="availablePaymentMethods"
            @confirm="handleUpdatePayment"
        />

        <ChangeAddressModal v-model:visible="isChangeAddressModalVisible" :order="orderToUpdateAddress" @confirm="handleChangeAddressConfirm" />
    </div>
</template>

<script setup>
import PosTeleportButton from '@/Components/UI/PosTeleportButton.vue';

import { ref, onMounted, onUnmounted, computed, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import ConfirmDialog from "primevue/confirmdialog";

import DesktopLayout from "@/Layouts/DesktopLayout.vue";
import SegmentedControl from "@/Components/UI/SegmentedControl.vue";
import ExpandableSearch from "@/Components/UI/ExpandableSearch.vue";

import TransferItemsModal from "@/Pages/Shared/Order/TransferItemsModal.vue";
import OrderContextMenu from "@/Pages/Shared/Order/OrderContextMenu.vue";
import OrderTransformer from "@/Pages/Shared/Order/OrderTransformer.vue";
import OpenTabs from "@/Pages/Operations/Components/Dashboard/OpenTabs.vue";
import DeliveryKanban from "@/Pages/Operations/Components/Dashboard/DeliveryKanban.vue";
import PaymentIntentModal from "@/Pages/Pos/Components/Checkout/PaymentIntentModal.vue";
import ChangeAddressModal from "@/Pages/Pos/Components/Checkout/ChangeAddressModal.vue";
import OrderDetails from "@/Pages/Operations/OrderDetails.vue";

import { useItemActions } from "@/Pages/Shared/Composables/useItemActions";
import { useControlPanel } from "@/Pages/Shared/Composables/useControlPanel";
import { useCart } from "@/Pages/Shared/Composables/useCart";

defineOptions({ layout: DesktopLayout, inheritAttrs: false });

const props = defineProps({
    initialOrders: Array,
    companySettings: Object,
    tableAreas: Array,
    isActive: {
        type: Boolean,
        default: false
    }
});

const page = usePage();
const toast = useToast();
const confirm = useConfirm();
const isMounted = ref(false);

const initialOrdersRef = computed(() => props.initialOrders);
const isTabsExpanded = ref(false);

defineEmits(['close', 'go-back']);

const {
    activeTab, searchQuery, contextMenuVisible, selectedOrderContext, orderToModify,
    isTransformerVisible, isPaymentModalVisible, orderToUpdatePayment,
    isChangeAddressModalVisible, orderToUpdateAddress, isTransferModalVisible,
    transferSourceOrder, transferItemIds, activeOrders,
    onSiteOrders, deliveryOrders, historyOrders, openContextMenu, handleMenuAction,
    handleChangeAddressConfirm, handleAdvanceStatus, handleUpdatePayment,
    handleTransferTrigger, handleExecuteTransfer, handleTransformation,
    focusedOrderId, focusedOrder
} = useControlPanel(initialOrdersRef);

const { draftRouting, orderType, clearCart } = useCart();

const tabOptions = computed(() => [
    { label: `Ativos (${activeOrders.value.length})`, value: 'active' },
    { label: `Histórico (${historyOrders.value.length})`, value: 'history' }
]);

const availablePaymentMethods = computed(() => {
    return page.props.paymentMethods || page.props.cashierData?.paymentMethods || [];
});

const dispatchedOrdersCount = computed(() => {
    return deliveryOrders.value.filter(o => o.status === 'dispatched').length;
});

const hasDispatchedOrders = computed(() => dispatchedOrdersCount.value > 0);

const confirmBulkComplete = () => {
    confirm.require({
        message: `Você tem certeza que deseja finalizar os ${dispatchedOrdersCount.value} pedidos que estão em rota? O sistema tentará processar os pagamentos automaticamente usando as promessas do motoboy.`,
        header: 'Baixar Entregas em Rota',
        icon: 'pi pi-bolt text-warning-500',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Sim, Baixar Todos', severity: 'warning', icon: 'pi pi-bolt' },
        accept: () => {
            executeBulkComplete();
        }
    });
};

const executeBulkComplete = () => {
    router.post(route('operations.orders.bulk-complete-dispatched'), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (pageProps) => {
            const flash = pageProps.props.flash || {};
            if (flash.success) toast.add({ severity: 'success', summary: 'Rota Baixada', detail: flash.success, life: 5000 });
            if (flash.warning) toast.add({ severity: 'warn', summary: 'Atenção (Inconsistências)', detail: flash.warning, life: 8000 });
        }
    });
};

watch(() => props.isActive, (active) => {
    if (!active) {
        focusedOrderId.value = null;
        contextMenuVisible.value = false;
        selectedOrderContext.value = null;
        isTransformerVisible.value = false;
        isPaymentModalVisible.value = false;
        isChangeAddressModalVisible.value = false;
        isTransferModalVisible.value = false;
        searchQuery.value = '';
    }
});

const navigateToPosTab = () => {
    window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'pos' }));
};

const handleNewOrder = () => {
    clearCart();
    orderType.value = 'table';
    draftRouting.value = {
        order_id: null,
        existing_order_id: null,
        type: 'table',
        table: null,
        identifier: '',
        customer: null,
        customer_phone: null,
        address: null
    };
    navigateToPosTab();
};

const handleCreateOrderOnTable = (table, isOccupied, existingOrder) => {
    if (isOccupied && existingOrder) {
        openContextMenu(existingOrder);
    } else {
        orderType.value = 'table';
        draftRouting.value = {
            order_id: null,
            existing_order_id: null,
            type: 'table',
            table: { id: table.id, name: table.name, status: table.status },
            identifier: '',
            customer: null,
            customer_phone: null,
            address: null
        };
        navigateToPosTab();
    }
};

const customHandleMenuAction = (actionId, order) => {
    if (actionId === 'new_subaccount') {
        contextMenuVisible.value = false;
        orderType.value = 'table';
        draftRouting.value = {
            order_id: null,
            existing_order_id: null,
            type: 'table',
            table: { id: order.dining_table_id || order.table_id || null, name: order.table_number },
            identifier: 'Nova Conta',
            customer: null,
            customer_phone: null,
            address: null
        };
        navigateToPosTab();
    } else {
        handleMenuAction(actionId, order);
    }
};

const { handleItemDuplicate, handleItemModify, handleItemSwap, handleItemCancel } = useItemActions(selectedOrderContext, contextMenuVisible, '/pos');

const handleItemModifyWithTabSwitch = (payload) => {
    handleItemModify(payload);
    navigateToPosTab();
};

const handleItemSwapWithTabSwitch = (payload) => {
    handleItemSwap(payload);
    navigateToPosTab();
};

const handleItemReprint = (item) => {
    router.post(route('operations.kds.print-item', item.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (pageProps) => {
            if (pageProps.props.flash?.error) {
                toast.add({ severity: 'error', summary: 'Atenção', detail: pageProps.props.flash.error, life: 4000 });
            } else {
                toast.add({ severity: 'success', summary: 'Reimpressão', detail: `Via de ${item.product_name || item.name} enviada para a produção!`, life: 3000 });
            }
        }
    });
};

const handleKeydown = (e) => {
    const activeTag = document.activeElement.tagName;
    if (activeTag === 'INPUT' || activeTag === 'TEXTAREA') return;
    if (e.key.toLowerCase() === 'n') {
        e.preventDefault();
        handleNewOrder();
    }
};

const handleTabReset = (e) => {
    if (e.detail === 'orders') {
        focusedOrderId.value = null;
    }
};

const handleCloseOrderDetails = () => {
    focusedOrderId.value = null;
};

const handleWaiterCalled = (event) => {
    toast.add({
        severity: 'warn',
        summary: `🔔 Chamada: ${event.table_name || 'Mesa'}`,
        detail: `${event.customer_name || 'Cliente'} está solicitando atendimento na mesa!`,
        life: 10000
    });

    try {
        const audio = new Audio('/sounds/notification.mp3');
        audio.play().catch(() => {});
    } catch (err) {}
};

onMounted(() => {
    isMounted.value = true;
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('pos-tab-reset', handleTabReset);
    window.addEventListener('pos-close-order-details', handleCloseOrderDetails);

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_order') && props.initialOrders) {
        const orderToOpen = props.initialOrders.find(o => o.id == urlParams.get('open_order'));
        if (orderToOpen) {
            focusedOrderId.value = orderToOpen.id;
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    const tenantId = page.props.tenantId || page.props.tenant?.id || window.Laravel?.tenant_id || 'central';

    window.Echo.channel(`tenant.${tenantId}.operations`)
        .listen('.waiter.called', handleWaiterCalled);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    const tenantId = page.props.tenantId || page.props.tenant?.id || window.Laravel?.tenant_id || 'central';

    window.Echo.leaveChannel(`tenant.${tenantId}.orders`);
    window.Echo.leaveChannel(`tenant.${tenantId}.operations`);

    window.removeEventListener('pos-tab-reset', handleTabReset);
    window.removeEventListener('pos-close-order-details', handleCloseOrderDetails);
});
</script>