<template>
    <div
        @click="$emit('toggle-selection', item._uniqueId)"
        class="flex items-center gap-3 px-4 py-2 border-b border-surface-border/40 transition-colors group cursor-pointer"
        :class="isSelected ? 'bg-primary-500/5' : 'bg-transparent hover:bg-surface-3/40'"
    >
        <!-- SELEÇÃO -->
        <div class="w-6 shrink-0 flex justify-center">
            <div
                class="w-3.5 h-3.5 rounded-[4px] border flex items-center justify-center transition-colors"
                :class="isSelected ? 'bg-primary-500 border-primary-500 text-white' : 'border-surface-border bg-surface-1 group-hover:border-surface-400'"
            >
                <i v-if="isSelected" class="pi pi-check text-[8px] font-black"></i>
            </div>
        </div>

        <!-- TIPO (ÍCONE) -->
        <div class="w-8 flex justify-center shrink-0 text-surface-muted group-hover:text-surface-text transition-colors">
            <template v-if="item._type === 'order'">
                <i :class="[item._typeIcon || 'pi pi-receipt', 'text-xs']" :title="item._typeLabel"></i>
            </template>
            <template v-else>
                <i :class="[
                    item.type === 'in'
                        ? (item.order_id ? 'pi pi-shopping-cart text-emerald-500' : 'pi pi-arrow-down text-blue-500')
                        : 'pi pi-arrow-up text-amber-500',
                    'text-xs font-bold'
                ]" :title="item.type === 'in' ? (item.order_id ? 'Venda' : 'Suprimento') : 'Sangria'"></i>
            </template>
        </div>

        <!-- CÓDIGO / BADGE -->
        <div class="w-14 text-xs font-medium text-surface-muted shrink-0 tabular-nums">
            <span v-if="item._type === 'order'">#{{ $formatNumber(item.id) }}</span>
            <span v-else class="text-[10px] uppercase font-bold tracking-wider"
                  :class="item.type === 'in' ? (item.order_id ? 'text-emerald-500' : 'text-blue-500') : 'text-amber-500'">
                {{ item.type === 'in' ? (item.order_id ? 'VENDA' : 'SUPRI') : 'SANGR' }}
            </span>
        </div>

        <!-- DETALHES DA OPERAÇÃO -->
        <div class="flex-1 min-w-[160px] flex items-center gap-2 truncate">
            <template v-if="item._type === 'order'">
                <span class="text-xs font-semibold text-surface-text truncate" :class="{ 'line-through opacity-40': item.status === 'cancelled' }">
                    {{ displayOrderTitle }}
                </span>
                <span v-if="item.status === 'cancelled'" class="text-[9px] font-bold uppercase tracking-wider text-danger-500 bg-danger-500/10 px-1.5 py-0.5 rounded shrink-0">
                    Estornado
                </span>
            </template>
            <p v-else class="text-xs font-medium text-surface-text truncate">
                {{ item.description || (item.type === 'in' ? (item.order_id ? 'Pagamento de Pedido' : 'Entrada de Caixa') : 'Retirada de Caixa') }}
            </p>
        </div>

        <!-- OPERADOR -->
        <div class="w-24 text-xs font-medium text-surface-muted truncate shrink-0">
            {{ abbreviateName(item.operator) || 'Sistema' }}
        </div>

        <!-- LIQUIDAÇÃO -->
        <div class="w-28 text-xs font-medium truncate shrink-0" :class="item._type === 'order' ? 'text-surface-text' : 'text-surface-muted'">
            <template v-if="item._type === 'order'">
                {{ item._paymentsLabel }}
            </template>
            <template v-else>
                Gaveta
            </template>
        </div>

        <!-- HORA -->
        <div class="w-14 text-xs font-medium text-surface-muted shrink-0 text-center tabular-nums">
            {{ item.time }}
        </div>

        <!-- VALOR COM CENTAVOS GARANTIDOS -->
        <div
            class="w-24 text-right text-xs font-bold shrink-0 tabular-nums"
            :class="{
                'text-surface-muted line-through opacity-40': item.status === 'cancelled',
                'text-emerald-600 dark:text-emerald-400': item._type === 'transaction' && item.type === 'in' && item.order_id,
                'text-blue-600 dark:text-blue-400': item._type === 'transaction' && item.type === 'in' && !item.order_id,
                'text-amber-600 dark:text-amber-400': item._type === 'transaction' && item.type === 'out',
                'text-surface-text': item._type === 'order' && item.status !== 'cancelled'
            }"
        >
            <span v-if="item._type === 'transaction'">{{ item.type === 'in' ? '+' : '-' }}</span>
            {{ $formatCurrency(item.amount ?? item.total ?? 0) }}
        </div>

        <!-- SEFAZ -->
        <div class="w-16 shrink-0 flex justify-center">
            <template v-if="item._type === 'order' && item.status !== 'cancelled'">
                <span
                    v-if="item.fiscal_status === 'authorized'"
                    class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-500/10 px-1.5 py-0.5 rounded flex items-center gap-1"
                >
                    <i class="pi pi-check text-[8px]"></i> NFC-e
                </span>
                <span
                    v-else-if="item.fiscal_status === 'rejected'"
                    class="text-[9px] font-bold uppercase tracking-wider text-danger-500 bg-danger-500/10 px-1.5 py-0.5 rounded flex items-center gap-1"
                >
                    <i class="pi pi-times text-[8px]"></i> Erro
                </span>
                <span
                    v-else
                    class="text-[9px] font-semibold text-surface-muted/60 uppercase tracking-wider"
                >
                    Pendente
                </span>
            </template>
        </div>

        <!-- AÇÃO MANUAL -->
        <div class="w-8 flex justify-end shrink-0">
            <button
                v-if="item._type === 'order'"
                type="button"
                @click.stop="$emit('action', { type: 'manage', order: item })"
                class="w-7 h-7 rounded-full bg-surface-1 text-surface-muted hover:text-primary-smart hover:bg-surface-3 transition-colors flex items-center justify-center outline-none border border-surface-border/50 opacity-0 group-hover:opacity-100 cursor-pointer shadow-xs"
                title="Abrir Pedido"
            >
                <i class="pi pi-arrow-right text-[9px] font-bold"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency, abbreviateName } from '@/Utils/formatters';

const props = defineProps({
    item: { type: Object, required: true },
    isSelected: { type: Boolean, default: false }
});

defineEmits(['toggle-selection', 'action']);

const displayOrderTitle = computed(() => {
    const it = props.item;
    const custName = it.customer_name?.trim();
    const tableNum = it.table_number?.trim();

    if (it.type === 'table') {
        if (!custName || custName.toLowerCase() === `mesa ${tableNum}`.toLowerCase()) {
            return `Mesa ${tableNum || 'Salão'}`;
        }
        return `${custName} • Mesa ${tableNum}`;
    }

    if (it.type === 'delivery') {
        return custName || 'Cliente Delivery';
    }

    return custName || 'Consumidor Final';
});
</script>
