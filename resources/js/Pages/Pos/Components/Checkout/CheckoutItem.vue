<template>
    <div
        class="flex items-center py-2.5 transition-all border-b-2 border-surface-border/50 group"
        :class="isActive ? '' : 'opacity-60 grayscale'"
    >
        <!-- SELEÇÃO -->
        <div @click.stop="$emit('toggle-select', item)" class="flex items-center justify-center w-5 h-5 rounded border transition-colors cursor-pointer shrink-0 mr-3" :class="isActive ? 'bg-primary-500 border-primary-500 text-white' : 'border-surface-border bg-surface-1 hover:border-surface-muted'">
            <i v-if="isActive" class="pi pi-check text-[10px] font-medium"></i>
        </div>

        <!-- QUANTIDADE CHECKOUT -->
        <QtyStepper
            v-if="mode === 'checkout'"
            :model-value="item.selected_parts"
            :min="0" :max="item.available_parts" :step="1"
            @increment="$emit('increment', item)" @decrement="$emit('decrement', item)"
            size="sm"
        >
            <template #value>
                <span class="text-xs">{{ formatFraction(item.selected_parts, item.parts_per_unit) }}</span>
            </template>
        </QtyStepper>

        <div v-if="mode === 'checkout'" class="flex items-center justify-center shrink-0 w-12 ml-2">
            <input
                type="number"
                min="1"
                max="20"
                :placeholder="item.parts_per_unit > 1 ? `/${item.parts_per_unit}` : '/'"
                v-model="localDivisor"
                @keyup.enter="handleInputImmediate"
                class="w-full h-8 text-center text-xs font-medium border border-surface-border rounded-lg bg-surface-1 focus:ring-1 focus:ring-primary-500/50 outline-none transition-all placeholder:text-surface-muted"
                title="Dividir este item (digite 1 para voltar ao normal)"
            />
        </div>

        <!-- QUANTIDADE DETAILS -->
        <div v-else class="flex items-center justify-center shrink-0 w-10 ml-1">
            <span class="font-black text-sm text-surface-text">{{ formatFraction(item.available_parts, item.parts_per_unit) }} ×</span>
        </div>

        <!-- DESCRIÇÃO E DETALHES -->
        <div class="flex-1 flex pl-3 ml-2 border-l-2 border-surface-border/50 items-center">
            <div class="flex-1 flex flex-col justify-center min-w-0">
                <div class="flex items-center gap-1.5">
                    <h3
                        class="font-medium leading-tight truncate text-surface-text"
                        :class="item.is_combo_child ? 'text-xs font-bold text-surface-text/90' : 'text-sm'"
                    >
                        {{ item.product_name || item.name }}
                    </h3>

                    <span v-if="item.is_combo_child" class="text-[9px] font-black uppercase tracking-wider text-surface-muted bg-surface-2 px-1.5 py-0.5 rounded shrink-0">
                        Subitem
                    </span>

                    <button
                        v-if="item.has_exploded_children"
                        @click.stop="handleExpandToggle"
                        class="text-surface-muted hover:text-surface-text transition-colors rounded p-0.5 flex items-center justify-center bg-surface-2 shrink-0 outline-none border-none cursor-pointer"
                    >
                        <i class="pi text-[8px] font-black" :class="isExpanded ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
                    </button>
                </div>

                <div v-if="item.user_name || item.created_at" class="flex items-center gap-1.5 mt-0.5">
                    <span v-if="item.created_at" class="text-[9px] font-medium text-surface-muted">
                        <i class="pi pi-clock text-[8px]"></i> {{ formatTime(item.created_at) }}
                    </span>
                    <span v-if="item.user_name" class="text-[9px] font-medium text-surface-muted truncate max-w-[100px]" :title="item.user_name">
                        <i class="pi fa-user text-[8px]"></i> {{ abbreviateName(item.user_name) }}
                    </span>
                </div>
            </div>

            <div v-if="parsedModifiersLines.length > 0" class="w-[35%] shrink-0 flex flex-col justify-center pl-2 ml-2">
                <span v-for="(line, idx) in parsedModifiersLines" :key="idx" class="text-xs text-surface-muted leading-tight break-words">
                    {{ line }}
                </span>
            </div>
        </div>

        <!-- PREÇO COM VALOR DE CATÁLOGO RISCADO CASO RATEADO -->
        <div class="flex items-center justify-end shrink-0 w-24">
            <div v-if="item.is_combo_child" class="flex flex-col items-end leading-tight">
                <span class="text-xs font-black text-surface-text">
                    {{ $formatCurrency(displayPrice) }}
                </span>
                <span v-if="item.catalog_price && item.catalog_price > item.effective_unit_price" class="text-[9px] text-surface-muted line-through font-semibold">
                    {{ $formatCurrency(item.catalog_price) }}
                </span>
            </div>
            <span v-else class="text-sm font-black text-surface-text">
                {{ $formatCurrency(displayPrice) }}
            </span>
        </div>

        <!-- MENU DE AÇÕES INDIVIDUAL (3 PONTINHOS) -->
        <div class="shrink-0 flex items-center justify-center w-6 ml-2">
            <button @click="$emit('toggle-menu',$event, item)" class="w-6 h-6 flex items-center justify-center rounded text-surface-muted hover:bg-surface-2 transition-colors outline-none border-none bg-transparent cursor-pointer">
                <i class="pi pi-ellipsis-v text-xs"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { formatCurrency, formatFraction, abbreviateName } from "@/Utils/formatters";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import QtyStepper from "@/Components/UI/QtyStepper.vue";

const props = defineProps({
    item: Object,
    mode: { type: String, default: 'checkout' },
    isSelected: { type: Boolean, default: false },
    isExpanded: { type: Boolean, default: false }
});

const emit = defineEmits(["increment", "decrement", "divide-item", "toggle-menu", "toggle-select", "toggle-expand"]);

const { formatModifiersList } = useOrderFormatting();
const localDivisor = ref("");

const handleExpandToggle = () => {
    emit('toggle-expand', props.item.id);
};

const isActive = computed(() => {
    if (props.mode === 'details') return props.isSelected;
    return props.item.selected_parts > 0;
});

const displayPrice = computed(() => {
    const ppu = props.item.parts_per_unit || 1;
    const price = props.item.effective_unit_price || 0;

    if (props.mode === 'details') {
        const parts = props.item.available_parts || props.item.quantity || 1;
        return parts * (price / ppu);
    }

    // No checkout: se estiver selecionado, calcula a cota selecionada; se estiver 0, mostra a cota de 1 unidade
    const parts = props.item.selected_parts > 0 ? props.item.selected_parts : 1;
    return parts * (price / ppu);
});

const parsedModifiersLines = computed(() => formatModifiersList(props.item));

const formatTime = (dateString) => {
    if (!dateString) return '--:--';
    return new Date(dateString).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
};

let debounceTimer = null;
watch(localDivisor, (newVal) => {
    if (!newVal && newVal !== 0) return;
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const val = parseInt(newVal);
        if (val && val >= 1) emit("divide-item", props.item, val);
        localDivisor.value = "";
    }, 800);
});

const handleInputImmediate = () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    const val = parseInt(localDivisor.value);
    if (val && val >= 1) emit("divide-item", props.item, val);
    localDivisor.value = "";
};
</script>

<style scoped>
@keyframes fade-in { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
.animate-fade-in { animation: fade-in 0.2s ease-out forwards; }
</style>
