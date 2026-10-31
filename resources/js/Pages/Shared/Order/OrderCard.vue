<template>
    <div
        @click="$emit('click', order)"
        class="relative overflow-hidden p-option group w-full rounded-2xl transition-all duration-300 shrink-0 flex flex-col justify-center cursor-pointer outline-none p-3.5 border"
        :class="[
            order.status === 'delivered' ? 'border-emerald-500/50 bg-emerald-500/[0.03]' : (isWaitingPix
                ? 'border-warning-500/80 bg-warning-500/10 shadow-warning-500/10 ring-2 ring-warning-500/40 hover:bg-warning-500/15 animate-pulse'
                : (isPaymentUndefined && !isTeamMode ? 'bg-warning-500/10 border-warning-500/40 hover:bg-warning-500/20' : 'border-transparent bg-surface-2 hover:bg-surface-3')),
            isHighlighted ? 'ring-4 ring-purple-500 z-10 scale-[1.02] animate-pulse' : ''
        ]"
    >
        <div class="flex flex-col w-full gap-2">

            <!-- 📱 VISÃO MOBILE / GARÇOM (Minimalista e Ultra Legível) -->
            <div v-if="isTeamMode" class="flex flex-col items-center text-center gap-1 py-2">
                <span class="text-[10px] font-black uppercase tracking-widest" :class="order.type === 'table' ? 'text-primary-500' : 'text-surface-muted'">
                    {{ typeLabel }}
                </span>
                <span class="font-black text-surface-text text-xl leading-tight truncate w-full" :title="order.customer_name">
                    {{ formattedName }}
                </span>
                <span v-if="isWaitingPix" class="text-[9px] font-black uppercase tracking-wider text-warning-400 bg-warning-500/20 px-2 py-0.5 rounded border border-warning-500/40 mt-1 flex items-center gap-1 animate-pulse">
                    <i class="pi pi-clock text-[8px]"></i> Aguardando Pix
                </span>
            </div>

            <!-- 🖥️ VISÃO DESKTOP / CAIXA -->
            <template v-else>
                <!-- LINHA 1: Timer reativo | Número do pedido -->
                <div class="flex justify-between items-center w-full text-xs font-bold text-surface-muted select-none">
                    <span class="flex items-center gap-1.5 text-primary-smart">
                        <i class="pi pi-clock text-[10px]"></i>
                        {{ timeElapsed }}
                    </span>
                    <div class="flex items-center gap-1.5"><span v-if="order.status === 'delivered'" class="text-[9px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 px-2 py-0.5 rounded-full flex items-center gap-1 shadow-xs"><i class="pi pi-check-circle text-[10px] text-emerald-500"></i> Entregue</span><span>#{{ $formatNumber(order.id) }}</span></div>
                </div>

                <!-- LINHA 2: Nome da Mesa/Cliente | Setas de avanço ou Valor -->
                <div class="flex justify-between items-center w-full gap-3">
                    <span class="font-black text-surface-text leading-tight truncate flex-1 text-base" :title="order.customer_name">
                        {{ formattedName }}
                    </span>

                    <div v-if="['counter', 'delivery'].includes(order.type)" class="flex items-center gap-1 shrink-0">
                        <button @click.stop="$emit('move-prev', order)" class="w-7 h-7 rounded-full text-surface-muted hover:bg-primary-500/60 hover:text-white flex items-center justify-center transition-colors outline-none cursor-pointer">
                            <i class="pi pi-chevron-left text-[10px] font-bold"></i>
                        </button>
                        <button @click.stop="$emit('move-next', order)" class="w-7 h-7 rounded-full text-surface-muted hover:bg-primary-500/60 hover:text-white flex items-center justify-center transition-colors outline-none cursor-pointer">
                            <i class="pi pi-chevron-right text-[10px] font-bold"></i>
                        </button>
                    </div>
                    <span v-else-if="order.type === 'table'" class="font-black text-sm shrink-0">
                        {{ $formatCurrency(order.total) }}
                    </span>
                </div>

                <!-- LINHA 3: Bairro/Tipo | Status Inteligente do Pagamento -->
                <div v-if="['counter', 'delivery'].includes(order.type)" class="flex justify-between items-center w-full text-[11px] font-medium border-t border-surface-border/30 pt-2 mt-0.5">
                    <span class="text-surface-muted truncate max-w-[50%] flex items-center gap-1" :class="order.type === 'delivery' ? 'text-primary-600 dark:text-primary-400 font-bold' : ''">
                        <i v-if="order.type === 'delivery'" class="pi pi-map-marker text-[9px]"></i>
                        {{ typeLabel }}
                    </span>

                    <!-- BADGES INTELIGENTES DE PAGAMENTO -->
                    <div class="flex items-center gap-1.5 shrink-0 max-w-[50%] truncate justify-end">

                        <!-- 0. AGUARDANDO PIX -->
                        <span v-if="isWaitingPix" class="text-[9px] font-black uppercase tracking-wider text-warning-400 bg-warning-500/20 px-2 py-0.5 rounded border border-warning-500/40  flex items-center gap-1 animate-pulse" title="Aguardando confirmação do Pix">
                            <i class="pi pi-hourglass text-[8px]"></i> Pix
                        </span>

                        <!-- 1. PAGO 100% -->
                        <span v-else-if="isPaid" class="text-[9px] font-black uppercase tracking-widest text-green-700 dark:text-green-300 bg-green-500/20 px-2 py-0.5 rounded border border-green-500/30  flex items-center gap-1">
                            <i class="pi pi-check text-[8px]"></i> Pago
                        </span>

                        <!-- 2. NÃO DEFINIDO -->
                        <span v-else-if="isPaymentUndefined" class="text-[9px] font-black uppercase tracking-widest text-warning-700 dark:text-warning-300 bg-warning-500/20 px-2 py-0.5 rounded border border-warning-500/30  animate-pulse flex items-center gap-1">
                            <i class="pi pi-exclamation-triangle text-[8px]"></i> Não Definido
                        </span>

                        <!-- 3. MÉTODO COM OU SEM TROCO -->
                        <span v-else class="text-[10px] font-black text-primary-smart bg-primary-500/10 border border-primary-500/20 px-2 py-0.5 rounded truncate max-w-[140px]" :title="paymentMethodLabel">
                            {{ paymentMethodLabel }}
                        </span>

                    </div>
                </div>
            </template>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { formatCurrency, formatNumber } from "@/Utils/formatters";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

const props = defineProps({
    order: Object,
    showTotal: { type: Boolean, default: false },
    isHighlighted: { type: Boolean, default: false },
    isTeamMode: { type: Boolean, default: false }
});

defineEmits(["click", "move-prev", "move-next"]);
const { getOrderTitle } = useOrderFormatting();

const now = ref(new Date());
let clockInterval = null;

onMounted(() => {
    clockInterval = setInterval(() => {
        now.value = new Date();
    }, 180000);
});

onBeforeUnmount(() => {
    if (clockInterval) clearInterval(clockInterval);
});

const parseDateStr = (dateStr) => {
    if (!dateStr) return null;
    let manipulatedStr = dateStr;
    if (typeof manipulatedStr === 'string') {
        manipulatedStr = manipulatedStr.replace(' ', 'T');
        if (!manipulatedStr.endsWith('Z') && !manipulatedStr.includes('-') && !manipulatedStr.includes('+')) {
            manipulatedStr += 'Z';
        }
    }
    const d = new Date(manipulatedStr);
    return isNaN(d.getTime()) ? null : d;
};

const timeElapsed = computed(() => {
    if (!props.order?.created_at) return "0min";

    const created = parseDateStr(props.order.created_at);
    if (!created) return "0min";

    // 🛑 Se o motoboy já indicou entrega (ou se o pedido foi finalizado), congela o timer!
    let referenceEnd = now.value;

    if (props.order.status === 'delivered' || props.order.delivered_at) {
        const deliveredStr = props.order.delivered_at || props.order.updated_at;
        const deliveredDate = parseDateStr(deliveredStr);
        if (deliveredDate && deliveredDate >= created) {
            referenceEnd = deliveredDate;
        }
    } else if (props.order.status === 'closed' && props.order.closed_at) {
        const closedDate = parseDateStr(props.order.closed_at);
        if (closedDate && closedDate >= created) {
            referenceEnd = closedDate;
        }
    }

    const diffMs = referenceEnd - created;
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 0) return "0min";
    if (diffMins < 60) return `${diffMins}min`;

    const hours = Math.floor(diffMins / 60);
    const mins = diffMins % 60;
    return mins > 0 ? `${hours}h ${mins}min` : `${hours}h`;
});

// 🚀 REGRAS FINANCEIRAS INTELIGENTES DO CARD
const isPaid = computed(() => {
    if (props.order?.payment_status === 'paid') return true;
    if (props.order?.payment_intent_status === 'pix_waiting' || props.order?.status === 'pending_payment') return false;
    const totalPaid = (props.order?.payments || [])
        .filter(p => p.is_paid === true || p.is_paid === 1 || p.status === 'completed' || p.status === 'paid')
        .reduce((sum, p) => sum + Number(p.amount || 0), 0);
    const orderTotal = Number(props.order?.total || 0);
    return orderTotal > 0 && totalPaid >= (orderTotal - 0.05);
});

// ⚠️ RECONHECIMENTO DE PIX PENDENTE (AGUARDANDO WEBHOOK OU COMPROVANTE)
const isWaitingPix = computed(() => {
    if (isPaid.value) return false;
    return props.order?.payment_intent_status === 'pix_waiting'
        || props.order?.status === 'pending_payment';
});

const deliveryMethodsParsed = computed(() => {
    let methods = props.order?.payment_intent_methods;
    if (!methods) return [];
    if (typeof methods === 'string') {
        try { methods = JSON.parse(methods); } catch (e) { return []; }
    }
    return Array.isArray(methods) ? methods : [];
});

const isPaymentUndefined = computed(() => {
    if (props.order?.type === 'table' || isPaid.value || isWaitingPix.value) return false;

    const status = props.order?.payment_intent_status;
    if (!status || status === 'pending_choice') return true;
    if (status === 'pay_on_delivery' && deliveryMethodsParsed.value.length === 0) return true;

    return false;
});

const paymentMethodLabel = computed(() => {
    if (isPaid.value) return 'Pago';

    if (isWaitingPix.value) {
        return 'Aguardando Pix';
    }

    if (props.order?.payment_intent_status === 'pay_now') {
        return 'Pagar no Caixa';
    }

    if (deliveryMethodsParsed.value.length > 0) {
        return deliveryMethodsParsed.value.map(m => {
            const name = m.method_name || m.name || m.method_id || 'Dinheiro';
            if (m.change_target && Number(m.change_target) > Number(m.amount)) {
                return `${name} (Troco R$ ${Number(m.change_target).toFixed(0)})`;
            }
            return name;
        }).join(' + ');
    }

    if (props.order?.payments && props.order.payments.length > 0) {
        return props.order.payments.map(p => p.method || p.method_name).join(' + ');
    }

    return null;
});

const formattedName = computed(() => {
    if (props.isTeamMode && props.order.type === "table") {
        const tableName = String(props.order.table_number || "Avulsa");
        const customerName = String(props.order.identifier || props.order.customer_name || "");

        const isGenericName = !customerName ||
                              customerName.toLowerCase() === tableName.toLowerCase() ||
                              customerName.toLowerCase() === `mesa ${tableName.toLowerCase()}` ||
                              customerName.toLowerCase() === 'geral' ||
                              customerName.toLowerCase() === 'nova conta';

        return isGenericName ? tableName : `${tableName} (${customerName})`;
    }

    return getOrderTitle(props.order);
});

const typeLabel = computed(() => {
    if (props.order.type === "table") return "Mesa";
    if (props.order.type === "counter") return "Balcão";

    const neighborhood =
        props.order.address?.neighborhood ||
        props.order.routing?.address?.neighborhood ||
        props.order.meta?.address?.neighborhood ||
        props.order.neighborhood ||
        props.order.delivery_neighborhood;

    return neighborhood || "Delivery";
});
</script>
