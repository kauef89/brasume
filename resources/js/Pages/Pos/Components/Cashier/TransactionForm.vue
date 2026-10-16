<template>
    <div class="h-full w-full flex flex-col justify-between p-6 bg-surface-2 select-none overflow-y-auto hide-scrollbar">

        <!-- BLOCO SUPERIOR: TÍTULO, INFO E CAMPOS -->
        <div class="flex flex-col gap-6">

            <!-- BADGE DE STATUS DA OPERAÇÃO -->
            <div class="flex items-center gap-2">
                <span
                    class="w-2.5 h-2.5 rounded-full"
                    :class="isInput ? 'bg-primary-500 animate-pulse' : 'bg-warning-500'"
                ></span>
                <span class="text-xs font-black uppercase tracking-widest text-surface-muted">
                    {{ isInput ? 'Suprimento (Entrada de Troco)' : 'Sangria (Retirada de Caixa)' }}
                </span>
            </div>

            <!-- CARD EXPLICATIVO -->
            <div
                class="p-4 rounded-2xl flex items-start gap-3 border shadow-xs"
                :class="isInput
                    ? 'bg-primary-500/10 border-primary-500/20 text-primary-smart'
                    : 'bg-warning-500/10 border-warning-500/20 text-warning-600 dark:text-warning-400'"
            >
                <div
                    class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                    :class="isInput ? 'bg-primary-500/20 text-primary-500' : 'bg-warning-500/20 text-warning-500'"
                >
                    <i :class="['pi text-base', isInput ? 'pi-arrow-down' : 'pi-arrow-up']"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-black text-xs uppercase tracking-wider">
                        {{ isInput ? 'Reforço de Fundo' : 'Retirada de Espécie' }}
                    </span>
                    <p class="text-xs font-medium text-surface-muted mt-0.5 leading-snug">
                        {{ isInput ? 'Adicione moedas ou troco físico diretamente na gaveta do caixa.' : 'Retire dinheiro físico do caixa para o cofre ou depósito bancário.' }}
                    </p>
                </div>
            </div>

            <!-- VALOR -->
            <div class="flex flex-col gap-2">
                <label class="text-xs font-black text-surface-muted uppercase tracking-widest">
                    Valor da Transação
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-black text-surface-muted text-base z-10 pointer-events-none">R$</span>
                    <InputNumber
                        v-model="amount"
                        mode="decimal"
                        :minFractionDigits="2"
                        :maxFractionDigits="2"
                        placeholder="0,00"
                        class="w-full [&>input]:h-14 [&>input]:pl-12 [&>input]:pr-4 [&>input]:rounded-2xl [&>input]:bg-surface-1 [&>input]:text-surface-text [&>input]:text-2xl [&>input]:font-black [&>input]:border [&>input]:border-surface-border/60 [&>input]:focus:border-primary-500 [&>input]:outline-none [&>input]:transition-all [&>input]:shadow-inner"
                    />
                </div>
            </div>

            <!-- MOTIVO / DESCRIÇÃO -->
            <div class="flex flex-col gap-2">
                <label class="text-xs font-black text-surface-muted uppercase tracking-widest">
                    Motivo / Descrição
                </label>
                <textarea
                    v-model="description"
                    rows="3"
                    autocomplete="off"
                    :placeholder="isInput ? 'Ex: Reforço de troco repassado pelo cofre...' : 'Ex: Sangria para depósito bancário / cofre...'"
                    class="w-full bg-surface-1 text-surface-text text-xs font-medium p-3.5 rounded-2xl border border-surface-border/60 focus:border-primary-500 outline-none resize-none transition-colors shadow-inner"
                ></textarea>
            </div>
        </div>

        <!-- BLOCO INFERIOR: BOTÕES DE CONFIRMAÇÃO E CANCELAMENTO -->
        <div class="pt-6 flex flex-col gap-3">
            <button
                type="button"
                @click="handleConfirm"
                :disabled="!isValid || isSubmitting"
                class="w-full h-14 rounded-2xl font-black text-xs uppercase tracking-widest text-white transition-all shadow-lg flex items-center justify-center gap-2 outline-none border-none"
                :class="[
                    !isValid || isSubmitting
                        ? 'opacity-50 cursor-not-allowed bg-surface-3 text-surface-muted'
                        : (isInput ? 'bg-primary-500 hover:bg-primary-600 active:scale-[0.98] shadow-primary-500/25 cursor-pointer' : 'bg-warning-500 hover:bg-warning-600 active:scale-[0.98] shadow-warning-500/25 cursor-pointer')
                ]"
            >
                <i :class="['pi text-sm', isSubmitting ? 'pi-spinner pi-spin' : (isInput ? 'pi-check' : 'pi-arrow-up')]"></i>
                <span>{{ isInput ? 'Confirmar Entrada' : 'Confirmar Sangria' }}</span>
            </button>

            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" @secure-click="$emit('cancel')" />
        </div>

    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';

import { ref, computed, watch } from 'vue';
import InputNumber from 'primevue/inputnumber';

const props = defineProps({
    type: {
        type: String,
        default: 'in'
    }
});

const emit = defineEmits(['confirm', 'cancel']);

const amount = ref(null);
const description = ref('');
const isSubmitting = ref(false);

const isInput = computed(() => props.type === 'in');
const isValid = computed(() => Number(amount.value) > 0 && description.value.trim().length >= 3);

watch(() => props.type, () => {
    amount.value = null;
    description.value = '';
    isSubmitting.value = false;
});

const handleConfirm = () => {
    if (!isValid.value || isSubmitting.value) return;
    isSubmitting.value = true;
    emit('confirm', {
        type: props.type,
        amount: Number(amount.value),
        description: description.value.trim()
    });
};
</script>
