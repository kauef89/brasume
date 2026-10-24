<template>
    <nav
        v-show="isVisible"
        ref="islandRef"
        :class="[baseIslandClass, shapeIslandClass]"
        :style="islandStyle"
    >
        <!-- Container isolado de layout -->
        <div ref="contentRef" class="w-max h-max flex items-center justify-center shrink-0 p-1.5 transition-all duration-300">

            <!-- ESTADO 1: NAVEGAÇÃO NORMAL -->
            <div
                v-show="!isCartMode && !configuratorType"
                class="flex items-center shrink-0"
            >
                <!-- MODO PADRÃO: Exibe Home -->
                <template v-if="!isTableMode && showAdvancedTabs">
                    <button
                        @click="handleTabClick('home')"
                        class="c-island-btn"
                        :class="activeTab === 'home' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                    >
                        <i class="pi pi-home"></i>
                    </button>
                </template>

                <!-- BOTÃO DO CARDÁPIO (Sempre Ativo) -->
                <button
                    @click="handleTabClick('menu')"
                    class="c-island-btn"
                    :class="activeTab === 'menu' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                >
                    <i class="pi pi-book"></i>
                </button>

                <!-- BOTÃO DA CONTA (Mesa ou Pedidos) -->
                <button
                    v-if="isTableMode || showAdvancedTabs"
                    @click="$emit('update:activeTab', 'orders')"
                    class="c-island-btn relative"
                    :class="activeTab === 'orders' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                    :title="isTableMode ? 'Conta da Mesa' : 'Meus Pedidos'"
                >
                    <i :class="isTableMode ? 'pi pi-table' : 'pi pi-receipt'"></i>
                    <span v-if="isTableMode && hasActiveOrder" class="absolute top-1 right-1 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-primary-500"></span>
                    </span>
                </button>

                <!-- PERFIL (Apenas fora do Modo Mesa) -->
                <template v-if="!isTableMode && showAdvancedTabs">
                    <button
                        @click="$emit('update:activeTab', 'account')"
                        class="c-island-btn"
                        :class="activeTab === 'account' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                    >
                        <i class="pi pi-user"></i>
                    </button>
                </template>

                <!-- CARRINHO PENDENTE -->
                <template v-if="totalCartCount > 0">
                    <button
                        @click="$emit('update:activeTab', 'cart')"
                        class="c-island-btn relative"
                        :class="activeTab === 'cart' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                    >
                        <i class="pi pi-shopping-cart"></i>
                        <span class="absolute top-1 right-1 flex h-[18px] min-w-[18px]">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-primary-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                                {{ totalCartCount }}
                            </span>
                        </span>
                    </button>
                </template>
            </div>

            <!-- 🚀 ESTADO 2: PORTAL DE CONFIGURADORES -->
            <!-- O alvo DEVE estar sempre no DOM (usamos v-show em vez de v-if) para que os Navs encontrem a âncora instantaneamente. -->
            <div
                v-show="configuratorType && !isCartMode"
                id="island-portal-target"
                class="w-max flex items-center justify-center shrink-0"
            ></div>

            <!-- ESTADO 3: MODO CARRINHO -->
            <div
                v-show="isCartMode"
                class="flex items-center gap-2 shrink-0 px-1"
            >
                <button
                    @click="$emit('update:activeTab', 'menu')"
                    class="w-11 h-11 shrink-0 rounded-full bg-surface-1 hover:bg-surface-2 text-surface-text flex items-center justify-center transition-colors border-none outline-none cursor-pointer shadow-sm"
                >
                    <i class="pi pi-arrow-left text-sm"></i>
                </button>

                <SlideToConfirm
                    :key="sliderKey"
                    :text="sliderButtonText"
                    success-text="Redirecionando..."
                    :icon="sliderButtonIcon"
                    :color="canOrder ? 'primary' : 'surface'"
                    format="long"
                    :disabled="!canOrder"
                    :is-success="isCheckoutSuccess"
                    @confirm="handleConfirmPayment"
                    class="w-56 sm:w-64 !h-11 transition-all"
                    :class="{ 'opacity-60 grayscale cursor-not-allowed pointer-events-none': !canOrder }"
                />
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useDynamicIsland } from "@/Pages/Shared/Composables/useDynamicIsland";
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';

const props = defineProps({
    activeTab: String,
    cartLength: { type: [Number, Array], default: 0 },
    cart: { type: Array, default: () => [] },
    customer: { type: Object, default: null },
    hasActiveOrder: { type: Boolean, default: false },
    configuratorType: { type: String, default: null },
    canOrder: { type: Boolean, default: true }
});

const emit = defineEmits(['update:activeTab', 'reset-menu', 'trigger-checkout']);
const page = usePage();

const isTableMode = computed(() => Boolean(page.props.tableMode));

const isCheckoutSuccess = ref(false);
const sliderKey = ref(0);

const totalCartCount = computed(() => {
    if (Array.isArray(props.cart) && props.cart.length > 0) {
        return props.cart.reduce((sum, item) => sum + (Number(item.qty) || 1), 0);
    }
    if (Array.isArray(props.cartLength)) {
        return props.cartLength.reduce((sum, item) => sum + (Number(item.qty) || 1), 0);
    }
    return Number(props.cartLength) || 0;
});

const isCartMode = computed(() => props.activeTab === 'cart' && totalCartCount.value > 0);

const sliderButtonText = computed(() => {
    if (!props.canOrder) return 'Loja Fechada';
    return isTableMode.value ? 'Deslize para Pedir' : 'Deslize para Pagar';
});

const sliderButtonIcon = computed(() => {
    if (!props.canOrder) return 'pi-lock';
    return isTableMode.value ? 'pi-send' : 'pi-shopping-cart';
});

const {
    baseIslandClass,
    shapeIslandClass,
    islandStyle,
    islandRef,
    contentRef,
    handleTabClick
} = useDynamicIsland(props, emit);

// 🚀 A ILHA ESTÁ SEMPRE VISÍVEL QUANDO ESTÁ NO MODO CONFIGURADOR
const isVisible = computed(() =>
    isTableMode.value ||
    totalCartCount.value > 0 ||
    props.customer !== null ||
    props.hasActiveOrder ||
    props.configuratorType !== null
);

const showAdvancedTabs = computed(() => props.customer !== null || props.hasActiveOrder);

const handleConfirmPayment = () => {
    if (!props.canOrder || isCheckoutSuccess.value) return;

    isCheckoutSuccess.value = true;
    emit('trigger-checkout');

    setTimeout(() => {
        isCheckoutSuccess.value = false;
        sliderKey.value++;
    }, 1000);
};

watch(
    () => [isCartMode.value, props.canOrder],
    ([newCartMode]) => {
        if (!newCartMode) isCheckoutSuccess.value = false;
        sliderKey.value++;
    }
);
</script>
