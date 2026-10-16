<template>
    <div class="c-glass-card mb-3 p-4 transition-all select-none cursor-default">
        <!-- Linha Superior: Qtd, Título e Preço -->
        <div class="flex justify-between items-center gap-3">
            <span class="c-text-main flex-1 flex items-baseline gap-1.5 min-w-0">
                <span class="text-white/90 font-black shrink-0">{{ qty || 1 }}</span>
                <span class="text-white/40 font-medium">×</span>
                <span class="truncate pl-0.5">{{ title }}</span>
            </span>

            <span v-if="price > 0" class="c-text-main font-black shrink-0 text-white/80">
                {{ $formatCurrency(price) }}
            </span>
        </div>

        <!-- Itens de Combo -->
        <div v-if="comboItems && comboItems.length > 0" class="flex flex-col gap-1 mt-2">
            <div v-for="(cItem, idx) in comboItems" :key="idx" class="flex flex-col">
                <div class="flex items-start gap-1.5 c-text-muted text-[11px]">
                    <span class="text-white/30">└</span>
                    <span class="text-white/80 font-medium">{{ cItem.name || cItem.product_name }}</span>
                </div>
                <div
                    v-if="getModsLines(cItem.structuredModifiers || cItem.modifiers, cItem.quantity || cItem.qty).length > 0"
                    class="flex flex-col ml-3.5 border-l border-white/10 pl-2 mt-0.5 mb-1"
                >
                    <div
                        v-for="(line, mIdx) in getModsLines(cItem.structuredModifiers || cItem.modifiers, cItem.quantity || cItem.qty)"
                        :key="mIdx"
                        class="text-[10px] text-white/50"
                    >
                        {{ line }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Modificadores e Observações Individuais -->
        <div v-else-if="parsedModifiersLines.length > 0" class="flex flex-col gap-0.5 mt-1.5 pl-0.5">
            <span
                v-for="(line, idx) in parsedModifiersLines"
                :key="idx"
                class="c-text-muted text-[11px]"
            >
                {{ line }}
            </span>
        </div>

        <!-- Ações do Item (Stepper + Edição + Remoção) -->
        <div class="flex flex-col mt-3 pt-2.5 border-t border-white/5">
            <div class="flex justify-between items-center">
                <slot name="actions"></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { formatCurrency } from "@/Utils/formatters";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

const props = defineProps({
    title: String,
    qty: { type: Number, default: 1 },
    price: { type: Number, default: 0 },
    structuredModifiers: { type: Array, default: () => [] },
    comboItems: Array,
});

const { formatModifiersList } = useOrderFormatting();

const parsedModifiersLines = computed(() => formatModifiersList({ structuredModifiers: props.structuredModifiers, qty: props.qty }));
const getModsLines = (rawArray, parentQty) => formatModifiersList({ structuredModifiers: rawArray, qty: parentQty || props.qty });
</script>
