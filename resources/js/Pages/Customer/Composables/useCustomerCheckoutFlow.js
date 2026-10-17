import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useAnalytics } from '@/Composables/useAnalytics';
import { useCoupons } from '@/Pages/Shared/Composables/useCoupons';

import { useCustomerCheckoutFinancials } from './useCustomerCheckoutFinancials';
import { useCustomerCheckoutPayload } from './useCustomerCheckoutPayload';
import { useCustomerCheckoutNavigation } from './useCustomerCheckoutNavigation';

export function useCustomerCheckoutFlow(props, cartContext, activeTab) {
    const toast = useToast();
    const { trackPurchase } = useAnalytics();
    const { cart, clearCart } = cartContext;
    const { activeCoupon, removeCoupon, calculateDiscount } = useCoupons();

    const isCheckoutModalVisible = ref(false);
    const isSubmitting = ref(false);
    const activePixData = ref(null);
    const activeOrderId = ref(null);

    const {
        checkoutState, headerState, goBack, openCheckout,
        cancelPixPayment, stepRef, isTableMode
    } = useCustomerCheckoutNavigation(
        props, cartContext, activeTab, isCheckoutModalVisible,
        isSubmitting, activePixData, activeOrderId
    );

    const { loyalty, financials } = useCustomerCheckoutFinancials(props, cartContext, checkoutState, calculateDiscount);
    const { buildPayload } = useCustomerCheckoutPayload(props, cartContext, financials, activeCoupon, checkoutState);

    const handleCheckoutLogin = (credentials) => {
        isSubmitting.value = true;
        router.post(route('customer.auth.login'), credentials, {
            preserveState: true, preserveScroll: true,
            onSuccess: (pageResp) => {
                isSubmitting.value = false;
                if (Object.keys(pageResp.props.errors).length === 0) checkoutState.step = isTableMode.value ? 'payment' : 'logistics';
            },
            onError: (errors) => {
                isSubmitting.value = false;
                toast.add({ severity: 'error', summary: 'Ops!', detail: Object.values(errors)[0], life: 4000 });
            }
        });
    };

    const finalizeOrder = () => {
        if (isSubmitting.value) return;

        isSubmitting.value = true;
        const backupCart = JSON.parse(JSON.stringify(cart.value));
        const finalSnapshot = financials.value.finalTotal;
        const payload = buildPayload(finalSnapshot);

        router.post(route('customer.checkout.store'), payload, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (pageResp) => {
                            isSubmitting.value = false;

                            if (pageResp.props.flash?.error) {
                                cart.value = backupCart;
                                toast.add({ severity: 'error', summary: 'Erro no Pedido', detail: pageResp.props.flash.error, life: 5000 });
                                return;
                            }

                            const returnedOrderId = pageResp.props.flash?.order_id || pageResp.props.order_id || pageResp.props.order?.id;
                            const returnedPixData = pageResp.props.flash?.pix_data || pageResp.props.pix_data || pageResp.props.order?.pix_data;
                            const methodType = checkoutState.selectedMethod?.engine_type || (returnedPixData ? 'bank_api' : 'local');

                            // 🚀 Como o pedido já foi gravado no banco, limpa o carrinho imediatamente.
                            // Isso evita que itens persistam se o cliente fechar a aba ou pagar direto no app bancário.
                            clearCart();
                            removeCoupon();

                            if (methodType === 'bank_api' || returnedPixData) {
                                if (returnedPixData) {
                                    activeOrderId.value = returnedOrderId;
                                    activePixData.value = returnedPixData;
                                    checkoutState.step = 'pix';

                                    sessionStorage.setItem('customer_active_pix_order_id', String(returnedOrderId));
                                    sessionStorage.setItem('customer_active_pix_data', JSON.stringify(returnedPixData));

                                    sessionStorage.removeItem('customer_pending_checkout');
                                    sessionStorage.removeItem('customer_checkout_saved_state');
                                } else {
                                    isCheckoutModalVisible.value = false;
                                    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Não foi possível carregar o QR Code. Tente novamente.', life: 5000 });
                                }
                            } else {
                                isCheckoutModalVisible.value = false;
                                activeTab.value = isTableMode.value ? 'orders' : 'home';
                                toast.add({ severity: 'success', summary: 'Pedido Recebido!', detail: 'O seu pedido já está sendo preparado!', life: 4000 });
                            }
                        },
            onError: (errors) => {
                isSubmitting.value = false;
                cart.value = backupCart;
                cancelPixPayment();
                toast.add({ severity: 'error', summary: 'Não foi possível finalizar', detail: Object.values(errors)[0], life: 5000 });
            }
        });
    };

    const handlePixSuccess = () => {
        clearCart();
        removeCoupon();
        isCheckoutModalVisible.value = false;
        activePixData.value = null;
        activeOrderId.value = null;
        sessionStorage.removeItem('customer_active_pix_order_id');
        sessionStorage.removeItem('customer_active_pix_data');
        activeTab.value = isTableMode.value ? 'orders' : 'home';
        toast.add({ severity: 'success', summary: 'Pagamento Aprovado!', detail: 'O seu pedido já está sendo preparado!', life: 4000 });
    };

    // 🚀 Listener global para reabrir o QR Code a partir do card na Home
    const handleResumePixEvent = (e) => {
        const order = e.detail?.order;
        if (!order) return;

        activeOrderId.value = order.id;

        const savedPix = sessionStorage.getItem('customer_active_pix_data');
        if (savedPix) {
            try {
                activePixData.value = JSON.parse(savedPix);
            } catch (err) {
                activePixData.value = savedPix;
            }
        } else if (order.txid) {
            activePixData.value = {
                txid: order.txid,
                qr_code: order.txid,
                pixCopiaECola: order.txid
            };
        }

        checkoutState.step = 'pix';
        isCheckoutModalVisible.value = true;
    };

    onMounted(() => {
        window.addEventListener('customer-resume-pix', handleResumePixEvent);
    });

    onUnmounted(() => {
        window.removeEventListener('customer-resume-pix', handleResumePixEvent);
    });

    return {
        isCheckoutModalVisible, isSubmitting, activePixData, activeOrderId,
        stepRef, headerState, goBack, checkoutState, loyalty, financials,
        openCheckout, handleCheckoutLogin, finalizeOrder, handlePixSuccess
    };
}
