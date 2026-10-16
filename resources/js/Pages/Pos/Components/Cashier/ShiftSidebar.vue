<template>
    <div class="w-1/3 flex flex-col h-full border-r border-stone-200 dark:border-stone-800 bg-surface-1 shadow-xl z-10 shrink-0">
        <div class="p-6 border-b border-stone-200 dark:border-stone-800 flex items-center gap-4">
            <Button icon="pi pi-home" variant="text" rounded severity="secondary" @click="$emit('go-home')" />
            <div>
                <h1 class="text-2xl font-black leading-tight">Fluxo de Caixa</h1>
                <span class="text-sm font-medium text-stone-500 uppercase tracking-widest">Controle Operacional</span>
            </div>
        </div>

        <div class="p-8 flex-1 flex flex-col gap-6 overflow-y-auto">
            <div class="p-6 rounded-2xl flex flex-col items-center justify-center text-center transition-all border-2"
                :class="currentShift ? 'bg-green-50 border-green-200 text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400'">
                <i class="pi text-5xl mb-2" :class="currentShift ? 'pi pi-lock-open' : 'pi pi-lock'"></i>
                <h2 class="text-2xl font-black uppercase tracking-widest">
                    {{ currentShift ? 'Caixa Aberto' : 'Caixa Fechado' }}
                </h2>
                <p v-if="currentShift" class="text-sm font-medium mt-1 opacity-80">Aberto em: {{ formatDateTime(currentShift.opened_at) }}</p>
            </div>

            <div class="flex flex-col gap-3 mt-4">
                <template v-if="!currentShift">
                    <Button label="Realizar Abertura" icon="pi pi-play" size="large" @click="$emit('open-shift', 'open')" class="w-full h-16 text-lg font-black" />
                </template>
                <template v-else>
                    <SecureActionButton action-code="cash_out" label="Sangria (Retirada)" icon="pi-minus-circle" variant="outlined" severity="warning" custom-class="w-full h-14 font-medium" @secure-click="$emit('open-transaction', 'out')" />
                    <Button label="Suprimento (Reforço)" icon="pi pi-plus-circle" severity="info" variant="outlined" @click="$emit('open-transaction', 'in')" class="w-full h-14 font-medium" />
                    <div class="border-t border-stone-200 dark:border-stone-800 my-2"></div>
                    <SecureActionButton action-code="close_shift" label="Fechar o Caixa" icon="pi-stop" severity="danger" custom-class="w-full h-16 text-lg font-black" @secure-click="$emit('open-shift', 'close')" />
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';

import Button from 'primevue/button';

defineProps({
    currentShift: Object
});

defineEmits(['go-home', 'open-shift', 'open-transaction']);

const formatDateTime = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
};
</script>
