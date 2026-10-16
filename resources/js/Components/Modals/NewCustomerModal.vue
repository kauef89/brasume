<template>
    <AdaptiveModal v-model:visible="isVisible">
        <div class="flex items-center gap-3 mb-4">
            <h2 class="text-xl font-black text-surface-text leading-tight">Cadastrar Nova Conta</h2>
        </div>

        <div class="flex flex-col gap-4 pt-2">
            <!-- 🚨 ALERTA DE MENOR DE IDADE -->
            <div v-if="isMinorError" class="p-3 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center gap-3 text-red-600 dark:text-red-400">
                <i class="pi fa-exclamation-triangle text-xl"></i>
                <div class="flex flex-col leading-tight">
                    <span class="font-black text-sm uppercase tracking-widest">Acesso Restrito (+18)</span>
                    <span class="text-xs font-medium opacity-80 mt-0.5">O cadastro não é permitido para menores de idade neste estabelecimento.</span>
                </div>
            </div>

            <template v-if="customerRegistrationMode === 'serpro_magic'">

                <div v-if="serproStep === 'cpf_input'" class="flex flex-col gap-3">
                    <FormField label="Seu CPF" required>
                        <InputText v-model="form.cpf" @input="formatCpfInput" placeholder="000.000.000-00" class="w-full h-12 font-medium text-center tracking-widest text-lg" @keyup.enter="isCpfValid ? lookupSerpro() : null" />
                    </FormField>

                    <Button label="Buscar Meus Dados" icon="pi pi-search" @click="lookupSerpro" :loading="isLoadingSerpro" :disabled="!isCpfValid" class="mt-2 font-black h-14 text-sm uppercase tracking-widest shadow-md" />
                </div>

                <div v-else-if="serproStep === 'cpf_exists'" class="flex flex-col gap-4 animate-in fade-in slide-in-from-right-4 duration-300">
                    <div class="flex flex-col items-center justify-center text-center gap-2 p-4 bg-primary-500/10 rounded-2xl border border-primary-500/20">
                        <div class="w-12 h-12 rounded-full bg-primary-500 text-white flex items-center justify-center text-xl shadow-md mb-1">
                            <i class="pi fa-user"></i>
                        </div>
                        <h3 class="font-black text-lg text-primary-700 dark:text-primary-300 leading-tight">CPF já cadastrado!</h3>
                        <p class="text-sm font-medium text-primary-600/80 dark:text-primary-400/80">
                            Encontramos uma conta vinculada a este CPF no nosso sistema.
                        </p>

                        <div class="flex flex-col w-full gap-2 mt-2 bg-surface-1 p-3 rounded-xl border border-surface-border text-left">
                            <div class="flex items-center gap-3">
                                <i class="pi fa-id-card text-surface-muted"></i>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Nome do Titular</span>
                                    <span class="text-sm font-medium text-surface-text">{{ existingAccount?.masked_name }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3" v-if="existingAccount?.masked_email">
                                <i class="pi fa-envelope text-surface-muted"></i>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">E-mail vinculado</span>
                                    <span class="text-sm font-medium text-surface-text">{{ existingAccount?.masked_email }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3" v-if="existingAccount?.masked_phone">
                                <i class="fa-brands fa-whatsapp text-surface-muted"></i>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">WhatsApp vinculado</span>
                                    <span class="text-sm font-medium text-surface-text">{{ existingAccount?.masked_phone }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 w-full mt-2">
                        <button @click="isVisible = false" class="w-full h-12 rounded-xl bg-primary-500 text-white font-black text-xs uppercase tracking-widest hover:bg-primary-600 transition-colors shadow-sm outline-none cursor-pointer border-none">
                            Fazer Login
                        </button>
                        <button v-if="hasSupport" @click="handleSupportHelp" class="w-full h-12 rounded-xl border-2 border-surface-border bg-surface-2 text-surface-muted font-medium text-xs uppercase tracking-widest hover:bg-surface-3 transition-colors outline-none flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-brands fa-whatsapp text-success-500 text-lg"></i> Preciso de Ajuda
                        </button>
                        <button @click="resetForm(form.name)" class="w-full h-10 text-[10px] font-medium text-surface-muted hover:text-surface-text uppercase tracking-widest outline-none mt-1 cursor-pointer border-none bg-transparent">
                            Digitar outro CPF
                        </button>
                    </div>
                </div>

                <div v-else-if="serproStep === 'confirm_data'" class="flex flex-col gap-3 animate-in fade-in">
                    <div class="p-3 bg-success-500/10 border border-success-500/20 rounded-xl flex items-start gap-3">
                        <i class="pi pi-check-circle text-success-600 mt-1"></i>
                        <div>
                            <span class="block text-[10px] font-black text-success-600 uppercase tracking-widest">Identidade Confirmada (Receita Federal)</span>
                            <span class="block font-black text-lg text-surface-text mt-1">{{ form.name }}</span>
                            <span class="block text-sm font-medium text-surface-muted mt-1">Nasc: {{ formatDate(form.birth_date) }}</span>
                        </div>
                    </div>

                    <FormField label="WhatsApp" :required="reqWhatsapp">
                        <InputText v-model="form.phone" @input="formatPhoneInput" placeholder="(00) 00000-0000" class="w-full h-12 font-medium" />
                    </FormField>
                </div>

                <div v-else-if="serproStep === 'manual_fallback'" class="flex flex-col gap-3 animate-in fade-in">
                    <div class="flex items-start gap-2 text-warning-600 mb-2 bg-warning-500/10 p-3 rounded-lg border border-warning-500/20">
                        <i class="pi pi-exclamation-circle mt-0.5"></i>
                        <p class="text-xs font-medium leading-tight">Por restrições cadastrais, preencha os dados manualmente:</p>
                    </div>

                    <FormField label="Nome Completo" required>
                        <InputText v-model="form.name" placeholder="Ex: Maria Silva" class="w-full h-12 font-medium" />
                    </FormField>

                    <FormField label="WhatsApp" :required="reqWhatsapp">
                        <InputText v-model="form.phone" @input="formatPhoneInput" placeholder="(00) 00000-0000" class="w-full h-12 font-medium" />
                    </FormField>

                    <FormField v-if="reqBirthDate || !allowMinors" label="Data de Nascimento" :required="reqBirthDate || !allowMinors">
                        <input type="date" v-model="form.birth_date" class="w-full h-12 px-3 rounded-xl border border-surface-border bg-surface-1 text-surface-text text-sm font-medium outline-none focus:border-primary-500 transition-colors" />
                    </FormField>
                </div>
            </template>

            <template v-else>
                <div class="flex flex-col gap-3">
                    <FormField label="Nome Completo" required>
                        <InputText v-model="form.name" placeholder="Ex: João da Silva" class="w-full h-12 font-medium" />
                    </FormField>

                    <FormField v-if="customerRegistrationMode === 'cpf_required' || customerRegistrationMode === 'customer_only'" label="CPF" required>
                        <InputText v-model="form.cpf" @input="formatCpfInput" placeholder="000.000.000-00" class="w-full h-12 font-medium" />
                    </FormField>

                    <FormField label="WhatsApp" :required="reqWhatsapp">
                        <InputText v-model="form.phone" @input="formatPhoneInput" placeholder="(00) 00000-0000" class="w-full h-12 font-medium" />
                    </FormField>

                    <FormField v-if="reqBirthDate || !allowMinors" label="Data de Nascimento" :required="reqBirthDate || !allowMinors">
                        <input type="date" v-model="form.birth_date" class="w-full h-12 px-3 rounded-xl border border-surface-border bg-surface-1 text-surface-text text-sm font-medium outline-none focus:border-primary-500 transition-colors" />
                    </FormField>
                </div>
            </template>

            <!-- 🚀 CAMPOS DE LOGIN: EXIBIDOS APENAS QUANDO SOLICITADOS EXPLICITAMENTE -->
            <div class="flex flex-col gap-3 mt-2 border-t border-surface-border/50 pt-4" v-if="(askEmail || askPassword) && (customerRegistrationMode !== 'serpro_magic' || (serproStep !== 'cpf_input' && serproStep !== 'cpf_exists'))">
                <FormField v-if="askEmail" label="E-mail (Login do App)" required>
                    <InputText v-model="form.email" type="email" placeholder="joao@email.com" class="w-full h-12 font-medium" />
                </FormField>

                <template v-if="askPassword">
                    <FormField label="Criar Senha" required>
                        <InputText v-model="form.password" type="password" placeholder="Mínimo de 6 caracteres" class="w-full h-12 font-medium" />
                    </FormField>
                </template>
            </div>

            <!-- RODAPÉ -->
            <div class="flex flex-col items-center gap-3 w-full mt-4" v-if="(customerRegistrationMode !== 'serpro_magic' || (serproStep !== 'cpf_input' && serproStep !== 'cpf_exists'))">
                <div class="flex items-center justify-end gap-2 w-full">
                    <Button label="Cancelar" variant="text" severity="secondary" @click="isVisible = false" class="font-medium flex-1" />
                    <Button
                        label="Finalizar Cadastro"
                        @click="handleSave"
                        class="font-black flex-1 shadow-md"
                        :disabled="!isReadyToSave"
                    />
                </div>

                <button v-if="hasSupport" @click="openSupport('registration_help')" type="button" class="text-[10px] font-black uppercase tracking-widest text-success-600 flex items-center gap-1 hover:underline outline-none bg-transparent border-none cursor-pointer mt-1">
                    <i class="fa-brands fa-whatsapp text-xs"></i> Precisa de ajuda com o cadastro?
                </button>
            </div>

            <div class="flex justify-center mt-6" v-if="hasSupport && customerRegistrationMode === 'serpro_magic' && serproStep === 'cpf_input'">
                <button @click="openSupport('registration_help')" type="button" class="text-[10px] font-black uppercase tracking-widest text-success-600 flex items-center gap-1 hover:underline outline-none bg-transparent border-none cursor-pointer">
                    <i class="fa-brands fa-whatsapp text-xs"></i> Precisa de ajuda com o cadastro?
                </button>
            </div>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { computed, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import FormField from "@/Components/UI/FormField.vue";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";

import { useCustomerRegistration } from "@/Pages/Customer/Composables/useCustomerRegistration";
import { useWhatsAppSupport } from "@/Pages/Customer/Composables/useWhatsAppSupport";

const props = defineProps({
    visible: Boolean,
    initialName: String,
    askPassword: { type: Boolean, default: false },
    askEmail: { type: Boolean, default: false } // 🚀 Padrão false: Não exige e-mail no PDV
});

const emit = defineEmits(["update:visible", "save"]);

const page = usePage();
const { hasSupport, openSupport } = useWhatsAppSupport();

const customerRegistrationMode = computed(() => page.props.companySettings?.customer_registration_mode || 'basic');
const isVisible = computed({ get: () => props.visible, set: (value) => emit("update:visible", value) });

const {
    form, serproStep, isLoadingSerpro, isCpfValid, isFormValid, isMinorError, existingAccount,
    reqWhatsapp, reqBirthDate, allowMinors,
    formatCpfInput, formatPhoneInput, lookupSerpro, resetForm
} = useCustomerRegistration(customerRegistrationMode);

const registrationMode = computed(() => {
    const mode = page.props.companySettings?.customer_registration_mode || 'basic';
    if (mode === 'customer_only') {
        return 'basic';
    }
    return mode;
});

const isSerproMagic = computed(() => registrationMode.value === 'serpro_magic');
const isCpfRequired = computed(() => registrationMode.value === 'cpf_required');

// 🚀 Validação condicional: só exige e-mail ou senha se as props estiverem ativadas
const isReadyToSave = computed(() => {
    if (!isFormValid.value) return false;
    if (props.askEmail && !form.value.email) return false;
    if (props.askPassword && (!form.value.password || form.value.password.length < 6)) return false;
    return true;
});

watch(() => props.visible, (isOpen) => {
    if (isOpen) { resetForm(props.initialName); }
});

const handleSave = () => { emit("save", { ...form.value }); };
const handleSupportHelp = () => { openSupport('login_cpf_exists', { cpf: form.value.cpf }); };
const formatDate = (isoString) => { if (!isoString) return ''; const [y, m, d] = isoString.split('-'); return `${d}/${m}/${y}`; };
</script>
