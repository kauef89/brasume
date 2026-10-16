<template>
    <div v-if="isGuest && hasActiveOrder" class="px-4 mt-2 mb-4 animate-in slide-in-from-top-4 duration-500">
        <div class="c-glass-card !bg-amber-500/20 backdrop-blur-2xl p-6 relative overflow-hidden select-none">
            <i class="pi pi-wallet absolute -right-6 -bottom-6 text-9xl text-white/5 rotate-[-15deg] pointer-events-none"></i>

            <div class="relative z-10">
                <h3 class="c-title text-base flex items-center gap-2 text-amber-300">
                    <i class="pi pi-exclamation-triangle"></i>
                    <span>{{ lostCashbackValue > 0 ? 'Você tem cashback reservado!' : 'Acompanhe seu pedido' }}</span>
                </h3>

                <p class="c-text-muted mt-1.5 leading-snug pr-2">
                    {{ lostCashbackValue > 0
                        ? `Seu pedido atual gerou R$ ${$formatCurrency(lostCashbackValue)} em cashback. Conclua seu cadastro para garantir o resgate e acompanhar a entrega em tempo real.`
                        : 'Crie sua conta agora mesmo para liberar o acompanhamento do seu pedido em tempo real.'
                    }}
                </p>

                <!-- Linha do Tempo Estilizada -->
                <div class="flex items-center justify-between w-full max-w-[200px] mx-auto my-6 relative">
                    <div class="absolute left-3 right-3 top-1/2 -translate-y-1/2 h-0.5 bg-white/20 rounded-full"></div>
                    <div v-for="i in 3" :key="i" class="relative z-10 w-9 h-9 rounded-full bg-white/10 backdrop-blur-md flex items-center justify-center shadow-inner">
                        <span class="text-amber-300 font-black text-sm animate-pulse">?</span>
                    </div>
                </div>

                <button
                    @click="$emit('open-register')"
                    class="c-btn-primary !h-12 !text-xs !bg-amber-400 !text-black active:!bg-amber-300"
                >
                    Concluir Cadastro Agora
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    isGuest: Boolean,
    activeOrders: { type: Array, default: () => [] },
    companySettings: { type: Object, default: () => ({}) }
});

defineEmits(['open-register']);

const hasActiveOrder = computed(() => props.activeOrders && props.activeOrders.length > 0);

const lostCashbackValue = computed(() => {
    if (!props.isGuest || !hasActiveOrder.value) return 0;

    const settings = props.companySettings;
    if (!settings || !settings.loyalty_is_active || settings.loyalty_reward_type !== 'cashback') return 0;

    const totalActiveSpend = props.activeOrders.reduce((sum, order) => sum + Number(order.total), 0);
    const multiplier = Number(settings.loyalty_points_multiplier || 1);
    const pointsEarned = Math.floor(totalActiveSpend * multiplier);
    const conversionRate = Number(settings.loyalty_cashback_conversion_rate || 5);

    return (pointsEarned / 100) * conversionRate;
});

const formatMoney = (val) => Number(val || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>
