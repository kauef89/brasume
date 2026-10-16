<template>
    <Teleport v-if="isMounted && isActive" to="#dynamic-island-portal">
        <!-- BUSCA -->
        <div class="relative w-34 shrink-0 transition-all duration-300 ease-out group focus-within:w-60">
            <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-surface-muted text-sm pointer-events-none group-focus-within:text-primary-500 transition-colors"></i>
            <input
                :value="searchQuery"
                @input="$emit('update:searchQuery', $event.target.value)"
                placeholder="Buscar..."
                class="w-full h-10 md:h-12 pl-10 pr-9 rounded-full bg-surface-1 text-surface-text text-sm font-medium focus:ring-2 focus:ring-primary-500/30 outline-none transition-all placeholder:text-surface-muted/50"
            />
            <button v-if="searchQuery" @click="$emit('update:searchQuery', '')" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full flex items-center justify-center text-surface-muted hover:text-danger-smart hover:bg-danger-500/10 transition-colors outline-none cursor-pointer">
                <i class="pi fa-circle-xmark text-sm text-surface-text font-medium"></i>
            </button>
        </div>

        <!-- FILTROS -->
        <button v-if="selectedCategory || searchQuery" class="h-10 md:h-12 px-4 rounded-full bg-surface-1 text-[11px] font-black text-surface-muted hover:text-primary-smart transition-colors flex items-center gap-2 cursor-pointer outline-none shrink-0">
            <i class="pi pi-filter text-[10px]"></i> Filtros
        </button>

        <!-- LAYOUT TOGGLES -->
        <SegmentedControl
            :model-value="isGridActive ? 'grid' : 'list'"
            @update:modelValue="$event === 'grid' ? emitGridClick() : emitListClick()"
            :options="[
                { label: 'Grade', value: 'grid', icon: 'pi-th-large' },
                { label: 'Lista', value: 'list', icon: 'pi-list' }
            ]"
        />

        <!-- FAVORITOS -->
        <button
            v-if="hasFavorites"
            @click="$emit('toggle-favorites')"
            class="h-10 md:h-12 px-4 rounded-full font-black text-sm transition-all outline-none flex items-center gap-2 shrink-0 cursor-pointer ml-1"
            :class="isFavoritesActive ? 'bg-amber-500/40' : 'bg-surface-1 hover:border-amber-500/50'"
            title="Alternar O de Sempre"
        >
            <i class="pi" :class="isFavoritesActive ? 'pi-star-fill' : 'pi-star'"></i>
            <span class="hidden md:inline">{{ isFavoritesActive ? 'Favoritos' : 'Ver Favoritos' }}</span>
        </button>

        <!-- CATEGORY MANAGER -->
        <PosTeleportButton
            icon="pi-sitemap"
            label="Organizar Cardápio"
            :active="isManagerActive"
            color="primary"
            @click="$emit('toggle-manager')"
        />
    </Teleport>
</template>

<script setup>
import PosTeleportButton from '@/Components/UI/PosTeleportButton.vue';

import SegmentedControl from '@/Components/UI/SegmentedControl.vue';

import { computed } from 'vue';

const props = defineProps({
    isMounted: Boolean,
    isActive: Boolean,
    searchQuery: String,
    selectedCategory: [String, Number],
    categoryLayout: String,
    productCardLayout: String,
    hasFavorites: Boolean,
    isFavoritesActive: Boolean,
    isManagerActive: Boolean
});

const emit = defineEmits([
    'update:searchQuery', 'update:categoryLayout', 'update:productCardLayout',
    'toggle-favorites', 'toggle-manager'
]);

const isGridActive = computed(() => {
    return (!props.selectedCategory && !props.searchQuery) ? props.categoryLayout === 'grid' : props.productCardLayout === 'square';
});

const isListActive = computed(() => {
    return (!props.selectedCategory && !props.searchQuery) ? props.categoryLayout === 'list' : props.productCardLayout === 'list';
});

const emitGridClick = () => {
    if (!props.selectedCategory && !props.searchQuery) emit('update:categoryLayout', 'grid');
    else emit('update:productCardLayout', 'square');
};

const emitListClick = () => {
    if (!props.selectedCategory && !props.searchQuery) emit('update:categoryLayout', 'list');
    else emit('update:productCardLayout', 'list');
};
</script>
