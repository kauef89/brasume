<template>
    <div>
        <!-- 🚀 Soft Prompt de Notificações Push -->
        <AdaptiveModal v-model:visible="showPushPromptModal">
            <div class="flex flex-col items-center justify-center w-full p-6">
                <div class="w-16 h-16 bg-primary-500/10 rounded-full flex items-center justify-center mb-4 shadow-inner relative">
                    <div class="absolute inset-0 bg-primary-500/20 rounded-full animate-ping opacity-50"></div>
                    <i class="pi pi-bell text-3xl text-primary-500 origin-top animate-[wiggle_1s_ease-in-out_infinite]"></i>
                </div>

                <h3 class="text-2xl font-black text-surface-text text-center m-0 tracking-tight mb-3">Acompanhe o Pedido!</h3>

                <div class="text-center text-surface-muted font-medium px-2 pb-6 leading-relaxed">
                    O seu pedido já está na cozinha. Deseja receber notificações em tempo real para saber quando ele sair para entrega?
                </div>

                <div class="flex flex-col gap-3 w-full">
                    <button @click="acceptPush" class="w-full h-14 bg-primary-500 hover:bg-primary-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest flex items-center justify-center gap-2 outline-none border-none active:scale-95 transition-all cursor-pointer shadow-md">
                        <i class="pi pi-check-circle text-lg"></i> Sim, quero ser avisado
                    </button>
                    <button @click="rejectPush" class="w-full h-12 bg-transparent text-surface-400 hover:text-surface-600 rounded-2xl font-bold text-xs uppercase tracking-widest flex items-center justify-center outline-none border border-surface-border active:bg-surface-2 transition-colors cursor-pointer">
                        Agora não
                    </button>
                </div>
            </div>
        </AdaptiveModal>

        <!-- 🛑 MODAL ADAPTATIVO DE CANCELAMENTO -->
        <AdaptiveModal v-model:visible="showCancelModal">
            <div class="flex flex-col items-center justify-center w-full p-6 text-center">
                <div class="w-16 h-16 bg-danger-500/15 text-danger-500 rounded-full flex items-center justify-center mb-4 relative shadow-inner">
                    <i class="pi pi-exclamation-triangle text-3xl"></i>
                </div>

                <h3 class="text-2xl font-black text-surface-text m-0 tracking-tight mb-2">Cancelar Pedido?</h3>

                <p class="text-surface-muted text-sm font-medium px-4 pb-6 leading-relaxed m-0">
                    Tem certeza de que deseja cancelar o <strong>Pedido #{{ $formatNumber(primaryOrder?.id) }}</strong>? Esta ação é definitiva e não poderá ser desfeita.
                </p>

                <div class="flex flex-col gap-3 w-full">
                    <button
                        @click="executeCancel"
                        class="w-full h-14 bg-danger-500 hover:bg-danger-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest flex items-center justify-center gap-2 outline-none border-none active:scale-95 transition-all cursor-pointer shadow-md shadow-danger-500/20"
                    >
                        <i class="pi pi-trash text-base"></i> Sim, cancelar pedido
                    </button>

                    <button
                        @click="showCancelModal = false"
                        class="w-full h-12 bg-transparent text-surface-text hover:bg-surface-2 rounded-2xl font-bold text-xs uppercase tracking-widest flex items-center justify-center outline-none border border-surface-border active:scale-98 transition-all cursor-pointer"
                    >
                        Não, manter pedido
                    </button>
                </div>
            </div>
        </AdaptiveModal>

        <!-- Só renderiza o Widget se houver um pedido ativo -->
        <div v-if="hasActiveOrder" class="w-full backdrop-blur-xl rounded-3xl bg-surface-1/40 overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-500 mb-8 border border-surface-border/50 shadow-sm">

            <!-- CABEÇALHO COMPACTO -->
            <div @click="isExpanded = !isExpanded" class="px-5 py-4 flex items-center justify-between cursor-pointer select-none transition-colors hover:bg-surface-2/30">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shadow-sm"
                         :class="isPixWaiting ? 'bg-warning-500 text-black' : 'bg-primary-500 text-white'">
                        <i class="pi text-lg" :class="isPixWaiting ? 'pi-qrcode' : 'pi-receipt'"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Pedido #{{ $formatNumber(primaryOrder.id) }}</span>
                        <span class="text-sm font-black" :class="isPixWaiting ? 'text-warning-400' : 'text-surface-text'">
                            {{ currentPhaseText }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-surface-2/30 text-surface-muted transition-transform duration-300" :class="{ 'rotate-180': isExpanded }">
                    <i class="pi pi-chevron-down text-xs font-bold"></i>
                </div>
            </div>

            <!-- CORPO EXPANSÍVEL -->
            <div v-show="isExpanded" class="border-t border-surface-border/30">

                <!-- 🚀 BANNER DESTACADO PARA PIX PENDENTE -->
                <div v-if="isPixWaiting" class="mx-5 my-4 p-4 rounded-2xl bg-warning-500/15 border border-warning-500/30 flex flex-col gap-3 animate-pulse">
                    <div class="flex items-start gap-3">
                        <i class="pi pi-clock text-warning-400 text-xl mt-0.5"></i>
                        <div class="flex flex-col">
                            <span class="text-xs font-black uppercase tracking-wider text-warning-300">Aguardando Pagamento Pix</span>
                            <span class="text-xs text-white/80 mt-0.5 leading-relaxed">
                                A loja aguarda a confirmação do pagamento para enviar seus itens para a produção.
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="resumePix"
                        class="w-full h-11 bg-warning-500 hover:bg-warning-400 text-black font-black text-xs uppercase tracking-widest rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer border-none shadow-sm active:scale-98"
                    >
                        <i class="pi pi-qrcode"></i> Pagar com Pix / Ver QR Code
                    </button>
                </div>

                <!-- TIMELINE (APENAS SE NÃO ESTIVER RETIDO EM PIX) -->
                <div v-if="!isPixWaiting" class="px-6 pt-8 pb-6">
                    <div class="relative flex items-start justify-between w-full max-w-sm mx-auto">
                        <div class="absolute left-[10%] right-[10%] top-6 h-1 bg-surface-border rounded-full z-0"></div>

                        <div class="absolute left-[10%] top-6 h-1 bg-primary-500 backdrop-blur-xl rounded-full z-0 transition-all duration-700 ease-out"
                            :style="{ width: progressWidth }"></div>

                        <!-- Passo 1: Recebido -->
                        <div class="relative z-10 flex flex-col items-center gap-2 w-1/3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-all duration-500"
                                :class="currentTimelineStep >= 1 ? 'bg-primary-500 backdrop-blur-xl text-white' : 'bg-surface-border text-surface-muted'">
                                <i class="pi pi-file-check text-lg" :class="{ 'animate-bounce': currentTimelineStep === 1 }"></i>
                            </div>
                            <div class="flex flex-col items-center text-center transition-colors">
                                <span class="text-sm transition-colors"
                                    :class="currentTimelineStep > 1 ? 'text-surface-text' : (currentTimelineStep === 1 ? 'text-primary-300 font-medium' : 'text-surface-muted')">
                                    Recebido
                                </span>
                                <div v-if="currentTimelineStep >= 1" class="flex flex-col items-center leading-tight mt-0.5">
                                    <span class="text-xs font-bold"
                                        :class="currentTimelineStep === 1 ? 'text-primary-300' : 'text-surface-muted'">
                                        {{ step1Time }}
                                    </span>
                                    <span v-if="currentTimelineStep === 1" class="text-[10px] text-primary-300/80 mt-0.5">
                                        {{ timeSinceUpdate }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Passo 2: Preparando -->
                        <div class="relative z-10 flex flex-col items-center gap-2 w-1/3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-all duration-500"
                                :class="currentTimelineStep >= 2 ? 'bg-primary-500 backdrop-blur-xl text-white' : 'bg-surface-border text-surface-muted'">
                                <i class="pi pi-shop text-lg" :class="{ 'animate-bounce': currentTimelineStep === 2 }"></i>
                            </div>
                            <div class="flex flex-col items-center text-center transition-colors">
                                <span class="text-sm transition-colors"
                                    :class="currentTimelineStep > 2 ? 'text-surface-text' : (currentTimelineStep === 2 ? 'text-primary-300 font-medium' : 'text-surface-muted')">
                                    Preparando
                                </span>
                                <div v-if="currentTimelineStep >= 2" class="flex flex-col items-center leading-tight mt-0.5">
                                    <span class="text-xs font-bold"
                                        :class="currentTimelineStep === 2 ? 'text-primary-300' : 'text-surface-muted'">
                                        {{ step2Time }}
                                    </span>
                                    <span v-if="currentTimelineStep === 2" class="text-[10px] text-primary-300/80 mt-0.5">
                                        {{ timeSinceUpdate }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Passo 3: Em Rota / Pronto -->
                        <div class="relative z-10 flex flex-col items-center gap-2 w-1/3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-all duration-500"
                                :class="currentTimelineStep >= 3 ? 'bg-primary-500 backdrop-blur-xl text-white' : 'bg-surface-border text-surface-muted'">
                                <i :class="primaryOrder.type === 'delivery' ? 'pi pi-truck' : 'pi pi-box'" class="text-lg"></i>
                            </div>
                            <div class="flex flex-col items-center text-center transition-colors">
                                <span class="text-sm whitespace-nowrap transition-colors"
                                    :class="currentTimelineStep > 3 ? 'text-surface-text' : (currentTimelineStep === 3 ? 'text-primary-300 font-medium' : 'text-surface-muted')">
                                    {{ primaryOrder.type === 'delivery' ? 'Em Rota' : 'Pronto' }}
                                </span>
                                <div v-if="currentTimelineStep >= 3" class="flex flex-col items-center leading-tight mt-0.5">
                                    <span class="text-xs font-bold"
                                        :class="currentTimelineStep === 3 ? 'text-primary-300' : 'text-surface-muted'">
                                        {{ step3Time }}
                                    </span>
                                    <span v-if="currentTimelineStep === 3" class="text-[10px] text-primary-300/80 mt-0.5">
                                        {{ timeSinceUpdate }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RESUMO DO PEDIDO -->
                <div class="px-5 pb-2">
                    <CustomerOrderHistoryCard :order="primaryOrder" :is-widget-mode="true" />
                </div>

                <!-- PAINEL DE AÇÕES -->
                <div class="px-5 py-4 mt-1 border-t border-white/10 flex flex-wrap items-center gap-2">
                    <button v-if="tenantAllowsAppend && !isPixWaiting"
                            :disabled="!isAppendAllowed"
                            @click="appendItems(primaryOrder.id)"
                            class="flex-1 h-11 px-6 rounded-full font-bold text-md flex items-center justify-center gap-2 transition-colors outline-none border-none"
                            :class="isAppendAllowed ? 'bg-primary-500 text-white cursor-pointer active:scale-95' : 'bg-surface-border/50 text-surface-muted/50 cursor-not-allowed'">
                        <i class="pi pi-plus-circle"></i> Adicionar Itens
                    </button>

                    <button @click="handleWhatsAppSupport"
                            class="flex-none px-6 h-11 bg-success-500 rounded-full font-bold text-md flex items-center justify-center gap-2 transition-colors cursor-pointer text-white border-none outline-none">
                        <i class="pi pi-whatsapp text-sm"></i> Suporte
                    </button>

                    <button v-if="canCancelEffective" @click="showCancelModal = true"
                            class="w-full sm:w-auto px-6 h-11 bg-transparent hover:bg-danger-500/10 text-danger-500 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center justify-center transition-colors cursor-pointer outline-none border border-danger-500/20 mt-2 sm:mt-0">
                        Cancelar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import { useActiveOrderWidget } from '@/Pages/Shared/Composables/useActiveOrderWidget';
import CustomerOrderHistoryCard from '@/Pages/Customer/Orders/CustomerOrderHistoryCard.vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import { useWhatsAppSupport } from '@/Pages/Customer/Composables/useWhatsAppSupport';

const props = defineProps({
    initialOrders: {
        type: Array,
        default: () => []
    }
});

const page = usePage();
const isExpanded = ref(false);
const showPushPromptModal = ref(false);
const showCancelModal = ref(false);

const acceptPush = () => { showPushPromptModal.value = false; };
const rejectPush = () => { showPushPromptModal.value = false; };

const {
    primaryOrder, hasActiveOrder, canCancel, canAppendItems,
    currentTimelineStep, timeSinceUpdate, cancelOrder, appendItems
} = useActiveOrderWidget(() => props.initialOrders);

const { openSupport } = useWhatsAppSupport();

const isPixWaiting = computed(() => {
    return primaryOrder.value?.status === 'pending_payment'
        || primaryOrder.value?.payment_intent_status === 'pix_waiting';
});

const canCancelEffective = computed(() => {
    return isPixWaiting.value || canCancel.value;
});

const tenantAllowsAppend = computed(() => {
    const settings = page.props.companySettings || {};
    return settings.customer_can_append_orders !== false;
});

const isAppendAllowed = computed(() => {
    return tenantAllowsAppend.value && canAppendItems.value;
});

const resumePix = () => {
    window.dispatchEvent(new CustomEvent('customer-resume-pix', { detail: { order: primaryOrder.value } }));
};

const executeCancel = () => {
    showCancelModal.value = false;
    if (primaryOrder.value?.id) {
        cancelOrder(primaryOrder.value.id);
    }
};

const currentPhaseText = computed(() => {
    if (isPixWaiting.value) return 'Aguardando Pagamento Pix';
    if (currentTimelineStep.value === 1) return 'Recebido pela loja';
    if (currentTimelineStep.value === 2) return 'Sendo preparado';
    if (currentTimelineStep.value === 3) return primaryOrder.value?.type === 'delivery' ? 'Saiu para entrega' : 'Pronto para retirada';
    return 'Aguardando processamento';
});

const formatTime = (dateVal) => {
    if (!dateVal) return '';
    try {
        const safeStr = typeof dateVal === 'string' ? dateVal.replace(' ', 'T') : dateVal;
        const d = new Date(safeStr);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return '';
    }
};

const step1Time = computed(() => formatTime(primaryOrder.value?.created_at));
const step2Time = computed(() => {
    if (!primaryOrder.value || currentTimelineStep.value < 2) return '';
    return formatTime(primaryOrder.value.started_preparing_at || primaryOrder.value.updated_at);
});
const step3Time = computed(() => {
    if (!primaryOrder.value || currentTimelineStep.value < 3) return '';
    return formatTime(
        primaryOrder.value.dispatched_at ||
        primaryOrder.value.ready_at ||
        primaryOrder.value.updated_at
    );
});

const progressWidth = computed(() => {
    if (currentTimelineStep.value <= 1) return '0%';
    if (currentTimelineStep.value === 2) return '50%';
    if (currentTimelineStep.value >= 3) return '80%';
    return '0%';
});

const handleWhatsAppSupport = () => {
    openSupport('order_issue', { orderId: primaryOrder.value.id });
};
</script>
