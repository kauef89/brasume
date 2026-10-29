import { useWhatsAppMessages } from "@/Composables/useWhatsAppMessages";
import { router } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";

export function useControlPanel(initialOrders) {
    const activeTab = ref("active");
    const searchQuery = ref("");
    const { notifyDeliveryOut, notifyPickupReady } = useWhatsAppMessages();

    const focusedOrderId = ref(null);

    const focusedOrder = computed(() => {
        if (!focusedOrderId.value || !initialOrders.value) return null;
        return initialOrders.value.find(o => o.id == focusedOrderId.value) || null;
    });

    // 🚀 SINCRONIZAÇÃO NATIVA COM A HISTORY API (?open_order=123)
    watch(focusedOrderId, (newId) => {
        const url = new URL(window.location.href);
        if (newId) {
            if (url.searchParams.get('open_order') !== String(newId)) {
                url.searchParams.set('open_order', newId);
                window.history.pushState({ open_order: newId }, '', url.toString());
            }
        } else {
            if (url.searchParams.has('open_order')) {
                url.searchParams.delete('open_order');
                window.history.pushState({}, '', url.toString());
            }
        }
    });

    const handlePopState = () => {
        const urlParams = new URLSearchParams(window.location.search);
        const orderIdFromUrl = urlParams.get('open_order');
        focusedOrderId.value = orderIdFromUrl ? Number(orderIdFromUrl) : null;
    };

    onMounted(() => {
        window.addEventListener('popstate', handlePopState);
        const urlParams = new URLSearchParams(window.location.search);
        const initialOpenId = urlParams.get('open_order');
        if (initialOpenId) {
            focusedOrderId.value = Number(initialOpenId);
        }
    });

    onBeforeUnmount(() => {
        window.removeEventListener('popstate', handlePopState);
    });

    const contextMenuVisible = ref(false);
    const selectedOrderContext = ref(null);
    const orderToModify = ref(null);
    const isTransformerVisible = ref(false);
    const isPaymentModalVisible = ref(false);
    const orderToUpdatePayment = ref(null);
    const isChangeAddressModalVisible = ref(false);
    const orderToUpdateAddress = ref(null);
    const isTransferModalVisible = ref(false);
    const transferSourceOrder = ref(null);
    const transferItemIds = ref([]);
    const highlightedOrderId = ref(null);

    const orderMatchesSearch = (order, queryStr, queryNum) => {
        if (!queryStr) return true;

        const searchableValues = [
            String(order.id || ''),
            order.daily_code,
            order.customer_name,
            order.identifier,
            order.phone,
            order.customer?.phone,
            order.customer?.cpf,
            String(order.table_number || ''),
            order.address,
            order.neighborhood,
            order.delivery_neighborhood,
            order.routing?.address?.neighborhood,
            order.meta?.address?.neighborhood
        ];

        const searchString = searchableValues
            .filter(val => val !== null && val !== undefined && val !== '')
            .join(' ')
            .toLowerCase();

        if (queryNum && searchString.replace(/\D/g, '').includes(queryNum)) {
            return true;
        }

        return searchString.includes(queryStr);
    };

    const activeOrders = computed(() => {
        if (!initialOrders.value) return [];
        const queryStr = (searchQuery.value || "").toLowerCase().replace(/^#\s*/, '').trim();
        const queryNum = queryStr.replace(/\D/g, '');

        return initialOrders.value.filter((o) => {
            const isActive = !["closed", "cancelled", "completed", "delivered"].includes(o.status);
            return isActive && orderMatchesSearch(o, queryStr, queryNum);
        });
    });

    const onSiteOrders = computed(() => {
        const filtered = activeOrders.value.filter((o) => o.type === "table");
        return filtered.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    });

    // 🚀 Suporta delivery, counter, pickup, takeaway e qualquer comanda que não seja mesa física
    const deliveryOrders = computed(() => {
        const filtered = activeOrders.value.filter((o) => o.type !== "table");
        return filtered.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    });

    const historyOrders = computed(() => {
        if (!initialOrders.value) return [];
        const queryStr = (searchQuery.value || "").toLowerCase().replace(/^#\s*/, '').trim();
        const queryNum = queryStr.replace(/\D/g, '');

        return initialOrders.value
            .filter((o) => {
                const isClosedOrCancelled = ["closed", "cancelled", "completed", "delivered"].includes(o.status);
                return isClosedOrCancelled && orderMatchesSearch(o, queryStr, queryNum);
            })
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    });

    const openContextMenu = (order) => {
        if (order?.id) {
            focusedOrderId.value = order.id;
        }
    };

    const handleMenuAction = (action, order) => {
        contextMenuVisible.value = false;
        switch (action) {
                        case "finish_order":
                router.post(route('operations.orders.update-status', order.id), {
                    status: 'closed'
                }, {
                    preserveScroll: true,
                    preserveState: true
                });
                break;
            case "checkout":
                router.visit(route('operations.checkout', order.id));
                break;
            case "add_items": {
                const currentPath = window.location.pathname.replace('/orders', '/pos');
                window.history.replaceState({}, '', `${currentPath}?append_to_order=${order.id}`);
                window.dispatchEvent(new CustomEvent('pos-append-to-order', { detail: order }));
                window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'pos' }));
                break;
            }
            case "transform_order":
                orderToModify.value = order;
                isTransformerVisible.value = true;
                break;
            case "delivery_payment":
                orderToUpdatePayment.value = order;
                isPaymentModalVisible.value = true;
                break;
            case "change_address":
                orderToUpdateAddress.value = order;
                isChangeAddressModalVisible.value = true;
                break;
            case "print_partial":
                alert(`Enviando impressão de conferência do pedido #${order.id}...`);
                break;
        }
    };

    const handleChangeAddressConfirm = (payload) => {
        router.post(route('operations.orders.change-address', payload.order_id), {
            address_id: payload.new_address_id,
            address: payload.new_address,
            delivery_fee: payload.new_delivery_fee
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { isChangeAddressModalVisible.value = false; },
            onError: () => alert("Erro ao atualizar o endereço do pedido.")
        });
    };

    const handleAdvanceStatus = (order, newStatus) => {
        const isPaid = order.payment_status === 'paid' || order.payment_intent_status === 'paid';

        if (
            order.type === 'delivery' &&
            (newStatus === 'ready' || newStatus === 'dispatched') &&
            !isPaid &&
            order.payment_intent_status === 'pending_choice'
        ) {
            alert("Atenção: Este pedido está configurado como 'Decidir Depois'. Defina a forma de pagamento primeiro.");
            focusedOrderId.value = order.id;
            return;
        }

        if (order.type === 'counter' && order.status === 'ready' && newStatus === 'dispatched') {
            if (order.payment_status === 'paid') {
                handleAdvanceStatus(order, 'closed');
                return;
            }
            if (confirm("Pedidos de Balcão não possuem entrega. Deseja abrir a tela de recebimento para liquidar a conta?")) {
                window.dispatchEvent(new CustomEvent('pos-open-checkout', { detail: { orderId: order.id } }));
            }
            return;
        }

        if (newStatus === 'closed') {
            if (order.payment_status !== 'paid') {
                if (confirm(`O Pedido #${order.id} ainda não foi pago. Deseja abrir a tela de recebimento para liquidar a conta?`)) {
                    window.dispatchEvent(new CustomEvent('pos-open-checkout', { detail: { orderId: order.id } }));
                }
                return;
            }

            if (!confirm(`Confirmar a entrega e finalizar o Pedido #${order.id}?`)) {
                return;
            }
        }

        let payload = { status: newStatus };
        if (order.status === 'pending' && (newStatus === 'ready' || newStatus === 'dispatched')) {
            payload.stamp_intermediates = true;
        }

        router.post(route('operations.orders.update-status', order.id), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (order.type === 'delivery' && newStatus === 'ready') {
                    if (confirm("Pedido pronto! Enviar ticket de despacho para o Motoboy?")) {
                        router.post(route('operations.orders.print-delivery', order.id), {}, { preserveScroll: true, preserveState: true });
                    }
                }

                if (order.type === 'delivery' && newStatus === 'dispatched') {
                    if (confirm("Pedido em rota! Enviar mensagem no WhatsApp do cliente?")) {
                        notifyDeliveryOut(order);
                    }
                }

                if (order.type === 'counter' && newStatus === 'ready') {
                    const phone = order.customer?.phone || order.customer_phone || null;
                    if (phone && phone.replace(/\D/g, '').length >= 10) {
                        if (confirm("Pedido de balcão pronto! Notificar cliente no WhatsApp?")) {
                            notifyPickupReady(order);
                        }
                    }
                }
            }
        });
    };

    const handleUpdatePayment = (intentData) => {
        router.post(route('operations.orders.update-delivery-payment', orderToUpdatePayment.value.id), intentData, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { isPaymentModalVisible.value = false; },
            onError: () => alert("Erro ao atualizar o pagamento."),
        });
    };

    const handleTransferTrigger = (data) => {
        transferSourceOrder.value = data.order;
        transferItemIds.value = data.itemIds;
        isTransferModalVisible.value = true;
    };

    const handleExecuteTransfer = (transferPayload) => {
        router.post(route('operations.transfer-items'), transferPayload, {
            preserveState: true,
            onSuccess: () => { isTransferModalVisible.value = false; }
        });
    };

    const handleTransformation = (payload) => {
        router.post(route('operations.orders.transform', payload.order.id), payload, {
            preserveState: true,
            onSuccess: () => { isTransformerVisible.value = false; },
        });
    };

    return {
        activeTab,
        searchQuery,
        contextMenuVisible,
        selectedOrderContext,
        orderToModify,
        isTransformerVisible,
        isPaymentModalVisible,
        orderToUpdatePayment,
        isChangeAddressModalVisible,
        orderToUpdateAddress,
        isTransferModalVisible,
        transferSourceOrder,
        transferItemIds,
        highlightedOrderId,
        activeOrders,
        onSiteOrders,
        deliveryOrders,
        historyOrders,
        openContextMenu,
        handleMenuAction,
        handleChangeAddressConfirm,
        handleAdvanceStatus,
        handleUpdatePayment,
        handleTransferTrigger,
        handleExecuteTransfer,
        handleTransformation,
        focusedOrderId,
        focusedOrder
    };
}
