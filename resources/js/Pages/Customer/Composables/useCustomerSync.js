import { useAnalytics } from '@/Composables/useAnalytics';
import { useTenantEcho } from '@/Composables/useTenantEcho';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from "primevue/usetoast";
import { onBeforeUnmount, onMounted, watch } from 'vue';

export function useCustomerSync(props, activeTab, couponContext, flowContext) {
    const page = usePage();
    const toast = useToast();
    const { initPixels } = useAnalytics();
    const { listenChannel } = useTenantEcho();

    const { fetchWallet, applyCoupon } = couponContext;
    const { initializeFlow, autoParkCurrent } = flowContext;

    const handlePopState = (event) => {
        if (event.state && event.state.tab) activeTab.value = event.state.tab;
    };

    const applyOrderRealTimeUpdate = (e) => {
        // 🚀 Suporte transparente tanto para payload enxuto (raiz) quanto empacotado
        const order = e?.orderData || e?.order || e;
        const currentCustomerId = page.props.customer?.id || localStorage.getItem('brasume_guest_anchor');
        const eventCustomerId = order?.customer_id;
        const orderId = order?.id || order?.orderId;
        const status = order?.status;

        // 🛡️ TRAVA ESTRITA: Só processa se o evento pertencer inequivocamente a este cliente
        if (!eventCustomerId || String(eventCustomerId) !== String(currentCustomerId)) {
            return;
        }

        const existingOrder = [...(props.myActiveOrders || []), ...(props.myHistory || [])]
            .find(o => String(o.id) === String(orderId));

        const hasStatusChanged = !existingOrder || existingOrder.status !== status;

        if (hasStatusChanged && status) {
            const statusMessages = {
                preparing: '👨‍🍳 Seu pedido começou a ser preparado!',
                ready: '✅ Seu pedido está pronto!',
                dispatched: '🛵 Seu pedido saiu para entrega!',
                closed: '✨ Pedido finalizado com sucesso!',
                cancelled: '❌ Pedido cancelado.'
            };

            if (statusMessages[status]) {
                toast.add({
                    severity: status === 'cancelled' ? 'error' : 'info',
                    summary: 'Status do Pedido',
                    detail: statusMessages[status],
                    life: 4000
                });
            }
        }

        router.reload({
            only: ['myActiveOrders', 'myHistory', 'customer'],
            preserveScroll: true,
            preserveState: true
        });
    };

    const handleShiftEvent = (isOpen, message) => {
        toast.add({
            severity: isOpen ? 'success' : 'warn',
            summary: isOpen ? 'Estamos Abertos!' : 'Expediente Encerrado',
            detail: message,
            life: 5000
        });

        router.reload({
            only: ['storeStatus', 'companySettings'],
            preserveScroll: true,
            preserveState: true
        });
    };

    onMounted(async () => {
        initPixels();
        await fetchWallet();

        const urlParams = new URLSearchParams(window.location.search);
        const couponParam = urlParams.get('auto_coupon') || urlParams.get('cupom') || urlParams.get('coupon');

        if (couponParam) {
            const cleanCode = couponParam.toUpperCase();
            localStorage.setItem('pos_magic_coupon', cleanCode);

            urlParams.delete('auto_coupon');
            urlParams.delete('cupom');
            urlParams.delete('coupon');
            const newQuery = urlParams.toString() ? '?' + urlParams.toString() : '';
            window.history.replaceState({}, document.title, window.location.pathname + newQuery);

            const success = await applyCoupon(cleanCode, 0);
            if (success) {
                toast.add({ severity: 'success', summary: 'Presente Ativado! 🎉', detail: `O desconto ${cleanCode} será aplicado no seu carrinho.`, life: 5000 });
            }
        }

        const path = window.location.pathname;
        if (path.includes('/customer/cart')) activeTab.value = 'cart';
        else if (path.includes('/customer/orders')) activeTab.value = 'orders';
        else if (path.includes('/customer/profile')) activeTab.value = 'account';
        else if (path.includes('/customer/home')) activeTab.value = 'home';
        else activeTab.value = 'menu';

        window.addEventListener('popstate', handlePopState);

        if (typeof initializeFlow === 'function') {
            initializeFlow(new URLSearchParams(window.location.search));
        }

        const serverCustomerId = page.props.customer?.id;
        if (serverCustomerId) {
            localStorage.setItem('brasume_guest_anchor', serverCustomerId);
        } else {
            const savedAnchor = localStorage.getItem('brasume_guest_anchor');
            if (savedAnchor) {
                axios.post(route('customer.auth.guest-restore'), { guest_id: savedAnchor })
                    .then(() => { router.reload({ preserveState: true, preserveScroll: true }); })
                    .catch(() => localStorage.removeItem('brasume_guest_anchor'));
            }
        }

        if (window.Echo) {
            const customerId = serverCustomerId || localStorage.getItem('brasume_guest_anchor');

            // 🚀 Canal privativo do cliente (sem duplicações de alias e sem canal fantasma 'orders')
            if (customerId) {
                listenChannel(`customer.${customerId}`, '.order.updated', applyOrderRealTimeUpdate);
                listenChannel(`customer.${customerId}`, '.order.cancelled', applyOrderRealTimeUpdate);
            }

            // 🚀 Sincronização de Turno: Atualiza "Aberto/Fechado" na hora
            listenChannel('shift', '.shift.opened', () => handleShiftEvent(true, 'A loja já está aceitando pedidos!'));
            listenChannel('shift', '.shift.closed', () => handleShiftEvent(false, 'A loja encerrou os pedidos por hoje.'));

            // 🚀 Ajustes da loja e Catálogo
            listenChannel('system', '.settings.updated', () => {
                router.reload({ only: ['storeStatus', 'companySettings'], preserveState: true, preserveScroll: true });
            });
            listenChannel('catalog', '.catalog.updated', () => {
                router.reload({ only: ['initialCategories', 'initialProducts'], preserveState: true, preserveScroll: true });
            });
        }
    });

    watch(activeTab, (newTab, oldTab) => {
        if (!newTab || newTab === oldTab) return;
        const newUrl = `/customer/${newTab}`;
        if (window.location.pathname !== newUrl) {
            window.history.pushState({ tab: newTab }, '', newUrl);
        }
    });

    onBeforeUnmount(() => {
        if (typeof autoParkCurrent === 'function') autoParkCurrent();
        window.removeEventListener('popstate', handlePopState);
    });
}
