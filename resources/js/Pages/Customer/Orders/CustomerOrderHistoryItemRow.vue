<template>
    <div class="flex flex-col gap-1 py-2.5">
        <!-- Linha Principal do Produto -->
        <div class="flex items-start justify-between gap-3">
            <div class="flex flex-col gap-0.5">
                <div class="flex gap-1 font-medium text-sm text-surface-text">
                    <span class="shrink-0">{{ itemQty }} ×</span>
                    <span>{{ cleanItemName }}</span>
                </div>
                <!-- 🚀 NOVO: Badge se for um resgate de pontos (Prêmio) -->
                <div v-if="isReward" class="pl-5 pt-0.5">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-purple-500/20 text-purple-400">
                        <i class="pi pi-star-fill text-[8px]"></i> Prêmio Resgatado
                    </span>
                </div>
            </div>
            <div class="flex flex-col items-end">
                <span class="text-sm text-surface-text shrink-0" :class="{ 'line-through opacity-50': isReward && itemTotal == 0 }">
                    {{ $formatCurrency(itemTotal) }}
                </span>
                <span v-if="isReward && itemTotal == 0" class="text-xs font-black text-emerald-400">
                    GRÁTIS
                </span>
            </div>
        </div>

        <!-- Lista de Modificadores/Adicionais -->
        <div v-if="cleanModifiers && cleanModifiers.length > 0" class="flex flex-col pl-6 mt-0.5 gap-0.5">
            <span v-for="(mod, idx) in cleanModifiers" :key="idx" class="text-xs font-medium text-surface-muted">
                → {{ mod.quantity > 1 ? mod.quantity + ' × ' : '' }}{{ mod.name }}
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    item: { type: Object, default: () => ({}) }
});

const itemQty = computed(() => props.item?.quantity || 1);

// 🚀 Higieniza o título do produto removendo marcadores internos de viagem e o emoji 🎁
const cleanItemName = computed(() => {
    const raw = props.item?.product_name || props.item?.name || 'Item';
    return raw
        .replace(/\s*[\(\[](Viagem|Para Levar|Takeaway)[\)\]]/gi, '')
        .replace('🎁 [Prêmio]', '')
        .trim();
});

const itemTotal = computed(() => props.item?.total_price || props.item?.price || 0);

// 🚀 Checa se o item foi um prêmio do programa de fidelidade
const isReward = computed(() => {
    return props.item?.is_reward === true
        || props.item?.loyalty_reward_type === 'product'
        || String(props.item?.product_name || '').includes('[Prêmio]');
});

// 🚀 Oculta modificadores fantasmas criados exclusivamente para controle de embalagem
const cleanModifiers = computed(() => {
    if (!props.item?.modifiers) return [];
    return props.item.modifiers.filter(m => {
        const name = (m.name || '').toLowerCase();
        return !name.includes('viagem') && !name.includes('para levar');
    });
});
</script>
