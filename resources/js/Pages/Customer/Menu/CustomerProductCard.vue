<template>
    <button
        @click="$emit('click', product)"
        :disabled="isBlocked"
        class="c-glass-card-interactive flex items-stretch p-3 md:p-4 gap-4 w-full text-left relative transition-all duration-200"
        :class="isBlocked ? 'grayscale opacity-50 cursor-not-allowed' : ''"
    >
        <!-- 🖼️ IMAGEM -->
        <div class="relative bg-white/5 flex items-center justify-center shrink-0 overflow-hidden w-24 h-24 md:w-28 md:h-28 rounded-2xl">
            <img
                v-if="finalImageUrl"
                :src="finalImageUrl"
                class="absolute inset-0 w-full h-full object-cover"
                loading="lazy"
            />
            <i v-else class="pi pi-image text-white/20 text-3xl"></i>

            <!-- Badges de Disponibilidade / Promo -->


            <div v-if="product?.promo_price > 0" class="absolute top-2 left-2 c-badge bg-warning-500/90 text-white font-black text-[9px] uppercase tracking-widest">
                Promo
            </div>
        </div>

        <!-- 📝 INFOS DO PRODUTO -->
        <div class="flex flex-col flex-1 min-w-0 w-full justify-center py-0.5">
            <h3 class="c-text-main w-full line-clamp-2">
                {{ product?.name }}
            </h3>

            <p
                v-if="displayDescription"
                class="c-text-muted line-clamp-2 mt-1"
            >
                {{ displayDescription }}
            </p>

            <div class="mt-auto flex items-end justify-between gap-2 w-full pt-2">
                <div class="flex flex-col">
                    <span v-if="product?.promo_price > 0" class="text-[10px] text-white/40 line-through font-medium">
                        {{ $formatCurrency(originalTotal ) }}
                    </span>
                    <span class="font-black text-lg text-white/80 tracking-tight">
                        {{ $formatCurrency(finalTotal ) }}
                    </span>
                </div>
            </div>
        </div>
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ product: Object });
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
    if (prodPath) return prodPath.startsWith('http') ? prodPath : `/media/file?path=${prodPath}`;
    const categories = page.props.initialCategories || [];
    const category = categories.find(c => c.id === props.product.category_id);
    if (category?.image_path) return category.image_path.startsWith('http') ? category.image_path : `/media/file?path=${category.image_path}`;
    return null;
});

const selections = computed(() => props.product?.favorite_selections || props.product?.cartItem?.structuredModifiers || []);

const displayDescription = computed(() => {
    const list = selections.value;
    if (list && list.length > 0) {
        const names = list.map(m => m.name).filter(Boolean);
        if (names.length === 1) return names[0];
        if (names.length > 1) return names.join(', ').replace(/, ([^,]*)$/, ' e $1');
    }
    return props.product?.description || '';
});

const extraPrice = computed(() => selections.value.reduce((sum, mod) => {
    const modPrice = typeof mod.price === 'string' ? parseFloat(mod.price.replace(',', '.')) : (mod.price || 0);
    return sum + (modPrice * parseInt(mod.qty || mod.quantity || 1));
}, 0));

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
