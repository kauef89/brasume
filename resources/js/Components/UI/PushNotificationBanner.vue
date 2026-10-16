<template>
    <div v-if="permissionStatus === 'default'" class="mx-4 mb-6 animate-in fade-in slide-in-from-top-4">
        <div class="bg-gradient-to-r from-primary-500 to-indigo-600 rounded-2xl p-4 shadow-lg text-white flex flex-col gap-3 relative overflow-hidden">
            <!-- Efeito de brilho no fundo -->
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/20 rounded-full blur-2xl pointer-events-none"></div>

            <div class="flex items-start gap-3 relative z-10">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                    <i class="pi pi-bell text-xl animate-wiggle"></i>
                </div>
                <div class="flex flex-col">
                    <h3 class="font-black text-base leading-tight">Não perca o seu lanche!</h3>
                    <p class="text-xs font-medium opacity-90 mt-1">Ative as notificações para saber exatamente quando o seu pedido saiu para entrega.</p>
                </div>
            </div>

            <button
                @click="enableNotifications"
                class="w-full py-2.5 bg-white text-primary-600 rounded-xl font-black text-xs uppercase tracking-widest shadow-md hover:scale-[1.02] transition-transform flex items-center justify-center gap-2"
            >
                <i class="pi pi-check-circle"></i> Ligar Notificações
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePushNotifications } from '@/Composables/usePushNotifications';

const page = usePage();
const { requestPushPermission, subscribeToWebPush } = usePushNotifications();

// Substitua a linha 30 por esta:
const permissionStatus = ref(
    typeof window !== 'undefined' && 'Notification' in window
    ? Notification.permission
    : 'granted'
);

onMounted(() => {
    if ("Notification" in window) {
        permissionStatus.value = Notification.permission;
    }
});

const enableNotifications = async () => {
    const granted = await requestPushPermission();
    if (granted) {
        permissionStatus.value = 'granted';
        // Usa a VAPID key que já está sendo injetada pelo seu HandleInertiaRequests
        await subscribeToWebPush(page.props.vapidPublicKey);
    } else {
        permissionStatus.value = 'denied';
    }
};
</script>

<style scoped>
@keyframes wiggle {
    0%, 100% { transform: rotate(-10deg); }
    50% { transform: rotate(10deg); }
}
.animate-wiggle { animation: wiggle 1s ease-in-out infinite; }
</style>
