<template>
    <div class="h-full w-full flex flex-col justify-between p-6 select-none overflow-y-auto hide-scrollbar">

        <!-- BLOCO SUPERIOR: TÍTULO, TOTAL E OBSERVAÇÃO -->
        <div class="flex flex-col gap-6">

            <!-- BADGE DE STATUS DA OPERAÇÃO -->
            <div class="flex items-center gap-2">
                <span
                    class="w-2.5 h-2.5 rounded-full"
                    :class="isOpenMode ? 'bg-primary-500 animate-pulse' : 'bg-danger-500'"
                ></span>
                <span class="text-xs font-black uppercase tracking-widest text-surface-muted">
                    {{ isOpenMode ? 'Abertura de Turno (Passo 2 de 2)' : 'Fechamento de Turno (Passo 1 de 2)' }}
                </span>
            </div>

            <!-- CARD DESTACADO: VALOR TOTAL CONFERIDO -->
            <div class="bg-surface-1 rounded-3xl p-6 border border-surface-border/50 text-center flex flex-col items-center justify-center gap-1 shadow-xs">
                <span class="text-[11px] font-black uppercase tracking-widest text-surface-muted">
                    {{ isOpenMode ? 'Fundo de Troco Conferido' : 'Total Contado em Gaveta' }}
                </span>
                <div
                    class="text-4xl font-black tracking-tight mt-1"
                    :class="isOpenMode ? 'text-primary-smart' : 'text-surface-text'"
                >
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
                @click="triggerConfirmation"
                :disabled="!isValid || isConfirming"
                class="w-full h-14 rounded-2xl font-black text-xs uppercase tracking-widest text-white transition-all shadow-lg flex items-center justify-center gap-2 outline-none border-none"
                :class="[
                    !isValid || isConfirming
                        ? 'opacity-50 cursor-not-allowed bg-surface-3 text-surface-muted'
                        : (isOpenMode ? 'bg-primary-500 hover:bg-primary-600 active:scale-[0.98] shadow-primary-500/25 cursor-pointer' : 'bg-danger-500 hover:bg-danger-600 active:scale-[0.98] shadow-danger-500/25 cursor-pointer')
                ]"
            >
                <i :class="['pi text-sm', isOpenMode ? 'pi-check' : 'pi-arrow-right']"></i>
                <span>{{ isOpenMode ? 'Abrir Caixa' : 'Avançar para Equipe' }}</span>
            </button>

            <!-- Botão de voltar para a equipe durante o fluxo de abertura -->
            <button
                v-if="isOpenMode && hasStagedAttendees"
                type="button"
                @click="$emit('back-to-attendance')"
                class="w-full h-10 rounded-full font-black text-xs uppercase tracking-widest text-primary-smart hover:bg-surface-3 transition-colors outline-none cursor-pointer border-none flex items-center justify-center gap-1.5"
            >
                <i class="pi pi-arrow-left text-xs"></i>
                <span>Revisar Equipe</span>
            </button>

            <!-- Link para cancelar -->
            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" custom-class="px-6 h-10 rounded-full font-black text-sm uppercase tracking-widest" @secure-click="$emit('cancel')" />
        </div>

    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';
import { ref, computed } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    mode: { type: String, default: 'open' },
    companySettings: { type: Object, default: () => ({}) },
    amount: { type: Number, default: 0 },
    coinsTotal: { type: Number, default: 0 },
    isValid: { type: Boolean, default: true },
    hasActiveShift: { type: Boolean, default: false },
    hasStagedAttendees: { type: Boolean, default: false }
});

const emit = defineEmits(['confirm', 'cancel', 'back-to-attendance']);
const confirm = useConfirm();

const isOpenMode = computed(() => props.mode === 'open');
const observation = ref('');
const isConfirming = ref(false);

const formatMoney = (val) => Number(val || 0).toFixed(2).replace('.', ',');

const triggerConfirmation = () => {
    if (!props.isValid || isConfirming.value) return;
    isConfirming.value = true;

    const acceptCallback = () => {
        isConfirming.value = false;
        emit('confirm', { observation: observation.value });
    };

    const rejectCallback = () => {
        isConfirming.value = false;
    };

    if (isOpenMode.value) {
        confirm.require({
            message: `Confirma a abertura do caixa com o fundo de troco de R$ ${formatMoney(props.amount)}?`,
            header: 'Abertura de Caixa',
            icon: 'pi pi-exclamation-triangle',
            acceptProps: { label: 'Sim, Abrir Caixa', icon: 'pi pi-check' },
            rejectProps: { label: 'Revisar', severity: 'secondary', outlined: true, icon: 'pi pi-times' },
            accept: acceptCallback,
            reject: rejectCallback,
            onHide: rejectCallback
        });
    } else {
        // No fechamento, avança diretamente para a etapa de acertos
        isConfirming.value = false;
        emit('confirm', { observation: observation.value });
    }
};

defineExpose({ triggerConfirmation });
</script>
