<template>
    <div class="w-full h-full flex flex-col p-6 md:p-8 overflow-y-auto hide-scrollbar select-none" @keydown.enter.prevent>
        <div class="w-full flex flex-col gap-6 animate-in fade-in duration-300 pb-8">

            <!-- CABEÇALHO DO PAINEL DE CONTAGEM -->
            <div class="flex items-center gap-3 shrink-0">
                <div
                    class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 shadow-sm"
                    :class="isOpenMode ? 'bg-primary-500/15 text-primary-500' : 'bg-danger-500/15 text-danger-500'"
                >
                    <i :class="['pi text-lg font-black', isOpenMode ? 'pi-lock-open' : 'pi-lock']"></i>
                </div>
                <div>
                    <h2 class="text-xl font-black text-surface-text leading-tight">
                        {{ isOpenMode ? 'Abertura de Caixa' : 'Fechamento de Caixa' }}
                    </h2>
                    <p class="text-xs font-medium text-surface-muted">
                        {{ isOpenMode ? 'Informe as cédulas e moedas do fundo de troco.' : 'Realize a contagem do dinheiro físico presente na gaveta.' }}
                    </p>
                </div>
            </div>

            <!-- AVISO DE CONTAGEM CEGA (APENAS FECHAMENTO EM MODO FUNCIONÁRIO) -->
            <div
                v-if="!isOpenMode && companySettings.cash_management_mode === 'employee'"
                class="bg-purple-500/10 border border-purple-500/20 p-4 rounded-2xl flex items-start gap-3.5"
            >
                <div class="mt-0.5 w-7 h-7 rounded-full bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                    <i class="pi pi-eye-slash text-xs font-black"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-purple-700 dark:text-purple-400 uppercase tracking-wider mb-0.5">
                        Contagem Cega Ativada
                    </p>
                    <p class="text-[11px] font-medium text-purple-800/80 dark:text-purple-300/80 leading-relaxed">
                        O saldo esperado pelo sistema está oculto. Diferenças acima da tolerância bloquearão o encerramento e exigirão autorização gerencial.
                    </p>
                </div>
            </div>

            <!-- 1. MODO TOTALIZADOR SIMPLES -->
            <div v-if="companySettings.cash_opening_mode === 'total'" class="flex flex-col items-center py-8">
                <label class="text-xs font-black text-surface-muted uppercase tracking-widest mb-3 text-center">
                    {{ isOpenMode ? 'Fundo de Troco Inicial' : 'Valor Total em Gaveta' }}
                </label>
                <div class="relative w-full max-w-sm">
                    <span class="absolute left-5 top-1/2 -translate-y-1/2 text-xl font-black text-surface-muted z-10">R$</span>
                    <InputNumber
                        v-model="simpleTotal"
                        autocomplete="off"
                        mode="decimal"
                        :minFractionDigits="2"
                        class="w-full text-3xl font-black [&>input]:text-center [&>input]:pl-12 [&>input]:h-16 rounded-2xl bg-surface-1 border-surface-border focus:border-primary-500 transition-colors shadow-inner"
                    />
                </div>

                <!-- RETENÇÃO MANUAL DE MOEDAS (APENAS FECHAMENTO) -->
                <div
                    v-if="!isOpenMode && companySettings.carry_over_mode === 'coins_only'"
                    class="mt-6 w-full max-w-sm p-4 bg-primary-500/5 rounded-2xl border border-primary-500/20 text-center"
                >
                    <label class="block text-[10px] font-black text-primary-smart uppercase tracking-wider mb-2 text-center">
                        Quanto desse valor ficará retido para o próximo turno?
                    </label>
                    <div class="relative max-w-xs mx-auto">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-black text-primary-500/50 z-10">R$</span>
                        <InputNumber
                            v-model="retainedCoins"
                            autocomplete="off"
                            mode="decimal"
                            :minFractionDigits="2"
                            :max="simpleTotal"
                            class="w-full text-lg font-black [&>input]:text-center [&>input]:pl-8 [&>input]:h-11 rounded-xl bg-surface-1 border-transparent focus:border-primary-500"
                        />
                    </div>
                </div>
            </div>

            <!-- 2. MODO CONTAGEM DETALHADA COM STEPPERS -->
            <div v-else class="flex flex-col gap-6">

                <!-- Seção de Cédulas -->
                <div class="flex flex-col gap-3">
                    <span class="text-xs font-black text-surface-muted uppercase tracking-widest">
                        Cédulas
                    </span>

                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-7 gap-2.5">
                        <div
                            v-for="(val, key) in moneyValues.bills"
                            :key="key"
                            class="flex flex-col items-center bg-surface-1 p-2 rounded-2xl border border-surface-border/40 shadow-xs"
                        >
                            <span class="text-xs font-black text-surface-muted mb-1.5">{{ $formatCurrency(val ) }}</span>

                            <button
                                type="button"
                                tabindex="-1"
                                @click="stepIncrement(key)"
                                class="w-full h-7 rounded-xl bg-surface-2 hover:bg-surface-3 active:scale-95 text-surface-muted hover:text-surface-text flex items-center justify-center transition-all cursor-pointer mb-1 border-none"
                                title="Adicionar 1 cédula"
                            >
                                <i class="pi pi-plus text-[10px] font-bold"></i>
                            </button>

                            <InputNumber
                                :ref="el => setInputRef(el, getIndex(key))"
                                v-model="counts[key]"
                                :min="0"
                                autocomplete="off"
                                @keydown="handleKeydown($event, getIndex(key))"
                                class="w-full [&>input]:w-full [&>input]:h-9 [&>input]:text-center [&>input]:px-1 [&>input]:text-xs [&>input]:font-black bg-surface-2 rounded-xl border-transparent focus:border-primary-500"
                            />

                            <button
                                type="button"
                                tabindex="-1"
                                @click="stepDecrement(key)"
                                :disabled="!counts[key] || counts[key] <= 0"
                                class="w-full h-7 rounded-xl bg-surface-2 hover:bg-surface-3 active:scale-95 text-surface-muted hover:text-surface-text flex items-center justify-center transition-all cursor-pointer mt-1 border-none disabled:opacity-20 disabled:cursor-not-allowed"
                                title="Remover 1 cédula"
                            >
                                <i class="pi pi-minus text-[10px] font-bold"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Seção de Moedas -->
                <div class="flex flex-col gap-3">
                    <span class="text-xs font-black text-surface-muted uppercase tracking-widest">
                        Moedas
                    </span>

                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2.5">
                        <div
                            v-for="(val, key) in moneyValues.coins"
                            :key="key"
                            class="flex flex-col items-center bg-surface-1 p-2 rounded-2xl border border-surface-border/40 shadow-xs"
                        >
                            <span class="text-xs font-black text-surface-muted mb-1.5">{{ $formatCurrency(val) }}</span>

                            <button
                                type="button"
                                tabindex="-1"
                                @click="stepIncrement(key)"
                                class="w-full h-7 rounded-xl bg-surface-2 hover:bg-surface-3 active:scale-95 text-surface-muted hover:text-surface-text flex items-center justify-center transition-all cursor-pointer mb-1 border-none"
                                title="Adicionar 1 moeda"
                            >
                                <i class="pi pi-plus text-[10px] font-bold"></i>
                            </button>

                            <InputNumber
                                :ref="el => setInputRef(el, getIndex(key))"
                                v-model="counts[key]"
                                :min="0"
                                autocomplete="off"
                                @keydown="handleKeydown($event, getIndex(key))"
                                class="w-full [&>input]:w-full [&>input]:h-9 [&>input]:text-center [&>input]:px-1 [&>input]:text-xs [&>input]:font-black bg-surface-2 rounded-xl border-transparent focus:border-primary-500"
                            />

                            <button
                                type="button"
                                tabindex="-1"
                                @click="stepDecrement(key)"
                                :disabled="!counts[key] || counts[key] <= 0"
                                class="w-full h-7 rounded-xl bg-surface-2 hover:bg-surface-3 active:scale-95 text-surface-muted hover:text-surface-text flex items-center justify-center transition-all cursor-pointer mt-1 border-none disabled:opacity-20 disabled:cursor-not-allowed"
                                title="Remover 1 moeda"
                            >
                                <i class="pi pi-minus text-[10px] font-bold"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import InputNumber from 'primevue/inputnumber';

const props = defineProps({
    mode: {
        type: String,
        default: 'open',
        validator: (v) => ['open', 'close'].includes(v)
    },
    companySettings: {
        type: Object,
        default: () => ({})
    },
    expectedTotal: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['update:data', 'submit']);

const isOpenMode = computed(() => props.mode === 'open');

const simpleTotal = ref(0);
const retainedCoins = ref(0);
const counts = ref({
    b200: null, b100: null, b50: null, b20: null, b10: null, b5: null, b2: null,
    c1: null, c50: null, c25: null, c10: null, c5: null
});

// Reseta o form: chamável do componente pai
const resetForm = () => {
    simpleTotal.value = 0;
    retainedCoins.value = 0;
    Object.keys(counts.value).forEach(key => {
        counts.value[key] = null;
    });
};

defineExpose({
    resetForm
});

const moneyValues = {
    bills: { b200: 200, b100: 100, b50: 50, b20: 20, b10: 10, b5: 5, b2: 2 },
    coins: { c1: 1, c50: 0.5, c25: 0.25, c10: 0.1, c5: 0.05 }
};

const formatMoney = (val) => Number(val || 0).toFixed(2).replace('.', ',');

const stepIncrement = (key) => {
    counts.value[key] = (Number(counts.value[key]) || 0) + 1;
};

const stepDecrement = (key) => {
    const current = Number(counts.value[key]) || 0;
    counts.value[key] = current > 1 ? current - 1 : (current === 1 ? 0 : null);
};

const allKeys = [...Object.keys(moneyValues.bills), ...Object.keys(moneyValues.coins)];
const inputRefs = ref([]);

const getIndex = (key) => allKeys.indexOf(key);

const setInputRef = (el, index) => {
    if (el) inputRefs.value[index] = el;
};

const focusInput = (index) => {
    const component = inputRefs.value[index];
    if (component && component.$el) {
        const input = component.$el.querySelector('input');
        if (input) {
            input.focus();
            setTimeout(() => input.select(), 10);
        }
    }
};

const handleKeydown = (event, index) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        if (index < allKeys.length - 1) {
            focusInput(index + 1);
        } else {
            emit('submit');
        }
    } else if (event.key === 'Backspace') {
        const val = counts.value[allKeys[index]];
        if (!val && index > 0) {
            event.preventDefault();
            focusInput(index - 1);
        }
    }
};

const coinsTotal = computed(() => {
    return Object.keys(moneyValues.coins).reduce((sum, k) => sum + ((counts.value[k] || 0) * moneyValues.coins[k]), 0);
});

const calculatedTotal = computed(() => {
    const bills = Object.keys(moneyValues.bills).reduce((sum, k) => sum + ((counts.value[k] || 0) * moneyValues.bills[k]), 0);
    return bills + coinsTotal.value;
});

const isConfirmDisabled = computed(() => {
    if (!isOpenMode.value && props.companySettings.cash_opening_mode === 'total' && props.companySettings.carry_over_mode === 'coins_only') {
        return Number(retainedCoins.value) > Number(simpleTotal.value);
    }
    return false;
});

// Sincroniza em tempo real com o painel lateral da direita
watch([counts, simpleTotal, retainedCoins, calculatedTotal, coinsTotal, isConfirmDisabled], () => {
    const isDetailed = props.companySettings.cash_opening_mode !== 'total';
    const finalAmount = isDetailed ? calculatedTotal.value : Number(simpleTotal.value || 0);

    let retainedAmount = 0;
    if (!isOpenMode.value) {
        if (props.companySettings.carry_over_mode === 'total') {
            retainedAmount = finalAmount;
        } else if (props.companySettings.carry_over_mode === 'coins_only') {
            retainedAmount = isDetailed ? coinsTotal.value : Number(retainedCoins.value || 0);
        }
    }

    emit('update:data', {
        amount: finalAmount,
        retainedAmount: retainedAmount,
        coinsTotal: coinsTotal.value,
        details: isDetailed ? counts.value : null,
        isValid: !isConfirmDisabled.value
    });
}, { deep: true, immediate: true });
</script>
