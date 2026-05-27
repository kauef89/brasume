<template>
    <div class="flex-1 overflow-y-auto p-8 flex flex-col gap-6 hide-scrollbar">

        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800">
            <div class="flex justify-between items-center mb-2 text-sm font-bold text-gray-500">
                <span>Subtotal (Itens)</span>
                <span class="text-gray-800 dark:text-gray-200">{{ formatCurrency(orderSubtotal) }}</span>
            </div>

            <div v-if="deliveryFee > 0" class="flex justify-between items-center mb-2 text-sm font-bold text-gray-500">
                <span>Taxa de Entrega</span>
                <span class="text-gray-800 dark:text-gray-200">{{ formatCurrency(deliveryFee) }}</span>
            </div>

            <div v-if="companySettings?.has_service_tax" class="flex justify-between items-center pb-4 mb-4 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2 text-sm font-bold text-gray-500">
                    <span>Gorjeta ({{ companySettings.service_tax_percentage }}%)</span>
                    <button @click="$emit('update:isServiceTaxEnabled', !isServiceTaxEnabled)" class="text-[10px] bg-gray-100 px-2 py-0.5 rounded text-blue-600 uppercase tracking-widest hover:bg-gray-200">
                        {{ isServiceTaxEnabled ? 'Remover' : 'Adicionar' }}
                    </button>
                </div>
                <span class="font-bold text-gray-800 dark:text-gray-200">
                    {{ isServiceTaxEnabled ? formatCurrency(serviceFeeAmount) : 'R$ 0,00' }}
                </span>
            </div>

            <div class="flex justify-between items-center mt-2">
                <span class="text-lg font-black uppercase tracking-widest">Total a Pagar</span>
                <span class="text-3xl font-black text-blue-600 dark:text-blue-400">{{ formatCurrency(dynamicTotal) }}</span>
            </div>
        </div>

        <PaymentStagingArea
            :available-methods="mappedMethods"
            :staged-payments="stagedPayments"
            :remaining-amount="pendingToStage"
            :allow-change-target="false"
            @add-payment="handleAddPayment"
            @remove-payment="$emit('remove-payment', $event)"
        >
            <template #extra-actions>
                <div class="grid grid-cols-4 gap-2 mb-2">
                    <button v-for="div in [2, 3, 4, 5]" :key="div" @click="$emit('divide', div)" class="py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-black text-gray-600 hover:bg-gray-100 transition-colors">
                        ÷{{ div }}
                    </button>
                </div>
            </template>
        </PaymentStagingArea>

    </div>
</template>

<script setup>
import { computed } from "vue";
import PaymentStagingArea from "../Shared/PaymentStagingArea.vue";
import { formatCurrency } from "@/Utils/formatters";

const props = defineProps({
    orderSubtotal: Number,
    deliveryFee: Number,
    serviceFeeAmount: Number,
    dynamicTotal: Number,
    pendingToStage: Number,
    stagedPayments: Array,
    paymentMethods: Array, // Vem do Banco de Dados
    companySettings: Object,
    isServiceTaxEnabled: Boolean
});

const emit = defineEmits(["update:isServiceTaxEnabled", "divide", "add-payment", "remove-payment"]);

const getIconForEngine = (engineType) => {
    switch (engineType) {
        case "money": return "pi pi-money-bill";
        case "manual": return "pi pi-credit-card";
        case "pix_sicredi": return "pi pi-qrcode";
        default: return "pi pi-wallet";
    }
};

// Transforma os métodos do banco no formato que o componente burro entende
const mappedMethods = computed(() => {
    return props.paymentMethods.map(m => ({
        id: m.id,
        name: m.name,
        icon: getIconForEngine(m.engine_type),
        is_cash: m.engine_type === 'money'
    }));
});

const handleAddPayment = (payload) => {
    // Repassa para o pai no formato que o Checkout original espera
    emit('add-payment', { method_id: payload.method_id, amount: payload.amount });
};
</script>
