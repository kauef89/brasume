<template>
    <div class="flex flex-col h-full bg-surface-2 rounded-4xl p-4 md:p-6 gap-4 md:gap-6 overflow-hidden w-full">

        <div class="flex justify-between items-center shrink-0">
            <div>
                <h2 class="font-black text-surface-text text-xl">Entregas & Balcão</h2>
            </div>
            <Badge :value="orders.length" severity="warn" />
        </div>

        <div class="flex-1 overflow-x-auto hide-scrollbar min-h-0">
            <div class="grid grid-cols-4 gap-2 h-full min-w-[800px] lg:min-w-0">

                <div
                    v-for="column in kanbanColumns"
                    :key="column.id"
                    class="flex flex-col bg-surface-3 rounded-[1.5rem] overflow-hidden h-full min-h-[400px]"
                >
                    <div class="p-3 md:p-4 flex justify-between items-center bg-surface-3 shrink-0">
                        <h3 class="font-black text-surface-text text-sm">{{ column.label }}</h3>
                        <Badge :value="columnsData[column.id]?.length || 0" :severity="column.badgeSeverity" />
                    </div>

                    <!-- Draggable container para as raias do Kanban -->
                    <draggable
                        v-model="columnsData[column.id]"
                        group="kanban-delivery"
                        item-key="id"
                        class="flex-1 overflow-y-auto p-3 flex flex-col gap-2 hide-scrollbar min-h-0"
                        ghost-class="opacity-40"
                        @change="onDragChange($event, column.id)"
                    >
                        <template #item="{ element: order }">
                            <div class="flex flex-col">
                                <OrderCard
                                    :order="order"
                                    @click="$emit('open-menu', order)"
                                    @move-prev="moveOrderPrev(order)"
                                    @move-next="moveOrderNext(order)"
                                    class="cursor-grab active:cursor-grabbing hover:scale-[1.02] transition-transform"
                                />
                            </div>
                        </template>
                    </draggable>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, defineProps, defineEmits } from "vue";
import Badge from "primevue/badge";
import OrderCard from "@/Pages/Shared/Order/OrderCard.vue";
import draggable from "vuedraggable";

const props = defineProps({ orders: { type: Array, default: () => [] } });
const emit = defineEmits(["advance-status", "open-menu"]);

const kanbanColumns = [
    { id: "pending", label: "Entrada", badgeSeverity: "secondary" },
    { id: "preparing", label: "Em Preparo", badgeSeverity: "secondary" },
    { id: "ready", label: "Despachando", badgeSeverity: "secondary" },
    { id: "dispatched", label: "Em Rota", badgeSeverity: "secondary" },
];

const columnsData = ref({
    pending: [],
    preparing: [],
    ready: [],
    dispatched: []
});

// Normaliza status para a coluna correspondente
const getColumnForStatus = (status) => {
    if (status === 'pending' || status === 'pending_payment') return 'pending';
    return status;
};

watch(() => props.orders, (newOrders) => {
    kanbanColumns.forEach(col => {
        columnsData.value[col.id] = (newOrders || [])
            .filter(o => getColumnForStatus(o.status) === col.id)
            .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    });
}, { immediate: true, deep: true });

const onDragChange = (event, newStatus) => {
    if (event.added) {
        const droppedOrder = event.added.element;
        emit('advance-status', droppedOrder, newStatus);
    }
};

// 🚀 NAVEGAÇÃO DAS SETINHAS
const moveOrderPrev = (order) => {
    if (!order) return;
    const currentStatus = getColumnForStatus(order.status);
    const currentIndex = kanbanColumns.findIndex(c => c.id === currentStatus);
    if (currentIndex > 0) {
        const previousStatus = kanbanColumns[currentIndex - 1].id;
        emit('advance-status', order, previousStatus);
    }
};

const moveOrderNext = (order) => {
    if (!order) return;
    const currentStatus = getColumnForStatus(order.status);
    const currentIndex = kanbanColumns.findIndex(c => c.id === currentStatus);

    if (currentStatus === 'dispatched' || currentIndex === kanbanColumns.length - 1) {
        emit('advance-status', order, 'closed');
        return;
    }

    if (currentIndex < kanbanColumns.length - 1 && currentIndex !== -1) {
        const nextStatus = kanbanColumns[currentIndex + 1].id;
        emit('advance-status', order, nextStatus);
    }
};
</script>
