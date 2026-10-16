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

            <!-- ABA 1: LIQUIDAÇÃO -->
            <template v-if="activeTab === 'payment'">
                <div class="bg-surface-3 p-4 rounded-2xl flex flex-col gap-3 shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="micro-label text-[10px] uppercase font-bold text-surface-muted">Colaborador</span>
                            <h3 class="text-lg font-black text-surface-text">{{ payoutData.user?.name || user?.name }}</h3>
                            <span v-if="payoutData.user?.pix_key" class="text-[11px] text-surface-muted mt-0.5">
                                PIX ({{ payoutData.user.pix_key_type || 'Chave' }}): <strong>{{ payoutData.user.pix_key }}</strong>
                            </span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-surface-border/60 flex flex-col gap-2">
                        <!-- SALDO ANTERIOR -->
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-surface-muted font-bold uppercase">Saldo Anterior (Pendente)</span>
                            <span class="font-black text-surface-text">{{ $formatCurrency(payoutData.previous_balance || 0) }}</span>
                        </div>

                        <!-- AJUSTE DA DIÁRIA DE HOJE -->
                        <div class="flex items-center justify-between text-xs mt-1">
                            <span class="text-surface-muted font-bold uppercase">Diária do Turno Atual</span>
                            <div class="relative w-28">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-surface-muted text-[10px] font-bold">R$</span>
                                <input type="number" v-model.number="localGrossRate" class="w-full h-7 pl-6 pr-2 text-right font-black text-xs bg-surface-1 border border-surface-border rounded outline-none focus:border-primary-500 transition-colors [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                            </div>
                        </div>

                        <!-- CONSUMO NO TURNO -->
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold uppercase" :class="payoutData.consumption_amount > 0 ? 'text-danger-500' : 'text-surface-muted'">(-) Consumo Interno</span>
                            <span class="font-black" :class="payoutData.consumption_amount > 0 ? 'text-danger-500' : 'text-surface-text'">{{ $formatCurrency(payoutData.consumption_amount) }}</span>
                        </div>
                        <div v-if="payoutData.consumption_details?.orders_count > 0" class="text-[10px] bg-danger-500/10 text-danger-400 p-1.5 rounded font-medium mt-1">
                            <i class="pi pi-info-circle mr-1"></i> {{ payoutData.consumption_details.orders_count }} comanda(s) abatida(s).
                        </div>
                    </div>

                    <!-- TOTAL DEVIDO VS VALOR A PAGAR -->
                    <div class="pt-3 border-t border-surface-border flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-wider text-surface-muted">Saldo Total Devido</span>
                            <span class="text-lg font-black text-surface-text">{{ $formatCurrency(totalDue) }}</span>
                        </div>

                        <div class="flex items-center justify-between bg-primary-500/10 p-3 rounded-xl border border-primary-500/20">
                            <label class="text-[11px] font-black text-primary-700 dark:text-primary-400 uppercase tracking-wider">Valor a Pagar Agora</label>
                            <div class="flex items-center gap-2">
                                <button v-if="localPaidAmount !== totalDue" @click="localPaidAmount = totalDue" class="text-[9px] font-bold uppercase bg-primary-500/20 text-primary-700 dark:text-primary-300 px-2 py-1 rounded cursor-pointer border-none outline-none hover:bg-primary-500/30">Total</button>
                                <div class="relative w-28">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-primary-500 text-[10px] font-bold">R$</span>
                                    <input type="number" v-model.number="localPaidAmount" class="w-full h-8 pl-6 pr-2 text-right font-black text-sm bg-surface-1 border border-primary-500/30 rounded-lg outline-none focus:border-primary-500 text-primary-700 dark:text-primary-400 transition-colors [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PASSO 1: ESCOLHA DA ORIGEM -->
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
                            <SelectableCard @select="selectMethod('accumulate')" color="purple" icon="pi-history" title="Acumular Dívida no Saldo" subtitle="Rolar a dívida e não realizar pagamentos hoje" />
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

const tabOptions = [
    { label: 'Liquidação', value: 'payment', icon: 'pi-money-bill' },
    { label: 'Extrato', value: 'ledger', icon: 'pi-list' }
];

const localGrossRate = ref(0);
const localPaidAmount = ref(0);

const payoutData = ref({
    previous_balance: 0,
    gross_rate: 0,
    consumption_amount: 0,
    net_amount: 0,
    ledger_history: [],
    user: {}
});

const totalDue = computed(() => {
    const prev = payoutData.value.previous_balance || 0;
    const gross = localGrossRate.value || 0;
    const cons = payoutData.value.consumption_amount || 0;
    return Math.max(0, prev + gross - cons);
});

watch(() => props.payoutDetails, (newVal) => {
    if (newVal && Object.keys(newVal).length > 0) {
        payoutData.value = newVal;
        localGrossRate.value = newVal.gross_rate || 0;

        const calcTotal = (newVal.previous_balance || 0) + (newVal.gross_rate || 0) - (newVal.consumption_amount || 0);
        localPaidAmount.value = Math.max(0, calcTotal);
    }
}, { immediate: true, deep: true });

onMounted(() => {
    step.value = 'method';
    activeTab.value = 'payment';
});

const selectMethod = (method, bankAccountId = null) => {
    emit('confirm', {
        method,
        bankAccountId,
        amount: localPaidAmount.value,
        gross_rate: localGrossRate.value,
        consumption_deducted: payoutData.value.consumption_amount
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(d);
};
</script>
