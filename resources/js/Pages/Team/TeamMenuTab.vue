<template>
    <div
        class="flex-1 flex flex-col min-h-0 p-4 pb-0 gap-4 animate-in fade-in duration-300"
        :style="{ paddingTop: 'var(--app-header-height, 7rem)' }"
    >
        <CategoryMenu
            v-if="categoryLayout === 'horizontal' || categoryLayout === 'vertical'"
            :categories="visibleMenuCategories"
            :selected="activeMenuCategory"
            :layout="categoryLayout"
            @update:selected="$emit('category-select', $event)"
            class="shrink-0"
        />

        <div ref="scrollContainerRef" class="flex-1 overflow-y-auto hide-scrollbar -mx-2 px-2">
            <!-- Subcategorias do nível atual -->
            <CategoryGrid
                v-if="shouldShowCategoryGrid"
                :categories="visibleGridCategories"
                :layout="mobileCategoryLayout"
                @category-click="$emit('category-select', $event)"
                :class="shouldShowProductGrid ? 'mb-6' : ''"
            />

            <!-- O divisor "Produtos Soltos" SÓ vai aparecer se houver subcategorias E produtos diretos na mesma pasta -->
            <div v-if="shouldShowCategoryGrid && shouldShowProductGrid" class="flex items-center gap-4 mb-4 opacity-50">
                <div class="h-px bg-surface-border flex-1"></div>
                <span class="micro-label text-[10px] font-bold uppercase tracking-widest">Produtos Soltos</span>
                <div class="h-px bg-surface-border flex-1"></div>
            </div>

            <!-- Grid de produtos da categoria -->
            <ProductGrid
                v-if="shouldShowProductGrid"
                :products="filteredProducts"
                :layout="productCardLayout"
                @product-click="$emit('product-click', $event)"
            />

            <div v-if="!shouldShowProductGrid && !shouldShowCategoryGrid" class="flex flex-col items-center justify-center py-20 text-surface-muted opacity-60">
                <i class="pi pi-search text-5xl mb-3 text-primary-500/40"></i>
                <h3 class="text-lg font-bold text-center">Nenhum produto encontrado</h3>
                <p class="text-xs mt-1 text-center max-w-xs">Não localizamos itens para a sua busca ou não há produtos disponíveis.</p>
            </div>

            <div class="h-32 shrink-0 w-full pointer-events-none"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import CategoryMenu from "@/Pages/Pos/Components/Menu/CategoryMenu.vue";
import ProductGrid from "@/Pages/Pos/Components/Menu/ProductGrid.vue";
import CategoryGrid from "@/Pages/Pos/Components/Menu/CategoryGrid.vue";

const props = defineProps({
    isActive: { type: Boolean, default: true },
    categoryLayout: String,
    mobileCategoryLayout: String,
    productCardLayout: String,
    visibleMenuCategories: Array,
    activeMenuCategory: [String, Number],
    visibleGridCategories: Array,
    shouldShowCategoryGrid: Boolean,
    shouldShowProductGrid: Boolean,
    filteredProducts: Array
});

defineEmits(['product-click', 'category-select']);

const scrollContainerRef = ref(null);

watch(() => props.activeMenuCategory, () => {
    if (scrollContainerRef.value) {
        scrollContainerRef.value.scrollTop = 0;
    }
});
</script>
