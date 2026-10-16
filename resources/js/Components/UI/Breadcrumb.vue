<template>
    <nav class="flex items-center gap-1.5 md:gap-2 whitespace-nowrap min-w-0" :class="sizeClasses[size] || sizeClasses.sm">
        <template v-for="(item, idx) in processedItems" :key="idx">
            <span
                class="transition-colors flex items-center whitespace-nowrap"
                :class="[
                    isLast(idx) && !lastItemClickable
                        ? 'font-black text-surface-text cursor-default pointer-events-none'
                        : 'font-bold text-surface-muted hover:text-primary-smart cursor-pointer',
                    isEditMode ? 'opacity-60 cursor-not-allowed' : ''
                ]"
                @click="handleItemClick(item, idx)"
            >
                <i v-if="isHomeItem(item)" class="pi pi-home text-sm leading-none" title="Início"></i>
                <template v-else>{{ getItemLabel(item) }}</template>
            </span>

            <i v-if="idx < processedItems.length - 1" class="pi pi-chevron-right opacity-40 text-[9px] md:text-[10px] shrink-0"></i>
        </template>
    </nav>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    activeItem: { type: [String, Object], default: null },
    size: { type: String, default: 'sm' },
    isEditMode: { type: Boolean, default: false },
    lastItemClickable: { type: Boolean, default: false }
});

const emit = defineEmits(['navigate']);

const isHomeItem = (item) => {
    if (!item) return false;
    if (typeof item === 'object' && item.id === null) return true;
    const val = typeof item === 'string' ? item : (item.label || item.name || item.id || '');
    const normalized = String(val).toLowerCase().trim();
    return normalized === 'início' || normalized === 'inicio' || normalized === 'home';
};

const getItemLabel = (item) => {
    if (!item) return '';
    if (typeof item === 'string') return item;
    return item.label || item.name || '';
};

const isLast = (idx) => idx === processedItems.value.length - 1;

const handleItemClick = (item, idx) => {
    if (props.isEditMode) return;
    if (isLast(idx) && !props.lastItemClickable) return;
    emit('navigate', item);
};

// 🚀 Todas as categorias completas, sem "..."
const processedItems = computed(() => {
    let arr = [...props.items];
    if (props.activeItem) {
        arr.push(props.activeItem);
    }
    return arr;
});

const sizeClasses = {
    sm: 'text-sm md:text-base',
    md: 'text-base md:text-lg',
    lg: 'text-lg md:text-xl'
};
</script>
