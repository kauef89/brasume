import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useTenantEcho } from '@/Composables/useTenantEcho';
import { onMounted } from 'vue';

export function usePosSync(activeTab, checkoutData) {
    const page = usePage();
    const toast = useToast();
    const echo = useTenantEcho();

    let reloadTimer = null;
    const reloadOperationsBoard = () => {
        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => {
            router.reload({
                only: ['initialOrders', 'managerData', 'cashierData', 'initialTableAreas', 'ordersHistory'],
                preserveState: true,
                preserveScroll: true
            });
        }, 300);
    };

    const handleOrderSignal = (e) => {
        const orderId = Number(e?.id || e?.orderId);

        // Se o operador está no checkout exatamente com esta conta aberta
        if (activeTab?.value === 'checkout' && checkoutData?.value?.order && orderId) {
            if (Number(checkoutData.value.order.id) === orderId) {
                if (e.status) checkoutData.value.order.status = e.status;
                if (e.payment_status) checkoutData.value.order.payment_status = e.payment_status;

                // 🚀 Notifica a tela de Checkout Pix apenas se houver confirmação Pix/Webhook
                if (e.payment_status === 'paid') {
                    window.dispatchEvent(new CustomEvent('pos-pix-approved', {
                        detail: {
                            orderId,
                            isPixWebhook: Boolean(e.is_pix_webhook)
                        }
                    }));
                }
            }
        }

        reloadOperationsBoard();
    };

    const initPosSync = () => {
        // 🎯 Inscrição única no canal central de operações
        echo.listenChannel('operations', '.order.created', reloadOperationsBoard);
        echo.listenChannel('operations', '.order.updated', handleOrderSignal);
        echo.listenChannel('operations', '.order.cancelled', handleOrderSignal);
        echo.listenChannel('operations', '.table.updated', reloadOperationsBoard);

        // Turno
        echo.listenChannel('shift', '.shift.opened', reloadOperationsBoard);
        echo.listenChannel('shift', '.shift.closed', reloadOperationsBoard);

        // Vitrine de produtos
        echo.listenChannel('catalog', '.catalog.updated', () => {
            if (activeTab?.value === 'checkout') return;
            router.reload({ only: ['catalog', 'initialProducts'], preserveState: true, preserveScroll: true });
        });
    };

    onMounted(() => {
        initPosSync();
    });

    return { initPosSync };
}
