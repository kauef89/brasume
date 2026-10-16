<template>
    <!-- Fundo de Tela Dinâmico -->
    <div class="min-h-screen flex items-end lg:items-center justify-center lg:justify-end bg-surface-1 font-sans p-0 lg:p-4 lg:pr-24 relative overflow-hidden bg-cover bg-center transition-all duration-1000"
         :style="backgroundStyle">

        <!-- CARD DIREITO (BOTTOM SHEET NO MOBILE) -->
        <div class="bg-black/30 backdrop-blur-xl shadow-2xl rounded-t-[2.5rem] lg:rounded-4xl flex flex-col w-full sm:max-w-md lg:max-w-[260px] overflow-hidden z-10 relative transition-all duration-500 border-t lg:border border-white/10 mx-auto lg:mx-0 pb-6 lg:pb-0">

            <!-- PUXADOR MOBILE (Indicador visual de Bottom Sheet) -->
            <div class="lg:hidden w-full flex justify-center pt-4 pb-1 shrink-0">
                <div class="w-12 h-1.5 bg-white/20 rounded-full"></div>
            </div>

            <!-- 1. LOGOTIPO NO TOPO -->
            <div class="flex flex-col items-center justify-center pt-2 lg:pt-8 pb-4 shrink-0 px-6">
                <img v-if="company?.logo" :src="`/media/file?path=${company.logo}`" class="h-10 invert object-contain" />
                <div v-else class="w-14 h-14 rounded-full flex items-center justify-center mb-2">
                    <i class="pi pi-lock text-2xl text-primary-500"></i>
                </div>

                <h1 v-if="!showMobileAuth && isDeviceAuthorized" class="text-white/90 text-xs mt-3 text-center">Acesso Frente de Caixa</h1>
                <h1 v-else-if="showMobileAuth" class="text-white/90 mt-3 text-xs text-center">Acesso Operacional</h1>
            </div>

            <!-- 2. MIOLO DINÂMICO ROLÁVEL -->
            <div class="flex-1 overflow-y-auto hide-scrollbar flex flex-col w-full">

                <!-- TELA PADRÃO: TERMINAL FIXO (DESKTOP) -->
                <template v-if="!showMobileAuth">

                    <!-- VERIFICANDO -->
                    <div v-if="isCheckingDevice" class="flex-1 flex flex-col items-center justify-center text-center w-full py-8">
                        <i class="pi pi-spinner pi-spin text-3xl text-primary-500 mb-4"></i>
                        <p class="text-xs font-bold text-white/60 animate-pulse uppercase tracking-widest">Verificando terminal</p>
                    </div>

                    <!-- EMPARELHAMENTO -->
                    <div v-else-if="!isDeviceAuthorized" class="flex-1 w-full flex flex-col items-center justify-center py-4">
                        <DevicePairingBox @paired="handleDevicePaired" class="w-full" />
                    </div>

                    <!-- LOGIN AUTORIZADO -->
                    <template v-else>
                        <!-- FALLBACK: SEM MÉTODOS -->
                        <div v-if="activeMethods.length === 0" class="flex-1 flex flex-col items-center justify-center text-center py-8">
                            <i class="pi pi-exclamation-triangle text-4xl text-warning-500 mb-4"></i>
                            <h2 class="text-base font-black text-white/90">Acesso Bloqueado</h2>
                            <p class="text-xs text-white/60 mt-2">
                                Nenhum método configurado para {{ portal === 'kds' ? 'a Cozinha (KDS)' : 'o Caixa' }}.
                            </p>
                        </div>

                        <!-- LÓGICA DE MÉTODOS (PIN / QR / REMOTO) -->
                        <div v-else class="flex flex-col w-full">

                            <!-- PIN PAD -->
                            <div v-if="activeMethods.includes('pin')" class="flex flex-col w-full items-center mb-6">
                                <p class="text-[10px] text-white/60 font-bold uppercase tracking-widest mb-3">Digite seu PIN de 4 dígitos</p>

                                <div class="flex justify-center gap-2 mb-4">
                                    <div v-for="i in 4" :key="i"
                                         class="w-10 h-12 rounded-xl flex items-center justify-center text-xl font-black transition-all"
                                         :class="pin.length >= i ? 'bg-primary-500 text-white shadow-md scale-105' : 'bg-surface-2 border border-surface-border text-white/60'">
                                        {{ pin.length >= i ? '•' : '' }}
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-2 w-full max-w-[240px] mx-auto">
                                    <button v-for="n in [1,2,3,4,5,6,7,8,9]" :key="n" @click="appendPin(n)"
                                            class="h-12 rounded-xl text-lg font-black text-white/90 transition-all outline-none shadow-sm active:scale-95 cursor-pointer">
                                        {{ n }}
                                    </button>
                                    <button @click="clearPin" class="h-12 rounded-xl bg-danger-500/10 text-danger-600 font-black text-[10px] uppercase tracking-widest hover:bg-danger-500 hover:text-white transition-all outline-none shadow-sm active:scale-95 cursor-pointer border-none">
                                        Limpar
                                    </button>
                                    <button @click="appendPin(0)" class="h-12 rounded-xl bg-surface-2 border border-surface-border text-lg font-black text-white/90 hover:bg-surface-3 hover:border-primary-500/50 transition-all outline-none shadow-sm active:scale-95 cursor-pointer">
                                        0
                                    </button>
                                    <button @click="submitPin" :disabled="pin.length < 4 || isSubmitting" class="h-12 rounded-xl bg-success-500 text-white font-black text-lg hover:bg-success-600 transition-all outline-none shadow-sm disabled:opacity-50 active:scale-95 flex items-center justify-center cursor-pointer border-none">
                                        <i class="pi" :class="isSubmitting ? 'pi-spinner pi-spin' : 'pi-check'"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- DIVISOR "OU" -->
                            <div v-if="activeMethods.includes('pin') && (activeMethods.includes('qr_code') || activeMethods.includes('remote'))" class="w-full flex items-center gap-3 mb-6">
                                <div class="flex-1 h-px bg-surface-border"></div>
                                <span class="text-[10px] font-bold text-white/60 uppercase tracking-widest">Ou</span>
                                <div class="flex-1 h-px bg-surface-border"></div>
                            </div>

                            <!-- QR CODE & LIBERAÇÃO REMOTA -->
                            <div v-if="activeMethods.includes('remote') || activeMethods.includes('qr_code')" class="flex flex-col w-full gap-4 items-center">

                                <!-- QR CODE -->
                                <div v-if="activeMethods.includes('qr_code')" class="flex flex-col items-center w-full">
                                    <div>
                                        <QrLoginBox :device-uuid="activeDeviceUuid" />
                                    </div>
                                </div>

                                <!-- SEPARADOR INTERNO -->
                                <div v-if="activeMethods.includes('qr_code') && activeMethods.includes('remote')" class="w-full h-px bg-surface-border/10 my-1"></div>

                                <!-- RADAR / REMOTO -->
                                <div v-if="activeMethods.includes('remote')" class="flex flex-col items-center w-full pb-5 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-warning-400/10 flex items-center justify-center relative shrink-0 text-warning-600">
                                            <div v-if="isWaiting" class="absolute inset-0 bg-warning-300/20 rounded-full animate-ping pointer-events-none"></div>
                                            <i class="pi pi-desktop text-sm"></i>
                                        </div>
                                        <div class="flex flex-col items-start leading-tight">
                                            <span class="text-xs font-black text-white/90">Liberação Remota</span>
                                            <span v-if="isWaiting" class="text-[10px] text-white/60 mt-0.5">ID: <strong class="font-mono text-primary-500">{{ activeDeviceUuid?.split('-')[0] }}</strong></span>
                                            <span v-else class="text-[10px] text-white/60 mt-0.5">Aguardando gestor</span>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </template>
                </template>

                <!-- TELA MOBILE / ACESSO NUVEM -->
                <template v-else>
                    <div class="flex flex-col items-center justify-center w-full mx-auto animate-in fade-in zoom-in-95 duration-300 p-4">
                        <div class="w-full flex flex-col px-12 gap-3">
                            <button v-if="teamMethods.includes('pin')" @click="showPinFlow = true" class="w-full h-12 rounded-xl bg-purple-500/10 text-purple-600 border border-purple-500/20 flex items-center justify-center font-black text-xs uppercase tracking-widest gap-2 hover:bg-purple-500/20 transition-colors outline-none cursor-pointer shadow-sm">
                                <i class="pi pi-users text-sm"></i> Entrar com PIN
                            </button>

                            <a v-if="teamMethods.includes('google')" :href="route('google.redirect', { intent: 'operation' })" class="w-full h-12 rounded-full bg-white text-black flex items-center justify-center font-black text-sm uppercase tracking-widest gap-2 hover:bg-surface-700 transition-colors no-underline outline-none cursor-pointer">
                                <i class="pi pi-google text-primary-400 text-sm"></i> Entrar com Google
                            </a>

                            <form @submit.prevent="loginTeam(mobileForm)" v-if="teamNeedsEmailFallback" class="flex flex-col gap-3 w-full mt-2">
                                <input v-model="mobileForm.email" type="email" autocomplete="username" required placeholder="E-mail Corporativo" class="w-full h-12 px-4 rounded-xl bg-surface-2 border border-surface-border text-white/90 placeholder:text-white/60 font-medium outline-none focus:border-primary-500 transition-colors shadow-inner text-sm" />
                                <input v-model="mobileForm.password" type="password" autocomplete="current-password" required placeholder="Senha" class="w-full h-12 px-4 rounded-xl bg-surface-2 border border-surface-border text-white/90 placeholder:text-white/60 font-medium outline-none focus:border-primary-500 transition-colors shadow-inner text-sm" />

                                <button type="submit" :disabled="isTeamLoggingIn" class="w-full h-12 rounded-xl bg-primary-500 text-white font-black uppercase tracking-widest text-xs mt-1 flex items-center justify-center gap-2 outline-none disabled:opacity-50 hover:bg-primary-600 transition-all shadow-md active:scale-95 cursor-pointer border-none">
                                    <i v-if="isTeamLoggingIn" class="pi pi-spinner pi-spin"></i>
                                    {{ isTeamLoggingIn ? 'Autenticando...' : 'Acessar Conta' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </template>

            </div>

            <!-- 3. RODAPÉ DO CARD: Alternância de Modo -->
            <div class="p-4 border-t border-surface-border/10 shrink-0 w-full flex justify-center">
                <button @click="showMobileAuth = !showMobileAuth" class="text-[10px] md:text-xs font-bold text-white/60 hover:text-primary-500 hover:bg-primary-400/10 border border-white/50 rounded-full transition-colors uppercase outline-none border-none bg-transparent cursor-pointer flex items-center gap-2">
                    <i class="pi" :class="showMobileAuth ? 'pi-desktop' : 'pi-mobile'"></i>
                    {{ showMobileAuth ? 'Acesso de Caixa' : 'Acesso de Atendimento' }}
                </button>
            </div>
        </div>

        <!-- MODAL DE PIN MOBILE -->
        <PinAuthFlow
            v-model:visible="showPinFlow"
            :staff="authorizedStaff"
            :is-logging-in="isPinLoggingIn"
            :error-message="pinErrorMessage"
            @clear-error="pinErrorMessage = ''"
            @submit-pin="handleMobilePinLogin"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { useRemoteUnlock } from '@/Pages/Gateway/Composables/useRemoteUnlock';
import { useLoginGateway } from '@/Pages/Gateway/Composables/useLoginGateway';
import { useDynamicBackground } from '@/Composables/useDynamicBackground';

import PinAuthFlow from '@/Pages/Gateway/PinAuthFlow.vue';
import QrLoginBox from '@/Pages/Gateway/QrLoginBox.vue';
import DevicePairingBox from '@/Pages/Gateway/DevicePairingBox.vue';

const page = usePage();
const toast = useToast();

const { backgroundStyle } = useDynamicBackground(true);

const props = defineProps({
    portal: String,
    mode: String,
    authorizedStaff: Array,
    storeStatus: Object,
});

const company = computed(() => page.props.company || {});

// 🚀 Determina o modo inicial: se rota foi 'mobile', abre Atendente; se 'desktop', abre Caixa
const showMobileAuth = ref(props.mode === 'mobile');
const mobileForm = ref({ email: '', password: '' });

const { teamMethods, teamNeedsEmailFallback, isTeamLoggingIn, loginTeam } = useLoginGateway(company.value);

const activeMethods = computed(() => {
    if (props.portal === 'kds') {
        return company.value.kdsAuthMethods || [];
    }
    return company.value.cashierAuthMethods || [];
});

const { isWaiting } = useRemoteUnlock();

const deviceType = ref('Desconhecido');
const activeDeviceUuid = ref('');
const isCheckingDevice = ref(true);
const isDeviceAuthorized = ref(false);

const checkDeviceAuthorization = async () => {
    const uuid = localStorage.getItem('brasume_device_uuid');

    if (!uuid) {
        isDeviceAuthorized.value = false;
        isCheckingDevice.value = false;
        return;
    }

    activeDeviceUuid.value = uuid;

    try {
        const response = await axios.post(route('auth.qr.validate'), { device_uuid: uuid });
        if (response.data.valid) {
            isDeviceAuthorized.value = true;
            deviceType.value = response.data.device.type;
        } else {
            handleUnauthorized();
        }
    } catch (error) {
        handleUnauthorized();
    } finally {
        isCheckingDevice.value = false;
    }
};

const handleUnauthorized = () => {
    localStorage.removeItem('brasume_device_uuid');
    activeDeviceUuid.value = '';
    isDeviceAuthorized.value = false;
};

const handleDevicePaired = (devicePayload) => {
    if (devicePayload && (devicePayload.uuid || devicePayload.device_uuid)) {
        const key = devicePayload.uuid || devicePayload.device_uuid;
        localStorage.setItem('brasume_device_uuid', key);
        window.location.reload();
    }
};

onMounted(() => {
    deviceType.value = localStorage.getItem('brasume_device_type') || 'Desconhecido';

    // 🚀 Só recorre ao user-agent se o modo não veio cravado pela URL
    if (!props.mode && (page.props.device?.is_mobile || page.props.device?.is_tablet)) {
        showMobileAuth.value = true;
    }

    checkDeviceAuthorization();
});

watch([activeDeviceUuid, isDeviceAuthorized], ([newUuid, isAuthorized]) => {
    if (newUuid && isAuthorized && window.Echo) {
        console.log(`📡 [Antena Gateway] Conectando ao canal: device.${newUuid}`);

        window.Echo.channel(`device.${newUuid}`)
            .listen('.DeviceRemoteUnlocked', (event) => {
                console.log('✅ [WebSocket] Evento de Liberação Recebido!', event);
                toast.add({ severity: 'success', summary: 'Autorizado', detail: 'O gestor liberou o terminal!', life: 3000 });

                try {
                    router.post(route('auth.remote.exchange'), { token: event.token }, {
                        preserveScroll: true,
                        preserveState: false,
                        onSuccess: () => {
                            console.log('🚀 [Gateway] Sessão gerada! Redirecionando...');

                            // 🛡️ SANITIZAÇÃO DE ESCOPO DO PWA:
                            // Garante SEMPRE caminho relativo local (/pos, /team),
                            // ignorando o domínio do gestor (adm.brasume.com.br) que disparou o evento.
                            let targetPath = '/pos';

                            if (event.redirectUrl) {
                                try {
                                    targetPath = event.redirectUrl.startsWith('http')
                                        ? new URL(event.redirectUrl).pathname
                                        : event.redirectUrl;
                                } catch {
                                    targetPath = '/pos';
                                }
                            }

                            window.location.href = targetPath;
                        },
                        onError: (errors) => {
                            console.error('🚨 [Gateway] Erro no exchange:', errors);
                            toast.add({ severity: 'error', summary: 'Erro', detail: errors.error || 'Falha na validação final.', life: 4000 });
                        }
                    });
                } catch (error) {
                    toast.add({ severity: 'error', summary: 'Erro', detail: 'Erro de comunicação.', life: 4000 });
                }
            });
    }
}, { immediate: true });

// LÓGICA DO PIN PARA MOBILE
const showPinFlow = ref(false);
const isPinLoggingIn = ref(false);
const pinErrorMessage = ref('');

const handleMobilePinLogin = (payload, clearPinCallback) => {
    isPinLoggingIn.value = true;
    pinErrorMessage.value = '';

    router.post(route('auth.pin.login'), {
        email: payload.email,
        pin: payload.pin,
        device_uuid: activeDeviceUuid.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isPinLoggingIn.value = false;
            showPinFlow.value = false;
        },
        onError: (errors) => {
            isPinLoggingIn.value = false;
            if (clearPinCallback) clearPinCallback();
            pinErrorMessage.value = errors.pin || 'Acesso negado. Verifique os dados.';
        }
    });
};

// LÓGICA DO PIN PAD (Terminal Fixo)
const pin = ref('');
const isSubmitting = ref(false);

const appendPin = (num) => {
    if (pin.value.length < 4) {
        pin.value += String(num);
        if (pin.value.length === 4) submitPin();
    }
};

const clearPin = () => { pin.value = ''; };

const submitPin = () => {
    if (pin.value.length !== 4) return;

    isSubmitting.value = true;
    router.post(route('auth.pin.login'), {
        pin: pin.value,
        device_uuid: activeDeviceUuid.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: (errors) => {
            isSubmitting.value = false;
            pin.value = '';
            toast.add({ severity: 'error', summary: 'Acesso Negado', detail: errors.pin || 'PIN incorreto ou turno fechado.', life: 4000 });
        }
    });
};
</script>
