<template>
    <div class="flex flex-col gap-3 p-4 bg-surface-3 rounded-2xl">
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-black uppercase tracking-wider text-surface-muted flex items-center gap-1.5">
                <i class="pi pi-bolt text-amber-500"></i> {{ simpleMode ? "Identificação do Cliente" : "Cadastro Rápido" }}
            </span>
            <span v-if="isCounter && !simpleMode" class="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                WhatsApp opcional
            </span>
        </div>

        <!-- Campo Nome -->
        <div class="flex flex-col gap-1">
            <label class="text-[11px] font-bold text-surface-muted uppercase">Nome do Cliente *</label>
            <input
                ref="nameInputRef"
                v-model="name"
                type="text"
                placeholder="Ex: João Silva"
                class="w-full h-11 px-3.5 rounded-xl bg-surface-1 border border-surface-border text-surface-text text-sm font-bold placeholder:text-surface-muted/50 outline-none focus:border-primary-500 transition-colors"
                @keydown.enter.prevent="handleEnterKey"
            />
        </div>

        <!-- Campo WhatsApp -->
        <div v-if="!simpleMode" class="flex flex-col gap-1">
            <div class="flex items-center justify-between">
                <label class="text-[11px] font-bold text-surface-muted uppercase">
                    WhatsApp {{ isCounter ? '(Opcional)' : '*' }}
                </label>
            </div>
            <input
                v-model="phone"
                v-maska="'(##) #####-####'"
                type="tel"
                :placeholder="isCounter ? '(00) 00000-0000 (Opcional)' : '(00) 00000-0000'"
                class="w-full h-11 px-3.5 rounded-xl bg-surface-2 border border-surface-border text-surface-text text-sm font-bold placeholder:text-surface-muted/50 outline-none focus:border-primary-500 transition-colors"
                @keydown.enter.prevent="handleSubmit"
            />
        </div>

        <button
            @click="handleSubmit"
            type="button"
            :disabled="!isValid || isSubmitting"
            class="w-full h-12 mt-1 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 outline-none border-none"
            :class="isValid && !isSubmitting
                ? 'bg-primary-500 hover:bg-primary-600 text-white cursor-pointer active:scale-98'
                : 'grayscale text-surface-muted cursor-not-allowed'"
        >
            <i class="pi" :class="isSubmitting ? 'pi-spinner pi-spin' : 'pi-check'"></i>
            <span>{{ isSubmitting ? 'Salvando...' : 'Confirmar e Usar' }}</span>
        </button>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { vMaska } from "maska/vue";

const props = defineProps({
    companySettings: { type: Object, default: () => ({}) },
    customers: { type: Array, default: () => [] },
    orderType: { type: String, default: 'delivery' },
    allowEmptyPhone: { type: Boolean, default: false },
    simpleMode: { type: Boolean, default: false }
});

const emit = defineEmits(['success']);

const toast = useToast();
const name = ref('');
const phone = ref('');
const isSubmitting = ref(false);
const nameInputRef = ref(null);

const isCounter = computed(() => props.orderType === 'counter' || props.allowEmptyPhone);

const rawPhone = computed(() => String(phone.value || '').replace(/\D/g, ''));

const isValid = computed(() => {
    const hasName = name.value.trim().length >= 2;
    if (!hasName) return false;

    if (props.simpleMode) {
        return true;
    }

    // No balcão: Se vazio, é válido. Se preenchido, exige 10 ou 11 dígitos.
    if (isCounter.value) {
        if (!rawPhone.value) return true;
        return rawPhone.value.length >= 10 && rawPhone.value.length <= 11;
    }

    // No delivery: WhatsApp é sempre obrigatório
    return rawPhone.value.length >= 10 && rawPhone.value.length <= 11;
});

const handleEnterKey = () => {
    if (props.simpleMode || (isCounter.value && !phone.value)) {
        handleSubmit();
    }
};

const handleSubmit = () => {
    if (!isValid.value || isSubmitting.value) return;

    isSubmitting.value = true;
    const cleanPhoneVal = rawPhone.value ? rawPhone.value : null;

    const createdCustomer = {
        id: `temp_${Date.now()}`,
        name: name.value.trim(),
        phone: cleanPhoneVal,
        is_temporary: true
    };

    toast.add({
        severity: 'success',
        summary: 'Cliente Definido',
        detail: `${createdCustomer.name} vinculado ao pedido.`,
        life: 3000
    });

    emit('success', createdCustomer);
    isSubmitting.value = false;
};

onMounted(() => {
    if (nameInputRef.value) {
        nameInputRef.value.focus();
    }
});
</script>
