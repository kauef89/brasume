<template>
    <Dialog
        v-model:visible="isVisible"
        modal
        class="w-[500px]"
        :closable="true"
        :draggable="false"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="bg-purple-100 dark:bg-purple-900/30 text-purple-600 p-2.5 rounded-lg"
                >
                    <i class="pi pi-arrows-alt text-xl"></i>
                </div>
                <div>
                    <h2
                        class="text-xl font-black text-gray-800 dark:text-gray-100"
                    >
                        Transformar Pedido #{{ order?.id }}
                    </h2>
                    <span
                        class="text-sm font-bold text-gray-500 uppercase tracking-widest"
                    >
                        Atual: {{ currentTypeLabel }}
                    </span>
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-4 mt-4">
            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">
                Para onde deseja transferir este pedido?
            </p>

            <div class="grid grid-cols-1 gap-3">
                <button
                    v-if="order?.type !== 'table'"
                    @click="selectTarget('table')"
                    class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all text-left"
                    :class="
                        targetType === 'table'
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                            : 'border-gray-200 dark:border-gray-800 hover:border-gray-300'
                    "
                >
                    <i class="pi pi-table text-2xl text-blue-500"></i>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-gray-200">
                            Transferir para Mesa
                        </h4>
                        <p class="text-xs text-gray-500">
                            Irá solicitar o número da mesa e remover a taxa de
                            entrega.
                        </p>
                    </div>
                </button>

                <button
                    v-if="order?.type !== 'counter'"
                    @click="selectTarget('counter')"
                    class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all text-left"
                    :class="
                        targetType === 'counter'
                            ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                            : 'border-gray-200 dark:border-gray-800 hover:border-gray-300'
                    "
                >
                    <i class="pi pi-shopping-bag text-2xl text-purple-500"></i>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-gray-200">
                            Mudar para Balcão/Comanda
                        </h4>
                        <p class="text-xs text-gray-500">
                            Transforma em pedido de retirada e remove taxa de
                            entrega.
                        </p>
                    </div>
                </button>

                <button
                    v-if="order?.type !== 'delivery'"
                    @click="selectTarget('delivery')"
                    class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all text-left"
                    :class="
                        targetType === 'delivery'
                            ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                            : 'border-gray-200 dark:border-gray-800 hover:border-gray-300'
                    "
                >
                    <i class="pi pi-motorcycle text-2xl text-red-500"></i>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-gray-200">
                            Converter para Delivery
                        </h4>
                        <p class="text-xs text-gray-500">
                            Redireciona ao Caixa para preenchimento de endereço
                            e taxa.
                        </p>
                    </div>
                </button>
            </div>

            <div v-if="targetType === 'table'" class="mt-2 animate-fade-in">
                <label
                    class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2"
                    >Digite o número da nova Mesa</label
                >
                <input
                    type="text"
                    v-model="targetIdentifier"
                    placeholder="Ex: 12"
                    class="w-full p-3 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-bold text-lg"
                />
            </div>

            <div v-if="targetType === 'counter'" class="mt-2 animate-fade-in">
                <label
                    class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2"
                    >Nome / Número da Comanda</label
                >
                <input
                    type="text"
                    v-model="targetIdentifier"
                    placeholder="Ex: João 45"
                    class="w-full p-3 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-purple-500 outline-none transition-all font-bold text-lg"
                />
            </div>
        </div>

        <template #footer>
            <div class="flex gap-2 w-full mt-4">
                <Button
                    label="Cancelar"
                    variant="text"
                    severity="secondary"
                    class="flex-1 font-bold"
                    @click="isVisible = false"
                />
                <Button
                    label="Confirmar Transferência"
                    severity="help"
                    class="flex-1 font-bold"
                    :disabled="!canSubmit"
                    @click="confirmTransformation"
                />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

const props = defineProps({ visible: Boolean, order: Object });
const emit = defineEmits(["update:visible", "transform"]);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const targetType = ref("");
const targetIdentifier = ref("");

// Resetar estado quando muda de ordem
watch(
    () => props.order,
    () => {
        targetType.value = "";
        targetIdentifier.value = "";
    },
);

const currentTypeLabel = computed(() => {
    const types = { table: "Mesa", counter: "Balcão", delivery: "Delivery" };
    return props.order ? types[props.order.type] : "";
});

const selectTarget = (type) => {
    targetType.value = type;
    targetIdentifier.value = ""; // Limpa o input extra
};

// Regras de validação do botão
const canSubmit = computed(() => {
    if (!targetType.value) return false;
    if (
        (targetType.value === "table" || targetType.value === "counter") &&
        targetIdentifier.value.trim() === ""
    )
        return false;
    return true;
});

const confirmTransformation = () => {
    emit("transform", {
        order: props.order,
        newType: targetType.value,
        identifier: targetIdentifier.value,
    });
    isVisible.value = false;
};
</script>

<style scoped>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in {
    animation: fade-in 0.2s ease-out forwards;
}
</style>
