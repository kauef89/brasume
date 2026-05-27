<template>
    <div
        class="flex flex-col h-full bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 shadow-sm w-80 shrink-0"
    >
        <div
            class="p-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex justify-between items-center"
        >
            <h2
                class="font-black text-gray-800 dark:text-gray-100 uppercase tracking-widest text-sm"
            >
                Mesas & Comandas
            </h2>
            <Badge :value="orders.length" severity="info" />
        </div>

        <div
            class="flex-1 overflow-y-auto p-3 flex flex-col gap-3 bg-gray-100/50 dark:bg-gray-900/50 hide-scrollbar"
        >
            <div
                v-if="orders.length === 0"
                class="text-center text-gray-400 my-auto text-sm"
            >
                Nenhuma mesa ou comanda aberta.
            </div>

            <OrderCard
                v-for="order in orders"
                :key="order.id"
                :order="order"
                @click="$emit('open-menu', order)"
            />
        </div>
    </div>
</template>

<script setup>
import Badge from "primevue/badge";
import OrderCard from "./OrderCard.vue";

defineProps({ orders: { type: Array, default: () => [] } });
defineEmits(["open-menu"]);
</script>

<style scoped>
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
