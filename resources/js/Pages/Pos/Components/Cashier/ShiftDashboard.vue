<template>
    <div class="h-full flex flex-col overflow-y-auto hide-scrollbar">

        <!-- CABEÇALHO FINANCEIRO -->
        <div class="p-6 text-white shrink-0">
            <h1 class="text-xl font-bold mb-1">Painel do Turno</h1>
            <p class="text-xs text-surface-muted mb-6 font-medium">Aberto às {{ formattedShiftStart }}</p>

            <div class="flex flex-col gap-4">
                <div class="bg-surface-3/50 rounded-3xl p-5">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <p class="text-[10px] font-black uppercase tracking-widest text-primary-400">Faturamento Bruto</p>
                        <span v-if="hasOpenOrders" class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-400">
                            + {{ $formatCurrency(openStats.total.amount) }} aberto
                        </span>
                    </div>

                    <h2 class="text-3xl font-black text-white leading-tight">
                        {{ $formatCurrency(displayRevenue) }}
                    </h2>

                    <div v-if="hasOpenOrders" class="mt-2 pt-2 border-t border-white/10 flex items-center justify-between text-xs">
                        <span class="text-surface-400 font-bold uppercase tracking-wider text-[10px]">Total c/ abertos</span>
                        <span class="font-black text-amber-300 text-sm">{{ $formatCurrency(projectedRevenue) }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-3 mt-3 text-xs flex-wrap">
                        <div>
                            <span class="opacity-60">Pedidos: </span>
                            <span class="font-bold">{{ displayOrderCount }}</span>
                            <span v-if="hasOpenOrders" class="text-amber-300/90 font-bold ml-1">({{ projectedOrderCount }} total)</span>
                        </div>
                        <div>
                            <span class="opacity-60">Ticket Médio: </span>
                            <span class="font-bold">{{ $formatCurrency(displayAvgTicket) }}</span>
                            <span v-if="hasOpenOrders" class="text-amber-300/90 font-bold ml-1">({{ $formatCurrency(projectedAvgTicket) }})</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-surface-3/50 rounded-3xl p-4 flex flex-col justify-center">
                        <span class="text-[9px] font-black uppercase tracking-widest text-surface-400">Saldo em Gaveta</span>
                        <span class="text-xl font-black text-success-400 mt-1">{{ $formatCurrency(expectedInDrawer) }}</span>
                    </div>

                    <div class="bg-surface-3/50 rounded-3xl p-4 flex flex-col justify-center transition-all duration-300" :class="selectedCount > 0 ? 'bg-primary-900/30 border-primary-500/50' : ''">
                        <span class="text-[9px] font-black uppercase tracking-widest text-surface-400" :class="selectedCount > 0 ? 'text-primary-300' : ''">
                            Auditoria <span v-if="selectedCount > 0">({{ selectedCount }})</span>
                        </span>
                        <span class="text-xl font-black text-white mt-1">{{ $formatCurrency(dynamicTotal) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEÇÃO DE VENDAS POR CANAL -->
        <div class="p-6 shrink-0">
            <h3 class="font-black text-surface-text mb-4 uppercase tracking-widest text-[10px] opacity-50">Distribuição de Receita</h3>

            <div class="grid grid-cols-1 gap-3">
                <!-- DELIVERY -->
                <div class="bg-surface-3/50 p-3.5 rounded-2xl flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-surface-3 text-surface-muted/80 flex items-center justify-center shrink-0">
                            <i class="pi pi-truck text-sm font-black"></i>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="font-bold text-surface-text text-sm leading-tight">Delivery</span>
                            <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">
                                {{ displayByType.delivery.count }} realizados
                                <template v-if="openStats.byType.delivery.count > 0">
                                    • <span class="text-amber-400">{{ openStats.byType.delivery.count }} aberto(s)</span>
                                </template>
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col items-end shrink-0">
                        <span class="font-black text-sm">{{ $formatCurrency(displayByType.delivery.amount) }}</span>
                        <span v-if="openStats.byType.delivery.amount > 0" class="text-[10px] font-bold text-amber-400 mt-0.5">
                            Total: {{ $formatCurrency(projectedByType.delivery.amount) }}
                        </span>
                    </div>
                </div>

                <!-- SALÃO -->
                <div class="bg-surface-3/50 p-3.5 rounded-2xl flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-surface-3 text-surface-muted/80 flex items-center justify-center shrink-0">
                            <i class="pi pi-table text-sm font-black"></i>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="font-bold text-surface-text text-sm leading-tight">Salão (Mesas)</span>
                            <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">
                                {{ displayByType.table.count }} realizados
                                <template v-if="openStats.byType.table.count > 0">
                                    • <span class="text-amber-400">{{ openStats.byType.table.count }} aberta(s)</span>
                                </template>
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col items-end shrink-0">
                        <span class="font-black text-sm">{{ $formatCurrency(displayByType.table.amount) }}</span>
                        <span v-if="openStats.byType.table.amount > 0" class="text-[10px] font-bold text-amber-400 mt-0.5">
                            Total: {{ $formatCurrency(projectedByType.table.amount) }}
                        </span>
                    </div>
                </div>

                <!-- BALCÃO -->
                <div class="bg-surface-3/50 p-3.5 rounded-2xl flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-surface-3 text-surface-muted/80 flex items-center justify-center shrink-0">
                            <i class="pi pi-shopping-bag text-sm font-black"></i>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="font-bold text-surface-text text-sm leading-tight">Balcão</span>
                            <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">
                                {{ displayByType.counter.count }} realizados
                                <template v-if="openStats.byType.counter.count > 0">
                                    • <span class="text-amber-400">{{ openStats.byType.counter.count }} aberto(s)</span>
                                </template>
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col items-end shrink-0">
                        <span class="font-black text-sm">{{ $formatCurrency(displayByType.counter.amount) }}</span>
                        <span v-if="openStats.byType.counter.amount > 0" class="text-[10px] font-bold text-amber-400 mt-0.5">
                            Total: {{ $formatCurrency(projectedByType.counter.amount) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    currentShift: { type: Object, default: () => null },
    dynamicTotal: { type: Number, default: 0 },
    expectedInDrawer: { type: Number, default: 0 },
    selectedCount: { type: Number, default: 0 },
    unifiedHistory: { type: Array, default: () => [] }
});

const page = usePage();

const normalizeChannel = (type) => {
    if (type === 'delivery') return 'delivery';
    if (type === 'table') return 'table';
    return 'counter';
};

const shiftOrders = computed(() => {
    const fromUnified = (props.unifiedHistory || []).filter(item => item._type === 'order' && item.status !== 'cancelled');
    if (fromUnified.length > 0) return fromUnified;

    const fromProps = (page.props.ordersHistory || []).filter(o => o.status !== 'cancelled');
    return fromProps.map(o => ({ ...o, amount: Number(o.total || o.amount || 0) }));
});

const realizedOrderIds = computed(() => {
    return new Set(shiftOrders.value.map(o => Number(o.id)));
});

const displayRevenue = computed(() => shiftOrders.value.reduce((sum, order) => sum + Number(order.amount ?? order.total ?? order._mathValue ?? 0), 0));
const displayOrderCount = computed(() => shiftOrders.value.length);
const displayAvgTicket = computed(() => displayOrderCount.value === 0 ? 0 : displayRevenue.value / displayOrderCount.value);

const displayByType = computed(() => {
    const byType = {
        delivery: { amount: 0, count: 0 },
        table: { amount: 0, count: 0 },
        counter: { amount: 0, count: 0 }
    };

    shiftOrders.value.forEach(order => {
        const channel = normalizeChannel(order.type);
        const val = Number(order.amount ?? order.total ?? order._mathValue ?? 0);

        byType[channel].amount += val;
        byType[channel].count += 1;
    });

    return byType;
});

// Pedidos ativos em aberto no turno (evita dupla contagem de pedidos já pagos/realizados)
const openStats = computed(() => {
    const stats = {
        total: { amount: 0, count: 0 },
        byType: {
            delivery: { amount: 0, count: 0 },
            table: { amount: 0, count: 0 },
            counter: { amount: 0, count: 0 }
        }
    };

    const terminalStatuses = ['closed', 'cancelled', 'completed', 'merged'];
    const activeOrders = (page.props.initialOrders || []).filter(o => o && !terminalStatuses.includes(o.status));

    activeOrders.forEach(o => {
        const orderTotal = Number(o.total || 0);
        const alreadyPaid = o.payment_status === 'paid'
            ? orderTotal
            : (Array.isArray(o.payments)
                ? o.payments.filter(p => p.status !== 'cancelled').reduce((s, p) => s + Number(p.amount || 0), 0)
                : 0);

        const openAmount = Math.max(0, Number((orderTotal - alreadyPaid).toFixed(2)));
        const isAlreadyCountedInRealized = realizedOrderIds.value.has(Number(o.id));

        if (openAmount <= 0.01 && isAlreadyCountedInRealized) {
            return;
        }

        const channel = normalizeChannel(o.type);

        if (openAmount > 0.01) {
            stats.total.amount += openAmount;
            stats.byType[channel].amount += openAmount;
        }

        if (!isAlreadyCountedInRealized) {
            stats.total.count += 1;
            stats.byType[channel].count += 1;
        }
    });

    return stats;
});

const hasOpenOrders = computed(() => openStats.value.total.amount > 0.01 || openStats.value.total.count > 0);

const projectedRevenue = computed(() => displayRevenue.value + openStats.value.total.amount);
const projectedOrderCount = computed(() => displayOrderCount.value + openStats.value.total.count);
const projectedAvgTicket = computed(() => projectedOrderCount.value === 0 ? 0 : projectedRevenue.value / projectedOrderCount.value);

const projectedByType = computed(() => ({
    delivery: {
        amount: displayByType.value.delivery.amount + openStats.value.byType.delivery.amount,
        count: displayByType.value.delivery.count + openStats.value.byType.delivery.count
    },
    table: {
        amount: displayByType.value.table.amount + openStats.value.byType.table.amount,
        count: displayByType.value.table.count + openStats.value.byType.table.count
    },
    counter: {
        amount: displayByType.value.counter.amount + openStats.value.byType.counter.amount,
        count: displayByType.value.counter.count + openStats.value.byType.counter.count
    }
}));

const parseSafeTime = (dateVal) => {
    if (!dateVal) return null;
    const str = String(dateVal).trim();
    const match = str.match(/(?:T|\s|^)([01]\d|2[0-3]):([0-5]\d)/);
    if (match) return `${match[1]}:${match[2]}`;
    try {
        const d = new Date(str);
        if (!isNaN(d.getTime())) return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {}
    return null;
};

const formattedShiftStart = computed(() => {
    const shift = props.currentShift || page.props.currentShift;
    const shiftDate = shift?.opened_at || shift?.created_at;
    if (shiftDate) {
        const parsed = parseSafeTime(shiftDate);
        if (parsed) return parsed;
    }

    const openTx = (props.unifiedHistory || []).find(item => item._type === 'transaction' && ((item.description && item.description.toLowerCase().includes('abert')) || item.is_opening_balance === true));
    if (openTx) return parseSafeTime(openTx.created_at || openTx.time);

    return '--:--';
});
</script>
