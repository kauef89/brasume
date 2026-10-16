<template>
    <div v-if="rewardProducts.length > 0 && isLoyaltyActive" class="flex flex-col gap-3 mt-2">
        <div class="flex items-center justify-between pl-2 pr-4">
            <h3 class="font-black text-sm uppercase tracking-widest text-surface-muted flex items-center gap-2">
                <i class="pi pi-gift text-primary-500"></i> Resgate seus Pontos
            </h3>
            <span class="text-[10px] font-black text-primary-600 bg-primary-500/10 px-2 py-1 rounded-lg uppercase tracking-widest border border-primary-500/20">
                Saldo: {{ customerPoints }} pts
            </span>
        </div>

        <div class="flex overflow-x-auto gap-4 pb-4 px-2 hide-scrollbar snap-x">
            <!-- 🚀 Loop totalmente limpo delegando o design ao Card -->
            <LoyaltyRewardCard
                v-for="product in rewardProducts"
                :key="'reward_' + product.id"
                :product="product"
                :can-redeem="canRedeem(product)"
                @redeem="handleRedeem"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import LoyaltyRewardCard from '@/Pages/Customer/Profile/LoyaltyRewardCard.vue'; // 🚀 Import do novo componente

const props = defineProps({
    products: { type: Array, required: true },
    customer: { type: Object, default: null },
    cart: { type: Array, required: true }
});

const emit = defineEmits(['redeem-reward']);
const page = usePage();
const toast = useToast();
const settings = computed(() => page.props.companySettings || {});

const isLoyaltyActive = computed(() => settings.value.loyalty_is_active && settings.value.loyalty_reward_type === 'product');

const rewardProducts = computed(() => {
    return props.products.filter(p => p.loyalty_reward === true && p.status === 'available');
});

const customerPoints = computed(() => props.customer?.loyalty_points || 0);

const pointsInCart = computed(() => {
    return props.cart.reduce((sum, item) => {
        return sum + (item.is_reward ? (item.points_cost * item.qty) : 0);
    }, 0);
});

const canRedeem = (product) => {
    const cost = Number(product.loyalty_points_cost || 0);
    return (customerPoints.value - pointsInCart.value) >= cost;
};

const handleRedeem = (product) => {
    if (canRedeem(product)) {
        emit('redeem-reward', product);
    } else {
        toast.add({ severity: 'warn', summary: 'Saldo Insuficiente', detail: 'Você não tem pontos suficientes para adicionar mais este item.', life: 3000 });
    }
};
</script>
