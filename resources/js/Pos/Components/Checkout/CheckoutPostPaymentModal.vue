<template>
    <Dialog
        :visible="visible"
        modal
        :closable="false"
        :style="{ width: '450px' }"
        class="bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-2xl border-0"
    >
        <template #header>
            <div
                class="flex flex-col items-center justify-center w-full pt-4 pb-2"
            >
                <div
                    class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-4"
                >
                    <i class="pi pi-check text-3xl font-black"></i>
                </div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white">
                    Conta Fechada!
                </h2>
                <p class="text-gray-500 text-sm mt-1">
                    O pedido foi pago na totalidade.
                </p>
            </div>
        </template>

        <div class="flex flex-col gap-4 py-4 px-2">
            <div class="flex flex-col gap-2">
                <label
                    class="font-bold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-widest"
                >
                    CPF/CNPJ na Nota (Opcional)
                </label>
                <input
                    v-model="cpf"
                    @input="handleCpfInput"
                    type="text"
                    placeholder="Deixe em branco para Consumidor Final"
                    maxlength="18"
                    class="w-full h-12 px-4 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-lg"
                />
            </div>
        </div>

        <template #footer>
            <div class="flex flex-col gap-2 w-full pb-2">
                <Button
                    label="Finalizar sem imprimir"
                    icon="pi pi-home"
                    size="large"
                    @click="handleAction('none')"
                    class="w-full font-bold !py-3"
                />
                <Button
                    label="Finalizar e imprimir Não Fiscal"
                    severity="secondary"
                    variant="outlined"
                    icon="pi pi-receipt"
                    @click="handleAction('non_fiscal')"
                    class="w-full font-bold"
                />
                <Button
                    label="Finalizar e imprimir Fiscal (NFC-e)"
                    severity="secondary"
                    variant="outlined"
                    icon="pi pi-file"
                    @click="handleAction('fiscal')"
                    class="w-full font-bold"
                />
                <Button
                    label="Imprimir Ambos"
                    severity="secondary"
                    variant="text"
                    icon="pi pi-print"
                    @click="handleAction('both')"
                    class="w-full font-bold text-sm"
                />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, defineProps, defineEmits } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

// 🚀 Import do formatador dinâmico
import { formatCpfCnpj } from "@/Utils/formatters";

defineProps({ visible: Boolean });
const emit = defineEmits(["action"]);

const cpf = ref("");

// 🚀 Atualiza o valor em tempo real enquanto o usuário digita
const handleCpfInput = (e) => {
    cpf.value = formatCpfCnpj(e.target.value);
};

const handleAction = (type) => {
    emit("action", { type, cpf: cpf.value });
};
</script>
