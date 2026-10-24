<template>
    <button
        @click="$emit('click', category)"
        :class="cardClasses"
    >
        <!-- 1. MODO HORIZONTAL (Pílula) -->
        <template v-if="layout === 'horizontal'">
            <span class="text-xs whitespace-nowrap leading-none">{{ category?.name }}</span>
        </template>

        <!-- 2. MODO GRADE (Full-Card + Scrim) -->
        <template v-else-if="layout === 'grid'">
            <!-- 🖼️ Imagem de Fundo Completa -->
            <img
                v-if="imageUrl"
                :src="imageUrl"
                class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 z-0"
                alt=""
            />
            <div v-else class="absolute inset-0 w-full h-full flex items-center justify-center text-surface-muted bg-surface-3/70 z-0">
                <i class="pi pi-image text-3xl opacity-25"></i>
            </div>

            <!-- 🌓 Scrim Escuro de Contraste -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/35 to-transparent z-[1] pointer-events-none"></div>

            <!-- 🏷️ Badge de Seleção -->
            <div
                v-if="isSelected"
                class="absolute top-2.5 right-2.5 z-10 w-6 h-6 rounded-full bg-primary-500 text-white flex items-center justify-center shadow-md animate-in zoom-in-75"
            >
                <i class="pi pi-check text-[10px] font-black"></i>
            </div>

            <!-- 📝 Nome sobre o Scrim -->
            <div class="relative z-10 mt-auto p-3 text-center w-full flex flex-col items-center justify-end">
                <span class="font-black text-sm text-white break-words leading-snug drop-shadow-sm">
                    {{ category?.name }}
                </span>
            </div>
        </template>

        <!-- 3. MODO LISTA / VERTICAL -->
        <template v-else>
            <div class="w-10 h-10 shrink-0 rounded-full bg-surface-2 overflow-hidden flex items-center justify-center">
                <img v-if="imageUrl" :src="imageUrl" class="w-full h-full object-cover" alt="" />
                <i v-else class="pi pi-image text-surface-muted opacity-30 text-base"></i>
            </div>

            <div class="flex-1 min-w-0">
                <span class="font-black text-sm break-words leading-tight">{{ category?.name }}</span>
            </div>

            <div v-if="isSelected" class="w-5 h-5 shrink-0 rounded-full bg-primary-500 text-white flex items-center justify-center mr-1">
                <i class="pi pi-check text-[9px] font-black"></i>
            </div>
        </template>
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    category: Object,
    layout: { type: String, default: 'grid' },
    isSelected: { type: Boolean, default: false },
    glassMode: { type: Boolean, default: false }
});

defineEmits(['click']);
const page = usePage();

const imageUrl = computed(() => {
    const path = props.category?.image_path || props.category?.image;
    if (path) {
        const ts = props.category?.updated_at ? new Date(props.category.updated_at).getTime() : '';
        return path.startsWith('http') ? path : `/media/file?path=${path}&v=${ts}`;
    }

    const categories = page?.props?.initialCategories || page?.props?.categories || [];
    let currentParentId = props.category?.parent_id;
    let depth = 0;

    while (currentParentId && Number(currentParentId) !== 0 && depth < 10) {
        const parentCat = categories.find(c => String(c.id) === String(currentParentId));
        if (!parentCat) break;
        const parentPath = parentCat.image_path || parentCat.image;
        if (parentPath) {
            const ts = parentCat.updated_at ? new Date(parentCat.updated_at).getTime() : '';
            return parentPath.startsWith('http') ? parentPath : `/media/file?path=${parentPath}&v=${ts}`;
        }
        currentParentId = parentCat.parent_id;
        depth++;
    }

    return null;
});

const cardClasses = computed(() => {
    const base = 'relative overflow-hidden transition-all duration-200 outline-none text-left group cursor-pointer shrink-0';

    // 1. Horizontal (Pílula)
    if (props.layout === 'horizontal') {
        const shape = 'flex items-center justify-center px-5 py-2.5 rounded-full w-auto min-w-[max-content]';

        if (props.glassMode) {
            if (props.isSelected) return `${base} ${shape} backdrop-blur-xl bg-primary-500/10 text-primary-300`;
            return `${base} ${shape} bg-white/10 backdrop-blur-xl text-surface-text`;
        } else {
            if (props.isSelected) return `${base} ${shape} bg-primary-500 text-white`;
            return `${base} ${shape} bg-surface-3 hover:bg-primary-500/20 text-surface-text`;
        }
    }

    // 2. Lista / Vertical
    if (props.layout === 'list' || props.layout === 'vertical') {
        const shape = 'flex items-center p-3 rounded-2xl gap-4 w-full';
        if (props.isSelected) return `${base} ${shape} bg-primary-500/10 text-primary-700 dark:text-primary-400`;
        return `${base} ${shape} bg-surface-1 hover:bg-primary-500/20 text-surface-text`;
    }

    // 3. Grade (Full-Bleed + Scrim)
    const shape = 'flex flex-col rounded-2xl w-full aspect-[3/4] bg-surface-2';
    if (props.isSelected) {
        return `${base} ${shape} ring-2 ring-primary-500 ring-offset-2 ring-offset-surface-1 shadow-lg`;
    }
    return `${base} ${shape} hover:shadow-xl`;
});
</script>
