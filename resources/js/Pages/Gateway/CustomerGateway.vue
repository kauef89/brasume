<template>
    <div
        class="h-[100dvh] w-screen flex flex-col items-center justify-center p-6 relative overflow-hidden font-sans bg-cover bg-center bg-surface-900"
        :style="currentBackground ? `background-image: url('/media/file?path=${currentBackground}');` : ''"
    >
        <!-- PELÍCULAS DE PROFUNDIDADE E LEITURA -->
        <div class="absolute inset-0 pointer-events-none z-0 bg-black/30 backdrop-blur-[2px]"></div>
        <div class="absolute inset-0 pointer-events-none z-0 bg-[radial-gradient(circle_at_center,transparent_0%,rgba(0,0,0,0.7)_100%)]"></div>

        <!-- O CARTÃO DE VIDRO (GLASSMORPHISM) -->
        <div class="z-10 w-full max-w-sm bg-surface-900/80 backdrop-blur-2xl border border-surface-700/50 rounded-[2rem] p-6 md:p-8 shadow-2xl flex flex-col gap-5 animate-in zoom-in-95 duration-500">

            <!-- CABEÇALHO -->
            <div class="text-center">
                <div v-if="company.logo" class="w-20 h-20 mx-auto mb-4 rounded-3xl bg-surface-800 border border-surface-700 flex items-center justify-center shadow-inner overflow-hidden p-1">
                    <img :src="`/media/file?path=${company.logo}`" class="w-full h-full object-contain drop-shadow-md" alt="Logo da Empresa" />
                </div>
                <div v-else class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-primary-600 flex items-center justify-center text-white font-black text-2xl shadow-xl">
                    {{ company.name?.charAt(0) || 'B' }}
                </div>

                <h1 class="text-2xl font-black text-white tracking-tight">{{ company.name }}</h1>
                <p class="text-primary-400 mt-1 font-bold uppercase tracking-widest text-[10px]">Acesso do Cliente</p>
            </div>

            <!-- ÁREA DE LOGIN -->
            <div class="flex flex-col gap-3">
                <button v-if="company.allowGuest" @click="handleGuest" class="w-full h-14 rounded-2xl bg-primary-500 text-white flex items-center justify-center font-black text-base shadow-lg hover:bg-primary-600 transition-colors outline-none">
                    Fazer Pedido Agora
                </button>

                <div v-if="company.allowGuest" class="flex items-center gap-3 my-1 opacity-60">
                    <div class="flex-1 h-px bg-surface-600"></div>
                    <span class="text-[9px] font-bold uppercase tracking-widest text-surface-300">Ou guarde o seu histórico</span>
                    <div class="flex-1 h-px bg-surface-600"></div>
                </div>

                <!-- GOOGLE OAUTH -->
                <a v-if="company.customerAuthMethods?.includes('google')" :href="route('google.redirect', { intent: 'customer' })" class="w-full h-14 rounded-2xl bg-surface-800 border border-surface-600 text-white flex items-center justify-center font-medium gap-3 hover:bg-surface-700 transition-colors no-underline outline-none shadow-sm">
                    <i class="pi pi-google text-primary-400"></i> Entrar com Google
                </a>

                <!-- 🚀 FACEBOOK OAUTH (NOVO) -->
                <a v-if="company.customerAuthMethods?.includes('facebook')" :href="route('facebook.redirect', { intent: 'customer' })" class="w-full h-14 rounded-2xl bg-[#1877F2]/10 border border-[#1877F2]/30 text-[#1877F2] flex items-center justify-center font-medium gap-3 hover:bg-[#1877F2]/20 transition-colors no-underline outline-none shadow-sm">
                    <i class="pi pi-facebook text-lg"></i> Entrar com Facebook
                </a>

                <!-- 🚀 WHATSAPP OTP (ATUALIZADO) -->
                <div v-if="company.require_whatsapp || company.customerAuthMethods?.includes('whatsapp')" class="w-full">
                    <button v-if="!showWhatsappInput" @click="showWhatsappInput = true" class="w-full h-14 rounded-2xl bg-success-500/10 border border-success-500/30 text-success-400 flex items-center justify-center font-bold gap-3 hover:bg-success-500/20 transition-colors outline-none">
                        <i class="pi pi-whatsapp text-xl"></i> Entrar com WhatsApp
                    </button>

                    <div v-else class="flex flex-col gap-2 animate-in fade-in slide-in-from-top-2">
                        <input v-model="whatsappNumber" type="tel" placeholder="(00) 00000-0000" class="w-full h-14 px-4 rounded-xl bg-surface-900 border border-surface-700 text-white placeholder:text-surface-500 font-medium text-sm outline-none focus:border-success-500 transition-all shadow-inner" />
                        <button @click="requestOtp" class="w-full h-12 rounded-xl bg-success-500 text-white font-black uppercase tracking-widest text-xs hover:bg-success-600 transition-colors outline-none">
                            Receber Código OTP
                        </button>
                    </div>
                </div>

                <!-- EMAIL FALLBACK -->
                <button v-if="!showEmailForm" @click="showEmailForm = true" class="text-xs font-medium text-surface-400 hover:text-white mt-2 outline-none transition-colors">
                    Usar email e senha
                </button>

                <form @submit.prevent="submitEmailLogin" v-if="showEmailForm" class="flex flex-col gap-2 mt-2 animate-in fade-in slide-in-from-bottom-2">
                    <input v-model="form.email" type="email" autocomplete="username" required placeholder="Seu e-mail" class="w-full h-12 px-4 rounded-xl bg-surface-900 border border-surface-700 text-white placeholder:text-surface-500 font-medium text-sm outline-none focus:border-primary-500 transition-all shadow-inner" />
                    <input v-model="form.password" type="password" autocomplete="current-password" required placeholder="Sua senha" class="w-full h-12 px-4 rounded-xl bg-surface-900 border border-surface-700 text-white placeholder:text-surface-500 font-medium text-sm outline-none focus:border-primary-500 transition-all shadow-inner" />

                    <button type="submit" :disabled="isLoggingIn" class="w-full h-12 rounded-xl bg-white text-surface-900 font-black uppercase tracking-widest text-xs mt-1 flex items-center justify-center gap-2 outline-none disabled:opacity-50 hover:bg-surface-200 transition-colors">
                        <i v-if="isLoggingIn" class="pi pi-spinner pi-spin"></i>
                        {{ isLoggingIn ? 'Autenticando...' : 'Entrar' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- BOTÃO DE FUGA -->
        <Link :href="route('tenant.home')" class="absolute bottom-6 md:bottom-10 text-center text-surface-300 hover:text-white transition-colors text-xs md:text-sm font-medium flex items-center justify-center gap-2 z-10 outline-none drop-shadow-md">
            <i class="pi pi-arrow-left"></i> Voltar à Landing Page
        </Link>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { useStoreStatus } from "@/Composables/useStoreStatus";

defineOptions({ layout: null });

const page = usePage();
// 🚀 Puxando o objeto global injetado pelo Middleware
const company = computed(() => page.props.company || {});
const { isOpen } = useStoreStatus();

const showWhatsappInput = ref(false);
const showEmailForm = ref(false);
const isLoggingIn = ref(false);
const whatsappNumber = ref('');
const form = ref({ email: '', password: '' });

// ==========================================
// MOTOR DE PLANO DE FUNDO
// ==========================================
const currentBackground = ref(null);

const pickRandomBackground = () => {
    const pubSettings = page.props.publicSettings;
    const bgOpen = pubSettings?.home_bg_open || [];
    const bgClosed = pubSettings?.home_bg_closed || [];
    let pool = isOpen.value ? bgOpen : (bgClosed.length > 0 ? bgClosed : bgOpen);

    if (pool && pool.length > 0) {
        currentBackground.value = pool[Math.floor(Math.random() * pool.length)];
    } else {
        currentBackground.value = null;
    }
};

onMounted(() => {
    pickRandomBackground();
});

watch(isOpen, () => pickRandomBackground());

// ==========================================
// AÇÕES
// ==========================================
const handleGuest = () => {};
const requestOtp = () => {};
const submitEmailLogin = () => {
    isLoggingIn.value = true;
    router.post(route('customer.auth.login'), {
        email: form.value.email,
        password: form.value.password
    }, {
        onFinish: () => { isLoggingIn.value = false; }
    });
};
</script>
