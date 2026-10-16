<template>
    <AdaptiveModal v-model:visible="isVisible">

        <!-- 🚀 CABEÇALHO -->
        <div class="flex items-center gap-3 mb-6 shrink-0">
            <div class="w-12 h-12 rounded-full bg-orange-500/10 text-orange-600 flex items-center justify-center shrink-0 border border-orange-500/20">
                <i class="pi pi-arrow-down-right text-xl"></i>
            </div>
            <div class="flex flex-col">
                <h2 class="text-xl font-black text-surface-text leading-tight">Registrar Desperdício</h2>
                <span class="text-[10px] font-black uppercase tracking-widest text-orange-500 mt-0.5">Baixa Automática de Estoque</span>
            </div>
        </div>

        <!-- 🚀 CONTEÚDO (Scrollável no Bottom Sheet) -->
        <div class="flex flex-col gap-6 py-2 overflow-y-auto hide-scrollbar flex-1 min-h-0">

            <!-- Seleção do Item -->
            <div class="flex flex-col gap-3">
                <label class="block text-[11px] font-black text-surface-muted uppercase tracking-widest pl-1">O que foi perdido?</label>
                <div class="grid grid-cols-1 gap-2">
                    <button
                        v-for="opt in compositionOptions"
                        :key="opt.id"
                        @click="form.targetItem = opt"
                        type="button"
                        class="flex items-center justify-between p-4 rounded-2xl font-black text-sm text-left transition-all border-2 outline-none cursor-pointer"
                        :class="form.targetItem?.id === opt.id ? 'bg-orange-500/10 border-orange-500 text-orange-700 dark:text-orange-400' : 'bg-surface-1 border-surface-border text-surface-text hover:border-orange-400 hover:bg-orange-500/5'"
                    >
                        {{ opt.label }}
                        <i v-if="form.targetItem?.id === opt.id" class="pi pi-check-circle text-orange-500 text-lg animate-in zoom-in duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- Quantidade (Reutilizando o seu QtyStepper!) -->
            <div class="flex flex-col gap-3 items-center bg-surface-2 p-5 rounded-2xl border border-surface-border">
                <label class="block text-[11px] font-black text-surface-text uppercase tracking-widest text-center">Quantidade Perdida</label>
                <QtyStepper v-model="form.quantity" :min="0.5" :step="0.5" size="lg" />
            </div>

            <!-- Motivo Rápido -->
            <div class="flex flex-col gap-3">
                <label class="block text-[11px] font-black text-surface-muted uppercase tracking-widest pl-1">Motivo do Desperdício</label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="reason in ['Caiu no chão', 'Erro na Receita', 'Passou do Ponto', 'Vencimento']"
                        :key="reason"
                        @click="form.reason = reason"
                        type="button"
                        class="p-4 rounded-xl font-bold text-xs transition-all border-2 outline-none text-center cursor-pointer"
                        :class="form.reason === reason ? 'bg-surface-text border-surface-text text-surface-1 shadow-md' : 'bg-surface-1 border-surface-border text-surface-muted hover:bg-surface-2'"
                    >
                        {{ reason }}
                    </button>
                </div>
            </div>
        </div>

        <!-- 🚀 RODAPÉ (Fixo na base) -->
        <div class="flex items-center gap-2 w-full mt-6 pt-4 border-t border-surface-border/50 shrink-0">
            <Button label="Cancelar" variant="text" severity="secondary" @click="isVisible = false" class="flex-1 font-medium h-14" />
            <Button label="Confirmar Baixa" severity="warning" @click="handleConfirm" :disabled="!isValid" class="flex-1 font-black h-14" />
        </div>

    </AdaptiveModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Button from 'primevue/button';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import QtyStepper from '@/Components/UI/QtyStepper.vue';

const props = defineProps({ visible: Boolean, item: Object });
const emit = defineEmits(['update:visible', 'confirm']);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const form = ref({ targetItem: null, quantity: 1, reason: null });

watch(() => props.visible, (isOpen) => {
    if (isOpen) {
        form.value = { targetItem: compositionOptions.value[0] || null, quantity: 1, reason: null };
    }
});

const compositionOptions = computed(() => {
    if (!props.item) return [];
    let options = [{ label: `Prato Completo (${props.item.product_name})`, type: 'main', id: props.item.product_id }];
    if (props.item.modifiers) {
        props.item.modifiers.forEach(mod => {
            options.push({ label: `Insumo Extra (${mod.name})`, type: 'modifier', id: mod.modifier_option_id, opt_name: mod.name });
        });
    }
    return options;
});

const isValid = computed(() => form.value.targetItem && form.value.quantity > 0 && form.value.reason);

const handleConfirm = () => {
    emit('confirm', {
        type: form.value.targetItem.type,
        target_id: form.value.targetItem.id,
        target_name: form.value.targetItem.opt_name || props.item.product_name,
        quantity: form.value.quantity,
        reason: form.value.reason,
        notes: ''
    });
};
</script>
