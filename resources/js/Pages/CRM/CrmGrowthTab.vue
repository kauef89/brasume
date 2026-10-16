<template>
    <div class="flex flex-col gap-6 animate-in fade-in duration-300">

        <!-- 🚀 1. STATUS DE TRÁFEGO PAGO (PIXELS) -->
        <div class="flex items-center justify-between p-4 bg-surface-2 border border-surface-border rounded-2xl shadow-sm">
            <div class="flex flex-col">
                <span class="font-black text-surface-text">Rastreamento de Tráfego Pago</span>
                <span class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">Status dos Pixels no App do Cliente</span>
            </div>
            <div class="flex gap-4">
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border" :class="hasMetaPixel ? 'bg-blue-500/10 border-blue-500/20 text-blue-600' : 'bg-surface-3 border-surface-border text-surface-muted'">
                    <i class="pi pi-facebook"></i>
                    <span class="text-xs font-black uppercase tracking-widest">{{ hasMetaPixel ? 'Meta Ativo' : 'Meta Inativo' }}</span>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border" :class="hasGooglePixel ? 'bg-red-500/10 border-red-500/20 text-red-600' : 'bg-surface-3 border-surface-border text-surface-muted'">
                    <i class="pi pi-google"></i>
                    <span class="text-xs font-black uppercase tracking-widest">{{ hasGooglePixel ? 'Google Ativo' : 'Google Inativo' }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- 🚀 2. GESTÃO DO PROGRAMA DE CASHBACK -->
            <div class="flex flex-col bg-surface-2 border border-surface-border rounded-[2rem] p-5 shadow-sm relative overflow-hidden">
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-primary-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <h3 class="font-black text-lg text-surface-text flex items-center gap-2 mb-4">
                    <i class="pi pi-star text-primary-500"></i> Programa de Cashback
                </h3>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Total Distribuído</span>
                        <span class="font-black text-2xl text-surface-text">{{ $formatCurrency(crmData?.growth?.cashback_given || 0) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Total Resgatado</span>
                        <span class="font-black text-2xl text-success-500">{{ $formatCurrency(crmData?.growth?.cashback_used || 0) }}</span>
                    </div>
                </div>

                <div class="mt-auto p-4 bg-surface-1 rounded-xl border border-surface-border flex items-center justify-between">
                    <span class="text-xs font-bold text-surface-muted">Passivo em Aberto (Não Resgatado)</span>
                    <span class="font-black text-danger-500">{{ $formatCurrency(liability) }}</span>
                </div>
            </div>

            <!-- 🚀 3. MARKETING OFFLINE (O2O FLYERS) -->
            <div class="flex flex-col bg-surface-2 border border-surface-border rounded-[2rem] p-5 shadow-sm relative overflow-hidden">
                <h3 class="font-black text-lg text-surface-text flex items-center gap-2 mb-4">
                    <i class="pi pi-ticket text-purple-500"></i> Campanhas O2O (Flyers)
                </h3>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="flex flex-col p-4 bg-surface-1 rounded-xl border border-surface-border">
                        <span class="text-2xl font-black text-surface-text">{{ crmData?.growth?.flyers_printed || 0 }}</span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted mt-1">Flyers Impressos</span>
                    </div>
                    <div class="flex flex-col p-4 bg-purple-500/10 border border-purple-500/20 rounded-xl">
                        <span class="text-2xl font-black text-purple-600">{{ crmData?.growth?.flyers_converted || 0 }}</span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-purple-700 mt-1">Conversões no App</span>
                    </div>
                </div>

                <div class="mt-auto flex items-center justify-between pt-2">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Taxa de Conversão</span>
                        <span class="font-black text-lg text-surface-text">{{ conversionRate }}%</span>
                    </div>

                    <div class="flex flex-col text-right">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Custo de Aquisição (CAC)</span>
                        <span class="font-black text-lg text-surface-text">{{ $formatCurrency(cac) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    crmData: Object
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
};

// Pixels
const hasMetaPixel = computed(() => !!page.props.companySettings?.meta_pixel_id);
const hasGooglePixel = computed(() => !!page.props.companySettings?.google_pixel_id);

// Cálculos de Cashback
const liability = computed(() => props.crmData?.growth?.cashback_liability || 0);

// Cálculos de O2O
const conversionRate = computed(() => {
    const printed = props.crmData?.growth?.flyers_printed || 0;
    const converted = props.crmData?.growth?.flyers_converted || 0;
    if (printed === 0) return 0;
    return ((converted / printed) * 100).toFixed(1);
});

const cac = computed(() => {
    const converted = props.crmData?.growth?.flyers_converted || 0;
    const costPerFlyer = 0.15; // Custo estimado do papel térmico
    const discountGiven = props.crmData?.growth?.flyer_discounts_given || 0;

    if (converted === 0) return 0;

    const totalCost = ((props.crmData?.growth?.flyers_printed || 0) * costPerFlyer) + discountGiven;
    return totalCost / converted;
});
</script>
