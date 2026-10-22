import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

export function useFiscalBatchEmitter(props, unifiedHistory) {
    const toast = useToast();
    const isRetryingFiscal = ref(false);

    const pendingFiscalOrders = computed(() => {
            const list = (props.ordersHistory && props.ordersHistory.length > 0)
                ? props.ordersHistory
                : (unifiedHistory.value || []).filter(item => item._type === 'order');

            return list.filter(order => {
                const isNotCancelled = order.status !== 'cancelled';
                const isNotAuthorized = order.fiscal_status !== 'authorized';

                // 🚀 REGRA FISCAL: Não tentar reemitir em lote pedidos intencionalmente dispensados
                const isNotSkipped = order.fiscal_status !== 'skipped';

                // 🛑 REGRA FISCAL ESTRITA: Apenas pedidos QUITADOS entram no lote
                const hasPayment = order.payment_status === 'paid' || (Array.isArray(order.payments) && order.payments.length > 0);
                const isSettled = ['closed', 'completed'].includes(order.status) || order.payment_status === 'paid';
                const isNotOpenTable = !(order.type === 'table' && order.status !== 'closed' && order.payment_status !== 'paid');

                return isNotCancelled && isNotAuthorized && isNotSkipped && hasPayment && isSettled && isNotOpenTable;
            });
        });

    const retryFailedFiscal = async () => {
        if (isRetryingFiscal.value) return;

        if (pendingFiscalOrders.value.length === 0) {
            toast.add({
                severity: 'info',
                summary: 'NFC-e em Dia',
                detail: 'Nenhum cupom fiscal pendente de emissão para os pedidos pagos deste turno.',
                life: 3000
            });
            return;
        }

        isRetryingFiscal.value = true;
        let successCount = 0;
        let failCount = 0;
        const errorsList = [];

        for (const order of pendingFiscalOrders.value) {
            try {
                const endpoint = window.route && route().has('operations.orders.emit-nfce')
                    ? route('operations.orders.emit-nfce', order.id)
                    : `/operations/orders/${order.id}/emit-nfce`;

                const res = await axios.post(endpoint, {}, { headers: { Accept: 'application/json' } });

                if (res.data?.success !== false) {
                    successCount++;
                } else {
                    failCount++;
                    errorsList.push(`#${order.id}: ${res.data.message || 'Erro Sefaz'}`);
                }
            } catch (error) {
                failCount++;
                const msg = error.response?.data?.message || error.message || 'Erro Sefaz';
                errorsList.push(`#${order.id}: ${msg}`);
            }
        }

        isRetryingFiscal.value = false;

        if (successCount > 0) {
            toast.add({
                severity: 'success',
                summary: 'Emissão Concluída',
                detail: `${successCount} cupom(ns) fiscal(is) autorizado(s) com sucesso na SEFAZ!`,
                life: 4000
            });

            router.reload({
                only: ['ordersHistory', 'cashierData', 'initialOrders'],
                preserveScroll: true,
                preserveState: true
            });
        }

        if (failCount > 0) {
            toast.add({
                severity: 'warn',
                summary: `${failCount} Falha(s) na SEFAZ`,
                detail: errorsList.slice(0, 2).join(' | '),
                life: 6000
            });
        }
    };

    return {
        isRetryingFiscal,
        pendingFiscalOrders,
        retryFailedFiscal
    };
}
