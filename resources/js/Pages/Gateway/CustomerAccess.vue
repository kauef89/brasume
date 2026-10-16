<template>
    <div class="flex flex-col items-center w-full select-none">
        <div class="w-full flex flex-col gap-3 animate-in fade-in duration-200">

            <template v-if="!showWhatsappFlow && !showEmailForm">

                <!-- 1. GOOGLE -->
                <button
                    v-if="activeMethods.includes('google')"
                    type="button"
                    @click.prevent="loginWithGoogle"
                    :disabled="isLoggingInLocally"
                    class="w-full p-4 flex items-center justify-center gap-3 rounded-full font-black transition-colors shadow-sm outline-none cursor-pointer"
                    :class="isCartMode ? 'bg-white text-black hover:bg-white/90' : 'bg-primary-500/10 border border-primary-500/20 text-surface-text hover:bg-primary-500/20'"
                >
                    <i v-if="isLoggingInLocally" class="pi pi-spinner animate-spin" :class="isCartMode ? 'text-black' : 'text-primary-500'"></i>
                    <i v-else class="pi pi-google text-lg" :class="isCartMode ? 'text-black' : 'text-red-500'"></i>
                    <span>{{ isLoggingInLocally ? 'Conectando...' : 'Continuar com Google' }}</span>
                </button>

                <!-- 2. FACEBOOK -->
                <button
                    v-if="activeMethods.includes('facebook')"
                    type="button"
                    @click.prevent="loginWithFacebook"
                    :disabled="isLoggingInFacebook"
                    class="w-full p-4 flex items-center justify-center gap-3 rounded-full font-black transition-colors shadow-sm outline-none cursor-pointer bg-[#1877F2] text-white hover:bg-[#166fe5]"
                >
                    <i v-if="isLoggingInFacebook" class="pi pi-spinner animate-spin text-white"></i>
                    <i v-else class="pi pi-facebook text-lg text-white"></i>
                    <span>{{ isLoggingInFacebook ? 'Conectando...' : 'Continuar com Facebook' }}</span>
                </button>

                <!-- 3. WHATSAPP OTP (Blindado) -->
                <button
                    v-if="activeMethods.includes('whatsapp')"
                    type="button"
                    @click="showWhatsappFlow = true"
                    :disabled="isLoggingIn"
                    class="w-full p-4 flex items-center justify-center gap-3 rounded-full font-black transition-colors shadow-sm outline-none cursor-pointer"
                    :class="isCartMode ? 'bg-[#25D366] text-black hover:bg-[#20bd5a]' : 'bg-[#25D366] text-white hover:bg-[#20bd5a]'"
                >
                    <i class="pi pi-whatsapp text-xl"></i>
                    <span>Continuar com WhatsApp</span>
                </button>

                <!-- 4. EMAIL FALLBACK -->
                <button
                    v-if="needsEmailFallback"
                    type="button"
                    @click="showEmailForm = true"
                    :disabled="isLoggingIn"
                    class="w-full p-4 flex items-center justify-center gap-3 rounded-full font-black transition-colors outline-none cursor-pointer"
                    :class="isCartMode ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-surface-2 text-surface-text hover:bg-surface-3 border border-surface-border'"
                >
                    <i class="pi pi-envelope text-lg"></i>
                    <span>Entrar com E-mail</span>
                </button>

                <!-- MODO VISITANTE -->
                <button
                    v-if="allowGuest"
                    type="button"
                    @click="emit('guest-action')"
                    class="mt-3 text-sm font-medium p-2 outline-none cursor-pointer text-center"
                    :class="isCartMode ? 'text-white/70 hover:text-white' : 'text-surface-muted hover:text-surface-text'"
                >
                    {{ isCartMode ? 'Continuar sem cadastro →' : 'Pular por enquanto →' }}
                </button>
            </template>

            <!-- 📱 FLUXO INTERATIVO WHATSAPP OTP -->
            <div v-else-if="showWhatsappFlow" class="flex flex-col gap-3 animate-in fade-in duration-200">
                <!-- ETAPA 1: NÚMERO DO WHATSAPP -->
                <div v-if="otpStep === 'phone'" class="flex flex-col gap-3">
                    <FormField label="Seu número do WhatsApp" required :is-glass="isCartMode">
                        <input
                            v-model="whatsappNumber"
                            v-maska="'(##) #####-####'"
                            type="tel"
                            placeholder="(00) 00000-0000"
                            class="w-full p-4 rounded-xl font-bold outline-none text-center text-lg"
                            :class="isCartMode ? 'bg-white/10 text-white' : 'bg-surface-2 border border-surface-border text-surface-text'"
                            @keydown.enter.prevent="requestOtp"
                        />
                    </FormField>

                    <button
                        type="button"
                        @click="requestOtp"
                        :disabled="isSendingOtp || whatsappNumber.length < 14"
                        class="w-full h-12 rounded-full font-black text-xs uppercase tracking-wider bg-[#25D366] text-white hover:bg-[#20bd5a] flex items-center justify-center gap-2 cursor-pointer border-none disabled:opacity-50"
                    >
                        <i v-if="isSendingOtp" class="pi pi-spinner pi-spin"></i>
                        <span>{{ isSendingOtp ? 'Enviando...' : 'Receber Código' }}</span>
                    </button>
                </div>

                <!-- ETAPA 2: DIGITAR O CÓDIGO OTP -->
                <div v-else class="flex flex-col gap-3">
                    <p class="text-xs text-center text-surface-muted">
                        Código enviado para <strong>{{ whatsappNumber }}</strong>
                    </p>

                    <input
                        v-model="otpCode"
                        v-maska="'######'"
                        type="text"
                        placeholder="000000"
                        class="w-full h-14 rounded-xl font-black text-2xl text-center tracking-[0.5em] outline-none"
                        :class="isCartMode ? 'bg-white/10 text-white' : 'bg-surface-2 border border-surface-border text-surface-text'"
                        @input="handleOtpInput"
                    />

                    <button
                        type="button"
                        @click="verifyOtp"
                        :disabled="isVerifyingOtp || otpCode.length < 6"
                        class="w-full h-12 rounded-full font-black text-xs uppercase tracking-wider bg-[#25D366] text-white hover:bg-[#20bd5a] flex items-center justify-center gap-2 cursor-pointer border-none disabled:opacity-50"
                    >
                        <i v-if="isVerifyingOtp" class="pi pi-spinner pi-spin"></i>
                        <span>{{ isVerifyingOtp ? 'Validando...' : 'Confirmar e Entrar' }}</span>
                    </button>
                </div>

                <button
                    type="button"
                    @click="resetWhatsappFlow"
                    class="text-xs font-bold text-surface-muted hover:text-surface-text text-center mt-2 cursor-pointer bg-transparent border-none"
                >
                    Escolher outro método
                </button>
            </div>

            <!-- FORMULÁRIO E-MAIL TRADICIONAL -->
            <form
                v-else-if="showEmailForm"
                @submit.prevent="submitForm"
                class="flex flex-col gap-4 animate-in fade-in slide-in-from-bottom-2 duration-200"
            >
                <FormField label="E-mail" required :is-glass="isCartMode">
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="seu@email.com"
                        required
                        class="w-full p-4 rounded-xl font-medium outline-none transition-all"
                        :class="isCartMode ? 'bg-white/10 border-none text-white focus:bg-white/20' : 'bg-surface-2 border border-surface-border text-surface-text focus:border-primary-500'"
                    />
                </FormField>

                <FormField label="Senha" required :is-glass="isCartMode">
                    <input
                        v-model="form.password"
                        type="password"
                        placeholder="••••••••"
                        required
                        class="w-full p-4 rounded-xl font-medium outline-none transition-all"
                        :class="isCartMode ? 'bg-white/10 border-none text-white focus:bg-white/20' : 'bg-surface-2 border border-surface-border text-surface-text focus:border-primary-500'"
                    />
                </FormField>

                <button
                    type="submit"
                    :disabled="isLoggingIn"
                    class="w-full p-4 rounded-full font-black transition-colors flex items-center justify-center gap-2 cursor-pointer mt-2"
                    :class="isCartMode ? 'bg-white text-black hover:bg-white/90' : 'bg-primary-500 text-white hover:bg-primary-600 border-none'"
                >
                    <i v-if="isLoggingIn" class="pi pi-spinner animate-spin"></i>
                    <span>Entrar</span>
                </button>

                <button
                    type="button"
                    @click="showEmailForm = false"
                    class="text-xs font-bold transition-colors mt-2 cursor-pointer bg-transparent border-none text-surface-muted hover:text-surface-text"
                >
                    Escolher outro método
                </button>
            </form>

        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import FormField from '@/Components/UI/FormField.vue';

const page = usePage();

const props = defineProps({
    allowGuest: Boolean,
    methods: {
        type: Array,
        default: null // 🚀 Permite usar o middleware global se null for passado
    },
    needsEmailFallback: Boolean,
    isLoggingIn: Boolean,
    isCartMode: { type: Boolean, default: false }
});

// 🛡️ BLINDAGEM REATIVA
const activeMethods = computed(() => {
    const list = props.methods ?? page.props.company?.customerAuthMethods ?? [];
    return Array.isArray(list) ? list : [];
});

const emit = defineEmits(['login', 'open-register', 'guest-action']);
const toast = useToast();

const isLoggingInLocally = ref(false);
const isLoggingInFacebook = ref(false);

const loginWithGoogle = async (e) => {
    if (e) e.preventDefault();
    isLoggingInLocally.value = true;
    try {
        const response = await axios.post(route('google.url', { intent: 'customer' }));
        if (response.data?.url) {
            window.location.href = response.data.url;
        }
    } catch {
        isLoggingInLocally.value = false;
    }
};

const loginWithFacebook = async (e) => {
    if (e) e.preventDefault();
    isLoggingInFacebook.value = true;
    try {
        const response = await axios.post(route('facebook.url', { intent: 'customer' }));
        if (response.data?.url) {
            window.location.href = response.data.url;
        }
    } catch {
        isLoggingInFacebook.value = false;
    }
};

// WhatsApp OTP State
const showWhatsappFlow = ref(false);
const otpStep = ref('phone');
const whatsappNumber = ref('');
const otpCode = ref('');
const isSendingOtp = ref(false);
const isVerifyingOtp = ref(false);

const requestOtp = async () => {
    if (whatsappNumber.value.length < 14) return;
    isSendingOtp.value = true;

    try {
        await axios.post(route('customer.auth.whatsapp.send'), { phone: whatsappNumber.value });
        otpStep.value = 'code';
        toast.add({ severity: 'success', summary: 'Código Enviado', detail: 'Verifique seu WhatsApp.', life: 4000 });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Atenção', detail: err.response?.data?.message || 'Falha ao enviar código.' });
    } finally {
        isSendingOtp.value = false;
    }
};

const handleOtpInput = () => {
    if (otpCode.value.length === 6) {
        verifyOtp();
    }
};

const verifyOtp = async () => {
    if (otpCode.value.length < 6 || isVerifyingOtp.value) return;
    isVerifyingOtp.value = true;

    try {
        await axios.post(route('customer.auth.whatsapp.verify'), {
            phone: whatsappNumber.value,
            code: otpCode.value
        });
        window.location.reload();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Código Inválido', detail: 'Verifique os 6 dígitos digitados.' });
    } finally {
        isVerifyingOtp.value = false;
    }
};

const resetWhatsappFlow = () => {
    showWhatsappFlow.value = false;
    otpStep.value = 'phone';
    whatsappNumber.value = '';
    otpCode.value = '';
};

// E-mail Form State
const showEmailForm = ref(false);
const form = ref({ email: '', password: '' });
const submitForm = () => {
    emit('login', { email: form.value.email, password: form.value.password });
};
</script>
