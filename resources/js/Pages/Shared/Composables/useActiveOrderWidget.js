import { useTenantEcho } from '@/Composables/useTenantEcho';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { computed, isRef, onMounted, onUnmounted, ref, watch } from 'vue';

export function useActiveOrderWidget(initialOrders = []) {
    const toast = useToast();
    const page = usePage();
    const { listenChannel } = useTenantEcho();

    const getInitialArray = () => {
        if (isRef(initialOrders)) return initialOrders.value || [];
        if (typeof initialOrders === 'function') return initialOrders() || [];
        return initialOrders || [];
    };

    const activeOrders = ref(getInitialArray());

    watch(() => getInitialArray(), (newVal) => {
        if (Array.isArray(newVal)) {
            activeOrders.value = [...newVal];
        }
    }, { deep: true });

    const primaryOrder = computed(() => {
        if (!activeOrders.value || activeOrders.value.length === 0) return null;
        return activeOrders.value[0];
    });

    const hasActiveOrder = computed(() => primaryOrder.value !== null);

    const canCancel = computed(() => {
        if (!primaryOrder.value) return false;
        return ['pending', 'pending_payment'].includes(primaryOrder.value.status);
    });

    const canAppendItems = computed(() => {
        if (!primaryOrder.value) return false;
        return ['pending', 'pending_payment', 'preparing'].includes(primaryOrder.value.status);
    });

    // 🚀 FASES CORRIGIDAS: 'dispatched' pertence ao Passo 3 (Em Rota)
    const currentTimelineStep = computed(() => {
        if (!primaryOrder.value) return 0;
        const status = primaryOrder.value.status;
        const isDelivery = primaryOrder.value.type === 'delivery';

        // Passo 1: Recebido / Aguardando confirmação
        if (['pending', 'pending_payment'].includes(status)) return 1;

        if (isDelivery) {
            // Delivery Passo 2: Sendo preparado ou Embalado na cozinha
            if (['preparing', 'ready'].includes(status)) return 2;
            // Delivery Passo 3: Com o motoboy / Entregue / Concluído
            if (['dispatched', 'out_for_delivery', 'delivered', 'closed', 'completed'].includes(status)) return 3;
        } else {
            // Balcão / Mesa
            if (['preparing'].includes(status)) return 2;
            if (['ready', 'closed', 'completed'].includes(status)) return 3;
        }

        return 0;
    });

    const now = ref(Date.now());
    let timerInterval = null;

    const timeSinceUpdate = computed(() => {
        if (!primaryOrder.value?.updated_at) return '';
        const diffMs = now.value - new Date(primaryOrder.value.updated_at).getTime();
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'agora mesmo';
        if (diffMins < 60) return `há ${diffMins} min`;
        const hours = Math.floor(diffMins / 60);
        return `há ${hours} h`;
    });

    const cancelOrder = (orderId) => {
        router.post(route('customer.orders.cancel', orderId), { preserveState: true, }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (pageResp) => {
                if (pageResp.props.flash?.success) toast.add({ severity: 'success', summary: 'Cancelado', detail: pageResp.props.flash.success, life: 5000 });
            },
            onError: (errors) => toast.add({ severity: 'error', summary: 'Ops!', detail: Object.values(errors)[0] || 'Erro ao cancelar.', life: 5000 })
        });
    };

    const appendItems = (orderId) => {
        if (!orderId) return;

        // 1. Grava o ID do pedido que receberá os novos itens
        sessionStorage.setItem('customer_append_order_id', String(orderId));

        // 2. Dispara evento local para troca instantânea de aba sem reload
        window.dispatchEvent(new CustomEvent('customer-switch-tab', { detail: 'menu' }));

        // 3. Redirecionamento seguro usando o nome correto da rota registrada ('customer.app')
        if (typeof route === 'function' && route().has('customer.app')) {
            router.visit(route('customer.app', { tab: 'menu' }), {
                preserveState: true,
                preserveScroll: true
            });
        }
    };

    // 🚀 Aplicação reativa imutável da atualização
    const applyOrderUpdate = (payload) => {
        const incoming = payload?.orderData || payload?.order || payload;
        if (!incoming || !incoming.id) return;

        const incomingId = Number(incoming.id);
        const index = activeOrders.value.findIndex(o => Number(o.id) === incomingId);

        if (index !== -1) {
            // Cria um novo array para disparar a reatividade imediata do Vue
            const updated = [...activeOrders.value];
            updated[index] = { ...updated[index], ...incoming };
            activeOrders.value = updated;
            now.value = Date.now();

            if (incoming.status === 'preparing') {
                toast.add({ severity: 'info', summary: 'Na Cozinha!', detail: 'Seu pedido começou a ser preparado.', life: 5000 });
            } else if (['dispatched', 'out_for_delivery'].includes(incoming.status)) {
                toast.add({ severity: 'info', summary: 'Saiu para entrega! 🛵', detail: 'O entregador já está a caminho.', life: 6000 });
            } else if (['closed', 'cancelled'].includes(incoming.status)) {
                activeOrders.value = activeOrders.value.filter(o => Number(o.id) !== incomingId);
            }
        }
    };

    const listenToOrders = () => {
        if (typeof window.Echo === 'undefined') return;

        // 1. Antena Canal Tenant Global
        try {
            // 🚀 CORREÇÃO: Usamos o listenChannel nativo.
            // Ele faz o stopListening automático no onBeforeUnmount sem matar o canal inteiro!
            listenChannel('orders', '.order.updated', applyOrderUpdate);
            listenChannel('orders', 'OrderUpdated', applyOrderUpdate);
        } catch (e) {}

        // 2. Antena Canal Específico do Pedido
        activeOrders.value.forEach(order => {
            window.Echo.channel(`order.${order.id}`)
                .listen('.order.updated', applyOrderUpdate)
                .listen('OrderUpdated', applyOrderUpdate);
        });
    };

    const stopListening = () => {
        if (typeof window.Echo === 'undefined') return;

        // 🚀 CORREÇÃO: Removemos o leave global. Fecha apenas os canais específicos dos pedidos concluídos.
        activeOrders.value.forEach(order => window.Echo.leave(`order.${order.id}`));
    };

    watch(() => activeOrders.value.map(o => o.id).join(','), () => {
        stopListening();
        listenToOrders();
    });

    onMounted(() => {
        listenToOrders();
        timerInterval = setInterval(() => now.value = Date.now(), 60000);
    });

    onUnmounted(() => {
        stopListening();
        if (timerInterval) clearInterval(timerInterval);
    });

    return {
        activeOrders, primaryOrder, hasActiveOrder, canCancel, canAppendItems,
        currentTimelineStep, timeSinceUpdate, cancelOrder, appendItems
    };
}
