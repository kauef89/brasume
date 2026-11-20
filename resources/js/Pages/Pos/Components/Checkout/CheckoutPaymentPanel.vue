<template>
    <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 hide-scrollbar relative">

        <div v-if="!$page.props.hasOpenShift" class="p-4 rounded-2xl bg-danger-500/10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 text-danger-600 dark:text-danger-400 animate-in fade-in shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-danger-500/10 flex items-center justify-center shrink-0">
                    <i class="pi pi-lock text-xl text-danger-500"></i>
                </div>
                <div>
                    <h4 class="text-sm font-black uppercase tracking-wider">Caixa Fechado</h4>
                    <p class="text-xs font-medium text-surface-muted mt-0.5">
                        Abra o caixa com o fundo de troco para liberar os pagamentos.
                    </p>
                </div>
            </div>

            <button
                type="button"
                @click="goToCashier"
                class="px-4 py-2.5 rounded-full bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider transition-all shadow-md flex items-center gap-2 outline-none cursor-pointer border-none shrink-0 self-end sm:self-center"
            >
                <i class="pi pi-lock-open text-xs"></i>
                <span>Abrir Caixa</span>
            </button>
        </div>

        <div>
            <div class="flex justify-between items-center mb-2 text-sm font-medium text-surface-muted">
                <span>Subtotal (Itens)</span>
                <span class="text-surface-text">{{ formatCurrency(orderSubtotal) }}</span>
            </div>

            <div v-if="deliveryFee > 0" class="flex justify-between items-center mb-2 text-sm font-medium text-surface-muted">
                <span>Taxa de Entrega</span>
                <span class="text-surface-text">{{ formatCurrency(deliveryFee) }}</span>
            </div>

            <div v-if="isTableServiceAvailable" class="flex justify-between items-center pb-3">
                <div class="flex items-center gap-2 text-sm font-medium text-surface-muted">
                    <span>Gorjeta ({{ companySettings.service_tax_percentage }}%)</span>
                    <button
                        type="button"
                        @click="$emit('update:isServiceTaxEnabled', !isServiceTaxEnabled)"
                        class="text-[10px] bg-surface-3 px-2 py-0.5 rounded text-primary-smart uppercase tracking-widest hover:bg-surface-border/50 transition-colors outline-none cursor-pointer border-none"
                    >
                        {{ isServiceTaxEnabled ? 'Remover' : 'Adicionar' }}
                    </button>
                </div>
                <span class="font-medium text-surface-text text-sm">
                    {{ isServiceTaxEnabled ? formatCurrency(serviceFeeAmount) : 'R$ 0,00' }}
                </span>
            </div>

            <div v-if="discount > 0" class="flex justify-between items-center mb-2 text-sm font-black text-success-smart">
                <span>Desconto Aplicado</span>
                <span>- {{ formatCurrency(discount) }}</span>
            </div>

            <!-- 🪙 GORJETA ESPONTÂNEA (FORA DA NFC-E / REPASSE À EQUIPE) -->
            <div class="my-3 p-3 rounded-2xl bg-surface-1 border border-surface-border/70 flex flex-col gap-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-heart-fill text-xs text-amber-500"></i>
                        <span class="text-xs font-black uppercase tracking-wider text-surface-text">Gorjeta Equipe</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-surface-3 text-surface-muted">Fora da NFC-e</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="applyQuickTipPct(10)"
                            class="px-2 py-0.5 rounded-lg bg-surface-2 hover:bg-primary-500/15 text-[10px] font-black text-primary-smart border border-surface-border cursor-pointer transition-colors"
                        >
                            +10%
                        </button>
                        <button
                            v-if="Number(tipAmount) > 0"
                            type="button"
                            @click="$emit('update:tipAmount', 0)"
                            class="px-2 py-0.5 rounded-lg bg-danger-500/10 text-[10px] font-black text-danger-500 border-none cursor-pointer"
                        >
                            Zerar
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-2 items-center">
                    <div class="col-span-5 relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-black text-surface-muted">R$</span>
                        <input
                            type="number"
                            step="0.50"
                            min="0"
                            :value="tipAmount || ''"
                            @input="$emit('update:tipAmount', Math.max(0, Number($event.target.value || 0)))"
                            placeholder="0,00"
                            class="w-full h-9 pl-8 pr-2 rounded-xl bg-surface-2 border border-surface-border text-xs font-black text-surface-text outline-none focus:border-primary-500"
                        />
                    </div>

                    <div class="col-span-7">
                        <select
                            :value="tipEmployeeId || ''"
                            @change="$emit('update:tipEmployeeId', $event.target.value ? Number($event.target.value) : null)"
                            class="w-full h-9 px-2.5 rounded-xl bg-surface-2 border border-surface-border text-xs font-bold text-surface-text outline-none focus:border-primary-500"
                        >
                            <option value="">Colaborador (opcional p/ caixa)...</option>
                            <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
                                {{ emp.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="Number(tipAmount) > 0" class="flex items-center gap-1.5 pt-0.5">
                    <button
                        type="button"
                        @click="$emit('update:tipPayoutMode', 'ledger_credit')"
                        class="flex-1 h-8 px-2 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all border cursor-pointer flex items-center justify-center gap-1"
                        :class="tipPayoutMode === 'ledger_credit'
                            ? 'bg-primary-500 text-white border-primary-500 shadow-xs'
                            : 'bg-surface-2 text-surface-muted border-surface-border hover:text-surface-text'"
                    >
                        <i class="pi pi-wallet text-[10px]"></i>
                        <span>Crédito Carteira RH</span>
                    </button>

                    <button
                        type="button"
                        @click="$emit('update:tipPayoutMode', 'cash_drawer')"
                        class="flex-1 h-8 px-2 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all border cursor-pointer flex items-center justify-center gap-1"
                        :class="tipPayoutMode === 'cash_drawer'
                            ? 'bg-amber-500 text-white border-amber-500 shadow-xs'
                            : 'bg-surface-2 text-surface-muted border-surface-border hover:text-surface-text'"
                    >
                        <i class="pi pi-money-bill text-[10px]"></i>
                        <span>Abater na Hora (Gaveta)</span>
                    </button>
                </div>
            </div>

            <div class="flex justify-between items-center mt-2 pt-2 border-t-2 border-surface-border/50">
                <span class="text-3xl font-black text-surface-text">Total</span>
                <span class="text-3xl font-black">{{ formatCurrency(dynamicTotal) }}</span>
            </div>
        </div>

        <PaymentStagingArea
            :class="{ 'opacity-40 pointer-events-none': !$page.props.hasOpenShift }"
            :available-methods="mappedMethods"
            :staged-payments="stagedPayments"
            :remaining-amount="pendingToStage"
            :suggested-amount="suggestedAmount"
            :active-divisor="activeDivisor"
            :allow-change-target="false"
            @divide="$emit('divide',$event)"
            @clear-divisor="$emit('clear-divisor')"
            @add-payment="$emit('add-payment', $event)"
            @remove-payment="$emit('remove-payment', $event)"
            @view-pix="$emit('view-pix', $event)"
        />
    </div>
</template>

<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import PaymentStagingArea from "@/Components/UI/PaymentStagingArea.vue";
import { formatCurrency } from "@/Utils/formatters";
import { usePaymentFormatting } from "@/Pages/Shared/Composables/usePaymentFormatting";

const props = defineProps({
    orderSubtotal: Number,
    deliveryFee: Number,
    serviceFeeAmount: Number,
    discount: { type: Number, default: 0 },
    dynamicTotal: Number,
    pendingToStage: Number,
    suggestedAmount: { type: Number, default: null },
    activeDivisor: { type: Number, default: null },
    stagedPayments: Array,
    paymentMethods: Array,
    companySettings: Object,
    isServiceTaxEnabled: Boolean,
    order: Object,
    cashbackValue: Number,
    tipAmount: { type: Number, default: 0 },
    tipEmployeeId: { type: [Number, String, null], default: null },
    tipPayoutMode: { type: String, default: 'ledger_credit' },
    employeesList: { type: Array, default: () => [] }
});

const emit = defineEmits([
    "update:isServiceTaxEnabled",
    "update:tipAmount",
    "update:tipEmployeeId",
    "update:tipPayoutMode",
    "divide",
    "clear-divisor",
    "add-payment",
    "remove-payment",
    "update:cashbackValue",
    "go-to-cashier",
    "view-pix"
]);

const applyQuickTipPct = (pct) => {
    const base = Number(props.orderSubtotal || 0);
    if (base <= 0) return;
    const val = Math.round((base * (pct / 100)) * 100) / 100;
    emit('update:tipAmount', val);
};

const page = usePage();
const { getPaymentIcon } = usePaymentFormatting();

const isTableServiceAvailable = computed(() => props.order?.type === 'table' && Boolean(props.companySettings?.has_service_tax));

const goToCashier = () => {
    window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'shift' }));
    window.dispatchEvent(new CustomEvent('pos-close-checkout'));
    emit('go-to-cashier');
};

const isLoyaltyActive = computed(() => props.companySettings?.loyalty_is_active && props.companySettings?.loyalty_reward_type === 'cashback');

// Resolução com fallback caso order.customer venha descarregado
const activeCustomer = computed(() => {
    if (props.order?.customer && typeof props.order.customer === 'object') {
        return props.order.customer;
    }
    const customerId = props.order?.customer_id;
    if (customerId) {
        const list = page.props.initialCustomers || page.props.customers || [];
        const found = list.find(c => c.id === customerId);
        if (found) return found;
    }
    return null;
});

const isCustomerLinkedToSupplier = computed(() => {
    const c = activeCustomer.value;
    if (!c) return false;
    return Boolean(c.supplier_id || c.supplier || c.is_convenio);
});

const isCustomerAnEmployee = computed(() => {
    const c = activeCustomer.value;
    if (!c) return false;
    return Boolean(c.employee_id || c.employee || c.is_employee);
});

const customerBalanceReais = computed(() => {
    if (!activeCustomer.value) return 0;
    const pts = activeCustomer.value.loyalty_points || 0;
    const rate = Number(props.companySettings?.loyalty_cashback_conversion_rate || 5);
    return (pts / 100) * rate;
});

const mappedMethods = computed(() => {
    const rawList = props.paymentMethods || [];

    const eligibleMethods = rawList.filter(m => {
        const engine = typeof m.engine_type === 'object' ? m.engine_type?.value : m.engine_type;

        // 1. Vale-alimentação nunca aparece no checkout normal
        if (engine === 'allowance') {
            return false;
        }

        // 2. Convênio / Faturado: exige cliente com vínculo de fornecedor
        if (engine === 'customer_credit') {
            return isCustomerLinkedToSupplier.value;
        }

        // 3. Consumo Interno: exige cliente colaborador
        if (engine === 'internal') {
            return isCustomerAnEmployee.value;
        }

        return true;
    });

    let methods = eligibleMethods.map(m => ({
        id: m.id,
        name: m.name,
        icon: getPaymentIcon(m),
        is_cash: m.engine_type === 'money',
        engine_type: m.engine_type
    }));

    if (isLoyaltyActive.value) {
        const hasCustomer = !!activeCustomer.value;
        const balance = customerBalanceReais.value;
        const alreadyStaged = props.stagedPayments.some(p => p.engine_type === 'internal_loyalty');
        const minRedeem = Number(props.companySettings?.loyalty_min_redeem_value || 0);
        const isSubtotalEligible = props.orderSubtotal >= minRedeem;

        let subtitle = 'Sem saldo';
        if (!hasCustomer) subtitle = 'Vincule o cliente';
        else if (alreadyStaged) subtitle = 'Já aplicado';
        else if (balance > 0 && !isSubtotalEligible) subtitle = `Mín. pedido: ${formatCurrency(minRedeem)}`;
        else if (balance > 0) subtitle = `Saldo: ${formatCurrency(balance)}`;

        const isDisabled = !hasCustomer || balance <= 0 || alreadyStaged || !isSubtotalEligible || props.pendingToStage <= 0;
        const maxVal = Number(props.companySettings?.loyalty_max_redeem_value || 0);
        const maxType = props.companySettings?.loyalty_max_redeem_type || 'fixed';
        const maxCap = maxVal > 0 ? (maxType === 'percentage' ? (props.orderSubtotal * maxVal) / 100 : maxVal) : Infinity;
        const maxAllowed = Math.min(balance, maxCap, props.pendingToStage);

        methods.unshift({
            id: 'cashback',
            name: 'Cashback',
            subtitle: subtitle,
            icon: 'pi-star-fill',
            iconColor: 'text-primary-500 text-2xl',
            is_cash: false,
            engine_type: 'internal_loyalty',
            disabled: isDisabled,
            max_value: maxAllowed,
            colorClass: 'border-primary-500/40 bg-primary-500/5 text-primary-700 dark:text-primary-400 hover:bg-primary-500/10 hover:border-primary-500/60'
        });
    }

    return methods;
});
</script>
