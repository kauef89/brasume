<template>
    <header class="w-full shrink-0 flex flex-col z-10 relative overflow-hidden px-6 py-4">

        <!-- MODO CLIENTE (B2C) -->
        <div v-if="isCustomerMode" class="flex justify-between items-start gap-4 shrink-0 w-full">
            <h1 class="text-2xl font-black text-surface-text leading-tight tracking-tight break-words flex-1 min-w-0">
                {{ title }}
            </h1>
            <div class="flex items-center shrink-0">
                <span class="text-lg font-black leading-none tracking-tight">
                    {{ $formatCurrency(total) }}
                </span>
            </div>
        </div>

        <!-- MODO PDV / EQUIPE (B2B) -->
        <template v-else>
            <!-- LINHA 1: BREADCRUMBS NO TOPO (Alinhado com o catálogo) -->
            <div v-if="showBreadcrumbs && normalizedItems.length > 0" class="flex-1 min-w-0 flex items-center mb-2">
                <Breadcrumb
                    :items="normalizedItems"
                    :is-edit-mode="isEditMode"
                    :last-item-clickable="true"
                    size="sm"
                    @navigate="(item) => $emit('crumb-click', item)"
                />
            </div>

            <!-- LINHA 2: NOME DO PRODUTO & PREÇO -->
            <div class="flex justify-between items-center gap-4 shrink-0 w-full min-h-[40px]">
                <h1 class="text-2xl md:text-3xl font-black text-surface-text leading-tight tracking-tight break-words flex-1 min-w-0">
                    {{ title }}
                </h1>

                <div class="flex items-center shrink-0">
                    <span class="text-3xl font-black leading-none tracking-tight">
                        {{ $formatCurrency(total) }}
                    </span>
                </div>
            </div>
        </template>

        <!-- Barra de Progresso (Combos) -->
        <div v-if="progressMax > 0" class="absolute bottom-0 left-0 right-0">
            <ProgressBar :current="progressCurrent" :max="progressMax" />
        </div>
    </header>
</template>

<script setup>
import { computed } from "vue";
import { formatCurrency } from "@/Utils/formatters";
import ProgressBar from "@/Components/UI/ProgressBar.vue";
import Breadcrumb from "@/Components/UI/Breadcrumb.vue";

const props = defineProps({
    title: { type: String, required: true },
    breadcrumbs: { type: [Array, Object], default: () => [] },
    showBreadcrumbs: { type: Boolean, default: false },
    total: { type: Number, required: true },
    isValid: { type: Boolean, default: true },
    isEditMode: { type: Boolean, default: false },
    progressCurrent: { type: Number, default: 0 },
    progressMax: { type: Number, default: 0 },
    hideBackBtn: { type: Boolean, default: false },
    isCustomerMode: { type: Boolean, default: false }
});

defineEmits(['close', 'crumb-click']);

const normalizedItems = computed(() => {
    if (Array.isArray(props.breadcrumbs)) {
        return props.breadcrumbs.length > 0 ? props.breadcrumbs : [{ label: 'Início', id: null }];
    }
    if (props.breadcrumbs && typeof props.breadcrumbs === 'object') {
        const items = Array.isArray(props.breadcrumbs.items) ? [...props.breadcrumbs.items] : [];
        if (props.breadcrumbs.active && !['início', 'inicio', 'home'].includes(String(props.breadcrumbs.active).toLowerCase().trim())) {
            items.push({ label: props.breadcrumbs.active, id: null });
        }
        if (items.length > 0) return items;
    }
    return [{ label: 'Início', id: null }];
});
</script>
