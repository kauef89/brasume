<template>
    <AdaptiveModal v-model:visible="isVisible" :content-class="'!p-0 !bg-transparent'">
        <div class="w-full flex flex-col overflow-hidden p-6 max-h-[85vh] bg-surface-1 rounded-3xl shadow-2xl">

            <!-- CABEÇALHO DO EXTRATO -->
            <div class="flex items-center justify-between pb-4 border-b border-surface-border/50 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-primary-500/10 text-primary-500 flex items-center justify-center">
                        <i class="pi pi-star-fill text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-surface-text leading-tight">Extrato de Fidelidade</h2>
                        <span class="text-[11px] font-bold text-surface-muted">Histórico de pontos e resgates</span>
                    </div>
                </div>

                <button
                    @click="isVisible = false"
                    class="w-8 h-8 rounded-full bg-surface-2 text-surface-muted hover:text-surface-text flex items-center justify-center cursor-pointer border-none outline-none transition-colors"
                >
                    <i class="pi pi-times text-xs"></i>
                </button>
            </div>

            <!-- CARD DE SALDO E VALIDADE -->
            <div class="my-4 p-4 rounded-2xl bg-surface-2 border border-surface-border/60 flex flex-col gap-3 shrink-0">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-surface-muted">Saldo Atual</span>
                    <span class="text-xs font-bold text-primary-smart">{{ customerPoints }} pontos</span>
                </div>

                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-surface-text">{{ $formatCurrency(availableBalance) }}</span>
                    <span class="text-[11px] font-medium text-surface-muted">
                        100 pts = {{ $formatCurrency(conversionRate) }}
                    </span>
                </div>

                <!-- ALERTA DE EXPIRAÇÃO -->
                <div
                    v-if="expirationWarning"
                    class="pt-2 border-t border-surface-border/40 flex items-center gap-2 text-[11px] font-bold"
                    :class="expirationWarning.isUrgent ? 'text-danger-500' : 'text-warning-600'"
                >
                    <i class="pi pi-clock text-xs shrink-0"></i>
                    <span>{{ expirationWarning.text }}</span>
                </div>
            </div>

            <!-- LISTAGEM DE MOVIMENTAÇÕES -->
            <div class="flex-1 overflow-y-auto hide-scrollbar flex flex-col gap-2.5 min-h-[220px]">
                <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted px-1">
                    Movimentações Recentes
                </span>

                <div v-if="statementEntries.length === 0" class="flex-1 flex flex-col items-center justify-center py-8 text-surface-muted opacity-60">
                    <i class="pi pi-receipt text-4xl mb-2"></i>
                    <p class="text-xs font-bold">Nenhum resgate ou acúmulo registrado ainda.</p>
                </div>

                <div
                    v-for="(item, idx) in statementEntries"
                    :key="idx"
                    class="p-3.5 rounded-xl bg-surface-2/60 border border-surface-border/40 flex items-center justify-between transition-colors hover:bg-surface-2"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-sm"
                            :class="item.type === 'redeem' ? 'bg-purple-500/10 text-purple-500' : 'bg-success-500/10 text-success-500'"
                        >
                            <i :class="item.type === 'redeem' ? 'pi pi-arrow-down-left' : 'pi pi-arrow-up-right'"></i>
                        </div>

                        <div class="flex flex-col min-w-0">
                            <span class="text-xs font-black text-surface-text truncate">
                                {{ item.title }}
                            </span>
                            <span class="text-[10px] font-medium text-surface-muted">
                                {{ item.date }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col items-end shrink-0 pl-2">
                        <span
                            class="text-xs font-black leading-tight"
                            :class="item.type === 'redeem' ? 'text-purple-500' : 'text-success-500'"
                        >
                            {{ item.type === 'redeem' ? '-' : '+' }} {{ item.formattedValue }}
                        </span>
                        <span class="text-[10px] font-bold text-surface-muted">
                            {{ item.type === 'redeem' ? `-${item.points} pts` : `+${item.points} pts` }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    visible: Boolean,
    customer: { type: Object, default: null },
    orders: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:visible']);
const page = usePage();

const isVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val)
});

const settings = computed(() => page.props.companySettings || {});
const conversionRate = computed(() => Number(settings.value.loyalty_cashback_conversion_rate || 5));
const customerPoints = computed(() => Number(props.customer?.loyalty_points || 0));
const availableBalance = computed(() => (customerPoints.value / 100) * conversionRate.value);

// 🚀 CÁLCULO VISUAL DO TEMPO DE VALIDADE RESTANTE
const expirationWarning = computed(() => {
    if (!props.customer?.loyalty_points_expires_at || customerPoints.value <= 0) return null;

    const expiryDate = new Date(props.customer.loyalty_points_expires_at);
    const now = new Date();
    const diffMs = expiryDate - now;
    const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays <= 0) {
        return { text: 'Pontos expirando hoje!', isUrgent: true };
    }

    const formattedDate = expiryDate.toLocaleDateString('pt-BR');
    if (diffDays <= 7) {
        return { text: `Atenção: Saldo expira em ${diffDays} dia(s) (${formattedDate})`, isUrgent: true };
    }

    return { text: `Válido até ${formattedDate} (${diffDays} dias restantes)`, isUrgent: false };
});

// 🚀 EXTRAÇÃO DA LINHA DO TEMPO DIRETO DOS PEDIDOS
const statementEntries = computed(() => {
    const list = [];

    (props.orders || []).forEach((order) => {
        if (order.status === 'cancelled') return;

        const dateStr = order.created_at
            ? new Date(order.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
            : 'Data não registrada';

        // 1. Resgate de Cashback
        const cashbackUsed = Number(order.cashback_used || 0);
        if (cashbackUsed > 0) {
            const pointsDeducted = Math.ceil((cashbackUsed / conversionRate.value) * 100);
            list.push({
                type: 'redeem',
                title: `Resgate no Pedido #${order.id}`,
                date: dateStr,
                rawDate: new Date(order.created_at || 0),
                formattedValue: formatCurrency(cashbackUsed),
                points: pointsDeducted
            });
        }

        // 2. Pontos Ganhos
        const earned = Number(order.loyalty_points_earned || 0);
        if (earned > 0) {
            const earnedValue = (earned / 100) * conversionRate.value;
            list.push({
                type: 'earn',
                title: `Pontos do Pedido #${order.id}`,
                date: dateStr,
                rawDate: new Date(order.created_at || 0),
                formattedValue: formatCurrency(earnedValue),
                points: earned
            });
        }
    });

    return list.sort((a, b) => b.rawDate - a.rawDate);
});
</script>
