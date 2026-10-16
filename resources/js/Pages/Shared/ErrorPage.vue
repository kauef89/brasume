<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-surface-1 text-surface-text p-6 text-center">
        <div class="max-w-md w-full bg-surface-2 p-8 rounded-3xl shadow-sm border border-surface-border flex flex-col items-center">

            <i :class="errorData.icon" class="text-6xl mb-4" :style="{ color: errorData.color }"></i>

            <h1 class="text-4xl font-black mb-2">{{ status }}</h1>
            <h2 class="text-xl font-bold mb-4">{{ errorData.title }}</h2>

            <p class="text-surface-muted mb-8 font-medium">
                {{ errorData.description }}
            </p>

            <div class="flex flex-col sm:flex-row items-center gap-3 w-full justify-center">
                <!-- Botão Secundário: Tenta voltar à tela anterior dentro do histórico -->
                <button
                    v-if="canGoBack"
                    @click="goPrevious"
                    type="button"
                    class="w-full sm:w-auto h-12 px-6 rounded-full font-black text-xs uppercase tracking-widest bg-surface-1 border border-surface-border text-surface-text hover:bg-surface-3 transition-colors outline-none cursor-pointer"
                >
                    Voltar
                </button>

                <!-- Botão Primário: Retorna à raiz do PWA contextual (Caixa, Garçom, KDS ou Cardápio) -->
                <button
                    @click="goToContextHome"
                    type="button"
                    class="w-full sm:w-auto h-12 px-6 rounded-full font-black text-xs uppercase tracking-widest bg-primary-500 text-white hover:bg-primary-600 transition-colors shadow-md outline-none border-none cursor-pointer flex items-center justify-center gap-2"
                >
                    <i class="pi pi-home text-xs"></i>
                    <span>Ir para {{ app_label }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
    home_url: {
        type: String,
        default: '/',
    },
    app_label: {
        type: String,
        default: 'Início',
    },
    return_url: {
        type: String,
        default: null,
    },
});

const errorData = computed(() => {
    const map = {
        503: { title: 'Serviço Indisponível', description: 'Estamos em manutenção programada. Volte em alguns minutinhos!', icon: 'pi pi-cog', color: '#f59e0b' },
        500: { title: 'Erro Interno', description: 'Ops, algo deu errado nos nossos servidores. Nossa equipe já foi notificada.', icon: 'pi pi-server', color: '#ef4444' },
        404: { title: 'Página Não Encontrada', description: 'O endereço digitado não existe ou foi movido dentro do sistema.', icon: 'pi pi-map', color: '#8b5cf6' },
        403: { title: 'Acesso Negado', description: 'Você não possui permissão para acessar esta seção com o perfil atual.', icon: 'pi pi-lock', color: '#ef4444' },
    };

    return map[props.status] || {
        title: 'Ops! Algo deu errado',
        description: 'Encontramos um comportamento inesperado.',
        icon: 'pi pi-exclamation-triangle',
        color: '#6b7280',
    };
});

const canGoBack = computed(() => {
    return typeof window !== 'undefined' && window.history.length > 1;
});

const goPrevious = () => {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
    } else if (props.return_url) {
        router.visit(props.return_url);
    } else {
        router.visit(props.home_url);
    }
};

const goToContextHome = () => {
    router.visit(props.home_url);
};
</script>
