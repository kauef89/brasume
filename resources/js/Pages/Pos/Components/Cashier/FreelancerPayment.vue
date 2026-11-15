<template>
    <div class="flex flex-col w-full h-full bg-surface-2">
        <!-- HEADER DO PAINEL -->
        <div class="h-14 px-6 border-b border-surface-border flex items-center justify-between shrink-0 gap-2">
            <h3 class="font-black text-sm uppercase tracking-widest text-surface-text flex items-center gap-2 truncate">
                <i class="pi pi-wallet text-primary-500"></i>
                <span class="truncate">{{ courierData ? 'Fechamento Motoboy & Diária' : 'Acerto de Diária' }}</span>
            </h3>

            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="handlePrintConference"
                    :disabled="isPrintingConference"
                    class="h-8 px-3 rounded-full bg-surface-1 hover:bg-primary-500/10 border border-surface-border hover:border-primary-500/40 text-surface-text hover:text-primary-500 font-black text-[10px] uppercase tracking-wider flex items-center gap-1.5 cursor-pointer outline-none transition-all active:scale-95 disabled:opacity-50"
                    title="Imprimir pré-conta para conferência antes de pagar"
                >
                    <i class="pi" :class="isPrintingConference ? 'pi-spinner pi-spin text-xs' : 'pi-print text-xs'"></i>
                    <span class="hidden sm:inline">{{ isPrintingConference ? 'Imprimindo...' : 'Conferência' }}</span>
                </button>

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
                    <!-- DADOS DO COLABORADOR E TURNO -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-col min-w-0">
                            <span class="micro-label text-[10px] uppercase font-bold text-surface-muted">Colaborador</span>
                            <h3 class="text-lg font-black text-surface-text truncate">{{ payoutData.user?.name || user?.name }}</h3>
                            <span v-if="payoutData.user?.pix_key" class="text-[11px] text-surface-muted mt-0.5 truncate">
                                PIX ({{ payoutData.user.pix_key_type || 'Chave' }}): <strong>{{ payoutData.user.pix_key }}</strong>
                            </span>
                            <span v-if="payoutData.shift_operator_name" class="text-[10px] text-surface-muted mt-0.5">
                                Operador Caixa: <strong>{{ payoutData.shift_operator_name }}</strong>
                            </span>
                        </div>

                        <!-- BADGE DE TURNO POR DATA -->
                        <div class="flex flex-col items-end shrink-0">
                            <span class="text-[10px] font-black uppercase tracking-wider text-surface-muted">Data / Turno</span>
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
                            Diária/Corridas (Rolar Consumo)
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
                                    <i class="pi pi-briefcase text-xs"></i> 1. Diária Fixa de Presença
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

                        <!-- 2. BLOCO EXCLUSIVO: FECHAMENTO LOGÍSTICO (MOTOBOY) -->
                        <div v-if="courierData" class="bg-surface-1/80 border border-primary-500/30 rounded-xl p-3 flex flex-col gap-2.5 shadow-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[10px] font-black uppercase tracking-wider text-primary-500 flex items-center gap-1.5">
                                    <i class="pi pi-truck text-xs"></i> 2. Entregas & Repasse ({{ courierData.deliveries_count || 0 }} corridas)
                                </span>
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-primary-500/10 text-primary-500">
                                    {{ courierData.payment_model_label || 'Taxa Fixa' }}
                                </span>
                            </div>

                            <!-- PARÂMETROS DO CONTRATO DO MOTOBOY -->
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="p-2 rounded-lg bg-surface-2/70 border border-surface-border/40 flex flex-col">
                                    <span class="text-[9px] font-bold uppercase text-surface-muted">
                                        {{ courierData.payment_model === 'distance' ? 'Valor / Km' : (courierData.payment_model === 'neighborhood' ? 'Taxa Padrão' : 'Taxa / Entrega') }}
                                    </span>
                                    <span class="text-xs font-black text-surface-text mt-0.5">
                                        {{ $formatCurrency(courierData.payment_model === 'distance' ? (courierData.fee_per_km || 0) : (courierData.default_delivery_fee || 0)) }}
                                    </span>
                                </div>

                                <div class="p-2 rounded-lg bg-surface-2/70 border border-surface-border/40 flex flex-col">
                                    <span class="text-[9px] font-bold uppercase text-surface-muted">Diária Fixa</span>
                                    <span class="text-xs font-black text-surface-text mt-0.5">
                                        {{ $formatCurrency(courierData.daily_fee || 0) }}
                                    </span>
                                </div>

                                <div class="p-2 rounded-lg bg-surface-2/70 border border-surface-border/40 flex flex-col">
                                    <span class="text-[9px] font-bold uppercase text-surface-muted">Piso Mínimo</span>
                                    <span class="text-xs font-black mt-0.5" :class="courierData.minimum_applied ? 'text-amber-500' : 'text-surface-text'">
                                        {{ $formatCurrency(courierData.minimum_guaranteed || 0) }}
                                    </span>
                                </div>
                            </div>

                            <!-- LISTA DETALHADA DAS ENTREGAS DO TURNO -->
                            <div v-if="courierData.deliveries && courierData.deliveries.length > 0" class="flex flex-col gap-1 max-h-40 overflow-y-auto pr-1 hide-scrollbar border border-surface-border/40 rounded-lg bg-surface-2/40 p-1.5">
                                <div
                                    v-for="del in courierData.deliveries"
                                    :key="del.id"
                                    class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-md bg-surface-1/90 text-xs"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="font-black text-[10px] text-primary-500 shrink-0">#{{ del.id }}</span>
                                        <span class="text-[10px] font-bold text-surface-muted shrink-0">{{ del.time }}</span>
                                        <span class="font-semibold text-surface-text truncate">{{ del.neighborhood }}</span>
                                        <span v-if="del.is_cash" class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 shrink-0">
                                            Dinheiro {{ $formatCurrency(del.cash_amount) }}
                                        </span>
                                    </div>
                                    <span class="font-black text-success-500 shrink-0">
                                        + {{ $formatCurrency(del.courier_fee) }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="p-2 rounded-lg bg-surface-2/40 text-center text-[11px] text-surface-muted">
                                Nenhuma entrega registrada neste turno.
                            </div>

                            <!-- SUBTOTAL DAS CORRIDAS + COMPLEMENTO PISO MÍNIMO -->
                            <div class="flex flex-col gap-1.5 pt-1">
                                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-2/60 border border-surface-border/50">
                                    <span class="text-xs font-bold text-surface-text">Total Repassado das Corridas</span>
                                    <span class="text-xs font-black text-success-500">
                                        + {{ $formatCurrency(courierData.total_deliveries_fee || 0) }}
                                    </span>
                                </div>

                                <div v-if="courierData.minimum_applied && courierData.minimum_complement > 0" class="flex items-center justify-between p-2 rounded-lg bg-amber-500/10 border border-amber-500/30">
                                    <span class="text-xs font-bold text-amber-700 dark:text-amber-400">Complemento Garantia Mínima</span>
                                    <span class="text-xs font-black text-amber-600">
                                        + {{ $formatCurrency(courierData.minimum_complement) }}
                                    </span>
                                </div>

                                <!-- CONFERÊNCIA DE NUMERÁRIO DA RUA -->
                                <div v-if="courierData.cash_collected > 0" class="flex items-center justify-between p-2 rounded-lg bg-amber-500/10 border border-amber-500/30">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-black text-amber-700 dark:text-amber-400">Dinheiro Coletado na Rua</span>
                                        <span class="text-[10px] text-surface-muted">Valor recebido em espécie dos clientes para prestar contas ao caixa</span>
                                    </div>
                                    <span class="text-xs font-black text-amber-600 shrink-0">
                                        {{ $formatCurrency(courierData.cash_collected) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. BLOCO: EXCEDENTE DE LANCHE (SE HOUVER) -->
                        <div v-if="excedenteItems.length > 0" class="bg-surface-1/80 border border-surface-border/70 rounded-xl p-3 flex flex-col gap-2 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-amber-500 flex items-center gap-1.5">
                                    <i class="pi pi-sparkles text-xs"></i> 3. Excedente de Lanche da Equipe
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

                        <!-- 4. BLOCO: CONSUMO INTERNO / BALCÃO (SE HOUVER) -->
                        <div v-if="balcaoItems.length > 0" class="bg-surface-1/80 border border-surface-border/70 rounded-xl p-3 flex flex-col gap-2 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-rose-500 flex items-center gap-1.5">
                                    <i class="pi pi-shopping-bag text-xs"></i> 4. Consumo Interno / Balcão
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

                    <!-- TOTALIZADORES -->
                    <div class="pt-3 border-t border-surface-border flex flex-col gap-2.5">
                        <div class="flex items-center justify-between bg-surface-1 p-2.5 rounded-xl border border-surface-border text-xs">
                            <span class="font-bold text-surface-muted uppercase text-[10px]">Saldo Rolado para Próximo Turno:</span>
                            <span class="font-black" :class="rolledBalance >= 0 ? 'text-purple-500' : 'text-amber-500'">
                                {{ rolledBalance >= 0 ? '+' : '' }} {{ $formatCurrency(rolledBalance) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between bg-primary-500/10 p-3 rounded-xl border border-primary-500/20">
                            <label class="text-[11px] font-black text-primary-700 dark:text-primary-400 uppercase tracking-wider">
                                Valor Total da Diária (A Liquidar)
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

                <!-- PASSO 1: SELEÇÃO DA ORIGEM -->
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
                        <SelectableCard v-for="bank in checkingBankAccounts" :key="bank.id" @select="selectMethod('bank', bank.id)" color="success" icon="pi-building-columns" :title="bank.name" :subtitle="bank.bank_name || 'Conta Corrente / Chave PIX'" />
                        <div v-if="checkingBankAccounts.length === 0" class="p-4 rounded-xl bg-surface-1 border border-surface-border text-center text-xs text-surface-muted font-medium">
                            Nenhuma Conta Corrente ativa encontrada para pagamento via PIX.
                        </div>
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
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
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
const toast = useToast();

const activeTab = ref('payment');
const step = ref('method');
const activePreset = ref('all');
const isPrintingConference = ref(false);

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
    courier_details: null,
    shift_operator_name: '',
    user: {}
});

const checkingBankAccounts = computed(() =>
    (props.bankAccounts || []).filter(b => b.type === 'checking' && b.is_active !== false)
);

const courierData = computed(() => payoutData.value.courier_details);

const shiftDateLabel = ref('');
const serviceItem = ref({
    title: 'Diária de Trabalho',
    amount: 0,
    selected: true,
    category: 'services'
});
const rawConsumptionItems = ref([]);

const excedenteItems = computed(() => rawConsumptionItems.value.filter(i => i.category === 'excedente'));
const balcaoItems = computed(() => rawConsumptionItems.value.filter(i => i.category === 'balcao'));

const settledItems = computed(() => {
    const list = [];
    if (serviceItem.value.selected && serviceItem.value.amount > 0) {
        list.push({ ...serviceItem.value });
    }
    if (courierData.value?.total_deliveries_fee > 0) {
        list.push({
            id: 'courier_deliveries',
            title: `Produção de Entregas (${courierData.value.deliveries_count} corridas)`,
            amount: courierData.value.total_deliveries_fee,
            type: 'service',
            category: 'courier'
        });
    }
    rawConsumptionItems.value.forEach(item => {
        if (item.selected) list.push({ ...item });
    });
    return list;
});

const rolledItems = computed(() => {
    const list = [];
    if (!serviceItem.value.selected && serviceItem.value.amount > 0) {
        list.push({ ...serviceItem.value, title: `${serviceItem.value.title} (Rolada)` });
    }
    rawConsumptionItems.value.forEach(item => {
        if (!item.selected) list.push({ ...item, title: `${item.title} (Rolado)` });
    });
    return list;
});

const calculatedNow = computed(() => {
    const serviceAmt = serviceItem.value.selected ? (serviceItem.value.amount || 0) : 0;
    const deliveriesAmt = courierData.value?.total_deliveries_fee || 0;
    const minGuaranteed = courierData.value?.minimum_guaranteed || 0;

    const gross = Math.max(serviceAmt + deliveriesAmt, minGuaranteed);

    const consAmt = rawConsumptionItems.value
        .filter(i => i.selected)
        .reduce((sum, i) => sum + (i.amount || 0), 0);

    const prev = payoutData.value.previous_balance || 0;
    return Math.max(0, prev + gross - consAmt);
});

const rolledBalance = computed(() => {
    const rolledServices = !serviceItem.value.selected ? (serviceItem.value.amount || 0) : 0;
    const rolledConsumptions = rawConsumptionItems.value
        .filter(i => !i.selected)
        .reduce((sum, i) => sum + (i.amount || 0), 0);

    return rolledServices - rolledConsumptions;
});

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

watch(() => props.payoutDetails, (newVal) => {
    if (newVal && Object.keys(newVal).length > 0) {
        payoutData.value = newVal;
        shiftDateLabel.value = newVal.shift_date_label || '';

        const baseDaily = Number(newVal.courier_details?.daily_fee ?? newVal.service_item?.amount ?? newVal.gross_rate ?? 0);
        serviceItem.value = {
            id: newVal.service_item?.id || 'service_rate',
            title: newVal.courier_details ? 'Diária Fixa do Turno' : (newVal.service_item?.title || 'Diária de Trabalho'),
            amount: baseDaily,
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

const handlePrintConference = async () => {
    const targetUserId = payoutData.value.user?.id || props.user?.id;
    if (!targetUserId || isPrintingConference.value) return;

    isPrintingConference.value = true;

    const deductedConsumption = rawConsumptionItems.value
        .filter(i => i.selected)
        .reduce((sum, i) => sum + i.amount, 0);

    try {
        const resp = await axios.post(route('operations.attendance.print-conference'), {
            user_id: targetUserId,
            amount: localPaidAmount.value,
            gross_rate: serviceItem.value.selected ? serviceItem.value.amount : 0,
            consumption_deducted: deductedConsumption,
            shift_date_label: shiftDateLabel.value,
            settled_items: settledItems.value,
            rolled_items: rolledItems.value,
            remaining_balance: rolledBalance.value
        });

        toast.add({
            severity: 'success',
            summary: 'Conferência Impressa',
            detail: resp.data?.message || 'Pré-conta enviada para a impressora do caixa.',
            life: 3000
        });
    } catch (e) {
        toast.add({
            severity: 'error',
            summary: 'Erro na Impressão',
            detail: e.response?.data?.error || 'Não foi possível imprimir a conferência.',
            life: 4000
        });
    } finally {
        isPrintingConference.value = false;
    }
};

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
