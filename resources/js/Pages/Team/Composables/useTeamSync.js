import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useTenantEcho } from '@/Composables/useTenantEcho';
import { onMounted } from 'vue';

export function useTeamSync(state = {}) {
    const toast = useToast();
    const echo = useTenantEcho();

    let reloadTimer = null;
    const reloadTeamData = () => {
        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => {
            router.reload({
                only: ['initialOrders', 'initialTableAreas', 'activeShift'],
                preserveState: true,
                preserveScroll: true
            });
        }, 300);
    };

    const handleOrderSignal = (e) => {
        const orderId = Number(e?.id || e?.orderId);

        if (state.selectedOrderContext?.value && orderId && Number(state.selectedOrderContext.value.id) === orderId) {
            if (e.status) state.selectedOrderContext.value.status = e.status;
            if (e.payment_status) state.selectedOrderContext.value.payment_status = e.payment_status;

            if (e.payment_status === 'paid' || e.status === 'closed') {
                toast.add({
                    severity: 'success',
                    summary: 'Conta Paga',
                    detail: 'A comanda selecionada foi quitada no caixa.',
                    life: 4000
                });
            }
        }

        reloadTeamData();
    };

    const initTeamSync = () => {
        // 🎯 Inscrição única no canal central de operações
        echo.listenChannel('operations', '.order.created', reloadTeamData);
        echo.listenChannel('operations', '.order.updated', handleOrderSignal);
        echo.listenChannel('operations', '.order.cancelled', reloadTeamData);
        echo.listenChannel('operations', '.table.updated', reloadTeamData);

        // Catálogo
        echo.listenChannel('catalog', '.catalog.updated', () => {
            router.reload({ only: ['initialCategories', 'initialProducts'], preserveState: true, preserveScroll: true });
        });

        // Turno
        echo.listenChannel('shift', '.shift.closed', () => {
            if (typeof state.onShiftClosed === 'function') state.onShiftClosed();
            toast.add({ severity: 'warn', summary: 'Turno Fechado', detail: 'O caixa encerrou o turno.', life: 5000 });
            reloadTeamData();
        });

        echo.listenChannel('shift', '.shift.opened', () => {
            toast.add({ severity: 'info', summary: 'Turno Aberto', detail: 'O caixa abriu um novo turno.', life: 3000 });
            reloadTeamData();
        });
    };

    onMounted(() => {
        initTeamSync();
    });

    return { initTeamSync };
}
