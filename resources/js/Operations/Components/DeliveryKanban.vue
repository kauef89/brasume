<template>
    <div
        class="flex-1 flex overflow-x-auto bg-gray-100 dark:bg-gray-950 p-4 gap-4 hide-scrollbar"
    >
        <div
            v-for="column in kanbanColumns"
            :key="column.id"
            class="flex flex-col w-72 shrink-0 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden"
        >
            <div
                class="p-3 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center"
                :class="column.headerClass"
            >
                <h3 class="font-black uppercase tracking-widest text-[11px]">
                    {{ column.label }}
                </h3>
                <Badge
                    :value="getOrdersByStatus(column.id).length"
                    :severity="column.badgeSeverity"
                />
            </div>

            <div
                class="flex-1 overflow-y-auto p-2 flex flex-col gap-4 hide-scrollbar"
            >
                <div
                    v-for="order in getOrdersByStatus(column.id)"
                    :key="order.id"
                    class="flex flex-col gap-1.5"
                >
                    <OrderCard
                        :order="order"
                        @click="$emit('open-menu', order)"
                    />

                    <Button
                        v-if="column.nextStatus"
                        :icon="column.actionIcon"
                        :label="column.actionLabel"
                        size="small"
                        variant="outlined"
                        severity="secondary"
                        class="w-full text-xs font-bold bg-white dark:bg-gray-800"
                        @click.stop="
                            $emit('advance-status', order, column.nextStatus)
                        "
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from "vue";
import Badge from "primevue/badge";
import Button from "primevue/button";
import OrderCard from "./OrderCard.vue"; // 🚀 Importado!

const props = defineProps({ orders: { type: Array, default: () => [] } });
defineEmits(["advance-status", "open-menu"]);

const kanbanColumns = [
    {
        id: "pending",
        label: "Entrada",
        headerClass:
            "bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400",
        badgeSeverity: "danger",
        nextStatus: "preparing",
        actionIcon: "pi pi-fire",
        actionLabel: "Iniciar Preparo",
    },
    {
        id: "preparing",
        label: "Em Preparação",
        headerClass:
            "bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400",
        badgeSeverity: "warn",
        nextStatus: "ready",
        actionIcon: "pi pi-check",
        actionLabel: "Pronto",
    },
    {
        id: "ready",
        label: "A Despachar",
        headerClass:
            "bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400",
        badgeSeverity: "info",
        nextStatus: "dispatched",
        actionIcon: "pi pi-send",
        actionLabel: "Despachar",
    },
    {
        id: "dispatched",
        label: "Em Rota",
        headerClass:
            "bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400",
        badgeSeverity: "secondary",
        nextStatus: null,
        actionIcon: null,
        actionLabel: null,
    },
];

const getOrdersByStatus = (status) =>
    props.orders.filter((o) => o.status === status);
</script>

<style scoped>
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
