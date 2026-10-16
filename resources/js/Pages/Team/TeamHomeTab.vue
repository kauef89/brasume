<template>
    <div class="h-full w-full relative overflow-hidden flex flex-col">

        <!-- 🖼️ CAMADA FIXA DO FUNDO (NÃO ROLA E NÃO DISTORCE) -->
        <div
            class="absolute inset-0 z-0 bg-cover bg-center bg-no-repeat transition-all duration-1000 pointer-events-none"
            :style="backgroundStyle"
        >
            <!-- Película escura sutil para contraste -->
            <div class="absolute inset-0 bg-black/35 pointer-events-none"></div>
        </div>

        <!-- 📜 CAMADA DE CONTEÚDO ROLÁVEL -->
        <div class="relative z-10 flex-1 h-full w-full overflow-y-auto pt-24 pb-32 hide-scrollbar">
            <div class="max-w-2xl w-full mx-auto flex flex-col gap-6 p-4 md:p-6">

                <!-- BANNER DE NOTIFICAÇÃO -->
                <PushNotificationBanner />

                <!-- CHECKLISTS EM GLASSMORPHISM -->
                <div class="bg-surface-1/60 dark:bg-surface-1/40 backdrop-blur-xl border border-surface-border/50 rounded-[2.5rem] shadow-xl overflow-hidden">
                    <TeamChecklists />
                </div>

            </div>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import TeamChecklists from '@/Pages/Team/TeamChecklists.vue';
import PushNotificationBanner from '@/Components/UI/PushNotificationBanner.vue';
import { useDynamicBackground } from '@/Composables/useDynamicBackground';

const page = usePage();
const { backgroundStyle } = useDynamicBackground();

const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] || 'Equipe');
</script>
