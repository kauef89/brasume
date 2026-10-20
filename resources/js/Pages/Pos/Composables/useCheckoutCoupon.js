import { ref } from 'vue';
import axios from 'axios';

export function useCheckoutCoupon({ selectedItemsValue, effectiveDeliveryFee, effectiveServiceFee, liveOrder, toast }) {
    const appliedCheckoutCoupon = ref(null);
    const appliedCheckoutDiscount = ref(0);
    const isCouponModalVisible = ref(false);

    const applyCoupon = async (code) => {
        if (!code || !code.trim()) {
            toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe um código de cupom.', life: 3000 });
            return;
        }

        try {
            const response = await axios.post(route('customer.coupons.validate'), {
                code: code.trim(),
                subtotal: selectedItemsValue.value,
                is_pos: true,
                customer_id: liveOrder.value?.customer_id
            });

            appliedCheckoutCoupon.value = response.data.code;

            const type = response.data.type;
            const val = Number(response.data.value || 0);

            if (type === 'percent') {
                appliedCheckoutDiscount.value = Math.round(((selectedItemsValue.value * val) / 100) * 100) / 100;
            } else if (type === 'fixed') {
                appliedCheckoutDiscount.value = Math.min(val, selectedItemsValue.value);
            } else if (type === 'free_delivery') {
                appliedCheckoutDiscount.value = effectiveDeliveryFee.value;
            } else if (type === 'fixed_total') {
                const bruto = selectedItemsValue.value + effectiveDeliveryFee.value + effectiveServiceFee.value;
                const diff = bruto - val;
                appliedCheckoutDiscount.value = diff > 0 ? diff : 0;
            }

            isCouponModalVisible.value = false;
            toast.add({ severity: 'success', summary: 'Cupom Aplicado', detail: response.data.message, life: 3000 });
        } catch (e) {
            toast.add({ severity: 'error', summary: 'Atenção', detail: e.response?.data?.message || 'Cupom inválido.', life: 4000 });
        }
    };

    const removeCoupon = () => {
        appliedCheckoutCoupon.value = null;
        appliedCheckoutDiscount.value = 0;
        toast.add({ severity: 'info', summary: 'Removido', detail: 'Cupom de desconto removido.', life: 2500 });
    };

    return {
        appliedCheckoutCoupon,
        appliedCheckoutDiscount,
        isCouponModalVisible,
        applyCoupon,
        removeCoupon
    };
}
