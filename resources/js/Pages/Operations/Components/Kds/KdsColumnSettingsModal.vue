<template>
    <BaseModal
        :modelValue="visible"
        @update:modelValue="$emit('update:visible', $event)"
        title="Configurar Colunas"
        icon="pi-sliders-h"
        width="500px"
        :dismissableMask="false"
    >
        <div class="flex flex-col gap-2">
            <p class="text-sm text-surface-muted mb-4 pt-2">
                Arraste as colunas para reordenar. A coluna marcada com <i class="pi pi-check-circle text-success-500"></i> possuirá o botão de despachar itens.
            </p>

            <draggable :model-value="columns" @update:model-value="$emit('update:columns', $event)" item-key="id" handle=".drag-handle" class="flex flex-col gap-2" ghost-class="opacity-40">
                <template #item="{ element, index }">
                    <div class="flex items-center gap-2 p-2 bg-surface-2 border border-surface-border rounded-xl shadow-sm">
                        <i class="pi pi-bars text-surface-muted cursor-grab active:cursor-grabbing drag-handle px-2 hover:text-primary-smart transition-colors"></i>
                        <div class="flex-1">
                            <input v-model="element.label" class="w-full h-10 px-3 rounded-lg bg-surface-1 border border-surface-border text-sm font-medium focus:border-primary-500 outline-none" />
                        </div>
                        <button @click="$emit('set-final', index)" class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors outline-none"
                                :class="element.is_final ? 'bg-success-500/20 text-success-600' : 'bg-surface-3 text-surface-muted hover:bg-surface-1'" title="Marcar como coluna final">
                            <i class="pi pi-check-circle"></i>
                        </button>
                        <button @click="$emit('remove', index)" class="w-10 h-10 rounded-lg bg-surface-3 text-surface-muted hover:text-red-500 hover:bg-red-500/10 flex items-center justify-center transition-colors outline-none cursor-pointer">
                            <i class="pi pi-trash"></i>
                        </button>
                    </div>
                </template>
            </draggable>

            <button @click="$emit('add')" class="mt-3 w-full p-3 border-2 border-dashed border-primary-500/30 text-primary-600 dark:text-primary-400 bg-primary-500/5 hover:bg-primary-500/10 rounded-xl font-medium text-sm transition-colors outline-none cursor-pointer">
                <i class="pi pi-plus-circle mr-1"></i> Nova Coluna
            </button>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-2 w-full mt-4">
                <Button label="Cancelar" variant="text" severity="secondary" @click="$emit('update:visible', false)" class="font-medium flex-1 md:flex-none" />
                <Button label="Salvar Colunas" icon="pi pi-save" @click="$emit('save')" class="font-medium flex-1 md:flex-none" />
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import Button from "primevue/button";
import draggable from "vuedraggable";

defineProps({
    visible: Boolean,
    columns: Array
});

defineEmits(['update:visible', 'update:columns', 'add', 'remove', 'set-final', 'save']);
</script>
