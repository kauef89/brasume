<template>
    <div
        @click="$emit('click', order)"
        class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-gray-400 hover:shadow-md transition-all relative overflow-hidden group cursor-pointer w-full text-left"
    >
        <div
            class="absolute left-0 top-0 bottom-0 w-1"
            :class="colorClass"
        ></div>

        <div class="flex justify-between items-start pl-2 mb-2">
            <div class="flex flex-col overflow-hidden pr-2 w-full">
                <span
                    class="text-[10px] font-black uppercase tracking-wider"
                    :class="textColorClass"
                >
                    {{ typeLabel }}
                </span>

                <span
                    class="font-black text-base text-gray-900 dark:text-gray-100 leading-tight truncate w-full"
                    :title="order.customer_name"
                >
                    {{ abbreviateName(order.customer_name) }}
                </span>

                <span
                    v-if="order.type === 'delivery' && order.customer_phone"
                    class="text-[10px] text-gray-500 dark:text-gray-400 font-bold mt-0.5 truncate"
                >
                    <i class="pi pi-phone text-[8px] mr-1"></i
                    >{{ formatPhone(order.customer_phone) }}
                </span>
            </div>

            <span
                class="text-[10px] font-bold text-gray-400 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded shrink-0"
            >
                #{{ order.id }}
            </span>
        </div>

        <div
            class="flex justify-between items-center pl-2 pt-2 border-t border-gray-100 dark:border-gray-700/50 mt-1"
        >
            <div class="flex flex-col">
                <span class="text-[10px] text-gray-400 font-medium">
                    Consumo total
                </span>
                <span
                    class="text-base font-black text-gray-900 dark:text-gray-100"
                >
                    {{ formatCurrency(order.total) }}
                </span>
            </div>
            <div
                class="flex gap-1 opacity-50 group-hover:opacity-100 transition-opacity text-gray-400"
            >
                <i class="pi pi-ellipsis-h text-xl"></i>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
// 🚀 ATUALIZAÇÃO DOS IMPORTS DE FORMATADORES
import {
    formatCurrency,
    abbreviateName,
    formatPhone,
} from "@/Utils/formatters";

const props = defineProps({ order: Object });
defineEmits(["click"]);

const typeLabel = computed(() => {
    if (props.order.type === "table") return "Mesa";
    if (props.order.type === "counter") return "Balcão";
    return "Delivery";
});

const colorClass = computed(() => {
    if (props.order.type === "table") return "bg-blue-500";
    if (props.order.type === "counter") return "bg-purple-500";
    return "bg-red-500";
});

const textColorClass = computed(() => {
    if (props.order.type === "table") return "text-blue-500";
    if (props.order.type === "counter") return "text-purple-500";
    return "text-red-500";
});
</script>
