<template>
    <!-- Adicionamos min-h-screen para mobile ficar elegante -->
    <div class="min-h-screen bg-surface-1 flex flex-col font-sans pb-20">

        <!-- HEADER MOBILE -->
        <div class="bg-surface-900 text-white p-6 rounded-b-4xl shadow-md">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-xl font-black">Visão do Gerente</h1>
                <span :class="currentShift ? 'bg-success-500/20 text-success-400' : 'bg-danger-500/20 text-danger-400'" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                    {{ currentShift ? 'Turno Aberto' : 'Turno Fechado' }}
                </span>
            </div>

            <p v-if="currentShift" class="text-surface-400 text-xs mb-4 font-medium flex items-center gap-2">
                <i class="pi pi-clock"></i> Aberto às {{ formattedShiftStart }}
            </p>

            <div class="bg-surface-800 rounded-2xl p-5 border border-surface-700 shadow-inner">
                <p class="text-[10px] font-black opacity-80 uppercase tracking-widest mb-1 text-primary-400">Faturamento Realizado</p>
                <h2 class="text-3xl font-black mb-1 text-white">
                    {{ $formatCurrency(displayRevenue) }}
                </h2>
                <div class="flex items-center gap-4 mt-3 text-xs">
                    <div><span class="opacity-60">Pedidos: </span><span class="font-bold">{{ displayOrderCount }}</span></div>
                    <div><span class="opacity-60">Ticket Médio: </span><span class="font-bold">{{ $formatCurrency(displayAvgTicket) }}</span></div>
                </div>
            </div>
        </div>

        <!-- CONTEÚDO -->
        <div class="p-4 sm:p-6 mt-2 flex flex-col gap-6">

            <!-- CAIXA E AUDITORIA -->
            <div>
                <h3 class="font-black text-surface-text mb-3 uppercase tracking-widest text-[10px] opacity-50 ml-2">Monitoramento de Caixa</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-surface-2 rounded-2xl p-4 border border-surface-border shadow-sm flex flex-col justify-center">
                        <span class="text-[9px] font-black uppercase tracking-widest text-surface-muted">Saldo Inicial</span>
                        <span class="text-lg font-black text-surface-text mt-1">{{ $formatCurrency(currentShift?.opening_balance || 0) }}</span>
                    </div>

                    <div class="bg-surface-2 rounded-2xl p-4 border border-surface-border shadow-sm flex flex-col justify-center">
                        <span class="text-[9px] font-black uppercase tracking-widest text-surface-muted">Dinheiro Físico Esperado</span>
                        <span class="text-lg font-black text-success-600 mt-1">{{ $formatCurrency(managerData?.expected_cash || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- DISTRIBUIÇÃO DE RECEITA -->
            <div>
                <h3 class="font-black text-surface-text mb-3 uppercase tracking-widest text-[10px] opacity-50 ml-2">Distribuição por Canal</h3>
                <div class="flex flex-col gap-3">
                    <div class="bg-surface-2 p-4 rounded-2xl border border-surface-border flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary-500/10 text-primary-600 flex items-center justify-center shrink-0">
                                <i class="pi pi-motorcycle text-sm font-black"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-text text-sm leading-tight">Delivery</span>
                                <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ byType.delivery.count }} pedidos</span>
                            </div>
                        </div>
                        <span class="font-black text-base text-surface-text">{{ $formatCurrency(byType.delivery.amount) }}</span>
                    </div>

                    <div class="bg-surface-2 p-4 rounded-2xl border border-surface-border flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-success-500/10 text-success-600 flex items-center justify-center shrink-0">
                                <i class="pi pi-table text-sm font-black"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-text text-sm leading-tight">Salão (Mesas)</span>
                                <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ byType.table.count }} pedidos</span>
                            </div>
                        </div>
                        <span class="font-black text-base text-surface-text">{{ $formatCurrency(byType.table.amount) }}</span>
                    </div>

                    <div class="bg-surface-2 p-4 rounded-2xl border border-surface-border flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center shrink-0">
                                <i class="pi pi-shopping-bag text-sm font-black"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-text text-sm leading-tight">Balcão</span>
                                <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ byType.counter.count }} pedidos</span>
                            </div>
                        </div>
                        <span class="font-black text-base text-surface-text">{{ $formatCurrency(byType.counter.amount) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentShift: { type: Object, default: () => null },
    managerData: { type: Object, default: () => null }
});

const formatMoney = (value) => parseFloat(value || 0).toFixed(2).replace('.', ',');

// Lemos diretamente das métricas consolidadas pelo backend
const displayRevenue = computed(() => props.managerData?.revenue || 0);
const displayOrderCount = computed(() => props.managerData?.order_count || 0);
const displayAvgTicket = computed(() => props.managerData?.avg_ticket || 0);

const byType = computed(() => {
    const defaultData = { amount: 0, count: 0 };
    const backendData = props.managerData?.by_type || {};

    return {
        delivery: backendData.delivery || { ...defaultData },
        table: backendData.table || { ...defaultData },
        counter: backendData.counter || { ...defaultData }
    };
});

const formattedShiftStart = computed(() => {
    const shiftDate = props.currentShift?.opened_at || props.currentShift?.created_at;
    if (!shiftDate) return '--:--';

    const match = String(shiftDate).match(/(?:T|\s|^)([01]\d|2[0-3]):([0-5]\d)/);
    return match ? `${match[1]}:${match[2]}` : '--:--';
});
</script>
