import { reactive, computed, watch, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useAnalytics } from '@/Composables/useAnalytics';

export function useCustomerCheckoutNavigation(props, cartContext, activeTab, isCheckoutModalVisible, isSubmitting, activePixData, activeOrderId) {
    const page = usePage();
    const { trackInitiateCheckout } = useAnalytics();
    const { cart, cartSubtotal } = cartContext;

    const stepRef = ref(null);
    const isTableMode = computed(() => Boolean(page.props.tableMode));

    const checkoutState = reactive({
        step: isTableMode.value ? 'payment' : 'logistics',
        authSubStep: 'methods',
        guestName: '',
        guestPhone: '',
        addressSubStep: 1,
        orderType: isTableMode.value ? 'table' : (cartContext.orderType?.value || 'delivery'),
        routingMeta: isTableMode.value ? {
            type: 'table',
            table_id: page.props.tableMode?.id,
            table: page.props.tableMode
        } : null,
        selectedMethod: null,
        cashChangeTarget: '',
        isTypingCoupon: false,
        useCashbackToggle: false,
    });

    const headerState = computed(() => {
        const isGuest = props.isGuest || !page.props.auth?.user;

        if (isTableMode.value) {
            const max = isGuest ? 2 : 1;
            let current = 1;
            let title = 'Finalizar Pedido na Mesa';

            if (checkoutState.step === 'auth') {
                current = 1;
                title = 'Identifique-se para Pedir';
            } else if (checkoutState.step === 'payment' || checkoutState.step === 'pix') {
                current = max;
                title = checkoutState.step === 'pix' ? 'Pagamento PIX da Mesa' : 'Como prefere pagar?';
            }

            
    watch(() => cart.value, () => {
        if (checkoutState.step === 'pix') {
            checkoutState.step = 'payment';
            activePixData.value = null;
        }
    }, { deep: true });

    return { title, current, max };
        }

        const max = isGuest ? 4 : 2;
        let current = 1;
        let title = 'Finalizar Pedido';

        if (checkoutState.step === 'auth') {
            current = 1;
            if (checkoutState.authSubStep === 'methods') title = 'Identifique-se';
            else if (checkoutState.authSubStep === 'guest_name') title = 'Como podemos te chamar?';
            else if (checkoutState.authSubStep === 'guest_phone') title = 'Qual seu WhatsApp?';
        } else if (checkoutState.step === 'logistics') {
            current = isGuest ? 2 : 1;
            title = 'Como quer receber?';
        } else if (checkoutState.step === 'new_address') {
            current = isGuest ? 3 : 1;
            title = checkoutState.addressSubStep === 1 ? 'Novo Endereço' : 'Detalhes do Local';
        } else if (checkoutState.step === 'payment' || checkoutState.step === 'pix') {
            current = max;
            if (checkoutState.step === 'pix') title = 'Pagamento PIX';
            else if (checkoutState.isTypingCoupon) title = 'Adicionar Cupom';
            else title = 'Como prefere pagar?';
        }

        
    watch(() => cart.value, () => {
        if (checkoutState.step === 'pix') {
            checkoutState.step = 'payment';
            activePixData.value = null;
        }
    }, { deep: true });

    return { title, current, max };
    });

    const goBack = () => {
        if (stepRef.value && typeof stepRef.value.goBack === 'function') {
            if (stepRef.value.goBack()) return;
        }

        const isGuest = props.isGuest || !page.props.auth?.user;

        if (checkoutState.step === 'auth') {
            if (checkoutState.authSubStep === 'guest_phone') {
                checkoutState.authSubStep = 'guest_name';
                return;
            }
            if (checkoutState.authSubStep === 'guest_name') {
                checkoutState.authSubStep = 'methods';
                return;
            }
            isCheckoutModalVisible.value = false;
            return;
        }

        if (isTableMode.value && checkoutState.step === 'payment') {
            if (checkoutState.isTypingCoupon) {
                checkoutState.isTypingCoupon = false;
            } else {
                isCheckoutModalVisible.value = false;
            }
            return;
        }

        if (checkoutState.step === 'logistics') {
            if (isGuest) {
                checkoutState.step = 'auth';
                checkoutState.authSubStep = 'guest_phone';
            } else {
                isCheckoutModalVisible.value = false;
            }
            return;
        }

        if (checkoutState.step === 'new_address') {
            checkoutState.step = 'logistics';
            checkoutState.orderType = '';
            checkoutState.routingMeta = null;
            return;
        }

        if (checkoutState.step === 'payment') {
            if (checkoutState.isTypingCoupon) {
                checkoutState.isTypingCoupon = false;
            } else {
                checkoutState.step = 'logistics';
            }
            return;
        }

        if (checkoutState.step === 'pix') {
            // Volta para a seleção de forma de pagamento mantendo o carrinho intacto
            cancelPixPayment(true);
            return;
        }
    };

    const cancelPixPayment = (forceCancelOrder = false) => {
        if (activeOrderId.value && forceCancelOrder) {
            router.post(route('customer.orders.cancel', activeOrderId.value), {}, {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    checkoutState.step = 'payment';
                    activePixData.value = null;
                    activeOrderId.value = null;
                    sessionStorage.removeItem('customer_active_pix_data');
                    sessionStorage.removeItem('customer_active_pix_order_id');
                }
            });
        } else {
            checkoutState.step = 'payment';
            activePixData.value = null;
            activeOrderId.value = null;
            sessionStorage.removeItem('customer_active_pix_data');
            sessionStorage.removeItem('customer_active_pix_order_id');
        }
    };

        const openCheckout = () => {
        const isGuest = props.isGuest || !page.props.auth?.user;
        const needsAuth = isGuest && !props.customer;

        if (needsAuth) {
            checkoutState.step = 'auth';
        } else if (isTableMode.value) {
            checkoutState.step = 'payment';
            checkoutState.orderType = 'table';
            checkoutState.routingMeta = {
                type: 'table',
                table_id: page.props.tableMode?.id,
                table: page.props.tableMode
            };
        } else {
            // Se já definiu logística (endereço selecionado ou retirada no balcão), retoma DIRETO no pagamento
            const hasLogistics = checkoutState.orderType && (
                checkoutState.orderType !== 'delivery' ||
                checkoutState.routingMeta?.address ||
                checkoutState.routingMeta?.address_id
            );

            if (hasLogistics) {
                checkoutState.step = 'payment';
            } else {
                checkoutState.step = 'logistics';
            }
        }

        checkoutState.authSubStep = 'methods';
        checkoutState.addressSubStep = 1;
        checkoutState.isTypingCoupon = false;

        sessionStorage.setItem('customer_pending_checkout', 'true');
        trackInitiateCheckout(cartSubtotal.value);

        isCheckoutModalVisible.value = true;
    };

    watch(isCheckoutModalVisible, (visible) => {
        if (!visible && !isSubmitting.value && checkoutState.step !== 'pix') {
            sessionStorage.removeItem('customer_pending_checkout');
            sessionStorage.removeItem('customer_checkout_saved_state');
        }
    });

    watch(
        () => [checkoutState.orderType, checkoutState.routingMeta],
        ([orderType, routingMeta]) => {
            if (isCheckoutModalVisible.value && orderType) {
                sessionStorage.setItem(
                    'customer_checkout_saved_state',
                    JSON.stringify({ orderType, routingMeta })
                );
            }
        },
        { deep: true }
    );

    onMounted(() => {
        const pendingCheckout = sessionStorage.getItem('customer_pending_checkout');
        const urlParams = new URLSearchParams(window.location.search);
        const hasCheckoutParam = urlParams.get('checkout') === 'true' || urlParams.get('resume_checkout') === 'true';

        if ((pendingCheckout === 'true' || hasCheckoutParam) && cart.value && cart.value.length > 0) {
            sessionStorage.removeItem('customer_pending_checkout');

            if (hasCheckoutParam) {
                urlParams.delete('checkout');
                urlParams.delete('resume_checkout');
                const newQuery = urlParams.toString() ? `?${urlParams.toString()}` : '';
                window.history.replaceState({}, document.title, `${window.location.pathname}${newQuery}`);
            }

            const savedState = sessionStorage.getItem('customer_checkout_saved_state');
            if (savedState) {
                try {
                    const parsed = JSON.parse(savedState);
                    if (parsed.orderType) checkoutState.orderType = parsed.orderType;
                    if (parsed.routingMeta) checkoutState.routingMeta = parsed.routingMeta;
                } catch (e) {}
                sessionStorage.removeItem('customer_checkout_saved_state');
            }

            if (activeTab && activeTab.value) {
                activeTab.value = 'cart';
            }

            setTimeout(() => {
                openCheckout();
            }, 250);
        }
    });

    
    watch(() => cart.value, () => {
        if (checkoutState.step === 'pix') {
            checkoutState.step = 'payment';
            activePixData.value = null;
        }
    }, { deep: true });

    return {
        checkoutState,
        headerState,
        goBack,
        openCheckout,
        cancelPixPayment,
        stepRef,
        isTableMode
    };
}
