<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in duration-300">
        <!-- BACKDROP -->
        <div class="absolute inset-0" @click="close"></div>

        <!-- CONTAINER MODAL -->
        <div
            class="relative z-10 w-full bg-surface-900/95 border border-surface-700/80 rounded-[2rem] p-6 md:p-8 shadow-2xl backdrop-blur-2xl transition-all duration-500"
            :class="isDeviceAuthorized ? 'max-w-md' : 'max-w-sm'"
        >
            <!-- BOTÃO FECHAR -->
            <button
                @click="close"
                class="absolute top-5 right-5 w-9 h-9 rounded-full bg-surface-800 border border-surface-700 text-surface-400 hover:text-white hover:bg-surface-700 flex items-center justify-center transition-all outline-none"
            >
                <i class="pi pi-times text-xs"></i>
            </button>

            <!-- ETAPA 1: VERIFICANDO O DISPOSITIVO -->
            <div v-if="isCheckingDevice" class="flex flex-col items-center justify-center py-10 gap-4">
                <i class="pi pi-spin pi-spinner text-primary-500 text-3xl"></i>
                <p class="text-surface-400 font-medium text-xs">Verificando autorização do terminal...</p>
            </div>

            <!-- ETAPA 2: DISPOSITIVO NÃO AUTORIZADO (EMPARELHAMENTO) -->
            <div v-else-if="!isDeviceAuthorized" class="flex flex-col gap-4">
                <div class="text-center pr-6">
                    <h2 class="text-lg font-black text-white tracking-tight">Emparelhar Terminal</h2>
                    <p class="text-surface-400 text-xs mt-0.5">Insira a chave gerada no painel Filament</p>
                </div>

                <DevicePairingBox @paired="handleDevicePaired" />
            </div>

            <!-- ETAPA 3: DISPOSITIVO HOMOLOGADO -> QR CODE LOGIN -->
            <div v-else class="flex flex-col gap-5">
                <!-- Status do Dispositivo -->
                <div class="bg-success-500/10 border border-success-500/30 p-4 rounded-2xl flex flex-col gap-3 text-success-400 shadow-inner">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-success-500/20 rounded-full flex items-center justify-center text-success-500">
                                <i class="pi pi-shield text-xl"></i>
                            </div>
                            <div class="flex flex-col text-left">
                                <span class="text-[9px] text-success-500/80 uppercase tracking-widest font-black">Terminal Homologado</span>
                                <span class="font-bold text-sm leading-tight text-success-600">{{ deviceData?.name || 'Máquina Principal' }}</span>
                            </div>
                        </div>
                        <button
                            @click="unpairDevice"
                            title="Desvincular máquina"
                            class="text-[10px] text-surface-400 hover:text-danger-500 underline font-medium outline-none transition-colors"
                        >
                            Trocar chave
                        </button>
                    </div>

                    <!-- 🚀 NOVO: TIPO DE DISPOSITIVO VISÍVEL AQUI -->
                    <div class="flex items-center gap-2 bg-white/50 border border-success-500/20 px-3 py-1.5 rounded-lg w-fit mt-1">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-success-600">Configuração:</span>
                        <span class="text-xs font-black uppercase text-success-700">{{ deviceData?.type || 'POS' }}</span>
                    </div>
                </div>

                <div class="w-full">
                    <QrLoginBox />
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import DevicePairingBox from './DevicePairingBox.vue';
import QrLoginBox from './QrLoginBox.vue';

const props = defineProps({
    visible: { type: Boolean, default: false }
});

const emit = defineEmits(['update:visible', 'close']);

const isCheckingDevice = ref(true);
const isDeviceAuthorized = ref(false);
const deviceData = ref(null);

const close = () => {
    emit('update:visible', false);
    emit('close');
};

const checkDeviceAuthorization = async () => {
    const uuid = localStorage.getItem('brasume_device_uuid');

    if (!uuid) {
        isDeviceAuthorized.value = false;
        isCheckingDevice.value = false;
        return;
    }

    try {
        const response = await axios.post(route('auth.qr.validate'), { device_uuid: uuid });
        if (response.data.valid) {
            isDeviceAuthorized.value = true;
            deviceData.value = response.data.device;
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
    isDeviceAuthorized.value = false;
};

const handleDevicePaired = (devicePayload) => {
    if (devicePayload && (devicePayload.uuid || devicePayload.device_uuid)) {
        const key = devicePayload.uuid || devicePayload.device_uuid;
        localStorage.setItem('brasume_device_uuid', key);
        deviceData.value = devicePayload;
        isDeviceAuthorized.value = true;
    } else {
        window.location.reload();
    }
};

const unpairDevice = () => {
    if (confirm('Deseja remover o emparelhamento deste terminal?')) {
        handleUnauthorized();
    }
};

watch(() => props.visible, (newVal) => {
    if (newVal) {
        isCheckingDevice.value = true;
        checkDeviceAuthorization();
    }
}, { immediate: true });
</script>
