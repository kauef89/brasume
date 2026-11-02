<template>
    <div class="flex flex-col w-full h-full bg-surface-2">
        <!-- HEADER DO PAINEL -->
        <div class="h-14 px-6 border-b border-surface-border flex items-center justify-between shrink-0">
            <h3 class="font-black text-sm uppercase tracking-widest text-surface-text flex items-center gap-2">
                <i class="pi pi-wallet text-primary-500"></i> Acerto de Diária
            </h3>

            <div class="flex items-center gap-2">
                <SegmentedControl
                    v-model="activeTab"
                    :options="tabOptions"
                />
                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="w-8 h-8 rounded-full bg-surface-1 border border-surface-border text-surface-muted hover:text-surface-text flex items-center justify-center cursor-pointer outline-none transition-colors"
                    title="Fechar Painel"
                >
                    <i class="pi pi-times text-xs"></i>
                </button>
            </div>
        </div>

        <!-- CORPO SCROLLÁVEL -->
        <div class="flex-1 overflow-y-auto p-6 hide-scrollbar flex flex-col gap-6">

            <!-- ABA 1: LIQUIDAÇÃO EM BLOCOS -->
            <template v-if="activeTab === 'payment'">
                <div class="bg-surface-3 p-4 rounded-2xl flex flex-col gap-3 shrink-0">
                    <!-- DADOS DO COLABORADOR -->
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="micro-label text-[10px] uppercase font-bold text-surface-muted">Colaborador</span>
                            <h3 class="text-lg font-black text-surface-text">{{ payoutData.user?.name || user?.name }}</h3>
                            <span v-if="payoutData.user?.pix_key" class="text-[11px] text-surface-muted mt-0.5">
                                PIX ({{ payoutData.user.pix_key_type || 'Chave' }}): <strong>{{ payoutData.user.pix_key }}</strong>
                            </span>
                        </div>

                        <!-- BADGE DE TURNO POR DATA -->
                        <div class="flex flex-col items-end">
                            <span class="text-[10px] font-black uppercase tracking-wider text-surface-muted">Turno</span>
                            <span class="px-3 py-1 rounded-lg bg-surface-1 border border-surface-border font-black text-xs text-primary-500 shadow-xs">
                                {{ shiftDateLabel }}
                            </span>
                        </div>
                    </div>

                    <!-- PRESETS DE AÇÃO RÁPIDA -->
                    <div class="pt-2 border-t border-surface-border/60 flex items-center gap-2">
                        <button
                            type="button"
                            @click="applyPreset('all')"
                            class="flex-1 py-1.5 px-2 rounded-lg font-black text-[10px] uppercase tracking-wider transition-colors border outline-none cursor-pointer"
                            :class="activePreset === 'all' ? 'bg-primary-500 text-white border-primary-500' : 'bg-surface-1 text-surface-muted hover:text-surface-text border-surface-border'"
                        >
                            Liquidar Tudo
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('full_rate')"
                            class="flex-1 py-1.5 px-2 rounded-lg font-black text-[10px] uppercase tracking-wider transition-colors border outline-none cursor-pointer"
                            :class="activePreset === 'full_rate' ? 'bg-primary-500 text-white border-primary-500' : 'bg-surface-1 text-surface-muted hover:text-surface-text border-surface-border'"
                        >
                            Diária Cheia (Rolar Consumo)
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('roll_all')"
                            class="flex-1 py-1.5 px-2 rounded-lg font-black text-[10px] uppercase tracking-wider transition-colors border outline-none cursor-pointer"
                            :class="activePreset === 'roll_all' ? 'bg-primary-500 text-white border-primary-500' : 'bg-surface-1 text-surface-muted hover:text-surface-text border-surface-border'"
                        >
                            Rolar Tudo
                        </button>
                    </div>

                    <!-- BLOCOS DE CONFERÊNCIA POR TURNO -->
                    <div class="flex flex-col gap-3 pt-2">
                        <!-- 1. BLOCO: SERVIÇOS / DIÁRIA -->
                        <div class="bg-surface-1/80 border border-surface-border/70 rounded-xl p-3 flex flex-col gap-2 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-primary-600 dark:text-primary-400 flex items-center gap-1.5">
                                    <i class="pi pi-briefcase text-xs"></i> 1. Serviços e Honorários
                                </span>
                                <span class="text-[10px] font-bold text-surface-muted">Turno {{ shiftDateLabel }}</span>
                            </div>

                            <div class="flex items-center justify-between p-2 rounded-lg bg-surface-2/60 border border-surface-border/50">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        v-model="serviceItem.selected"
                                        class="w-4 h-4 rounded text-primary-500 focus:ring-0 cursor-pointer"
                                    />
                                    <span class="text-xs font-bold text-surface-text">{{ serviceItem.title }}</span>
                                </label>

                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-success-500">
                                        + {{ $formatCurrency(serviceItem.amount) }}
                                    </span>
                                    <span
                                        class="text-[9px] font-black uppercase px-2 py-0.5 rounded"
                                        :class="serviceItem.selected ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-purple-500/10 text-purple-500 border border-purple-500/20'"
                                    >
                                        {{ serviceItem.selected ? 'Pagar Hoje' : 'Rolada' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. BLOCO: EXCEDENTE DE LANCHE (SE HOUVER) -->
                        <div v-if="excedenteItems.length > 0" class="bg-surface-1/80 border border-surface-border/70 rounded-xl p-3 flex flex-col gap-2 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-amber-500 flex items-center gap-1.5">
                                    <i class="pi pi-sparkles text-xs"></i> 2. Excedente de Lanche da Equipe
                                </span>
                                <span class="text-[9px] text-surface-muted font-bold">Ultrapassou teto de benefício</span>
                            </div>

                            <div
                                v-for="item in excedenteItems"
                                :key="item.id"
                                class="flex items-center justify-between p-2 rounded-lg bg-surface-2/60 border border-surface-border/50"
                            >
                                <label class="flex items-center gap-2 cursor-pointer select-none min-w-0">
                                    <input
                                        type="checkbox"
                                        v-model="item.selected"
                                        class="w-4 h-4 rounded text-amber-500 focus:ring-0 cursor-pointer"
                                    />
                                    <span class="text-xs font-medium text-surface-text truncate max-w-[180px]">
                                        <span class="text-[9px] font-bold text-surface-muted mr-1">[{{ item.time }}]</span>
                                        {{ item.title }}
                                    </span>
                                </label>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs font-black text-danger-500">
                                        - {{ $formatCurrency(item.amount) }}
                                    </span>
                                    <span
                                        class="text-[9px] font-black uppercase px-2 py-0.5 rounded cursor-pointer select-none"
                                        :class="item.selected ? 'bg-danger-500/10 text-danger-500 border border-danger-500/20' : 'bg-purple-500/10 text-purple-500 border border-purple-500/20'"
                                        @click="item.selected = !item.selected"
                                    >
                                        {{ item.selected ? 'Descontar' : 'Rolar' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. BLOCO: CONSUMO BALCÃO / PESSOAL (SE HOUVER) -->
                        <div v-if="balcaoItems.length > 0" class="bg-surface-1/80 border border-surface-border/70 rounded-xl p-3 flex flex-col gap-2 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-rose-500 flex items-center gap-1.5">
                                    <i class="pi pi-shopping-bag text-xs"></i> 3. Consumo Balcão (Itens Pessoais)
                                </span>
                                <span class="text-[9px] text-surface-muted font-bold">Compras avulsas / Saída</span>
                            </div>

                            <div
                                v-for="item in balcaoItems"
                                :key="item.id"
                                class="flex items-center justify-between p-2 rounded-lg bg-surface-2/60 border border-surface-border/50"
                            >
                                <label class="flex items-center gap-2 cursor-pointer select-none min-w-0">
                                    <input
                                        type="checkbox"
                                        v-model="item.selected"
                                        class="w-4 h-4 rounded text-rose-500 focus:ring-0 cursor-pointer"
                                    />
                                    <span class="text-xs font-medium text-surface-text truncate max-w-[180px]">
                                        <span class="text-[9px] font-bold text-surface-muted mr-1">[{{ item.time }}]</span>
                                        {{ item.title }}
                                    </span>
                                </label>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs font-black text-danger-500">
                                        - {{ $formatCurrency(item.amount) }}
                                    </span>
                                    <span
                                        class="text-[9px] font-black uppercase px-2 py-0.5 rounded cursor-pointer select-none"
                                        :class="item.selected ? 'bg-danger-500/10 text-danger-500 border border-danger-500/20' : 'bg-purple-500/10 text-purple-500 border border-purple-500/20'"
                                        @click="item.selected = !item.selected"
                                    >
                                        {{ item.selected ? 'Descontar' : 'Rolar' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SALDO ANTERIOR PENDENTE -->
                    <div v-if="payoutData.previous_balance !== 0" class="p-3 bg-surface-1 rounded-xl border border-surface-border flex items-center justify-between text-xs">
                        <span class="font-bold uppercase text-surface-muted">Saldo de Turnos Anteriores</span>
                        <span class="font-black" :class="payoutData.previous_balance >= 0 ? 'text-success-500' : 'text-danger-500'">
                            {{ payoutData.previous_balance >= 0 ? '+' : '' }} {{ $formatCurrency(payoutData.previous_balance) }}
                        </span>
                    </div>

                    <!-- TOTALIZADORES: A PAGAR AGORA VS ROLADO P/ O FUTURO -->
                    <div class="pt-3 border-t border-surface-border flex flex-col gap-2.5">
                        <div class="flex items-center justify-between bg-surface-1 p-2.5 rounded-xl border border-surface-border text-xs">
                            <span class="font-bold text-surface-muted uppercase text-[10px]">Saldo Rolado para Próximo Turno:</span>
                            <span class="font-black" :class="rolledBalance >= 0 ? 'text-purple-500' : 'text-amber-500'">
                                {{ rolledBalance >= 0 ? '+' : '' }} {{ $formatCurrency(rolledBalance) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between bg-primary-500/10 p-3 rounded-xl border border-primary-500/20">
                            <label class="text-[11px] font-black text-primary-700 dark:text-primary-400 uppercase tracking-wider">
                                Valor a Pagar Agora
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="relative w-32">
                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-500 text-xs font-bold">R$</span>
                                    <input
                                        type="number"
                                        v-model.number="localPaidAmount"
                                        class="w-full h-8 pl-8 pr-2 text-right font-black text-sm bg-surface-1 border border-primary-500/30 rounded-lg outline-none focus:border-primary-500 text-primary-700 dark:text-primary-400 transition-colors [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PASSO 1: SELEÇÃO DA ORIGEM / GAVETA OU PIX -->
                <div v-if="step === 'method'" class="flex-1 flex flex-col animate-in fade-in slide-in-from-left-4 duration-300">
                    <p class="text-xs font-bold uppercase tracking-wider text-surface-muted text-center my-2">
                        {{ localPaidAmount === 0 ? 'Ação de Fechamento:' : 'Selecione a fonte do pagamento:' }}
                    </p>
                    <div class="flex flex-col gap-2.5">
                        <template v-if="localPaidAmount > 0">
                            <SelectableCard @select="selectMethod('cash')" color="warning" icon="pi-receipt" title="Gaveta de Caixa (Dinheiro)" subtitle="Registra a saída física do caixa" />
                            <SelectableCard @select="step = 'bank'" color="success" icon="pi-building-columns" title="Conta Bancária / PIX" subtitle="Pagamento feito pela empresa" />
                        </template>
                        <template v-else>
                            <SelectableCard @select="selectMethod('accumulate')" color="purple" icon="pi-history" title="Acumular no Saldo do Colaborador" subtitle="Rolar valores e não realizar saídas do caixa hoje" />
                        </template>
                    </div>
                </div>

                <!-- PASSO 2: SELEÇÃO DA CONTA BANCÁRIA -->
                <div v-else-if="step === 'bank'" class="flex-1 flex flex-col animate-in fade-in slide-in-from-right-4 duration-300">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-surface-muted">Conta de Origem do PIX:</p>
                        <button @click="step = 'method'" class="text-xs font-bold text-primary-smart hover:underline outline-none cursor-pointer border-none bg-transparent">Voltar</button>
                    </div>

                    <div class="flex flex-col gap-2 overflow-y-auto pr-1 hide-scrollbar max-h-56">
                        <SelectableCard v-for="bank in bankAccounts" :key="bank.id" @select="selectMethod('bank', bank.id)" color="success" icon="pi-building-columns" :title="bank.name" :subtitle="bank.bank_name || 'Conta Digital / Chave PIX'" />
                    </div>
                </div>
            </template>

            <!-- ABA 2: EXTRATO (LEDGER) -->
            <template v-else-if="activeTab === 'ledger'">
                <div class="flex flex-col gap-0 border border-surface-border rounded-xl bg-surface-1 overflow-hidden">
                    <div v-if="payoutData.ledger_history?.length === 0" class="p-8 text-center text-surface-muted text-sm font-medium">
                        Nenhuma movimentação financeira encontrada.
                    </div>

                    <div v-for="entry in payoutData.ledger_history" :key="entry.id" class="flex gap-3 text-sm p-4 border-b border-surface-border/50 last:border-0 hover:bg-surface-2 transition-colors">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" :class="entry.type === 'credit' ? 'bg-success-500/10 text-success-500' : 'bg-danger-500/10 text-danger-500'">
                            <i class="pi" :class="entry.type === 'credit' ? 'pi-arrow-down-left' : 'pi-arrow-up-right'"></i>
                        </div>
                        <div class="flex-1 flex flex-col justify-center min-w-0">
                            <span class="font-bold text-surface-text truncate text-xs">{{ entry.description }}</span>
                            <span class="text-[10px] text-surface-muted">{{ formatDate(entry.created_at) }}</span>
                        </div>
                        <div class="text-right flex flex-col justify-center shrink-0">
                            <span class="font-black text-sm" :class="entry.type === 'credit' ? 'text-success-500' : 'text-danger-500'">
                                {{ entry.type === 'credit' ? '+' : '-' }} {{ $formatCurrency(entry.amount) }}
                            </span>
                            <span class="text-[9px] font-bold text-surface-muted uppercase">Saldo: {{ $formatCurrency(entry.balance) }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import SelectableCard from '@/Components/UI/SelectableCard.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    user: Object,
    payoutDetails: {
        type: Object,
        default: () => ({})
    },
    bankAccounts: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['cancel', 'confirm']);

const activeTab = ref('payment');
const step = ref('method');
const activePreset = ref('all');

const tabOptions = [
    { label: 'Liquidação', value: 'payment', icon: 'pi-money-bill' },
    { label: 'Extrato', value: 'ledger', icon: 'pi-list' }
];

const localPaidAmount = ref(0);

const payoutData = ref({
    previous_balance: 0,
    gross_rate: 0,
    consumption_amount: 0,
    net_amount: 0,
    ledger_history: [],
    user: {}
});

const shiftDateLabel = ref('');
const serviceItem = ref({
    title: 'Diária de Trabalho',
    amount: 0,
    selected: true,
    category: 'services'
});
const rawConsumptionItems = ref([]);

// Itens categorizados
const excedenteItems = computed(() => {
    return rawConsumptionItems.value.filter(i => i.category === 'excedente');
});

const balcaoItems = computed(() => {
    return rawConsumptionItems.value.filter(i => i.category === 'balcao');
});

// Itens selecionados para liquidação AGORA
const settledItems = computed(() => {
    const list = [];
    if (serviceItem.value.selected && serviceItem.value.amount > 0) {
        list.push({ ...serviceItem.value });
    }
    rawConsumptionItems.value.forEach(item => {
        if (item.selected) {
            list.push({ ...item });
        }
    });
    return list;
});

// Itens que serão ROLADOS para o próximo turno
const rolledItems = computed(() => {
    const list = [];
    if (!serviceItem.value.selected && serviceItem.value.amount > 0) {
        list.push({ ...serviceItem.value, title: `${serviceItem.value.title} (Rolada)` });
    }
    rawConsumptionItems.value.forEach(item => {
        if (!item.selected) {
            list.push({ ...item, title: `${item.title} (Rolado)` });
        }
    });
    return list;
});

// Cálculo em tempo real do que deve ser pago agora
const calculatedNow = computed(() => {
    const serviceAmt = serviceItem.value.selected ? (serviceItem.value.amount || 0) : 0;
    const consAmt = rawConsumptionItems.value
        .filter(i => i.selected)
        .reduce((sum, i) => sum + (i.amount || 0), 0);

    const prev = payoutData.value.previous_balance || 0;
    return Math.max(0, prev + serviceAmt - consAmt);
});

// Saldo rolado para a próxima vez
const rolledBalance = computed(() => {
    const rolledServices = !serviceItem.value.selected ? (serviceItem.value.amount || 0) : 0;
    const rolledConsumptions = rawConsumptionItems.value
        .filter(i => !i.selected)
        .reduce((sum, i) => sum + (i.amount || 0), 0);

    return rolledServices - rolledConsumptions;
});

// Presets de Ação Rápida
const applyPreset = (preset) => {
    activePreset.value = preset;
    if (preset === 'all') {
        serviceItem.value.selected = true;
        rawConsumptionItems.value.forEach(i => i.selected = true);
    } else if (preset === 'full_rate') {
        serviceItem.value.selected = true;
        rawConsumptionItems.value.forEach(i => i.selected = false);
    } else if (preset === 'roll_all') {
        serviceItem.value.selected = false;
        rawConsumptionItems.value.forEach(i => i.selected = false);
    }
    localPaidAmount.value = calculatedNow.value;
};

// Sincroniza inputs
watch(() => props.payoutDetails, (newVal) => {
    if (newVal && Object.keys(newVal).length > 0) {
        payoutData.value = newVal;
        shiftDateLabel.value = newVal.shift_date_label || '';

        serviceItem.value = {
            id: newVal.service_item?.id || 'service_rate',
            title: newVal.service_item?.title || 'Diária de Trabalho',
            amount: Number(newVal.service_item?.amount ?? newVal.gross_rate ?? 0),
            selected: true,
            category: 'services',
            type: 'service'
        };

        const consItems = newVal.consumption_details?.items || [];
        rawConsumptionItems.value = consItems.map(item => ({
            ...item,
            amount: Number(item.amount || 0),
            selected: true
        }));

        localPaidAmount.value = calculatedNow.value;
    }
}, { immediate: true, deep: true });

watch(calculatedNow, (val) => {
    localPaidAmount.value = val;
});

onMounted(() => {
    step.value = 'method';
    activeTab.value = 'payment';
    activePreset.value = 'all';
});

const selectMethod = (method, bankAccountId = null) => {
    const deductedConsumption = rawConsumptionItems.value
        .filter(i => i.selected)
        .reduce((sum, i) => sum + i.amount, 0);

    emit('confirm', {
        method,
        bankAccountId,
        amount: localPaidAmount.value,
        gross_rate: serviceItem.value.selected ? serviceItem.value.amount : 0,
        consumption_deducted: deductedConsumption,
        shift_date_label: shiftDateLabel.value,
        settled_items: settledItems.value,
        rolled_items: rolledItems.value
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(d);
};
</script>
