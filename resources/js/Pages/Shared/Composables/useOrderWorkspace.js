import { useOrderSubmission } from '@/Pages/Shared/Composables/useOrderSubmission';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { computed, ref, unref, watch } from 'vue';

export function useOrderWorkspace(cartContext, favoritesContext, editOrderData) {
    const { cart, clearCart, draftRouting, orderType } = cartContext;
    const { favorites, fetchFavorites, clearFavorites } = favoritesContext;
    const toast = useToast();

    const isFavoritesViewActive = ref(false);

    const resolvedEditOrder = computed(() => unref(editOrderData));
    const resolvedOrderId = computed(() => resolvedEditOrder.value?.id || null);

    const currentCustomerId = computed(() => {
        const meta = draftRouting.value;
        if (!meta) return null;
        return meta.customer?.id || meta.sharedCustomer?.id || meta.address?.customer_id || meta.deliveryAddress?.customer_id || null;
    });

    watch(currentCustomerId, (newId) => {
        if (newId) fetchFavorites(newId);
        else {
            clearFavorites();
            isFavoritesViewActive.value = false;
        }
    }, { immediate: true });

    watch(favorites, (newFavs) => {
        isFavoritesViewActive.value = !!(newFavs && newFavs.length > 0);
    }, { deep: true });

    const activeCartSidebarView = ref('cart');
    const stashedCheckoutData = ref(null);

    const { submitOrder } = useOrderSubmission(cart, resolvedOrderId, 'pos_app');

    const handleClearWorkspace = () => {
        clearCart();
        activeCartSidebarView.value = 'cart';
        stashedCheckoutData.value = null;

        if (draftRouting.value) {
            draftRouting.value.order_id = null;
            draftRouting.value.existing_order_id = null;
            draftRouting.value.append_to_order = null;
        }

        if (typeof window !== 'undefined' && window.location.search) {
            const url = new URL(window.location.href);
            url.searchParams.delete('append_to_order');
            url.searchParams.delete('order_id');
            const cleanSearch = url.searchParams.toString() ? `?${url.searchParams.toString()}` : '';
            window.history.replaceState({}, document.title, `${url.pathname}${cleanSearch}`);
        }
    };

    const sanitizeMeta = (rawMeta) => {
        if (!rawMeta || typeof rawMeta !== 'object') return {};

        const rawCustomer = rawMeta.customer || {};
        const rawTable = rawMeta.table || {};
        const rawAddress = rawMeta.address || {};

        let parsedNeighborhood = '';
        if (rawAddress.neighborhood) {
            parsedNeighborhood = typeof rawAddress.neighborhood === 'object'
                ? (rawAddress.neighborhood.name || '')
                : String(rawAddress.neighborhood || '');
        }

        const resolvedTableId = rawTable.id || rawMeta.dining_table_id || rawMeta.table_id || null;
        const resolvedAddressId = rawAddress.id || rawMeta.address_id || null;

        return {
            ...rawMeta,
            order_id: rawMeta.order_id || rawMeta.id || resolvedOrderId.value || null,
            existing_order_id: rawMeta.existing_order_id || resolvedOrderId.value || null,
            type: rawMeta.type || orderType.value || 'table',
            dining_table_id: resolvedTableId,
            table_id: resolvedTableId,
            table: (rawMeta.table || resolvedTableId) ? {
                id: resolvedTableId,
                name: rawTable.name || rawTable.number || rawMeta.table_number || ''
            } : null,
            identifier: String(rawMeta.identifier || ''),
            customer: rawMeta.customer ? {
                id: rawCustomer.id || null,
                name: rawCustomer.name || '',
                phone: rawCustomer.phone || null
            } : null,
            customer_phone: rawMeta.customer_phone || rawCustomer.phone || null,
            address_id: resolvedAddressId,
            address: rawMeta.address ? {
                id: resolvedAddressId,
                street: rawAddress.street || '',
                number: rawAddress.number || '',
                neighborhood: parsedNeighborhood,
                delivery_fee: Number(rawAddress.delivery_fee || rawAddress.fee || 0)
            } : null
        };
    };

    const handleCheckoutWrapper = (checkoutData) => {
        const rawPayload = (checkoutData instanceof Event || checkoutData?.target) ? {} : (checkoutData || {});

        const cleanMeta = sanitizeMeta(rawPayload.meta || draftRouting.value);

        const isDelivery = cleanMeta.type === 'delivery' || !!cleanMeta.address || orderType.value === 'delivery';
        const isCounter = cleanMeta.type === 'counter' || orderType.value === 'counter';

        const safePayload = {
            ...rawPayload,
            order_id: resolvedOrderId.value || rawPayload.order_id || null,
            meta: cleanMeta,
            totals: rawPayload.totals || {}
        };

        if (isDelivery || isCounter) {
            safePayload.meta.type = isDelivery ? 'delivery' : 'counter';
            stashedCheckoutData.value = safePayload;
            activeCartSidebarView.value = 'payment';
            return Promise.resolve();
        } else {
            return submitOrder(safePayload);
        }
    };

    const handleInlinePaymentConfirm = (intentData) => {
        const targetOrderId = resolvedOrderId.value;
        const hasCartItems = (cart.value && cart.value.length > 0) || !!stashedCheckoutData.value;

        // Caso 1: Existe pedido carregado MAS NÃO HÁ itens no carrinho nem no checkout retido.
        // Trata-se de uma troca avulsa de método de pagamento da comanda já existente.
        if (targetOrderId && !hasCartItems) {
            return new Promise((resolve, reject) => {
                router.post(route('operations.orders.update-delivery-payment', targetOrderId), intentData, {
                    preserveScroll: true,
                    onSuccess: () => {
                        if (intentData.intent === 'pay_now' || intentData.payment_intent_status === 'pay_now') {
                            window.dispatchEvent(new CustomEvent('pos-open-checkout', { detail: { orderId: targetOrderId } }));
                        }
                        activeCartSidebarView.value = 'cart';
                        resolve({ order: { id: targetOrderId }, id: targetOrderId });
                    },
                    onError: reject
                });
            });
        }

        // Caso 2: Existem itens no carrinho (seja pedido novo ou append em pedido existente).
        if (!stashedCheckoutData.value) {
            return Promise.reject(new Error('Nenhum dado de pedido retido para checkout.'));
        }

        if (targetOrderId) {
            stashedCheckoutData.value.order_id = targetOrderId;
            if (!stashedCheckoutData.value.meta) {
                stashedCheckoutData.value.meta = {};
            }
            stashedCheckoutData.value.meta.order_id = targetOrderId;
            stashedCheckoutData.value.meta.existing_order_id = targetOrderId;
        }

        stashedCheckoutData.value.meta.payment_intent_status = intentData.payment_intent_status;
        stashedCheckoutData.value.meta.payment_intent_methods = intentData.payment_intent_methods;
        stashedCheckoutData.value.frontend_intent = intentData.intent || intentData.payment_intent_status;

        if (Array.isArray(intentData.payment_intent_methods)) {
            const cashMethod = intentData.payment_intent_methods.find(m => m.change_target);
            if (cashMethod) {
                stashedCheckoutData.value.meta.change_target = cashMethod.change_target;
            }
        }

        const submissionPromise = submitOrder(stashedCheckoutData.value);
        activeCartSidebarView.value = 'cart';
        return submissionPromise;
    };

    return {
        isFavoritesViewActive,
        activeCartSidebarView,
        stashedCheckoutData,
        handleClearWorkspace,
        handleCheckoutWrapper,
        handleInlinePaymentConfirm,
        handleInlineDeliveryPaymentConfirm: handleInlinePaymentConfirm
    };
}
