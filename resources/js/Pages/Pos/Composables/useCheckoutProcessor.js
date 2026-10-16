import axios from 'axios';
import { ref, unref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export function useCheckoutProcessor({
    order,
    props, toast, emitClose, isFiscalEmitter, checkoutPrintMode, customerDocument,
    stagedPayments, reactiveItems, serviceFeeAmount, localCashbackAmountToSend,
    authActionType, itemToProcess, authNeedsPin, initiateSecureAction, isReasonModalVisible, isPostPaymentPhase,
    appliedCheckoutCoupon,
    isZeroRevenueOrder
}) {
    const isEmittingNfce = ref(false);
    const isFiscalSuccess = ref(false);
    const fiscalEmitError = ref(null);
    const isProcessingPayment = ref(false);
    const isPaymentSuccess = ref(false);

    const page = usePage();

    const getOrder = () => (order && order.value) ? order.value : (props.order?.value || props.order);
    const getOrderId = () => getOrder()?.id;

    const goBack = () => {
        const url = new URL(window.location.href);
        url.searchParams.delete('order');
        url.searchParams.delete('checkout');
        url.searchParams.delete('open_order');
        window.history.replaceState({ tab: 'orders' }, '', '/pos/orders');

        window.dispatchEvent(new CustomEvent('pos-close-order-details'));
        emitClose();
    };

    const executeAutoPrintRule = () => {
        if (checkoutPrintMode.value && checkoutPrintMode.value !== 'ask' && checkoutPrintMode.value !== 'none') {
            if (['home', 'no_print', 'finish', 'close'].includes(checkoutPrintMode.value)) {
                return;
            }
            handlePostPaymentAction({ type: checkoutPrintMode.value });
        }
    };

    const triggerSilentEmission = async () => {
        const orderId = getOrderId();
        if (!orderId) return;

        if (isZeroRevenueOrder && unref(isZeroRevenueOrder)) {
            isEmittingNfce.value = false;
            return;
        }

        isEmittingNfce.value = true;
        fiscalEmitError.value = null;

        try {
            const apiClient = axios || window.axios;
            const response = await apiClient.post(route('operations.orders.emit-nfce', orderId), {
                document: unref(customerDocument)
            }, {
                headers: { 'Accept': 'application/json' }
            });

            if (response.data?.success) {
                isFiscalSuccess.value = true;

                const target = getOrder();
                if (target) {
                    target.has_fiscal_document = true;
                    target.fiscal_status = 'authorized';
                }

                if (props.order) {
                    if (props.order.value) {
                        props.order.value.has_fiscal_document = true;
                        props.order.value.fiscal_status = 'authorized';
                    } else {
                        props.order.has_fiscal_document = true;
                        props.order.fiscal_status = 'authorized';
                    }
                }

                const list = page.props.initialOrders || page.props.orders || [];
                const fromList = list.find(o => o.id === orderId);
                if (fromList) {
                    fromList.has_fiscal_document = true;
                    fromList.fiscal_status = 'authorized';
                }

                setTimeout(() => {
                    isEmittingNfce.value = false;
                    executeAutoPrintRule();
                }, 1500);

            } else {
                isEmittingNfce.value = false;
                isFiscalSuccess.value = false;
                fiscalEmitError.value = response.data?.message || 'Falha ao emitir nota fiscal.';
                toast.add({ severity: 'error', summary: 'Aviso Fiscal', detail: fiscalEmitError.value, life: 6000 });
            }
        } catch (error) {
            isEmittingNfce.value = false;
            isFiscalSuccess.value = false;
            const errMsg = error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0] || 'O servidor recusou a requisição fiscal.';
            fiscalEmitError.value = errMsg;
            toast.add({ severity: 'error', summary: 'Erro Fiscal', detail: errMsg, life: 6000 });
        }
    };

    const processPayment = async () => {
        const orderId = getOrderId();
        if (isProcessingPayment.value || !orderId) return;

        isProcessingPayment.value = true;

        const paymentsToSubmit = (stagedPayments.value || []).map(p => ({
            method_id: p.real_method_id || p.method_id || p.payment_method_id || p.payment_method?.id,
            amount: Number(p.amount)
        }));

        // 🚀 FILTRA APENAS OS ITENS SELECIONADOS PARA ESTA RODADA DE PAGAMENTO
        const itemsToPay = (reactiveItems?.value || [])
            .filter(i => (Number(i.selected_parts) || 0) > 0)
            .map(i => {
                const ppu = Number(i.parts_per_unit) || 1;
                const qty = Number(i.selected_parts) / ppu;
                const unitPrice = Number(i.effective_unit_price || i.unit_price || 0);
                return {
                    id: i.id,
                    pay_qty: qty,
                    amount: Math.round(qty * unitPrice * 100) / 100
                };
            });

        const payload = {
            payments: paymentsToSubmit,
            items: itemsToPay,
            service_fee: Math.max(0, Number(unref(serviceFeeAmount) || 0)),
            cashback_used: Math.max(0, Number(unref(localCashbackAmountToSend) || 0)),
            document: unref(customerDocument),
            coupon_code: unref(appliedCheckoutCoupon)
        };

        try {
            const apiClient = axios || window.axios;
            const response = await apiClient.post(route('operations.orders.pay', orderId), payload, {
                headers: { 'Accept': 'application/json' }
            });

            const returnedOrder = response.data?.order || response.data?.data || null;

            stagedPayments.value = [];
            localCashbackAmountToSend.value = 0;

            if (reactiveItems && reactiveItems.value) {
                itemsToPay.forEach(paidItem => {
                    const found = reactiveItems.value.find(i => i.id === paidItem.id);
                    if (found) {
                        found.base_paid = (found.base_paid || 0) + paidItem.pay_qty;
                    }
                });
            }

            const updatedOrder = returnedOrder || getOrder();

            const isZeroRev = isZeroRevenueOrder ? unref(isZeroRevenueOrder) : false;

            const shouldEmit = Boolean(
                isFiscalEmitter?.value &&
                !isZeroRev &&
                updatedOrder?.fiscal_status !== 'authorized' &&
                !updatedOrder?.has_fiscal_document
            );

            isPaymentSuccess.value = true;
            isProcessingPayment.value = false;

            if (shouldEmit) {
                isEmittingNfce.value = true;
            }

            if (isPostPaymentPhase) {
                isPostPaymentPhase.value = true;
            }

            if (shouldEmit) {
                triggerSilentEmission();
            } else {
                executeAutoPrintRule();
            }

        } catch (error) {
            isProcessingPayment.value = false;
            isEmittingNfce.value = false;
            const errMsg = error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0] || 'Falha ao processar o pagamento.';
            toast.add({ severity: 'error', summary: 'Erro de Pagamento', detail: errMsg, life: 5000 });
        }
    };

    const handlePostPaymentAction = async (action) => {
        const orderId = getOrderId();
        const type = typeof action === 'string' ? action : action?.type;

        if (type === 'no_print' || type === 'close' || type === 'home') {
            goBack();
            return;
        }

        if (type === 'emit_nfce') {
            if (isZeroRevenueOrder && unref(isZeroRevenueOrder)) {
                toast.add({ severity: 'warn', summary: 'Aviso', detail: 'Emissão fiscal não permitida para pedidos sem faturamento.', life: 4000 });
                return;
            }
            triggerSilentEmission();
            return;
        }

        if (type === 'reactivate' || type === 'correct_payment') {
            if (authActionType) authActionType.value = type;
            if (authNeedsPin) authNeedsPin.value = Boolean(props.companySettings?.require_pin_for_voids ?? true);
            if (isReasonModalVisible) isReasonModalVisible.value = true;
            return;
        }

        try {
            const apiClient = axios || window.axios;
            await apiClient.post(route('operations.finalize-receipt', orderId), { print_action: type }, {
                headers: { 'Accept': 'application/json' }
            });
        } catch (error) {
            const errMsg = error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0] || "Falha na comunicação de impressão.";
            toast.add({ severity: 'error', summary: 'Erro', detail: errMsg, life: 4000 });
        }
    };

    const confirmCancellation = async (authData) => {
        const orderId = getOrderId();
        const targetType = unref(authActionType);

        try {
            if (targetType === 'reactivate' || targetType === 'correct_payment') {
                await router.post(route('operations.orders.reactivate', orderId), {
                    pin: authData.password || authData.pin,
                    reason: authData.reason,
                    mode: targetType
                }, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        isReasonModalVisible.value = false;
                        if (isPostPaymentPhase) isPostPaymentPhase.value = false;
                        toast.add({ severity: 'success', summary: 'Conta Reaberta', detail: 'O status do pedido voltou para pendente.', life: 3000 });

                        router.reload({ only: ['initialOrders', 'cashierData'], preserveState: true, preserveScroll: true });
                    },
                    onError: (err) => {
                        const errMsg = err.pin || err.message || "Erro ao reabrir conta.";
                        toast.add({ severity: "error", summary: "Acesso Negado", detail: errMsg, life: 5000 });
                    }
                });
                return;
            }

            const apiClient = axios || window.axios;
            await apiClient.post(route('operations.orders.refund-and-clone', orderId), {
                pin: authData.password || authData.pin,
                reason: authData.reason,
                is_loss: authData.isLoss
            }, { headers: { 'Accept': 'application/json' } });

            isReasonModalVisible.value = false;
            router.reload({ only: ['initialOrders', 'cashierData'], preserveState: true, preserveScroll: true });
        } catch (e) {
            const errMsg = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0] || "Erro interno.";
            toast.add({ severity: "error", summary: "Acesso Negado", detail: errMsg, life: 5000 });
        }
    };

    return {
        isEmittingNfce,
        isFiscalSuccess,
        fiscalEmitError,
        isProcessingPayment,
        isPaymentSuccess,
        processPayment,
        handlePostPaymentAction,
        confirmCancellation
    };
}
