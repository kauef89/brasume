<template>
    <div class="p-4 md:p-6 flex flex-col sm:flex-row sm:items-start justify-between z-10 shrink-0 gap-4 w-full">

        <!-- BLOCO DA ESQUERDA: TÍTULO (MESA/CLIENTE) E IDENTIFICADOR DO PAGAMENTO -->
        <div class="flex items-start gap-3 md:gap-4 flex-1 min-w-0 w-full">
            <button @click="$emit('go-back')" class="btn-ghost mt-1 shrink-0 cursor-pointer">
                <i class="pi pi-arrow-left font-medium text-sm"></i>
            </button>

            <div class="flex flex-col gap-1.5 flex-1 min-w-0">
                <!-- LINHA 1: Mesa / Cliente em destaque principal (Grande) -->
                <div class="flex items-center gap-3">
                    <h1 class="heading-h1 truncate text-xl md:text-2xl font-black text-surface-text">{{ title }}</h1>
                    <slot name="title-badges"></slot>
                </div>

                <!-- LINHA 2: "Pagamento #123" (Pequeno) + Chips e Subtítulo -->
                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="orderLabel" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface-1 border border-surface-border text-xs font-bold text-surface-muted">
                        <i class="pi pi-receipt text-[10px] text-primary-500"></i>
                        <span>{{ orderLabel }}</span>
                    </span>

                    <slot name="subtitle">
                        <span v-if="subtitle && subtitle !== title" class="text-xs text-surface-muted font-medium truncate">{{ subtitle }}</span>
                    </slot>
                </div>
            </div>
        </div>

        <!-- BLOCO DA DIREITA: BUSCA EXPANSÍVEL, AÇÕES & CONTROLE DE AGRUPAMENTO -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0 mt-2 sm:mt-0">
            <!-- 🔍 BUSCA POR ITEM OU MODIFICADOR -->
            <ExpandableSearch
                v-if="showSearch"
                :model-value="searchQuery"
                @update:model-value="$emit('update:searchQuery', $event)"
                placeholder="Buscar item ou adicional..."
                class="!w-36 sm:!w-44 md:!w-56"
            />

            <slot name="actions"></slot>

            <SegmentedControl
                v-if="!hideGroupToggle"
                :model-value="isGrouped"
                :options="groupOptions"
                @update:model-value="$emit('update:isGrouped', $event)"
                class="!mx-0"
            />
        </div>
    </div>
</template>

<script setup>
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import ExpandableSearch from '@/Components/UI/ExpandableSearch.vue';

defineProps({
    title: { type: String, required: true },
    orderLabel: String,
    subtitle: String,
    isGrouped: { type: Boolean, default: true },
    hideGroupToggle: { type: Boolean, default: false },
    isAllSelected: { type: Boolean, default: false },
    searchQuery: { type: String, default: '' },
    showSearch: { type: Boolean, default: true }
});

defineEmits(['go-back', 'update:isGrouped', 'toggle-all', 'update:searchQuery']);

const groupOptions = [
    { label: 'Agrupado', value: true, icon: 'pi-clone' },
    { label: 'Separado', value: false, icon: 'pi-bars' }
];
</script>
