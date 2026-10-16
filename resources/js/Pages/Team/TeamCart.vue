<template>
    <div class="flex-1 w-full h-full flex pt-28 flex-col overflow-hidden bg-surface-2">

        <div class="flex-1 overflow-y-auto px-4 pt-4 pb-36 hide-scrollbar flex flex-col gap-6 relative">

            <!-- EMPTY STATE -->
            <div v-if="cart.length === 0" class="flex-1 flex flex-col items-center justify-center text-center opacity-60 mt-10">
                <div class="w-20 h-20 bg-surface-2 rounded-full flex items-center justify-center text-surface-muted mb-4 shadow-inner">
                    <i class="pi pi-shopping-bag text-3xl"></i>
                </div>
                <h3 class="font-black text-lg text-surface-text">Comanda Vazia</h3>
                <p class="text-sm font-medium text-surface-muted mt-1 px-4">
                    Adicione itens do cardápio para iniciar o pedido.
                </p>
            </div>

            <template v-else>
                <!-- ROTEAMENTO NORMAL -->
                <div v-if="!isAppending" class="w-full">
                    <OrderRouting
                        :model-value="orderType"
                        @update:modelValue="$emit('update:orderType',$event)"
                        :is-open="isRoutingOpen"
                        @open="isRoutingOpen = true"
                        @toggle-open="isRoutingOpen = !isRoutingOpen"
                        @apply="isRoutingOpen = false"
                        :routing-meta="cartRoutingMeta"
                        :table-areas="tableAreas"
                        :customers="customers"
                        :is-team-mode="true"
                        :is-allowance-mode="isAllowanceMode"
                        @routing-change="handleRoutingChange"
                    />
                </div>

                <!-- MODO ADIÇÃO EM CONTA EXISTENTE -->
                <div v-else class="bg-primary-500/10 p-4 rounded-[1.5rem] border border-primary-500/30 shadow-sm flex items-center justify-between mb-2">
                    <div class="flex flex-col min-w-0 pr-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-primary-500">Adicionando à Conta</span>
                        <span class="text-surface-text font-black text-sm truncate">{{ appendOrderTitle }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-primary-500/20 text-primary-600 flex items-center justify-center shrink-0">
                        <i class="pi pi-link text-lg"></i>
                    </div>
                </div>

                <!-- LISTA DE ITENS -->
                <div class="flex flex-col gap-3">
                    <h3 class="font-black text-sm uppercase tracking-widest text-surface-muted px-2">Itens Selecionados</h3>

                    <TeamCartItemCard
                        v-for="(item, index) in cart"
                        :key="item.cartId || index"
                        :item="item"
                        :index="index"
                        @update-qty="(idx, delta) => $emit('update-qty', idx, delta)"
                        @edit-item="(it, idx) => $emit('edit-item', it, idx)"
                        @toggle-takeaway="handleToggleTakeaway"
                    />
                </div>

                <!-- RESUMO FINANCEIRO -->
                <div class="mt-4 pt-4 border-t border-surface-border flex flex-col gap-4">
                    <div class="flex justify-between items-center px-2">
                        <span class="text-sm font-black text-surface-muted uppercase tracking-widest">Total</span>
                        <span class="text-2xl font-black">{{ $formatCurrency(cartSubtotal) }}</span>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency } from '@/Utils/formatters';
import { useToast } from 'primevue/usetoast';
import { useCart } from "@/Pages/Shared/Composables/useCart";
import OrderRouting from "@/Pages/Pos/Components/Cart/OrderRouting.vue";
import TeamCartItemCard from "@/Pages/Team/TeamCartItemCard.vue";

const props = defineProps({
    cart: { type: Array, required: true },
    cartSubtotal: { type: Number, required: true },
    orderType: { type: String, default: 'table' },
    tableAreas: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    editOrderData: { type: Object, default: null },
    isAllowanceMode: { type: Boolean, default: false },
    cartRoutingMeta: { type: Object, default: null }
});

const emit = defineEmits([
    'update-qty', 'clear-cart', 'edit-item', 'checkout',
    'update:orderType', 'routingValid', 'routingMeta'
]);

const toast = useToast();
const { updateQty, draftRouting } = useCart();

const isRoutingOpen = ref(!props.cartRoutingMeta?.table && !draftRouting?.value?.table && props.orderType === 'table');
const isAppending = computed(() => !!props.cartRoutingMeta?.id || !!draftRouting?.value?.id || !!props.editOrderData);

const appendOrderTitle = computed(() => {
    const meta = props.cartRoutingMeta || draftRouting?.value || {};
    const edit = props.editOrderData || {};

    const tableName = meta.table?.name
        || meta.table?.number
        || meta.table_number
        || edit.table?.name
        || edit.table?.number
        || edit.table_number
        || '';

    const orderId = edit.id || meta.id || meta.order_id || '';

    if ((meta.type || edit.type || props.orderType) === 'table') {
        if (tableName) return `Mesa ${tableName}`;
        if (orderId) return `Mesa (Pedido #${orderId})`;
        return 'Mesa Salão';
    }

    return meta.identifier || edit.customer_name || (orderId ? `Pedido #${orderId}` : 'Pedido Aberto');
});

watch(
    () => [props.cartRoutingMeta, props.orderType, isAppending.value],
    ([meta, type, isApp]) => {
        if (isApp) {
            emit('routingValid', true);
            const existing = props.cartRoutingMeta || draftRouting?.value || {};
            const merged = {
                ...existing,
                id: props.editOrderData?.id || existing.id,
                type: props.editOrderData?.type || existing.type || 'table'
            };
            emit('routingMeta', merged);
            return;
        }

        if (meta) {
            const hasValidTable = type === 'table' && (meta.table?.id || meta.table_id || meta.dining_table_id || meta.id);
            const hasValidCounter = type === 'counter' && (meta.customer || meta.identifier || meta.customer_name);
            const hasValidDelivery = type === 'delivery' && (meta.address || meta.address_id);

            if (hasValidTable || hasValidCounter || hasValidDelivery) {
                emit('routingValid', true);
                emit('routingMeta', meta);
            }
        }
    },
    { immediate: true, deep: true }
);

const syncTakeawayState = () => {
    const isAlwaysTakeaway = props.orderType === 'delivery' || props.orderType === 'counter';

    props.cart.forEach(item => {
        if (isAlwaysTakeaway) {
            if (item._originalTakeaway === undefined) {
                item._originalTakeaway = !!item.isTakeaway;
            }
            item.isTakeaway = true;
        } else {
            if (item._originalTakeaway !== undefined) {
                item.isTakeaway = item._originalTakeaway;
                delete item._originalTakeaway;
            }
        }
    });
};

watch(() => props.orderType, syncTakeawayState, { immediate: true });
watch(() => props.cart.length, syncTakeawayState, { immediate: true });

const handleRoutingChange = (payload) => {
    emit('routingValid', payload.isValid);
    emit('routingMeta', payload.meta);
};

const handleToggleTakeaway = (index) => {
    if (props.orderType === 'delivery' || props.orderType === 'counter') {
        toast.add({
            severity: 'info',
            summary: 'Destino Fixo',
            detail: 'Todos os itens de Delivery ou Balcão são para viagem.',
            life: 3000
        });
        return;
    }

    if (props.cart[index]) {
        props.cart[index].isTakeaway = !props.cart[index].isTakeaway;
    }
};

defineExpose({
    toggleRouting: () => {
        isRoutingOpen.value = !isRoutingOpen.value;
    }
});
</script>
