<template>
    <div class="w-full h-[100dvh] relative flex flex-col select-none animate-in fade-in duration-500 bg-surface-1 bg-cover bg-center overflow-hidden font-sans transition-all duration-1000"
         :style="backgroundStyle">

        <div class="relative z-10 flex flex-col w-full h-full">

            <!-- GLOBAL TOPBAR -->
            <CustomerHeader
                :is-team-mode="false"
                active-tab="home"
                @open-hours="showHoursModal = true"
                @open-location="showLocationModal = true"
            />

            <div class="flex-1"></div>

            <!-- RODAPÉ -->
            <div class="w-full max-w-2xl mx-auto absolute bottom-0 left-0 right-0 z-20">
                <HomeStatusBlock
                    @menu="handleMenuAction"
                />
            </div>
        </div>

        <!-- BOTTOM SHEETS -->
        <LocationSheet v-model:visible="showLocationModal" />
        <HoursSheet v-model:visible="showHoursModal" />

        <!-- 🚀 ALERTA DO NAVEGADOR INTERNO DO INSTAGRAM COM GATINHO -->
        <InAppBrowserWarningModal />

    </div>
</template>

<script setup>
import { ref, onBeforeMount } from 'vue';
import { router } from '@inertiajs/vue3';
import CustomerHeader from "@/Pages/Customer/CustomerHeader.vue";
import HomeStatusBlock from "@/Pages/Customer/Banners/HomeStatusBlock.vue";
import LocationSheet from "@/Pages/Splash/Components/LocationSheet.vue";
import HoursSheet from "@/Pages/Splash/Components/HoursSheet.vue";
import InAppBrowserWarningModal from "@/Components/UI/InAppBrowserWarningModal.vue";
import { useDynamicBackground } from '@/Composables/useDynamicBackground';
import { useAnalytics } from '@/Composables/useAnalytics';

defineOptions({ layout: null });

const showHoursModal = ref(false);
const showLocationModal = ref(false);

const { grantConsent } = useAnalytics();
const { backgroundStyle } = useDynamicBackground();

onBeforeMount(() => {
    document.documentElement.classList.add('dark');
    document.documentElement.classList.remove('light');
    localStorage.theme = 'dark';
});

const handleMenuAction = () => {
    grantConsent();
    router.visit(route('customer.app', { tab: 'menu' }));
};
</script>
