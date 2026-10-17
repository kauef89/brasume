<template>
    <div
        v-if="isAppending"
        class="flex flex-col gap-2 p-3 rounded-2xl text-xs animate-in fade-in duration-200"
        :class="changeAnalysis.isExceeded
            ? 'bg-danger-500/10 border-danger-500/30 text-danger-600 dark:text-danger-400'
            : (wasOriginallyPaid
                ? 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300'
                : 'bg-surface-3')"
    >
        <!-- DETALHES FINANCEIROS CONSOLIDADOS -->
        <div class="grid grid-cols-3 gap-2 text-[11px]">
            <div>
                <span class="text-[9px] uppercase tracking-wider text-surface-muted block">Já Pago</span>
                <span class="font-bold text-surface-text">{{ $formatCurrency(alreadyPaidAmount) }}</span>
            </div>
            <div>
                <span class="text-[9px] uppercase tracking-wider text-surface-muted block">Novos Itens</span>
                <span class="font-bold text-primary-500">+ {{ $formatCurrency(newItemsAmount) }}</span>
            </div>
            <div>
                <span class="text-[9px] uppercase tracking-wider text-surface-muted block">Saldo a Pagar</span>
                <span class="font-black" :class="balanceDue > 0 ? 'text-amber-500' : 'text-emerald-500'">
                    {{ $formatCurrency(balanceDue) }}
                </span>
            </div>
        </div>

        <!-- ALERTA DE TROCO EM DINHEIRO -->
        <div v-if="changeAnalysis.hasChange" class="pt-1 border-t border-surface-border/40 text-[10px] font-bold flex items-center gap-1.5">
            <i class="pi" :class="changeAnalysis.isExceeded ? 'pi-exclamation-triangle text-danger-500 animate-pulse' : 'pi-money-bill text-emerald-500'"></i>
            <span>{{ changeAnalysis.warning }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    isAppending: Boolean,
    targetOrderId: [Number, String],
    originalOrder: Object,
    alreadyPaidAmount: Number,
    newItemsAmount: Number,
    balanceDue: Number,
    wasOriginallyPaid: Boolean,
    changeAnalysis: Object
});
</script>
