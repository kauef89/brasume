<template>
    <div class="flex flex-col gap-6 animate-in fade-in duration-300">

        <!-- 🚀 1. OS KPIs (PANORAMA GERAL) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- Card: Média Geral -->
            <div class="bg-surface-2 p-5 rounded-[1.5rem] border border-surface-border shadow-sm flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center font-black text-2xl" :class="averageColorClass">
                    {{ averageRating }}
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Média Geral</span>
                    <span class="font-black text-sm text-surface-text">{{ totalRatings }} avaliações</span>
                </div>
            </div>

            <!-- Card: Promotores (5 Estrelas) -->
            <div class="bg-surface-2 p-5 rounded-[1.5rem] border border-success-500/20 shadow-sm flex items-center gap-4 relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-success-500/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="w-14 h-14 rounded-2xl bg-success-500/10 text-success-500 flex items-center justify-center text-2xl">
                    <i class="pi pi-heart-fill"></i>
                </div>
                <div class="flex flex-col relative z-10">
                    <span class="text-[10px] font-black uppercase tracking-widest text-success-600">Promotores</span>
                    <span class="font-black text-lg text-surface-text">{{ fiveStarsCount }} clientes</span>
                    <span class="text-xs font-bold text-surface-muted">{{ promoterPercentage }}% do total</span>
                </div>
            </div>

            <!-- Card: Críticos (Abaixo de 4 Estrelas) -->
            <div class="bg-surface-2 p-5 rounded-[1.5rem] border border-warning-500/30 shadow-sm flex items-center gap-4 relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-warning-500/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="w-14 h-14 rounded-2xl bg-warning-500/10 text-warning-600 flex items-center justify-center text-2xl">
                    <i class="pi fa-exclamation-triangle"></i>
                </div>
                <div class="flex flex-col relative z-10">
                    <span class="text-[10px] font-black uppercase tracking-widest text-warning-700">Atenção Necessária</span>
                    <span class="font-black text-lg text-surface-text">{{ criticalCount }} pedidos</span>
                    <span class="text-xs font-bold text-surface-muted">Ações de retenção sugeridas</span>
                </div>
            </div>
        </div>

        <!-- 🚀 2. LISTA DE FEEDBACKS (Ouvindo o Cliente) -->
        <div class="flex flex-col bg-surface-2 border border-surface-border rounded-[2rem] overflow-hidden shadow-sm">
            <div class="p-5 border-b border-surface-border flex items-center justify-between bg-surface-1/50">
                <h3 class="font-black text-base text-surface-text">Últimos Feedbacks</h3>

                <!-- Filtro Simples -->
                <div class="flex gap-1 bg-surface-3 p-1 rounded-xl">
                    <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-surface-1 shadow-sm text-surface-text' : 'text-surface-muted'" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-colors outline-none cursor-pointer border-none">
                        Todos
                    </button>
                    <button @click="filter = 'critical'" :class="filter === 'critical' ? 'bg-warning-500 text-white shadow-sm' : 'text-surface-muted'" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-colors outline-none cursor-pointer border-none">
                        Críticos
                    </button>
                </div>
            </div>

            <div class="flex flex-col p-2 divide-y divide-surface-border/50">

                <!-- Empty State -->
                <div v-if="filteredFeedbacks.length === 0" class="p-10 text-center flex flex-col items-center opacity-60">
                    <i class="pi pi-comments text-4xl text-surface-muted mb-3"></i>
                    <p class="font-bold text-surface-text text-sm">Nenhum feedback encontrado.</p>
                </div>

                <!-- Linha de Feedback -->
                <div v-for="feedback in filteredFeedbacks" :key="feedback.id" class="p-4 flex flex-col md:flex-row gap-4 hover:bg-surface-1/30 transition-colors">

                    <!-- Bloco da Nota -->
                    <div class="flex flex-col items-center justify-center shrink-0 w-20">
                        <span class="text-2xl font-black" :class="getRatingColor(feedback.rating)">{{ feedback.rating }}.0</span>
                        <div class="flex gap-0.5 text-[8px] mt-1" :class="getRatingColor(feedback.rating)">
                            <i v-for="i in 5" :key="i" class="pi" :class="i <= feedback.rating ? 'pi-star-fill' : 'pi-star opacity-30'"></i>
                        </div>
                    </div>

                    <!-- Bloco de Conteúdo -->
                    <div class="flex-1 min-w-0 flex flex-col justify-center">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-black text-sm text-surface-text">{{ feedback.customer_name || 'Cliente Anônimo' }}</span>
                            <span class="text-[10px] font-bold text-surface-muted">• Pedido #{{ $formatNumber(feedback.order_id) }}</span>
                            <span class="text-[10px] font-medium text-surface-muted">• {{ formatDate(feedback.created_at) }}</span>
                        </div>

                        <p v-if="feedback.comment" class="text-xs font-medium text-surface-text/90 italic border-l-2 border-surface-border pl-3 my-2 leading-relaxed">
                            "{{ feedback.comment }}"
                        </p>

                        <!-- Tags Justificativas (Sabor, Entrega, etc.) -->
                        <div v-if="feedback.tags && feedback.tags.length > 0" class="flex flex-wrap gap-1.5 mt-2">
                            <span v-for="tag in feedback.tags" :key="tag" class="px-2 py-0.5 bg-surface-3 text-surface-text rounded-md text-[9px] font-black uppercase tracking-wider">
                                {{ tag }}
                            </span>
                        </div>
                    </div>

                    <!-- Ação Rápida (Entrar em Contato) -->
                    <div class="shrink-0 flex items-center justify-end" v-if="feedback.customer_phone">
                        <a :href="`https://wa.me/55${cleanPhone(feedback.customer_phone)}`" target="_blank" class="w-10 h-10 bg-[#25D366]/10 text-[#25D366] hover:bg-[#25D366] hover:text-white rounded-full flex items-center justify-center transition-colors no-underline">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                    </div>
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
        default: () => ({ ratings: [] })
    }
});

const filter = ref('all');

// --- Processamento dos KPIs ---
const allRatings = computed(() => props.crmData?.ratings || []);
const totalRatings = computed(() => allRatings.value.length);

const averageRating = computed(() => {
    if (totalRatings.value === 0) return '0.0';
    const sum = allRatings.value.reduce((acc, curr) => acc + curr.rating, 0);
    return (sum / totalRatings.value).toFixed(1);
});

const fiveStarsCount = computed(() => allRatings.value.filter(r => r.rating === 5).length);
const criticalCount = computed(() => allRatings.value.filter(r => r.rating <= 3).length);

const promoterPercentage = computed(() => {
    if (totalRatings.value === 0) return 0;
    return Math.round((fiveStarsCount.value / totalRatings.value) * 100);
});

const averageColorClass = computed(() => {
    const avg = parseFloat(averageRating.value);
    if (avg >= 4.5) return 'bg-success-500/10 text-success-600';
    if (avg >= 3.5) return 'bg-warning-500/10 text-warning-600';
    return 'bg-danger-500/10 text-danger-600';
});

const getRatingColor = (rating) => {
    if (rating >= 4) return 'text-success-500';
    if (rating === 3) return 'text-warning-500';
    return 'text-danger-500';
};

// --- Filtro de Lista ---
const filteredFeedbacks = computed(() => {
    let list = [...allRatings.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    if (filter.value === 'critical') {
        list = list.filter(r => r.rating <= 3);
    }
    return list;
});

// --- Helpers ---
const formatDate = (isoStr) => {
    if (!isoStr) return '';
    return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(isoStr));
};

const cleanPhone = (phone) => {
    if (!phone) return '';
    return phone.replace(/\D/g, '');
};
</script>
