<template>
    <div class="flex flex-col gap-6 w-full">
        <div v-if="stagedPayments.length > 0" class="flex flex-col gap-2">
            <div v-for="(p, idx) in stagedPayments" :key="idx" class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex flex-col">
                    <span class="font-bold text-gray-800 dark:text-gray-200">{{ p.method_name }}</span>
                    <span v-if="p.change_target" class="text-xs font-bold text-orange-500 mt-0.5">
                        Troco para {{ formatCurrency(p.change_target) }}
                    </span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="font-black text-lg text-gray-900 dark:text-white">{{ formatCurrency(p.amount) }}</span>
                    <Button icon="pi pi-trash" variant="text" severity="danger" rounded class="w-8 h-8 p-0" @click="$emit('remove-payment', idx)" />
                </div>
            </div>
        </div>

        <div v-if="remainingAmount > 0" class="flex flex-col gap-4">

            <div class="flex gap-2">
                <div class="relative flex-1">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-black text-gray-400">R$</span>
                    <input
                        type="number"
                        v-model.number="localAmount"
                        class="w-full text-2xl font-black text-right p-4 pl-12 rounded-2xl border-2 border-gray-200 dark:border-gray-700 focus:border-blue-500 bg-white dark:bg-gray-900 outline-none transition-all"
                    />
                </div>
                <button
                    @click="localAmount = remainingAmount"
                    class="px-5 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold border border-gray-200 dark:border-gray-700 hover:bg-gray-200 transition-colors"
                >
                    Restante
                </button>
            </div>

            <slot name="extra-actions"></slot>

            <div v-if="pendingCashMethod" class="p-5 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-2xl animate-fade-in flex flex-col gap-3">
                <label class="text-xs font-black text-orange-800 dark:text-orange-400 uppercase tracking-widest">
                    Troco para quanto? (R$)
                </label>
                <input
                    type="number"
                    v-model.number="localChangeTarget"
                    :placeholder="'Ex: ' + (localAmount + 10)"
                    class="p-4 rounded-xl border border-orange-300 dark:border-orange-700 text-right font-black text-xl bg-white dark:bg-gray-950"
                />
                <div class="flex justify-end gap-2 mt-1">
                    <Button label="Sem Troco" size="small" variant="text" severity="secondary" @click="confirmPayment(false)" />
                    <Button label="Confirmar" size="small" severity="warning" @click="confirmPayment(true)" :disabled="localChangeTarget <= localAmount" />
                </div>
            </div>

            <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <button
                    v-for="method in availableMethods"
                    :key="method.id"
                    @click="handleMethodClick(method)"
                    :disabled="!localAmount || localAmount <= 0"
                    class="p-4 rounded-xl border-2 border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:border-blue-500 hover:bg-blue-50 hover:text-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex flex-col items-center justify-center gap-2 transition-all shadow-sm"
                >
                    <i :class="method.icon" class="text-2xl"></i>
                    <span class="font-bold text-[11px] uppercase tracking-wider text-center leading-tight">{{ method.name }}</span>
                </button>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import Button from 'primevue/button';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    availableMethods: Array, // [{ id, name, icon, is_cash }]
    stagedPayments: Array,   // [{ method_id, method_name, amount, change_target }]
    remainingAmount: Number,
    allowChangeTarget: Boolean // true para Motoboy, false para Caixa
});

const emit = defineEmits(['add-payment', 'remove-payment']);

const localAmount = ref(props.remainingAmount);
const pendingCashMethod = ref(null);
const localChangeTarget = ref(null);

// Mantém o input numérico sempre atualizado com o que falta pagar
watch(() => props.remainingAmount, (newVal) => {
    if (newVal > 0) localAmount.value = newVal;
});

const handleMethodClick = (method) => {
    if (!localAmount.value || localAmount.value <= 0) return;

    if (props.allowChangeTarget && method.is_cash) {
        pendingCashMethod.value = method;
        localChangeTarget.value = null;
    } else {
        emit('add-payment', {
            method_id: method.id,
            method_name: method.name,
            amount: Number(localAmount.value),
            change_target: null
        });
        localAmount.value = null;
    }
};

const confirmPayment = (withChange) => {
    emit('add-payment', {
        method_id: pendingCashMethod.value.id,
        method_name: pendingCashMethod.value.name,
        amount: Number(localAmount.value),
        change_target: withChange ? Number(localChangeTarget.value) : null
    });

    pendingCashMethod.value = null;
    localChangeTarget.value = null;
    localAmount.value = null;
};
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.2s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
</style>
