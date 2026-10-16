<template>
    <!-- ======================================================== -->
    <!-- 📱 MODO 1: MODAL IMERSIVO (Para o Garçom no TeamApp)     -->
    <!-- ======================================================== -->
    <div
        v-if="mode === 'modal'"
        class="fixed inset-0 z-[9999] bg-black/80 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-200"
    >
        <div class="bg-surface-2 border border-danger-500/40 rounded-3xl p-6 max-w-sm w-full shadow-2xl flex flex-col items-center text-center animate-in zoom-in-95 duration-200">
            <!-- Ícone de Alerta de Impressora -->
            <div class="w-16 h-16 rounded-full bg-danger-500/10 border border-danger-500/30 text-danger-500 flex items-center justify-center mb-4 shadow-inner">
                <i class="pi pi-print text-2xl animate-pulse"></i>
            </div>

            <span class="text-[10px] font-black uppercase tracking-widest text-danger-500 bg-danger-500/10 px-3 py-1 rounded-full mb-2">
                Atenção: Não Foi Impresso!
            </span>

            <h3 class="text-lg font-black text-surface-text leading-tight mb-2">
                {{ alert?.order_title || 'Pedido Registrado' }}
            </h3>

            <p class="text-xs text-surface-muted font-medium leading-relaxed mb-4">
                O pedido foi salvo no sistema, mas a comanda <strong class="text-surface-text font-bold">NÃO saiu na impressora</strong> da cozinha ({{ alert?.printer_name || 'Impressora Offline' }}).
            </p>

            <div class="w-full bg-surface-1/60 border border-surface-border p-3 rounded-2xl mb-5 text-left flex items-start gap-2.5">
                <i class="pi pi-info-circle text-primary-smart text-sm mt-0.5 shrink-0"></i>
                <div class="flex flex-col text-[11px] leading-snug">
                    <span class="font-bold text-surface-text">Ação recomendada:</span>
                    <span class="text-surface-muted">Comunique a cozinha ou o bar verbalmente sobre este pedido.</span>
                </div>
            </div>

            <button
                type="button"
                @click="$emit('dismiss')"
                class="w-full h-12 rounded-2xl bg-danger-600 hover:bg-danger-700 active:scale-95 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-danger-600/30 flex items-center justify-center gap-2 outline-none border-none cursor-pointer transition-all"
            >
                <i class="pi pi-check"></i>
                <span>Entendi, vou avisar</span>
            </button>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 🖥️ MODO 2: TOAST NO CANTO INFERIOR DIREITO (Para o PosApp)-->
    <!-- ======================================================== -->
    <div
        v-else-if="mode === 'toast'"
        class="bg-surface-2/95 dark:bg-surface-1/95 border-2 border-amber-500/60 shadow-2xl rounded-2xl p-4 backdrop-blur-xl flex flex-col gap-3 min-w-[300px] max-w-sm animate-in slide-in-from-bottom-5 fade-in duration-300 pointer-events-auto"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center shrink-0">
                    <i class="pi pi-exclamation-triangle text-sm"></i>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-500 leading-tight">
                        Falha na Impressão
                    </span>
                    <h4 class="text-xs font-black text-surface-text truncate leading-tight mt-0.5">
                        {{ alert?.order_title }}
                    </h4>
                </div>
            </div>

            <button
                type="button"
                @click="$emit('dismiss')"
                class="w-6 h-6 rounded-full bg-surface-3 hover:bg-surface-border text-surface-muted hover:text-surface-text flex items-center justify-center border-none cursor-pointer transition-colors"
                title="Fechar"
            >
                <i class="pi pi-times text-[10px]"></i>
            </button>
        </div>

        <div class="flex flex-col gap-1 text-[11px] text-surface-muted">
            <div class="flex items-center justify-between">
                <span>Impressora:</span>
                <strong class="text-surface-text">{{ alert?.printer_name || 'Cozinha/Bar' }}</strong>
            </div>
            <div class="flex items-center justify-between">
                <span>Origem:</span>
                <span class="font-bold px-1.5 py-0.5 rounded text-[9px] uppercase tracking-wider" :class="alert?.source === 'customer_app' ? 'bg-primary-500/10 text-primary-smart' : 'bg-surface-3 text-surface-text'">
                    {{ alert?.source === 'customer_app' ? 'Cardápio Online' : 'Garçom / Equipe' }}
                </span>
            </div>
        </div>

        <button
            type="button"
            @click="$emit('dismiss')"
            class="w-full h-8 rounded-xl bg-amber-500 hover:bg-amber-600 active:scale-95 text-black font-black text-[10px] uppercase tracking-wider flex items-center justify-center gap-1.5 border-none cursor-pointer transition-all shadow-sm"
        >
            <i class="pi pi-check text-[9px] font-bold"></i>
            <span>Entendi</span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    alert: { type: Object, required: true },
    mode: { type: String, default: 'modal' } // 'modal' ou 'toast'
});

defineEmits(['dismiss']);
</script>
