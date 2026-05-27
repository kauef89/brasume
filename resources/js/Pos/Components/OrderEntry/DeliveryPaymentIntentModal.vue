<template>
    <Dialog
        :visible="visible"
        modal
        header="Instruções para o Motoboy"
        :style="{ width: '550px' }"
        @update:visible="$emit('update:visible', $event)"
        class="bg-white dark:bg-gray-900 rounded-2xl"
    >
        <div class="flex flex-col gap-4 py-2">

            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl flex justify-between items-center border border-gray-200 dark:border-gray-700">
                <span class="text-xs font-black text-gray-500 uppercase tracking-wider">Total do Pedido</span>
                <span class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ formatCurrency(orderTotal) }}</span>
            </div>

            <div v-if="currentStep === 'mode'" class="flex flex-col gap-3">
                <button @click="selectMode('pending_choice')" class="p-4 border-2 rounded-xl text-left transition-all hover:border-purple-400 flex flex-col gap-1 border-gray-200 dark:border-gray-700">
                    <span class="font-black text-gray-800 dark:text-gray-200"><i class="pi pi-clock mr-1 text-purple-500"></i> Decidir Depois</span>
                    <span class="text-xs text-gray-500">Manda direto para a cozinha. Será exigido na expedição.</span>
                </button>

                <button @click="selectMode('pay_on_delivery')" class="p-4 border-2 rounded-xl text-left transition-all hover:border-blue-400 flex flex-col gap-1 border-gray-200 dark:border-gray-700">
                    <span class="font-black text-gray-800 dark:text-gray-200"><i class="pi pi-motorcycle mr-1 text-blue-500"></i> Pagar na Entrega (Motoboy)</span>
                    <span class="text-xs text-gray-500">Compor o pagamento para o motoboy levar troco ou máquina.</span>
                </button>

                <button @click="selectMode('pay_now')" class="p-4 border-2 rounded-xl text-left transition-all hover:border-green-400 flex flex-col gap-1 border-gray-200 dark:border-gray-700">
                    <span class="font-black text-gray-800 dark:text-gray-200"><i class="pi pi-wallet mr-1 text-green-500"></i> Pagar Agora na Lanchonete</span>
                    <span class="text-xs text-gray-500">Vai para o Caixa imediatamente receber o dinheiro físico.</span>
                </button>
            </div>

            <div v-if="currentStep === 'payment_panel'" class="animate-fade-in">
                <PaymentStagingArea
                    :available-methods="logisticMethods"
                    :staged-payments="promises"
                    :remaining-amount="remainingAmount"
                    :allow-change-target="true"
                    @add-payment="handleAddPromise"
                    @remove-payment="handleRemovePromise"
                />
            </div>
        </div>

        <template #footer>
            <div class="flex justify-between gap-2 pt-4 border-t border-gray-100 dark:border-gray-800 w-full mt-4">
                <div>
                    <Button v-if="currentStep === 'payment_panel'" label="Voltar" icon="pi pi-arrow-left" variant="text" severity="secondary" @click="currentStep = 'mode'" />
                </div>
                <div class="flex gap-2">
                    <Button label="Cancelar" variant="text" severity="secondary" @click="$emit('update:visible', false)" />
                    <Button v-if="currentStep === 'payment_panel'" label="Confirmar Roteiro" icon="pi pi-check" :disabled="remainingAmount > 0" @click="handleConfirmFinal" />
                </div>
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import PaymentStagingArea from '../Shared/PaymentStagingArea.vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({ visible: Boolean, orderTotal: Number });
const emit = defineEmits(['update:visible', 'confirm']);

const currentStep = ref('mode');
const finalIntent = ref('pending_choice');
const promises = ref([]);

// "Falso" banco de dados apenas com instruções logísticas
const logisticMethods = [
    { id: 'Dinheiro', name: 'Dinheiro Físico', icon: 'pi pi-money-bill', is_cash: true },
    { id: 'Cartão de Crédito', name: 'Máquina (Crédito)', icon: 'pi pi-credit-card', is_cash: false },
    { id: 'Cartão de Débito', name: 'Máquina (Débito)', icon: 'pi pi-credit-card', is_cash: false },
    { id: 'Pix', name: 'Chave PIX (QR Code)', icon: 'pi pi-qrcode', is_cash: false }
];

watch(() => props.visible, (val) => {
    if (val) {
        currentStep.value = 'mode';
        promises.value = [];
    }
});

const remainingAmount = computed(() => {
    const allocated = promises.value.reduce((sum, p) => sum + p.amount, 0);
    return Math.max(0, Number((props.orderTotal - allocated).toFixed(2)));
});

const selectMode = (mode) => {
    finalIntent.value = mode;
    if (mode === 'pay_on_delivery') {
        currentStep.value = 'payment_panel';
    } else {
        emit('confirm', { intent: mode, delivery_payment_status: 'pending_choice', delivery_payment_methods: null });
    }
};

const handleAddPromise = (payload) => {
    promises.value.push(payload);
};

const handleRemovePromise = (idx) => {
    promises.value.splice(idx, 1);
};

const handleConfirmFinal = () => {
    emit('confirm', {
        intent: 'pay_on_delivery',
        delivery_payment_status: 'pay_on_delivery',
        delivery_payment_methods: promises.value
    });
};
</script>
