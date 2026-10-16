<template>
    <footer class="c-glass-card p-5 flex flex-col gap-3 shrink-0 mb-4 select-none">
        <div class="space-y-2.5">
            <!-- Subtotal -->
            <div class="flex justify-between items-center c-text-muted text-xs">
                <span>Subtotal</span>
                <span class="text-white/90 font-bold">{{ $formatCurrency(subtotal) }}</span>
            </div>

            <!-- Taxa de Entrega Dinâmica -->
            <div v-if="orderType === 'delivery'" class="flex justify-between items-center c-text-muted text-xs">
                <span>Taxa de Entrega</span>
                <span v-if="deliveryFee === 0" class="text-emerald-400 font-bold uppercase tracking-wider text-[11px]">Grátis</span>
                <span v-else class="text-white/90 font-bold">+ {{ $formatCurrency(deliveryFee) }}</span>
            </div>

            <!-- Taxa de Serviço Dinâmica -->
            <div v-if="shouldDisplayServiceFee" class="flex justify-between items-center c-text-muted text-xs">
                <span>Taxa de Serviço {{ serviceTaxPercentage > 0 ? `(${serviceTaxPercentage}%)` : '' }}</span>
                <span v-if="serviceFee === 0" class="text-emerald-400 font-bold uppercase tracking-wider text-[11px]">R$ 0,00</span>
                <span v-else class="text-white/90 font-bold">+ {{ $formatCurrency(serviceFee) }}</span>
            </div>

            <!-- Bloco de Cupom de Desconto -->
            <div class="flex flex-col gap-2.5 pt-0.5">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span :class="discount > 0 ? 'text-emerald-400 font-black text-xs' : 'c-text-muted text-xs'">
                            {{ appliedCouponName ? `Cupom (${appliedCouponName})` : "Cupom de Desconto" }}
                        </span>

                        <button
                            v-if="!discount"
                            @click.prevent="toggleCouponInput"
                            class="c-btn-ghost !h-7 !px-3 !text-[10px] tracking-wider"
                        >
                            <i class="pi pi-ticket text-[10px]"></i>
                            <span>{{ showCouponInput ? "Cancelar" : "Adicionar" }}</span>
                        </button>

                        <button
                            v-else
                            @click.prevent="toggleCouponInput"
                            class="c-btn-ghost !h-7 !px-3 !text-[10px] tracking-wider text-red-400 active:text-red-300"
                        >
                            <i class="pi pi-times text-[10px]"></i>
                            <span>Remover</span>
                        </button>
                    </div>

                    <span v-if="discount > 0" class="text-emerald-400 font-black text-xs tracking-tight">
                        - {{ $formatCurrency(discount) }}
                    </span>
                </div>

                <!-- Input Flutuante de Cupom -->
                <div v-if="showCouponInput" class="relative flex items-center w-full animate-in fade-in duration-200 mt-1">
                    <i class="pi pi-ticket absolute left-3.5 text-white/40 text-xs pointer-events-none"></i>
                    <input
                        type="text"
                        v-model="couponCode"
                        placeholder="Código do cupom"
                        class="w-full h-11 pl-9 pr-24 rounded-2xl bg-white/10 text-white placeholder-white/30 text-xs font-bold uppercase tracking-wider border-none outline-none focus:bg-white/15 transition-all"
                        @keyup.enter="handleApplyCoupon"
                    />
                    <button
                        @click="handleApplyCoupon"
                        :disabled="!couponCode"
                        class="absolute right-1.5 h-8 px-4 rounded-xl font-black text-[10px] uppercase tracking-wider transition-all border-none outline-none"
                        :class="couponCode ? 'bg-primary-500 text-white cursor-pointer active:scale-95' : 'bg-white/10 text-white/30 cursor-not-allowed'"
                    >
                        Aplicar
                    </button>
                </div>
            </div>

            <!-- Cashback Utilizado -->
            <div v-if="cashbackDiscount > 0" class="flex justify-between items-center text-xs text-emerald-400 font-bold uppercase pt-0.5">
                <span class="flex items-center gap-1.5">
                    <i class="pi pi-wallet text-xs"></i> Cashback Utilizado
                </span>
                <span class="font-black">- {{ $formatCurrency(cashbackDiscount) }}</span>
            </div>

            <!-- Total Geral -->
            <div class="flex justify-between items-center pt-3 mt-1 border-t border-white/10">
                <span class="c-title text-xl">Total</span>
                <span class="c-title text-2xl text-primary-400">{{ $formatCurrency(finalTotal) }}</span>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { formatCurrency } from "@/Utils/formatters";

const props = defineProps({
    subtotal: { type: Number, required: true },
    deliveryFee: { type: Number, default: 0 },
    serviceFee: { type: Number, default: 0 },
    serviceTaxPercentage: { type: Number, default: 0 },
    showServiceFeeRow: { type: Boolean, default: false },
    discount: { type: Number, default: 0 },
    cashbackDiscount: { type: Number, default: 0 },
    finalTotal: { type: Number, required: true },
    orderType: { type: String, default: null },
    appliedCouponName: { type: String, default: null }
});

const emit = defineEmits(["apply-coupon", "remove-coupon", "toggle-coupon-form"]);

const showCouponInput = ref(false);
const couponCode = ref("");

const shouldDisplayServiceFee = computed(() => props.showServiceFeeRow || props.serviceFee > 0);

const toggleCouponInput = () => {
    showCouponInput.value = !showCouponInput.value;
    emit('toggle-coupon-form', showCouponInput.value);

    if (!showCouponInput.value && !props.appliedCouponName) {
        couponCode.value = "";
        emit('remove-coupon');
    }
};

const handleApplyCoupon = () => {
    if (!couponCode.value || couponCode.value.trim() === '') return;
    emit('apply-coupon', couponCode.value.trim(), props.subtotal);
};

watch(
    () => [props.discount, props.appliedCouponName],
    ([newDiscount, newCouponName]) => {
        if (newDiscount > 0 || newCouponName) {
            showCouponInput.value = false;
            emit('toggle-coupon-form', false);
        }
    }
);

const closeCouponForm = () => {
    showCouponInput.value = false;
    emit('toggle-coupon-form', false);
};

defineExpose({ closeCouponForm });
</script>
