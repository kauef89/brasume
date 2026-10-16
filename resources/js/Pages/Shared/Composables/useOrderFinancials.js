import { computed, unref } from "vue";

export function useOrderFinancials(
    order,
    orderTotalItemsValue,
    isServiceTaxEnabled,
    companySettings,
) {
    const rawOrder = computed(() => unref(order) || {});

    // 🚀 A taxa de entrega só pode existir se a modalidade for estritamente delivery
    const deliveryFee = computed(() => {
        const type = rawOrder.value.type || 'table';
        if (type !== 'delivery') return 0;
        return Number(rawOrder.value.delivery_fee || 0);
    });

    const discount = computed(() => Number(rawOrder.value.discount || 0));

    const showServiceFeeRow = computed(() => Boolean(companySettings?.has_service_tax));

    const serviceTaxPercentage = computed(() => Number(companySettings?.service_tax_percentage || 0));

    const isChannelEligible = computed(() => {
        const type = rawOrder.value.type || 'table';
        if (type === 'table') {
            return companySettings?.service_tax_table !== false && companySettings?.service_tax_table !== 0;
        }
        if (type === 'counter' || type === 'takeaway' || type === 'pickup') {
            return Boolean(companySettings?.service_tax_counter);
        }
        if (type === 'delivery') {
            return Boolean(companySettings?.service_tax_delivery);
        }
        return false;
    });

    const serviceFeeAmount = computed(() => {
        const isManuallyActive = unref(isServiceTaxEnabled) !== false;
        if (!isManuallyActive || !showServiceFeeRow.value || !isChannelEligible.value) {
            return 0;
        }

        const percentage = serviceTaxPercentage.value;
        return Math.round((Number(unref(orderTotalItemsValue) || 0) * (percentage / 100)) * 100) / 100;
    });

    const dynamicOrderTotal = computed(
        () =>
            Number(unref(orderTotalItemsValue) || 0) +
            deliveryFee.value +
            serviceFeeAmount.value -
            discount.value,
    );

    const feesAndDiscounts = computed(
        () => deliveryFee.value + serviceFeeAmount.value - discount.value,
    );

    return {
        deliveryFee,
        discount,
        serviceFeeAmount,
        serviceTaxPercentage,
        showServiceFeeRow,
        isChannelEligible,
        dynamicOrderTotal,
        feesAndDiscounts,
    };
}
