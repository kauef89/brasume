<template>
    <div class="h-full w-full flex flex-col justify-between p-6 select-none overflow-y-auto hide-scrollbar">

        <!-- BLOCO SUPERIOR -->
        <div class="flex flex-col gap-6">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-primary-500" :class="isOpenMode ? 'animate-pulse' : ''"></span>
                <span class="text-xs font-black uppercase tracking-widest text-surface-muted">
                    {{ isOpenMode ? 'Abertura de Turno (Passo 2 de 2)' : 'Fecho de Turno (Passo 1 de 2)' }}
                </span>
            </div>

            <div class="bg-surface-1 rounded-3xl p-6 border border-surface-border/50 text-center flex flex-col items-center justify-center gap-1 shadow-xs">
                <span class="text-[11px] font-black uppercase tracking-widest text-surface-muted">
                    {{ isOpenMode ? 'Fundo de Troco Conferido' : 'Total Contado em Gaveta' }}
                </span>
                <div class="text-4xl font-black tracking-tight mt-1 text-primary-smart">
                    {{ $formatCurrency(amount) }}
                </div>
            </div>

            <!-- CAMPO DE OBSERVAÇÃO -->
            <div class="flex flex-col gap-2">
                <label class="text-xs font-black text-surface-muted uppercase tracking-widest">
                    Observação / Justificação
                </label>
                <textarea
                    v-model="observation"
                    rows="3"
                    placeholder="Justifique eventuais sobras, quebras ou notas do turno..."
                    class="w-full bg-surface-1 text-surface-text text-xs font-medium p-3.5 rounded-2xl border border-surface-border/60 focus:border-primary-500 outline-none resize-none transition-colors shadow-inner"
                ></textarea>
            </div>
        </div>

        <!-- BLOCO INFERIOR COM BOTÕES -->
        <div class="pt-6 flex flex-col gap-3">
            <button
                type="button"
                @click="checkDiscrepancyBeforeConfirm"
                :disabled="!isValid || isAnalyzing"
                class="w-full h-14 rounded-2xl font-black text-xs uppercase tracking-widest text-white transition-all shadow-lg flex items-center justify-center gap-2 outline-none border-none"
                :class="[!isValid || isAnalyzing ? 'opacity-50 cursor-not-allowed bg-surface-3 text-surface-muted' : 'bg-primary-500 hover:bg-primary-600 active:scale-[0.98] shadow-primary-500/25 cursor-pointer']"
            >
                <i v-if="isAnalyzing" class="pi pi-spinner pi-spin text-sm"></i>
                <i v-else :class="['pi text-sm', isOpenMode ? 'pi-check' : 'pi-arrow-right']"></i>
                <span>{{ isAnalyzing ? 'A Inspecionar...' : (isOpenMode ? 'Abrir Caixa' : 'Avançar para Equipa') }}</span>
            </button>

            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" custom-class="px-6 h-10 rounded-full font-black text-sm uppercase tracking-widest" @secure-click="$emit('cancel')" />
        </div>

        <!-- MODAL DE SUGESTÃO DE CORREÇÃO INTELIGENTE -->
        <div v-if="smartSuggestion" class="fixed inset-0 z-[120] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in duration-200">
            <div class="bg-surface-1 border border-warning-500/40 rounded-3xl max-w-md w-full p-6 shadow-2xl flex flex-col gap-4">
                <div class="flex items-center gap-3 text-warning-500">
                    <div class="w-10 h-10 rounded-2xl bg-warning-500/10 flex items-center justify-center shrink-0">
                        <i class="pi pi-exclamation-triangle text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-base text-surface-text">Anomalia Detetada</h4>
                        <p class="text-xs text-surface-muted">Diferença de {{ $formatCurrency(discrepancyDiff) }} em gaveta</p>
                    </div>
                </div>

                <div class="bg-surface-2 rounded-2xl p-4 border border-surface-border/60 flex flex-col gap-2">
                    <span class="text-xs font-bold text-surface-text">{{ smartSuggestion.reason }}</span>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-surface-border/40">
                        <span class="text-surface-muted">Método Atual: <strong class="text-danger-500">{{ smartSuggestion.current_method_name }}</strong></span>
                        <i class="pi pi-arrow-right text-[10px] text-surface-muted"></i>
                        <span class="text-surface-muted">Sugerido: <strong class="text-success-smart">{{ smartSuggestion.suggested_method_name }}</strong></span>
                    </div>
                </div>

                <div class="flex flex-col gap-2 pt-2">
                    <button
                        type="button"
                        @click="applyCorrection"
                        class="w-full py-3.5 rounded-xl bg-warning-500 hover:bg-warning-600 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer border-none shadow-md"
                    >
                        <i class="pi pi-check"></i>
                        <span>Corrigir para {{ smartSuggestion.suggested_method_name }}</span>
                    </button>

                    <button
                        type="button"
                        @click="ignoreAndProceed"
                        class="w-full py-2.5 rounded-xl bg-transparent hover:bg-surface-2 text-surface-muted font-bold text-xs uppercase tracking-wider transition-colors cursor-pointer border-none"
                    >
                        Manter Diferença (Registar Quebra)
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';
import { ref, computed } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    mode: { type: String, default: 'open' },
    companySettings: { type: Object, default: () => ({}) },
    amount: { type: Number, default: 0 },
    isValid: { type: Boolean, default: true }
});

const emit = defineEmits(['confirm', 'cancel']);
const toast = useToast();

const isOpenMode = computed(() => props.mode === 'open');
const observation = ref('');
const isAnalyzing = ref(false);
const smartSuggestion = ref(null);
const discrepancyDiff = ref(0);

const checkDiscrepancyBeforeConfirm = async () => {
    if (isOpenMode.value) {
        emit('confirm', { observation: observation.value });
        return;
    }

    isAnalyzing.value = true;

    try {
        const response = await axios.post(route('operations.cashier.inspect-discrepancy'), {
            amount: props.amount
        });

        if (response.data?.has_discrepancy && response.data?.suggestion) {
            smartSuggestion.value = response.data.suggestion;
            discrepancyDiff.value = response.data.difference;
            isAnalyzing.value = false;
            return;
        }
    } catch (e) {
        console.error('Falha na inspeção prévia:', e);
    } finally {
        isAnalyzing.value = false;
    }

    emit('confirm', { observation: observation.value });
};

const applyCorrection = async () => {
    try {
        const res = await axios.post(route('operations.cashier.fix-payment-method'), {
            payment_id: smartSuggestion.value.payment_id,
            new_method_id: smartSuggestion.value.suggested_method_id
        });

        toast.add({
            severity: 'success',
            summary: 'Lançamento Regularizado',
            detail: res.data?.message || 'Meio de pagamento corrigido com sucesso!',
            life: 4000
        });

        smartSuggestion.value = null;
        emit('confirm', { observation: observation.value });
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Erro na Correção',
            detail: err.response?.data?.error || 'Não foi possível corrigir o meio de pagamento.',
            life: 5000
        });
    }
};

const ignoreAndProceed = () => {
    smartSuggestion.value = null;
    emit('confirm', { observation: observation.value });
};
</script>
