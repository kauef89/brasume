<template>
    <div
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[9000] bg-white/90 dark:bg-gray-900/90 backdrop-blur-md shadow-[0_10px_40px_-10px_rgba(0,0,0,0.3)] border border-gray-200 dark:border-gray-700 rounded-full px-3 py-2 flex items-center gap-2"
    >
        <button
            @click="navigate('/pos')"
            class="flex items-center justify-center w-12 h-12 rounded-full transition-all duration-300"
            :class="
                isCurrent('/pos')
                    ? 'bg-blue-600 text-white shadow-md scale-105'
                    : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
            "
            title="Novo Pedido (Caixa)"
        >
            <i class="pi pi-desktop text-xl"></i>
        </button>

        <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-1"></div>

        <button
            @click="navigate('/operations')"
            class="flex items-center justify-center w-12 h-12 rounded-full transition-all duration-300"
            :class="
                isCurrent('/operations') && !isCurrent('/operations/cashier')
                    ? 'bg-purple-600 text-white shadow-md scale-105'
                    : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
            "
            title="Gestão de Pedidos"
        >
            <i class="pi pi-sliders-h text-xl"></i>
        </button>

        <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-1"></div>

        <button
            @click="navigate('/operations/cashier')"
            class="flex items-center justify-center w-12 h-12 rounded-full transition-all duration-300"
            :class="
                isCurrent('/operations/cashier')
                    ? 'bg-green-600 text-white shadow-md scale-105'
                    : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
            "
            title="Fluxo de Caixa (Turnos)"
        >
            <i class="pi pi-wallet text-xl"></i>
        </button>

        <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-1"></div>

        <a
            href="/admin"
            class="flex items-center justify-center w-12 h-12 rounded-full transition-all duration-300 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white"
            title="Retaguarda (ERP)"
        >
            <i class="pi pi-cog text-xl"></i>
        </a>
    </div>
</template>

<script setup>
import { router, usePage } from "@inertiajs/vue3";

const page = usePage();

// 🚀 Melhorei a lógica de detecção de URL ativa
const isCurrent = (url) => {
    if (url === '/operations') {
        return page.url === '/operations';
    }
    return page.url.startsWith(url);
};

const navigate = (url) => {
    if (page.url !== url) {
        router.visit(url);
    }
};
</script>
