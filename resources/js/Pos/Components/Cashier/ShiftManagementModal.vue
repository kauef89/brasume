<template>
    <Dialog
        :visible="visible"
        modal
        :header="actionType === 'open' ? 'Abertura de Caixa' : 'Fechamento de Caixa'"
        :style="{ width: '500px' }"
        @update:visible="$emit('update:visible', $event)"
        class="bg-white dark:bg-gray-900 rounded-2xl"
    >
        <div class="py-2 flex flex-col gap-6">

            <div v-if="actionType === 'open' && companySettings.carry_over_mode !== 'none'" class="bg-blue-50 dark:bg-blue-900/30 p-3 rounded-lg flex items-start gap-3">
                <i class="pi pi-info-circle text-blue-500 mt-0.5"></i>
                <div class="text-sm text-blue-800 dark:text-blue-300">
                    <p class="font-bold">Importação Automática</p>
                    <p v-if="companySettings.carry_over_mode === 'total'">O sistema considerará todo o saldo do último fechamento como fundo de troco inicial.</p>
                    <p v-if="companySettings.carry_over_mode === 'coins_only'">O sistema importará apenas o valor em moedas/troco miúdo do último fechamento.</p>
                </div>
            </div>

            <div v-if="companySettings.cash_opening_mode === 'total'">
                <label class="block text-sm font-black text-gray-500 uppercase tracking-widest mb-3">
                    {{ actionType === 'open' ? 'Fundo de Troco (R$)' : 'Valor Total em Gaveta (R$)' }}
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-black text-gray-400">R$</span>
                    <input
                        type="number" v-model="simpleTotal"
                        class="w-full text-3xl font-black text-right p-4 pl-12 rounded-xl border-2 border-gray-200 focus:border-blue-500 bg-gray-50 outline-none"
                    />
                </div>
            </div>

            <div v-if="companySettings.cash_opening_mode === 'detailed'" class="flex flex-col gap-4">
                <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded-xl text-center">
                    <span class="text-xs font-black text-gray-500 uppercase tracking-widest">Total Contado</span>
                    <div class="text-4xl font-black text-green-600">{{ formatCurrency(calculatedTotal) }}</div>
                </div>

                <div class="grid grid-cols-2 gap-4 h-[40vh] overflow-y-auto pr-2">
                    <div class="flex flex-col gap-2">
                        <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest border-b pb-1">Cédulas</h4>
                        <div v-for="(val, key) in moneyValues.bills" :key="key" class="flex items-center justify-between gap-2">
                            <span class="font-bold text-gray-700 dark:text-gray-300 w-12 text-right">{{ formatCurrency(val) }}</span>
                            <span class="text-gray-400 text-xs">x</span>
                            <input type="number" v-model.number="counts[key]" min="0" class="w-16 p-1.5 text-center font-bold border rounded-lg bg-white" placeholder="0" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest border-b pb-1">Moedas</h4>
                        <div v-for="(val, key) in moneyValues.coins" :key="key" class="flex items-center justify-between gap-2">
                            <span class="font-bold text-gray-700 dark:text-gray-300 w-12 text-right">{{ formatCurrency(val) }}</span>
                            <span class="text-gray-400 text-xs">x</span>
                            <input type="number" v-model.number="counts[key]" min="0" class="w-16 p-1.5 text-center font-bold border rounded-lg bg-white" placeholder="0" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-gray-500 uppercase">Observação (Opcional)</label>
                <textarea v-model="observation" rows="2" class="w-full p-2 border rounded-lg resize-none"></textarea>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2 w-full pt-4">
                <Button label="Cancelar" variant="text" severity="secondary" @click="$emit('update:visible', false)" />
                <Button :label="actionType === 'open' ? 'Abrir Caixa' : 'Confirmar Fechamento'" icon="pi pi-check" @click="handleConfirm" />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    visible: Boolean,
    actionType: String, // 'open' ou 'close'
    companySettings: Object
});
const emit = defineEmits(['update:visible', 'confirm']);

// Estado
const simpleTotal = ref(0);
const observation = ref("");

// Dicionário de valores financeiros
const moneyValues = {
    bills: { b200: 200, b100: 100, b50: 50, b20: 20, b10: 10, b5: 5, b2: 2 },
    coins: { c1: 1, c50: 0.5, c25: 0.25, c10: 0.1, c5: 0.05 }
};

// Contadores reativos (iniciam a 0)
const counts = ref({});
const resetCounts = () => {
    Object.keys(moneyValues.bills).forEach(k => counts.value[k] = 0);
    Object.keys(moneyValues.coins).forEach(k => counts.value[k] = 0);
    simpleTotal.value = 0;
    observation.value = "";
};

// Calcula a soma da grade
const calculatedTotal = computed(() => {
    let sum = 0;
    Object.keys(moneyValues.bills).forEach(k => sum += (counts.value[k] || 0) * moneyValues.bills[k]);
    Object.keys(moneyValues.coins).forEach(k => sum += (counts.value[k] || 0) * moneyValues.coins[k]);
    return sum;
});

// Limpa tudo ao abrir o modal
watch(() => props.visible, (newVal) => { if (newVal) resetCounts(); });

const handleConfirm = () => {
    const finalAmount = props.companySettings.cash_opening_mode === 'detailed' ? calculatedTotal.value : Number(simpleTotal.value);

    // Empacotamos os detalhes para guardar no banco caso queira auditar no futuro
    const payload = {
        amount: finalAmount,
        observation: observation.value,
        details: props.companySettings.cash_opening_mode === 'detailed' ? counts.value : null
    };

    emit('confirm', payload);
};
</script>
