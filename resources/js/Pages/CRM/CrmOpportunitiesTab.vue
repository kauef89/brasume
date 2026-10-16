<template>
    <div class="flex flex-col gap-6 animate-in fade-in duration-300">

        <div class="flex items-center justify-between bg-surface-2 p-5 rounded-[1.5rem] border border-surface-border shadow-sm">
            <div class="flex flex-col">
                <h3 class="font-black text-lg text-surface-text">Garimpo de Oportunidades</h3>
                <p class="text-xs font-medium text-surface-muted">Clientes que precisam de um "empurrãozinho" para comprar hoje.</p>
            </div>

            <div class="flex gap-2">
                <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-primary-500 text-white' : 'bg-surface-3 text-surface-muted'" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors outline-none border-none cursor-pointer">
                    Todos
                </button>
                <button @click="filter = 'birthday'" :class="filter === 'birthday' ? 'bg-purple-500 text-white' : 'bg-surface-3 text-surface-muted'" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors outline-none border-none cursor-pointer">
                    <i class="pi pi-gift mr-1"></i> Aniversários
                </button>
                <button @click="filter = 'churn'" :class="filter === 'churn' ? 'bg-warning-500 text-white' : 'bg-surface-3 text-surface-muted'" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors outline-none border-none cursor-pointer">
                    <i class="pi pi-history mr-1"></i> Risco de Fuga
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Empty State -->
            <div v-if="filteredOpportunities.length === 0" class="col-span-full p-10 flex flex-col items-center justify-center border-2 border-dashed border-surface-border rounded-[2rem] opacity-60">
                <i class="pi pi-check-circle text-4xl text-success-500 mb-3"></i>
                <span class="font-black text-surface-text">Tudo tranquilo por aqui!</span>
                <span class="text-sm font-medium text-surface-muted">Nenhuma oportunidade pendente no filtro atual.</span>
            </div>

            <!-- Cards de Oportunidade -->
            <div v-for="opp in filteredOpportunities" :key="opp.id" class="flex flex-col bg-surface-2 border border-surface-border rounded-[1.5rem] overflow-hidden shadow-sm hover:border-primary-500/30 transition-colors">

                <div class="p-4 border-b border-surface-border/50 flex items-center justify-between" :class="opp.reason === 'birthday' ? 'bg-purple-500/10' : 'bg-warning-500/10'">
                    <span class="text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5" :class="opp.reason === 'birthday' ? 'text-purple-600' : 'text-warning-700'">
                        <i class="pi" :class="opp.reason === 'birthday' ? 'pi-gift' : 'pi pi-clock'"></i>
                        {{ opp.reason === 'birthday' ? 'Aniversariante do Mês' : 'Sumido há dias' }}
                    </span>
                </div>

                <div class="p-4 flex flex-col gap-1">
                    <span class="font-black text-base text-surface-text">{{ opp.name }}</span>
                    <span class="text-xs font-medium text-surface-muted flex items-center gap-1.5">
                        <i class="pi fa-cart-shopping text-[10px]"></i> Última compra: há {{ opp.last_order_days }} dias
                    </span>
                </div>

                <div class="p-4 pt-0 mt-auto flex gap-2">
                    <a :href="`https://wa.me/55${cleanPhone(opp.phone)}?text=${encodeURIComponent(getSuggestedMessage(opp))}`" target="_blank" class="flex-1 bg-[#25D366]/10 text-[#25D366] hover:bg-[#25D366] hover:text-white h-10 rounded-xl flex items-center justify-center gap-2 text-xs font-black uppercase tracking-widest transition-colors no-underline">
                        <i class="fa-brands fa-whatsapp"></i> Abordar
                    </a>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    crmData: {
        type: Object,
        default: () => ({ opportunities: [] })
    }
});

const filter = ref('all');

const filteredOpportunities = computed(() => {
    let list = props.crmData?.opportunities || [];
    if (filter.value !== 'all') {
        list = list.filter(o => o.reason === filter.value);
    }
    return list;
});

const cleanPhone = (phone) => {
    if (!phone) return '';
    return phone.replace(/\D/g, '');
};

const getSuggestedMessage = (opp) => {
    const firstName = opp.name.split(' ')[0];
    if (opp.reason === 'birthday') {
        return `Olá ${firstName}! Vimos que este é o mês do seu aniversário 🎉 Tem presente te esperando no nosso app, vem resgatar!`;
    }
    return `Oi ${firstName}! Faz um tempinho que você não pede com a gente. Preparamos algo especial para matar a saudade, dá uma olhadinha no nosso cardápio! 🍔`;
};
</script>
