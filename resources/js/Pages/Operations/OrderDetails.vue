<template>
    <div class="flex h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans gap-2 relative">

        <Teleport v-if="isMounted" to="#dynamic-island-portal">
            <div v-if="selectedItemIds.length > 0" class="flex items-center gap-1.5 h-10 md:h-12 bg-surface-2 border border-surface-border rounded-full px-2 shadow-sm animate-in fade-in zoom-in-95 duration-200 mr-2">
                <span class="text-[10px] md:text-xs font-black text-surface-text px-2.5 uppercase tracking-widest select-none">
                    <span class="text-primary-500">{{ $formatNumber(selectedItemIds.length) }}</span> sel.
                </span>

                <div class="w-px h-5 bg-surface-border/50 mx-1"></div>

                <!-- 1. Imprimir Itens Selecionados -->
                <PosTeleportButton
                    icon="pi-print"
                    color="surface"
                    label="Imprimir Itens"
                    @click="executeBulkAction('print')"
                />

                <!-- 2. Transferir Itens Selecionados -->
                <PosTeleportButton
                    icon="pi-sync"
                    color="primary"
                    label="Transferir Itens"
                    @click="executeBulkAction('transfer')"
                />

                <!-- 3. Cancelar Seleção -->
                <PosTeleportButton
                    icon="pi-times"
                    color="danger"
                    label="Cancelar Seleção"
                    @click="selectedItemIds = []"
                />
            </div>
        </Teleport>

        <div class="flex-1 flex flex-col h-full bg-surface-2 rounded-3xl overflow-hidden min-h-0">
            <CheckoutHeader
                class="shrink-0"
                :title="formattedOrderTitle"
                :is-all-selected="selectedItemIds.length === activeItemsExploded.length && activeItemsExploded.length > 0"
                :is-grouped="true"
                :hide-group-toggle="true"
                v-model:search-query="itemSearchQuery"
                @toggle-all="toggleSelectAllItems"
                @go-back="closeOrderDetails"
            >
                <template #title-badges>
                    <span v-if="isPaid" class="bg-success-500/50 px-2.5 py-1 rounded-lg font-black text-xs text-success-800 dark:text-success-200 flex items-center gap-1">
                        <i class="pi pi-check-circle text-[8px]"></i> Pago
                    </span>
                    <span v-if="isGuestUser" class="bg-surface-1 text-surface-muted shadow-sm border border-surface-border px-2.5 py-1 rounded-lg font-black uppercase tracking-widest text-[9px] flex items-center gap-1" title="Cliente sem cadastro completo">
                        <i class="pi pi-user text-[8px]"></i> Visitante
                    </span>
                </template>

                <template #subtitle>
                    <button @click="handleAction(order?.type === 'table' ? 'transfer_all' : 'transform_order')" :class="chipInteractive">
                        <i class="pi pi-sync text-[9px]"></i> {{ translatedType }}
                    </button>

                    <button
                        @click="handleAction('delivery_payment')"
                        v-if="order?.type !== 'table'"
                        :class="paymentMissing && !isPaid ? chipWarning : chipInteractive"
                    >
                        <template v-if="parsedPaymentBadges.length > 0">
                            <span v-for="(badge, idx) in parsedPaymentBadges" :key="idx" class="flex items-center gap-1">
                                <i :class="['pi', badge.icon, 'text-xs']"></i> {{ badge.label }}
                            </span>
                        </template>
                        <template v-else-if="isPaid">
                            <i class="pi pi-check-circle text-xs text-success-500"></i> Conta Paga
                        </template>
                        <template v-else>
                            <i class="pi pi-exclamation-triangle text-xs"></i> Definir Pgto.
                        </template>
                    </button>

                    <button v-if="order?.type === 'delivery'" @click="handleAction('change_address')" :class="!parsedAddressData ? chipDanger : chipInteractive">
                        <i class="pi pi-map-marker text-[9px]"></i> {{ displayAddressText }}
                    </button>

                    <button v-if="order?.type === 'delivery'" @click="handleAction('swap_courier')" :class="!assignedCourier ? chipDanger : chipInteractive">
                        <i class="pi pi-truck text-[9px]"></i> {{ assignedCourier ? abbreviateName(assignedCourier.name) : '+ Atribuir Entregador' }}
                    </button>

                    <button v-if="['counter', 'delivery'].includes(order?.type)" @click="openCustomerWhatsApp" :class="!hasPhone ? chipDanger : chipInteractive">
                        <i class="pi pi-whatsapp text-[9px]"></i> {{ hasPhone ? formatPhone(customerPhone) : 'Sem Telefone' }}
                    </button>
                </template>
            </CheckoutHeader>

            <CheckoutItemsList
                class="flex-1 min-h-0 overflow-y-auto"
                mode="details"
                :display-items="activeItemsExploded"
                :selected-ids="selectedItemIds"
                :is-all-selected="selectedItemIds.length === activeItemsExploded.length && activeItemsExploded.length > 0"
                :order-payments="order?.payments || []"
                :search-query="itemSearchQuery"
                @toggle-select="toggleItemSelection($event)"
                @toggle-all="toggleSelectAllItems"
                @action="handleItemAction"
            />
        </div>

        <!-- PAINEL LATERAL DESKTOP -->
        <div class="w-full md:w-[400px] lg:w-[480px] shrink-0 flex flex-col bg-surface-2 rounded-3xl overflow-hidden min-h-0 relative">
            <Transition name="fade" mode="out-in">
                <div v-if="activeSidebarView === 'menu'" class="flex flex-col h-full w-full absolute inset-0">
                    <div class="p-6 shrink-0 flex flex-col">
                        <div class="flex justify-between items-start w-full">
                            <h3 class="text-xl font-black text-surface-text">Ações no Pedido</h3>
                            <span class="bg-surface-3 text-surface-muted px-2.5 py-1 rounded-lg font-black text-xs">
                                #{{ $formatNumber(order?.id) }}
                            </span>
                        </div>
                        <p class="text-xs text-surface-muted font-medium mt-1">Gerenciamento operacional</p>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto scroll-container px-6">
                        <OrderActionsGrid :order="order" :is-team-mode="false" @action="handleGridAction" />
                    </div>

                    <div class="p-6 shrink-0 z-10 flex flex-col w-full gap-2 mt-auto">
                        <div class="flex justify-between items-center text-sm font-medium text-surface-muted">
                            <span>Subtotal</span><span>{{ $formatCurrency(order?.subtotal || 0) }}</span>
                        </div>
                        <div v-if="order?.delivery_fee > 0" class="flex justify-between items-center text-sm font-medium text-surface-muted">
                            <span>Taxa de Entrega</span><span>{{ $formatCurrency(order?.delivery_fee) }}</span>
                        </div>
                        <div v-if="order?.service_fee > 0" class="flex justify-between items-center text-sm font-medium text-surface-muted">
                            <span>Taxa de Serviço</span><span>{{ $formatCurrency(order?.service_fee) }}</span>
                        </div>
                        <div v-if="order?.discount > 0" class="flex justify-between items-center text-sm font-medium text-success-500">
                            <span>Desconto</span><span>- {{ $formatCurrency(order?.discount) }}</span>
                        </div>
                        <div class="flex justify-between items-center mt-2 pt-2 border-t-2 border-surface-border/50">
                            <span class="text-xs font-black text-surface-muted uppercase tracking-widest">Falta Pagar</span>
                            <span class="text-3xl font-black text-primary-smart">{{ $formatCurrency(orderRemaining) }}</span>
                        </div>
                    </div>
                </div>

                <ChangeAddressModal
                    v-else-if="activeSidebarView === 'address'"
                    mode="inline"
                    :visible="true"
                    :order="order"
                    @cancel="activeSidebarView = 'menu'"
                    @confirm="handleChangeAddressConfirm"
                    @deactivate="handleDeactivateAddress"
                    @create-new="handleCreateNewAddress"
                    @edit="handleEditAddress"
                />

                <NewAddressModal
                    v-else-if="activeSidebarView === 'new_address'"
                    mode="inline"
                    :visible="true"
                    :customer-id="order?.customer_id"
                    :address-to-edit="addressToEdit"
                    @cancel="activeSidebarView = 'address'"
                    @success="handleNewAddressSuccess"
                />

                <PaymentIntentModal
                    v-else-if="activeSidebarView === 'payment'"
                    mode="inline"
                    :visible="true"
                    :order="order"
                    :customer="order?.customer"
                    :order-total="Number(order?.total || 0)"
                    :existing-intent="order?.payment_intent_status"
                    :existing-methods="order?.payment_intent_methods"
                    :payment-methods="$page.props.paymentMethods"
                    :hide-pending-choice="true"
                    @cancel="activeSidebarView = 'menu'"
                    @confirm="handleUpdatePayment"
                />

                <TransferItemsModal
                    v-else-if="activeSidebarView === 'transfer'"
                    mode="inline"
                    :visible="true"
                    :source-order="order"
                    :item-ids="transferItemIds"
                    :table-areas="tableAreas"
                    @cancel="activeSidebarView = 'menu'"
                    @confirm="handleExecuteTransfer"
                />

                <OrderTransformer
                    v-else-if="activeSidebarView === 'transform'"
                    mode="inline"
                    :visible="true"
                    :order="order"
                    :table-areas="tableAreas"
                    @cancel="activeSidebarView = 'menu'"
                    @transform="handleTransformation"
                />

                <AssignCourierModal
                    v-else-if="activeSidebarView === 'courier_swap'"
                    mode="inline"
                    :visible="true"
                    :order="order"
                    :couriers="couriers"
                    @cancel="cancelAssignmentModal"
                    @assign="triggerCourierAssignment"
                />

                <OperationReasonModal
                    v-else-if="activeSidebarView === 'reason'"
                    mode="inline"
                    :visible="true"
                    :item-name="authActionType === 'cancel_order' ? `Pedido #${$formatNumber(itemToProcess?.id)}` : itemToProcess?.name"
                    :action-type="authActionType"
                    :requires-pin="authNeedsPin"
                    :item-to-process="itemToProcess"
                    @cancel="activeSidebarView = 'menu'"
                    @confirm="handleConfirmAuth"
                />
            </Transition>
        </div>

        <!-- MODAIS MOBILE -->
        <template v-if="isMobile">
            <TransferItemsModal v-model:visible="isTransferModalVisible" :source-order="order" :item-ids="transferItemIds" :table-areas="tableAreas" @confirm="handleExecuteTransfer" />
            <OrderTransformer v-model:visible="isTransformerVisible" :order="order" :table-areas="tableAreas" @transform="handleTransformation" />

            <PaymentIntentModal
                v-model:visible="isPaymentModalVisible"
                :order="order"
                :customer="order?.customer"
                :order-total="Number(order?.total || 0)"
                :existing-intent="order?.payment_intent_status"
                :existing-methods="order?.payment_intent_methods"
                :payment-methods="$page.props.paymentMethods"
                :hide-pending-choice="true"
                @confirm="handleUpdatePayment"
            />

            <ChangeAddressModal v-model:visible="isChangeAddressModalVisible" :order="order" @confirm="handleChangeAddressConfirm" @deactivate="handleDeactivateAddress" @create-new="handleCreateNewAddress" @edit="handleEditAddress" />
            <NewAddressModal v-model:visible="isNewAddressModalVisible" mode="modal" :customer-id="order?.customer_id" :address-to-edit="addressToEdit" @cancel="isNewAddressModalVisible = false; isChangeAddressModalVisible = true;" @success="handleNewAddressSuccess" />
            <AssignCourierModal mode="modal" v-model:visible="isCourierSwapModalVisible" :order="order" :couriers="couriers" @cancel="cancelAssignmentModal" @assign="triggerCourierAssignment" />

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
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import DesktopLayout from "@/Layouts/DesktopLayout.vue";
import { formatCurrency, formatNumber, formatPhone } from "@/Utils/formatters";
import { useViewport } from "@/Composables/useViewport";

import CheckoutHeader from "@/Pages/Pos/Components/Checkout/CheckoutHeader.vue";
import CheckoutItemsList from "@/Pages/Pos/Components/Checkout/CheckoutItemsList.vue";
import OrderActionsGrid from "@/Pages/Shared/Order/OrderActionsGrid.vue";
import OperationReasonModal from "@/Pages/Pos/Components/Checkout/OperationReasonModal.vue";
import TransferItemsModal from "@/Pages/Shared/Order/TransferItemsModal.vue";
import OrderTransformer from "@/Pages/Shared/Order/OrderTransformer.vue";
import PaymentIntentModal from "@/Pages/Pos/Components/Checkout/PaymentIntentModal.vue";
import ChangeAddressModal from "@/Pages/Pos/Components/Checkout/ChangeAddressModal.vue";
import AssignCourierModal from "@/Pages/Shared/Order/AssignCourierModal.vue";
import NewAddressModal from "@/Components/Modals/NewAddressModal.vue";
import PosTeleportButton from "@/Components/UI/PosTeleportButton.vue";

import { useSecureAction } from "@/Composables/useSecureAction";
import { useItemActions } from "@/Pages/Shared/Composables/useItemActions";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useWhatsAppMessages } from "@/Composables/useWhatsAppMessages";
import { useOrderDetailsOrchestrator } from "@/Pages/Shared/Composables/useOrderDetailsOrchestrator";

defineOptions({ layout: DesktopLayout });

const props = defineProps({
    order: { type: Object, required: true },
    tableAreas: { type: Array, default: () => [] },
    companySettings: { type: Object, default: () => ({}) },
    couriers: { type: Array, default: () => [] }
});

const emit = defineEmits(["close"]);

const toast = useToast();
const { isMobile } = useViewport();
const isMounted = ref(false);
const itemSearchQuery = ref("");

const closeOrderDetails = () => {
    emit('close');
};

onMounted(() => {
    isMounted.value = true;
    window.addEventListener('pos-close-order-details', closeOrderDetails);
});

onUnmounted(() => {
    window.removeEventListener('pos-close-order-details', closeOrderDetails);
});

const isNewAddressModalVisible = ref(false);
const addressToEdit = ref(null);

const handleCreateNewAddress = () => {
    addressToEdit.value = null;
    if (isMobile.value) {
        isChangeAddressModalVisible.value = false;
        setTimeout(() => { isNewAddressModalVisible.value = true; }, 150);
    } else {
        activeSidebarView.value = 'new_address';
    }
};

const handleEditAddress = (addr) => {
    addressToEdit.value = addr;
    if (isMobile.value) {
        isChangeAddressModalVisible.value = false;
        setTimeout(() => { isNewAddressModalVisible.value = true; }, 150);
    } else {
        activeSidebarView.value = 'new_address';
    }
};

const handleNewAddressSuccess = () => {
    isNewAddressModalVisible.value = false;
    setTimeout(() => {
        if (isMobile.value) isChangeAddressModalVisible.value = true;
        else activeSidebarView.value = 'address';
    }, 150);
};

const chipBase = "flex items-center gap-1 px-3 py-2 rounded-full text-xs font-black outline-none transition-colors ";
const chipInteractive = chipBase + "p-option cursor-pointer";
const chipWarning = chipBase + "bg-warning-500/10 text-warning-600 dark:text-warning-400 cursor-pointer hover:bg-warning-500/20";
const chipDanger = chipBase + "bg-danger-500/10 text-danger-700 dark:text-danger-300 cursor-pointer hover:bg-danger-500/20";

const {
    getOrderTitle, abbreviateName, formatAddress,
    parseAddress, getCustomerPhone, getPaymentBadges
} = useOrderFormatting();

const { dispatchCourier, sendWhatsApp } = useWhatsAppMessages();

const activeItemsExploded = computed(() => {
    const rawItems = props.order?.items || [];
    if (!rawItems.length) return [];

    const roots = [];
    const orphanChildren = [];

    rawItems.forEach(item => {
        if (item.status === 'cancelled') return;

        if (item.parent_item_id) {
            orphanChildren.push(item);
        } else {
            roots.push({ ...item });
        }
    });

    const result = [];

    roots.forEach(parent => {
        result.push({
            ...parent,
            available_parts: Number(parent.quantity || 1),
            selected_parts: Number(parent.quantity || 1),
            parts_per_unit: 1,
            effective_unit_price: Number(parent.unit_price || 0)
        });

        const dbChildren = orphanChildren.filter(c => String(c.parent_item_id) === String(parent.id));
        const embeddedChildren = parent.sub_items || parent.subItems || parent.combo_items || parent.comboChildren || [];

        const allChildrenMap = new Map();
        [...dbChildren, ...embeddedChildren].forEach(c => {
            if (c && c.id && c.status !== 'cancelled') {
                allChildrenMap.set(c.id, c);
            }
        });

        allChildrenMap.forEach(child => {
            result.push({
                ...child,
                is_combo_child: true,
                combo_parent_id: parent.id,
                combo_parent_name: parent.product_name || parent.name,
                effective_unit_price: 0,
                unit_price: 0,
                total_price: 0,
                available_parts: Number(child.quantity || parent.quantity || 1),
                selected_parts: Number(child.quantity || parent.quantity || 1),
                parts_per_unit: 1
            });
        });
    });

    orphanChildren.forEach(child => {
        if (!result.some(r => r.id === child.id)) {
            result.push({
                ...child,
                is_combo_child: true,
                effective_unit_price: 0,
                unit_price: 0,
                total_price: 0,
                available_parts: Number(child.quantity || 1),
                selected_parts: Number(child.quantity || 1),
                parts_per_unit: 1
            });
        }
    });

    return result;
});

const {
    activeSidebarView, selectedItemIds, isTransferModalVisible,
    isTransformerVisible, isPaymentModalVisible, isChangeAddressModalVisible,
    isCourierSwapModalVisible, transferItemIds, toggleItemSelection, toggleSelectAllItems,
    handleAction, openSingleTransfer, handleExecuteTransfer, handleTransformation,
    handleUpdatePayment, handleChangeAddressConfirm, handleDeactivateAddress,
    handleAssignCourier, executeBulkAction
} = useOrderDetailsOrchestrator(props, activeItemsExploded);

const assignedCourier = computed(() => {
    if (!props.order?.driver_id) return null;
    return props.couriers.find(c => c.id === props.order.driver_id);
});

const pendingDispatch = ref(false);
const executeCourierDispatch = () => {
    const courier = assignedCourier.value;
    if (!courier) {
        pendingDispatch.value = true;
        toast.add({ severity: 'info', summary: 'Atenção', detail: 'Vincule o entregador primeiro.' });
        if (isMobile.value) isCourierSwapModalVisible.value = true;
        else activeSidebarView.value = 'courier_swap';
        return;
    }
    if (!courier.phone) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Sem WhatsApp cadastrado.' });
        return;
    }
    dispatchCourier(props.order, courier.phone);
};

const cancelAssignmentModal = () => {
    pendingDispatch.value = false;
    if (!isMobile.value) activeSidebarView.value = 'menu';
};

const triggerCourierAssignment = (courier) => {
    handleAssignCourier(courier, (assignedData) => {
        if (pendingDispatch.value) {
            pendingDispatch.value = false;
            if (assignedData.phone) setTimeout(() => dispatchCourier(props.order, assignedData.phone), 300);
            else toast.add({ severity: 'error', summary: 'Erro', detail: 'Motoboy vinculado não possui WhatsApp.' });
        }
    });
};

const formattedOrderTitle = computed(() => getOrderTitle(props.order));
const isGuestUser = computed(() => !props.order?.customer_id && ['counter', 'delivery'].includes(props.order?.type));

const parsedAddressData = computed(() => parseAddress(props.order?.address));
const displayAddressText = computed(() => parsedAddressData.value ? (formatAddress(props.order) || 'Endereço sem detalhes') : '+ Adicionar Endereço');

const customerPhone = computed(() => getCustomerPhone(props.order));
const hasPhone = computed(() => customerPhone.value && customerPhone.value.replace(/[^0-9]/g, '').length >= 10);
const openCustomerWhatsApp = () => {
    if (hasPhone.value) sendWhatsApp(customerPhone.value, `Olá! Falando do restaurante sobre o pedido #${props.order?.id}. `);
};

const isPaid = computed(() => props.order?.payment_status === 'paid');
const orderTotalPaid = computed(() => (props.order?.payments || []).reduce((sum, p) => sum + Number(p.amount), 0));
const orderRemaining = computed(() => Math.max(0, Number(props.order?.total || 0) - orderTotalPaid.value));

const paymentMissing = computed(() => {
    if (props.order?.type === 'table' || isPaid.value) return false;
    if (props.order?.payment_intent_status && props.order?.payment_intent_status !== 'pending_choice') return false;
    if (props.order?.payments && props.order?.payments.length > 0) return false;
    return true;
});

const parsedPaymentBadges = computed(() => getPaymentBadges(props.order));
const translatedType = computed(() => {
    const t = { table: "Mesa", counter: "Balcão", delivery: "Delivery" };
    return t[props.order?.type] || "";
});

const dummyRef = ref(true);
const { handleItemDuplicate, handleItemModify, handleItemSwap, handleItemCancel } = useItemActions(
    computed(() => props.order),
    dummyRef,
    'pos.index'
);

const { isReasonModalVisible, itemToProcess, authActionType, authNeedsPin, initiateSecureAction } = useSecureAction(
    props.companySettings
);

const handleItemReprint = (item, qty = 'all') => {
    router.post(route('operations.kds.print-item', item.id), {
        qty: qty
    }, {
        preserveScroll: true,
        onSuccess: () => void(0),
        onError: (err) => toast.add({ severity: 'error', summary: 'Falha na Impressão', detail: Object.values(err)[0] || 'Erro ao enviar para o spooler.' })
    });
};

const handlePrintDeliveryDispatch = () => {
    router.post(route('operations.orders.print-delivery', props.order.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            void(0)
        },
        onError: (err) => {
            toast.add({ severity: 'error', summary: 'Falha na Impressão', detail: Object.values(err)[0] || 'Erro ao enviar para o spooler.', life: 4000 });
        }
    });
};

const triggerSecureAction = (item, type, qty = 'all') => {
    if (item) item.action_qty = qty;
    initiateSecureAction(item, type);
    if (!isMobile.value) {
        isReasonModalVisible.value = false;
        setTimeout(() => activeSidebarView.value = 'reason', 50);
    }
};

const handleItemAction = (payload) => {
    const { type, item, qty = 'all' } = payload;
    if (item) item.action_qty = qty;

    if (type === 'reprint') {
        handleItemReprint(item, qty);
    } else if (type === 'duplicate') {
        handleItemDuplicate(item);
    } else if (type === 'edit') {
        triggerSecureAction(item, 'edit', qty);
    } else if (type === 'transfer') {
        if (item) item.transfer_qty = qty;
        openSingleTransfer(item);
    } else if (type === 'cancel') {
        triggerSecureAction(item, 'cancel', qty);
    }
};

const handleDirectReprint = (item) => handleItemReprint(item, 'all');

const handleFinishOrder = () => {
    router.post(route('operations.orders.update-status', props.order.id), {
        status: 'closed'
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Pedido Finalizado', detail: `Pedido #${props.order.id} concluído e arquivado no histórico.`, life: 3000 });
            closeOrderDetails();
        },
        onError: (err) => {
            toast.add({ severity: 'error', summary: 'Erro ao Finalizar', detail: Object.values(err)[0] || 'Não foi possível finalizar o pedido.', life: 4000 });
        }
    });
};

const handleGridAction = (actionId) => {
    if (actionId === 'add_items') {
        const orderData = props.order;
        const newUrl = `/pos?append_to_order=${orderData.id}`;
        window.history.pushState({ tab: 'pos' }, '', newUrl);
        window.dispatchEvent(new CustomEvent('pos-append-to-order', { detail: orderData }));
        closeOrderDetails();
        window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'pos' }));
        return;
    }

    if (actionId === 'dispatch_courier') executeCourierDispatch();
    else if (['print_delivery', 'print_dispatch', 'print_courier', 'delivery_dispatch', 'print_driver', 'print_motoboy'].includes(actionId)) handlePrintDeliveryDispatch();
    else if (actionId === 'cancel_order') triggerSecureAction(props.order, 'cancel_order');
    else if (actionId === 'finish_order') handleFinishOrder();
    else handleAction(actionId);
};

const handleConfirmAuth = (authData) => {
    const qty = itemToProcess.value?.action_qty || 'all';

    if (authActionType.value === 'edit') {
        handleItemModify({ item: itemToProcess.value, authData, action_qty: qty });
    } else if (authActionType.value === 'swap') {
        handleItemSwap({ item: itemToProcess.value, authData, action_qty: qty });
    } else if (authActionType.value === 'cancel_order') {
        router.post(route('operations.orders.cancel', itemToProcess.value.id), {
            reason: authData.reason,
            is_loss: authData.isLoss,
            lost_items: authData.lostItems,
            pin: authData.password
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isReasonModalVisible.value = false;
                toast.add({ severity: 'success', summary: 'Pedido Cancelado', detail: 'O pedido foi estornado e encerrado.', life: 4000 });
                closeOrderDetails();
            },
            onError: (err) => {
                toast.add({ severity: 'error', summary: 'Erro', detail: err.pin || 'Erro ao cancelar.', life: 4000 });
            }
        });
    } else {
        handleItemCancel({
            item: itemToProcess.value,
            action_qty: qty,
            isLoss: authData.isLoss,
            reason: authData.reason,
            password: authData.password
        });
    }

    isReasonModalVisible.value = false;
    activeSidebarView.value = 'menu';
    selectedItemIds.value = [];
};
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: scale(0.98); }
</style>
