<template>
    <div class="flex flex-col gap-4 shrink-0">
        <!-- PASSO 1: NOME (Aceita 1 ou 'guest_name') -->
        <div v-if="currentStep === 1 || currentStep === 'guest_name'" class="flex flex-col gap-4 animate-in slide-in-from-right-2">
            <FormField label="Como podemos te chamar?" required :is-glass="isCustomerMode">
                <input
                    v-model="name"
                    type="text"
                    placeholder="Ex: Maria Souza"
                    class="w-full h-14 px-4 rounded-xl font-bold text-sm outline-none transition-all"
                    :class="isCustomerMode ? 'bg-white/10 border-none text-white focus:bg-white/15' : 'bg-surface-2 border border-surface-border text-surface-text focus:border-primary-500'"
                    @input="handleNameInput"
                    @keyup.enter="isNameValid ? handleAdvance() : null"
                    autofocus
                />
            </FormField>
            <button @click="handleAdvance" :disabled="!isNameValid" class="c-btn-primary mt-2">
                Próximo <i class="pi pi-arrow-right text-xs"></i>
            </button>
        </div>

        <!-- PASSO 2: TELEFONE (Aceita 2 ou 'guest_phone') -->
        <div v-else-if="currentStep === 2 || currentStep === 'guest_phone'" class="flex flex-col gap-4 animate-in slide-in-from-right-2">
            <FormField label="Seu WhatsApp" :required="reqWhatsapp" :is-glass="isCustomerMode">
                <input
                    v-model="phone"
                    type="tel"
                    inputmode="numeric"
                    maxlength="15"
                    placeholder="(00) 00000-0000"
                    class="w-full h-14 px-4 rounded-xl font-bold text-sm outline-none transition-all"
                    :class="isCustomerMode ? 'bg-white/10 border-none text-white focus:bg-white/15' : 'bg-surface-2 border border-surface-border text-surface-text focus:border-primary-500'"
                    @input="handlePhoneInput"
                    @keyup.enter="submitRegistration"
                    autofocus
                />
            </FormField>
            <SecureActionButton action-code="guest_register" @secure-click="submitRegistration" :disabled="!isPhoneValid" :is-loading="loading" severity="primary" custom-class="c-btn-primary mt-2 w-full h-11" >
                <i v-if="loading" class="pi pi-spinner pi-spin"></i>
                <span>{{ loading ? 'Processando...' : 'Continuar para Pedido' }}</span>
            </SecureActionButton>
        </div>
    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';

import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { formatPhone } from "@/Utils/formatters";
import FormField from "@/Components/UI/FormField.vue";
import { useToast } from "primevue/usetoast";

const props = defineProps({
    isCustomerMode: { type: Boolean, default: false },
    step: { type: [Number, String], default: 1 },
    initialName: { type: String, default: "" },
    initialPhone: { type: String, default: "" }
});

const emit = defineEmits(['registered', 'update:step', 'update:name', 'update:phone', 'advance']);

const page = usePage();
const toast = useToast();
const settings = computed(() => page.props.companySettings || {});
const reqWhatsapp = computed(() => settings.value.require_whatsapp ?? true);

const currentStep = computed(() => props.step);

// Restaura os dados digitados anteriormente
const name = ref(props.initialName || "");
const phone = ref(props.initialPhone || "");
const loading = ref(false);

watch(() => props.initialName, (val) => { if (val) name.value = val; });
watch(() => props.initialPhone, (val) => { if (val) phone.value = val; });

const handleNameInput = () => {
    emit('update:name', name.value);
};

const handlePhoneInput = (e) => {
    let val = e?.target?.value || e;
    if (typeof val === 'string') {
        phone.value = formatPhone(val);
        if (e?.target) e.target.value = phone.value;
    }
    emit('update:phone', phone.value);
};

const isNameValid = computed(() => name.value.trim().length >= 3);
const isPhoneValid = computed(() => {
    const raw = phone.value.replace(/\D/g, '');
    return reqWhatsapp.value ? raw.length >= 10 : true;
});

const handleAdvance = () => {
    if (isNameValid.value) {
        emit('advance');
    }
};

const submitRegistration = () => {
    if (!isPhoneValid.value) return;
    loading.value = true;

    router.post(route('customer.auth.guest-register'), {
        name: name.value,
        phone: phone.value
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            loading.value = false;
            emit('registered');
        },
        onError: (errors) => {
            loading.value = false;
            const errorMsg = errors.guest || Object.values(errors)[0] || 'Verifique os dados e tente novamente.';
            toast.add({ severity: 'error', summary: 'Erro', detail: errorMsg, life: 5000 });
        }
    });
};
</script>
