<template>
    <div class="flex flex-col gap-6 w-full">

        <!-- PAGAMENTOS JÁ ADICIONADOS -->
        <div v-if="stagedPayments && stagedPayments.length > 0" class="flex flex-col gap-1">
            <div v-for="(p, idx) in stagedPayments" :key="idx" class="flex items-center justify-between animate-in fade-in slide-in-from-top-2  bg-surface-3 pl-2 rounded-xl">
                <div class="flex flex-col">
                    <span class="font-medium text-stone-800 dark:text-stone-200">{{ p.method_name }}</span>
                    <span v-if="p.change_target" class="text-xs font-medium text-orange-500 mt-0.5">
                        Troco para {{ $formatCurrency(p.change_target) }}
                    </span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="font-black text-lg" :class="p.engine_type === 'internal_loyalty' ? 'text-primary-500' : 'text-stone-900 dark:text-white'">
                        {{ $formatCurrency(p.amount) }}
                    </span>
                    <Button icon="pi pi-trash" variant="text" severity="danger" rounded class="w-8 h-8 p-0 hover:bg-danger-500" @click="$emit('remove-payment', idx)" />
                </div>
            </div>
        </div>

        <div v-if="remainingAmount > 0" class="flex flex-col gap-3">

            <!-- VALOR A RECEBER -->
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-black text-surface-text">R$</span>
                    <input
                        type="number"
                        v-model.number="localAmount"
                        class="w-full text-2xl font-black text-right py-2 pl-12 rounded-2xl focus:border-primary-500 bg-surface-2 outline-none transition-all"
                    />
                </div>
                <button
                    type="button"
                    @click="handleSetRemaining"
                    class="px-5 rounded-full font-black text-xs uppercase tracking-wider transition-all cursor-pointer border"
                    :class="!activeDivisor
                        ? 'bg-surface-2 text-surface-text border-surface-border shadow-sm'
                        : 'bg-transparent text-surface-muted border-transparent hover:text-surface-text'"
                >
                    Restante
                </button>
            </div>

            <!-- FERRAMENTA DE DIVISÃO (LIGA / DESLIGA) -->
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted shrink-0">Dividir:</span>
                <div class="grid grid-cols-4 gap-1.5 flex-1">
                    <button
                        v-for="d in [2, 3, 4, 5]"
                        :key="d"
                        type="button"
                        @click="$emit('divide', d)"
                        class="py-1.5 rounded-xl font-black text-xs transition-all outline-none cursor-pointer border flex items-center justify-center gap-1 active:scale-95"
                        :class="activeDivisor === d
                            ? 'bg-primary-500 text-white border-primary-500 shadow-sm ring-2 ring-primary-500/20'
                            : 'bg-surface-2 text-surface-muted border-surface-border/60 hover:text-surface-text hover:bg-surface-1'"
                    >
                        <span>÷{{ d }}</span>
                    </button>
                </div>
            </div>

            <slot name="extra-actions"></slot>

            <!-- BLOCO DE TROCO COM MÁSCARA -->
            <div v-if="pendingCashMethod" class="p-5 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-2xl flex flex-col gap-4 mt-2">

                <div class="flex items-center justify-between">
                    <label class="text-xs font-black text-orange-800 dark:text-orange-400 uppercase tracking-widest">
                        Troco para quanto?
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer select-none outline-none">
                        <span class="text-[10px] font-black uppercase tracking-widest transition-colors" :class="isNoChange ? 'text-orange-600 dark:text-orange-400' : 'text-surface-muted'">Sem Troco</span>
                        <div class="w-10 h-6 rounded-full transition-colors relative shadow-inner" :class="isNoChange ? 'bg-orange-500' : 'bg-surface-border'">
                            <div class="absolute top-1 left-1 bg-white w-4 h-4 rounded-full transition-transform shadow-sm" :class="isNoChange ? 'translate-x-4' : ''"></div>
                        </div>
                        <input type="checkbox" v-model="isNoChange" class="hidden" />
                    </label>
                </div>

                <div class="relative transition-all duration-300" :class="isNoChange ? 'opacity-40 grayscale pointer-events-none' : ''">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-black text-orange-800/50 dark:text-orange-400/50">R$</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        :value="formattedChangeTarget"
                        @input="handleCashInput"
                        placeholder="0,00"
                        class="w-full p-4 pl-12 rounded-xl border border-orange-300 dark:border-orange-700 text-right font-black text-2xl bg-white dark:bg-stone-950 focus:outline-none focus:ring-2 focus:ring-orange-500/50 text-orange-900 dark:text-orange-100 transition-colors"
                    />
                </div>

                <div class="flex justify-end gap-2 mt-1">
                    <Button label="Cancelar" size="small" variant="text" severity="secondary" @click="cancelCashPayment" />
                    <Button label="Confirmar" size="small" severity="warning" @click="executeCashPayment" :disabled="!isNoChange && numericChangeTarget < localAmount" />
                </div>
            </div>

            <!-- FORMAS DE PAGAMENTO: BOTÕES ESTILO KEYPAD PDV -->
            <div v-else class="grid grid-cols-2 gap-2 pt-3 border-t-2 border-surface-border/50 mt-1">
                <button
                    v-for="method in availableMethods"
                    :key="method.id"
                    @click="handleMethodClick(method)"
                    :disabled="isMethodDisabled(method)"
                    type="button"
                    class="group relative p-2 rounded-2xl flex flex-row items-center justify-center gap-1.5 transition-all text-center outline-none select-none active:scale-[0.97]"
                    :class="[
                        isMethodDisabled(method)
                            ? 'opacity-40 bg-primary-500/50 grayscale text-surface-muted cursor-not-allowed'
                            : 'border-surface-border bg-primary-500/10 text-surface-text hover:bg-primary-500/30 cursor-pointer'
                    ]"
                >
                    <!-- Ícone em container suave -->
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                        :class="[
                            isMethodDisabled(method)
                                ? 'bg-surface-2 text-surface-muted'
                                : 'bg-black/10 text-surface-text group-hover:bg-primary-500/10 group-hover:text-primary-200'
                        ]"
                    >
                        <i :class="['pi text-base', method.icon || 'pi-credit-card']"></i>
                    </div>

                    <!-- Textos centralizados com foco em legibilidade rápida -->
                    <div class="flex flex-col items-center min-w-0 w-full leading-tight">
                        <span class="font-black text-xs uppercase tracking-wide truncate w-full">
                            {{ method.name }}
                        </span>
                        <span v-if="method.subtitle" class="text-[9px] font-medium text-surface-muted truncate w-full mt-0.5">
                            {{ method.subtitle }}
                        </span>
                    </div>
                </button>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import Button from 'primevue/button';
import { formatCurrency } from '@/Utils/formatters';
import { useCashInput } from '@/Pages/Shared/Composables/useCashInput';

const props = defineProps({
    availableMethods: {
        type: Array,
        default: () => []
    },
    stagedPayments: {
        type: Array,
        default: () => []
    },
    remainingAmount: {
        type: Number,
        default: 0
    },
    allowChangeTarget: {
        type: Boolean,
        default: false
    },
    suggestedAmount: {
        type: Number,
        default: null
    },
    activeDivisor: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['add-payment', 'remove-payment', 'divide', 'clear-divisor']);

const localAmount = ref(props.suggestedAmount ?? props.remainingAmount);
const pendingCashMethod = ref(null);

const {
    isNoChange,
    formattedChangeTarget,
    numericChangeTarget,
    handleCashInput,
    resetCashInput
} = useCashInput();

watch(() => props.suggestedAmount, (newVal) => {
    if (newVal !== null && newVal !== undefined && newVal > 0) {
        localAmount.value = newVal;
    }
}, { immediate: true });

watch(() => props.remainingAmount, (newVal) => {
    if (!props.activeDivisor && newVal > 0) {
        localAmount.value = newVal;
    }
});

const handleSetRemaining = () => {
    emit('clear-divisor');
    localAmount.value = props.remainingAmount;
};

const isMethodDisabled = (method) => {
    if (method.disabled) return true;
    if (method.engine_type === 'internal_loyalty') return false;
    return !localAmount.value || localAmount.value <= 0;
};

const handleMethodClick = (method) => {
    if (isMethodDisabled(method)) return;

    if (method.engine_type === 'internal_loyalty') {
        emit('add-payment', {
            method_id: method.id,
            method_name: method.name,
            engine_type: method.engine_type,
            amount: Number(Math.min(method.max_value, props.remainingAmount).toFixed(2)),
            change_target: null
        });
        return;
    }

    if (props.allowChangeTarget && method.is_cash) {
        pendingCashMethod.value = method;
        resetCashInput();
    } else {
        emit('add-payment', {
            method_id: method.id,
            method_name: method.name,
            engine_type: method.engine_type,
            amount: Number(localAmount.value),
            change_target: null
        });
    }
};

const executeCashPayment = () => {
    emit('add-payment', {
        method_id: pendingCashMethod.value.id,
        method_name: pendingCashMethod.value.name,
        engine_type: pendingCashMethod.value.engine_type,
        amount: Number(localAmount.value),
        change_target: isNoChange.value ? null : numericChangeTarget.value
    });

    cancelCashPayment();
};

const cancelCashPayment = () => {
    pendingCashMethod.value = null;
    resetCashInput();
};
</script>
