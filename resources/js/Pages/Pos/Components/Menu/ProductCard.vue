<template>
    <button
        @click="$emit('click', product)"
        :disabled="isBlocked"
        class="relative overflow-hidden transition-all duration-300 outline-none text-left group select-none"
        :class="[
            layout === 'list'
                ? 'w-full flex items-stretch p-3 rounded-[1.5rem] bg-surface-1 hover:bg-surface-2 gap-4'
                : 'w-full aspect-[3/4] rounded-2xl flex flex-col justify-between p-3.5 bg-surface-2 shadow-sm hover:shadow-xl',
            isBlocked ? 'grayscale cursor-not-allowed' : 'cursor-pointer'
        ]"
    >
        <template v-if="layout !== 'list'">
            <img
                v-if="finalImageUrl"
                :src="finalImageUrl"
                class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 z-0"
                alt=""
            />
            <div v-else class="absolute inset-0 w-full h-full bg-surface-3 flex items-center justify-center text-surface-muted opacity-30 z-0">
                <i class="pi pi-image text-4xl"></i>
            </div>

            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/65 to-transparent z-[1] pointer-events-none"></div>

            <div v-if="product?.promo_price > 0" class="relative z-10 flex items-start justify-end gap-2 w-full">
                <span class="bg-warning-500 text-white text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md shadow-sm">
                    Promo
                </span>
            </div>

            <div class="relative z-10 mt-auto flex flex-col gap-1 w-full">
                <!-- Nome completo sem truncamento -->
                <h3 class="font-black text-sm text-white leading-tight drop-shadow-sm break-words">
                    {{ product?.name }}
                </h3>

                <!-- 🌟 EXIBIÇÃO DE MODIFICADORES / ANOTAÇÕES NO GRID DO POS -->
                <p v-if="displayDescription" class="text-[11px] font-medium text-white/80 line-clamp-2 leading-tight drop-shadow-xs mb-1">
                    {{ displayDescription }}
                </p>

                <div class="flex items-end justify-between gap-2 pt-1">
                    <div class="flex flex-col">
                        <span v-if="product?.promo_price > 0" class="text-[10px] text-white/70 line-through font-medium leading-none">
                            {{ $formatCurrency(originalTotal) }}
                        </span>
                        <span class="font-black text-white text-base leading-tight drop-shadow-sm">
                            {{ $formatCurrency(finalTotal) }}
                        </span>
                    </div>

                    <div v-if="!isBlocked" class="w-8 h-8 rounded-full bg-white/10 backdrop-blur-md text-white flex items-center justify-center group-hover:bg-primary-500 transition-colors shadow-sm shrink-0 active:scale-90">
                        <i class="pi pi-plus text-xs font-black"></i>
                    </div>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="relative bg-surface-3 flex items-center justify-center shrink-0 overflow-hidden w-20 h-20 md:w-24 md:h-24 rounded-xl">
                <img v-if="finalImageUrl" :src="finalImageUrl" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="" />
                <i v-else class="pi pi-image text-surface-muted opacity-20 text-2xl"></i>

                <div v-if="product?.promo_price > 0" class="absolute top-1.5 right-1.5 bg-warning-500 text-white text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded-md z-10">
                    Promo
                </div>
            </div>

            <div class="flex flex-col flex-1 min-w-0 w-full justify-center py-1">
                <!-- Nome completo sem truncamento -->
                <h3 class="font-medium text-sm text-surface-text w-full leading-snug break-words">
                    {{ product?.name }}
                </h3>

                <p v-if="displayDescription" class="text-xs font-medium line-clamp-2 leading-snug w-full mt-0.5" :class="selections.length > 0 ? 'text-surface-text' : 'text-surface-muted'">
                    {{ displayDescription }}
                </p>

                <div class="mt-auto flex items-end justify-between gap-2 w-full pt-1">
                    <div class="flex flex-col">
                        <span v-if="product?.promo_price > 0" class="text-[10px] text-surface-muted line-through font-medium">
                            {{ $formatCurrency(originalTotal) }}
                        </span>
                        <span class="font-black text-primary-700 dark:text-primary-300 text-base">
                            {{ $formatCurrency(finalTotal) }}
                        </span>
                    </div>

                    <div v-if="!isBlocked" class="w-8 h-8 rounded-full bg-primary-500/10 flex items-center justify-center shrink-0 group-hover:bg-primary-500 group-hover:text-white transition-colors">
                        <i class="pi pi-plus text-xs font-medium"></i>
                    </div>
                </div>
            </div>
        </template>
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    product: Object,
    layout: { type: String, default: 'grid' }
});

defineEmits(['click']);

const page = usePage();

const isPaused = computed(() => props.product?.status === 'paused');

const isOutOfStock = computed(() => {
    if (props.product?.status === 'out_of_stock' || props.product?.status === 'unavailable') return true;
    if (props.product?.track_inventory && parseFloat(props.product?.current_stock) <= 0) return true;
    return false;
});

const isBlocked = computed(() => isPaused.value || isOutOfStock.value);

const finalImageUrl = computed(() => {
    if (!props.product) return null;

    const prodPath = props.product.image || props.product.image_path;
    if (prodPath) {
        const ts = props.product.updated_at ? new Date(props.product.updated_at).getTime() : '';
        return prodPath.startsWith('http') ? prodPath : `/media/file?path=${prodPath}&v=${ts}`;
    }

    const categories = page.props.initialCategories || page.props.categories || [];
    const category = categories.find(c => c.id === props.product.category_id);

    if (category) {
        const catPath = category.image_path || category.image;
        if (catPath) {
            const ts = category.updated_at ? new Date(category.updated_at).getTime() : '';
            return catPath.startsWith('http') ? catPath : `/media/file?path=${catPath}&v=${ts}`;
        }
    }

    return null;
});

const selections = computed(() => {
    if (props.product?.favorite_selections && Array.isArray(props.product.favorite_selections)) {
        return props.product.favorite_selections;
    }
    if (props.product?.cartItem?.structuredModifiers && Array.isArray(props.product.cartItem.structuredModifiers)) {
        return props.product.cartItem.structuredModifiers;
    }
    return [];
});

const displayDescription = computed(() => {
    // 🍔 Se for combo com subitens:
    const comboList = props.product?.cartItem?.comboItems || props.product?.combo_items;
    if (comboList && Array.isArray(comboList) && comboList.length > 0) {
        return comboList.map(c => {
            const mod = c.modifiersText ? ` (${c.modifiersText})` : "";
            const qty = Number(c.qty || c.quantity || 1);
            return (qty > 1 ? `${qty}x ` : "") + (c.name || c.product_name) + mod;
        }).join(" + ");
    }

    const list = selections.value;
    const parts = [];

    if (list && list.length > 0) {
        const names = list.map(m => {
            const qty = Number(m.qty || m.quantity || 1);
            return qty > 1 ? `${qty}x ${m.name}` : m.name;
        }).filter(Boolean);

        if (names.length === 1) parts.push(names[0]);
        else if (names.length > 1) parts.push(names.join(', ').replace(/, ([^,]*)$/, ' e $1'));
    }

    if (props.product?.notes && String(props.product.notes).trim() !== '') {
        parts.push(`Obs: ${props.product.notes.trim()}`);
    }

    if (parts.length > 0) {
        return parts.join(' • ');
    }

    return props.product?.description || '';
});

const extraPrice = computed(() => {
    let extra = 0;
    selections.value.forEach(mod => {
        const modPrice = typeof mod.price === 'string' ? parseFloat(mod.price.replace(',', '.')) : (mod.price || 0);
        extra += (modPrice * parseInt(mod.qty || mod.quantity || 1));
    });
    return extra;
});

const originalTotal = computed(() => {
    const rawPrice = props.product?.price ?? props.product?.dine_in_price ?? 0;
    const base = typeof rawPrice === 'string' ? parseFloat(rawPrice.replace(',', '.')) : Number(rawPrice || 0);
    return base + extraPrice.value;
});

const finalTotal = computed(() => {
    const rawBase = props.product?.promo_price > 0 ? props.product.promo_price : (props.product?.price ?? props.product?.dine_in_price ?? 0);
    const base = typeof rawBase === 'string' ? parseFloat(rawBase.replace(',', '.')) : Number(rawBase || 0);
    return base + extraPrice.value;
});
</script>
