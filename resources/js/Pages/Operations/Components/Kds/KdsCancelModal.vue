<template>
    <AdaptiveModal v-model:visible="isVisible">

        <!-- 🚀 CABEÇALHO -->
        <div class="flex items-center gap-3 mb-6 shrink-0">
            <div class="w-12 h-12 rounded-full bg-danger-500/10 text-danger-600 flex items-center justify-center shrink-0 border border-danger-500/20">
                <i class="pi fa-circle-xmark text-xl"></i>
            </div>
            <div class="flex flex-col">
                <h2 class="text-xl font-black text-surface-text leading-tight">Cancelar Via</h2>
                <span class="text-[10px] font-black uppercase tracking-widest text-danger-500 mt-0.5">Estorno de Produção e Caixa</span>
            </div>
        </div>

        <!-- 🚀 CONTEÚDO -->
        <div class="flex flex-col gap-6 py-2 overflow-y-auto hide-scrollbar flex-1 min-h-0">

            <div class="bg-danger-500/10 p-4 rounded-xl border border-danger-500/20 flex gap-3 items-start">
                <i class="pi fa-exclamation-triangle text-danger-600 mt-0.5"></i>
                <p class="text-xs text-danger-800 dark:text-danger-300 font-medium leading-tight">
                    A via será removida da cozinha e o valor deduzido da conta. Se o cliente já pagou, um crédito será gerado na retaguarda.
                </p>
            </div>

            <!-- Motivo -->
            <div class="flex flex-col gap-3">
                <label class="block text-[11px] font-black text-surface-muted uppercase tracking-widest pl-1">Por que está cancelando?</label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="r in ['Falta de Insumos', 'Erro no Lançamento', 'Cliente Desistiu', 'Problema na Cozinha']"
                        :key="r"
                        @click="form.reason = r; if(r !== 'Falta de Insumos') form.blockSales = false;"
                        type="button"
                        class="p-4 rounded-xl font-bold text-xs transition-all border-2 outline-none text-center cursor-pointer flex items-center justify-center h-14"
                        :class="form.reason === r ? 'bg-danger-500 border-danger-500 text-white shadow-md' : 'bg-surface-1 border-surface-border text-surface-text hover:border-danger-400 hover:bg-danger-500/10'"
                    >
                        {{ r }}
                    </button>
                </div>
            </div>

            <!-- Bloco Dinâmico: STOP THE LINE (Só aparece se faltar insumo) -->
            <div v-if="form.reason === 'Falta de Insumos'" class="flex flex-col gap-4 p-5 bg-surface-2 border border-surface-border rounded-2xl animate-in fade-in slide-in-from-top-2">

                <!-- Toggle Switch Estilizado -->
                <button @click="form.blockSales = !form.blockSales" type="button" class="flex items-center justify-between w-full outline-none text-left cursor-pointer">
                    <div class="flex flex-col">
                        <span class="block font-black text-surface-text text-sm">Bloquear Novas Vendas</span>
                        <span class="block text-[10px] uppercase font-bold tracking-widest mt-1 transition-colors" :class="form.blockSales ? 'text-danger-500' : 'text-surface-muted'">Marcar Item como Esgotado</span>
                    </div>
                    <div class="w-14 h-7 rounded-full transition-colors relative shadow-inner" :class="form.blockSales ? 'bg-danger-500' : 'bg-surface-border'">
                        <div class="absolute top-1 bottom-1 w-5 bg-white rounded-full transition-all shadow-sm" :class="form.blockSales ? 'left-[30px]' : 'left-1'"></div>
                    </div>
                </button>

                <!-- Seleção do que secou no estoque -->
                <div v-if="form.blockSales" class="pt-4 border-t border-surface-border/50 flex flex-col gap-3 animate-in fade-in duration-300">
                    <label class="block text-[10px] font-black text-danger-500 uppercase tracking-widest pl-1">O que secou no estoque?</label>
                    <div class="grid grid-cols-1 gap-2 max-h-[160px] overflow-y-auto pr-1 hide-scrollbar">
                        <button
                            v-for="opt in compositionOptions"
                            :key="opt.id"
                            @click="form.itemToBlock = opt"
                            type="button"
                            class="flex items-center justify-between p-4 rounded-xl font-black text-sm text-left transition-all border-2 outline-none cursor-pointer"
                            :class="form.itemToBlock?.id === opt.id ? 'bg-danger-500/10 border-danger-500 text-danger-700 dark:text-danger-400' : 'bg-surface-1 border-surface-border text-surface-text hover:border-danger-300'"
                        >
                            {{ opt.label }}
                            <i v-if="form.itemToBlock?.id === opt.id" class="pi pi-check-circle text-danger-500 text-lg animate-in zoom-in"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🚀 RODAPÉ -->
        <div class="flex items-center gap-2 w-full mt-6 pt-4 border-t border-surface-border/50 shrink-0">
            <Button label="Voltar" variant="text" severity="secondary" @click="isVisible = false" class="flex-1 font-medium h-14" />
            <Button label="Confirmar Cancelamento" severity="danger" @click="handleConfirm" :disabled="!isValid" class="flex-1 font-black h-14" />
        </div>

    </AdaptiveModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Button from 'primevue/button';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

const props = defineProps({ visible: Boolean, item: Object });
const emit = defineEmits(['update:visible', 'confirm']);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const form = ref({ reason: null, blockSales: false, itemToBlock: null });

watch(() => props.visible, (isOpen) => {
    if (isOpen) form.value = { reason: null, blockSales: false, itemToBlock: compositionOptions.value[0] || null };
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

const isValid = computed(() => {
    if (!form.value.reason) return false;
    if (form.value.reason === 'Falta de Insumos' && form.value.blockSales && !form.value.itemToBlock) return false;
    return true;
});

const handleConfirm = () => {
    emit('confirm', {
        reason: form.value.reason,
        block_sales: form.value.blockSales,
        block_type: form.value.blockSales ? form.value.itemToBlock.type : null,
        block_id: form.value.blockSales ? form.value.itemToBlock.id : null
    });
};
</script>
