import { computed } from "vue";

export function useOrderFinancials(
    order,
    orderTotalItemsValue,
    isServiceTaxEnabled,
    companySettings,
) {
    const deliveryFee = computed(() => Number(order.delivery_fee || 0));

    const discount = computed(() => Number(order.discount || 0));

    const serviceFeeAmount = computed(() => {
        if (!isServiceTaxEnabled.value) return 0;
        const percentage = Number(
            companySettings?.service_tax_percentage || 10,
        );
        return orderTotalItemsValue.value * (percentage / 100);
    });

    const dynamicOrderTotal = computed(
        () =>
            orderTotalItemsValue.value +
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
        dynamicOrderTotal,
        feesAndDiscounts,
    };
}
