<template>
    <div class="p-4 md:p-8 flex flex-col h-full w-full bg-surface-1 overflow-y-auto hide-scrollbar pb-32">
        <div class="max-w-2xl w-full mx-auto flex flex-col gap-6">

            <!-- Cabeçalho de Boas-Vindas -->
            <div class="bg-primary-600 dark:bg-primary-800 p-6 rounded-[2rem] text-white shadow-md flex flex-col gap-2 relative overflow-hidden">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                <h1 class="text-2xl font-black tracking-tight relative z-10">Bom turno, {{ firstName }}!</h1>
                <p class="text-primary-100 text-sm relative z-10">Estas são as suas rotinas para hoje.</p>
            </div>

            <!-- Componente Desacoplado das Rotinas -->
            <TeamChecklists />

        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import TeamChecklists from '@/Pages/Team/TeamChecklists.vue';
import DesktopLayout from "@/Layouts/DesktopLayout.vue";

defineOptions({ layout: DesktopLayout });

const page = usePage();
const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] || 'Equipe');
</script>
