<template>
  <div class="flex h-full max-h-full w-full bg-surface-1 p-2 gap-2 text-surface-text overflow-hidden font-sans relative">

    <Teleport v-if="isMounted" to="#dynamic-island-portal">
        <div class="flex items-center gap-2 h-10 md:h-12 shrink-0 mr-2">
            <button @click="printBill" class="px-4 h-full rounded-full bg-surface-1 border border-surface-border text-[10px] md:text-xs font-black text-surface-muted uppercase tracking-widest hover:text-primary-smart hover:border-primary-500/50 transition-colors flex items-center gap-2 outline-none shadow-sm cursor-pointer">
                <i class="pi pi-print"></i>
                <span class="hidden md:inline">Imprimir Conta</span>
            </button>
            <button @click="isLinkCustomerModalVisible = true" class="px-4 h-full rounded-full bg-surface-1 border border-surface-border text-[10px] md:text-xs font-black text-surface-muted uppercase tracking-widest hover:text-primary-smart hover:border-primary-500/50 transition-colors flex items-center gap-2 outline-none shadow-sm cursor-pointer">
                <i class="pi pi-user-plus"></i>
                <span class="hidden md:inline">Vincular Cliente</span>
            </button>

            <button @click="handleManualClose" class="w-10 md:w-12 h-full rounded-full bg-surface-2 border border-surface-border text-surface-text hover:bg-surface-3 transition-colors flex items-center justify-center outline-none shadow-sm cursor-pointer" title="Fechar">
                <i class="pi pi-times font-black"></i>
            </button>
        </div>
    </Teleport>

    <!-- PAINEL ESQUERDO: LISTAGEM DE ITENS COM ROLAGEM INDEPENDENTE -->
    <div class="flex-1 flex flex-col h-full max-h-full bg-surface-2 rounded-4xl overflow-hidden min-h-0">
    <CheckoutHeader
        class="shrink-0"
        :title="orderDestinationTitle"
        :order-label="`Pagamento #${liveOrder.id}`"
        :subtitle="headerSubtitle"
        :is-all-selected="isAllSelected"
        v-model:is-grouped="isGrouped"
        v-model:search-query="itemSearchQuery"
        @toggle-all="toggleAllSelections"
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

    <!-- PAINEL DIREITO: PAGAMENTO -->
    <div class="w-full md:w-[400px] lg:w-[480px] shrink-0 flex flex-col bg-surface-2 rounded-4xl overflow-hidden min-h-0 relative">
      <Transition name="fade-slide" mode="out-in">

        <!-- FASE 1: AGUARDANDO QR CODE -->
        <div v-if="isPixWaitingPhase" class="flex flex-col h-full w-full bg-surface-2 p-6 justify-center" key="pix-phase">
            <PixWaitingArea
                :order-id="liveOrder.id"
                :qr-code-string="liveOrder.txid"
                :txid="liveOrder.txid"
                @cancel="cancelPixPayment"
                @refresh="refreshPixPayment"
                @payment-approved="onPixApprovedByWebhook"
            />
        </div>

        <!-- FASE 2: SELEÇÃO DE PAGAMENTO NORMAL -->
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
                :company-settings="companySettings"
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

                @apply-coupon="handleApplyCheckoutCoupon"
                @remove-coupon="handleRemoveCheckoutCoupon"
            />

            <div class="p-6 bg-surface-2 border-t border-surface-border shrink-0 z-10 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.02)]">
                <Button
                    label="Confirmar Recebimento"
                    icon="pi pi-check-circle"
                    class="w-full h-16 text-xl font-black shadow-md border-none outline-none transition-colors"
                    :class="canSubmitCheckout ? 'bg-success-500 hover:bg-success-600 text-white cursor-pointer' : 'bg-surface-3 text-surface-muted cursor-not-allowed'"
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
    </div>

<!-- Modais -->
    <Dialog v-model:visible="isLinkCustomerModalVisible" modal header="Identificar Cliente" class="w-[450px]" :draggable="false">
      <CustomerSearch @selected="customerToLink = $event; linkCustomer();" @new-customer="openNewCustomerModal" />
    </Dialog>

    <NewCustomerModal v-model:visible="isNewCustomerModalVisible" :initial-name="newCustomerInitialName" @save="handleSaveNewCustomer" />

    <OperationReasonModal
        v-model:visible="isReasonModalVisible"
        :item-name="itemToProcess?.name || itemToProcess?.product_name || (itemToProcess?.method ? 'Pagamento ' + itemToProcess.method : 'Item')"
        :action-type="authActionType"
        :requires-pin="authNeedsPin"
        @confirm="handleConfirmAction"
    />
  </div>
</template>

<script setup>
import axios from "axios";
import { ref, computed, onMounted, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";

import Button from "primevue/button";
import Dialog from "primevue/dialog";

import CheckoutItemsList from "@/Pages/Pos/Components/Checkout/CheckoutItemsList.vue";
import CheckoutPaymentPanel from "@/Pages/Pos/Components/Checkout/CheckoutPaymentPanel.vue";
import CheckoutPostPaymentPanel from "@/Pages/Pos/Components/Checkout/CheckoutPostPaymentPanel.vue";
import CheckoutHeader from "@/Pages/Pos/Components/Checkout/CheckoutHeader.vue";
import PixWaitingArea from "@/Pages/Pos/Components/Checkout/PixWaitingArea.vue";
import CustomerSearch from "@/Pages/Pos/Components/Cart/CustomerSearch.vue";
import NewCustomerModal from "@/Components/Modals/NewCustomerModal.vue";
import OperationReasonModal from "@/Pages/Pos/Components/Checkout/OperationReasonModal.vue";

import { useCheckoutMath } from "@/Pages/Shared/Composables/useCheckoutMath";
import { useOrderFinancials } from "@/Pages/Shared/Composables/useOrderFinancials";
import { useCheckoutStaging } from "@/Pages/Shared/Composables/useCheckoutStaging";
import { useSecureAction } from "@/Composables/useSecureAction";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

import { useCheckoutPix } from "@/Pages/Pos/Composables/useCheckoutPix";
import { useCheckoutProcessor } from "@/Pages/Pos/Composables/useCheckoutProcessor";

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

    const payments = Array.isArray(base.payments)
        ? base.payments
        : (fromList.payments || []);

    return {
        ...base,
        ...fromList,
        items,
        payments
    };
});

const orderRef = computed(() => liveOrder.value);
const checkoutPrintMode = computed(() => props.companySettings?.checkout_print_mode || 'ask');

const isPostPaymentPhase = ref(
    liveOrder.value?.payment_status === 'paid' ||
    liveOrder.value?.status === 'closed' ||
    liveOrder.value?.status === 'cancelled'
);

watch(
    () => [liveOrder.value?.payment_status, liveOrder.value?.status],
    ([payStatus, orderStatus]) => {
        if (payStatus === 'paid' || orderStatus === 'closed' || orderStatus === 'cancelled') {
            isPostPaymentPhase.value = true;
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
const customerToLink = ref(null);
const isNewCustomerModalVisible = ref(false);
const newCustomerInitialName = ref("");
const appliedCheckoutCoupon = ref(null);
const appliedCheckoutDiscount = ref(0);

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
    const items = (liveOrder.value?.items && liveOrder.value.items.length > 0)
        ? liveOrder.value.items
        : (props.order?.items || []);
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

const totalAlreadyPaid = computed(() => {
    return (liveOrder.value?.payments || []).reduce((sum, p) => sum + Number(p.amount || 0), 0);
});

// 🚀 Detecta se o pedido foi 100% baixado em Perda ou 100% em Cupom de Desconto
const isZeroRevenueOrder = computed(() => {
    const o = liveOrder.value || {};
    const subtotal = Number(o.subtotal || 0);
    const delivery = Number(o.delivery_fee || 0);
    const discountVal = Math.max(Number(o.discount || 0), Number(appliedCheckoutDiscount.value || 0));
    const effectiveTotal = Math.max(0, subtotal + delivery - discountVal);

    // 1. 100% de desconto por cupom (total do faturamento zerado)
    if (effectiveTotal <= 0.01) {
        return true;
    }

    // 2. 100% com método de Perda / Quebra (engine_type === 'loss')
    const payments = Array.isArray(o.payments) && o.payments.length > 0
        ? o.payments
        : (stagedPayments.value || []);

    if (payments.length > 0) {
        const methodsMap = new Map((props.paymentMethods || []).map(m => [m.id, m.engine_type || m.engine]));
        const allLoss = payments.every(p => {
            const engine = p.payment_method?.engine_type
                || p.payment_method?.engine
                || methodsMap.get(p.payment_method_id || p.method_id);
            const name = (p.method || p.name || '').toLowerCase();
            return engine === 'loss' || name.includes('perda') || name.includes('quebra');
        });
        if (allLoss) {
            return true;
        }
    }

    return false;
});

const itemsPaidValue = computed(() => {
    return reactiveItems.value
        .filter(i => !i.parent_item_id)
        .reduce((sum, item) => sum + (Number(item.base_paid || 0) * item.effective_unit_price), 0);
});

const unallocatedPaid = computed(() => {
    return Math.max(0, totalAlreadyPaid.value - itemsPaidValue.value);
});

const handleApplyCheckoutCoupon = async (code) => {
    try {
        const response = await axios.post(route('customer.coupons.validate'), {
            code: code,
            subtotal: selectedItemsValue.value,
            is_pos: true,
            customer_id: liveOrder.value?.customer_id
        });

        appliedCheckoutCoupon.value = response.data.code;

        // Puxa o cálculo oficial do backend simulando o applyCoupon no composable
        const type = response.data.type;
        const val = Number(response.data.value || 0);

        if (type === 'percent') {
            appliedCheckoutDiscount.value = Math.round(((selectedItemsValue.value * val) / 100) * 100) / 100;
        } else if (type === 'fixed') {
            appliedCheckoutDiscount.value = Math.min(val, selectedItemsValue.value);
        } else if (type === 'free_delivery') {
            appliedCheckoutDiscount.value = effectiveDeliveryFee.value;
        } else if (type === 'fixed_total') {
            const bruto = selectedItemsValue.value + effectiveDeliveryFee.value + effectiveServiceFee.value;
            const diff = bruto - val;
            appliedCheckoutDiscount.value = diff > 0 ? diff : 0;
        }

        toast.add({ severity: 'success', summary: 'Sucesso', detail: response.data.message, life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Atenção', detail: e.response?.data?.message || 'Cupom inválido.', life: 4000 });
    }
};

const handleRemoveCheckoutCoupon = () => {
    appliedCheckoutCoupon.value = null;
    appliedCheckoutDiscount.value = 0;
};

const intendedPaymentTotal = computed(() => {
    const rawTotal = selectedItemsValue.value + (feesAndDiscounts.value * selectionRatio.value);
    const paidDeduction = unallocatedPaid.value * selectionRatio.value;

    // 🚀 NOVO: Abate o valor do cupom do total da conta!
    const finalAmount = rawTotal - paidDeduction - appliedCheckoutDiscount.value;

    return Math.max(0, Number((finalAmount).toFixed(2)));
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

const canSubmitCheckout = computed(() => {
    const requiredAmount = intendedPaymentTotal.value - localCashbackAmountToSend.value;
    const stagedTotalAmount = stagedPayments.value.reduce((sum, p) => sum + p.amount, 0);
    return intendedPaymentTotal.value > 0 && stagedTotalAmount >= (requiredAmount - 0.01);
});

const handleManualClose = () => {
    emit('close');
};

const handleAddPayment = (method) => { addPayment(method, toast); };

const handleQuickAdd = (item) => {
    const targetId = item?._parent_ref?.id || item?.id;
    router.post(route('operations.quick-add', liveOrder.value?.id || props.order?.id), {
        item_id: targetId,
        should_print: false,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const { isReasonModalVisible, itemToProcess, authActionType, authNeedsPin, initiateSecureAction } = useSecureAction(props.companySettings);

const handleQuickCancel = (item) => {
    initiateSecureAction(item?._parent_ref || item, 'cancel');
};

const handleRollbackPayment = (payment) => {
    if (!payment || !payment.id) {
        toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Identificador não localizado.', life: 3000 });
        return;
    }

    itemToProcess.value = payment;
    authActionType.value = 'rollback_payment';
    authNeedsPin.value = Boolean(props.companySettings?.require_pin_for_voids ?? true);
    isReasonModalVisible.value = true;
};

const handleConfirmAction = (authData) => {
    // 🚀 Tratamento de ações a nível de Conta Inteira (Reativar / Corrigir Tudo)
    if (authActionType.value === 'reactivate' || authActionType.value === 'correct_payment') {
        confirmCancellation(authData);
        return;
    }

    // Fluxo para Estorno Unitário de um Pagamento já existe
    if (authActionType.value === 'rollback_payment') {
        const payment = itemToProcess.value;
        if (!payment || !payment.id) {
            toast.add({ severity: 'error', summary: 'Erro', detail: 'Nenhum pagamento selecionado.', life: 3000 });
            return;
        }

        router.delete(route('operations.orders.payments.rollback', { order: liveOrder.value.id, payment: payment.id }), {
            data: {
                pin: authData?.password || authData?.pin || null,
                reason: authData?.reason || 'Estorno solicitado pelo operador'
            },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isReasonModalVisible.value = false;
                itemToProcess.value = null;

                if (props.order?.payments) {
                    props.order.payments = props.order.payments.filter(p => p.id !== payment.id);
                }

                if (props.order?.items) {
                    props.order.items.forEach(i => {
                        i.paid_qty = 0;
                    });
                }

                if (props.order) {
                    props.order.payment_status = 'pending';
                    if (props.order.status === 'closed' || props.order.status === 'completed') {
                        props.order.status = 'pending';
                    }
                }

                const fromList = (page.props.initialOrders || []).find(o => o.id === liveOrder.value?.id);
                if (fromList) {
                    fromList.payments = (fromList.payments || []).filter(p => p.id !== payment.id);
                    if (fromList.items) fromList.items.forEach(i => { i.paid_qty = 0; });
                    fromList.payment_status = 'pending';
                    fromList.status = 'pending'; // Força a visualização pendente
                }

                reactiveItems.value.forEach(item => {
                    item.base_paid = 0;
                    item.paid_parts = 0;
                    item.available_parts = (item.base_qty ?? item.quantity ?? 1) * (item.parts_per_unit || 1);
                    item.selected_parts = item.available_parts;
                });

                stagedPayments.value = [];
                clearDivisor();

                toast.add({ severity: 'success', summary: 'Estornado', detail: 'Pagamento revertido com sucesso!', life: 3000 });
                isPostPaymentPhase.value = false;

                router.reload({
                    only: ['cashierData', 'initialOrders'],
                    preserveScroll: true,
                    preserveState: true
                });
            },
            onError: (errors) => {
                toast.add({ severity: 'error', summary: 'Falha no Estorno', detail: errors?.pin || errors?.reason || 'Não foi possível estornar.', life: 4000 });
            }
        });
    } else {
        // Fluxo de Estorno de Item isolado
        const targetItem = itemToProcess.value;
        const targetId = targetItem?._parent_ref?.id || targetItem?.id;
        if (!targetId) {
            toast.add({ severity: 'error', summary: 'Erro', detail: 'Nenhum item selecionado.', life: 3000 });
            return;
        }

        router.post(route('operations.orders.cancel-item', liveOrder.value.id), {
            item_id: targetId,
            pin: authData?.password || authData?.pin || null,
            reason: authData?.reason || 'Ajuste no encerramento de conta',
            is_loss: Boolean(authData?.isLoss || authData?.is_loss)
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isReasonModalVisible.value = false;
                itemToProcess.value = null;
                toast.add({ severity: 'success', summary: 'Item Estornado', detail: 'Item removido do pedido.', life: 2500 });
            },
            onError: (errors) => {
                toast.add({ severity: 'error', summary: 'Falha', detail: errors?.pin || errors?.reason || 'Não foi possível estornar o item.', life: 3000 });
            }
        });
    }
};

const printBill = () => {
    router.post(route('operations.print-conference', liveOrder.value.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {}
    });
};

const linkCustomer = () => {
    const payload = {
        customer_id: typeof customerToLink.value === 'object' ? customerToLink.value.id : null,
        customer_name: typeof customerToLink.value === 'object' ? customerToLink.value.name : customerToLink.value,
        coupon_code: appliedCheckoutCoupon.value
    };
    router.post(route('operations.orders.link-customer', liveOrder.value.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isLinkCustomerModalVisible.value = false;
            toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Cliente vinculado!', life: 3000 });
        }
    });
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

const handlePixApproved = () => {
    isPostPaymentPhase.value = true;
};

const {
    isPixWaitingPhase, cancelPixPayment, refreshPixPayment, onPixApprovedByWebhook
} = useCheckoutPix(orderRef, handlePixApproved, toast);

const handlePostPaymentActionIntercepted = (action) => {
    const type = typeof action === 'string' ? action : action?.type;
    if (type === 'close' || type === 'finish' || type === 'home' || type === 'no_print') {
        emit('close');
    } else {
        handlePostPaymentAction(action);
    }
};

const {
    isEmittingNfce,
    isFiscalSuccess,
    fiscalEmitError,
    processPayment,
    handlePostPaymentAction,
    confirmCancellation
} = useCheckoutProcessor({
    order: orderRef,
    props,
    toast,
    emitClose: () => {
        emit('close');
    },
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
    autoStagePayments(liveOrder.value, props.paymentMethods);
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
