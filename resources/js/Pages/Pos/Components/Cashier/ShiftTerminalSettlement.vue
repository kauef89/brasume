<template>
    <div class="bg-surface-1 rounded-3xl p-5 md:p-6 border border-surface-border/60 flex flex-col gap-4 shadow-xs select-none">
        <!-- CABEÇALHO -->
        <div class="flex items-center justify-between pb-3 border-b border-surface-border/40 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-primary-500/10 text-primary-500 flex items-center justify-center text-lg shrink-0">
                    <i class="pi pi-credit-card"></i>
                </div>
                <div>
                    <h4 class="text-sm font-black text-surface-text leading-tight">Terminais APOS / Maquinetas</h4>
                    <p class="text-[11px] text-surface-muted leading-tight mt-0.5">Informe o total emitido no fecho de cada máquina</p>
                </div>
            </div>

            <div class="text-right shrink-0">
                <span class="text-[10px] font-black uppercase tracking-wider text-surface-muted block">Total Maquinetas</span>
                <span class="text-base font-black text-primary-smart">{{ formatCurrency(totalDeclared) }}</span>
            </div>
        </div>

        <!-- LISTAGEM DE TERMINAIS -->
        <div v-if="terminals.length === 0" class="py-8 text-center text-xs text-surface-muted flex flex-col items-center gap-2">
            <i class="pi pi-info-circle text-base text-surface-muted"></i>
            <span>Nenhum terminal com código TID configurado neste estabelecimento.</span>
        </div>

        <div v-else class="flex flex-col gap-3">
            <div
                v-for="term in terminals"
                :key="term.terminal_code"
                class="bg-surface-2 p-3.5 rounded-2xl border border-surface-border/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-colors hover:border-surface-border"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-surface-3 flex items-center justify-center text-xs text-surface-text shrink-0 font-bold">
                        <i class="pi pi-desktop text-xs"></i>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-black text-surface-text truncate">{{ term.name }}</span>
                        <span class="text-[10px] text-surface-muted font-mono">TID: {{ term.terminal_code }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                    <span class="text-[11px] font-bold text-surface-muted">Total Fita: R$</span>
                    <InputNumber
                        v-model="declaredValues[term.terminal_code]"
                        mode="decimal"
                        :minFractionDigits="2"
                        :min="0"
                        placeholder="0,00"
                        class="w-32 [&>input]:h-9 [&>input]:text-right [&>input]:px-2.5 [&>input]:text-xs [&>input]:font-black bg-surface-1 rounded-xl border-surface-border focus:border-primary-500"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import InputNumber from 'primevue/inputnumber';

const props = defineProps({
    devices: {
        type: Array,
        default: () => []
    },
    modelValue: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['update:modelValue', 'update:total']);

const terminals = computed(() => {
    return (props.devices || []).filter(d => Boolean(d.terminal_code) && d.is_active !== false);
});

const declaredValues = ref({});

onMounted(() => {
    terminals.value.forEach(t => {
        declaredValues.value[t.terminal_code] = props.modelValue[t.terminal_code] ?? null;
    });
});

const totalDeclared = computed(() => {
    return Object.values(declaredValues.value).reduce((sum, val) => sum + (Number(val) || 0), 0);
});

watch(declaredValues, (newVal) => {
    emit('update:modelValue', newVal);
    emit('update:total', totalDeclared.value);
}, { deep: true });

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};
</script>
