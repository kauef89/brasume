<template>
    <Dialog
        v-model:visible="isVisible"
        modal
        class="w-[400px]"
        :closable="false"
        :draggable="false"
    >
        <template #header>
            <div class="flex items-center gap-2 text-red-600">
                <i class="pi pi-shield text-xl"></i>
                <h3 class="text-lg font-black">Autorização Necessária</h3>
            </div>
        </template>

        <div class="flex flex-col gap-5 py-2">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                A cancelar:
                <strong class="text-gray-900 dark:text-gray-100">{{
                    itemName
                }}</strong>
            </p>

            <div
                class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 p-4 rounded-xl"
            >
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="cancellationData.isLoss"
                        class="mt-1 w-5 h-5 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                    />
                    <div>
                        <span
                            class="block font-bold text-orange-800 dark:text-orange-400 text-sm"
                            >O item já foi produzido? (Desperdício)</span
                        >
                    </div>
                </label>

                <div
                    v-if="cancellationData.isLoss"
                    class="mt-4 animate-fade-in"
                >
                    <label
                        class="block text-xs font-bold text-orange-800 dark:text-orange-400 uppercase tracking-widest mb-1.5"
                        >Motivo da Perda</label
                    >
                    <select
                        v-model="cancellationData.reason"
                        class="w-full p-2.5 rounded-lg border-orange-200 bg-white dark:bg-gray-800 dark:border-gray-700 text-sm font-medium focus:ring-2 focus:ring-orange-500 outline-none transition-all"
                    >
                        <option value="" disabled>
                            Selecione um motivo...
                        </option>
                        <option value="Erro do Garçom">
                            Erro do Garçom/Apontamento
                        </option>
                        <option value="Erro da Cozinha">Erro da Cozinha</option>
                        <option value="Acidente">
                            Acidente (Caiu no chão)
                        </option>
                        <option value="Cliente Recusou">
                            Cliente recusou o prato
                        </option>
                    </select>
                </div>
            </div>

            <div>
                <label
                    class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5"
                    >Senha do Gestor</label
                >
                <input
                    type="password"
                    v-model="cancellationData.password"
                    class="w-full p-3 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-blue-500 outline-none text-center tracking-[0.5em] font-black text-lg"
                />
            </div>
        </div>

        <template #footer>
            <div class="flex gap-2 w-full">
                <Button
                    label="Cancelar"
                    variant="text"
                    severity="secondary"
                    class="flex-1 font-bold"
                    @click="isVisible = false"
                />
                <Button
                    label="Confirmar Estorno"
                    severity="danger"
                    class="flex-1 font-bold"
                    :disabled="!isValid"
                    @click="confirm"
                />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

const props = defineProps({
    visible: Boolean,
    itemName: String,
});

const emit = defineEmits(["update:visible", "confirm"]);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const cancellationData = ref({ isLoss: false, reason: "", password: "" });

// Reseta o form toda vez que o modal abre
watch(isVisible, (newVal) => {
    if (newVal)
        cancellationData.value = { isLoss: false, reason: "", password: "" };
});

const isValid = computed(() => {
    if (cancellationData.value.password.length < 4) return false;
    if (cancellationData.value.isLoss && !cancellationData.value.reason)
        return false;
    return true;
});

const confirm = () => {
    emit("confirm", { ...cancellationData.value });
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
