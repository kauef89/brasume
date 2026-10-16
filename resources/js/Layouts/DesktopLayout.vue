<template>
    <div class="w-full h-screen bg-surface-1 text-surface-text overflow-hidden flex flex-col">
        <Toast position="top-right" />
        <slot />
    </div>
</template>

<script setup>
import { onMounted } from "vue";
import { router } from "@inertiajs/vue3";
import Toast from "primevue/toast";
import { usePushNotifications } from "@/Composables/usePushNotifications";
import { useAudioAlert } from "@/Composables/useAudioAlert";
import { useTenantEcho } from "@/Composables/useTenantEcho";

const { sendPushNotification } = usePushNotifications();
const { playBeep } = useAudioAlert();
const { listenChannel } = useTenantEcho();

onMounted(() => {
    listenChannel('orders', '.order.created', (payload) => {
        const source = payload?.source || payload?.order?.source;
        const orderId = payload?.orderId || payload?.order?.id;

        if (source === 'customer_app') {
            playBeep('cashier');
            sendPushNotification('🍔 Novo Pedido Online!', {
                body: `Pedido #${orderId || ''} recebido pelo Cardápio do Cliente.`,
                tag: `novo_pedido_${orderId}`
            });
        }
    });

    listenChannel('system', '.settings.updated', () => {
        router.reload({
            only: ['companySettings', 'company_settings', 'settings'],
            preserveState: true,
            preserveScroll: true
        });
    });
});
</script>
