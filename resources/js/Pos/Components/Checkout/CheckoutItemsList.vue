<template>
    <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 hide-scrollbar bg-gray-50/50 dark:bg-gray-900/50">

        <div class="space-y-3">
            <h3 class="text-xs font-black text-gray-500 uppercase tracking-widest mb-3 border-b border-gray-200 dark:border-gray-800 pb-2">
                Itens a Pagar
            </h3>
            <CheckoutItem
                v-for="item in activeItems" :key="item.unique_id" :item="item"
                @increment="$emit('increment', $event)" @decrement="$emit('decrement', $event)"
                @divide-item="(itemToDivide, divisor) => $emit('divide-item', itemToDivide, divisor)"
                @toggle-menu="toggleMenu" @toggle-select="$emit('toggle-select', $event)"
            />
        </div>

        <div v-if="paidItems.length > 0 || orderPayments.length > 0" class="mt-4 animate-fade-in">
            <h3 class="text-xs font-black text-green-600 uppercase tracking-widest mb-3 border-b border-green-200 dark:border-green-900/50 pb-2">
                Pagamentos Realizados
            </h3>

            <div v-if="orderPayments.length > 0" class="flex flex-wrap gap-2 mb-4">
                <div v-for="pay in orderPayments" :key="pay.id" class="flex items-center gap-2 bg-white dark:bg-gray-800 border border-green-200 dark:border-green-800 px-3 py-1.5 rounded-lg shadow-sm">
                    <span class="text-green-600 font-bold text-xs"><i class="pi pi-check-circle mr-1"></i> {{ pay.method }}</span>
                    <span class="text-gray-400 text-xs font-medium">{{ pay.created_at }}</span>
                    <span class="text-gray-800 dark:text-gray-200 font-black text-sm ml-2">{{ formatCurrency(pay.amount) }}</span>
                </div>
            </div>

            <div class="flex flex-col gap-2 opacity-70">
                <div v-for="item in paidItems" :key="item.unique_id + '_paid'" class="flex items-center gap-3 bg-green-50 dark:bg-green-900/10 p-2.5 rounded-xl border border-green-100 dark:border-green-900/30">
                    <div class="w-10 text-center font-black text-green-700 dark:text-green-500 bg-green-100 dark:bg-green-900/40 rounded py-0.5 text-xs">
                        {{ formatFraction(item.paid_parts, item.parts_per_unit) }}
                    </div>
                    <div class="flex-1 flex flex-col">
                        <span class="font-bold text-green-800 dark:text-green-400 text-sm">{{ item.name }}</span>
                    </div>
                    <div class="text-right font-black text-green-700 dark:text-green-500 text-sm">
                        {{ formatCurrency(item.paid_parts * (item.effective_unit_price / item.parts_per_unit)) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Menu ref="contextMenu" :model="menuItems" :popup="true" />
</template>

<script setup>
import { ref, computed, defineProps, defineEmits } from "vue";
import Menu from "primevue/menu";
import CheckoutItem from "./CheckoutItem.vue";
import { formatCurrency, formatFraction } from "@/Utils/formatters";

const props = defineProps({ displayItems: Array, orderPayments: { type: Array, default: () => [] } }); // 🚀 Adicionada a prop dos pagamentos
const emit = defineEmits(["increment", "decrement", "divide-item", "quick-add", "quick-cancel", "toggle-select"]);

const activeItems = computed(() => props.displayItems.filter((i) => i.available_parts > 0));
const paidItems = computed(() => props.displayItems.filter((i) => i.paid_parts > 0));

const contextMenu = ref(null);
const activeContextItem = ref(null);

const menuItems = ref([
    { label: "Adicionar Lançamento (+1)", icon: "pi pi-plus-circle", command: () => emit("quick-add", activeContextItem.value) },
    { separator: true },
    { label: "Estornar (Requer Senha)", icon: "pi pi-trash", class: "text-red-500 font-bold", command: () => emit("quick-cancel", activeContextItem.value) },
]);

const toggleMenu = (event, item) => { activeContextItem.value = item; contextMenu.value.toggle(event); };
</script>
