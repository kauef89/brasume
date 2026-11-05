<template>
    <div class="h-full w-full flex flex-col justify-between p-6 select-none overflow-y-auto hide-scrollbar">

        <!-- BLOCO SUPERIOR: TÍTULO, TOTAL E OBSERVAÇÃO -->
        <div class="flex flex-col gap-6">

            <!-- BADGE DE STATUS DA OPERAÇÃO -->
            <div class="flex items-center gap-2">
                <span
                    class="w-2.5 h-2.5 rounded-full bg-primary-500"
                    :class="isOpenMode ? 'animate-pulse' : ''"
                ></span>
                <span class="text-xs font-black uppercase tracking-widest text-surface-muted">
                    {{ isOpenMode ? 'Abertura de Turno (Passo 2 de 2)' : 'Fecho de Turno (Passo 1 de 2)' }}
                </span>
            </div>

            <!-- CARD DESTACADO: VALOR TOTAL CONFERIDO -->
            <div class="bg-surface-1 rounded-3xl p-6 border border-surface-border/50 text-center flex flex-col items-center justify-center gap-1 shadow-xs">
                <span class="text-[11px] font-black uppercase tracking-widest text-surface-muted">
                    {{ isOpenMode ? 'Fundo de Troco Conferido' : 'Total Contado em Gaveta' }}
                </span>
                <div class="text-4xl font-black tracking-tight mt-1 text-primary-smart">
                    {{ $formatCurrency(amount) }}
                </div>

                <div
                    v-if="!isOpenMode && companySettings.carry_over_mode === 'coins_only'"
                    class="mt-3 inline-flex items-center gap-1.5 bg-primary-500/10 text-primary-smart px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider border border-primary-500/20"
                >
                    <i class="pi pi-lock text-[9px]"></i>
                    <span>{{ $formatCurrency(coinsTotal) }} retidos para troco</span>
                </div>
            </div>

            <!-- CAMPO DE COMENTÁRIO / OBSERVAÇÃO -->
            <div class="flex flex-col gap-2">
                <label class="text-xs font-black text-surface-muted uppercase tracking-widest">
                    Observação / Comentário
                </label>
                <textarea
                    v-model="observation"
                    rows="4"
                    autocomplete="off"
                    :placeholder="isOpenMode ? 'Ex: Fundo extra repassado pelo cofre...' : 'Justifique eventuais sobras, quebras ou observações do turno...'"
                    class="w-full bg-surface-1 text-surface-text text-xs font-medium p-3.5 rounded-2xl border border-surface-border/60 focus:border-primary-500 outline-none resize-none transition-colors shadow-inner"
                ></textarea>
            </div>
        </div>

        <!-- BLOCO INFERIOR: BOTÃO PRINCIPAL DE AÇÃO -->
        <div class="pt-6 flex flex-col gap-3">
            <button
                type="button"
                @click="checkDiscrepancyBeforeConfirm"
                :disabled="!isValid || isAnalyzing"
                class="w-full h-14 rounded-2xl font-black text-xs uppercase tracking-widest text-white transition-all shadow-lg flex items-center justify-center gap-2 outline-none border-none"
                :class="[
                    !isValid || isAnalyzing
                        ? 'opacity-50 cursor-not-allowed bg-surface-3 text-surface-muted'
                        : 'bg-primary-500 hover:bg-primary-600 active:scale-[0.98] shadow-primary-500/25 cursor-pointer'
                ]"
            >
                <i v-if="isAnalyzing" class="pi pi-spinner pi-spin text-sm"></i>
                <i v-else :class="['pi text-sm', isOpenMode ? 'pi-check' : 'pi-arrow-right']"></i>
                <span>{{ isAnalyzing ? 'A Inspecionar...' : (isOpenMode ? 'Abrir Caixa' : 'Avançar para Equipa') }}</span>
            </button>

            <!-- Botão de voltar para a equipe durante o fluxo de abertura -->
            <button
                v-if="isOpenMode && hasStagedAttendees"
                type="button"
                @click="$emit('back-to-attendance')"
                class="w-full h-10 rounded-full font-black text-xs uppercase tracking-widest text-primary-smart hover:bg-surface-3 transition-colors outline-none cursor-pointer border-none flex items-center justify-center gap-1.5"
            >
                <i class="pi pi-arrow-left text-xs"></i>
                <span>Revisar Equipa</span>
            </button>

            <!-- Link para cancelar -->
            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" custom-class="px-6 h-10 rounded-full font-black text-sm uppercase tracking-widest" @secure-click="$emit('cancel')" />
        </div>

        <!-- MODAL DE SUGESTÃO DE CORREÇÃO INTELIGENTE (PRÉ-FECHO) -->
        <div v-if="smartSuggestion" class="fixed inset-0 z-[120] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in duration-200">
            <div class="bg-surface-1 border border-warning-500/40 rounded-3xl max-w-md w-full p-6 shadow-2xl flex flex-col gap-4">
                <div class="flex items-center gap-3 text-warning-500">
                    <div class="w-10 h-10 rounded-2xl bg-warning-500/10 flex items-center justify-center shrink-0">
                        <i class="pi pi-exclamation-triangle text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-base text-surface-text">Anomalia Identificada</h4>
                        <p class="text-xs text-surface-muted">Divergência de {{ $formatCurrency(discrepancyDiff) }} na gaveta</p>
                    </div>
                </div>

                <div class="bg-surface-2 rounded-2xl p-4 border border-surface-border/60 flex flex-col gap-2">
                    <span class="text-xs font-bold text-surface-text leading-relaxed">{{ smartSuggestion.reason }}</span>
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
import { ref, computed, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    mode: { type: String, default: 'open' },
    companySettings: { type: Object, default: () => ({}) },
    amount: { type: Number, default: 0 },
    coinsTotal: { type: Number, default: 0 },
    terminalTotals: { type: Object, default: () => ({}) },
    isValid: { type: Boolean, default: true },
    hasActiveShift: { type: Boolean, default: false },
    hasStagedAttendees: { type: Boolean, default: false }
});

const emit = defineEmits(['confirm', 'cancel', 'back-to-attendance']);
const confirm = useConfirm();
const toast = useToast();

const isOpenMode = computed(() => props.mode === 'open');
const storageObsKey = computed(() => `pos_shift_obs_${props.mode}`);

const getInitialObs = () => {
    try {
        return localStorage.getItem(`pos_shift_obs_${props.mode}`) || '';
    } catch (e) {
        return '';
    }
};

const observation = ref(getInitialObs());
const isAnalyzing = ref(false);
const smartSuggestion = ref(null);
const discrepancyDiff = ref(0);

watch(() => props.mode, () => {
    observation.value = getInitialObs();
});

watch(observation, (newVal) => {
    try {
        localStorage.setItem(storageObsKey.value, newVal || '');
    } catch (e) {}
});

const formatMoney = (val) => Number(val || 0).toFixed(2).replace('.', ',');

const checkDiscrepancyBeforeConfirm = async () => {
    if (!props.isValid || isAnalyzing.value) return;

    if (isOpenMode.value) {
        confirm.require({
            message: `Confirma a abertura do caixa com o fundo de troco de R$ ${formatMoney(props.amount)}?`,
            header: 'Abertura de Caixa',
            icon: 'pi pi-exclamation-triangle',
            acceptProps: { label: 'Sim, Abrir Caixa', icon: 'pi pi-check' },
            rejectProps: { label: 'Revisar', severity: 'secondary', outlined: true, icon: 'pi pi-times' },
            accept: () => emit('confirm', { observation: observation.value })
        });
        return;
    }

    isAnalyzing.value = true;

    try {
        const response = await axios.post(route('operations.cashier.inspect-discrepancy'), {
            amount: props.amount,
            terminal_totals: props.terminalTotals
        });

        if (response.data?.has_discrepancy && response.data?.suggestion) {
            smartSuggestion.value = response.data.suggestion;
            discrepancyDiff.value = response.data.difference;
            isAnalyzing.value = false;
            return;
        }
    } catch (e) {
        console.error('Falha na inspeção prévia de fecho:', e);
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

defineExpose({ triggerConfirmation: checkDiscrepancyBeforeConfirm });
</script>
