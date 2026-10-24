<template>
    <div class="flex flex-col h-full overflow-hidden w-full" :class="isTeamMode ? 'bg-transparent' : 'bg-surface-2 rounded-4xl'">

        <!-- 1. CABEÇALHO APENAS PARA DESKTOP/POS (QUANDO NÃO FOR TEAM MODE) -->
        <div v-if="!isTeamMode" class="shrink-0 flex justify-between items-center p-4 md:p-6 pb-0">
            <div>
                <h2 class="font-black text-surface-text text-xl">Mesas</h2>
            </div>

            <div class="flex items-center gap-3">
                <Badge v-if="viewMode === 'cards'" :value="orders.length" severity="secondary" />
                <SegmentedControl
                    v-model="viewMode"
                    :options="viewOptions"
                />
            </div>
        </div>

        <!-- 2. TELEPORT DO SEGMENTED CONTROL NO TEAM MODE PARA O HEADER -->
        <Teleport to="#team-header-action-portal" v-if="isMounted && isTeamMode">
            <SegmentedControl
                v-model="viewMode"
                :options="viewOptions"
                class="!h-9 text-xs"
            />
        </Teleport>

        <!-- CARDS VIEW -->
        <div v-show="viewMode === 'cards'" class="flex-1 flex flex-col min-h-0 pt-2">
            <EmptyState
                v-if="sortedOrders.length === 0"
                icon="pi-thumbs-up"
                title="Nenhuma mesa ou comanda aberta."
            />

            <TransitionGroup v-else name="list" tag="div"
                class="flex-1 overflow-y-auto hide-scrollbar min-h-0 relative content-start auto-rows-max"
                :class="isTeamMode
                    ? 'grid grid-cols-2 gap-2 p-0 pb-32'
                    : (isExpanded ? 'grid grid-cols-2 xl:grid-cols-3 gap-4 px-4 pb-6' : 'grid grid-cols-1 gap-2 px-4 pb-6')">
                <OrderCard
                    v-for="order in sortedOrders"
                    :key="order.id"
                    :order="order"
                    :show-total="!isTeamMode"
                    :is-highlighted="highlightedId === order.id"
                    :is-team-mode="isTeamMode"
                    @click="$emit('open-menu', order)"
                />
            </TransitionGroup>
        </div>

        <!-- MAP VIEW -->
        <div v-show="viewMode === 'map'" class="flex-1 overflow-y-auto hide-scrollbar min-h-0 relative" :class="isTeamMode ? 'p-0 pb-32 bg-transparent' : 'p-4 bg-surface-1'">
            <RoutingTable
                :table-areas="tableAreas"
                mode="direct"
                @table-click="handleTableClick"
                @load-order="handleLoadOrder"
            />
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import Badge from "primevue/badge";
import OrderCard from "@/Pages/Shared/Order/OrderCard.vue";
import RoutingTable from "@/Pages/Pos/Components/Cart/RoutingTable.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";
import SegmentedControl from "@/Components/UI/SegmentedControl.vue";

const props = defineProps({
    orders: { type: Array, default: () => [] },
    tableAreas: { type: Array, default: () => [] },
    highlightedId: { type: [Number, String], default: null },
    isTeamMode: { type: Boolean, default: false },
    isExpanded: { type: Boolean, default: false }
});

const emit = defineEmits(["open-menu", "create-order"]);
const viewMode = ref('cards');
const isMounted = ref(false);

onMounted(() => { isMounted.value = true; });

const viewOptions = [
    { label: 'Lista', value: 'cards' },
    { label: 'Mapa', value: 'map' }
];

const sortedOrders = computed(() => {
    return [...props.orders].sort((a, b) => {
        let dateA = a.created_at ? new Date(a.created_at) : new Date(0);
        let dateB = b.created_at ? new Date(b.created_at) : new Date(0);
        return dateA - dateB;
    });
});

const handleTableClick = (table, isOccupied, existingOrder) => {
    let fullOrder = existingOrder;
    if (isOccupied && existingOrder) {
        fullOrder = props.orders.find(o => o.id === existingOrder.id) || existingOrder;
    }
    emit('create-order', table, isOccupied, fullOrder);
};

const handleLoadOrder = (orderId) => {
    const fullOrder = props.orders.find(o => o.id === orderId);
    if (fullOrder) {
        emit('open-menu', fullOrder);
    }
};
</script>

<style scoped>
.list-move, .list-enter-active, .list-leave-active {
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.list-enter-from {
  opacity: 0;
  transform: translateY(20px);
}
.list-leave-to {
  opacity: 0;
  transform: scale(0.9);
}
</style>
