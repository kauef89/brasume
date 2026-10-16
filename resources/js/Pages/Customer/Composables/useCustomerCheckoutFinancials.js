import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useCustomerCheckoutFinancials(props, cartContext, checkoutState, calculateDiscount) {
    const page = usePage();
    const { cartSubtotal } = cartContext;

    const isTableMode = computed(() => Boolean(page.props.tableMode));

    const loyalty = computed(() => {
        const settings = page.props.companySettings || {};
        const isActive = (settings.loyalty_is_active === true || settings.loyalty_is_active === 1 || settings.loyalty_is_active === '1')
            && settings.loyalty_reward_type === 'cashback';

        const points = props.customer?.loyalty_points ? Number(props.customer.loyalty_points) : 0;
        const conversionRate = Number(settings.loyalty_cashback_conversion_rate || 5);
        const balance = (points / 100) * conversionRate;
        const minRedeem = Number(settings.loyalty_min_redeem_value || 0);
        const missing = Math.max(0, minRedeem - cartSubtotal.value);
        const canRedeem = isActive && points > 0 && missing <= 0;

        const maxVal = Number(settings.loyalty_max_redeem_value || 0);
        const maxType = settings.loyalty_max_redeem_type || 'fixed';
        const maxCap = maxVal > 0
            ? (maxType === 'percentage' ? (cartSubtotal.value * maxVal) / 100 : maxVal)
            : Infinity;

        return { isActive, points, balance, missing, canRedeem, maxVal, maxType, maxCap };
    });

    const financials = computed(() => {
        const settings = page.props.companySettings || {};
        const hasServiceTaxGlobal = Boolean(settings.has_service_tax);
        const basePercentage = Number(settings.service_tax_percentage || 0);

        // 🚀 No modo mesa ou balcão, a taxa de entrega é sempre zero
        let deliveryFee = 0;
        if (!isTableMode.value && checkoutState.orderType === 'delivery' && checkoutState.routingMeta) {
            const addr = checkoutState.routingMeta.address || checkoutState.routingMeta.meta?.address;
            if (addr?.delivery_fee !== undefined && addr?.delivery_fee !== null) {
                deliveryFee = Number(addr.delivery_fee);
            } else if (addr?.neighborhood?.delivery_fee !== undefined) {
                deliveryFee = Number(addr.neighborhood.delivery_fee);
            }
        }

        // Taxa de serviço por canal
        let isTaxEnabledForChannel = false;
        if (hasServiceTaxGlobal) {
            const type = isTableMode.value ? 'table' : (checkoutState.orderType || 'delivery');
            if (type === 'table') {
                isTaxEnabledForChannel = settings.service_tax_table !== false && settings.service_tax_table !== 0;
            } else if (type === 'counter' || type === 'takeaway' || type === 'pickup') {
                isTaxEnabledForChannel = Boolean(settings.service_tax_counter);
            } else if (type === 'delivery') {
                isTaxEnabledForChannel = Boolean(settings.service_tax_delivery);
            }
        }

        const effectiveRate = isTaxEnabledForChannel ? basePercentage : 0;
        const serviceFee = isTaxEnabledForChannel
            ? Math.round((cartSubtotal.value * (basePercentage / 100)) * 100) / 100
            : 0;

        const discount = calculateDiscount(cartSubtotal.value, deliveryFee, serviceFee);
        let cashbackDiscount = 0;

        if (checkoutState.useCashbackToggle && loyalty.value.canRedeem) {
            const availableSubtotal = Math.max(0, cartSubtotal.value - discount);
            cashbackDiscount = Math.min(loyalty.value.balance, availableSubtotal, loyalty.value.maxCap);
        }

        const finalTotal = Math.max(0, cartSubtotal.value + deliveryFee + serviceFee - discount - cashbackDiscount);

        return {
            subtotal: cartSubtotal.value,
            deliveryFee,
            delivery_fee: deliveryFee,
            serviceFee,
            service_fee: serviceFee,
            serviceTaxPercentage: basePercentage,
            effectiveServiceTaxRate: effectiveRate,
            hasServiceTaxGlobal,
            isTaxEnabledForChannel,
            showServiceFeeRow: hasServiceTaxGlobal,
            discount,
            cashbackDiscount,
            finalTotal
        };
    });

    return { loyalty, financials };
}
