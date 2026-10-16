<template>
    <div class="flex flex-col items-center justify-center gap-4 text-center p-2">

        <!-- 🚀 1. NOME E STATUS NA MESMA LINHA -->
        <div class="flex items-center justify-between w-full max-w-[224px]">
            <span class="text-xs uppercase text-white truncate flex-1 text-left mr-2">
                {{ deviceData?.name || 'Terminal' }}
            </span>
            <div class="flex items-center gap-1.5 text-white/90 text-xs uppercase shrink-0">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-300 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-success-400"></span>
                </span>
                Pareado
            </div>
        </div>

        <!-- 2. QR CODE -->
        <div class="relative w-38 h-38 p-3 bg-white rounded-2xl flex items-center justify-center overflow-hidden shadow-lg">

            <qrcode-vue
                v-if="sessionToken"
                :value="qrUrl"
                :size="200"
                level="H"
                render-as="svg"
                class="transition-opacity duration-300"
                :class="{'opacity-30 blur-sm scale-95': isGenerating}"
            />

            <!-- Corrigido texto invisível (branco no branco) -->
            <div v-if="!sessionToken" class="flex flex-col items-center gap-2 text-surface-500">
                <i class="pi pi-spin pi-spinner text-2xl text-surface-400"></i>
                <span class="text-xs font-bold">Criptografando...</span>
            </div>

            <div v-if="isAuthorized" class="absolute inset-0 bg-success-500/95 backdrop-blur-sm flex flex-col items-center justify-center text-white animate-in zoom-in-95 duration-300">
                <i class="pi pi-check-circle text-5xl mb-2 animate-bounce"></i>
                <span class="font-black text-sm uppercase tracking-wider">Autorizado!</span>
                <span class="text-[10px] opacity-80">Abrindo sistema...</span>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import QrcodeVue from 'qrcode.vue';
import axios from 'axios';

const props = defineProps({
    deviceUuid: { type: String, default: '' }
});

const page = usePage();
const activeUuid = ref('');
const sessionToken = ref('');
const deviceData = ref(null);
const isAuthorized = ref(false);
const isGenerating = ref(false);
let rotationInterval = null;

// 🚀 A URL aponta para a Sessão Volátil
const qrUrl = computed(() => {
    return sessionToken.value ? `${window.location.origin}/team/quick-unlock/${sessionToken.value}` : '';
});

// Busca o Nome e Tipo para exibir na tela usando a rota correta do Ziggy
const fetchDeviceDetails = async () => {
    try {
        const response = await axios.post(route('auth.qr.validate'), { device_uuid: activeUuid.value });
        if (response.data?.valid) {
            deviceData.value = response.data.device;
        }
    } catch (error) {
        console.error('Falha ao validar máquina:', error);
    }
};

// Cria um token aleatório e avisa o Laravel
const generateSession = async () => {
    if (!activeUuid.value) return;

    isGenerating.value = true;
    const token = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);

    // 🚀 Desconecta da rádio velha antes de criar uma nova
    if (sessionToken.value && window.Echo) {
        window.Echo.leaveChannel(`qr-auth.${sessionToken.value}`);
    }

    try {
        await axios.post(route('auth.remote.register-qr'), {
            device_uuid: activeUuid.value,
            session_token: token
        });

        sessionToken.value = token;
        listenForWebSocket(); // 🚀 Inicia a escuta IMEDIATAMENTE após gerar o token!

    } catch (error) {
        console.error('Falha ao registrar sessão de segurança:', error);
    } finally {
        setTimeout(() => { isGenerating.value = false; }, 300);
    }
};

// Escuta a autorização remota via WebSockets e faz o handshake na rota correta
// Escuta a autorização remota via WebSockets e faz o handshake na rota correta
const listenForWebSocket = () => {
    if (!sessionToken.value) {
        console.warn('📡 [Echo] Tentou sintonizar sem token!');
        return;
    }

    if (!window.Echo) {
        console.error('❌ [Echo] window.Echo não está disponível no navegador!');
        return;
    }

    const channelName = `qr-auth.${sessionToken.value}`;
    console.log(`📡 [Echo] Sintonizando a antena no canal: ${channelName}`);

    // A função que processa o destravamento caso qualquer um dos eventos abaixo seja ouvido
    const processUnlock = async (event, eventName) => {
        console.log(`🚨 [Echo] BINGO! Evento [${eventName}] recebido no terminal:`, event);
        isAuthorized.value = true;
        window.Echo.leaveChannel(channelName);

        try {
            console.log('🚀 [Axios] Disparando o Exchange Final para injetar a sessão...', {
                device_uuid: activeUuid.value,
                token: sessionToken.value
            });

            const response = await axios.post(route('auth.qr.exchange'), {
                device_uuid: activeUuid.value,
                token: sessionToken.value
            });

            console.log('✅ [Axios] Resposta do Servidor:', response.data);

            if (response.data?.success) {
                console.log('🎉 [Axios] Sucesso! Redirecionando a tela em 800ms...');
                setTimeout(() => {
                    window.location.href = response.data.redirect || '/team';
                }, 800);
            } else {
                console.warn('⚠️ [Axios] O servidor não retornou success = true:', response.data);
            }
        } catch (error) {
            console.error('❌ [Axios] Erro crítico ao efetuar handshake final:', error);
            console.error('❌ [Axios] Resposta do erro:', error.response?.data);
            isAuthorized.value = false;
        }
    };

    // 🚀 A REDE DE PESCA: Vamos ouvir 3 variações do nome do evento para garantir!
    window.Echo.channel(channelName)
        .listen('.DeviceQrAuthorized', (e) => processUnlock(e, '.DeviceQrAuthorized'))
        .listen('DeviceQrAuthorized', (e) => processUnlock(e, 'DeviceQrAuthorized'))
        .listen('.App\\Events\\DeviceQrAuthorized', (e) => processUnlock(e, 'FQCN (App\\Events...)'));
};

// Inicializador centralizado
const initDevice = (uuid) => {
    if (!uuid || activeUuid.value === uuid) return;

    activeUuid.value = uuid;

    fetchDeviceDetails();
    generateSession();

    if (rotationInterval) clearInterval(rotationInterval);
    rotationInterval = setInterval(generateSession, 60000);
    // Removemos a chamada do listenForWebSocket daqui, pois ela agora ocorre dentro do generateSession() após criar o Token.
};

onMounted(() => {
    // Tenta pegar de qualquer lugar (Prop, Inertia, LocalStorage)
    const initialUuid = props.deviceUuid || page.props.device?.uuid || localStorage.getItem('brasume_device_uuid') || '';
    if (initialUuid) {
        initDevice(initialUuid);
    }
});

// Fica de olho caso a prop mude ou atrase
watch(() => props.deviceUuid, (newUuid) => {
    if (newUuid) {
        initDevice(newUuid);
    }
});

onUnmounted(() => {
    if (rotationInterval) clearInterval(rotationInterval);
    if (activeUuid.value && window.Echo) window.Echo.leaveChannel(`device.${activeUuid.value}`);
});
</script>
