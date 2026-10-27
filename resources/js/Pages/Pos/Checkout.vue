<template>
  <div class="flex h-full max-h-full w-full bg-surface-1 p-2 gap-2 text-surface-text overflow-hidden font-sans relative">

    <!-- TELEPORT PARA A DYNAMIC ISLAND -->
    <Teleport v-if="isMounted" to="#dynamic-island-portal">
        <div class="flex items-center gap-2 shrink-0 mr-2">
            <PosTeleportButton
                icon="pi pi-print"
                label="Imprimir Conta"
                color="surface"
                @click="printBill"
            />
            <PosTeleportButton
                icon="pi pi-user-plus"
                label="Vincular Cliente"
                color="surface"
                @click="isLinkCustomerModalVisible = true"
            />
        </div>
    </Teleport>

    <!-- PAINEL ESQUERDO: LISTAGEM DE ITENS -->
    <div class="flex-1 flex flex-col h-full max-h-full bg-surface-2 rounded-4xl overflow-hidden min-h-0">
        <CheckoutHeader
            class="shrink-0"
            :title="orderDestinationTitle"
            :order-label="`Pagamento #${liveOrder.id}`"
            :subtitle="headerSubtitle"
            :applied-coupon="appliedCheckoutCoupon"
            :coupon-discount="appliedCheckoutDiscount"
            :is-all-selected="isAllSelected"
            v-model:is-grouped="isGrouped"
            v-model:search-query="itemSearchQuery"
            @toggle-all="toggleAllSelections"
            @open-coupon="isCouponModalVisible = true"
            @go-back="handleManualClose"
        />

        <CheckoutItemsList
            class="flex-1 min-h-0 overflow-y-auto"
            :display-items="displayItems"
            :is-all-selected="isAllSelected"
            :order-payments="liveOrder.payments || []"
            :search-query="itemSearchQuery"
            @rollback-payment="handleRollbackPayment"
            @increment="increment"
            @decrement="decrement"
            @divide-item="handleDivideItem"
            @toggle-select="handleToggleSelect"
            @toggle-all="toggleAllSelections"
            @quick-add="handleQuickAdd"
            @quick-cancel="handleQuickCancel"
        />
    </div>

    <!-- PAINEL DIREITO: PAGAMENTO E SUBPAINÉIS EMBUTIDOS -->
    <div class="w-full md:w-[400px] lg:w-[480px] shrink-0 flex flex-col bg-surface-2 rounded-4xl overflow-hidden min-h-0 relative">
      <Transition name="fade-slide" mode="out-in">

        <!-- FASE 1: AGUARDANDO PIX -->
        <div v-if="showPixPanel" class="flex flex-col h-full w-full bg-surface-2 p-6 justify-center" key="pix-phase">
            <PixWaitingArea
                :order-id="liveOrder.id"
                :qr-code-string="liveOrder.pix_payload"
                :txid="liveOrder.txid"
                :expires-at="liveOrder.pix_expires_at"
                @hide="showPixPanel = false"
                @cancel="cancelPixPayment(false)"
                @refresh="refreshPixPayment"
                @expired="cancelPixPayment(true)"
                @payment-approved="onPixApprovedByWebhook"
            />
        </div>

        <!-- FASE 2: SELEÇÃO DE PAGAMENTO -->
        <div v-else-if="!isPostPaymentPhase" class="flex flex-col h-full min-h-0 w-full" key="payment-phase">
            <CheckoutPaymentPanel
                class="flex-1 min-h-0 bg-surface-2"
                :order-subtotal="selectedItemsValue"
                :delivery-fee="effectiveDeliveryFee"
                :service-fee-amount="effectiveServiceFee"
                :discount="effectiveDiscount"
                :dynamic-total="intendedPaymentTotal"
                :pending-to-stage="pendingToStage"
                :payment-methods="paymentMethods"
                :companySettings="companySettings"
                :staged-payments="stagedPayments"
                :order="liveOrder"
                :suggested-amount="suggestedAmount"
                :active-divisor="activeDivisor"

                :applied-coupon-code="appliedCheckoutCoupon"
                :applied-coupon-discount="appliedCheckoutDiscount"

                v-model:is-service-tax-enabled="isServiceTaxEnabled"
                v-model:cashback-value="localCashbackAmountToSend"
                v-model:customerDocument="customerDocument"

                @divide="divideBy"
                @clear-divisor="clearDivisor"
                @set-remaining="setAmountToRemaining"
                @add-payment="handleAddPayment"
                @remove-payment="removePayment"
                @view-pix="showPixPanel = true"

                @apply-coupon="applyCoupon"
                @remove-coupon="removeCoupon"
            />

            <div class="p-6 shrink-0 z-10">
                <Button
                    v-if="stagedPayments.some(p => p.is_pending)"
                    label="Aguardando Confirmação do PIX..."
                    icon="pi pi-spin pi-spinner"
                    class="w-full h-16 text-xl rounded-full! font-black border-none outline-none transition-colors bg-amber-500 text-white cursor-not-allowed shadow-sm"
                    disabled
                />
                <Button
                    v-else
                    :label="canSubmitCheckout ? 'Confirmar Recebimento' : 'Aguardando Pagamento'"
                    :icon="canSubmitCheckout ? 'pi pi-check-circle' : 'pi pi-lock'"
                    class="w-full h-16 text-xl rounded-full! font-black border-none outline-none transition-colors"
                    :class="canSubmitCheckout ? 'bg-success-500 hover:bg-success-600 text-white cursor-pointer shadow-md' : 'bg-surface-3 text-surface-muted cursor-not-allowed'"
                    :disabled="!canSubmitCheckout"
                    @click="processPayment"
                />
            </div>
        </div>

        <!-- FASE 3: RECIBO PÓS-PAGAMENTO -->
        <CheckoutPostPaymentPanel
            v-else
            @action="handlePostPaymentActionIntercepted"
            :is-cancelled="liveOrder?.status === 'cancelled'"
            :order="liveOrder"
            :flash-error="fiscalEmitError || $page.props.flash?.error"
            :is-emitting="isEmittingNfce"
            :fiscal-success="isFiscalSuccess"
            :is-zero-revenue="isZeroRevenueOrder"
            key="post-payment-phase"
        />

      </Transition>

      <!-- 🚀 SUBPAINEL EMBUTIDO 1: VINCULAR CLIENTE -->
      <Transition name="fade-slide">
          <CheckoutCustomerDrawer
              v-if="isLinkCustomerModalVisible"
              :order-id="liveOrder.id"
              :coupon-code="appliedCheckoutCoupon"
              @close="isLinkCustomerModalVisible = false"
              @create-new="openNewCustomerModal"
          />
      </Transition>

      <!-- 🚀 SUBPAINEL EMBUTIDO 2: GAVETA DE CUPOM DE DESCONTO -->
      <Transition name="fade-slide">
          <CheckoutCouponDrawer
              v-if="isCouponModalVisible"
              :applied-coupon="appliedCheckoutCoupon"
              :applied-discount="appliedCheckoutDiscount"
              :subtotal="selectedItemsValue"
              :customer-id="liveOrder?.customer_id || liveOrder?.customer?.id"
              @close="isCouponModalVisible = false"
              @apply="applyCoupon"
              @remove="removeCoupon"
          />
      </Transition>

      <!-- 🚀 SUBPAINEL EMBUTIDO 3: MOTIVO & PIN (CORREÇÃO / REATIVAÇÃO / ESTORNO) -->
      <Transition name="fade-slide">
          <OperationReasonModal
              v-if="isReasonModalVisible"
              v-model:visible="isReasonModalVisible"
              mode="inline"
              class="absolute inset-0 z-40 bg-surface-2"
              :item-name="itemToProcess?.name || itemToProcess?.product_name || (itemToProcess?.method ? 'Pagamento ' + itemToProcess.method : 'Item')"
              :action-type="authActionType"
              :requires-pin="authNeedsPin"
              @cancel="isReasonModalVisible = false"
              @confirm="handleConfirmAction"
          />
      </Transition>

    </div>

    <!-- Modal Auxiliar de Criação Rápida de Cliente -->
    <NewCustomerModal v-model:visible="isNewCustomerModalVisible" :initial-name="newCustomerInitialName" @save="handleSaveNewCustomer" />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch , onBeforeUnmount } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import Button from "primevue/button";

import PosTeleportButton from "@/Components/UI/PosTeleportButton.vue";
import CheckoutItemsList from "@/Pages/Pos/Components/Checkout/CheckoutItemsList.vue";
import CheckoutPaymentPanel from "@/Pages/Pos/Components/Checkout/CheckoutPaymentPanel.vue";
import CheckoutPostPaymentPanel from "@/Pages/Pos/Components/Checkout/CheckoutPostPaymentPanel.vue";
import CheckoutHeader from "@/Pages/Pos/Components/Checkout/CheckoutHeader.vue";
import PixWaitingArea from "@/Pages/Pos/Components/Checkout/PixWaitingArea.vue";
import NewCustomerModal from "@/Components/Modals/NewCustomerModal.vue";
import OperationReasonModal from "@/Pages/Pos/Components/Checkout/OperationReasonModal.vue";

// Subpainéis embutidos
import CheckoutCustomerDrawer from "@/Pages/Pos/Components/Checkout/CheckoutCustomerDrawer.vue";
import CheckoutCouponDrawer from "@/Pages/Pos/Components/Checkout/CheckoutCouponDrawer.vue";

// Composables de Domínio
import { useCheckoutMath } from "@/Pages/Shared/Composables/useCheckoutMath";
import { useOrderFinancials } from "@/Pages/Shared/Composables/useOrderFinancials";
import { useCheckoutStaging } from "@/Pages/Shared/Composables/useCheckoutStaging";
import { useSecureAction } from "@/Composables/useSecureAction";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useCheckoutPix } from "@/Pages/Pos/Composables/useCheckoutPix";
import { useCheckoutProcessor } from "@/Pages/Pos/Composables/useCheckoutProcessor";
import { useCheckoutCoupon } from "@/Pages/Pos/Composables/useCheckoutCoupon";
import { usePaymentRollback } from "@/Pages/Pos/Composables/usePaymentRollback";

const props = defineProps({ order: Object, paymentMethods: Array, companySettings: Object });
const emit = defineEmits(['close']);
const toast = useToast();
const page = usePage();

const isMounted = ref(false);

const liveOrder = computed(() => {
    const base = props.order || {};
    const list = page.props.initialOrders || page.props.orders || [];
    const fromList = list.find(o => o.id === base.id);

    if (!fromList) return base;

    const items = (base.items && base.items.length > 0)
        ? base.items.map(bItem => {
            const uItem = fromList.items?.find(fi => fi.id === bItem.id);
            return uItem ? { ...bItem, ...uItem } : bItem;
        })
        : (fromList.items || []);

    const payments = Array.isArray(base.payments) ? base.payments : (fromList.payments || []);

    return {
        ...base,
        ...fromList,
        customer: base.customer || fromList.customer,
        address: base.address || fromList.address,
        diningTable: base.diningTable || fromList.diningTable,
        items,
        payments
    };
});

const orderRef = computed(() => liveOrder.value);
const checkoutPrintMode = computed(() => props.companySettings?.checkout_print_mode || 'ask');

const isPostPaymentPhase = ref(false);

watch(
    () => [
        liveOrder.value?.payment_status,
        liveOrder.value?.status,
        liveOrder.value?.total,
        liveOrder.value?.total_paid,
        liveOrder.value?.payments
    ],
    ([payStatus, orderStatus, totalStr, totalPaidVal, paymentsList]) => {
        const orderTotal = Number(totalStr || 0);

        const calcPaid = totalPaidVal !== undefined && totalPaidVal !== null
            ? Number(totalPaidVal)
            : (Array.isArray(paymentsList) ? paymentsList.filter(p => p.status !== 'cancelled').reduce((acc, p) => acc + Number(p.amount || 0), 0) : 0);

        const isFullyPaid = payStatus === 'paid' || (calcPaid >= orderTotal - 0.05 && orderTotal > 0);
        const isClosedOrFinal = ['closed', 'completed', 'delivered'].includes(orderStatus);

        if (isClosedOrFinal || isFullyPaid) {
            isPostPaymentPhase.value = true;
        } else {
            isPostPaymentPhase.value = false;
        }
    },
    { immediate: true }
);

const customerDocument = ref(liveOrder.value?.customer?.cpf || "");
const localCashbackAmountToSend = ref(0);
const isGrouped = ref(true);
const itemSearchQuery = ref("");

const isServiceTaxEnabled = ref(liveOrder.value?.type === 'table' && Boolean(props.companySettings?.has_service_tax));
watch(() => [liveOrder.value?.id, liveOrder.value?.type], ([, type]) => {
    isServiceTaxEnabled.value = type === 'table' && Boolean(props.companySettings?.has_service_tax);
});

const isFiscalEmitter = computed(() => props.companySettings?.is_fiscal_emitter || false);
const isLinkCustomerModalVisible = ref(false);
const isNewCustomerModalVisible = ref(false);
const newCustomerInitialName = ref("");

const { getOrderTitle } = useOrderFormatting();

const orderDestinationTitle = computed(() => {
    if (liveOrder.value?.type === 'table') {
        return liveOrder.value.table_number ? `Mesa ${liveOrder.value.table_number}` : 'Mesa Salão';
    }
    if (liveOrder.value?.customer_name || liveOrder.value?.customer?.name) {
        return liveOrder.value.customer_name || liveOrder.value.customer?.name;
    }
    return getOrderTitle(liveOrder.value) || 'Balcão';
});

const headerSubtitle = computed(() => getOrderTitle(liveOrder.value));

const activeOrderItems = computed(() => {
    const items = (liveOrder.value?.items && liveOrder.value.items.length > 0) ? liveOrder.value.items : (props.order?.items || []);
    return items.filter(i => i.status !== 'cancelled');
});

const {
    reactiveItems, isAllSelected, displayItems, toggleAllSelections,
    handleDivideItem, handleToggleSelect, increment, decrement,
    orderTotalItemsValue, selectedSubtotal
} = useCheckoutMath(activeOrderItems, isGrouped);

const selectedItemsValue = computed(() => selectedSubtotal.value);
const selectionRatio = computed(() => {
    const total = orderTotalItemsValue.value;
    return total === 0 ? 0 : Math.min(1, selectedItemsValue.value / total);
});

const { deliveryFee, discount, serviceFeeAmount, feesAndDiscounts } = useOrderFinancials(liveOrder, orderTotalItemsValue, isServiceTaxEnabled, props.companySettings);

const effectiveServiceFee = computed(() => Number((serviceFeeAmount.value * selectionRatio.value).toFixed(2)));
const effectiveDeliveryFee = computed(() => Number((deliveryFee.value * selectionRatio.value).toFixed(2)));
const effectiveDiscount = computed(() => Number((discount.value * selectionRatio.value).toFixed(2)));

const {
    appliedCheckoutCoupon,
    appliedCheckoutDiscount,
    isCouponModalVisible,
    applyCoupon,
    removeCoupon
} = useCheckoutCoupon({
    selectedItemsValue,
    effectiveDeliveryFee,
    effectiveServiceFee,
    liveOrder,
    toast
});

const totalAlreadyPaid = computed(() => {
    return (liveOrder.value?.payments || []).reduce((sum, p) => sum + Number(p.amount || 0), 0);
});

const pendingOriginalBalance = computed(() => {
    if (['delivery', 'counter', 'pickup'].includes(liveOrder.value?.type)) {
        const orderTotal = Number(liveOrder.value?.total || 0);
        if (orderTotal > 0 && orderTotal > totalAlreadyPaid.value) {
           return Math.max(0, orderTotal - totalAlreadyPaid.value);
        }
    }
    return 0;
});

const isZeroRevenueOrder = computed(() => {
    const o = liveOrder.value || {};
    const subtotal = Number(o.subtotal || 0);
    const delivery = Number(o.delivery_fee || 0);
    const discountVal = Math.max(Number(o.discount || 0), Number(appliedCheckoutDiscount.value || 0));
    const effectiveTotal = Math.max(0, subtotal + delivery - discountVal);

    if (effectiveTotal <= 0.01) return true;

    const payments = Array.isArray(o.payments) && o.payments.length > 0 ? o.payments : (stagedPayments.value || []);
    if (payments.length > 0) {
        const methodsMap = new Map((props.paymentMethods || []).map(m => [m.id, m.engine_type || m.engine]));
        return payments.every(p => {
            const engine = p.payment_method?.engine_type || p.payment_method?.engine || methodsMap.get(p.payment_method_id || p.method_id);
            const name = (p.method || p.name || '').toLowerCase();
            return engine === 'loss' || name.includes('perda') || name.includes('quebra');
        });
    }
    return false;
});

const itemsPaidValue = computed(() => {
    return reactiveItems.value
        .filter(i => !i.parent_item_id)
        .reduce((sum, item) => sum + (Number(item.base_paid || 0) * item.effective_unit_price), 0);
});

const unallocatedPaid = computed(() => Math.max(0, totalAlreadyPaid.value - itemsPaidValue.value));

const intendedPaymentTotal = computed(() => {
    const baseTotal = orderTotalItemsValue.value > 0
        ? (selectedItemsValue.value + (feesAndDiscounts.value * selectionRatio.value))
        : pendingOriginalBalance.value;

    const paidDeduction = unallocatedPaid.value * (orderTotalItemsValue.value > 0 ? selectionRatio.value : 1);
    const finalAmount = baseTotal - paidDeduction - appliedCheckoutDiscount.value;

    return Math.max(0, Number(finalAmount.toFixed(2)));
});

const {
    stagedPayments, pendingToStage, addPayment,
    removePayment, divideBy, setAmountToRemaining, autoStagePayments,
    suggestedAmount, activeDivisor, clearDivisor
} = useCheckoutStaging(intendedPaymentTotal);

watch(intendedPaymentTotal, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        stagedPayments.value = [];
        clearDivisor();
    }
});

watch(() => liveOrder.value?.payments, (newPayments, oldPayments) => {
    if (newPayments && oldPayments && newPayments.length !== oldPayments.length) {
        stagedPayments.value = [];
        clearDivisor();
        localCashbackAmountToSend.value = 0;
    }
}, { deep: true });

const hasBankApiStaged = computed(() => {
    return (stagedPayments.value || []).some(p => p.engine_type === 'bank_api');
});

const canSubmitCheckout = computed(() => {
    const requiredAmount = intendedPaymentTotal.value - localCashbackAmountToSend.value;
    const stagedTotalAmount = stagedPayments.value.reduce((sum, p) => sum + p.amount, 0);
    return intendedPaymentTotal.value > 0 && stagedTotalAmount >= (requiredAmount - 0.01);
});

const handleManualClose = () => { emit('close'); };

const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        const activeEl = document.activeElement;
        const activeTag = activeEl?.tagName;

        if (activeTag === 'INPUT' || activeTag === 'TEXTAREA') {
            activeEl.blur();
            return;
        }

        if (isNewCustomerModalVisible.value) { isNewCustomerModalVisible.value = false; return; }
        if (isLinkCustomerModalVisible.value) { isLinkCustomerModalVisible.value = false; return; }
        if (isCouponModalVisible.value) { isCouponModalVisible.value = false; return; }
        if (isReasonModalVisible.value) { isReasonModalVisible.value = false; return; }

        e.preventDefault();
        handleManualClose();
    }
};

const handleAddPayment = async (method) => {
    if (method.engine_type === 'bank_api' || String(method.name).toLowerCase().includes('pix')) {
        const success = await generateDirectPix(method.amount);
        if (success) {
            showPixPanel.value = true;
        }
        return;
    }
    addPayment(method, toast);
};

const handleQuickAdd = (item) => {
    const targetId = item?._parent_ref?.id || item?.id;
    router.post(route('operations.quick-add', liveOrder.value?.id || props.order?.id), {
        item_id: targetId,
        should_print: false,
    }, { preserveScroll: true, preserveState: true });
};

const { isReasonModalVisible, itemToProcess, authActionType, authNeedsPin, initiateSecureAction } = useSecureAction(props.companySettings);

const handleQuickCancel = (item) => { initiateSecureAction(item?._parent_ref || item, 'cancel'); };

const handleRollbackPayment = (payment) => {
    if (!payment?.id) {
        toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Identificador não localizado.', life: 3000 });
        return;
    }
    itemToProcess.value = payment;
    authActionType.value = 'rollback_payment';
    authNeedsPin.value = Boolean(props.companySettings?.require_pin_for_voids ?? true);
    isReasonModalVisible.value = true;
};

const { rollbackPayment, cancelItem } = usePaymentRollback({
    liveOrder,
    props,
    page,
    reactiveItems,
    stagedPayments,
    clearDivisor,
    isPostPaymentPhase,
    isReasonModalVisible,
    itemToProcess,
    toast
});

const handleConfirmAction = (authData) => {
    if (authActionType.value === 'reactivate' || authActionType.value === 'correct_payment') {
        confirmCancellation(authData);
    } else if (authActionType.value === 'rollback_payment') {
        rollbackPayment(itemToProcess.value, authData);
    } else {
        cancelItem(itemToProcess.value, authData);
    }
};

const printBill = () => {
    router.post(route('operations.print-conference', liveOrder.value.id), {}, { preserveScroll: true, preserveState: true });
};

const openNewCustomerModal = (initialName = "") => {
    newCustomerInitialName.value = typeof initialName === 'string' ? initialName : "";
    isLinkCustomerModalVisible.value = false;
    isNewCustomerModalVisible.value = true;
};

const handleSaveNewCustomer = (customerData) => {
    router.post(route('operations.customers.store'), customerData, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isNewCustomerModalVisible.value = false;
            toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Cliente cadastrado!', life: 3000 });
        }
    });
};

const handlePixApproved = () => { isPostPaymentPhase.value = true; };

const { showPixPanel, cancelPixPayment, refreshPixPayment, onPixApprovedByWebhook } = useCheckoutPix(orderRef, handlePixApproved, toast);

const handlePostPaymentActionIntercepted = (action) => {
    const type = typeof action === 'string' ? action : action?.type;
    if (['close', 'finish', 'home', 'no_print'].includes(type)) {
        emit('close');
    } else {
        handlePostPaymentAction(action);
    }
};

const {
    isEmittingNfce,
    isFiscalSuccess,
    fiscalEmitError,
    processPayment, generateDirectPix,
    handlePostPaymentAction,
    confirmCancellation
} = useCheckoutProcessor({
    order: orderRef,
    props,
    toast,
    emitClose: () => { emit('close'); },
    isFiscalEmitter,
    checkoutPrintMode,
    customerDocument,
    stagedPayments,
    reactiveItems,
    serviceFeeAmount,
    localCashbackAmountToSend,
    authActionType,
    itemToProcess,
    authNeedsPin,
    initiateSecureAction,
    isReasonModalVisible,
    isPostPaymentPhase,
    appliedCheckoutCoupon,
    isZeroRevenueOrder
});

onMounted(() => {
    isMounted.value = true;
    window.addEventListener('keydown', handleKeydown);
    autoStagePayments(liveOrder.value, props.paymentMethods);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(20px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}
</style>
