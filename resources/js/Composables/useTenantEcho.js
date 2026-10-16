import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount } from 'vue';

export function useTenantEcho() {
    const page = usePage();
    const tenantId = page.props.tenantId || page.props.tenant?.id || window.Laravel?.tenant_id || 'central';

    // Guarda as escutas ativas para limpar no final
    const listeners = [];

    const listenChannel = (channelSuffix, eventName, callback) => {
        if (tenantId === 'central' || typeof window.Echo === 'undefined') return;

        const fullChannelName = `tenant.${tenantId}.${channelSuffix}`;

        // Proteção: Evita duplicar a antena se o listenChannel for chamado num Watcher
        const alreadyListening = listeners.find(
            l => l.fullChannelName === fullChannelName && l.eventName === eventName
        );

        if (!alreadyListening) {
            // Assina o canal imediatamente
            window.Echo.channel(fullChannelName).listen(eventName, callback);
            // Guarda a referência
            listeners.push({ fullChannelName, eventName, callback });
        }
    };

    // 🚀 A MÁGICA: Registrado de forma SÍNCRONA logo quando o composable é chamado!
    // O Vue nunca vai perder o contexto deste gancho, mesmo com awaits externos.
    onBeforeUnmount(() => {
        if (typeof window.Echo === 'undefined') return;

        listeners.forEach(({ fullChannelName, eventName, callback }) => {
            window.Echo.channel(fullChannelName).stopListening(eventName, callback);
        });
    });

    return { tenantId, listenChannel };
}
