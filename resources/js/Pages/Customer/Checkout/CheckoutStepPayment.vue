<template>
    <div class="w-full flex-1 overflow-y-auto hide-scrollbar px-6 pt-2 pb-8 flex flex-col gap-4">

        <CustomerCartSummary
            ref="cartSummaryRef"
            :subtotal="baseSubtotal"
            :delivery-fee="deliveryFee"
            :discount="activeDiscount"
            :cashback-discount="useCashbackToggle ? cashbackDiscount : 0"
            :final-total="finalTotalWithDiscount"
            :order-type="selectedOrderType"
            :applied-coupon-name="appliedCouponName"
            @apply-coupon="(code, subtotal) => $emit('apply-coupon', code, subtotal)"
            @remove-coupon="$emit('remove-coupon')"
            @toggle-coupon-form="$emit('update:isTypingCoupon', $event)"
        />

        <div v-show="!isTypingCoupon" class="flex flex-col gap-4 animate-in fade-in slide-in-from-bottom-4 duration-300">

        <LoyaltyCashbackWidget
            v-if="isLoyaltyActive && availableBalance > 0 && !isGuest"
            :is-active="isLoyaltyActive"
            :can-redeem="canRedeem"
            :available-balance="availableBalance"
            :customer-points="customerPoints"
            :missing-amount="missingAmount"
            :model-value="useCashbackToggle"
            @update:model-value="$emit('update:useCashbackToggle', $event)"
        />

            <div v-if="isGuestRestricted && !selectedMethod"
                 :class="{ 'animate-shake ring-2 ring-warning-400/50 shadow-[0_0_15px_rgba(251,191,36,0.3)]': isShaking }"
                 class="p-5 c-glass-card !bg-warning-500/15 backdrop-blur-xl flex flex-col gap-4 text-warning-300 transition-all duration-300">
                <div class="flex items-start gap-2.5">
                    <i class="pi pi-exclamation-circle mt-0.5 text-lg text-warning-400"></i>
                    <span class="text-xs font-medium leading-relaxed flex-1 text-white/90">
                        Visitantes pagam apenas com Pix. Para liberar outras formas, crie sua conta grátis.
                    </span>
                </div>
                <button @click="$emit('upgrade-guest')" class="c-btn-primary !h-12 !text-xs !bg-warning-500 text-black active:!bg-warning-400">
                    Criar Conta Agora
                </button>
            </div>

            <div v-if="finalTotalWithDiscount <= 0" class="p-6 c-glass-card !bg-emerald-500/15 flex flex-col gap-3 text-emerald-400 items-center justify-center text-center">
                <i class="pi pi-check-circle text-4xl mb-1 text-emerald-400"></i>
                <h3 class="font-black text-sm uppercase tracking-widest text-emerald-300">Pedido Grátis!</h3>
            </div>

            <div v-else class="flex flex-col gap-2 mt-1">
                <span class="text-[10px] font-black text-white/50 uppercase tracking-widest ml-1 mb-1">Escolha a Forma</span>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="method in availableMethods"
                        :key="method.id"
                        @click="handleMethodClick(method)"
                        type="button"
                        class="w-full p-3.5 c-glass-card-interactive !rounded-2xl flex flex-row items-center justify-center gap-2.5 text-center transition-all cursor-pointer outline-none border border-transparent"
                        :class="[
                            (isGuestRestricted && method.engine_type !== 'bank_api')
                                ? 'opacity-40 grayscale pointer-events-none !bg-white/5 !border-transparent'
                                : selectedMethod?.id === method.id
                                    ? '!bg-primary-500/90 !border-primary-400 text-white shadow-md shadow-primary-500/30 scale-[1.02]'
                                    : '!bg-primary-500/20 hover:!bg-primary-500/30 !border-primary-500/30 text-white/90'
                        ]"
                    >
                        <div class="relative flex items-center justify-center h-8 shrink-0">
                            <i class="pi text-xl transition-colors" :class="[getIcon(method), selectedMethod?.id === method.id ? 'text-white' : 'text-primary-300']"></i>
                        </div>
                        <span class="font-black text-[10px] leading-tight line-clamp-2 transition-colors" :class="selectedMethod?.id === method.id ? 'text-white font-extrabold' : 'text-white/90'">
                            {{ method.name }}
                        </span>
                    </button>
                </div>
            </div>

            <SlideToConfirm
                v-if="selectedMethod || finalTotalWithDiscount <= 0"
                :text="finalTotalWithDiscount <= 0 ? 'Deslizar para Concluir' : (selectedMethod?.engine_type === 'bank_api' ? 'Deslize para Pagar via Pix' : 'Deslizar e Fazer Pedido')"
                :is-loading="isSubmitting"
                :disabled="finalTotalWithDiscount > 0 && !selectedMethod"
                @confirm="handleConfirmClick"
                class="mt-4"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CustomerCartSummary from '@/Pages/Customer/Cart/CustomerCartSummary.vue';
import LoyaltyCashbackWidget from '@/Pages/Pos/Components/Cart/LoyaltyCashbackWidget.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';
import { useCashInput } from '@/Pages/Shared/Composables/useCashInput';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    isGuest: Boolean,
    baseSubtotal: Number,
    deliveryFee: Number,
    activeDiscount: Number,
    subtotalWithFeesAndCoupons: Number,
    finalTotalWithDiscount: Number,
    selectedOrderType: String,
    appliedCouponName: String,
    isTypingCoupon: Boolean,
    availableMethods: Array,
    selectedMethod: Object,
    cashChangeTarget: [Number, String],
    useCashbackToggle: Boolean,
    isLoyaltyActive: Boolean,
    availableBalance: Number,
    customerPoints: Number,
    canRedeem: Boolean,
    missingAmount: Number,
    cashbackDiscount: { type: Number, default: 0 },
    isSubmitting: Boolean
});

const emit = defineEmits([
    'update:isTypingCoupon', 'apply-coupon', 'remove-coupon', 'update:selectedMethod',
    'update:cashChangeTarget', 'update:useCashbackToggle', 'confirm', 'upgrade-guest'
]);

const page = usePage();
const isShaking = ref(false);
const cartSummaryRef = ref(null);

const isGuestRestricted = computed(() => props.isGuest && page.props.company?.guestRestrictedPayments);

const triggerWarningShake = () => {
    isShaking.value = true;
    setTimeout(() => { isShaking.value = false; }, 450);
};

const handleMethodClick = (method) => {
    console.log('📍 [SONDA VUE 1] Método selecionado pelo cliente:', method.name, method.id);
    if (isGuestRestricted.value && method.engine_type !== 'bank_api') {
        triggerWarningShake();
        return;
    }
    emit('update:selectedMethod', method);
};

// 🚀 NOVA TRAVA DE SEGURANÇA FRONTEND COM LOG
const handleConfirmClick = () => {
    console.log('📍 [SONDA VUE 2] SlideToConfirm disparado.', {
        total: props.finalTotalWithDiscount,
        metodo: props.selectedMethod
    });

    if (props.finalTotalWithDiscount > 0 && !props.selectedMethod) {
        console.warn('🚨 [SONDA VUE ERRO] Tentativa de avanço barrada. Pagamento é obrigatório.');
        return;
    }
    emit('confirm');
};

const getIcon = (method) => {
    const name = method.name.toLowerCase();
    if (method.engine_type === 'bank_api' || name.includes('pix')) return 'pi-qrcode';
    if (name.includes('cartão') || name.includes('credito') || name.includes('debito')) return 'pi-credit-card';
    if (name.includes('dinheiro') || name.includes('cash')) return 'pi-money-bill';
    return 'pi-wallet';
};

const goBack = () => {
    if (props.isTypingCoupon) {
        emit('update:isTypingCoupon', false);
        return true;
    }
    return false;
};
defineExpose({ goBack });
</script>
