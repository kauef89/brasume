<template>
    <BaseModal
        :modelValue="visible"
        @update:modelValue="$emit('update:visible', $event)"
        :title="actionType === 'open' ? 'Abertura de Caixa' : 'Fechamento de Caixa'"
        :icon="actionType === 'open' ? 'pi pi-lock-open' : 'pi pi-lock'"
        :iconColor="actionType === 'open' ? 'success' : 'danger'"
        width="500px"
    >
        <div class="py-2 flex flex-col gap-6">

            <!-- AVISO DE CONTAGEM CEGA -->
            <div v-if="actionType === 'close' && companySettings.cash_management_mode === 'employee'" class="bg-purple-50 dark:bg-purple-900/30 p-3 rounded-lg flex items-start gap-3 border border-purple-200 dark:border-purple-800">
                <i class="pi pi-eye-slash text-purple-500 mt-0.5"></i>
                <div class="text-sm text-purple-800 dark:text-purple-300">
                    <p class="font-medium">Contagem Cega Ativada</p>
                    <p class="text-xs mt-0.5">Conte o dinheiro físico com atenção. Diferenças acima do limite exigirão autorização do Gestor.</p>
                </div>
            </div>

            <!-- MODO: VALOR FECHADO (TOTAL) -->
            <div v-if="companySettings.cash_opening_mode === 'total'">
                <label class="block text-sm font-black text-stone-500 uppercase tracking-widest mb-3">
                    {{ actionType === 'open' ? 'Fundo de Troco (R$)' : 'Valor Total em Gaveta (R$)' }}
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-black text-stone-400 z-10">R$</span>
                    <InputNumber v-model="simpleTotal" mode="decimal" :minFractionDigits="2" class="w-full text-3xl font-black [&>input]:text-right [&>input]:pl-12" />
                </div>

                <!-- 🚀 PERGUNTA DE RETENÇÃO DE MOEDAS NO FECHAMENTO -->
                <div v-if="actionType === 'close' && companySettings.carry_over_mode === 'coins_only'" class="mt-4 p-4 bg-surface-2 rounded-xl border border-surface-border">
                    <label class="block text-xs font-black text-primary-600 uppercase tracking-widest mb-2">
                        Desse total, quanto ficará de troco para amanhã? (Moedas/Notas menores)
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-stone-400 z-10">R$</span>
                        <InputNumber v-model="retainedCoins" mode="decimal" :minFractionDigits="2" :max="simpleTotal" class="w-full text-lg font-medium [&>input]:text-right [&>input]:pl-10" />
                    </div>
                </div>
            </div>

            <!-- MODO: CONTAGEM DETALHADA -->
            <div v-if="companySettings.cash_opening_mode === 'detailed'" class="flex flex-col gap-4">
                <div class="bg-stone-100 dark:bg-stone-800 p-4 rounded-xl text-center flex flex-col items-center justify-center border border-surface-border">
                    <span class="text-xs font-black text-stone-500 uppercase tracking-widest">Total Contado Fisicamente</span>
                    <div class="text-4xl font-black text-green-600">
                        {{ $formatCurrency(calculatedTotal) }}
                    </div>
                    <div v-if="actionType === 'close' && companySettings.carry_over_mode === 'coins_only'" class="mt-1 text-[10px] font-medium text-primary-500 uppercase tracking-widest">
                        {{ $formatCurrency(coinsTotal) }} retidos para o próximo turno
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 h-[35vh] overflow-y-auto pr-2 hide-scrollbar">
                    <div class="flex flex-col gap-2">
                        <h4 class="text-xs font-black text-stone-500 uppercase tracking-widest border-b pb-1">Cédulas</h4>
                        <div v-for="(val, key) in moneyValues.bills" :key="key" class="flex items-center justify-between gap-2">
                            <span class="font-medium text-stone-700 dark:text-stone-300 w-12 text-right">{{ $formatCurrency(val) }}</span>
                            <span class="text-stone-400 text-xs">x</span>
                            <InputNumber v-model="counts[key]" :min="0" class="w-16 [&>input]:text-center font-medium" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <h4 class="text-xs font-black text-stone-500 uppercase tracking-widest border-b pb-1">Moedas</h4>
                        <div v-for="(val, key) in moneyValues.coins" :key="key" class="flex items-center justify-between gap-2">
                            <span class="font-medium text-stone-700 dark:text-stone-300 w-12 text-right">{{ $formatCurrency(val) }}</span>
                            <span class="text-stone-400 text-xs">x</span>
                            <InputNumber v-model="counts[key]" :min="0" class="w-16 [&>input]:text-center font-medium" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-xs font-medium text-stone-500 uppercase tracking-widest">Observação (Opcional)</label>
                <InputText v-model="observation" placeholder="Relate algo sobre o turno..." class="w-full" />
            </div>
        </div>

        <template #footer>
            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" @secure-click="$emit('update:visible', false)" />
            <SecureActionButton :action-code="actionType === 'open' ? 'open_shift' : 'close_shift'" :label="actionType === 'open' ? 'Abrir Caixa' : 'Confirmar Fechamento'" icon="pi-check" :disabled="isConfirmDisabled" severity="primary" custom-class="h-11 px-4 rounded-xl text-sm font-bold" @secure-click="handleConfirm" />
        </template>
    </BaseModal>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';

import { ref, computed, watch } from 'vue';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({ visible: Boolean, actionType: String, companySettings: Object });
const emit = defineEmits(['update:visible', 'confirm']);

const simpleTotal = ref(0);
const retainedCoins = ref(0);
const observation = ref("");

const moneyValues = {
    bills: { b200: 200, b100: 100, b50: 50, b20: 20, b10: 10, b5: 5, b2: 2 },
    coins: { c1: 1, c50: 0.5, c25: 0.25, c10: 0.1, c5: 0.05 }
};

const counts = ref({});

const resetCounts = () => {
    Object.keys(moneyValues.bills).forEach(k => counts.value[k] = 0);
    Object.keys(moneyValues.coins).forEach(k => counts.value[k] = 0);
    simpleTotal.value = 0;
    retainedCoins.value = 0;
    observation.value = "";
};

// Soma apenas as moedas (para o modo detalhado)
const coinsTotal = computed(() => {
    let sum = 0;
    Object.keys(moneyValues.coins).forEach(k => sum += (counts.value[k] || 0) * moneyValues.coins[k]);
    return sum;
});

const calculatedTotal = computed(() => {
    let sum = 0;
    Object.keys(moneyValues.bills).forEach(k => sum += (counts.value[k] || 0) * moneyValues.bills[k]);
    sum += coinsTotal.value;
    return sum;
});

const isConfirmDisabled = computed(() => {
    if (props.actionType === 'open') return false;
    // Impede o usuário de reter mais troco do que o total que ele informou na gaveta
    if (props.companySettings.cash_opening_mode === 'total' && props.companySettings.carry_over_mode === 'coins_only') {
        return retainedCoins.value > simpleTotal.value;
    }
    return false;
});

watch(() => props.visible, (newVal) => { if (newVal) resetCounts(); });

const handleConfirm = () => {
    const finalAmount = props.companySettings.cash_opening_mode === 'detailed' ? calculatedTotal.value : Number(simpleTotal.value);

    // Calcula automaticamente o fundo de troco retido para enviar ao backend
    let retainedAmount = 0;
    if (props.actionType === 'close') {
        if (props.companySettings.carry_over_mode === 'total') {
            retainedAmount = finalAmount;
        } else if (props.companySettings.carry_over_mode === 'coins_only') {
            retainedAmount = props.companySettings.cash_opening_mode === 'detailed' ? coinsTotal.value : Number(retainedCoins.value);
        }
    }

    const payload = {
        amount: finalAmount,
        retained_amount: retainedAmount, // 🚀 NOVO: Enviamos o valor retido para o Laravel
        observation: observation.value,
        details: props.companySettings.cash_opening_mode === 'detailed' ? counts.value : null
    };

    emit('confirm', payload);
};
</script>
