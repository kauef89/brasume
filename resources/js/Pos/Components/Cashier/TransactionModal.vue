<template>
    <Dialog
        :visible="visible"
        modal
        :header="type === 'in' ? 'Suprimento (Entrada)' : 'Sangria (Retirada)'"
        :style="{ width: '400px' }"
        @update:visible="$emit('update:visible', $event)"
        class="bg-white dark:bg-gray-900 rounded-2xl"
    >
        <div class="flex flex-col gap-4 py-2">
            <div class="p-3 rounded-lg flex items-start gap-3" :class="type === 'in' ? 'bg-blue-50 text-blue-800' : 'bg-orange-50 text-orange-800'">
                <i class="pi mt-0.5" :class="type === 'in' ? 'pi-info-circle text-blue-500' : 'pi-exclamation-triangle text-orange-500'"></i>
                <p class="text-sm font-medium">
                    {{ type === 'in' ? 'Use para adicionar moedas ou troco na gaveta.' : 'Use para retirar excesso de dinheiro físico do caixa para o cofre.' }}
                </p>
            </div>

            <div>
                <label class="block text-sm font-black text-gray-500 uppercase tracking-widest mb-2">Valor (R$)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-gray-400">R$</span>
                    <input type="number" v-model.number="amount" placeholder="0.00" class="w-full text-xl font-bold text-right p-3 pl-12 rounded-xl border border-gray-300 focus:border-blue-500 bg-gray-50 outline-none" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-black text-gray-500 uppercase tracking-widest mb-2">Motivo / Descrição</label>
                <input type="text" v-model="description" placeholder="Ex: Reforço de troco, Retirada para o banco..." class="w-full text-base p-3 rounded-xl border border-gray-300 focus:border-blue-500 bg-gray-50 outline-none" />
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2 pt-2">
                <Button label="Cancelar" variant="text" severity="secondary" @click="$emit('update:visible', false)" />
                <Button label="Confirmar Transação" icon="pi pi-check" :severity="type === 'in' ? 'info' : 'warning'" @click="handleConfirm" :disabled="!isValid" />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

const props = defineProps({ visible: Boolean, type: String });
const emit = defineEmits(['update:visible', 'confirm']);

const amount = ref(null);
const description = ref('');

watch(() => props.visible, (val) => {
    if (val) { amount.value = null; description.value = ''; }
});

const isValid = computed(() => amount.value > 0 && description.value.trim().length > 2);

const handleConfirm = () => {
    emit('confirm', { type: props.type, amount: amount.value, description: description.value });
};
</script>
