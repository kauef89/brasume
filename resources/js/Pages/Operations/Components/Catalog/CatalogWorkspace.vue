<template>
    <div class="w-full h-full flex flex-col min-h-0 bg-surface-2 rounded-2xl relative">

        <!-- CABEÇALHO UNIFICADO DE NAVEGAÇÃO -->
            <div v-if="!isManagerActive" class="w-full flex items-center gap-3 shrink-0 px-6 py-3.5 min-h-[56px] z-10">

                <!-- 1. MODO BUSCA -->
                <div v-if="searchQuery" class="flex items-center gap-2 text-xs font-bold text-surface-muted min-h-[32px]">
                    <i class="pi pi-search text-xs"></i>
                    <span>Buscando por: <strong class="text-surface-text">"{{ searchQuery }}"</strong></span>
                </div>

                <!-- 2. MODO FAVORITOS -->
                <div v-else-if="isFavoritesActive" class="shrink-0 flex items-center min-h-[32px]">
                    <span class="text-xl font-black flex items-center gap-2">
                        <i class="pi pi-star-fill text-amber-500"></i> O de Sempre
                    </span>
                </div>

                <!-- 3. MODO NORMAL (BREADCRUMB COMPLETO) -->
                <div v-else class="shrink-0 flex items-center min-h-[32px]">
                    <Breadcrumb
                        :items="catalogBreadcrumbs.items"
                        :active-item="catalogBreadcrumbs.active"
                        @navigate="$emit('navigate', $event)"
                        size="sm"
                    />
                </div>
            </div>

            <!-- CABEÇALHO ORGANIZADOR DE CARDÁPIO -->
            <div v-else class="w-full flex items-center justify-between shrink-0 px-6 py-3.5 border-b border-surface-border/50 bg-surface-2 min-h-[56px] z-10">
                <button @click="$emit('close-manager')" class="flex items-center gap-2 text-surface-muted hover:text-primary-smart transition-colors font-black uppercase tracking-widest text-[11px] outline-none cursor-pointer border-none bg-transparent">
                    <i class="pi pi-arrow-left"></i> Voltar ao Cardápio
                </button>
                <span class="text-sm font-black text-primary-500 uppercase tracking-widest flex items-center gap-2">
                    <i class="pi pi-sitemap"></i> Organizar Cardápio
                </span>
            </div>


        <div class="scroll-container flex-1 min-h-0 overflow-y-auto hide-scrollbar p-6">

            <!-- TELA 1: FAVORITOS -->
            <div v-show="isFavoritesActive && favorites.length > 0" class="pb-8">
                <CustomerFavoritesGrid :favorites="favorites" @favorite-click="$emit('favorite-click', $event)"/>
            </div>

            <!-- TELA 2: ORGANIZADOR DE CATEGORIAS INLINE -->
            <div v-show="isManagerActive" class="pb-8 flex flex-col w-full min-w-0">
                <div class="flex items-center justify-between mb-6 shrink-0 bg-surface-1 p-4 rounded-xl border border-surface-border shadow-sm">
                    <span class="text-sm font-medium text-surface-text flex items-center gap-2">
                        <i class="pi pi-braille text-surface-muted"></i> Arraste para encadear ou reordenar
                    </span>

                    <div class="flex items-center gap-3">
                        <button
                            @click="$emit('undo-category-change')"
                            :disabled="historyStack.length === 0"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all outline-none cursor-pointer"
                            :class="historyStack.length > 0 ? 'bg-surface-2 text-surface-text hover:bg-surface-3 hover:text-primary-smart border border-surface-border shadow-xs' : 'opacity-40 cursor-not-allowed text-surface-muted border border-transparent'"
                            title="Desfazer alteração (Ctrl + Z)"
                        >
                            <i class="pi pi-undo text-xs"></i>
                            <span>Desfazer</span>
                            <span v-if="historyStack.length > 0" class="ml-1 text-[10px] bg-primary-500/10 text-primary-smart px-1.5 py-0.5 rounded-md font-bold">
                                {{ historyStack.length }}
                            </span>
                        </button>

                        <span class="text-[10px] bg-success-500/10 text-success-smart px-2.5 py-1.5 rounded-md font-black uppercase tracking-widest">
                            Salvo Auto
                        </span>
                    </div>
                </div>

                <CategoryCloudNode :model-value="localCategories" :search-query="searchQuery" @save="$emit('save-category')" @update:model-value="$emit('update:localCategories', $event)"/>
            </div>

            <!-- TELA 3: CARDÁPIO NORMAL -->
            <div v-show="!isFavoritesActive && !isManagerActive">
                <CategoryGrid
                    v-if="shouldShowCategoryGrid"
                    :categories="visibleGridCategories"
                    :class="shouldShowProductGrid ? 'mb-4' : ''"
                    :layout="categoryLayout"
                    @category-click="$emit('category-click', $event)"
                />

                <div v-if="shouldShowCategoryGrid && shouldShowProductGrid" class="flex items-center gap-4 mb-4 opacity-30 mt-2">
                    <div class="h-px bg-surface-border flex-1"></div>
                    <span class="micro-label text-[10px] font-bold uppercase tracking-widest text-surface-muted">Produtos Soltos</span>
                    <div class="h-px bg-surface-border flex-1"></div>
                </div>

                <ProductGrid
                    v-if="shouldShowProductGrid"
                    :layout="productCardLayout"
                    :products="filteredProducts"
                    @product-click="$emit('product-click', $event)"
                />
            </div>

        </div>
    </div>
</template>

<script setup>
import Breadcrumb from "@/Components/UI/Breadcrumb.vue";
import CustomerFavoritesGrid from '@/Pages/Operations/Components/Catalog/CustomerFavoritesGrid.vue';
import CategoryCloudNode from "@/Pages/Pos/Components/Menu/CategoryCloudNode.vue";
import CategoryGrid from "@/Pages/Pos/Components/Menu/CategoryGrid.vue";
import ProductGrid from "@/Pages/Pos/Components/Menu/ProductGrid.vue";

defineProps({
    isTeamMode: { type: Boolean, default: false },
    searchQuery: String,
    categoryHierarchy: String,
    selectedCategory: [String, Number],
    isFavoritesActive: Boolean,
    isManagerActive: Boolean,
    catalogBreadcrumbs: Object,
    favorites: Array,
    historyStack: Array,
    localCategories: Array,
    shouldShowCategoryGrid: Boolean,
    visibleGridCategories: Array,
    categoryLayout: String,
    shouldShowProductGrid: Boolean,
    filteredProducts: Array,
    productCardLayout: String
});

defineEmits([
    'navigate', 'close-manager', 'undo-category-change', 'update:localCategories',
    'save-category', 'favorite-click', 'category-click', 'product-click'
]);
</script>
