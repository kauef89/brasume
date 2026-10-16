<template>
    <div class="flex-1 flex flex-col h-full w-full overflow-hidden relative select-none bg-surface-1">

        <!-- ========================================================
             1. CABEÇALHO DA CONTA DA MESA
             ======================================================== -->
        <div class="px-5 pt-4 pb-3 flex items-center justify-between border-b border-surface-border/40 shrink-0 bg-surface-1/90 backdrop-blur-md z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-primary-500/15 text-primary-smart flex items-center justify-center font-black text-lg border border-primary-500/20">
                    <i class="pi pi-table"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs font-black text-surface-muted uppercase tracking-widest">Conta em Aberto</span>
                    <h2 class="text-lg font-black text-surface-text m-0 leading-tight">
                        {{ tableMode?.name || 'Mesa do Salão' }}
                    </h2>
                </div>
            </div>

            <!-- Botão Atualizar Conta -->
            <button
                @click="reloadTableOrders"
                :disabled="isReloading"
                class="w-9 h-9 rounded-full bg-surface-2 hover:bg-surface-3 flex items-center justify-center text-surface-muted hover:text-white transition-all border border-surface-border/50 outline-none cursor-pointer"
                title="Recarregar conta"
            >
                <i class="pi pi-refresh text-xs" :class="{ 'animate-spin': isReloading }"></i>
            </button>
        </div>

        <!-- ========================================================
             2. ÁREA ROLÁVEL COM A LISTAGEM DE ITENS
             ======================================================== -->
        <div class="flex-1 overflow-y-auto hide-scrollbar px-4 py-4 flex flex-col gap-3 relative pb-28">

            <div v-if="allItems.length === 0" class="h-64 flex flex-col items-center justify-center text-center px-4">
                <div class="w-16 h-16 rounded-full bg-surface-2 flex items-center justify-center text-surface-muted mb-3">
                    <i class="pi pi-inbox text-2xl"></i>
                </div>
                <p class="text-sm font-bold text-surface-text m-0">Nenhum item lançado ainda</p>
                <p class="text-xs text-surface-muted mt-1">Aceda ao cardápio para fazer o primeiro pedido da mesa.</p>
                <button
                    @click="$emit('go-to-menu')"
                    class="mt-4 px-6 h-10 rounded-full bg-primary-500 text-white font-black text-xs uppercase tracking-wider border-none outline-none cursor-pointer shadow-md"
                >
                    Ver Cardápio
                </button>
            </div>

            <template v-else>
                <div class="flex items-center justify-between px-1">
                    <span class="text-[11px] font-black uppercase tracking-wider text-surface-muted">
                        Itens Consumidos na Mesa ({{ activeItemsCount }})
                    </span>
                    <span class="text-[10px] text-surface-muted font-bold">
                        {{ myActiveOrders.length }} comanda(s)
                    </span>
                </div>

                <!-- CARDS DE ITENS -->
                <div class="flex flex-col gap-2">
                    <div
                        v-for="(item, idx) in allItems"
                        :key="item.id || idx"
                        class="p-3.5 rounded-2xl border transition-all"
                        :class="item.isCancelled
                            ? 'bg-surface-2/30 border-dashed border-danger-500/30 opacity-70'
                            : 'bg-surface-2/80 border-surface-border/50 shadow-sm'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <!-- Informações do Item -->
                            <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                <span
                                    class="w-6 h-6 rounded-lg flex items-center justify-center font-black text-xs shrink-0"
                                    :class="item.isCancelled ? 'bg-danger-500/20 text-danger-400' : 'bg-primary-500/20 text-primary-smart'"
                                >
                                    {{ Math.round(item.quantity) }}x
                                </span>

                                <div class="flex flex-col min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span
                                            class="font-black text-sm text-surface-text leading-tight"
                                            :class="{ 'line-through text-surface-muted': item.isCancelled }"
                                        >
                                            {{ item.product_name }}
                                        </span>

                                        <!-- Badge de Cancelado -->
                                        <span v-if="item.isCancelled" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-danger-500/20 text-danger-400 border border-danger-500/30">
                                            Cancelado
                                        </span>
                                    </div>

                                    <!-- Modificadores / Observações -->
                                    <p v-if="item.modifiersText" class="text-[11px] text-surface-muted mt-0.5 m-0 leading-tight">
                                        {{ item.modifiersText }}
                                    </p>

                                    <!-- Identificador de quem pediu -->
                                    <span v-if="item.orderedBy" class="text-[10px] font-bold text-primary-400/90 mt-1 flex items-center gap-1">
                                        <i class="pi pi-user text-[9px]"></i> {{ item.orderedBy }}
                                    </span>
                                </div>
                            </div>

                            <!-- Preço do Item -->
                            <div class="flex flex-col items-end shrink-0">
                                <template v-if="item.isCancelled">
                                    <span class="text-xs line-through text-surface-muted">
                                        {{ $formatCurrency(item.originalTotal) }}
                                    </span>
                                    <span class="font-black text-xs text-danger-400">
                                        R$ 0,00
                                    </span>
                                </template>

                                <span v-else class="font-black text-sm text-surface-text">
                                    {{ $formatCurrency(item.total_price) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ========================================================
             3. BOTÃO FLUTUANTE (FAB): CHAMAR ATENDIMENTO
             ======================================================== -->
        <button
            @click="triggerCallWaiter"
            :disabled="isCallingWaiter || waiterCooldown > 0"
            class="fixed bottom-36 right-5 z-40 h-12 px-4 rounded-full font-black text-xs uppercase tracking-wider flex items-center gap-2 shadow-2xl transition-all border border-amber-500/40 outline-none cursor-pointer active:scale-95"
            :class="waiterCooldown > 0
                ? 'bg-surface-3 text-surface-muted border-surface-border cursor-not-allowed opacity-80'
                : 'bg-amber-500 hover:bg-amber-600 text-slate-950 shadow-amber-500/25'"
        >
            <i class="pi pi-bell text-sm" :class="{ 'animate-bounce': waiterCooldown === 0 }"></i>
            <span>{{ waiterCooldown > 0 ? `Aguarde (${waiterCooldown}s)` : 'Chamar Garçom' }}</span>
        </button>

        <!-- ========================================================
             4. RODAPÉ FIXO (FORA DA ÁREA DE ROLAGEM)
             ======================================================== -->
        <div class="shrink-0 bg-surface-2 border-t border-surface-border/60 p-4 flex flex-col gap-3 shadow-2xl z-30">

            <!-- Detalhamento de Valores da Mesa -->
            <div class="flex flex-col gap-1.5 px-1">
                <div class="flex justify-between items-center text-xs text-surface-muted">
                    <span>Consumo Ativo</span>
                    <span class="font-bold text-surface-text">{{ $formatCurrency(tableFinancials.subtotal) }}</span>
                </div>

                <div v-if="tableFinancials.serviceFee > 0" class="flex justify-between items-center text-xs text-surface-muted">
                    <span>Taxa de Serviço</span>
                    <span class="font-bold text-surface-text">{{ $formatCurrency(tableFinancials.serviceFee) }}</span>
                </div>

                <div v-if="tableFinancials.discount > 0" class="flex justify-between items-center text-xs font-bold text-emerald-400">
                    <span>Descontos</span>
                    <span>- {{ $formatCurrency(tableFinancials.discount) }}</span>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-surface-border/40">
                    <span class="text-sm font-black text-surface-text uppercase tracking-wider">Total a Pagar</span>
                    <span class="text-xl font-black text-primary-smart">{{ $formatCurrency(tableFinancials.total) }}</span>
                </div>
            </div>

            <!-- BLOCO DE PAGAMENTO VIA PIX (engine = bank_api) -->
            <div v-if="pixMethod && tableFinancials.total > 0" class="flex flex-col gap-2">
                <button
                    v-if="!showPixArea"
                    @click="initiatePixPayment"
                    :disabled="isGeneratingPix"
                    class="w-full h-12 rounded-2xl bg-teal-500 hover:bg-teal-600 text-white font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 border-none outline-none cursor-pointer shadow-md shadow-teal-500/20 active:scale-[0.99] transition-all"
                >
                    <i class="pi pi-qrcode text-base" :class="{ 'animate-spin': isGeneratingPix }"></i>
                    <span>Pagar via PIX no Telemóvel</span>
                </button>

                <!-- ÁREA PIX EMBUTIDA (Quando gerado) -->
                <div v-else class="p-3 bg-surface-1 rounded-2xl border border-teal-500/30 flex flex-col gap-3 animate-in fade-in">
                    <div class="flex items-center justify-between pb-2 border-b border-surface-border/40">
                        <span class="text-xs font-black uppercase text-teal-400 flex items-center gap-1.5">
                            <i class="pi pi-qrcode"></i> QR Code PIX Gerado
                        </span>
                        <button @click="showPixArea = false" class="text-xs text-surface-muted hover:text-white bg-transparent border-none cursor-pointer">
                            Fechar
                        </button>
                    </div>

                    <CheckoutStepPix
                        :order-id="activePixOrderId"
                        :pix-data="activePixData"
                        @pix-success="handlePixSuccess"
                        @cancel="showPixArea = false"
                    />
                </div>
            </div>

            <!-- AVISO DE OUTROS MÉTODOS DE PAGAMENTO -->
            <div class="p-2.5 rounded-xl bg-surface-1/70 border border-surface-border/40 flex items-center gap-2.5 text-surface-muted">
                <i class="pi pi-info-circle text-sm text-surface-400 shrink-0"></i>
                <p class="text-[11px] font-medium leading-tight m-0">
                    Para pagamentos em <strong>Cartão</strong> ou <strong>Dinheiro</strong>, dirija-se ao caixa ao finalizar.
                </p>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

import { formatCurrency } from '@/Utils/formatters';
import CheckoutStepPix from '@/Pages/Customer/Checkout/CheckoutStepPix.vue';

const props = defineProps({
    myActiveOrders: { type: Array, default: () => [] },
    tableMode: { type: Object, default: () => null },
    paymentMethods: { type: Array, default: () => [] }
});

const emit = defineEmits(['go-to-menu', 'order-paid']);

const page = usePage();
const toast = useToast();

const isReloading = ref(false);
const isCallingWaiter = ref(false);
const waiterCooldown = ref(0);
let cooldownTimer = null;

// Gestão de PIX
const showPixArea = ref(false);
const isGeneratingPix = ref(false);
const activePixData = ref(null);
const activePixOrderId = ref(null);

const pixMethod = computed(() => {
    return (props.paymentMethods || []).find(m => m.engine_type === 'bank_api' || String(m.name).toLowerCase().includes('pix'));
});

// 🚀 Extração e normalização de todos os itens com suporte a cancelados
const allItems = computed(() => {
    const list = [];

    (props.myActiveOrders || []).forEach(order => {
        const orderCustomer = order.customer?.name || order.customer_name || 'Mesa';

        (order.items || []).forEach(item => {
            const isCancelled = item.status === 'cancelled' || order.status === 'cancelled';
            const originalTotal = Number(item.total_price || item.price || 0);

            list.push({
                ...item,
                orderId: order.id,
                orderedBy: orderCustomer.split(' ')[0],
                isCancelled,
                originalTotal,
                total_price: isCancelled ? 0.00 : originalTotal,
                modifiersText: (item.modifiers || []).map(m => m.name).join(', ') || item.modifiers_text || ''
            });
        });
    });

    return list;
});

const activeItemsCount = computed(() => allItems.value.filter(i => !i.isCancelled).length);

// 🚀 Totais consolidados da mesa
const tableFinancials = computed(() => {
    let subtotal = 0;
    let serviceFee = 0;
    let discount = 0;
    let total = 0;

    (props.myActiveOrders || []).forEach(order => {
        if (order.status !== 'cancelled') {
            subtotal += Number(order.subtotal || 0);
            serviceFee += Number(order.service_fee || 0);
            discount += Number(order.discount || 0);
            total += Number(order.total || 0);
        }
    });

    return { subtotal, serviceFee, discount, total };
});

const reloadTableOrders = () => {
    isReloading.value = true;
    router.reload({
        only: ['myActiveOrders'],
        preserveScroll: true,
        onFinish: () => { isReloading.value = false; }
    });
};

// 🚀 Disparo de Chamar Garçom
const triggerCallWaiter = async () => {
    if (waiterCooldown.value > 0 || isCallingWaiter.value) return;

    isCallingWaiter.value = true;

    try {
        const response = await axios.post(route('customer.table.call-waiter'));
        toast.add({
            severity: 'success',
            summary: 'Garçom Notificado!',
            detail: response.data?.message || 'Um atendente está a caminho da sua mesa.',
            life: 5000
        });

        // Inicia Cooldown de 60 segundos
        waiterCooldown.value = 60;
        cooldownTimer = setInterval(() => {
            if (waiterCooldown.value > 0) {
                waiterCooldown.value--;
            } else {
                clearInterval(cooldownTimer);
            }
        }, 1000);
    } catch (e) {
        toast.add({
            severity: 'error',
            summary: 'Erro',
            detail: e.response?.data?.message || 'Não foi possível chamar o atendente. Tente novamente.',
            life: 4000
        });
    } finally {
        isCallingWaiter.value = false;
    }
};

// 🚀 Inicializa o Pagamento PIX da Mesa
const initiatePixPayment = () => {
    const firstUnpaidOrder = (props.myActiveOrders || []).find(o => o.payment_status !== 'paid');

    if (!firstUnpaidOrder) {
        toast.add({ severity: 'info', summary: 'Conta Paga', detail: 'Esta comanda já consta como liquidada.', life: 3000 });
        return;
    }

    if (firstUnpaidOrder.pix_data) {
        activePixData.value = firstUnpaidOrder.pix_data;
        activePixOrderId.value = firstUnpaidOrder.id;
        showPixArea.value = true;
    } else {
        toast.add({
            severity: 'warn',
            summary: 'PIX no Caixa',
            detail: 'Para liquidar esta comanda via PIX, o operador do caixa pode gerar o código no balcão.',
            life: 5000
        });
    }
};

const handlePixSuccess = () => {
    showPixArea.value = false;
    toast.add({ severity: 'success', summary: 'Pagamento Confirmado!', detail: 'A sua comanda foi liquidada.', life: 4000 });
    reloadTableOrders();
    emit('order-paid');
};

onUnmounted(() => {
    if (cooldownTimer) clearInterval(cooldownTimer);
});
</script>
