<template>
    <div v-if="orderPayments.length > 0 || paidItems.length > 0" class="mt-2 animate-fade-in flex flex-col gap-3">
        <!-- CABEÇALHO DO BLOCO -->
        <div class="flex items-center justify-between border-b border-surface-border/50 pb-2">
            <h3 class="text-[11px] font-black text-surface-muted uppercase tracking-widest flex items-center gap-1.5">
                <i class="pi pi-check-circle text-success-500 text-xs"></i>
                Pagamentos Realizados ({{ orderPayments.length }})
            </h3>
            <span class="text-xs font-black text-success-500">
                Total Pago: {{ $formatCurrency(totalPaidAmount) }}
            </span>
        </div>

        <div class="flex flex-col gap-3">
            <!-- BLOCO DE CADA PAGAMENTO -->
            <div
                v-for="(pay, pIdx) in paymentBlocks"
                :key="pay.id || pIdx"
                class="bg-surface-1 border border-surface-border rounded-2xl p-3 flex flex-col gap-2.5 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-success-500/10 text-success-500 flex items-center justify-center font-bold text-sm shrink-0">
                            <i :class="getPaymentIcon(pay.method)"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-surface-text">{{ pay.method || 'Pagamento' }}</span>
                            <span class="text-[11px] text-surface-muted font-bold flex items-center gap-1 mt-0.5">
                                <i class="pi pi-clock text-[10px]"></i>
                                {{ formatPaymentTime(pay.created_at) || `Lançamento #${pIdx + 1}` }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-right">
                        <div class="flex flex-col items-end">
                            <span class="text-sm font-black text-success-500">{{ $formatCurrency(pay.amount) }}</span>
                            <span class="text-[9px] text-surface-muted font-bold uppercase tracking-wider">Recebido</span>
                        </div>

                        <button
                            v-if="mode === 'checkout' && pay.id"
                            type="button"
                            @click.stop="$emit('rollback-payment', pay)"
                            class="w-8 h-8 rounded-lg bg-red-500/10 text-red-600 hover:bg-red-500 hover:text-white transition-colors flex items-center justify-center outline-none shrink-0 cursor-pointer"
                            title="Estornar Pagamento"
                        >
                            <i class="pi pi-undo text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- ITENS COBERTOS -->
                <div v-if="pay.items && pay.items.length > 0" class="border-t border-surface-border/40 pt-2 flex flex-col gap-1.5">
                    <span class="text-[9px] font-black uppercase tracking-wider text-surface-muted">Itens cobertos:</span>
                    <div v-for="(item, iIdx) in pay.items" :key="iIdx" class="flex flex-col gap-1 py-1.5 px-2.5 rounded-lg bg-surface-2/60 text-surface-text">
                        <div class="flex justify-between items-center text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="font-bold text-surface-muted text-[11px]">{{ formatFraction(item.paid_parts || item.quantity, item.parts_per_unit || 1) }}×</span>
                                <span class="truncate font-medium text-xs">{{ item.name || item.product_name }}</span>
                            </div>
                            <span class="font-bold text-surface-text text-xs shrink-0 ml-2">
                                {{ $formatCurrency(item.total_amount) }}
                            </span>
                        </div>

                        <div v-if="getItemModsLines(item).length > 0 || item.notes" class="flex flex-col pl-4 text-[10px] text-surface-muted gap-0.5">
                            <span v-for="(modLine, mIdx) in getItemModsLines(item)" :key="mIdx" class="leading-tight">
                                ↳ {{ modLine }}
                            </span>
                            <span v-if="item.notes && getItemModsLines(item).length === 0" class="leading-tight">
                                ↳ {{ item.notes }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BAIXAS SEM COMPROVANTE ISOLADO -->
            <div v-if="unassignedPaidItems.length > 0" class="bg-surface-1/70 border border-surface-border/70 rounded-2xl p-3 flex flex-col gap-2">
                <div class="flex items-center justify-between border-b border-surface-border/40 pb-1.5">
                    <span class="text-[10px] font-black uppercase tracking-wider text-surface-muted flex items-center gap-1">
                        <i class="pi pi-receipt text-xs"></i> Baixas sem comprovante isolado
                    </span>
                </div>
                <div class="flex flex-col gap-1">
                    <div v-for="item in unassignedPaidItems" :key="(item.unique_id || item.id) + '_unassigned'" class="flex justify-between items-center text-xs py-1 px-2 rounded-lg bg-surface-2/50">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="font-black text-surface-muted text-[11px]">{{ formatFraction(item.paid_parts, item.parts_per_unit) }}×</span>
                            <span class="truncate font-medium text-xs text-surface-text">{{ item.name || item.product_name }}</span>
                        </div>
                        <span class="font-black text-surface-muted text-xs shrink-0 ml-2">
                            {{ $formatCurrency(item.paid_parts * (item.effective_unit_price / item.parts_per_unit)) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { formatFraction } from "@/Utils/formatters";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

const props = defineProps({
    orderPayments: { type: Array, default: () => [] },
    paidItems: { type: Array, default: () => [] },
    mode: { type: String, default: 'checkout' }
});

defineEmits(['rollback-payment']);

const { formatModifiersList } = useOrderFormatting();
const getItemModsLines = (item) => formatModifiersList(item);

const totalPaidAmount = computed(() => {
    if (props.orderPayments && props.orderPayments.length > 0) {
        return props.orderPayments.filter(p => p.is_paid || p.status === 'paid').reduce((sum, p) => sum + Number(p.amount || 0), 0);
    }
    return props.paidItems.reduce((sum, i) => sum + (i.paid_parts * (i.effective_unit_price / i.parts_per_unit)), 0);
});

const paymentBlocks = computed(() => {
    const payments = props.orderPayments || [];
    if (payments.length === 0) return [];

    return payments.map(pay => {
        if (Array.isArray(pay.items) && pay.items.length > 0) {
            return {
                ...pay,
                items: pay.items.map(it => {
                    const qty = Number(it.pivot?.quantity ?? it.quantity ?? 1);
                    let amount = Number(it.pivot?.amount ?? it.total_amount ?? 0);

                    if (amount <= 0 && Number(pay.amount) > 0) {
                        amount = pay.items.length === 1
                            ? Number(pay.amount)
                            : (qty * (Number(it.unit_price) || (Number(pay.amount) / pay.items.length)));
                    }

                    let ppu = Number(it.parts_per_unit || 1);
                    let paid_parts = qty;

                    if (qty > 0 && qty < 1) {
                        ppu = Math.round(1 / qty);
                        paid_parts = Math.round(qty * ppu);
                    } else if (qty % 1 !== 0) {
                        ppu = 2;
                        paid_parts = Math.round(qty * 2);
                    }

                    return {
                        ...it,
                        name: it.name || it.product_name,
                        paid_parts: paid_parts,
                        parts_per_unit: ppu,
                        effective_unit_price: qty > 0 ? (amount / qty) : amount,
                        total_amount: amount,
                        modifiers: it.modifiers || [],
                        notes: it.notes || null
                    };
                })
            };
        }

        if (payments.length === 1 && props.paidItems.length > 0) {
            return {
                ...pay,
                items: props.paidItems.map(it => ({
                    ...it,
                    total_amount: it.paid_parts * (it.effective_unit_price / it.parts_per_unit)
                }))
            };
        }

        return { ...pay, items: [] };
    });
});

const unassignedPaidItems = computed(() => {
    const hasAttachedItems = (props.orderPayments || []).some(p => Array.isArray(p.items) && p.items.length > 0);
    if (hasAttachedItems || (props.orderPayments || []).length <= 1) return [];
    return props.paidItems;
});

const getPaymentIcon = (methodName = '') => {
    const m = String(methodName).toLowerCase();
    if (m.includes('pix')) return 'pi pi-bolt';
    if (m.includes('cart') || m.includes('créd') || m.includes('déb')) return 'pi pi-credit-card';
    if (m.includes('dinheiro') || m.includes('money') || m.includes('espécie')) return 'pi pi-money-bill';
    return 'pi pi-wallet';
};

const formatPaymentTime = (dateValue) => {
    if (!dateValue) return '';
    try {
        const str = String(dateValue);
        if (str.includes(' ') && str.split(' ')[1]) return str.split(' ')[1].substring(0, 5);
        if (str.includes('T')) {
            const date = new Date(str);
            if (!isNaN(date.getTime())) return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        const date = new Date(dateValue);
        if (!isNaN(date.getTime())) return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        return str.substring(11, 16);
    } catch (e) { return ''; }
};
</script>
