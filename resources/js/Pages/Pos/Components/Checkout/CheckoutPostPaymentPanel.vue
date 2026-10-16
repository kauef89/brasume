<template>
    <div class="flex-1 flex flex-col h-full bg-surface-1/30 p-6 md:p-8 relative select-none">

        <!-- ESTADO: EMITINDO NFC-e -->
        <div v-if="isEmitting" class="flex flex-col items-center justify-center h-full text-center animate-pulse gap-2">
            <div class="w-24 h-24 rounded-full flex items-center justify-center mb-6 bg-primary-500/10 text-primary-500 shadow-inner">
                <i class="pi pi-spinner pi-spin text-5xl font-black"></i>
            </div>
            <h2 class="text-3xl font-black text-surface-text leading-tight tracking-tight">A Emitir NFC-e...</h2>
            <p class="text-surface-muted text-sm font-medium mt-2">Comunicando com a Receita Federal, por favor aguarde.</p>
        </div>

        <template v-else>
            <!-- CABEÇALHO DE STATUS DO FECHAMENTO -->
            <div class="flex flex-col items-center justify-center text-center mt-4 mb-3 animate-in fade-in zoom-in-95 duration-500">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4 shadow-inner ring-4"
                     :class="isCancelled ? 'bg-danger-500/10 text-danger-500 ring-danger-500/20' : 'bg-success-500/10 text-success-500 ring-success-500/20'">
                    <i class="pi text-4xl font-black" :class="isCancelled ? 'pi-times' : 'pi-check'"></i>
                </div>
                <h2 class="text-2xl font-black text-surface-text leading-tight tracking-tight">
                    {{ isCancelled ? 'Pedido Cancelado' : 'Conta Fechada!' }}
                </h2>
                <p class="text-surface-muted text-xs font-medium mt-1">
                    {{ isCancelled ? 'Este pedido foi estornado e anulado.' : 'O pedido foi liquidado na totalidade.' }}
                </p>
            </div>

            <!-- STATUS FISCAL (NFC-e) -->
            <div v-if="!isCancelled && isFiscalEmitter" class="flex justify-center mb-3 animate-in fade-in slide-in-from-bottom-2">
                <!-- CASO 1: FATURAMENTO R$ 0,00 (DISPENSADO DE NFC-e) -->
                <span v-if="isZeroRevenue" class="bg-surface-2 text-surface-muted border border-surface-border px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                    <i class="pi pi-info-circle text-xs text-amber-500"></i> NFC-e Dispensada (Faturamento R$ 0,00)
                </span>

                <!-- CASO 2: AUTORIZADA NA SEFAZ -->
                <span v-else-if="hasFiscalDocument" class="bg-success-500/10 text-success-600 border border-success-500/20 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                    <i class="pi pi-check-circle text-xs"></i> NFC-e Emitida com Sucesso
                </span>

                <!-- CASO 3: FALHA NA SEFAZ -->
                <span v-else class="bg-danger-500/10 text-danger-500 border border-danger-500/20 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                    <i class="pi pi-exclamation-triangle text-xs"></i> Falha na Emissão da NFC-e
                </span>
            </div>

            <div v-if="flashError" class="mb-3 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-600 dark:text-red-400 text-xs font-medium flex flex-col gap-1 animate-pulse shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-widest text-red-500 flex items-center gap-1">
                    <i class="pi pi-exclamation-triangle"></i> Rejeição da Sefaz
                </span>
                {{ flashError }}
            </div>

            <div class="mt-auto flex flex-col gap-2.5 animate-in fade-in slide-in-from-bottom-4 duration-500">

                <!-- 🖨️ MARKETING OFFLINE -->
                <div v-if="!isCancelled && hasAnyFlyerAction" class="flex flex-col gap-1.5 p-3 rounded-2xl bg-surface-2/60 border border-surface-border/60">
                    <span class="text-[9px] font-black uppercase tracking-widest text-surface-muted flex items-center gap-1">
                        <i class="pi pi-megaphone text-[10px] text-amber-500"></i> Marketing Impresso
                    </span>

                    <div class="grid gap-2" :class="marketingGridCols">
                        <button
                            v-if="canPrintTableClaimFlyer"
                            @click="$emit('action', { type: 'table_claim' })"
                            class="h-10 px-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-400 font-black text-[10px] uppercase tracking-wider flex items-center justify-center gap-1.5 transition-all cursor-pointer outline-none active:scale-95"
                            title="Imprimir QR Code para resgate de cashback no App"
                        >
                            <i class="pi pi-qrcode text-xs text-amber-400"></i> Flyer Resgate Mesa
                        </button>

                        <button
                            v-if="canPrintLoyaltyFlyer"
                            @click="$emit('action', { type: 'loyalty' })"
                            class="h-10 px-2 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-400 font-black text-[10px] uppercase tracking-wider flex items-center justify-center gap-1.5 transition-all cursor-pointer outline-none active:scale-95"
                            title="Imprimir comprovante de cashback"
                        >
                            <i class="pi pi-star-fill text-xs text-purple-400"></i> Flyer Cashback
                        </button>

                        <button
                            v-if="canPrintGrowthFlyer"
                            @click="$emit('action', { type: 'growth' })"
                            class="h-10 px-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 font-black text-[10px] uppercase tracking-wider flex items-center justify-center gap-1.5 transition-all cursor-pointer outline-none active:scale-95"
                            title="Imprimir cupom de presente"
                        >
                            <i class="pi pi-gift text-xs text-emerald-400"></i> Flyer Presente
                        </button>
                    </div>
                </div>

                <!-- RECIBOS PADRÃO -->
                <template v-if="!isCancelled">
                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            @click="$emit('action', { type: 'non_fiscal' })"
                            class="h-12 rounded-xl bg-surface-2 border border-surface-border text-surface-muted font-bold text-xs uppercase tracking-wider hover:text-surface-text hover:border-surface-muted transition-colors outline-none flex items-center justify-center gap-2 cursor-pointer shadow-sm"
                        >
                            <i class="pi pi-receipt text-base"></i> Recibo Interno
                        </button>

                        <button
                            v-if="isFiscalEmitter && !hasFiscalDocument"
                            @click="isZeroRevenue ? null : $emit('action', { type: 'emit_nfce' })"
                            :disabled="isZeroRevenue"
                            class="h-12 rounded-xl border font-black text-xs uppercase tracking-wider transition-colors outline-none flex items-center justify-center gap-2 shadow-sm"
                            :class="isZeroRevenue
                                ? 'bg-surface-2 border-surface-border/50 text-surface-muted/40 cursor-not-allowed opacity-50'
                                : 'bg-warning-500/10 border-warning-500/20 text-warning-500 hover:bg-warning-500/20 cursor-pointer'"
                            :title="isZeroRevenue ? 'Emissão fiscal não permitida para pedidos com faturamento zerado' : 'Tentar NFC-e'"
                        >
                            <i class="pi pi-refresh text-base"></i> Tentar NFC-e
                        </button>

                        <button
                            v-if="isFiscalEmitter && hasFiscalDocument"
                            @click="isZeroRevenue ? null : $emit('action', { type: 'fiscal' })"
                            :disabled="isZeroRevenue"
                            class="h-12 rounded-xl border font-black text-xs uppercase tracking-wider transition-colors outline-none flex items-center justify-center gap-2 shadow-sm"
                            :class="isZeroRevenue
                                ? 'bg-surface-2 border-surface-border/50 text-surface-muted/40 cursor-not-allowed opacity-50'
                                : 'bg-success-500/10 border-success-500/20 text-success-500 hover:bg-success-500/20 cursor-pointer'"
                            :title="isZeroRevenue ? 'Emissão fiscal dispensada' : 'Imprimir NFC-e'"
                        >
                            <i class="pi pi-print text-base"></i> Imprimir NFC-e
                        </button>
                    </div>
                </template>

                <div class="flex items-center gap-3 opacity-40 my-0.5">
                    <div class="h-px bg-surface-border flex-1"></div>
                    <span class="text-[8px] uppercase tracking-widest font-black text-surface-muted">Administrativo</span>
                    <div class="h-px bg-surface-border flex-1"></div>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <SecureActionButton
                        action-code="reactivate_order"
                        label="Reativar Pedido"
                        icon="pi-refresh"
                        variant="outlined"
                        severity="danger"
                        :disabled="isCancelled"
                        custom-class="w-full h-11 text-[10px] uppercase tracking-wider"
                        @secure-click="$emit('action', { type: 'reactivate' })"
                    />

                    <SecureActionButton
                        action-code="correct_payment"
                        label="Reabrir / Corrigir Pag."
                        icon="pi-pencil"
                        variant="outlined"
                        severity="warning"
                        :disabled="isCancelled"
                        custom-class="w-full h-11 text-[10px] uppercase tracking-wider"
                        @secure-click="$emit('action', { type: 'correct_payment' })"
                    />
                </div>

                <button @click="$emit('action', { type: 'no_print' })" class="w-full h-14 mt-1 rounded-2xl bg-success-500 text-white font-black text-sm uppercase tracking-widest hover:bg-success-600 transition-colors shadow-md outline-none flex items-center justify-center gap-2 cursor-pointer border-none">
                    <i class="pi pi-check-circle text-lg"></i> Concluir e Sair
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    isCancelled: { type: Boolean, default: false },
    order: { type: Object, default: () => ({}) },
    flashError: { type: String, default: null },
    isEmitting: { type: Boolean, default: false },
    fiscalSuccess: { type: Boolean, default: false },
    isZeroRevenue: { type: Boolean, default: false }
});

defineEmits(["action"]);

const page = usePage();
const companySettings = computed(() => page.props.companySettings || {});
const isFiscalEmitter = computed(() => companySettings.value.is_fiscal_emitter || false);

const hasFiscalDocument = computed(() => {
    return props.fiscalSuccess === true
        || props.order?.has_fiscal_document === true
        || props.order?.fiscal_status === 'authorized'
        || props.order?.fiscal_document?.status === 'authorized'
        || props.order?.fiscalDocument?.status === 'authorized'
        || page.props.hasFiscalDocument === true
        || false;
});

const isEligibleChannel = computed(() => {
    const type = props.order?.type;
    return ['delivery', 'counter', 'takeaway', 'pickup'].includes(type);
});

const isClaimEligibleChannel = computed(() => {
    const type = props.order?.type;
    return ['table', 'counter', 'takeaway', 'pickup', 'delivery'].includes(type);
});

const isFromCustomerApp = computed(() => {
    const src = props.order?.source;
    const hasEmployee = Boolean(props.order?.employee_id);
    return src === 'customer_app' || (!hasEmployee && !['pos_app', 'team_app', 'pos', 'team'].includes(src));
});

const isFromOperatorApp = computed(() => {
    const src = props.order?.source;
    const hasEmployee = Boolean(props.order?.employee_id);
    return hasEmployee || ['pos_app', 'team_app', 'pos', 'team'].includes(src);
});

const canPrintLoyaltyFlyer = computed(() => {
    const s = companySettings.value;
    const isLoyaltyActive = Boolean(s.loyalty_is_active && s.print_loyalty_flyer);
    const hasRewards = Number(props.order?.loyalty_points_earned || 0) > 0 || Number(props.order?.cashback_earned || 0) > 0;
    const hasCustomer = Boolean(props.order?.customer || props.order?.customer_id);

    return isLoyaltyActive && isEligibleChannel.value && isFromOperatorApp.value && hasCustomer && hasRewards;
});

const canPrintGrowthFlyer = computed(() => {
    const s = companySettings.value;
    return Boolean(s.print_growth_flyer && isEligibleChannel.value && isFromCustomerApp.value);
});

const canPrintTableClaimFlyer = computed(() => {
    const s = companySettings.value;
    const isLoyaltyActive = Boolean(s.loyalty_is_active);
    const isAnonymous = !props.order?.customer && !props.order?.customer_id;
    const hasTotal = Number(props.order?.total || 0) > 0;

    return isLoyaltyActive && isClaimEligibleChannel.value && isAnonymous && hasTotal;
});

const hasAnyFlyerAction = computed(() => {
    return canPrintLoyaltyFlyer.value || canPrintGrowthFlyer.value || canPrintTableClaimFlyer.value;
});

const marketingGridCols = computed(() => {
    const activeCount = [
        canPrintLoyaltyFlyer.value,
        canPrintGrowthFlyer.value,
        canPrintTableClaimFlyer.value
    ].filter(Boolean).length;

    return activeCount > 1 ? 'grid-cols-2' : 'grid-cols-1';
});
</script>
