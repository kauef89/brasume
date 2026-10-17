<template>
    <div class="flex-1 overflow-y-auto p-6 md:p-8 flex flex-col gap-6 hide-scrollbar">

        <!-- 🚨 BANNER DE CAIXA FECHADO COM ATALHO DE ABERTURA -->
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

            <!-- 🎯 TAXA DE SERVIÇO / GORJETA -->
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

            <div class="flex justify-between items-center mt-2 pt-2 border-t-2 border-surface-border/50">
                <span class="text-3xl font-black text-surface-text">Total</span>
                <span class="text-3xl font-black">{{ formatCurrency(dynamicTotal) }}</span>
            </div>
        </div>

        <!-- 🚀 STAGING AREA INTEGRADA COM DIVISOR E VALOR SUGERIDO (Sem slot duplicado) -->
        <PaymentStagingArea
            :class="{ 'opacity-40 pointer-events-none': !$page.props.hasOpenShift }"
            :available-methods="mappedMethods"
            :staged-payments="stagedPayments"
            :remaining-amount="pendingToStage"
            :suggested-amount="suggestedAmount"
            :active-divisor="activeDivisor"
            :allow-change-target="false"
            @divide="$emit('divide', $event)"
            @clear-divisor="$emit('clear-divisor')"
            @add-payment="handleAddPayment"
            @remove-payment="handleRemovePayment"
        />

        <div v-if="pendingToStage <= 0 && companySettings?.is_fiscal_emitter" class="animate-in fade-in slide-in-from-bottom-4 flex flex-col gap-3 p-5 bg-surface-3 rounded-3xl">
            <div class="flex items-center justify-between">
                <label class="text-sm font-black text-surface-muted">CPF/CNPJ na Nota (Opcional)</label>
                <span class="text-xs font-black bg-success-500/10 text-success-600 dark:text-success-400 px-1.5 py-1 rounded-md">
                    <i class="pi pi-check-circle text-[8px]"></i> Pagamento Pronto
                </span>
            </div>

            <div class="relative">
                <i class="pi pi-id-card absolute left-4 top-1/2 -translate-y-1/2 text-surface-muted text-sm pointer-events-none"></i>
                <input
                    type="text"
                    :value="customerDocument"
                    @input="handleDocumentInput"
                    placeholder="Deixe em branco p/ Consumidor Final"
                    maxlength="18"
                    class="w-full h-14 pl-12 pr-4 rounded-xl bg-surface-2 text-surface-text text-lg font-black focus:border-primary-500 outline-none transition-all"
                />
            </div>
        </div>

    </div>
</template>

<script setup>
import { computed } from "vue";
import PaymentStagingArea from "@/Components/UI/PaymentStagingArea.vue";
import { formatCurrency, formatCpfCnpj } from "@/Utils/formatters";
import { usePaymentFormatting } from "@/Pages/Shared/Composables/usePaymentFormatting";

const props = defineProps({
    orderSubtotal: Number,
    deliveryFee: Number,
    serviceFeeAmount: Number,
    discount: { type: Number, default: 0 },
    dynamicTotal: Number,
    pendingToStage: Number,
    suggestedAmount: {
        type: Number,
        default: null
    },
    activeDivisor: {
        type: Number,
        default: null
    },
    stagedPayments: Array,
    paymentMethods: Array,
    companySettings: Object,
    isServiceTaxEnabled: Boolean,
    order: Object,
    cashbackValue: Number,
    customerDocument: String
});

const emit = defineEmits([
    "update:isServiceTaxEnabled",
    "divide",
    "clear-divisor",
    "add-payment",
    "remove-payment",
    "update:cashbackValue",
    "update:customerDocument",
    "go-to-cashier"
]);

const { getPaymentIcon } = usePaymentFormatting();

const isTableServiceAvailable = computed(() => {
    return props.order?.type === 'table' && Boolean(props.companySettings?.has_service_tax);
});

const goToCashier = () => {
    window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'shift' }));
    window.dispatchEvent(new CustomEvent('pos-close-checkout'));
    emit('go-to-cashier');
};

const handleDocumentInput = (e) => {
    const formatted = formatCpfCnpj(e.target.value);
    e.target.value = formatted;
    emit('update:customerDocument', formatted);
};

const isLoyaltyActive = computed(() => props.companySettings?.loyalty_is_active && props.companySettings?.loyalty_reward_type === 'cashback');

const customerBalanceReais = computed(() => {
    if (!props.order?.customer) return 0;
    const pts = props.order.customer.loyalty_points || 0;
    const rate = Number(props.companySettings?.loyalty_cashback_conversion_rate || 5);
    return (pts / 100) * rate;
});

const mappedMethods = computed(() => {
    let methods = props.paymentMethods.map(m => ({
        id: m.id,
        name: m.name,
        icon: getPaymentIcon(m),
        is_cash: m.engine_type === 'money',
        engine_type: m.engine_type
    }));

    if (isLoyaltyActive.value) {
        const hasCustomer = !!props.order?.customer;
        const balance = customerBalanceReais.value;
        const alreadyStaged = props.stagedPayments.some(p => p.engine_type === 'internal_loyalty');

        let subtitle = 'Sem saldo';
        if (!hasCustomer) subtitle = 'Vincule o cliente';
        else if (alreadyStaged) subtitle = 'Já aplicado';
        else if (balance > 0) subtitle = `Saldo: ${formatCurrency(balance)}`;

        const isDisabled = !hasCustomer || balance <= 0 || alreadyStaged || props.pendingToStage <= 0;

        methods.unshift({
            id: 'cashback',
            name: 'Cashback',
            subtitle: subtitle,
            icon: 'pi-star-fill',
            iconColor: 'text-primary-500 text-2xl',
            is_cash: false,
            engine_type: 'internal_loyalty',
            disabled: isDisabled,
            max_value: balance,
            colorClass: 'border-primary-500/40 bg-primary-500/5 text-primary-700 dark:text-primary-400 hover:bg-primary-500/10 hover:border-primary-500/60'
        });
    }

    return methods;
});

const handleAddPayment = (payload) => {
    if (payload.engine_type === 'internal_loyalty') {
        emit('update:cashbackValue', payload.amount);
    }
    emit('add-payment', payload);
};

const handleRemovePayment = (idx) => {
    const payment = props.stagedPayments[idx];
    if (payment && payment.engine_type === 'internal_loyalty') {
        emit('update:cashbackValue', 0);
    }
    emit('remove-payment', idx);
};
</script>
