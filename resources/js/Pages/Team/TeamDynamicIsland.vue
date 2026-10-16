<template>
    <nav
        ref="islandRef"
        :class="[baseIslandClass, shapeIslandClass]"
        :style="islandStyle"
    >
        <!-- Container isolado de layout -->
        <div ref="contentRef" class="w-max h-max flex items-center justify-center shrink-0 p-1.5">

            <!-- 1. MODO CONFIGURADOR -->
            <div
                v-if="configuratorType"
                id="island-portal-target"
                class="w-max flex flex-col justify-center shrink-0"
            ></div>

            <!-- 2. MODO CARRINHO DA EQUIPE (Apenas se houver itens) -->
            <div
                v-else-if="activeTab === 'cart' && cartLength > 0"
                class="flex items-center gap-2 shrink-0 px-1"
            >
                <button
                    @click.prevent="emit('update:activeTab', 'menu')"
                    class="c-island-btn shrink-0 !bg-surface-1 text-surface-text hover:bg-surface-2"
                    title="Voltar ao Cardápio"
                >
                    <i class="pi pi-arrow-left"></i>
                </button>

                <SlideToConfirm
                    class="w-56 sm:w-64 !h-11 transition-all"
                    :color="isAllowanceMode ? 'primary' : 'success'"
                    :text="isAllowanceMode ? 'Deslize p/ Debitar' : 'Deslize p/ Confirmar'"
                    :success-text="isAllowanceMode ? 'Debitado!' : 'Enviado!'"
                    :icon="isAllowanceMode ? 'pi-wallet' : 'pi-send'"
                    format="long"
                    :disabled="!cartRoutingValid || cartLength === 0"
                    :is-loading="isSubmitting"
                    :is-success="isSuccess"
                    @confirm="emit('submit-order')"
                />

                <button
                    @click.prevent="emit('clear-cart')"
                    class="c-island-btn shrink-0 !bg-danger-500/10 hover:!bg-danger-500 !text-danger-500 hover:!text-white transition-colors"
                    title="Esvaziar Carrinho"
                >
                    <i class="pi pi-trash"></i>
                </button>
            </div>

            <!-- 3. MODO CARDÁPIO EM VENDA ATIVA (Apenas se houver itens no carrinho) -->
            <div
                v-else-if="activeTab === 'menu' && isOrderFlow"
                class="flex items-center gap-1 shrink-0"
            >
                <button
                    v-if="isInCategoryView"
                    @click.prevent="emit('go-back-category')"
                    class="c-island-btn c-island-btn-inactive shrink-0"
                    title="Voltar Categoria"
                >
                    <i class="pi pi-arrow-left"></i>
                </button>

                <button
                    @click.prevent="handleTabClick('menu')"
                    class="c-island-btn c-island-btn-active"
                    title="Cardápio"
                >
                    <i class="pi pi-book"></i>
                </button>

                <!-- 🛒 Só renderiza se houver itens -->
                <button
                    v-if="cartLength > 0"
                    @click.prevent="handleTabClick('cart')"
                    class="c-island-btn c-island-btn-inactive relative"
                    title="Carrinho"
                >
                    <i class="pi pi-shopping-cart text-white"></i>
                    <span class="absolute top-1 right-1 flex h-[18px] min-w-[18px]">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-primary-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                            {{ cartLength }}
                        </span>
                    </span>
                </button>
            </div>

            <!-- 🚀 4. MODO REPOSIÇÃO / WMS -->
            <div
                v-else-if="activeTab === 'restock'"
                class="flex items-center gap-1.5 shrink-0 px-1"
            >
                <button
                    @click.prevent="handleRestockBack"
                    class="c-island-btn shrink-0 !bg-surface-1 text-surface-text hover:bg-surface-2"
                    title="Voltar"
                >
                    <i class="pi pi-arrow-left"></i>
                </button>

                <div class="w-px h-6 bg-surface-border/50 mx-0.5"></div>

                <button
                    v-if="isFreeMode"
                    @click.prevent="activeSubTab = 'clipboard'"
                    class="c-island-btn"
                    :class="activeSubTab === 'clipboard' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                    title="Prancheta: Anotar o que falta"
                >
                    <i class="pi pi-clipboard"></i>
                </button>

                <button
                    @click.prevent="activeSubTab = 'box'"
                    class="c-island-btn relative"
                    :class="activeSubTab === 'box' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                    title="Caixa: Itens para Coleta"
                >
                    <i class="pi pi-box"></i>
                    <span
                        v-if="totalBoxItemsCount > 0"
                        class="absolute top-1 right-1 flex h-[18px] min-w-[18px]"
                    >
                        <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-amber-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                            {{ totalBoxItemsCount }}
                        </span>
                    </span>
                </button>
            </div>

            <!-- 5. NAVEGAÇÃO DA EQUIPE -->
            <div
                v-else
                class="flex items-center gap-1 shrink-0"
            >
                <!-- 🛵 MODO MOTOBOY EXCLUSIVO -->
                <template v-if="resolvedIsDriver">
                    <button
                        @click.prevent="handleTabClick('home')"
                        class="c-island-btn"
                        :class="activeTab === 'home' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Início"
                    >
                        <i class="pi pi-home"></i>
                    </button>

                    <button
                        @click.prevent="handleTabClick('driver')"
                        class="c-island-btn"
                        :class="activeTab === 'driver' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Minhas Entregas"
                    >
                        <i class="pi pi-motorcycle"></i>
                    </button>

                    <button
                        @click.prevent="handleTabClick('checklists')"
                        class="c-island-btn"
                        :class="activeTab === 'checklists' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Minhas Tarefas"
                    >
                        <i class="pi pi-list-check"></i>
                    </button>
                </template>

                <!-- 🍽️ MODO ATENDIMENTO OPERACIONAL (GARÇOM / BALCÃO / SALÃO) -->
                <template v-else>
                    <button
                        v-if="orderContext"
                        @click.prevent="emit('close-context')"
                        class="c-island-btn c-island-btn-inactive shrink-0"
                        title="Fechar Comanda"
                    >
                        <i class="pi pi-arrow-left"></i>
                    </button>

                    <button
                        v-else-if="isInCategoryView"
                        @click.prevent="emit('go-back-category')"
                        class="c-island-btn c-island-btn-inactive shrink-0"
                        title="Voltar Categoria"
                    >
                        <i class="pi pi-arrow-left"></i>
                    </button>

                    <button
                        @click.prevent="handleTabClick('home')"
                        class="c-island-btn"
                        :class="activeTab === 'home' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Início"
                    >
                        <i class="pi pi-home"></i>
                    </button>

                    <button
                        @click.prevent="handleTabClick('operations')"
                        class="c-island-btn"
                        :class="activeTab === 'operations' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Mapa de Mesas / Salão"
                    >
                        <i class="pi pi-th-large"></i>
                    </button>

                    <button
                        v-if="isKdsBarEnabled"
                        @click.prevent="handleTabClick('bar')"
                        class="c-island-btn relative"
                        :class="activeTab === 'bar' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="KDS Bar / Copa"
                    >
                        <i class="pi pi-receipt"></i>
                        <span
                            v-if="barItemsCount > 0"
                            class="absolute top-1 right-1 flex h-[18px] min-w-[18px]"
                        >
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-orange-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                                {{ barItemsCount }}
                            </span>
                        </span>
                    </button>

                    <button
                        v-if="orderContext"
                        @click.prevent="emit('show-more-actions')"
                        class="c-island-btn c-island-btn-inactive !text-amber-500"
                        title="Ações da Mesa"
                    >
                        <i class="pi pi-plus"></i>
                    </button>

                    <button
                        v-else
                        @click.prevent="handleTabClick('menu')"
                        class="c-island-btn"
                        :class="activeTab === 'menu' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Cardápio"
                    >
                        <i class="pi pi-book"></i>
                    </button>

                    <!-- 🛒 CARRINHO: Estritamente visível apenas se houver itens -->
                    <button
                        v-if="cartLength > 0"
                        @click.prevent="handleTabClick('cart')"
                        class="c-island-btn c-island-btn-inactive relative"
                        title="Carrinho"
                    >
                        <i class="pi pi-shopping-cart text-white"></i>
                        <span class="absolute top-1 right-1 flex h-[18px] min-w-[18px]">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-primary-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                                {{ cartLength }}
                            </span>
                        </span>
                    </button>

                    <button
                        @click.prevent="emit('open-more')"
                        class="c-island-btn relative"
                        :class="isExtraTabActive ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Mais Módulos"
                    >
                        <i class="pi pi-cog"></i>
                        <span
                            v-if="restockCount > 0"
                            class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full bg-warning-500 ring-2 ring-surface-0 animate-pulse"
                        ></span>
                    </button>
                </template>
            </div>

        </div>
    </nav>
</template>

<script setup>
import { computed, watch } from 'vue';
import { usePage } from "@inertiajs/vue3";
import SlideToConfirm from "@/Components/UI/SlideToConfirm.vue";
import { useDynamicIsland } from "@/Pages/Shared/Composables/useDynamicIsland";
import { useRestock } from "@/Pages/Shared/Composables/useRestock";

const props = defineProps({
    activeTab: String,
    cartLength: { type: Number, default: 0 },
    selectedCategory: [String, Number],
    categoryLayout: String,
    breadcrumbs: { type: Array, default: () => [] },
    orderContext: { type: Object, default: null },
    cartRoutingValid: { type: Boolean, default: false },
    cartRoutingMeta: { type: Object, default: null },
    isEditMode: { type: Boolean, default: false },
    isSubmitting: { type: Boolean, default: false },
    isSuccess: { type: Boolean, default: false },
    restockCount: { type: Number, default: 0 },
    isAllowanceMode: { type: Boolean, default: false },
    barItemsCount: { type: Number, default: 0 },
    configuratorType: { type: String, default: null },
    isDriver: { type: Boolean, default: false }
});

const emit = defineEmits([
    'update:activeTab', 'go-back-category', 'reset-menu', 'order-action',
    'close-context', 'show-more-actions', 'toggle-routing', 'clear-cart',
    'submit-order', 'open-more', 'navigate'
]);

const page = usePage();

// 🚀 Avaliação do perfil: detecta se a visualização atual deve ser a visão limpa de entregas
const resolvedIsDriver = computed(() => {
    if (props.isDriver || page.props.isDriver) return true;

    const user = page.props.auth?.user;
    if (!user) return false;

    const role = (user.role || '').toLowerCase();
    const roles = Array.isArray(user.roles)
        ? user.roles.map(r => (typeof r === 'string' ? r : r.name || '').toLowerCase())
        : [];

    // Se o usuário for estritamente motorista (não acumula funções de gestão/garçom)
    const isExclusivelyDriver = (role === 'driver' || role === 'motoboy') &&
        !roles.some(r => ['admin', 'manager', 'gerente', 'waiter', 'garcom'].includes(r));

    return isExclusivelyDriver;
});

const {
    baseIslandClass,
    shapeIslandClass,
    islandStyle,
    islandRef,
    contentRef,
    handleTabClick
} = useDynamicIsland(props, emit);

// 🚀 Trava defensiva: se o carrinho for zerado enquanto estiver na aba cart, volta ao menu
watch(() => props.cartLength, (newLen) => {
    if (newLen === 0 && props.activeTab === 'cart') {
        emit('update:activeTab', 'menu');
    }
});

const { activeSubTab, isFreeMode, totalBoxItemsCount } = useRestock();

const handleRestockBack = () => {
    if (isFreeMode.value && activeSubTab.value === 'box') {
        activeSubTab.value = 'clipboard';
    } else {
        handleTabClick('home');
    }
};

// 🚀 Venda ativa só existe se houver ao menos 1 item no carrinho
const isOrderFlow = computed(() => props.cartLength > 0);

const isKdsBarEnabled = computed(() => {
    const mode = page.props.companySettings?.kds_panel_mode || 'all';
    return mode === 'all' || mode === 'bar';
});

// Inclui 'driver' e 'checklists' na lista de abas nativas para evitar o destaque falso no ícone da engrenagem
const isExtraTabActive = computed(() => {
    return !['home', 'operations', 'menu', 'cart', 'bar', 'restock', 'driver', 'checklists'].includes(props.activeTab);
});

const isInCategoryView = computed(() => {
    return props.selectedCategory &&
        (props.categoryLayout === 'grid' || props.categoryLayout === 'list') &&
        props.activeTab === 'menu';
});
</script>
