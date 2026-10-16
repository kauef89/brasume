<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        modal
        :closable="false"
        class="w-full max-w-md mx-4 rounded-3xl overflow-hidden border border-surface-border shadow-2xl bg-surface-2"
    >
        <template #header>
            <div class="flex items-center gap-3 pt-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                     :class="actionType === 'close_shift_override' ? 'bg-warning-500/10 text-warning-500' : 'bg-primary-500/10 text-primary-500'">
                    <i class="pi text-lg font-black" :class="modalIcon"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-surface-text leading-tight">{{ modalTitle }}</h3>
                    <p class="text-xs font-bold text-surface-muted">{{ itemName || 'Autorização de Gestão' }}</p>
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-4 py-2">
            <!-- ALERTA DE DIFERENÇA DE VALORES -->
            <div v-if="actionType === 'close_shift_override'" class="p-4 rounded-2xl bg-warning-500/10 border border-warning-500/20 flex flex-col gap-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-warning-600 dark:text-warning-400">
                    Atenção à Quebra de Caixa
                </span>
                <p class="text-xs font-medium text-surface-text leading-relaxed">
                    O valor contado na gaveta diverge do saldo esperado pelo sistema. Para registrar o encerramento com esta quebra, insira o PIN de um gestor.
                </p>
            </div>

            <!-- CAMPO DO PIN -->
            <div class="flex flex-col gap-2">
                <label class="text-xs font-black text-surface-muted uppercase tracking-widest">
                    PIN do Gestor / Admin
                </label>
                <div class="relative">
                    <input
                        ref="pinInputRef"
                        v-model="pin"
                        type="password"
                        maxlength="6"
                        inputmode="numeric"
                        placeholder="••••"
                        autocomplete="off"
                        @keydown.enter="handleConfirm"
                        class="w-full h-14 text-center tracking-[0.5em] text-2xl font-black rounded-2xl bg-surface-1 border border-surface-border focus:border-primary-500 outline-none text-surface-text transition-colors shadow-inner"
                    />
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center gap-2 w-full pt-2">
                <button
                    @click="$emit('update:visible', false)"
                    class="h-12 flex-1 rounded-xl font-black text-xs uppercase tracking-widest text-surface-muted hover:bg-surface-3 transition-colors outline-none cursor-pointer border-none"
                >
                    Cancelar
                </button>
                <button
                    @click="handleConfirm"
                    :disabled="!pin || pin.length < 3"
                    class="h-12 flex-1 rounded-xl font-black text-xs uppercase tracking-widest text-white bg-primary-500 hover:bg-primary-600 transition-colors shadow-md disabled:opacity-40 disabled:cursor-not-allowed outline-none cursor-pointer border-none"
                >
                    Autorizar
                </button>
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import Dialog from 'primevue/dialog';

const props = defineProps({
    visible: { type: Boolean, default: false },
    actionType: { type: String, default: 'close_shift_override' },
    itemName: { type: String, default: '' }
});

const emit = defineEmits(['update:visible', 'confirm']);

const pin = ref('');
const pinInputRef = ref(null);

watch(() => props.visible, (isOpen) => {
    if (isOpen) {
        pin.value = '';
        nextTick(() => {
            pinInputRef.value?.focus();
        });
    }
});

const modalTitle = computed(() => {
    const titles = {
        close_shift_override: 'Quebra de Caixa',
        cash_out: 'Autorização de Sangria',
        shift_attendance: 'Validação de Escala'
    };
    return titles[props.actionType] || 'Autorização do Gestor';
});

const modalIcon = computed(() => {
    const icons = {
        close_shift_override: 'pi-exclamation-triangle',
        cash_out: 'pi-arrow-up',
        shift_attendance: 'pi-users'
    };
    return icons[props.actionType] || 'pi-lock';
});

const handleConfirm = () => {
    if (!pin.value || pin.value.length < 3) return;
    emit('confirm', { password: pin.value });
};
</script>
