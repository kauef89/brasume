<template>
    <div class="c-glass-card select-none transition-all duration-200">

        <!-- ==========================================
             CABEÇALHO (Sempre Visível - Resumo)
             ========================================== -->
        <div @click="isExpanded = !isExpanded" class="p-4 flex items-center justify-between cursor-pointer transition-colors active:bg-white/5">
            <div class="flex flex-col gap-1.5 min-w-0 flex-1 mr-3">

                <!-- Linha 1: ID, Modalidade e Status -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="c-text-main font-black">#{{ formattedOrderId }}</span>

                    <span class="c-badge !bg-white/10 !text-white/80 flex items-center gap-1">
                        <i :class="['pi text-[9px]', typeInfo.icon]"></i> {{ typeInfo.label }}
                    </span>

                    <span class="c-badge" :class="statusBadge.class">
                        {{ statusBadge.label }}
                    </span>
                </div>

                <!-- Linha 2: Data, Valor e Status de Pagamento -->
                <div class="flex items-center gap-2 text-xs c-text-muted flex-wrap">
                    <span class="flex items-center gap-1">
                        <i class="pi pi-calendar text-[11px]"></i> {{ formattedDate }}
                    </span>
                    <span class="w-1 h-1 rounded-full bg-white/20 shrink-0"></span>
                    <span class="text-white font-bold">{{ $formatCurrency(orderTotal) }}</span>
                    <span class="w-1 h-1 rounded-full bg-white/20 shrink-0"></span>
                    <span
                        class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full border"
                        :class="paymentStatusBadge.class"
                    >
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="paymentStatusBadge.dotClass"></span>
                        {{ paymentStatusBadge.label }}
                    </span>
                </div>
            </div>

            <!-- Chevron de Expansão -->
            <div class="flex items-center shrink-0">
                <div class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-white/50 transition-transform duration-300" :class="{ 'rotate-180 !text-white': isExpanded }">
                    <i class="pi pi-chevron-down text-xs"></i>
                </div>
            </div>
        </div>

        <!-- ==========================================
             CORPO EXPANSÍVEL
             ========================================== -->
        <div v-if="isExpanded" class="p-4 pt-2 bg-black/20 flex flex-col gap-4 animate-in fade-in duration-200">

            <!-- ITENS DO PEDIDO -->
            <div class="flex flex-col gap-1 pt-2">
                <span class="text-xs font-black uppercase tracking-wider text-white/50 flex items-center gap-1.5">
                    <i class="pi pi-shopping-bag text-xs"></i> Itens do Pedido
                </span>

                <div class="flex flex-col mt-1">
                    <template v-for="(item, idx) in order.items" :key="item.id || idx">
                        <CustomerOrderHistoryItemRow :item="item" />
                        <div v-if="idx < order.items.length - 1" class="h-px w-full bg-white/5 my-1.5"></div>
                    </template>
                </div>
            </div>

            <!-- DETALHES FINANCEIROS -->
            <div class="flex flex-col gap-2">
                <span class="text-xs font-black uppercase tracking-wider text-white/50 flex items-center gap-1.5">
                    <i class="pi pi-chart-pie text-xs"></i> Detalhes Financeiros
                </span>

                <!-- Formas de Pagamento -->
                <div class="flex flex-col bg-white/5 rounded-2xl overflow-hidden p-3 gap-1.5">
                    <div v-for="(pay, i) in paymentDetails" :key="i" class="flex justify-between items-center text-xs">
                        <span class="font-medium text-white/80 flex items-center gap-1.5">
                            <i :class="pay.icon" class="text-xs"></i> {{ pay.label }}
                        </span>
                        <span class="font-bold text-white">
                            {{ pay.value !== null ? $formatCurrency(pay.value) : '--' }}
                        </span>
                    </div>
                </div>

                <!-- Cupom Aplicado -->
                <div v-if="order.coupon" class="flex items-center justify-between p-3 bg-primary-500/15 border border-primary-500/20 rounded-2xl text-xs">
                    <span class="text-primary-300 font-bold flex items-center gap-1.5">
                        <i class="pi pi-ticket text-xs"></i> Cupom Aplicado
                    </span>
                    <span class="font-black text-primary-300 uppercase tracking-wider">
                        {{ order.coupon.code }}
                    </span>
                </div>

                <!-- ==========================================
                                     💰 INCENTIVOS E MARKETING (GANHOS DO CLIENTE)
                                     ========================================== -->
                                <div v-if="order.loyalty_points_earned > 0 || order.cashback_earned > 0" class="flex flex-col gap-1">

                                    <!-- Linha: Cashback (Dinheiro de Volta) -->
                                    <div v-if="order.cashback_earned > 0" class="flex items-center justify-between p-3 bg-emerald-500/15 border border-emerald-500/20 rounded-2xl text-xs">
                                        <div class="flex flex-col">
                                            <span class="font-black uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                                                <i class="pi pi-money-bill text-xs"></i> Cashback Ganho
                                            </span>
                                            <span class="text-[10px] text-emerald-500/80 font-medium">Disponível na sua carteira</span>
                                        </div>
                                        <span class="font-black text-emerald-400 text-sm">+ {{ $formatCurrency(order.cashback_earned) }}</span>
                                    </div>

                                    <!-- Linha: Pontos de Fidelidade -->
                                    <div v-if="order.loyalty_points_earned > 0" class="flex items-center justify-between p-3 bg-purple-500/15 border border-purple-500/20 rounded-2xl text-xs">
                                        <span class="font-black uppercase tracking-wider text-purple-400 flex items-center gap-1.5">
                                            <i class="pi pi-star-fill text-xs"></i> Pontos de Fidelidade
                                        </span>
                                        <span class="font-black text-purple-300 text-sm">+ {{ order.loyalty_points_earned }} pts</span>
                                    </div>

                                </div>

                <!-- Resumo dos Valores -->
                <div class="flex flex-col gap-1.5 p-3 bg-white/5 rounded-2xl">
                    <div class="flex justify-between items-center text-xs c-text-muted">
                        <span>Subtotal dos Produtos</span>
                        <span class="text-white/90 font-bold">{{ $formatCurrency(orderSubtotal) }}</span>
                    </div>

                    <div v-if="orderDeliveryFee > 0" class="flex justify-between items-center text-xs c-text-muted">
                        <span>Taxa de Entrega</span>
                        <span class="text-white/90 font-bold">{{ $formatCurrency(orderDeliveryFee) }}</span>
                    </div>

                    <div v-if="orderServiceFee > 0" class="flex justify-between items-center text-xs c-text-muted">
                        <span>Taxa de Serviço</span>
                        <span class="text-white/90 font-bold">{{ $formatCurrency(orderServiceFee) }}</span>
                    </div>

                    <div v-if="orderDiscount > 0" class="flex justify-between items-center text-xs font-black text-emerald-400 pt-0.5">
                        <span>Desconto / Benefício</span>
                        <span>- {{ $formatCurrency(orderDiscount) }}</span>
                    </div>

                    <div class="flex justify-between items-center pt-2.5 mt-1 border-t border-white/10">
                        <span class="c-title !text-sm">Total</span>
                        <span class="c-title !text-base text-primary-400">{{ $formatCurrency(orderTotal) }}</span>
                    </div>
                </div>
            </div>

            <!-- AÇÕES DO PEDIDO (Apenas modo lista) -->
            <div v-if="!isWidgetMode" class="flex items-center gap-2 pt-1 w-full">
                <!-- Recibo Fiscal -->
                <component
                    :is="canViewReceipt ? 'a' : 'div'"
                    :href="canViewReceipt ? route('customer.orders.receipt', order.id) : null"
                    :target="canViewReceipt ? '_blank' : null"
                    class="c-btn-ghost flex-1 !h-11 !text-xs no-underline"
                    :class="!canViewReceipt ? 'opacity-40 pointer-events-none' : ''"
                >
                    <i class="pi pi-file-pdf text-xs" :class="canViewReceipt ? 'text-red-400' : ''"></i>
                    <span>{{ canViewReceipt ? 'Recibo' : 'Sem Recibo' }}</span>
                </component>

                <!-- Repetir Pedido -->
                <button
                    @click="$emit('reorder', order)"
                    class="c-btn-primary flex-1 !h-11 !text-xs"
                >
                    <i class="pi pi-refresh text-xs"></i>
                    <span>Repetir Pedido</span>
                </button>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { formatCurrency } from '@/Utils/formatters';
import CustomerOrderHistoryItemRow from './CustomerOrderHistoryItemRow.vue';

const props = defineProps({
    order: { type: Object, required: true },
    isWidgetMode: { type: Boolean, default: false }
});

defineEmits(['reorder']);

const isExpanded = ref(false);

const formattedOrderId = computed(() => {
    return Number(props.order.id || 0).toLocaleString('pt-BR');
});

const orderTotal = computed(() => props.order.total || 0);
const orderSubtotal = computed(() => props.order.subtotal || 0);
const orderDeliveryFee = computed(() => props.order.delivery_fee || 0);
const orderServiceFee = computed(() => props.order.service_fee || 0);
const orderDiscount = computed(() => props.order.discount || 0);

// 🚀 Badge dinâmico de status financeiro (Pago / Pendente / Estornado)
const paymentStatusBadge = computed(() => {
    const ps = props.order.payment_status;
    const dps = props.order.payment_intent_status;

    if (ps === 'paid' || dps === 'paid') {
        return {
            label: 'Pago',
            class: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/20',
            dotClass: 'bg-emerald-400'
        };
    }

    if (ps === 'refunded') {
        return {
            label: 'Estornado',
            class: 'bg-purple-500/15 text-purple-400 border-purple-500/20',
            dotClass: 'bg-purple-400'
        };
    }

    return {
        label: 'Pagto. Pendente',
        class: 'bg-amber-500/15 text-amber-400 border-amber-500/20',
        dotClass: 'bg-amber-400 animate-pulse'
    };
});

const hasCashback = computed(() => {
    return Number(props.order.cashback_used) > 0 ||
           (props.order.payments || []).some(p => p.payment_method?.engine_type === 'internal_loyalty');
});

const typeInfo = computed(() => {
    const t = props.order.type || 'delivery';
    if (t === 'delivery') return { label: 'Entrega', icon: 'pi-truck' };
    if (t === 'table') return { label: `Mesa ${props.order.table_number || ''}`.trim(), icon: 'pi-book' };
    if (t === 'counter') return { label: 'Balcão', icon: 'pi-shop' };
    if (['pickup', 'takeaway'].includes(t)) return { label: 'Retirada', icon: 'pi-shopping-bag' };
    return { label: 'Pedido', icon: 'pi-receipt' };
});

const formattedDate = computed(() => {
    if (!props.order.created_at) return '';
    const date = new Date(props.order.created_at);

    let weekday = new Intl.DateTimeFormat('pt-BR', { weekday: 'short' }).format(date).replace('.', '');
    weekday = weekday.charAt(0).toUpperCase() + weekday.slice(1);
    const dateStr = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' }).format(date);

    return `${weekday}, ${dateStr}`;
});

const statusBadge = computed(() => {
    const s = props.order.status;
    if (['delivered', 'closed', 'completed'].includes(s)) return { label: 'Concluído', class: 'bg-success-400/10 text-success-500' };
    if (s === 'cancelled') return { label: 'Cancelado', class: 'bg-danger-400/10 text-danger-400' };
    return { label: 'Em Andamento', class: 'bg-warning-400/10 text-warning-400' };
});

const paymentDetails = computed(() => {
    const list = [];
    let totalRealPaid = 0;

    if (props.order.payments && props.order.payments.length > 0) {
        props.order.payments.forEach((p) => {
            const name = String(p.method_name || p.payment_method?.name || p.method || 'Pagamento');
            let icon = 'pi pi-wallet text-surface-muted';
            const isCashback = p.payment_method?.engine_type === 'internal_loyalty';

            const nameLower = name.toLowerCase();
            if (isCashback) icon = 'pi pi-star-fill text-yellow-500';
            else if (nameLower.includes('pix')) icon = 'pi pi-qrcode text-teal-500';
            else if (nameLower.includes('cartão') || nameLower.includes('credito') || nameLower.includes('debito')) icon = 'pi pi-credit-card text-blue-500';
            else if (nameLower.includes('dinheiro') || nameLower.includes('cash')) icon = 'pi pi-money-bill text-emerald-500';

            list.push({ label: name, value: Number(p.amount), isCashback: isCashback, icon });
            totalRealPaid += Number(p.amount);
        });
    } else if (props.order.payment_intent_methods) {
        try {
            const arr = typeof props.order.payment_intent_methods === 'string'
                ? JSON.parse(props.order.payment_intent_methods)
                : props.order.payment_intent_methods;

            arr.forEach(a => {
                const name = String(a.method_name || a.method_id || a);
                list.push({ label: `Pagar na Entrega: ${name}`, value: null, isCashback: false, icon: 'pi pi-wallet text-surface-muted' });
            });
        } catch(e) {
            list.push({ label: 'Pagar na Entrega', value: null, isCashback: false, icon: 'pi pi-wallet text-surface-muted' });
        }
    }

    const cashbackVal = Number(props.order.cashback_used || orderDiscount.value);
    const isCashbackListed = list.some(l => l.isCashback === true);

    if (hasCashback.value && !isCashbackListed) {
        if (cashbackVal > 0) {
            list.push({ label: 'Cashback Utilizado', value: cashbackVal, isCashback: true, icon: 'pi pi-star-fill text-yellow-500' });
        } else if (props.order.payment_status === 'paid' && list.length === 0) {
            list.push({ label: 'Saldo de Cashback (100%)', value: orderTotal.value, isCashback: true, icon: 'pi pi-star-fill text-yellow-500' });
        }
    }

    if (list.length === 0) {
        list.push({ label: 'Não Informado', value: null, isCashback: false, icon: 'pi pi-question-circle text-surface-muted' });
    }

    return list;
});

const canViewReceipt = computed(() => {
    // 🚀 Pedidos legados e cancelados não possuem DANFE PDF nativa
    if (props.order.is_legacy || props.order.status === 'cancelled') return false;

    if (typeof props.order.has_fiscal_document !== 'undefined') {
        return Boolean(props.order.has_fiscal_document);
    }

    return ['delivered', 'closed', 'completed'].includes(props.order.status);
});
</script>
