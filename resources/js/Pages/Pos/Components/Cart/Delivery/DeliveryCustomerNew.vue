<template>
    <div class="flex flex-col gap-3 p-4 bg-surface-3/40 rounded-2xl border border-surface-border animate-in fade-in duration-200">

        <!-- 1. IDENTIFICAÇÃO DE CLIENTE JÁ CADASTRADO -->
        <div v-if="serproStep === 'cpf_exists'" class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex flex-col gap-3 animate-in fade-in">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <i class="pi pi-user-check text-lg"></i>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">Cliente Já Cadastrado</span>
                    <span class="text-base font-black text-surface-text leading-tight truncate mt-0.5">
                        {{ resolvedExistingCustomer?.name || 'Cliente Localizado' }}
                    </span>
                    <span class="text-xs font-medium text-surface-muted mt-0.5">
                        {{ formatPhone(resolvedExistingCustomer?.phone) || formatCpfCnpj(form.cpf) }}
                    </span>
                </div>
            </div>

            <p class="text-xs text-surface-muted leading-relaxed">
                Este CPF já possui cadastro no sistema. Deseja vinculá-lo diretamente a este pedido?
            </p>

            <div class="flex flex-col sm:flex-row items-center gap-2 pt-1 border-t border-amber-500/20">
                <button
                    type="button"
                    @click="chooseExistingCustomer"
                    class="w-full sm:flex-1 h-11 bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider rounded-xl border-none cursor-pointer shadow-md transition-all flex items-center justify-center gap-2"
                >
                    <i class="pi pi-check text-xs"></i>
                    <span>Vincular ao Pedido</span>
                </button>
                <button
                    type="button"
                    @click="serproStep = 'cpf_input'"
                    class="w-full sm:w-auto h-11 px-4 bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text font-black text-xs uppercase tracking-wider rounded-xl border border-surface-border cursor-pointer transition-colors"
                >
                    Outro CPF
                </button>
            </div>
        </div>

        <!-- 2. ALERTA DE MENOR DE IDADE -->
        <div v-if="isMinorError" class="p-3 bg-danger-500/10 border border-danger-500/20 rounded-xl flex items-center gap-2.5 text-danger-400 animate-in fade-in">
            <i class="pi pi-ban text-base shrink-0"></i>
            <span class="text-xs font-bold leading-tight">Cadastro bloqueado: Este estabelecimento não permite menores de 18 anos.</span>
        </div>

        <!-- 3. FLUXO SERPRO MAGIC (RECEITA FEDERAL) -->
        <template v-if="isSerproMagic && serproStep !== 'cpf_exists'">
            <div v-if="serproStep === 'cpf_input'" class="flex flex-col gap-2">
                <label class="block text-[10px] font-black uppercase text-surface-muted">Consultar CPF na Receita (Serpro) *</label>
                <div class="flex gap-2">
                    <input
                        :value="form.cpf"
                        @input="formatCpfInput"
                        @keydown.enter.prevent="isCpfValid && !isLoadingSerpro ? lookupSerpro() : null"
                        type="text"
                        maxlength="14"
                        placeholder="000.000.000-00"
                        class="flex-1 h-11 px-3.5 bg-surface-2 border border-surface-border rounded-xl text-sm font-black text-surface-text outline-none focus:border-primary-500 tracking-wider"
                    />
                    <button
                        type="button"
                        @click="lookupSerpro"
                        :disabled="!isCpfValid || isLoadingSerpro"
                        class="px-4 h-11 bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider rounded-xl border-none cursor-pointer transition-all flex items-center gap-2 disabled:opacity-40"
                    >
                        <i v-if="isLoadingSerpro" class="pi pi-spinner pi-spin"></i>
                        <i v-else class="pi pi-search"></i>
                        <span class="hidden sm:inline">Consultar</span>
                    </button>
                </div>
                <div class="flex justify-end">
                    <button
                        type="button"
                        @click="serproStep = 'manual_fallback'"
                        class="text-[10px] font-bold text-surface-muted hover:text-primary-smart underline bg-transparent border-none cursor-pointer"
                    >
                        Preencher manualmente sem consulta
                    </button>
                </div>
            </div>

            <div v-else-if="serproStep === 'confirm_data'" class="flex flex-col gap-3 animate-in fade-in">
                <div class="p-3.5 rounded-xl bg-success-500/10 border border-success-500/20 flex items-start justify-between">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase text-success-600 dark:text-success-400">Identidade Confirmada na Receita</span>
                        <span class="text-sm font-black text-surface-text mt-0.5 leading-tight">{{ form.name }}</span>
                        <span v-if="form.birth_date" class="text-xs text-surface-muted mt-0.5">Nascimento: {{ formatDate(form.birth_date) }}</span>
                    </div>
                    <button
                        type="button"
                        @click="serproStep = 'cpf_input'"
                        class="text-[10px] font-black uppercase text-surface-muted hover:text-surface-text bg-transparent border-none cursor-pointer"
                    >
                        Trocar
                    </button>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-surface-muted mb-1">
                        WhatsApp {{ reqWhatsapp ? '*' : '(Opcional)' }}
                    </label>
                    <input
                        :value="form.phone"
                        @input="formatPhoneInput($event.target.value)"
                        :required="reqWhatsapp"
                        type="text"
                        maxlength="20"
                        placeholder="(00) 00000-0000"
                        class="w-full h-11 px-3.5 bg-surface-2 border border-surface-border rounded-xl text-sm font-semibold text-surface-text outline-none focus:border-primary-500"
                    />
                </div>
            </div>
        </template>

        <!-- 4. FORMULÁRIO MANUAL (COMPATÍVEL COM CPF_REQUIRED) -->
        <div v-if="(!isSerproMagic || serproStep === 'manual_fallback') && serproStep !== 'cpf_exists'" class="flex flex-col gap-3">
            <div v-if="isSerproMagic" class="flex items-center justify-between pb-1 border-b border-surface-border/40">
                <span class="text-[10px] font-black uppercase text-surface-muted">Preenchimento Manual</span>
                <button
                    type="button"
                    @click="serproStep = 'cpf_input'"
                    class="text-[10px] font-bold text-primary-600 hover:underline bg-transparent border-none cursor-pointer"
                >
                    Voltar para Consulta Serpro
                </button>
            </div>

            <!-- Campo Nome -->
            <div>
                <label class="block text-[10px] font-black uppercase text-surface-muted mb-1">Nome Completo *</label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    placeholder="Nome do cliente"
                    class="w-full h-11 px-3.5 bg-surface-2 border border-surface-border rounded-xl text-sm font-semibold text-surface-text outline-none focus:border-primary-500"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Campo WhatsApp -->
                <div>
                    <label class="block text-[10px] font-black uppercase text-surface-muted mb-1">
                        WhatsApp {{ reqWhatsapp ? '*' : '(Opcional)' }}
                    </label>
                    <input
                        v-model="form.phone"
                        @input="formatPhoneInput"
                        :required="reqWhatsapp"
                        type="text"
                        maxlength="15"
                        placeholder="(00) 00000-0000"
                        class="w-full h-11 px-3.5 bg-surface-2 border border-surface-border rounded-xl text-sm font-semibold text-surface-text outline-none focus:border-primary-500"
                        :class="{ 'border-danger-500': form.phone && !isPhoneValid }"
                    />
                    <span v-if="form.phone && !isPhoneValid" class="text-[10px] text-danger-500 font-bold mt-0.5 block">
                        Informe o DDD e número válido
                    </span>
                </div>

                <!-- Campo Data de Nascimento (se exigido) -->
                <div v-if="reqBirthDate">
                    <label class="block text-[10px] font-black uppercase text-surface-muted mb-1">Data de Nascimento *</label>
                    <input
                        v-model="form.birth_date"
                        type="date"
                        required
                        class="w-full h-11 px-3.5 bg-surface-2 border border-surface-border rounded-xl text-sm font-semibold text-surface-text outline-none focus:border-primary-500"
                    />
                </div>

                <!-- Campo CPF: Rótulo reflete a regra de negócio do tenant -->
                <div>
                    <label class="block text-[10px] font-black uppercase text-surface-muted mb-1">
                        CPF {{ isCpfRequired ? '*' : '(Opcional)' }}
                    </label>
                    <input
                        v-model="form.cpf"
                        @input="formatCpfInput"
                        type="text"
                        maxlength="14"
                        placeholder="000.000.000-00"
                        class="w-full h-11 px-3.5 bg-surface-2 border border-surface-border rounded-xl text-sm font-semibold text-surface-text outline-none focus:border-primary-500"
                        :class="{ 'border-danger-500': form.cpf && !isCpfValid }"
                    />
                    <span v-if="form.cpf && !isCpfValid" class="text-[10px] text-danger-500 font-bold mt-0.5 block">
                        CPF incompleto ou inválido
                    </span>
                </div>
            </div>
        </div>

        <!-- 5. BOTÃO DE SUBMISSÃO -->
        <button
            v-if="serproStep !== 'cpf_exists' && (serproStep !== 'cpf_input' || !isSerproMagic)"
            type="button"
            @click="submit"
            :disabled="!isFormValid || isMinorError || isSubmitting"
            class="w-full h-11 bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md transition-all flex items-center justify-center gap-2 border-none cursor-pointer disabled:opacity-40 mt-1"
        >
            <i v-if="isSubmitting" class="pi pi-spinner pi-spin text-xs"></i>
            <span>{{ isSubmitting ? 'Cadastrando...' : 'Cadastrar e Avançar' }}</span>
        </button>

    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useCustomerRegistration } from "@/Pages/Customer/Composables/useCustomerRegistration";
import { formatPhone, formatCpfCnpj } from '@/Utils/formatters';

const props = defineProps({
    companySettings: { type: Object, default: () => ({}) },
    customers: { type: Array, default: () => [] },
    initialName: { type: String, default: '' }
});

const emit = defineEmits(['success']);
const toast = useToast();
const isSubmitting = ref(false);

const registrationMode = computed(() => props.companySettings.customer_registration_mode || 'basic');
const isSerproMagic = computed(() => registrationMode.value === 'serpro_magic');
const isCpfRequired = computed(() => registrationMode.value === 'cpf_required');

const {
    form,
    serproStep,
    isLoadingSerpro,
    isCpfValid,
    isPhoneValid,
    isFormValid,
    isMinorError,
    existingAccount,
    reqWhatsapp,
    reqBirthDate,
    formatCpfInput,
    formatPhoneInput,
    lookupSerpro,
    resetForm
} = useCustomerRegistration(registrationMode);

// 🚀 SONDA REATIVA NO CONSOLE (Identifica exatamente qual campo está barrando)
watch(
    [
        () => form.value.name,
        () => form.value.phone,
        () => form.value.cpf,
        () => form.value.birth_date,
        isFormValid,
        isMinorError,
        isSubmitting
    ],
    () => {
        console.log('📍 [DIAGNÓSTICO CADASTRO NOVO CLIENTE]', {
            nomePreenchido: Boolean(form.value.name && form.value.name.trim().length >= 2),
            telefoneValido: isPhoneValid.value,
            cpfDigitado: form.value.cpf,
            cpfValido: isCpfValid.value,
            cpfObrigatorio: isCpfRequired.value,
            dataNascimentoObrigatoria: reqBirthDate.value,
            dataNascimentoPreenchida: Boolean(form.value.birth_date),
            isMinorError: isMinorError.value,
            modoDoTenant: registrationMode.value,
            FORMULARIO_VALIDO: isFormValid.value,
            BOTAO_DESABILITADO: !isFormValid.value || isMinorError.value || isSubmitting.value
        });
    },
    { deep: true, immediate: true }
);

watch(() => props.initialName, (val) => {
    if (val && !form.value.name) {
        form.value.name = val;
    }
}, { immediate: true });

const resolvedExistingCustomer = computed(() => {
    if (serproStep.value !== 'cpf_exists') return null;

    const rawCpf = String(form.value.cpf || '').replace(/\D/g, '');

    if (existingAccount.value?.customer) return existingAccount.value.customer;
    if (existingAccount.value?.data?.id) return existingAccount.value.data;
    if (existingAccount.value?.id) return existingAccount.value;

    if (rawCpf && props.customers?.length > 0) {
        const found = props.customers.find(c => {
            const cCpf = String(c.cpf || '').replace(/\D/g, '');
            return cCpf === rawCpf;
        });
        if (found) return found;
    }

    return {
        id: existingAccount.value?.id || null,
        name: existingAccount.value?.name || form.value.name || 'Cliente Cadastrado',
        phone: existingAccount.value?.phone || form.value.phone || '',
        cpf: form.value.cpf
    };
});

const chooseExistingCustomer = () => {
    if (!resolvedExistingCustomer.value) return;

    toast.add({
        severity: 'info',
        summary: 'Cliente Vinculado',
        detail: `${resolvedExistingCustomer.value.name} foi selecionado para o pedido.`,
        life: 3000
    });

    emit('success', resolvedExistingCustomer.value);
    resetForm();
};

const submit = () => {
    if (!isFormValid.value || isMinorError.value || isSubmitting.value) return;

    isSubmitting.value = true;

    router.post(route('operations.customers.store'), form.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (res) => {
            const created = res.props.flash?.createdCustomer || { ...form.value };
            toast.add({
                severity: 'success',
                summary: 'Cliente Cadastrado',
                detail: `${created.name} pronto para delivery.`,
                life: 3000
            });
            emit('success', created);
            resetForm();
        },
        onError: (err) => {
            const msg = Object.values(err)[0] || 'Verifique as informações do formulário.';
            toast.add({ severity: 'error', summary: 'Erro no Cadastro', detail: msg, life: 5000 });
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

const formatDate = (isoString) => {
    if (!isoString) return '';
    const parts = isoString.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    return isoString;
};
</script>
