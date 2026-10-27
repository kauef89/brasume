import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';

export function useCheckoutPix(order, handlePixApprovedCallback, toast) {
    const showPixPanel = ref(false);
    const page = usePage();
    
    const checkPendingPix = () => {
        if (!order.value) return;
        const hasPixIntent = order.value.payment_intent_status === 'pending_pix';
        const hasTxidAndPayload = Boolean(order.value.txid && order.value.pix_payload);
        const isPendingOrPartial = ['pending', 'pending_payment', 'partial'].includes(order.value.payment_status);

        if ((hasPixIntent || (hasTxidAndPayload && isPendingOrPartial)) && order.value.payment_intent_status !== 'manual') {
            showPixPanel.value = true;
        }
    };

    const onPixApprovedByWebhook = () => {
        showPixPanel.value = false;
        toast.add({ severity: 'success', summary: 'PIX Confirmado!', detail: 'A transferência foi reconhecida pelo banco.', life: 5000 });
        if (typeof handlePixApprovedCallback === 'function') handlePixApprovedCallback();
        router.reload({ only: ['order', 'initialOrders'], preserveState: true, preserveScroll: true });
    };

    const handleWebSocketsPixEvent = (e) => {
        if (String(e.detail?.orderId) === String(order.value?.id)) onPixApprovedByWebhook();
    };

    onMounted(() => {
        window.addEventListener('pos-pix-approved', handleWebSocketsPixEvent);
        checkPendingPix();
    });

    onBeforeUnmount(() => window.removeEventListener('pos-pix-approved', handleWebSocketsPixEvent));

    const cancelPixPayment = async (isAutoExpire = false) => {
        const orderId = order.value?.id;
        if (!orderId) return;

        showPixPanel.value = false; // Fecha a tela de imediato por UX
        
        try {
            // 🚀 Cancelamento silencioso usando axios
            await (window.axios || axios).post(route('operations.orders.cancel-intent', orderId));
        } catch (e) {
            console.warn('PIX já cancelado ou erro de rede', e);
        }

        // Limpa referências reativas cruzadas
        if (order.value) {
            order.value.txid = null;
            order.value.pix_payload = null;
            order.value.pix_expires_at = null;
            order.value.payment_intent_status = null;
        }

        const fromList = (page.props.initialOrders || []).find(o => o.id === orderId);
        if (fromList) {
            fromList.txid = null;
            fromList.pix_payload = null;
            fromList.pix_expires_at = null;
            fromList.payment_intent_status = null;
        }

        if (isAutoExpire) {
            toast.add({ severity: 'error', summary: 'PIX Expirado', detail: 'O tempo limite esgotou-se. A cobrança foi descartada.', life: 5000 });
        } else {
            toast.add({ severity: 'warn', summary: 'PIX Cancelado', detail: 'A cobrança digital foi descartada.', life: 3000 });
        }
    };

    const refreshPixPayment = async () => {
        const orderId = order.value?.id;
        if (!orderId) return;
        try {
            toast.add({ severity: 'info', summary: 'Atualizando PIX', detail: 'Gerando nova cobrança...', life: 3000 });
            const apiClient = axios || window.axios;
            const response = await apiClient.post(route('operations.orders.generate-pix', orderId));
            if (response.data?.success) {
                // Atualiza reatividade
                const applyData = (o) => {
                    o.txid = response.data.txid;
                    o.pix_payload = response.data.qr_code;
                    o.pix_expires_at = response.data.expires_at;
                    o.payment_intent_status = 'pending_pix';
                };
                if (order.value) applyData(order.value);
                const fromList = (page.props.initialOrders || []).find(o => o.id === orderId);
                if (fromList) applyData(fromList);
                
                showPixPanel.value = true;
                toast.add({ severity: 'success', summary: 'Renovado', detail: 'Novo código PIX disponível.', life: 3000 });
            }
        } catch (e) {
            toast.add({ severity: 'error', summary: 'Erro', detail: e.response?.data?.message || 'Falha ao renovar PIX.', life: 4000 });
        }
    };

    return { showPixPanel, cancelPixPayment, refreshPixPayment, onPixApprovedByWebhook };
}
