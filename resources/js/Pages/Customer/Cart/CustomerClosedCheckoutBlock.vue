<template>
    <div class="c-glass-card mt-4 mb-8 p-6 flex flex-col items-center text-center gap-3 shrink-0 animate-in fade-in slide-in-from-bottom-4 duration-300 select-none">
        <!-- Ícone Estilizado -->
        <div class="w-12 h-12 rounded-full bg-white/5 flex items-center justify-center text-white/70 mb-1">
            <i class="pi pi-moon text-lg"></i>
        </div>

        <!-- Textos de Status da Loja -->
        <div class="flex flex-col gap-1">
            <h3 class="c-text-main text-sm md:text-base font-black">{{ closedCheckoutTitle }}</h3>
            <p class="text-[11px] font-bold text-primary-400 uppercase tracking-widest">{{ storeAvailabilityText }}</p>
        </div>

        <div class="w-full h-px bg-white/10 my-1"></div>

        <!-- Cliente Logado: Toggle de Notificação -->
        <div v-if="customer && !isGuest" class="flex items-center justify-between w-full gap-3 text-left pt-1">
            <div class="flex flex-col flex-1 min-w-0">
                <span class="c-text-main text-xs font-black">Avisar quando abrir</span>
                <span class="c-text-muted text-[10px]">Mandaremos uma notificação para você voltar e finalizar.</span>
            </div>

            <button
                type="button"
                @click="toggleOpeningNotification"
                :disabled="isTogglingNotification"
                class="w-12 h-7 rounded-full relative transition-colors duration-200 shrink-0 outline-none border-none cursor-pointer"
                :class="isNotifyMeActive ? 'bg-primary-500' : 'bg-white/15'"
            >
                <div
                    class="w-5 h-5 rounded-full bg-white absolute top-1 flex items-center justify-center transition-transform duration-200 shadow-md"
                    :class="isNotifyMeActive ? 'translate-x-6' : 'translate-x-1'"
                >
                    <i v-if="isTogglingNotification" class="pi pi-spinner pi-spin text-primary-600 text-[10px]"></i>
                </div>
            </button>
        </div>

        <!-- Visitante / Anônimo: CTA de Cadastro -->
        <div v-else class="flex flex-col items-center w-full gap-3 pt-1">
            <p class="c-text-muted text-[11px] leading-snug px-2">
                Crie uma conta para ser notificado assim que a loja abrir e não perder seus itens.
            </p>
            <button
                type="button"
                @click="$emit('open-register')"
                class="c-btn-ghost w-full !h-12 !text-xs text-primary-300 !bg-primary-500/20 active:!bg-primary-500/30"
            >
                <i class="pi pi-bell text-xs"></i>
                <span>Me avise quando abrir</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { useStoreStatus } from '@/Composables/useStoreStatus';

defineProps({
    isGuest: { type: Boolean, default: false },
    customer: { type: Object, default: null }
});

defineEmits(['open-register']);

const {
    closedCheckoutTitle,
    storeAvailabilityText,
    isNotifyMeActive,
    isTogglingNotification,
    toggleOpeningNotification
} = useStoreStatus();
</script>
