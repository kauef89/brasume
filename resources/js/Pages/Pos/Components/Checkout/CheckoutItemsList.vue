<template>
    <div class="flex-1 min-h-0 overflow-y-auto scroll-container px-4 md:px-6 flex flex-col gap-4 pb-6">

        <div>
            <!-- CABEÇALHO DA TABELA DE ITENS -->
            <div class="sticky top-0 z-10 bg-surface-2 pt-2 flex items-center border-b-2 border-surface-border/50 pb-2 mb-2">
                <div class="flex items-center justify-center shrink-0 mr-3">
                    <label class="flex items-center cursor-pointer" title="Selecionar / Desselecionar Todos">
                        <div class="flex items-center justify-center w-5 h-5 rounded border transition-colors cursor-pointer"
                            :class="isAllSelected ? 'bg-primary-500 border-primary-500 text-white' : 'border-surface-border bg-surface-1 hover:border-surface-muted'">
                            <i v-if="isAllSelected" class="pi pi-check text-[10px] font-bold"></i>
                        </div>
                        <input type="checkbox" class="hidden" :checked="isAllSelected" @change="$emit('toggle-all')" />
                    </label>
                </div>
                <div class="flex items-center justify-center shrink-0 w-10 ml-1">
                    <span class="text-[11px] font-black text-surface-muted uppercase tracking-widest">Qtd.</span>
                </div>
                <div class="flex-1 flex pl-3 ml-2 items-center min-w-0 border-l-2 border-transparent">
                    <h3 class="text-[11px] font-black text-surface-muted uppercase tracking-widest truncate">
                        {{ mode === 'details' ? 'Itens do Pedido' : 'Itens a Pagar' }}
                    </h3>
                </div>
                <div class="flex items-center justify-end shrink-0 w-20">
                    <span class="text-[10px] font-black text-surface-muted uppercase tracking-widest text-right leading-tight">Valor Unitário</span>
                </div>
                <div class="shrink-0 w-6 ml-2"></div>
            </div>

            <!-- LISTA DE ITENS ATIVOS -->
            <div class="flex flex-col">
                <template v-for="item in activeItems" :key="item.unique_id || item.id">
                    <CheckoutItem
                        v-show="!item.is_combo_child || expandedCombos.includes(item.combo_parent_id) || Boolean(searchQuery)"
                        :item="item"
                        :mode="mode"
                        :is-selected="mode === 'details' ? selectedIds.includes(item.id) : (item.selected_parts > 0)"
                        :is-expanded="expandedCombos.includes(item.id) || Boolean(searchQuery)"
                        @increment="$emit('increment', item)"
                        @decrement="$emit('decrement', item)"
                        @divide-item="(arg1, arg2) => handleItemDivide(item, arg1, arg2)"
                        @toggle-menu="handleToggleMenu"
                        @toggle-select="$emit('toggle-select', item)"
                        @toggle-expand="toggleCombo(item.id)"
                    />
                </template>
                <div v-if="activeItems.length === 0" class="py-8 text-center flex flex-col items-center justify-center text-surface-muted">
                    <template v-if="searchQuery">
                        <i class="pi pi-search text-3xl text-surface-muted/60 mb-2"></i>
                        <span class="text-sm font-bold text-surface-text">Nenhum item localizado</span>
                        <span class="text-xs">Não encontramos itens ou adicionais para "{{ searchQuery }}".</span>
                    </template>
                    <template v-else>
                        <i class="pi pi-check-circle text-3xl text-success-500 mb-2"></i>
                        <span class="text-sm font-bold text-surface-text">Todos os itens desta conta foram quitados!</span>
                        <span class="text-xs">Confira o histórico de pagamentos logo abaixo.</span>
                    </template>
                </div>
            </div>
        </div>

        <!-- 🚀 HISTÓRICO DE PAGAMENTOS REALIZADOS (COMPONENTE MODULAR) -->
        <CheckoutPaymentHistory
            :order-payments="orderPayments"
            :paid-items="paidItems"
            :mode="mode"
            @rollback-payment="$emit('rollback-payment', $event)"
        />

        <ItemActionPopover
            ref="actionPopoverRef"
            :mode="mode"
            @action="$emit('action',$event)"
            @quick-add="$emit('quick-add',$event)"
            @quick-cancel="$emit('quick-cancel',$event)"
        />
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import CheckoutItem from "@/Pages/Pos/Components/Checkout/CheckoutItem.vue";
import CheckoutPaymentHistory from "@/Pages/Pos/Components/Checkout/CheckoutPaymentHistory.vue";
import ItemActionPopover from "@/Pages/Pos/Components/Checkout/ItemActionPopover.vue";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

const props = defineProps({
    displayItems: { type: Array, default: () => [] },
    orderPayments: { type: Array, default: () => [] },
    mode: { type: String, default: 'checkout' },
    selectedIds: { type: Array, default: () => [] },
    isAllSelected: { type: Boolean, default: false },
    searchQuery: { type: String, default: '' }
});

const emit = defineEmits([
    "increment",
    "decrement",
    "divide-item",
    "quick-add",
    "quick-cancel",
    "toggle-select",
    "toggle-all",
    "action",
    "rollback-payment"
]);

const { formatModifiersList } = useOrderFormatting();

const expandedCombos = ref([]);
const actionPopoverRef = ref(null);

const toggleCombo = (id) => {
    const idx = expandedCombos.value.indexOf(id);
    if (idx > -1) expandedCombos.value.splice(idx, 1);
    else expandedCombos.value.push(id);
};

const handleToggleMenu = (event, item) => {
    if (actionPopoverRef.value) {
        actionPopoverRef.value.toggle(event, item);
    }
};

const itemMatchesQuery = (item, query) => {
    if (!query) return true;
    const cleanQ = String(query).toLowerCase().trim();
    if (!cleanQ) return true;

    const name = String(item.product_name || item.name || '').toLowerCase();
    if (name.includes(cleanQ)) return true;

    const notes = String(item.notes || '').toLowerCase();
    if (notes.includes(cleanQ)) return true;

    const modLines = formatModifiersList(item) || [];
    if (modLines.some(line => String(line).toLowerCase().includes(cleanQ))) {
        return true;
    }

    if (Array.isArray(item.modifiers)) {
        const matched = item.modifiers.some(m => {
            const mName = String(m.name || m.modifier_name || m.option_name || '').toLowerCase();
            return mName.includes(cleanQ);
        });
        if (matched) return true;
    }

    const structured = item.structured_modifiers || item.structuredModifiers;
    if (Array.isArray(structured)) {
        const matched = structured.some(m => String(m.name || '').toLowerCase().includes(cleanQ));
        if (matched) return true;
    }

    return false;
};

const activeItems = computed(() => {
    const rawItems = props.displayItems || [];
    if (!rawItems.length) return [];

    let base = rawItems.filter(i => i.status !== 'cancelled');

    if (props.mode !== 'details') {
        base = base.filter(i => (Number(i.available_parts) > 0.0001 || Number(i.selected_parts) > 0));
    }

    const q = (props.searchQuery || '').toLowerCase().trim();
    if (!q) {
        return base;
    }

    const matchingIds = new Set();
    const parentsToKeep = new Set();

    base.forEach(item => {
        if (itemMatchesQuery(item, q)) {
            matchingIds.add(String(item.id));
            if (item.unique_id) matchingIds.add(String(item.unique_id));
            if (item.is_combo_child && item.combo_parent_id) {
                parentsToKeep.add(String(item.combo_parent_id));
            }
        }
    });

    return base.filter(item => {
        const itemIdStr = String(item.id);
        const uniqueIdStr = item.unique_id ? String(item.unique_id) : null;
        const isDirectMatch = matchingIds.has(itemIdStr) || (uniqueIdStr && matchingIds.has(uniqueIdStr));

        if (isDirectMatch) return true;

        if (item.has_exploded_children && parentsToKeep.has(itemIdStr)) {
            return true;
        }

        if (item.is_combo_child && item.combo_parent_id && matchingIds.has(String(item.combo_parent_id))) {
            return true;
        }

        return false;
    });
});

const paidItems = computed(() => {
    return (props.displayItems || []).filter((i) => {
        const paid = i.paid_parts !== undefined ? Number(i.paid_parts) : Number(i.paid_qty || 0);
        return paid > 0.0001;
    }).map(i => {
        if (i.paid_parts === undefined) {
            return { ...i, paid_parts: Number(i.paid_qty || 0), parts_per_unit: 1, effective_unit_price: Number(i.unit_price) };
        }
        return i;
    });
});

const handleItemDivide = (loopItem, arg1, arg2) => {
    const targetItem = (arg2 !== undefined && typeof arg1 === 'object') ? arg1 : loopItem;
    const targetDivisor = arg2 !== undefined ? arg2 : arg1;
    emit('divide-item', targetItem, targetDivisor);
};
</script>

<style scoped>
.scroll-container {
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}
</style>
