<template>
    <!-- Estilo Glass: Fundo semi-transparente com desfoque forte -->
    <div class="p-6 m-4 backdrop-blur-sm bg-black/30 saturate-150 rounded-4xl flex flex-col gap-6 pb-safe">
        <div class="flex flex-row justify-between items-center gap-4">
            <!-- 0. LOGO DA LANCHONETE -->
            <div class="flex flex-col items-center justify-center mt-2 pointer-events-none">
                <img
                    v-if="page.props.companySettings?.logo_svg_path || page.props.companySettings?.logo_path"
                    :src="`/media/file?path=${page.props.companySettings.logo_svg_path || page.props.companySettings?.logo_path}`"
                    alt="Logo da Loja"
                    class="max-w-full object-contain brightness-0 invert"
                />
                <h1 v-else class="text-3xl md:text-4xl font-black text-surface-text drop-shadow-md uppercase tracking-widest text-center">
                    {{ page.props.companySettings?.trade_name || page.props.companySettings?.company_name || 'Lanchonete' }}
                </h1>
            </div>

            <!-- 1. STATUS DA LOJA -->
            <div class="flex items-center p-2 rounded-3xl bg-white/5">
                <div class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center text-white transition-colors duration-300" :class="statusColors.bg">
                    <i :class="['text-xl font-bold pi', statusIcon]"></i>
                </div>
                <div class="flex flex-col px-2 items-start justify-center flex-1 min-w-0">
                    <h3 class="text-sm font-black text-surface-text leading-tight truncate w-full">{{ titleText }}</h3>
                    <p class="text-[11px] font-medium text-surface-text/80 leading-tight truncate w-full mt-0.5">{{ storeAvailabilityText }}</p>
                </div>
            </div>
        </div>

        <!-- 2. BOTÃO ÚNICO DE AÇÃO + TERMOS -->
        <div class="flex flex-col items-center gap-2.5 w-full">
            <button
                @click="handleMenuClick"
                :disabled="isLoading"
                class="w-full h-14 rounded-full bg-white/70 text-black/70 font-black text-base uppercase tracking-widest transition-all border-none outline-none flex items-center justify-center gap-3 cursor-pointer hover:bg-primary-600 hover:text-white active:scale-[0.99]"
                :class="isLoading ? 'cursor-not-allowed opacity-90' : ''"
            >
                <i v-if="isLoading" class="pi pi-spinner pi-spin text-xl"></i>
                <span>{{ isLoading ? 'Carregando...' : 'Ver Cardápio →' }}</span>
            </button>

            <!-- AVISO CONTEXTUAL DE CONSENTIMENTO E PRIVACIDADE -->
            <p class="text-[10px] text-white/50 text-center font-medium leading-tight px-2">
                Ao entrar no cardápio, você concorda com nossos
                <a :href="route('customer.legal')" class="underline underline-offset-2 text-white/70 hover:text-white transition-colors">
                    Termos de Uso e Privacidade
                </a>
                e o uso de cookies.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useStoreStatus } from '@/Composables/useStoreStatus';

const emit = defineEmits(['menu']);

const page = usePage();
const {
    isOpen,
    isPreOrder,
    isClosedIndefinitely,
    isOpeningSoon,
    isPastClosingTime,
    storeAvailabilityText,
    statusTitle
} = useStoreStatus();

const isLoading = ref(false);

const handleMenuClick = () => {
    isLoading.value = true;

    try {
        localStorage.setItem('cookie_consent_granted', 'true');
    } catch (e) {}

    if (typeof window !== 'undefined' && typeof window.gtag === 'function') {
        window.gtag('consent', 'update', {
            'ad_storage': 'granted',
            'ad_user_data': 'granted',
            'ad_personalization': 'granted',
            'analytics_storage': 'granted'
        });
    }

    emit('menu');
};

const titleText = computed(() => {
    if (isClosedIndefinitely.value) return 'Voltamos em breve!';
    if (isOpen.value && isPastClosingTime.value) return 'Fechando em breve';
    if (isOpen.value) return 'Estamos abertos!';
    if (isOpeningSoon.value) return 'Quase abrindo!';
    if (isPreOrder.value) return 'Garanta o seu lugar!';
    return 'Fechado agora';
});

const statusIcon = computed(() => {
    if (isClosedIndefinitely.value) return 'pi-lock';
    if (isOpen.value && isPastClosingTime.value) return 'pi-hourglass';
    if (isOpen.value) return 'pi-check-circle';
    if (isOpeningSoon.value || isPreOrder.value) return 'pi-clock';
    return 'pi-moon';
});

const statusColors = computed(() => {
    if (isClosedIndefinitely.value) return { bg: 'bg-danger-500' };
    if (isOpen.value && isPastClosingTime.value) return { bg: 'bg-amber-500' };
    if (isOpen.value) return { bg: 'bg-success-500' };
    if (isOpeningSoon.value || isPreOrder.value) return { bg: 'bg-warning-500' };
    return { bg: 'bg-surface-400' };
});
</script>
