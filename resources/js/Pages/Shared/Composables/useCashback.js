import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export function useCashback(customerRef, orderSubtotalRef) {
    const page = usePage();
    const settings = computed(() => page.props.companySettings || {});

    const useCashbackToggle = ref(false);

    const isLoyaltyActive = computed(() => settings.value.loyalty_is_active && settings.value.loyalty_reward_type === 'cashback');
    const minRedeemValue = computed(() => Number(settings.value.loyalty_min_redeem_value || 0));
    const conversionRate = computed(() => Number(settings.value.loyalty_cashback_conversion_rate || 5));

    // 🚀 CONFIGURAÇÃO DE TETO MÁXIMO
    const maxRedeemValue = computed(() => Number(settings.value.loyalty_max_redeem_value || 0));
    const maxRedeemType = computed(() => settings.value.loyalty_max_redeem_type || 'fixed');

    const customerPoints = computed(() => customerRef.value?.loyalty_points || 0);
    const canRedeem = computed(() => orderSubtotalRef.value >= minRedeemValue.value);

    const missingAmount = computed(() => Math.max(0, minRedeemValue.value - orderSubtotalRef.value));
    const availableBalance = computed(() => (customerPoints.value / 100) * conversionRate.value);

    // 🚀 CÁLCULO DO TETO APLICÁVEL AO PEDIDO
    const maxAllowedCap = computed(() => {
        if (maxRedeemValue.value <= 0) return Infinity;

        if (maxRedeemType.value === 'percentage') {
            return (orderSubtotalRef.value * maxRedeemValue.value) / 100;
        }

        return maxRedeemValue.value;
    });

    const cashbackDiscount = computed(() => {
        if (!useCashbackToggle.value || !canRedeem.value || customerPoints.value <= 0) return 0;

        // 🚀 O desconto respeita: saldo disponível, subtotal do pedido e o teto configurado
        return Math.min(availableBalance.value, orderSubtotalRef.value, maxAllowedCap.value);
    });

    watch([canRedeem, isLoyaltyActive], ([redeemable, active]) => {
        if (!redeemable || !active || customerPoints.value <= 0) {
            useCashbackToggle.value = false;
        }
    });

    return {
        useCashbackToggle,
        cashbackDiscount,
        isLoyaltyActive,
        availableBalance,
        customerPoints,
        canRedeem,
        missingAmount,
        maxRedeemValue,
        maxRedeemType,
        maxAllowedCap
    };
}
