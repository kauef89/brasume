import axios from 'axios';
import { ref, unref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export function useCheckoutProcessor({
    order, props, toast, emitClose, isFiscalEmitter, checkoutPrintMode, customerDocument,
    stagedPayments, reactiveItems, serviceFeeAmount, localCashbackAmountToSend,
    authActionType, itemToProcess, authNeedsPin, initiateSecureAction, isReasonModalVisible, isPostPaymentPhase,
    appliedCheckoutCoupon, isZeroRevenueOrder
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
        window.history.replaceState({ tab: 'orders' }, '', '/pos/orders');
        window.dispatchEvent(new CustomEvent('pos-close-order-details'));
        emitClose();
    };

    const executeAutoPrintRule = () => {
        if (checkoutPrintMode.value && checkoutPrintMode.value !== 'ask' && checkoutPrintMode.value !== 'none') {
            if (['home', 'no_print', 'finish', 'close'].includes(checkoutPrintMode.value)) return;
            handlePostPaymentAction({ type: checkoutPrintMode.value });
        }
    };

    const triggerSilentEmission = async () => {
        const orderId = getOrderId();
        if (!orderId || (isZeroRevenueOrder && unref(isZeroRevenueOrder))) {
            isEmittingNfce.value = false;
            return;
        }

        isEmittingNfce.value = true;
        fiscalEmitError.value = null;

        try {
            const apiClient = axios || window.axios;
            const response = await apiClient.post(route('operations.orders.emit-nfce', orderId), {
                document: unref(customerDocument)
            }, { headers: { 'Accept': 'application/json' } });

            if (response.data?.success) {
                isFiscalSuccess.value = true;
                const target = getOrder();
                if (target) { target.has_fiscal_document = true; target.fiscal_status = 'authorized'; }
                setTimeout(() => { isEmittingNfce.value = false; executeAutoPrintRule(); }, 1500);
            } else {
                throw new Error(response.data?.message || 'Falha ao emitir nota fiscal.');
            }
        } catch (error) {
            isEmittingNfce.value = false;
            isFiscalSuccess.value = false;
            fiscalEmitError.value = error.response?.data?.message || error.message || 'O servidor recusou a requisição fiscal.';
            toast.add({ severity: 'error', summary: 'Aviso Fiscal', detail: fiscalEmitError.value, life: 6000 });
        }
    };

    // 🚀 O CORAÇÃO DO FLUXO: Geração Direta (One-Click PIX)
    const generateDirectPix = async (amount) => {
        const orderId = getOrderId();
        if (isProcessingPayment.value || !orderId) return false;
        
        isProcessingPayment.value = true;
        const apiClient = axios || window.axios;

        try {
            // Se o operador já colocou outro pagamento na gaveta (Ex: R$ 50 em dinheiro), salva primeiro
            const standardPayments = stagedPayments.value.filter(p => !p.is_pending);
            if (standardPayments.length > 0) {
                const itemsToPay = (reactiveItems?.value || []).filter(i => (Number(i.selected_parts) || 0) > 0).map(i => {
                    const qty = Number(i.selected_parts) / (Number(i.parts_per_unit) || 1);
                    return { id: i.id, pay_qty: qty, amount: Math.round(qty * Number(i.effective_unit_price || i.unit_price || 0) * 100) / 100 };
                });

                await apiClient.post(route('operations.orders.pay', orderId), {
                    payments: standardPayments.map(p => ({ method_id: p.real_method_id || p.method_id, amount: Number(p.amount) })),
                    items: itemsToPay,
                    service_fee: Math.max(0, Number(unref(serviceFeeAmount) || 0)),
                    cashback_used: Math.max(0, Number(unref(localCashbackAmountToSend) || 0)),
                    document: unref(customerDocument),
                    coupon_code: unref(appliedCheckoutCoupon)
                });
            }

            // Dispara API Bancária
            const pixResponse = await apiClient.post(route('operations.orders.generate-pix', orderId), { amount: Number(amount) });
            
            if (pixResponse.data?.success) {
                const targetOrder = getOrder();
                const applyData = (o) => {
                    o.txid = pixResponse.data.txid;
                    o.pix_payload = pixResponse.data.qr_code;
                    o.pix_expires_at = pixResponse.data.expires_at;
                    o.payment_intent_status = 'pending_pix';
                };
                if (targetOrder) applyData(targetOrder);
                
                const fromList = (page.props.initialOrders || []).find(o => o.id === orderId);
                if (fromList) applyData(fromList);

                // Esvazia os dinheiros/cartões e deixa só o PIX pendente na gaveta com a badge amarela
                stagedPayments.value = [{
                    id: 'pix_' + Date.now(),
                    real_method_id: 'pix',
                    method_name: 'PIX (Sicredi)',
                    engine_type: 'bank_api',
                    amount: Number(amount),
                    is_pending: true
                }];

                isProcessingPayment.value = false;
                return true;
            }
            throw new Error(pixResponse.data?.message || 'Falha ao gerar cobrança PIX.');
        } catch (error) {
            isProcessingPayment.value = false;
            toast.add({ severity: 'error', summary: 'Erro PIX Sicredi', detail: error.response?.data?.message || error.message || 'Falha na comunicação.', life: 5000 });
            return false;
        }
    };

    const processPayment = async () => {
        const orderId = getOrderId();
        if (isProcessingPayment.value || !orderId) return;

        isProcessingPayment.value = true;
        const apiClient = axios || window.axios;
        const standardPayments = stagedPayments.value.filter(p => !p.is_pending);

        const itemsToPay = (reactiveItems?.value || []).filter(i => (Number(i.selected_parts) || 0) > 0).map(i => {
            const qty = Number(i.selected_parts) / (Number(i.parts_per_unit) || 1);
            return { id: i.id, pay_qty: qty, amount: Math.round(qty * Number(i.effective_unit_price || i.unit_price || 0) * 100) / 100 };
        });

        try {
            await apiClient.post(route('operations.orders.pay', orderId), {
                payments: standardPayments.map(p => ({ method_id: p.real_method_id || p.method_id, amount: Number(p.amount) })),
                items: itemsToPay,
                service_fee: Math.max(0, Number(unref(serviceFeeAmount) || 0)),
                cashback_used: Math.max(0, Number(unref(localCashbackAmountToSend) || 0)),
                document: unref(customerDocument),
                coupon_code: unref(appliedCheckoutCoupon)
            }, { headers: { 'Accept': 'application/json' } });

            stagedPayments.value = [];
            localCashbackAmountToSend.value = 0;
            isPaymentSuccess.value = true;
            isProcessingPayment.value = false;
            if (isPostPaymentPhase) isPostPaymentPhase.value = true;
            executeAutoPrintRule();
        } catch (error) {
            isProcessingPayment.value = false;
            toast.add({ severity: 'error', summary: 'Erro de Pagamento', detail: error.response?.data?.message || 'Falha ao processar o pagamento.', life: 5000 });
        }
    };

    const handlePostPaymentAction = async (action) => {
        const type = typeof action === 'string' ? action : action?.type;
        if (['no_print', 'close', 'home'].includes(type)) { goBack(); return; }
        if (type === 'emit_nfce') { triggerSilentEmission(); return; }
        
        if (['reactivate', 'correct_payment'].includes(type)) {
            if (authActionType) authActionType.value = type;
            if (authNeedsPin) authNeedsPin.value = Boolean(props.companySettings?.require_pin_for_voids ?? true);
            if (isReasonModalVisible) isReasonModalVisible.value = true;
            return;
        }

        try {
            await (axios || window.axios).post(route('operations.finalize-receipt', getOrderId()), { print_action: type });
        } catch (error) {
            toast.add({ severity: 'error', summary: 'Erro', detail: error.response?.data?.message || "Falha na impressão.", life: 4000 });
        }
    };

    const confirmCancellation = async (authData) => {
        const orderId = getOrderId();
        const targetType = unref(authActionType);
        try {
            if (['reactivate', 'correct_payment'].includes(targetType)) {
                await router.post(route('operations.orders.reactivate', orderId), {
                    pin: authData.password || authData.pin, reason: authData.reason, mode: targetType
                }, {
                    preserveScroll: true, preserveState: true,
                    onSuccess: () => {
                        isReasonModalVisible.value = false;
                        if (isPostPaymentPhase) isPostPaymentPhase.value = false;
                        toast.add({ severity: 'success', summary: 'Conta Reaberta', detail: 'O status do pedido voltou para pendente.', life: 3000 });
                    }
                });
                return;
            }
            await (axios || window.axios).post(route('operations.orders.refund-and-clone', orderId), {
                pin: authData.password || authData.pin, reason: authData.reason, is_loss: authData.isLoss
            });
            isReasonModalVisible.value = false;
            router.reload({ only: ['initialOrders', 'cashierData'], preserveState: true, preserveScroll: true });
        } catch (e) {
            toast.add({ severity: "error", summary: "Acesso Negado", detail: e.response?.data?.message || "Erro interno.", life: 5000 });
        }
    };

    return { isEmittingNfce, isFiscalSuccess, fiscalEmitError, isProcessingPayment, isPaymentSuccess, processPayment, generateDirectPix, handlePostPaymentAction, confirmCancellation };
}
