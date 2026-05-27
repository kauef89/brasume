<template>
    <div class="bg-white dark:bg-gray-900 px-4 py-3 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">

        <div class="w-12 shrink-0 flex justify-center">
            <div class="w-8 h-8 rounded-full flex items-center justify-center font-black text-sm"
                 :class="transaction.type === 'in' ? 'bg-green-100 text-green-600' : 'bg-orange-100 text-orange-600'">
                <i class="pi" :class="transaction.type === 'in' ? 'pi-arrow-down' : 'pi-arrow-up'"></i>
            </div>
        </div>

        <div class="w-40 shrink-0">
            <p class="font-black text-gray-800 dark:text-gray-200">
                {{ transaction.type === 'in' ? 'Suprimento' : 'Sangria / Estorno' }}
            </p>
        </div>

        <div class="flex-[2] min-w-0">
            <p class="text-sm text-gray-600 dark:text-gray-400 truncate">
                {{ transaction.description }}
            </p>
        </div>

        <div class="w-32 flex items-center gap-1.5 text-sm text-gray-500 font-medium truncate shrink-0">
            <i class="pi pi-user text-[10px]"></i>
            <span class="truncate">{{ transaction.operator }}</span>
        </div>

        <div class="w-20 flex items-center gap-1.5 text-sm text-gray-500 font-medium shrink-0">
            <i class="pi pi-clock text-[10px]"></i>
            <span>{{ transaction.time }}</span>
        </div>

        <div class="w-32 text-right shrink-0">
            <span class="text-lg font-black" :class="transaction.type === 'in' ? 'text-green-600' : 'text-orange-600'">
                {{ transaction.type === 'in' ? '+' : '-' }} {{ formatCurrency(transaction.amount) }}
            </span>
        </div>

        <div class="w-10 shrink-0"></div>
    </div>
</template>

<script setup>
import { formatCurrency } from '@/Utils/formatters';
defineProps({ transaction: Object });
</script>
