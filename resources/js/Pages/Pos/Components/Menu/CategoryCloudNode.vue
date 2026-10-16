<template>
    <draggable
        v-model="internalList"
        item-key="id"
        group="categories"
        :empty-insert-threshold="180"
        :fallback-on-body="true"
        :force-fallback="true"
        fallback-class="drag-clone"
        :swap-threshold="0.65"
        :animation="150"
        :scroll="true"
        :scroll-sensitivity="100"
        :scroll-speed="20"
        handle=".drag-handle"
        ghost-class="drag-ghost"
        class="flex flex-wrap gap-2.5 items-start w-full transition-all"
        :class="isSub ? 'min-h-[52px] p-2 bg-surface-2/60 rounded-xl border-2 border-dashed border-surface-border/70 mt-1.5 w-full' : 'min-h-[20px]'"
        @end="onEnd"
    >
        <template #item="{ element }">
            <div class="flex flex-col p-2 border rounded-xl transition-all shadow-sm select-none min-w-[170px] max-w-full"
                 :class="isMatch(element.name) ? 'bg-warning-500/10 border-warning-500/40 text-warning-smart ring-2 ring-warning-500/10' : 'bg-surface-1 border-surface-border text-surface-text hover:border-primary-500/30'">

                {{ ensureChildren(element) }}

                <div class="drag-handle px-2 py-1 font-black text-sm flex items-center gap-2 cursor-grab active:cursor-grabbing shrink-0 w-full min-w-0">
                    <i class="pi pi-braille text-surface-muted text-xs pointer-events-none shrink-0"></i>
                    <span class="category-title truncate w-full min-w-0 block" :title="element.name">
                        {{ element.name }}
                    </span>
                </div>

                <div class="children-wrapper w-full flex-1">
                    <CategoryCloudNode
                        v-model="element.children"
                        :search-query="searchQuery"
                        :is-sub="true"
                        @save="$emit('save')"
                    />
                </div>
            </div>
        </template>

        <template #footer>
            <div v-if="isSub && internalList.length === 0" class="w-full h-full flex items-center justify-center pointer-events-none py-1.5 min-h-[32px]">
                <span class="text-[10px] uppercase font-black text-surface-muted/50 tracking-widest flex items-center gap-1">
                    <i class="pi pi-plus text-[9px]"></i> Solte Aqui
                </span>
            </div>
        </template>
    </draggable>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    modelValue: { type: Array, required: true },
    searchQuery: { type: String, default: '' },
    isSub: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue', 'save']);

// ==========================================
// 🚀 LOG DIAGNÓSTICO: Roxo (A Fonte da Verdade)
// ==========================================
const logCloudOrder = () => {
    if (!props.modelValue) return;
    const nomes = props.modelValue.map(c => c.name);
    const nivel = props.isSub ? 'SUB-NÍVEL' : 'RAIZ';
};

onMounted(logCloudOrder);
watch(() => props.modelValue, logCloudOrder, { deep: true });
// ==========================================

const internalList = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const onEnd = () => {
    setTimeout(() => {
        emit('save');
    }, 150);
};

const ensureChildren = (element) => {
    if (!element.children) {
        element.children = [];
    }
    return '';
};

const isMatch = (name) => {
    if (!props.searchQuery || props.searchQuery.trim() === '') return false;
    return name.toLowerCase().includes(props.searchQuery.toLowerCase().trim());
};
</script>

<style scoped>
.drag-ghost {
    opacity: 0.3;
    transform: scale(0.95);
}

.drag-ghost .children-wrapper {
    display: none !important;
}
</style>

<style>
/* 🚀 ESTILO GLOBAL PARA O CLONE FLUTUANTE (.sortable-fallback / .drag-clone) */
.sortable-fallback,
.drag-clone {
    z-index: 999999 !important;
    width: 120px !important;
    max-width: 120px !important;
    min-width: 120px !important;
    opacity: 0.95 !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.35) !important;
    pointer-events: none !important;

    /* 🚀 CORREÇÃO DO CARD PESADO: Anula transições CSS e margens do clone */
    transition: none !important;
    margin: 0 !important;
    animation: none !important;
}

/* Trunca o texto do clone no mouse para 120px com "..." */
.sortable-fallback .category-title,
.drag-clone .category-title {
    display: block !important;
    width: 100% !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

/* Oculta os subitens do clone no ar */
.sortable-fallback .children-wrapper,
.drag-clone .children-wrapper {
    display: none !important;
}
</style>
