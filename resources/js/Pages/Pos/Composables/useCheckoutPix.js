import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';

export function useCheckoutPix(order, handlePixApprovedCallback, toast) {
    // 🚀 FASE 1: Distinção Clara! O QR Code só fica a aguardar se for de facto uma API de banco!
    const isPixWaitingPhase = computed(() => {
        if (!order.value) return false;

        const isPending = order.value.payment_status === 'pending_payment';
        const hasTxid = Boolean(order.value.txid);
        const isNotManual = order.value.payment_intent_status !== 'manual';

        return isPending && hasTxid && isNotManual;
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
        // Envia um pedido de DELETE ou POST para limpar a promessa de pagamento do pedido
        router.post(route('operations.orders.cancel-intent', order.value.id), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (isAutoExpire) {
                    toast.add({ severity: 'error', summary: 'PIX Expirado', detail: 'O tempo limite de 10 minutos esgotou-se. O PIX foi cancelado.', life: 6000 });
                } else {
                    toast.add({ severity: 'warn', summary: 'PIX Cancelado', detail: 'A cobrança digital foi descartada.', life: 4000 });
                }
            },
            onError: () => {
                // Tenta forçar via cancelamento global se a intenção falhar
                router.post(route('operations.orders.cancel', order.value.id));
            }
        });
    };

    const refreshPixPayment = () => {
        toast.add({ severity: 'info', summary: 'Recarregando', detail: 'Gerando uma nova cobrança na instituição financeira...', life: 3000 });
        cancelPixPayment(false);
    };

    return {
        isPixWaitingPhase,
        cancelPixPayment,
        refreshPixPayment,
        onPixApprovedByWebhook
    };
}
