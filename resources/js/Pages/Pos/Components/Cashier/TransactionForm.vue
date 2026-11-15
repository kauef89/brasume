<template>
    <div class="h-full w-full flex flex-col justify-between p-6 bg-surface-2 select-none overflow-y-auto hide-scrollbar">

        <!-- BLOCO SUPERIOR: TÍTULO, INFO E CAMPOS -->
        <div class="flex flex-col gap-5">

            <!-- BADGE DE STATUS DA OPERAÇÃO -->
            <div class="flex items-center gap-2">
                <span
                    class="w-2.5 h-2.5 rounded-full"
                    :class="isInput ? 'bg-primary-500 animate-pulse' : 'bg-warning-500'"
                ></span>
                <span class="text-xs font-black uppercase tracking-widest text-surface-muted">
                    {{ isInput ? 'Suprimento (Entrada de Troco)' : 'Sangria (Retirada de Caixa)' }}
                </span>
            </div>

            <!-- SELETOR DE DESTINO DA SANGRIA -->
            <div v-if="!isInput" class="grid grid-cols-3 gap-2">
                <button
                    type="button"
                    @click="outPurpose = 'general'"
                    class="p-2.5 rounded-xl border text-left transition-all cursor-pointer outline-none flex flex-col gap-1"
                    :class="outPurpose === 'general'
                        ? 'bg-warning-500/15 border-warning-500 text-warning-700 dark:text-warning-300 shadow-xs'
                        : 'bg-surface-1 border-surface-border/60 text-surface-muted hover:text-surface-text'"
                >
                    <i class="pi pi-lock text-xs"></i>
                    <span class="text-[10px] font-black uppercase tracking-wider leading-tight">Cofre / Geral</span>
                </button>

                <button
                    type="button"
                    @click="outPurpose = 'employee'"
                    class="p-2.5 rounded-xl border text-left transition-all cursor-pointer outline-none flex flex-col gap-1"
                    :class="outPurpose === 'employee'
                        ? 'bg-purple-500/15 border-purple-500 text-purple-700 dark:text-purple-300 shadow-xs'
                        : 'bg-surface-1 border-surface-border/60 text-surface-muted hover:text-surface-text'"
                >
                    <i class="pi pi-user text-xs"></i>
                    <span class="text-[10px] font-black uppercase tracking-wider leading-tight">Vale / Equipe</span>
                </button>

                <button
                    type="button"
                    @click="outPurpose = 'supplier'"
                    class="p-2.5 rounded-xl border text-left transition-all cursor-pointer outline-none flex flex-col gap-1"
                    :class="outPurpose === 'supplier'
                        ? 'bg-emerald-500/15 border-emerald-500 text-emerald-700 dark:text-emerald-300 shadow-xs'
                        : 'bg-surface-1 border-surface-border/60 text-surface-muted hover:text-surface-text'"
                >
                    <i class="pi pi-truck text-xs"></i>
                    <span class="text-[10px] font-black uppercase tracking-wider leading-tight">Fornecedor</span>
                </button>
            </div>

            <!-- CARD EXPLICATIVO CONTEXTUAL -->
            <div
                class="p-3.5 rounded-2xl flex items-start gap-3 border shadow-xs"
                :class="headerBannerClass"
            >
                <div
                    class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                    :class="headerIconBgClass"
                >
                    <i :class="['pi text-sm', headerIcon]"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-black text-[11px] uppercase tracking-wider">
                        {{ headerTitle }}
                    </span>
                    <p class="text-[11px] font-medium text-surface-muted mt-0.5 leading-snug">
                        {{ headerSubtitle }}
                    </p>
                </div>
            </div>

            <!-- BLOCO COLABORADOR (VALE CLT OU ABATIMENTO DE CONTA) -->
            <template v-if="!isInput && outPurpose === 'employee'">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-black text-surface-muted uppercase tracking-widest">
                        Colaborador
                    </label>
                    <select
                        v-model="selectedEmployeeId"
                        class="w-full h-11 px-3 rounded-xl bg-surface-1 text-surface-text text-xs font-bold border border-surface-border/70 focus:border-purple-500 outline-none cursor-pointer"
                    >
                        <option :value="null">Selecione o colaborador...</option>
                        <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
                            {{ emp.name }} ({{ emp.employment_type === 'clt' ? 'CLT' : 'Freelancer / Extra' }})
                        </option>
                    </select>
                </div>

                <div v-if="selectedEmployeeId" class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-black text-surface-muted uppercase tracking-widest flex items-center justify-between">
                        <span>Vincular a Conta em Aberto</span>
                        <span class="text-[10px] font-semibold text-purple-500 normal-case">Opcional</span>
                    </label>
                    <select
                        v-model="selectedPayableId"
                        class="w-full h-11 px-3 rounded-xl bg-surface-1 text-surface-text text-xs font-bold border border-surface-border/70 focus:border-purple-500 outline-none cursor-pointer"
                    >
                        <option :value="null">Sem vínculo (Vale / Adiantamento na Folha CLT)</option>
                        <option v-for="ap in employeeOpenPayables" :key="ap.id" :value="ap.id">
                            #{{ ap.id }} • {{ ap.description }} — Saldo: {{ $formatCurrency(ap.amount) }} (Venc. {{ formatShortDate(ap.due_date) }})
                        </option>
                    </select>
                    <span v-if="selectedPayable" class="text-[11px] font-semibold text-purple-600 dark:text-purple-400">
                        {{ isPartialPayment
                            ? `Abatimento parcial: restarão ${$formatCurrency(remainingAfterPayment)} em aberto no título #${selectedPayable.id}.`
                            : `Quitação total do título #${selectedPayable.id}.` }}
                    </span>
                </div>
            </template>

            <!-- BLOCO FORNECEDOR (CONTA EXISTENTE OU COMPRA EMERGENCIAL) -->
            <template v-if="!isInput && outPurpose === 'supplier'">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-black text-surface-muted uppercase tracking-widest">
                        Fornecedor
                    </label>
                    <select
                        v-model="selectedSupplierId"
                        class="w-full h-11 px-3 rounded-xl bg-surface-1 text-surface-text text-xs font-bold border border-surface-border/70 focus:border-emerald-500 outline-none cursor-pointer"
                    >
                        <option :value="null">Fornecedor Avulso / Compra Direta no Balcão</option>
                        <option v-for="sup in suppliersList" :key="sup.id" :value="sup.id">
                            {{ sup.name }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-black text-surface-muted uppercase tracking-widest">
                        Tipo de Pagamento
                    </label>
                    <select
                        v-model="selectedPayableId"
                        class="w-full h-11 px-3 rounded-xl bg-surface-1 text-surface-text text-xs font-bold border border-surface-border/70 focus:border-emerald-500 outline-none cursor-pointer"
                    >
                        <option :value="null">+ Compra Emergencial / Despesa à Vista (Gerar Conta Paga)</option>
                        <option v-for="ap in supplierOpenPayables" :key="ap.id" :value="ap.id">
                            Baixar Título #{{ ap.id }} • {{ ap.description }} — Saldo: {{ $formatCurrency(ap.amount) }}
                        </option>
                    </select>
                    <span v-if="selectedPayable" class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ isPartialPayment
                            ? `Abatimento parcial: restarão ${$formatCurrency(remainingAfterPayment)} em aberto no título #${selectedPayable.id}.`
                            : `Quitação total do título #${selectedPayable.id}.` }}
                    </span>
                </div>

                <div v-if="!selectedPayableId" class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-black text-surface-muted uppercase tracking-widest">
                        Classificação DRE (Compra Emergencial)
                    </label>
                    <select
                        v-model="accountingCategory"
                        class="w-full h-11 px-3 rounded-xl bg-surface-1 text-surface-text text-xs font-bold border border-surface-border/70 focus:border-emerald-500 outline-none cursor-pointer"
                    >
                        <option value="ingredients">01. Insumos & Matéria-Prima (CMV)</option>
                        <option value="packaging">02. Embalagens & Descartáveis (CMV)</option>
                        <option value="maintenance">05. Manutenção & Limpeza</option>
                        <option value="utilities">04. Utilidades (Gás / Água / Energia)</option>
                        <option value="administrative">07. Administrativo & Escritório</option>
                        <option value="marketing">06. Marketing & Impressos</option>
                    </select>
                </div>
            </template>

            <!-- VALOR -->
            <div class="flex flex-col gap-1.5">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-black text-surface-muted uppercase tracking-widest">
                        Valor da Transação
                    </label>
                    <button
                        v-if="selectedPayable"
                        type="button"
                        @click="amount = Number(selectedPayable.amount)"
                        class="text-[10px] font-black uppercase text-primary-500 hover:underline bg-transparent border-none cursor-pointer"
                    >
                        Usar Saldo Total ({{ $formatCurrency(selectedPayable.amount) }})
                    </button>
                </div>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-black text-surface-muted text-base z-10 pointer-events-none">R$</span>
                    <InputNumber
                        v-model="amount"
                        mode="decimal"
                        :minFractionDigits="2"
                        :maxFractionDigits="2"
                        placeholder="0,00"
                        class="w-full [&>input]:h-14 [&>input]:pl-12 [&>input]:pr-4 [&>input]:rounded-2xl [&>input]:bg-surface-1 [&>input]:text-surface-text [&>input]:text-2xl [&>input]:font-black [&>input]:border [&>input]:border-surface-border/60 [&>input]:focus:border-primary-500 [&>input]:outline-none [&>input]:transition-all [&>input]:shadow-inner"
                    />
                </div>
                <span v-if="exceedsPayableBalance" class="text-[11px] font-bold text-danger-500">
                    O valor não pode ultrapassar o saldo pendente de {{ $formatCurrency(selectedPayable.amount) }}.
                </span>
            </div>

            <!-- MOTIVO / DESCRIÇÃO -->
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-black text-surface-muted uppercase tracking-widest">
                    Motivo / Observação {{ selectedPayable ? '(Opcional)' : '' }}
                </label>
                <textarea
                    v-model="description"
                    rows="2"
                    autocomplete="off"
                    :placeholder="descriptionPlaceholder"
                    class="w-full bg-surface-1 text-surface-text text-xs font-medium p-3 rounded-2xl border border-surface-border/60 focus:border-primary-500 outline-none resize-none transition-colors shadow-inner"
                ></textarea>
            </div>
        </div>

        <!-- BLOCO INFERIOR: BOTÕES DE CONFIRMAÇÃO E CANCELAMENTO -->
        <div class="pt-5 flex flex-col gap-2.5">
            <button
                type="button"
                @click="handleConfirm"
                :disabled="!isValid || isSubmitting"
                class="w-full h-13 rounded-2xl font-black text-xs uppercase tracking-widest text-white transition-all shadow-lg flex items-center justify-center gap-2 outline-none border-none"
                :class="[
                    !isValid || isSubmitting
                        ? 'opacity-50 cursor-not-allowed bg-surface-3 text-surface-muted'
                        : confirmButtonClass
                ]"
            >
                <i :class="['pi text-sm', isSubmitting ? 'pi-spinner pi-spin' : (isInput ? 'pi-check' : 'pi-arrow-up')]"></i>
                <span>{{ confirmButtonText }}</span>
            </button>

            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" @secure-click="$emit('cancel')" />
        </div>

    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';
import { ref, computed, watch } from 'vue';
import InputNumber from 'primevue/inputnumber';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    type: { type: String, default: 'in' },
    employeesList: { type: Array, default: () => [] },
    suppliersList: { type: Array, default: () => [] },
    pendingPayables: { type: Array, default: () => [] }
});

const emit = defineEmits(['confirm', 'cancel']);

const outPurpose = ref('general');
const selectedEmployeeId = ref(null);
const selectedSupplierId = ref(null);
const selectedPayableId = ref(null);
const accountingCategory = ref('ingredients');
const amount = ref(null);
const description = ref('');
const isSubmitting = ref(false);

const isInput = computed(() => props.type === 'in');

const employeeOpenPayables = computed(() => {
    if (!selectedEmployeeId.value) return [];
    return (props.pendingPayables || []).filter(p => Number(p.employee_id) === Number(selectedEmployeeId.value));
});

const supplierOpenPayables = computed(() => {
    const list = (props.pendingPayables || []).filter(p => !p.employee_id);
    if (!selectedSupplierId.value) return list;
    return list.filter(p => Number(p.supplier_id) === Number(selectedSupplierId.value));
});

const selectedPayable = computed(() => {
    if (!selectedPayableId.value) return null;
    return (props.pendingPayables || []).find(p => Number(p.id) === Number(selectedPayableId.value)) || null;
});

const exceedsPayableBalance = computed(() => {
    if (!selectedPayable.value || !amount.value) return false;
    return Number(amount.value) > (Number(selectedPayable.value.amount) + 0.01);
});

const isPartialPayment = computed(() => {
    if (!selectedPayable.value || !amount.value) return false;
    return (Number(selectedPayable.value.amount) - Number(amount.value)) >= 0.01;
});

const remainingAfterPayment = computed(() => {
    if (!selectedPayable.value || !amount.value) return 0;
    return Math.max(0, Number(selectedPayable.value.amount) - Number(amount.value));
});

const headerBannerClass = computed(() => {
    if (isInput.value) return 'bg-primary-500/10 border-primary-500/20 text-primary-smart';
    if (outPurpose.value === 'employee') return 'bg-purple-500/10 border-purple-500/20 text-purple-600 dark:text-purple-400';
    if (outPurpose.value === 'supplier') return 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400';
    return 'bg-warning-500/10 border-warning-500/20 text-warning-600 dark:text-warning-400';
});

const headerIconBgClass = computed(() => {
    if (isInput.value) return 'bg-primary-500/20 text-primary-500';
    if (outPurpose.value === 'employee') return 'bg-purple-500/20 text-purple-500';
    if (outPurpose.value === 'supplier') return 'bg-emerald-500/20 text-emerald-500';
    return 'bg-warning-500/20 text-warning-500';
});

const headerIcon = computed(() => {
    if (isInput.value) return 'pi-arrow-down';
    if (outPurpose.value === 'employee') return 'pi-id-card';
    if (outPurpose.value === 'supplier') return 'pi-shopping-bag';
    return 'pi-arrow-up';
});

const headerTitle = computed(() => {
    if (isInput.value) return 'Reforço de Fundo';
    if (outPurpose.value === 'employee') return 'Vale CLT ou Abatimento de Conta';
    if (outPurpose.value === 'supplier') return 'Pagamento ou Compra Emergencial';
    return 'Retirada de Espécie';
});

const headerSubtitle = computed(() => {
    if (isInput.value) return 'Adicione moedas ou troco físico diretamente na gaveta do caixa.';
    if (outPurpose.value === 'employee') return 'Retira o valor da gaveta, abate o título ou debita na conta-corrente CLT e imprime recibo.';
    if (outPurpose.value === 'supplier') return 'Quita/abate um título existente ou lança compra emergencial no DRE usando dinheiro do caixa.';
    return 'Retire dinheiro físico do caixa para o cofre ou depósito bancário.';
});

const descriptionPlaceholder = computed(() => {
    if (isInput.value) return 'Ex: Reforço de troco repassado pelo cofre...';
    if (outPurpose.value === 'employee') return selectedPayable.value ? 'Ex: Adiantamento parcial da semana...' : 'Ex: Vale transporte / adiantamento salarial...';
    if (outPurpose.value === 'supplier') return selectedPayable.value ? 'Ex: Pagamento referente à entrega de hoje...' : 'Ex: Compra emergencial de gelo / pão / hortifrúti...';
    return 'Ex: Sangria para depósito bancário / cofre...';
});

const confirmButtonClass = computed(() => {
    if (isInput.value) return 'bg-primary-500 hover:bg-primary-600 active:scale-[0.98] shadow-primary-500/25 cursor-pointer';
    if (outPurpose.value === 'employee') return 'bg-purple-600 hover:bg-purple-700 active:scale-[0.98] shadow-purple-500/25 cursor-pointer';
    if (outPurpose.value === 'supplier') return 'bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] shadow-emerald-500/25 cursor-pointer';
    return 'bg-warning-500 hover:bg-warning-600 active:scale-[0.98] shadow-warning-500/25 cursor-pointer';
});

const confirmButtonText = computed(() => {
    if (isInput.value) return 'Confirmar Entrada';
    if (outPurpose.value === 'employee') return selectedPayable.value ? (isPartialPayment.value ? 'Abater Título & Emitir Recibo' : 'Quitar Título & Emitir Recibo') : 'Registrar Vale & Emitir Recibo';
    if (outPurpose.value === 'supplier') return selectedPayable.value ? (isPartialPayment.value ? 'Abater Título Fornecedor' : 'Quitar Título Fornecedor') : 'Lançar Compra & Sangria';
    return 'Confirmar Sangria';
});

const isValid = computed(() => {
    const val = Number(amount.value);
    if (val <= 0 || exceedsPayableBalance.value) return false;

    if (isInput.value || outPurpose.value === 'general') {
        return description.value.trim().length >= 3;
    }

    if (outPurpose.value === 'employee') {
        if (!selectedEmployeeId.value) return false;
        return selectedPayableId.value ? true : description.value.trim().length >= 3;
    }

    if (outPurpose.value === 'supplier') {
        return selectedPayableId.value ? true : description.value.trim().length >= 3;
    }

    return false;
});

watch(() => props.type, () => {
    outPurpose.value = 'general';
    selectedEmployeeId.value = null;
    selectedSupplierId.value = null;
    selectedPayableId.value = null;
    amount.value = null;
    description.value = '';
    isSubmitting.value = false;
});

watch(outPurpose, () => {
    selectedEmployeeId.value = null;
    selectedSupplierId.value = null;
    selectedPayableId.value = null;
    description.value = '';
});

watch([selectedEmployeeId, selectedSupplierId], () => {
    selectedPayableId.value = null;
});

const formatShortDate = (dateStr) => {
    if (!dateStr) return '';
    const clean = String(dateStr).split('T')[0];
    const parts = clean.split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}` : clean;
};

const handleConfirm = () => {
    if (!isValid.value || isSubmitting.value) return;
    isSubmitting.value = true;
    emit('confirm', {
        type: props.type,
        out_purpose: isInput.value ? 'general' : outPurpose.value,
        amount: Number(amount.value),
        description: description.value.trim(),
        employee_id: outPurpose.value === 'employee' ? selectedEmployeeId.value : null,
        supplier_id: outPurpose.value === 'supplier' ? selectedSupplierId.value : null,
        account_payable_id: selectedPayableId.value || null,
        accounting_category: (!isInput.value && outPurpose.value === 'supplier' && !selectedPayableId.value) ? accountingCategory.value : null
    });
};
</script>
