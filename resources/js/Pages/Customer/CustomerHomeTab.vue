<template>
    <div class="w-full h-full relative overflow-hidden select-none">
        <div
            class="relative z-10 h-full overflow-y-auto pb-32 hide-scrollbar flex flex-col"
            :style="{ top: 'var(--app-header-height, 7rem)' }"
        >

            <!-- 1. BANNER DE CONVERSÃO DO VISITANTE (Estratégia de captura) -->
            <GuestConversionBanner
                v-if="!hasActiveOrder || isGuest"
                :is-guest="isGuest"
                :active-orders="$page.props.myActiveOrders"
                :company-settings="$page.props.companySettings"
                @open-register="$emit('open-register')"
            />

            <!-- 2. WIDGET DO PEDIDO ATIVO (Apenas clientes logados) -->
            <div class="px-4 mt-2 mb-3" v-if="hasActiveOrder && !isGuest">
                <ActiveOrderWidget :initial-orders="$page.props.myActiveOrders" />
            </div>

            <!-- 3. CARD DE STATUS DA LOJA NA HOME (HORÁRIO E DISPONIBILIDADE) -->
            <div class="px-4 mb-3 animate-in fade-in slide-in-from-top-2 duration-300">
                <div
                    @click="$emit('open-hours')"
                    class="c-glass-card p-4 rounded-3xl flex items-center justify-between gap-3 cursor-pointer transition-all hover:bg-white/10 active:scale-[0.99] border border-white/10 shadow-sm"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 shadow-inner"
                            :class="statusCardBadge.bg"
                        >
                            <i :class="['text-lg pi', statusCardBadge.icon, statusCardBadge.text]"></i>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black uppercase tracking-wider text-white">
                                    {{ statusCardBadge.title }}
                                </span>
                                <span
                                    v-if="isOpen"
                                    class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0"
                                ></span>
                            </div>
                            <p class="text-[11px] font-medium text-white/70 truncate mt-0.5">
                                {{ storeAvailabilityText }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 text-white/40 shrink-0 text-xs font-bold pl-2">
                        <span class="hidden sm:inline text-[10px] uppercase tracking-wider">Horários</span>
                        <i class="pi pi-chevron-right text-[10px]"></i>
                    </div>
                </div>
            </div>

            <!-- 4. REORDER WIDGET: REPETIR ÚLTIMO PEDIDO CONCLUÍDO -->
            <div class="px-4 mb-3" v-if="lastReorderableOrder && !hasActiveOrder && !isGuest">
                <ReorderWidget
                    :order="lastReorderableOrder"
                    @reorder="$emit('reorder',$event)"
                />
            </div>

            <!-- 5. SOLICITAÇÃO DE AVALIAÇÃO (Pedidos recém-concluídos) -->
            <div class="px-3" v-if="isRatingEnabled && pendingRatingOrder && !isGuest && !hasActiveOrder">
                <OrderRatingWidget
                    :order-id="pendingRatingOrder.id"
                    @rated="handleRatingSuccess"
                />
            </div>

            <!-- 6. BANNER DE CRM DINÂMICO (CUPONS) -->
            <CrmFeaturedBanner
                v-if="featuredCrmCoupon && !hasActiveOrder"
                :coupon="featuredCrmCoupon"
                :company-settings="$page.props.companySettings"
                @use-coupon="handleBannerCta"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCoupons } from '@/Pages/Shared/Composables/useCoupons';
import { useStoreStatus } from '@/Composables/useStoreStatus';
import { useToast } from 'primevue/usetoast';
import confetti from 'canvas-confetti';

import ActiveOrderWidget from '@/Pages/Customer/Orders/ActiveOrderWidget.vue';
import OrderRatingWidget from '@/Pages/Customer/Orders/OrderRatingWidget.vue';
import GuestConversionBanner from '@/Pages/Customer/Banners/GuestConversionBanner.vue';
import CrmFeaturedBanner from '@/Pages/Customer/Banners/CrmFeaturedBanner.vue';
import ReorderWidget from '@/Pages/Customer/Orders/ReorderWidget.vue';

const props = defineProps({
    customer: { type: Object, default: null },
    isGuest: { type: Boolean, default: true },
    myHistory: { type: Array, default: () => [] }
});

const emit = defineEmits(['navigate-to-menu', 'open-register', 'open-hours', 'open-location', 'reorder']);

const page = usePage();
const toast = useToast();
const { wallet, fetchWallet, applyCoupon } = useCoupons();
const {
    isOpen,
    isOpeningSoon,
    isPreOrder,
    isClosedIndefinitely,
    storeAvailabilityText,
    status
} = useStoreStatus();

const hasActiveOrder = computed(() => page.props.myActiveOrders && page.props.myActiveOrders.length > 0);

// 🚀 ANIMAÇÃO DE CONFETTI NA HOME
const fireConfetti = () => {
    const duration = 3 * 1000;
    const animationEnd = Date.now() + duration;
    const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 9999 };

    const interval = setInterval(function() {
        const timeLeft = animationEnd - Date.now();
        if (timeLeft <= 0) return clearInterval(interval);

        const particleCount = 50 * (timeLeft / duration);
        confetti(Object.assign({}, defaults, { particleCount, origin: { x: (Math.random() * 0.4) + 0.1, y: Math.random() - 0.2 } }));
        confetti(Object.assign({}, defaults, { particleCount, origin: { x: (Math.random() * 0.4) + 0.5, y: Math.random() - 0.2 } }));
    }, 250);
};

const checkAndFireConfetti = (orderId) => {
    if (!orderId) return;
    const storageKey = `confetti_fired_order_${orderId}`;
    if (!sessionStorage.getItem(storageKey)) {
        sessionStorage.setItem(storageKey, 'true');
        fireConfetti();
    }
};

const onOrderPlacedSuccess = (e) => {
    const orderId = e.detail?.orderId;
    if (orderId) checkAndFireConfetti(orderId);
};

// 🎯 Vigia o flash message deste cliente específico
watch(
    [() => page.props.flash?.success, () => page.props.flash?.order_id],
    ([hasSuccess, flashOrderId]) => {
        if (!flashOrderId) return;

        const isOrderCreation = Boolean(page.props.flash?.order_id) && (
            !hasSuccess || (
                /pedido/i.test(hasSuccess) &&
                /(realizado|criado|confirmado|recebido|enviado)/i.test(hasSuccess) &&
                !/cancelad/i.test(hasSuccess)
            )
        );

        if (isOrderCreation) {
            checkAndFireConfetti(flashOrderId);
        }
    },
    { immediate: true }
);

onMounted(async () => {
    window.addEventListener('order-placed-success', onOrderPlacedSuccess);
    await fetchWallet();

    const urlParams = new URLSearchParams(window.location.search);
    const couponParam = urlParams.get('cupom') || urlParams.get('coupon');

    if (couponParam) {
        const cleanCode = couponParam.toUpperCase();
        window.history.replaceState({}, document.title, window.location.pathname);
        const success = await applyCoupon(cleanCode, 0);
        if (success) {
            toast.add({ severity: 'success', summary: 'Cupom Ativado! 🎉', detail: 'O desconto foi engatilhado para seu pedido.', life: 5000 });
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('order-placed-success', onOrderPlacedSuccess);
});

const lastReorderableOrder = computed(() => {
    const history = props.myHistory?.length > 0 ? props.myHistory : (page.props.myHistory || []);
    return history.find(o => ['delivered', 'completed', 'closed'].includes(o.status)) || null;
});

const statusCardBadge = computed(() => {
    if (isClosedIndefinitely.value) {
        return {
            title: 'Fechado temporariamente',
            bg: 'bg-danger-500/20',
            text: 'text-danger-400',
            icon: 'pi-lock'
        };
    }

    if (isOpen.value) {
        return {
            title: 'Loja Aberta',
            bg: 'bg-emerald-500/20',
            text: 'text-emerald-400',
            icon: 'pi-check-circle'
        };
    }

    if (isOpeningSoon.value || status.value?.reason === 'cashier_closed') {
        return {
            title: isPreOrder.value ? 'Pré-Pedido Liberado' : 'Quase Abrindo',
            bg: 'bg-amber-500/20',
            text: 'text-amber-400',
            icon: isPreOrder.value ? 'pi-shopping-bag' : 'pi-clock'
        };
    }

    return {
        title: 'Loja Fechada',
        bg: 'bg-white/10',
        text: 'text-white/60',
        icon: 'pi-moon'
    };
});

const isRatingEnabled = computed(() => {
    return page.props.companySettings?.request_order_rating !== false;
});

const pendingRatingOrder = computed(() => {
    const history = props.myHistory?.length > 0 ? props.myHistory : (page.props.myHistory || []);
    if (!isRatingEnabled.value || history.length === 0) {
        return null;
    }

    return history.find(order =>
        !order.is_legacy &&
        ['delivered', 'completed', 'closed'].includes(order.status) &&
        !order.rating
    );
});

const handleRatingSuccess = (ratingData) => {
    if (pendingRatingOrder.value) {
        pendingRatingOrder.value.rating = ratingData;
    }
};

const featuredCrmCoupon = computed(() => {
    if (!wallet.value || wallet.value.length === 0) return null;
    return wallet.value.find(c => c.purpose === 'birthday') ||
           wallet.value.find(c => c.purpose === 'churn') ||
           wallet.value.find(c => c.is_exclusive) ||
           wallet.value[0] || null;
});

const handleBannerCta = async (couponCode) => {
    await applyCoupon(couponCode, 0);
    emit('navigate-to-menu');
};
</script>
