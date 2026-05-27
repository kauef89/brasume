
<template>
    <div class="bg-white dark:bg-gray-900 px-4 py-3 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">

        <div class="w-16 flex flex-col items-start shrink-0">
            <span class="font-black text-gray-900 dark:text-gray-100">#{{ order.id }}</span>
            <span v-if="order.status === 'cancelled'" class="text-[9px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded uppercase font-black tracking-widest mt-0.5">Estornado</span>
        </div>

        <div class="flex-[1.5] min-w-[120px]">
            <p class="font-bold text-gray-800 dark:text-gray-200 truncate" :class="{'line-through opacity-50': order.status === 'cancelled'}">
                {{ order.customer_name }}
            </p>
        </div>

        <div class="w-20 flex items-center gap-1.5 text-sm text-gray-500 font-medium shrink-0">
            <i class="pi pi-clock text-[10px]"></i>
            <span>{{ order.time }}</span>
        </div>

        <div class="w-32 flex items-center gap-1.5 text-sm text-gray-500 font-medium truncate shrink-0">
            <i class="pi pi-user text-[10px]"></i>
            <span class="truncate">{{ order.operator }}</span>
        </div>

        <div class="flex-1 flex items-center gap-1.5 text-sm font-bold text-green-600 dark:text-green-400 truncate min-w-[100px]">
            <i v-if="order.payments && order.payments.length > 0" class="pi pi-wallet text-[10px]"></i>
            <span class="truncate">{{ order.payments?.map(p => p.method).join(', ') || 'N/A' }}</span>
        </div>

        <div class="w-28 text-right shrink-0">
            <span class="text-lg font-black" :class="order.status === 'cancelled' ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white'">
                {{ formatCurrency(order.total) }}
            </span>
        </div>

        <div class="w-10 flex justify-end shrink-0">
            <Button
                v-if="order.status !== 'cancelled'"
                icon="pi pi-ellipsis-v"
                variant="text"
                rounded
                severity="secondary"
                @click="toggleMenu"
            />
            <Menu ref="actionMenu" :model="menuItems" :popup="true" />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({ order: Object });
const emit = defineEmits(['action']);

const actionMenu = ref(null);

const toggleMenu = (event) => {
    actionMenu.value.toggle(event);
};

const menuItems = ref([
    {
        label: 'Reimprimir NFC-e',
        icon: 'pi pi-receipt',
        command: () => emit('action', { type: 'print-nfce', order: props.order })
    },
    {
        label: 'Reimprimir Conferência',
        icon: 'pi pi-print',
        command: () => emit('action', { type: 'print-conference', order: props.order })
    },
    { separator: true },
    {
        label: 'Estornar e Clonar',
        icon: 'pi pi-copy',
        class: 'text-red-500 font-bold',
        command: () => emit('action', { type: 'refund', order: props.order })
    },
]);
</script>
