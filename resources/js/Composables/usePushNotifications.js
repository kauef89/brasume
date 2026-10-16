import axios from 'axios';
import { ref } from 'vue';

export function usePushNotifications() {
    // 🚀 SONDA VISUAL: Estado reativo para exibir na tela
    const pushStatus = ref('⏳ Checando...');

    const checkSubscriptionStatus = async () => {
        if (!("Notification" in window) || !("serviceWorker" in navigator)) {
            pushStatus.value = '❌ Não Suportado';
            return;
        }

        if (Notification.permission === 'denied') {
            pushStatus.value = '🚫 Bloqueado';
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                pushStatus.value = '✅ Inscrito (Ativo)';
                console.log("📍 [SONDA PUSH FRONT] Dispositivo já possui inscrição:", subscription.toJSON());
            } else {
                pushStatus.value = '⚠️ Não Inscrito';
                console.log("📍 [SONDA PUSH FRONT] Dispositivo suporta, mas não está inscrito no PushManager.");
            }
        } catch (error) {
            pushStatus.value = '❌ Erro SW';
            console.error("📍 [SONDA PUSH FRONT] Erro ao checar inscrição:", error);
        }
    };

    const requestPushPermission = async () => {
        if (!("Notification" in window) || !("serviceWorker" in navigator)) return false;
        if (Notification.permission === "granted") return true;

        if (Notification.permission !== "denied") {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                pushStatus.value = '⚠️ Permissão Concedida (Sem Inscrição)';
            } else {
                pushStatus.value = '🚫 Bloqueado';
            }
            return permission === "granted";
        }
        return false;
    };

    const subscribeToWebPush = async (vapidPublicKey) => {
        pushStatus.value = '⏳ Inscrevendo...';
        console.log("📍 [SONDA PUSH FRONT] Iniciando inscrição com a chave VAPID:", vapidPublicKey);

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
            });

            console.log("📍 [SONDA PUSH FRONT] Inscrição gerada no navegador. Enviando para o Laravel...", subscription.toJSON());

            await axios.post(route('api.push.subscribe'), subscription.toJSON());

            pushStatus.value = '✅ Inscrito (Ativo)';
            console.log("📍 [SONDA PUSH FRONT] Laravel confirmou o recebimento da inscrição!");

        } catch (error) {
            pushStatus.value = '❌ Falha na Inscrição';
            console.error("📍 [SONDA PUSH FRONT] Erro crítico ao inscrever Push:", error);
        }
    };

    const urlBase64ToUint8Array = (base64String) => {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) { outputArray[i] = rawData.charCodeAt(i); }
        return outputArray;
    };

    const sendPushNotification = async (title, options = {}) => {
        // Lógica mantida...
        if (!("Notification" in window) || Notification.permission !== "granted") return { success: false };
        try {
            if ('serviceWorker' in navigator) {
                const registration = await navigator.serviceWorker.ready;
                await registration.showNotification(title, { icon: '/favicon.ico', requireInteraction: true, ...options });
                return { success: true };
            }
        } catch (error) { return { success: false }; }
    };

    return { pushStatus, checkSubscriptionStatus, requestPushPermission, subscribeToWebPush, sendPushNotification };
}
