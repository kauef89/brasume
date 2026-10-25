import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

export function useCheckoutPix(order, handlePixApprovedCallback, toast) {
    // Controla se a view do QR Code está visível no painel da direita
    const showPixPanel = ref(false);
    
    // Abre automaticamente se detectar intenção pendente ao carregar o pedido
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

        if (typeof handlePixApprovedCallback === 'function') {
            handlePixApprovedCallback();
        }

        router.reload({ only: ['order', 'initialOrders'], preserveState: true, preserveScroll: true });
    };

    const handleWebSocketsPixEvent = (e) => {
        if (String(e.detail?.orderId) === String(order.value?.id)) {
            onPixApprovedByWebhook();
        }
    };

    onMounted(() => {
        window.addEventListener('pos-pix-approved', handleWebSocketsPixEvent);
        checkPendingPix();
    });

    onBeforeUnmount(() => {
        window.removeEventListener('pos-pix-approved', handleWebSocketsPixEvent);
    });

    const cancelPixPayment = (isAutoExpire = false) => {
        const orderId = order.value?.id;
        if (!orderId) return;

        router.post(route('operations.orders.cancel-intent', orderId), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showPixPanel.value = false;
                if (order.value) {
                    order.value.txid = null;
                    order.value.pix_payload = null;
                    order.value.pix_expires_at = null;
                    order.value.payment_intent_status = null;
                }

                if (isAutoExpire) {
                    toast.add({ severity: 'error', summary: 'PIX Expirado', detail: 'O tempo limite esgotou-se. A cobrança foi descartada.', life: 6000 });
                } else {
                    toast.add({ severity: 'warn', summary: 'PIX Cancelado', detail: 'A cobrança digital foi descartada.', life: 4000 });
                }
            }
        });
    };

    const refreshPixPayment = async () => {
        const orderId = order.value?.id;
        if (!orderId) return;

        try {
            toast.add({ severity: 'info', summary: 'Atualizando PIX', detail: 'Gerando nova cobrança...', life: 3000 });
            const apiClient = axios || window.axios;

            await apiClient.post(route('operations.orders.cancel-intent', orderId));
            const response = await apiClient.post(route('operations.orders.generate-pix', orderId));

            if (response.data?.success && order.value) {
                order.value.txid = response.data.txid;
                order.value.pix_payload = response.data.qr_code;
                order.value.pix_expires_at = response.data.expires_at;
                order.value.payment_intent_status = 'pending_pix';
                showPixPanel.value = true;
                toast.add({ severity: 'success', summary: 'QR Code Renovado', detail: 'Novo código PIX disponível.', life: 3000 });
            }
        } catch (e) {
            toast.add({ severity: 'error', summary: 'Erro', detail: e.response?.data?.message || 'Falha ao renovar PIX.', life: 4000 });
        }
    };

    return {
        showPixPanel,
        cancelPixPayment,
        refreshPixPayment,
        onPixApprovedByWebhook
    };
}
