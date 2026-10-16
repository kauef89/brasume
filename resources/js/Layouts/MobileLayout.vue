<template>
    <div class="w-full h-screen h-dvh bg-surface-1 text-surface-text flex flex-col relative overflow-hidden">
        <!-- O slot preenche 100% da viewport dinâmica real do celular -->
        <slot />
    </div>
</template>

<script setup>
import { onMounted } from "vue";
import { router } from "@inertiajs/vue3";
import { usePushNotifications } from "@/Composables/usePushNotifications";
import { useAudioAlert } from "@/Composables/useAudioAlert";
import { useTenantEcho } from "@/Composables/useTenantEcho";

const { sendPushNotification } = usePushNotifications();
const { playBeep } = useAudioAlert();
const { listenChannel } = useTenantEcho();

onMounted(() => {
    const isCustomerApp = window.location.pathname.startsWith('/cardapio')
        || window.location.pathname.startsWith('/customer');

    if (isCustomerApp) {
        return;
    }

    listenChannel('kds', '.items.created', () => {
        playBeep('kds');
        sendPushNotification('🔥 Novos itens na Cozinha!', {
            body: 'A cozinha tem novos pratos para preparar.',
            tag: 'kds_novo'
        });
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
