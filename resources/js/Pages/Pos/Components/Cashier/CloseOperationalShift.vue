<template>
    <div class="flex flex-col h-full bg-surface-2 text-surface-text">
        <!-- HEADER -->
        <div class="p-5 md:p-6 border-b border-surface-border flex items-center gap-4 shrink-0">
            <div class="w-12 h-12 rounded-2xl bg-primary-500/10 border border-primary-500/20 text-primary-500 flex items-center justify-center shrink-0 text-xl font-bold">
                <i class="pi pi-globe"></i>
            </div>
            <div>
                <h3 class="text-lg font-black leading-tight text-surface-text">Loja Online (CustomerApp)</h3>
                <p class="text-xs text-surface-muted mt-1 leading-relaxed">
                    Controle de recebimento de novos pedidos no app e cardápio digital.
                </p>
            </div>
        </div>

        <!-- BODY -->
        <div class="flex-1 p-5 md:p-6 overflow-y-auto flex flex-col gap-6 hide-scrollbar">

            <!-- STATUS ATUAL -->
            <div class="flex items-center justify-between p-4 rounded-2xl border"
                 :class="isCurrentlyAccepting ? 'bg-success-500/10 border-success-500/30 text-success-smart' : 'bg-warning-500/10 border-warning-500/30 text-warning-smart'">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full" :class="isCurrentlyAccepting ? 'bg-success-500 animate-pulse' : 'bg-warning-500'"></span>
                    <span class="text-xs font-black uppercase tracking-wider">
                        Status Atual: {{ isCurrentlyAccepting ? 'Recebendo Pedidos Online' : 'Pausada (Offline)' }}
                    </span>
                </div>
            </div>

            <!-- AVISO DE DESACOPLAMENTO OPERACIONAL -->
            <div class="bg-surface-3/60 border border-surface-border/80 rounded-2xl p-4 flex items-start gap-3">
                <i class="pi pi-info-circle text-primary-500 mt-0.5 shrink-0"></i>
                <span class="text-xs text-surface-text leading-relaxed">
                    Esta ação afeta <strong>apenas o aplicativo do cliente (Delivery e Retirada)</strong>. As mesas do salão, comandas locais, balcão e o caixa financeiro continuam funcionando normalmente.
                </span>
            </div>

            <!-- OPÇÕES DE ESCOLHA -->
            <div class="flex flex-col gap-3">
                <label class="text-xs font-black uppercase tracking-widest text-surface-muted">
                    Alterar Disponibilidade
                </label>

                <div class="flex flex-col gap-2">
                    <!-- PAUSAR LOJA ONLINE -->
                    <button
                        type="button"
                        @click="closeStoreChoice = true"
                        class="p-4 rounded-2xl border text-left flex flex-col gap-1 transition-all outline-none cursor-pointer"
                        :class="closeStoreChoice
                            ? 'bg-danger-500/10 border-danger-500 text-danger-500 ring-2 ring-danger-500/20'
                            : 'bg-surface-3/50 border-surface-border text-surface-muted hover:bg-surface-3'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm">Pausar Loja Online (Fechar)</span>
                            <i v-if="closeStoreChoice" class="pi pi-check text-sm font-black"></i>
                        </div>
                        <span class="text-xs opacity-75">Cardápio do cliente exibirá 'Expediente encerrado' e bloqueará novos carrinhos.</span>
                    </button>

                    <!-- MANTER / REABRIR LOJA ONLINE -->
                    <button
                        type="button"
                        @click="closeStoreChoice = false"
                        class="p-4 rounded-2xl border text-left flex flex-col gap-1 transition-all outline-none cursor-pointer"
                        :class="!closeStoreChoice
                            ? 'bg-success-500/10 border-success-500 text-success-smart ring-2 ring-success-500/20'
                            : 'bg-surface-3/50 border-surface-border text-surface-muted hover:bg-surface-3'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm">Manter / Reabrir Loja Online</span>
                            <i v-if="!closeStoreChoice" class="pi pi-check text-sm font-black"></i>
                        </div>
                        <span class="text-xs opacity-75">Cardápio digital liberado para novos pedidos de delivery e retirada.</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="p-5 md:p-6 border-t border-surface-border mt-auto shrink-0 flex items-center justify-end gap-3">
            <SecureActionButton action-code="cancel" label="Voltar" variant="text" severity="secondary" custom-class="px-6 h-10 rounded-full font-black text-sm uppercase tracking-widest" @secure-click="$emit('cancel')" />
            <button
                type="button"
                @click="$emit('confirm', closeStoreChoice)"
                :disabled="isProcessing"
                class="px-5 py-3 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-xs font-black uppercase tracking-wider flex items-center gap-2 shadow-sm transition-all outline-none cursor-pointer border-none active:scale-95 disabled:opacity-50"
            >
                <i v-if="isProcessing" class="pi pi-spinner pi-spin"></i>
                <span>{{ isProcessing ? 'Atualizando...' : 'Confirmar' }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    isProcessing: Boolean,
    currentShift: Object
});

defineEmits(['cancel', 'confirm']);

const isCurrentlyAccepting = computed(() => {
    return props.currentShift?.accepting_orders !== false;
});

const closeStoreChoice = ref(isCurrentlyAccepting.value);

watch(isCurrentlyAccepting, (nowAccepting) => {
    closeStoreChoice.value = nowAccepting;
}, { immediate: true });
</script>
