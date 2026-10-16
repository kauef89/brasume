<template>
    <BaseModal
        :modelValue="visible"
        @update:modelValue="$emit('update:visible', $event)"
        title="Contingência de Impressora"
        icon="pi-arrow-right-arrow-left"
        iconColor="warning"
        width="450px"
    >
        <div class="flex flex-col gap-4 py-2">
            <p class="text-sm text-surface-muted">
                Se a impressora <strong>{{ printer?.name }}</strong> estiver com problemas, você pode desviar temporariamente todas as impressões dela para outro equipamento.
            </p>

            <div class="flex flex-col gap-2">
                <label class="text-xs font-black uppercase tracking-widest text-surface-muted">Desviar impressões para:</label>
                <Select
                    v-model="selectedFallback"
                    :options="availablePrinters"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Selecione o destino de emergência..."
                    class="w-full"
                    showClear
                />
                <small class="text-surface-muted italic text-[10px]">Deixe em branco para voltar ao funcionamento normal.</small>
            </div>
        </div>

        <template #footer>
            <Button label="Cancelar" variant="text" severity="secondary" @click="$emit('update:visible', false)" />
            <Button label="Salvar Rota" icon="pi pi-save" severity="warning" @click="handleSave" />
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Button from 'primevue/button';
import Select from 'primevue/select';

const props = defineProps({ visible: Boolean, printer: Object, allPrinters: Array });
const emit = defineEmits(['update:visible', 'save']);

const selectedFallback = ref(null);

watch(() => props.visible, (isOpen) => {
    if (isOpen && props.printer) {
        selectedFallback.value = props.printer.fallback_printer_id;
    }
});

const availablePrinters = computed(() => {
    return props.allPrinters.filter(p => p.id !== props.printer?.id);
});

const handleSave = () => {
    emit('save', { printer_id: props.printer.id, fallback_printer_id: selectedFallback.value });
};
</script>
