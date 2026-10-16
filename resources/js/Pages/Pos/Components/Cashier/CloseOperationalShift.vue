<template>
    <div class="flex flex-col h-full bg-surface-2 text-surface-text">
        <!-- HEADER -->
        <div class="p-5 md:p-6 border-b border-surface-border flex items-center gap-4 shrink-0">
            <div class="w-12 h-12 rounded-2xl bg-warning-500/10 border border-warning-500/20 text-warning-500 flex items-center justify-center shrink-0 text-xl font-bold">
                <i class="pi pi-history"></i>
            </div>
            <div>
                <h3 class="text-lg font-black leading-tight text-surface-text">Turno Operacional</h3>
                <p class="text-xs text-surface-muted mt-1 leading-relaxed">
                    Sincroniza prateleiras e WMS.
                </p>
            </div>
        </div>

        <!-- BODY -->
        <div class="flex-1 p-5 md:p-6 overflow-y-auto flex flex-col gap-6 hide-scrollbar">

            <!-- AVISO DE COMANDAS ABERTAS (REGRA 1 E 2) -->
            <div class="bg-surface-3/60 border border-surface-border/80 rounded-2xl p-4 flex items-start gap-3">
                <i class="pi pi-info-circle text-primary-500 mt-0.5 shrink-0"></i>
                <span class="text-xs text-surface-text leading-relaxed">
                    Mesas e pedidos em andamento <strong>permanecem ativos</strong>. O caixa financeiro continua aberto normalmente.
                </span>
            </div>

            <!-- PERGUNTA DE LOJA ONLINE (REGRA 3) -->
            <div class="flex flex-col gap-3">
                <label class="text-xs font-black uppercase tracking-widest text-surface-text">
                    Loja no App do Cliente
                </label>

                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        @click="closeStoreChoice = true"
                        class="p-4 rounded-2xl border text-left flex flex-col gap-1 transition-all outline-none cursor-pointer"
                        :class="closeStoreChoice
                            ? 'bg-danger-500/10 border-danger-500 text-danger-500 ring-2 ring-danger-500/20'
                            : 'bg-surface-3/50 border-surface-border text-surface-muted hover:bg-surface-3'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm">Fechar loja online</span>
                            <i v-if="closeStoreChoice" class="pi pi-check text-sm font-black"></i>
                        </div>
                        <span class="text-xs opacity-75">Cardápio ficará offline para novos pedidos</span>
                    </button>

                    <button
                        type="button"
                        @click="closeStoreChoice = false"
                        class="p-4 rounded-2xl border text-left flex flex-col gap-1 transition-all outline-none cursor-pointer"
                        :class="!closeStoreChoice
                            ? 'bg-primary-500/10 border-primary-500 text-primary-500 ring-2 ring-primary-500/20'
                            : 'bg-surface-3/50 border-surface-border text-surface-muted hover:bg-surface-3'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm">Manter loja aberta</span>
                            <i v-if="!closeStoreChoice" class="pi pi-check text-sm font-black"></i>
                        </div>
                        <span class="text-xs opacity-75">Apenas troca de equipe (vendas continuam)</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="p-5 md:p-6 border-t border-surface-border mt-auto shrink-0 flex items-center justify-end gap-3">
            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" custom-class="px-6 h-10 rounded-full font-black text-sm uppercase tracking-widest" @secure-click="$emit('cancel')" />
            <button
                type="button"
                @click="$emit('confirm', closeStoreChoice)"
                :disabled="isProcessing"
                class="px-5 py-3 rounded-xl bg-warning-500 hover:bg-warning-600 text-white text-xs font-black uppercase tracking-wider flex items-center gap-2 shadow-sm transition-all outline-none cursor-pointer border-none active:scale-95 disabled:opacity-50"
            >
                <i v-if="isProcessing" class="pi pi-spinner pi-spin"></i>
                <span>{{ isProcessing ? 'Processando...' : 'Confirmar' }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';

import { ref } from 'vue';

defineProps({
    isProcessing: Boolean
});

defineEmits(['cancel', 'confirm']);

const closeStoreChoice = ref(true);
</script>
