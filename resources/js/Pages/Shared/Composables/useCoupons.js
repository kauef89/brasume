import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

// Estado global compartilhado em toda a aplicação
const wallet = ref([]);
const activeCoupon = ref(null);
const isLoadingWallet = ref(false);
const isApplyingCoupon = ref(false);

export function useCoupons() {
    const toast = useToast();

    // Nome legível do cupom ativo
    const appliedCouponName = computed(() => {
        if (!activeCoupon.value) return null;
        return activeCoupon.value.code || activeCoupon.value.name || null;
    });

    // 🚀 O MOTOR DE CÁLCULO CENTRALIZADO (Espelha o backend CouponService.php)
    const calculateDiscount = (subtotal = 0, deliveryFee = 0, serviceFee = 0) => {
        if (!activeCoupon.value) return 0;

        const type = activeCoupon.value.type;
        const val = Number(activeCoupon.value.value || 0);
        const sub = Number(subtotal || 0);
        const delivery = Number(deliveryFee || 0);
        const service = Number(serviceFee || 0);

        if (type === 'percent') {
            return Math.round(((sub * val) / 100) * 100) / 100;
        }

        if (type === 'fixed') {
            return Math.min(val, sub);
        }

        if (type === 'free_delivery') {
            return delivery;
        }

        // Tipo exclusivo do teste PIX / valor fixo cravado
        if (type === 'fixed_total') {
            const totalBruto = sub + delivery + service;
            const diff = totalBruto - val;
            return diff > 0 ? diff : 0;
        }

        return 0;
    };

    // Busca a carteira de cupons do cliente
    const fetchWallet = async () => {
        if (isLoadingWallet.value) return;
        isLoadingWallet.value = true;

        try {
            const response = await axios.get(route('customer.coupons.wallet'));
            wallet.value = response.data.coupons || [];
        } catch (error) {
            console.error('Erro ao buscar carteira de cupons:', error);
            wallet.value = [];
        } finally {
            isLoadingWallet.value = false;
        }
    };

    // Valida e aplica o cupom
    const applyCoupon = async (code, subtotal) => {
        if (!code || isApplyingCoupon.value) return false;
        isApplyingCoupon.value = true;

        try {
            const response = await axios.post(route('customer.coupons.validate'), {
                code: code,
                subtotal: subtotal,
                is_pos: true // 🚀 Permite cupons exclusivos do caixa
            });

            activeCoupon.value = response.data;
            toast.add({ severity: 'success', summary: 'Oba!', detail: response.data.message, life: 3000 });
            return true;

        } catch (error) {
            activeCoupon.value = null;
            const msg = error.response?.data?.message || 'Código inválido ou expirado.';

            const requiresAuth = error.response?.status === 401 || error.response?.status === 403 || msg.toLowerCase().includes('login');

            if (requiresAuth) {
                toast.add({
                    severity: 'warn',
                    summary: 'Benefício Exclusivo',
                    detail: 'Crie uma conta rapidinho (ou faça login) para garantir este desconto!',
                    life: 6000
                });

                window.dispatchEvent(new CustomEvent('open-customer-auth-modal'));
            } else {
                toast.add({ severity: 'error', summary: 'Ops...', detail: msg, life: 4000 });
            }

            return false;
        } finally {
            isApplyingCoupon.value = false;
        }
    };

    // Remove o cupom ativo
    const removeCoupon = () => {
        activeCoupon.value = null;
    };

    return {
        wallet,
        activeCoupon,
        appliedCouponName,
        isLoadingWallet,
        isApplyingCoupon,
        fetchWallet,
        applyCoupon,
        removeCoupon,
        calculateDiscount // 🚀 Exportado para qualquer componente
    };
}
