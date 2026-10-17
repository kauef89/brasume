<template>
    <footer class="flex flex-col gap-5 z-10 shrink-0 p-6">
        <div class="space-y-3">
            <div class="flex justify-between text-xs text-surface-muted font-medium">
                <span>Subtotal</span>
                <span>{{ $formatCurrency(subtotal) }}</span>
            </div>

            <!-- LINHA DA TAXA DE ENTREGA (DELIVERY) -->
            <div v-if="orderType === 'delivery'" class="flex justify-between items-center text-xs text-surface-muted font-medium">
                <span>Taxa de Entrega</span>

                <!-- 1. Sem endereço indicado: botão de ação -->
                <button
                    v-if="!hasAddress"
                    type="button"
                    @click="$emit('add-address')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 border border-amber-500/30 transition-all cursor-pointer outline-none active:scale-95"
                    title="Clique para selecionar ou cadastrar o endereço de entrega"
                >
                    <i class="pi pi-map-marker text-[10px]"></i>
                    <span>Adicionar endereço</span>
                </button>

                <!-- 2. Com endereço indicado e taxa zerada: Frete Grátis -->
                <span v-else-if="deliveryFee === 0" class="text-success-smart font-bold">
                    Grátis
                </span>

                <!-- 3. Com endereço e taxa calculada -->
                <span v-else class="text-surface-text font-bold">
                    + {{ $formatCurrency(deliveryFee) }}
                </span>
            </div>

            <!-- LINHA DA TAXA DE SERVIÇO DINÂMICA -->
            <div v-if="shouldDisplayServiceFee" class="flex justify-between text-xs text-surface-muted font-medium">
                <span>Taxa de Serviço {{ serviceTaxPercentage > 0 ? `(${serviceTaxPercentage}%)` : '' }}</span>
                <span v-if="serviceFee === 0" class="text-success-smart font-medium">R$ 0,00</span>
                <span v-else>+ {{ $formatCurrency(serviceFee) }}</span>
            </div>

            <!-- LINHA DO CUPOM -->
            <div class="flex flex-col gap-3">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span :class="discount > 0 ? 'text-success-smart font-black text-xs' : 'text-xs text-surface-muted font-medium'">
                            {{ appliedCouponName ? `Cupom (${appliedCouponName})` : "Cupom de Desconto" }}
                        </span>

                        <button
                            v-if="!discount"
                            @click.prevent="openCouponModal"
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary-500/10 text-primary-smart hover:bg-primary-500/20 transition-colors font-medium text-[10px] uppercase border-none outline-none cursor-pointer"
                        >
                            <i class="pi pi-ticket text-[10px]"></i>
                            Adicionar
                        </button>

                        <button
                            v-else
                            @click.prevent="handleRemoveCoupon"
                            class="flex items-center gap-1 px-2.5 py-1 rounded-full bg-danger-500/10 text-danger-smart hover:bg-danger-500/20 transition-colors font-medium text-[10px] uppercase border-none outline-none cursor-pointer"
                        >
                            <i class="pi pi-times text-[10px]"></i> Remover
                        </button>
                    </div>

                    <span v-if="discount > 0" class="text-success-smart font-black text-xs uppercase">
                        - {{ $formatCurrency(discount) }}
                    </span>
                </div>
            </div>

            <!-- LINHA DO CASHBACK -->
            <div v-if="cashbackDiscount > 0" class="flex justify-between items-center text-xs text-success-500 font-medium uppercase animate-in fade-in pt-1">
                <span class="flex items-center gap-1.5"><i class="pi pi-wallet text-[10px]"></i> Cashback Utilizado</span>
                <span class="font-black">- {{ $formatCurrency(cashbackDiscount) }}</span>
            </div>

            <!-- TOTAL GERAL -->
            <div class="flex justify-between items-center text-3xl font-black text-surface-text pt-2 mt-2 border-t border-surface-border/30">
                <span>Total</span>
                <span>{{ $formatCurrency(finalTotal) }}</span>
            </div>
        </div>

        <Button
            label="Confirmar Pedido"
            rounded
            icon="pi pi-send"
            class="w-full h-14 rounded-full font-black text-xl transition-all active:scale-[0.98] border-none outline-none mt-2"
            :class="canSubmit ? 'bg-primary-500 text-white hover:bg-primary-600 cursor-pointer' : 'opacity-20 grayscale cursor-not-allowed'"
            :disabled="!canSubmit"
            @click="$emit('checkout')"
        />

        <!-- MODAL SELETOR DE CUPONS & FALLBACK DE COMBOS -->
        <CouponPickerModal
            v-model="showCouponModal"
            :subtotal="subtotal"
            :customer-id="customerId"
            @apply="handleApplyFromModal"
        />
    </footer>
</template>

<script setup>
import { ref, computed } from "vue";
import Button from "primevue/button";
import { formatCurrency } from "@/Utils/formatters";
import CouponPickerModal from "./CouponPickerModal.vue";

const props = defineProps({
    subtotal: { type: Number, required: true },
    deliveryFee: { type: Number, default: 0 },
    hasAddress: { type: Boolean, default: false },
    serviceFee: { type: Number, default: 0 },
    serviceTaxPercentage: { type: Number, default: 0 },
    showServiceFeeRow: { type: Boolean, default: false },
    discount: { type: Number, default: 0 },
    cashbackDiscount: { type: Number, default: 0 },
    finalTotal: { type: Number, required: true },
    orderType: { type: [String, null], default: null },
    isRoutingValid: { type: Boolean, default: false },
    canSubmit: { type: Boolean, default: false },
    appliedCouponName: { type: String, default: null },
});

const emit = defineEmits(['checkout', 'add-address', 'open-coupons', 'remove-coupon']);

const showCouponModal = ref(false);

const shouldDisplayServiceFee = computed(() => props.showServiceFeeRow || props.serviceFee > 0);

const openCouponModal = () => {
    showCouponModal.value = true;
    emit("toggle-coupon-form", true);
};

const handleApplyFromModal = (code) => {
    emit("apply-coupon", code, props.subtotal);
    showCouponModal.value = false;
    emit("toggle-coupon-form", false);
};

const handleRemoveCoupon = () => {
    emit("remove-coupon");
};

const closeCouponForm = () => {
    showCouponModal.value = false;
    emit("toggle-coupon-form", false);
};

defineExpose({ closeCouponForm });
</script>
