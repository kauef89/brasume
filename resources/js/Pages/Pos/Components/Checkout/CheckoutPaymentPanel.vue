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
    cashbackValue: Number
});

const emit = defineEmits([
    "update:isServiceTaxEnabled",
    "divide",
    "clear-divisor",
    "add-payment",
    "remove-payment",
    "update:cashbackValue",
    "go-to-cashier",
    "view-pix"
]);

const { getPaymentIcon } = usePaymentFormatting();

const isTableServiceAvailable = computed(() => props.order?.type === 'table' && Boolean(props.companySettings?.has_service_tax));

const goToCashier = () => {
    window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'shift' }));
    window.dispatchEvent(new CustomEvent('pos-close-checkout'));
    emit('go-to-cashier');
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
