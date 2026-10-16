<template>
    <!-- 🚀 CABEÇALHO GLOBAL DA EQUIPE -->
    <header ref="headerEl" class="fixed top-0 inset-x-0 z-[60] flex flex-col shrink-0 pointer-events-none pb-6 pt-safe">

        <!-- Blur Progressivo -->
        <div class="absolute inset-0 backdrop-blur-xl [mask-image:linear-gradient(to_bottom,black_65%,transparent_100%)] -z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-surface-1/95 via-surface-1/70 to-transparent -z-10"></div>

        <div class="relative z-10 flex flex-col pointer-events-auto w-full">

            <!-- LINHA 1: Logo e Ações -->
            <div class="flex items-center justify-between w-full px-4 py-3">
                <div class="flex items-center gap-3">
                    <img v-if="$page.props.companySettings?.logo_svg_path || $page.props.companySettings?.logo_path"
                         :src="`/media/file?path=${$page.props.companySettings.logo_svg_path || $page.props.companySettings.logo_path}`"
                         alt="Logo" class="h-6 md:h-7 w-auto object-contain drop-shadow-sm dark:brightness-0 dark:invert" />
                    <span v-else class="text-[11px] font-black uppercase tracking-widest text-surface-text">
                        {{ $page.props.companySettings?.trade_name || 'Brasume' }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="$emit('open-notifications')" class="w-9 h-9 rounded-full text-surface-muted flex items-center justify-center hover:text-primary-500 hover:bg-surface-3 transition-all outline-none cursor-pointer shadow-sm relative active:scale-90">
                        <i class="pi pi-bell text-sm"></i>
                        <span class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full bg-danger-500 border-2 border-surface-1"></span>
                    </button>
                </div>
            </div>

            <!-- LINHA 2: Título Contextual + Busca Teleportada -->
            <div class="px-4 flex items-center justify-between gap-3 pb-2 min-h-[44px]">
                <div class="flex flex-col gap-1 min-w-0 flex-1">
                    <template v-if="activeProduct">
                        <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 leading-tight animate-in fade-in">
                            {{ activeProduct.category_name || activeCategory?.name || 'Montando Item' }}
                        </span>
                        <div class="flex items-center justify-between gap-4 animate-in fade-in">
                            <h1 class="text-xl font-black text-surface-text leading-none tracking-tight truncate">
                                {{ activeProduct.name }}
                            </h1>
                            <span class="font-black text-base text-surface-text whitespace-nowrap">
                                R$ {{ Number(currentTotal || activeProduct.price || activeProduct.base_price || 0).toFixed(2).replace('.', ',') }}
                            </span>
                        </div>
                    </template>

                    <template v-else-if="contextTitle">
                        <span v-if="contextSubtitle" class="text-[10px] font-black uppercase tracking-widest text-primary-500 leading-tight animate-in fade-in">
                            {{ contextSubtitle }}
                        </span>
                        <h1 class="text-2xl font-black text-surface-text leading-none tracking-tight truncate animate-in fade-in">
                            {{ contextTitle }}
                        </h1>
                    </template>

                    <template v-else>
                        <h1 class="text-2xl font-black text-surface-text leading-none tracking-tight animate-in fade-in truncate">
                            {{ pageTitle }}
                        </h1>
                    </template>
                </div>

                <!-- 🎯 PORTAL RECEPTOR PARA BUSCA E FILTROS -->
                <div v-show="!activeProduct" class="shrink-0 flex items-center justify-end z-10 gap-2">
                    <div id="team-header-action-portal" class="empty:hidden flex items-center gap-2"></div>
                </div>
            </div>
        </div>

        <div id="team-header-portal-target" class="w-full pointer-events-auto relative z-50 px-4 pb-2 empty:hidden"></div>
    </header>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useDynamicHeaderHeight } from '@/Composables/useDynamicHeaderHeight';

const props = defineProps({
    activeTab: String,
    activeCategory: Object,
    activeProduct: Object,
    currentTotal: { type: [Number, String, Function], default: 0 },
    contextTitle: { type: String, default: null },
    contextSubtitle: { type: String, default: null }
});

defineEmits(['open-notifications']);

const headerEl = ref(null);
useDynamicHeaderHeight(headerEl);

const tabTitles = {
    home: 'Início', operations: 'Comandas', restock: 'Reposição',
    inventory: 'Estoque',
    manager: 'Gestor', fleet: 'Terminais', settings: 'Ajustes',
    wallet: 'Sua Carteira', driver: 'Motoboy', checklists: 'Checklists',
    production: 'Produção', shopping: 'Lista de Compras', cart: 'Carrinho',
    bar: 'Copa & Bar'
};

const pageTitle = computed(() => {
    if (props.activeTab === 'menu') {
        return props.activeCategory?.name || props.activeCategory?.displayName || 'Cardápio';
    }
    return tabTitles[props.activeTab] || '';
});
</script>
