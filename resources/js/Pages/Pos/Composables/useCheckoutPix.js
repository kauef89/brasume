import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';

export function useCheckoutPix(order, handlePixApprovedCallback, toast) {
    // 🚀 FASE 1: Aguarda PIX se houver intenção ativa e o pedido ainda não estiver pago
    const isPixWaitingPhase = computed(() => {
        if (!order.value) return false;

        const hasPixIntent = order.value.payment_intent_status === 'pending_pix';
        const hasTxidAndPayload = Boolean(order.value.txid && order.value.pix_payload);
        const isPendingOrPartial = ['pending', 'pending_payment', 'partial'].includes(order.value.payment_status);

        return (hasPixIntent || (hasTxidAndPayload && isPendingOrPartial)) && order.value.payment_intent_status !== 'manual';
    });

    const onPixApprovedByWebhook = () => {
        if (!isPixWaitingPhase.value) return;

        toast.add({ severity: 'success', summary: 'PIX Confirmado!', detail: 'A transferência foi reconhecida pelo banco.', life: 5000 });

        if (typeof handlePixApprovedCallback === 'function') {
            handlePixApprovedCallback();
        }

        router.reload({ only: ['order', 'initialOrders'], preserveState: true, preserveScroll: true });
    };

    const handleWebSocketsPixEvent = (e) => {
        if (!isPixWaitingPhase.value) return;
        if (String(e.detail?.orderId) === String(order.value?.id)) {
            onPixApprovedByWebhook();
        }
    };

    onMounted(() => {
        window.addEventListener('pos-pix-approved', handleWebSocketsPixEvent);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('pos-pix-approved', handleWebSocketsPixEvent);
    });

    // 🚀 CANCELAMENTO MANUAL OU POR EXPIRAÇÃO (10 MIN)
    const cancelPixPayment = (isAutoExpire = false) => {
        const orderId = order.value?.id;
        if (!orderId) return;

        router.post(route('operations.orders.cancel-intent', orderId), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
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
            },
            onError: () => {
                toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao cancelar cobrança PIX.', life: 4000 });
            }
        });
    };

    // 🚀 GERA UM NOVO CÓDIGO IMEDIATAMENTE (RENOVAÇÃO)
    const refreshPixPayment = async () => {
        const orderId = order.value?.id;
        if (!orderId) return;

        try {
            toast.add({ severity: 'info', summary: 'Atualizando PIX', detail: 'Gerando nova cobrança na instituição financeira...', life: 3000 });
            const apiClient = axios || window.axios;

            await apiClient.post(route('operations.orders.cancel-intent', orderId));
            const response = await apiClient.post(route('operations.orders.generate-pix', orderId));

            if (response.data?.success && order.value) {
                order.value.txid = response.data.txid;
                order.value.pix_payload = response.data.qr_code;
                order.value.pix_expires_at = response.data.expires_at;
                order.value.payment_intent_status = 'pending_pix';

                toast.add({ severity: 'success', summary: 'QR Code Renovado', detail: 'Novo código PIX disponível.', life: 3000 });
            }
        } catch (e) {
            toast.add({ severity: 'error', summary: 'Erro', detail: e.response?.data?.message || 'Falha ao renovar PIX.', life: 4000 });
        }
    };

    return {
        isPixWaitingPhase,
        cancelPixPayment,
        refreshPixPayment,
        onPixApprovedByWebhook
    };
}
