<template>
    <div class="h-full flex flex-col overflow-y-auto hide-scrollbar">

        <!-- CABEÇALHO FINANCEIRO -->
        <div class="p-6 text-white shrink-0">
            <h1 class="text-xl font-bold mb-1">Painel do Turno</h1>
            <p class="text-xs text-surface-muted mb-6 font-medium">Aberto às {{ formattedShiftStart }}</p>

            <div class="flex flex-col gap-4">
                <div class="bg-surface-3/50 rounded-3xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest mb-1 text-primary-400">Faturamento Bruto</p>
                    <h2 class="text-3xl font-black mb-1 text-white">
                        {{ $formatCurrency(displayRevenue) }}
                    </h2>
                    <div class="flex items-center gap-4 mt-3 text-xs">
                        <div><span class="opacity-60">Pedidos: </span><span class="font-bold">{{ displayOrderCount }}</span></div>
                        <div><span class="opacity-60">Ticket Médio: </span><span class="font-bold">{{ $formatCurrency(displayAvgTicket) }}</span></div>
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
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-surface-3 text-surface-muted/80 flex items-center justify-center shrink-0">
                            <i class="pi pi-truck text-sm font-black"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-surface-text text-sm leading-tight">Delivery</span>
                            <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ displayByType.delivery.count }} pedidos</span>
                        </div>
                    </div>
                    <span class="font-black text-sm">{{ $formatCurrency(displayByType.delivery.amount) }}</span>
                </div>

                <!-- SALÃO -->
                <div class="bg-surface-3/50 p-3.5 rounded-2xl flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-surface-3 text-surface-muted/80 flex items-center justify-center shrink-0">
                            <i class="pi pi-table text-sm font-black"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-surface-text text-sm leading-tight">Salão (Mesas)</span>
                            <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ displayByType.table.count }} pedidos</span>
                        </div>
                    </div>
                    <span class="font-black text-sm">{{ $formatCurrency(displayByType.table.amount) }}</span>
                </div>

                <!-- BALCÃO -->
                <div class="bg-surface-3/50 p-3.5 rounded-2xl flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-surface-3 text-surface-muted/80 flex items-center justify-center shrink-0">
                            <i class="pi pi-shopping-bag text-sm font-black"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-surface-text text-sm leading-tight">Balcão</span>
                            <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ displayByType.counter.count }} pedidos</span>
                        </div>
                    </div>
                    <span class="font-black text-sm">{{ $formatCurrency(displayByType.counter.amount) }}</span>
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

const formatMoney = (value) => parseFloat(value || 0).toFixed(2).replace('.', ',');

const shiftOrders = computed(() => {
    const fromUnified = (props.unifiedHistory || []).filter(item => item._type === 'order' && item.status !== 'cancelled');
    if (fromUnified.length > 0) return fromUnified;

    const fromProps = (page.props.ordersHistory || []).filter(o => o.status !== 'cancelled');
    return fromProps.map(o => ({ ...o, amount: Number(o.total || o.amount || 0) }));
});

const displayRevenue = computed(() => shiftOrders.value.reduce((sum, order) => sum + Number(order.amount ?? order.total ?? order._mathValue ?? 0), 0));
const displayOrderCount = computed(() => shiftOrders.value.length);
const displayAvgTicket = computed(() => displayOrderCount.value === 0 ? 0 : displayRevenue.value / displayOrderCount.value);

// 🚀 Aqui separamos por Valor (amount) e Quantidade (count)
const displayByType = computed(() => {
    const byType = {
        delivery: { amount: 0, count: 0 },
        table: { amount: 0, count: 0 },
        counter: { amount: 0, count: 0 }
    };

    shiftOrders.value.forEach(order => {
        const type = order.type || 'counter';
        const val = Number(order.amount ?? order.total ?? order._mathValue ?? 0);

        if (byType[type] !== undefined) {
            byType[type].amount += val;
            byType[type].count += 1;
        } else {
            byType.counter.amount += val;
            byType.counter.count += 1;
        }
    });

    return byType;
});

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
