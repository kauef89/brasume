<template>
    <div class="p-4 bg-surface-3 rounded-[1.5rem] flex flex-col gap-3">
        <!-- CABEÇALHO DO ITEM: Nome, Preço e Badge P/ Viagem -->
        <div class="flex justify-between items-start gap-3">
            <div class="flex flex-col min-w-0 flex-1">
                <span class="font-black text-sm text-surface-text leading-tight">{{ item.name }}</span>
                <span v-if="item.isTakeaway" class="text-[10px] font-bold uppercase tracking-widest text-warning-500 mt-1 flex items-center gap-1">
                    <i class="pi pi-shopping-bag text-[10px]"></i> P/ Viagem
                </span>
            </div>
            <span class="font-black shrink-0">{{ $formatCurrency(itemTotal) }}</span>
        </div>

        <!-- MODIFICADORES E COMBOS -->
        <div
            v-if="(item.structuredModifiers && item.structuredModifiers.length > 0) || (item.comboItems && item.comboItems.length > 0)"
            class="flex flex-col gap-1 pl-2 border-l-2 border-surface-border/50"
        >
            <template v-if="item.comboItems">
                <div v-for="(cItem, cIdx) in item.comboItems" :key="'c' + cIdx" class="flex flex-col gap-0.5">
                    <span class="text-xs font-bold text-surface-text">+ {{ cItem.name || cItem.product_name }}</span>
                    <div v-if="cItem.structuredModifiers && cItem.structuredModifiers.length > 0" class="flex flex-col pl-2">
                        <span v-for="(m, mIdx) in cItem.structuredModifiers" :key="'cm' + mIdx" class="text-[10px] font-medium text-surface-muted">
                            - {{ m.qty > 1 ? m.qty + 'x ' : '' }}{{ m.name }}
                        </span>
                    </div>
                </div>
            </template>

            <template v-else-if="item.structuredModifiers">
                <span v-for="(mod, mIdx) in item.structuredModifiers" :key="'m' + mIdx" class="text-xs font-medium text-surface-muted">
                    + {{ mod.qty > 1 ? mod.qty + 'x ' : '' }}{{ mod.name }}
                </span>
            </template>
        </div>

        <!-- AÇÕES DO ITEM (RODAPÉ) -->
        <div class="flex items-center justify-between mt-2 pt-3 border-t border-surface-border/50">
            <QtyStepper
                :model-value="numericQty"
                :min="1"
                @increment="$emit('update-qty', index, 1)"
                @decrement="$emit('update-qty', index, -1)"
                size="sm"
            />

            <div class="flex items-center gap-2">
                <!-- 🛍️ BOTÃO SACOLINHA (PARA VIAGEM) -->
                <button
                    type="button"
                    @click.prevent="$emit('toggle-takeaway', index)"
                    class="w-10 h-10 rounded-xl flex items-center justify-center transition-colors outline-none cursor-pointer border-none shadow-sm active:scale-95"
                    :class="item.isTakeaway
                        ? 'bg-warning-500 text-white'
                        : 'bg-surface-3 text-surface-muted hover:text-surface-text hover:bg-surface-border'"
                    :title="item.isTakeaway ? 'Item marcado para viagem' : 'Marcar para viagem'"
                >
                    <i class="pi pi-shopping-bag text-sm"></i>
                </button>

                <!-- ✏️ BOTÃO EDITAR -->
                <button
                    type="button"
                    @click.prevent="$emit('edit-item', item, index)"
                    class="w-10 h-10 rounded-xl bg-surface-3 text-surface-text hover:bg-surface-border transition-colors flex items-center justify-center outline-none cursor-pointer border-none shadow-sm active:scale-95"
                    title="Editar produto"
                >
                    <i class="pi pi-pencil text-sm"></i>
                </button>

                <!-- 🗑️ BOTÃO REMOVER -->
                <button
                    type="button"
                    @click.prevent="$emit('update-qty', index, -numericQty)"
                    class="w-10 h-10 rounded-xl bg-danger-500/10 text-danger-500 hover:bg-danger-500 hover:text-white transition-colors flex items-center justify-center outline-none cursor-pointer border-none shadow-sm active:scale-95"
                    title="Remover do carrinho"
                >
                    <i class="pi pi-trash text-sm"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency } from '@/Utils/formatters';
import QtyStepper from "@/Components/UI/QtyStepper.vue";

const props = defineProps({
    item: { type: Object, required: true },
    index: { type: Number, required: true }
});

defineEmits(['update-qty', 'edit-item', 'toggle-takeaway']);

// 🚀 Fallback duplo com blindagem numérica
const numericQty = computed(() => {
    const raw = props.item?.qty ?? props.item?.quantity ?? 1;
    const parsed = Number(raw);
    return isNaN(parsed) || parsed <= 0 ? 1 : parsed;
});

const itemTotal = computed(() => {
    const unitPrice = Number(props.item?.price) || 0;
    return unitPrice * numericQty.value;
});
</script>
