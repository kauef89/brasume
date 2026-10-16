<template>
    <div class="flex h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans">
        <div class="flex-1 flex flex-col md:flex-row w-full h-full overflow-hidden">

            <aside class="w-full md:w-80 shrink-0 p-6 md:p-8 flex flex-col gap-4 border-b md:border-b-0 md:border-r border-surface-border/50 bg-surface-1 z-10">
                <header class="mb-2 hidden md:block">
                    <h1 class="text-3xl font-black text-surface-text leading-tight">Configurações</h1>
                    <p class="text-surface-muted text-sm mt-1">Este dispositivo (Local)</p>
                </header>

                <nav class="flex flex-row md:flex-col gap-2 overflow-x-auto md:overflow-visible hide-scrollbar pb-2 md:pb-0 mt-2">
                    <button @click="activeTab = 'appearance'" class="px-4 py-3.5 rounded-2xl flex items-center gap-3 text-sm font-medium transition-all outline-none text-left whitespace-nowrap md:whitespace-normal cursor-pointer" :class="activeTab === 'appearance' ? 'bg-primary-500/10 text-primary-smart' : 'text-surface-muted hover:bg-surface-2 hover:text-surface-text'">
                        <i class="pi pi-desktop"></i> Aparência e Layout
                    </button>
                    <button @click="activeTab = 'sorting'" class="px-4 py-3.5 rounded-2xl flex items-center gap-3 text-sm font-medium transition-all outline-none text-left whitespace-nowrap md:whitespace-normal cursor-pointer" :class="activeTab === 'sorting' ? 'bg-primary-500/10 text-primary-smart' : 'text-surface-muted hover:bg-surface-2 hover:text-surface-text'">
                        <i class="pi pi-sort-alt"></i> Ordenação do Cardápio
                    </button>
                    <button @click="activeTab = 'whatsapp'" class="px-4 py-3.5 rounded-2xl flex items-center gap-3 text-sm font-medium transition-all outline-none text-left whitespace-nowrap md:whitespace-normal cursor-pointer" :class="activeTab === 'whatsapp' ? 'bg-success-500/10 text-success-smart' : 'text-surface-muted hover:bg-surface-2 hover:text-surface-text'">
                        <i class="fa-brands fa-whatsapp"></i> Estimativas de Entrega
                    </button>
                </nav>
            </aside>

            <main class="flex-1 p-6 md:p-8 lg:p-10 bg-surface-1 md:bg-transparent overflow-y-auto hide-scrollbar">
                <div class="bg-surface-2 rounded-[2rem] p-6 md:p-8 lg:p-10 flex flex-col gap-6 shadow-sm min-h-full">

                    <AppearanceSettings v-if="activeTab === 'appearance'" />

                    <SortingSettings v-else-if="activeTab === 'sorting'" :categories="categories" />

                    <EstimateSettings v-else-if="activeTab === 'whatsapp'" />

                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import AppLayout from "@/Layouts/DesktopLayout.vue";
import AppearanceSettings from "./Components/AppearanceSettings.vue";
import SortingSettings from "./Components/SortingSettings.vue";
import EstimateSettings from "./Components/EstimateSettings.vue";

defineOptions({ layout: AppLayout });

defineProps({
    categories: { type: Array, default: () => [] }
});

const activeTab = ref('appearance');
</script>
