<template>
    <AdaptiveModal v-model:visible="isVisible">
        <div class="flex items-center gap-3 mb-4">
            <div class="p-2.5 rounded-lg shrink-0" :class="`bg-${iconColor}-500/10 text-${iconColor}-600`">
                <i class="pi fa-shield-halved text-xl"></i>
            </div>
            <h2 class="text-xl font-black text-surface-text leading-tight">{{ modalTitle }}</h2>
        </div>

        <div class="flex flex-col gap-5 py-2">
            <p class="text-sm text-stone-600 dark:text-stone-400" v-if="itemName">
                Alvo da operação: <strong class="text-stone-900 dark:text-stone-100">{{ itemName }}</strong>
            </p>

            <div class="flex flex-col gap-2">
                <form @submit.prevent="confirm" class="flex flex-col gap-2">
                    <label class="block text-xs font-medium text-stone-500 uppercase tracking-widest">Senha do Gestor</label>

                    <InputText
                        type="tel"
                        autocomplete="off"
                        data-1p-ignore="true"
                        data-lpignore="true"
                        v-model="password"
                        class="w-full text-center tracking-[0.5em] font-black text-lg"
                        style="-webkit-text-security: disc;"
                        autofocus
                    />
                    <button type="submit" class="hidden"></button>
                </form>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 w-full mt-4">
            <Button label="Cancelar" variant="text" severity="secondary" class="flex-1 font-medium" @click="isVisible = false" />
            <Button
                :label="confirmLabel"
                :severity="iconColor"
                class="flex-1 font-medium"
                :disabled="password.length < 4"
                @click="confirm"
            />
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";

const props = defineProps({ visible: Boolean, itemName: String, actionType: { type: String, default: 'cancel' } });
const emit = defineEmits(["update:visible", "confirm"]);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const password = ref("");

watch(isVisible, (newVal) => { if (newVal) password.value = ""; });

const modalTitle = computed(() => {
    if (props.actionType === 'edit') return 'Autorização para Edição';
    if (props.actionType === 'cash_out') return 'Autorização de Sangria';
    if (props.actionType === 'transfer') return 'Autorização de Transferência';
    if (props.actionType === 'close_shift_override') return 'Diferença de Caixa';
    return 'Autorização Necessária';
});

const iconColor = computed(() => ['edit', 'transfer'].includes(props.actionType) ? 'warning' : 'danger');

const confirmLabel = computed(() => {
    if (props.actionType === 'edit') return 'Confirmar Edição';
    if (props.actionType === 'cash_out') return 'Liberar Sangria';
    if (props.actionType === 'transfer') return 'Liberar Transferência';
    if (props.actionType === 'close_shift_override') return 'Forçar Fechamento';
    return 'Confirmar Operação';
});

const confirm = () => {
    emit("confirm", { password: password.value });
    isVisible.value = false;
};
</script>
