<template>
    <div v-if="isLoyaltyActive" class="p-4 rounded-[1.5rem] border transition-all duration-300" :class="modelValue ? 'bg-primary-500/10 border-primary-500/30 shadow-inner' : 'bg-surface-2 border-surface-border shadow-sm'">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3" :class="customerPoints <= 0 || isGuest ? 'opacity-50 grayscale' : ''">
                <div class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center transition-all duration-300" :class="modelValue ? 'bg-primary-500 text-white shadow-[0_0_15px_rgba(var(--color-primary-500),0.4)] scale-110' : 'bg-surface-3 text-surface-muted'">
                    <i class="pi pi-star-fill text-lg"></i>
                </div>

                <div class="flex flex-col">
                    <span class="font-black text-sm text-surface-text leading-tight">{{ title }}</span>
                    <span class="text-[10px] font-bold" :class="modelValue ? 'text-primary-600 dark:text-primary-400' : 'text-surface-muted'">
                        Saldo: {{ $formatCurrency(availableBalance) }} ({{ customerPoints }} pts)
                    </span>
                </div>
            </div>

            <label class="relative inline-flex items-center" :class="!canRedeem || customerPoints <= 0 || isGuest ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
                <input
                    type="checkbox"
                    :checked="modelValue"
                    @change="$emit('update:modelValue', $event.target.checked)"
                    class="sr-only peer"
                    :disabled="!canRedeem || customerPoints <= 0 || isGuest"
                >
                <div class="w-11 h-6 bg-surface-border rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
            </label>
        </div>

        <template v-if="!isGuest">
            <!-- 🚀 AVISO DO TETO DE RESGATE (FIXO OU PERCENTUAL) -->
            <div
                v-if="canRedeem && hasMaxLimit"
                class="mt-3 text-[11px] px-3 py-2 rounded-xl border flex items-start gap-2 transition-colors"
                :class="isCapLimitingBalance ? 'bg-primary-500/10 border-primary-500/30 text-primary-smart' : 'bg-surface-3/70 border-surface-border/50 text-surface-muted'"
            >
                <i class="pi pi-info-circle text-xs shrink-0 mt-0.5" :class="isCapLimitingBalance ? 'text-primary-500' : 'text-surface-muted'"></i>
                <div class="leading-tight flex flex-col gap-0.5">
                    <div>
                        <span>Limite máximo de resgate: </span>
                        <b class="text-surface-text">{{ maxLimitDescription }}</b>
                    </div>
                    <span v-if="isCapLimitingBalance" class="text-[10px] opacity-85">
                        O saldo restante permanecerá salvo na sua conta para as próximas compras.
                    </span>
                </div>
            </div>

            <div v-if="!canRedeem && missingAmount > 0 && customerPoints > 0" class="mt-3 text-[10px] font-bold text-warning-600 bg-warning-500/10 px-3 py-2 rounded-xl flex items-center gap-2 border border-warning-500/20">
                <i class="pi pi-info-circle text-xs shrink-0"></i>
                <span>Faltam <b>{{ $formatCurrency(missingAmount) }}</b> em produtos para liberar o uso de cashback neste pedido.</span>
            </div>

            <div v-else-if="customerPoints <= 0" class="mt-3 text-[10px] font-bold text-surface-muted bg-surface-3 px-3 py-2 rounded-xl flex items-center gap-2 border border-surface-border/50">
                <i class="pi pi-lock text-xs shrink-0"></i>
                <span>Você ainda não possui saldo de cashback para resgatar.</span>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { formatCurrency } from "@/Utils/formatters";

const props = defineProps({
    modelValue: Boolean,
    isLoyaltyActive: Boolean,
    availableBalance: Number,
    customerPoints: Number,
    canRedeem: Boolean,
    missingAmount: Number,
    maxCap: { type: Number, default: null },
    maxRedeemType: { type: String, default: null }, // 'fixed' | 'percentage'
    maxRedeemValue: { type: [Number, String], default: null },
    isGuest: { type: Boolean, default: false },
    title: { type: String, default: 'Cashback' }
});

defineEmits(['update:modelValue']);

const page = usePage();
const companySettings = computed(() => page.props.companySettings || {});

// 🎯 Resolve tipo e valor (Props têm precedência sobre os settings globais)
const resolvedLimitType = computed(() => {
    return props.maxRedeemType || companySettings.value.loyalty_max_redeem_type || 'fixed';
});

const resolvedLimitValue = computed(() => {
    if (props.maxRedeemValue !== null && props.maxRedeemValue !== undefined) {
        return Number(props.maxRedeemValue);
    }
    const settingVal = companySettings.value.loyalty_max_redeem_value;
    return settingVal ? Number(settingVal) : null;
});

const hasMaxLimit = computed(() => {
    return resolvedLimitValue.value !== null && resolvedLimitValue.value > 0;
});

// Checa se o teto é menor que o saldo total acumulado do cliente
const isCapLimitingBalance = computed(() => {
    if (!props.availableBalance) return false;
    if (props.maxCap !== null && props.maxCap !== undefined) {
        return props.maxCap < props.availableBalance;
    }
    if (resolvedLimitType.value === 'fixed' && resolvedLimitValue.value) {
        return resolvedLimitValue.value < props.availableBalance;
    }
    return false;
});

// Monta o descritivo dinâmico para valor fixo ou percentual
const maxLimitDescription = computed(() => {
    if (!hasMaxLimit.value) return '';

    if (resolvedLimitType.value === 'percentage') {
        const pctText = `${resolvedLimitValue.value}% do pedido`;
        if (props.maxCap) {
            return `${pctText} (${formatCurrency(props.maxCap)})`;
        }
        return pctText;
    }

    // Tipo Fixo
    return `${formatCurrency(resolvedLimitValue.value)} por pedido`;
});
</script>
