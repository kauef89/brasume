<template>
    <div class="flex justify-between items-center bg-surface-1 p-4 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-800 shrink-0">
        <div class="flex items-center gap-3 w-1/2">
            <i class="pi pi-search text-stone-400"></i>
            <InputText
                :model-value="searchQuery"
                @update:model-value="$emit('update:searchQuery', $event)"
                placeholder="Pesquisar produto pelo nome ou código..."
                class="w-full p-inputtext-sm border-none bg-transparent focus:shadow-none"
            />
        </div>

        <div class="flex items-center gap-4 text-sm font-medium text-stone-500">

            <!-- 🚀 BOTÃO MINI DE NOTIFICAÇÃO (PISCANTE) -->
            <button
                v-if="permissionStatus === 'default'"
                @click="enableNotifications"
                class="w-8 h-8 rounded-full bg-danger-500/10 text-danger-500 flex items-center justify-center hover:bg-danger-500 hover:text-white transition-colors outline-none cursor-pointer relative"
                title="Ativar Alertas de Sistema"
            >
                <span class="absolute top-0 right-0 w-2 h-2 bg-danger-500 rounded-full animate-ping"></span>
                <span class="absolute top-0 right-0 w-2 h-2 bg-danger-500 rounded-full border border-surface-1"></span>
                <i class="pi pi-bell animate-wiggle"></i>
            </button>

            <div class="flex items-center gap-2 border-l border-surface-border pl-4">
                <i class="pi fa-user text-xs"></i>
                <span>{{ operatorName }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import InputText from "primevue/inputtext";
import { usePushNotifications } from '@/Composables/usePushNotifications';

defineProps({
    searchQuery: String,
    operatorName: String,
});

defineEmits(['update:searchQuery']);

const page = usePage();
const { requestPushPermission, subscribeToWebPush } = usePushNotifications();

// Lê o status real imediatamente
const permissionStatus = ref(
    typeof window !== 'undefined' && 'Notification' in window
    ? Notification.permission
    : 'granted'
);

const enableNotifications = async () => {
    const granted = await requestPushPermission();
    if (granted) {
        permissionStatus.value = 'granted';
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
