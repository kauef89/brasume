<template>
    <div class="p-4 pt-28 pb-32 max-w-4xl mx-auto flex flex-col gap-4 select-none">

        <!-- 🚀 BOTÃO TELEPORT NA MESMA ROW DO TÍTULO NO TEAMHEADER -->
        <Teleport v-if="isMounted" to="#team-header-action-portal">
            <PosTeleportButton
                icon="pi-camera"
                color="primary"
                label="Escanear QR de Terminal"
                @click="openScanner"
            />
        </Teleport>

        <!-- LOADING -->
        <div v-if="isLoading" class="flex justify-center py-12">
            <i class="pi pi-spinner pi-spin text-3xl text-primary-500"></i>
        </div>

        <!-- ESTADO VAZIO -->
        <div v-else-if="devices.length === 0" class="text-center py-12 bg-surface-2 rounded-2xl border border-surface-border flex flex-col items-center">
            <i class="pi pi-inbox text-4xl text-surface-muted mb-2 opacity-60"></i>
            <p class="text-sm font-bold text-surface-text">Nenhum terminal cadastrado</p>
            <p class="text-xs text-surface-muted mt-0.5">Homologue uma máquina para ela aparecer aqui.</p>
        </div>

        <!-- LISTA DE TERMINAIS (CARDS COMPACTOS COM SLIDETOCONFIRM) -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
            <div
                v-for="device in devices"
                :key="device.uuid"
                class="bg-surface-3 border rounded-3xl p-3.5 flex flex-col justify-between gap-2.5 relative overflow-hidden transition-all duration-200 shadow-xs"
                :class="[
                    device.is_waiting ? 'border-warning-500/60 bg-warning-500/[0.02]' : 'border-transparent',
                    device.status === 'operating' && !device.is_waiting ? 'border-success-500/30' : ''
                ]"
            >
                <!-- Barra luminosa para terminal em espera -->
                <div v-if="device.is_waiting" class="absolute top-0 left-0 right-0 h-1 bg-warning-500 animate-pulse"></div>

                <!-- LINHA SUPERIOR: ÍCONE + IDENTIFICAÇÃO + STATUS -->
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-xs"
                            :class="device.type === 'kds'
                                ? 'bg-orange-500/10 text-orange-500'
                                : 'bg-primary-500/10 text-primary-500'"
                        >
                            <i class="pi text-sm" :class="device.type === 'kds' ? 'pi-receipt' : 'pi-desktop'"></i>
                        </div>

                        <div class="flex flex-col min-w-0">
                            <span class="font-black text-xs text-surface-text truncate leading-tight">
                                {{ device.name }}
                            </span>
                            <div class="flex items-center gap-1.5 text-[10px] text-surface-muted leading-tight mt-0.5 font-medium">
                                <span>{{ device.type === 'kds' ? 'KDS / Cozinha' : 'POS / Caixa' }}</span>
                                <span class="opacity-40">•</span>
                                <span class="font-mono text-[9px] opacity-70" title="UUID do Dispositivo">
                                    {{ device.uuid.split('-')[0] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BADGE DE STATUS -->
                    <span
                        v-if="device.is_waiting"
                        class="text-[9px] font-black uppercase tracking-wider text-warning-500 bg-warning-500/10 border border-warning-500/20 px-2 py-0.5 rounded-full flex items-center gap-1 shrink-0"
                    >
                        <i class="pi pi-bell text-[8px] animate-bounce"></i>
                        Aguardando
                    </span>
                    <span
                        v-else-if="device.status === 'operating'"
                        class="text-[9px] font-black uppercase tracking-wider text-success-500 bg-success-500/10 border border-success-500/20 px-2 py-0.5 rounded-full flex items-center gap-1 shrink-0"
                    >
                        <i class="pi pi-circle-fill text-[6px] animate-pulse"></i>
                        Operando
                    </span>
                    <span
                        v-else
                        class="text-[9px] font-black uppercase tracking-wider text-surface-muted bg-surface-1 border border-surface-border px-2 py-0.5 rounded-full flex items-center gap-1 shrink-0"
                    >
                        <i class="pi pi-lock text-[8px]"></i>
                        Bloqueada
                    </span>
                </div>

                <!-- LINHA DO OPERADOR (COMPACTA) -->
                <div
                    v-if="device.operator"
                    class="flex items-center justify-between px-2.5 py-1.5 bg-surface-1 border border-surface-border/50 rounded-xl"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-5 h-5 rounded-full bg-success-500/15 text-success-500 flex items-center justify-center text-[9px] font-black uppercase shrink-0">
                            {{ device.operator.name.charAt(0) }}
                        </div>
                        <span class="text-[11px] font-bold text-surface-text truncate">
                            {{ formatName(device.operator.name) }}
                        </span>
                    </div>
                    <span class="text-[9px] uppercase tracking-wider text-surface-muted font-bold shrink-0 ml-2">
                        {{ device.operator.role || 'Operador' }}
                    </span>
                </div>

                <!-- AÇÕES COM SLIDETOCONFIRM -->
                <div class="pt-0.5">
                    <!-- 🛡️ 1. SLIDE PARA LIBERAR / AUTORIZAR ACESSO -->
                    <SlideToConfirm
                        v-if="device.is_waiting || device.status !== 'operating'"
                        :text="device.is_waiting ? 'Deslize p/ Autorizar' : 'Deslize p/ Liberar'"
                        success-text="Liberado!"
                        icon="pi-unlock"
                        :color="device.is_waiting ? 'warning' : 'primary'"
                        :disabled="isSubmitting === device.uuid"
                        :is-loading="isSubmitting === device.uuid"
                        format="long"
                        @confirm="toggleDeviceAccess(device)"
                        class="!h-10 text-[10px] w-full"
                    />

                    <!-- 🛑 2. SLIDE PARA BLOQUEAR SESSÃO OPERANTE -->
                    <SlideToConfirm
                        v-else
                        text="Deslize p/ Bloquear"
                        success-text="Bloqueado!"
                        icon="pi-lock"
                        color="danger"
                        :disabled="isSubmitting === device.uuid"
                        :is-loading="isSubmitting === device.uuid"
                        format="long"
                        @confirm="toggleDeviceAccess(device)"
                        class="!h-10 text-[10px] w-full"
                    />
                </div>
            </div>
        </div>

        <!-- MODAL DA CÂMERA (QR SCANNER) -->
        <div v-if="isScanning" class="fixed inset-0 z-[9999] bg-black flex flex-col animate-in fade-in zoom-in-95 duration-300">
            <div class="absolute top-0 w-full p-4 flex justify-between items-center z-10 bg-gradient-to-b from-black/80 to-transparent">
                <div class="text-white">
                    <h3 class="font-black text-lg">Escanear Terminal</h3>
                    <p class="text-xs opacity-80">Aponte para o QR Code na tela do computador</p>
                </div>
                <button
                    type="button"
                    @click="closeScanner"
                    class="w-10 h-10 rounded-full bg-surface-800/80 text-white flex items-center justify-center outline-none border-none cursor-pointer"
                >
                    <i class="pi pi-times"></i>
                </button>
            </div>

            <div class="flex-1 w-full bg-black flex items-center justify-center relative overflow-hidden">
                <qrcode-stream @detect="onDetectQrCode" @error="onCameraError" class="h-full w-full object-cover"></qrcode-stream>
                <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                    <div class="w-64 h-64 border-2 border-white/50 rounded-3xl relative">
                        <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-primary-500 rounded-tl-3xl"></div>
                        <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-primary-500 rounded-tr-3xl"></div>
                        <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-primary-500 rounded-bl-3xl"></div>
                        <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-primary-500 rounded-br-3xl"></div>
                    </div>
                </div>
            </div>
        </div>

        <QuickUnlockSheet
            v-model:visible="isQuickUnlockVisible"
            :device-uuid="scannedDeviceUuid"
            :token="activeSessionToken"
            @unlocked="fetchDevices"
        />

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { QrcodeStream } from 'vue-qrcode-reader';
import QuickUnlockSheet from '@/Pages/Gateway/QuickUnlockSheet.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';
import PosTeleportButton from '@/Components/UI/PosTeleportButton.vue';

const toast = useToast();
const devices = ref([]);
const isLoading = ref(true);
const isSubmitting = ref(null);
const isMounted = ref(false);
let pollInterval = null;

const isScanning = ref(false);
const isQuickUnlockVisible = ref(false);
const activeSessionToken = ref('');
const scannedDeviceUuid = ref('');

const formatName = (name) => {
    if (!name) return 'Operador';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0];
    return `${parts[0]} ${parts[parts.length - 1].charAt(0)}.`;
};

const fetchDevices = async () => {
    try {
        const response = await axios.get(route('auth.remote.fleet'));
        devices.value = response.data;
    } catch (error) {
        console.error('Erro ao buscar frota:', error);
    } finally {
        isLoading.value = false;
    }
};

const toggleDeviceAccess = async (device) => {
    isSubmitting.value = device.uuid;

    try {
        if (device.status === 'operating' && !device.is_waiting) {
            await axios.post(route('team.remote-unlock.lock'), { device_uuid: device.uuid });
            device.status = 'locked';
            device.operator = null;
            toast.add({
                severity: 'info',
                summary: 'Terminal Bloqueado',
                detail: `A sessão de ${device.name} foi encerrada.`,
                life: 3000
            });
        } else {
            await axios.post(route('team.remote-unlock.approve'), { device_uuid: device.uuid });
            device.is_waiting = false;
            device.status = 'operating';
            toast.add({
                severity: 'success',
                summary: 'Terminal Liberado',
                detail: `${device.name} liberado com sucesso.`,
                life: 3000
            });
        }

        await fetchDevices();

    } catch (error) {
        const msg = error.response?.data?.error || 'Falha na comunicação com o terminal.';
        toast.add({ severity: 'error', summary: 'Ação Interrompida', detail: msg, life: 3000 });
    } finally {
        isSubmitting.value = null;
    }
};

const openScanner = () => { isScanning.value = true; };
const closeScanner = () => { isScanning.value = false; };

const onDetectQrCode = async (detectedCodes) => {
    if (!detectedCodes || detectedCodes.length === 0) return;
    const scannedText = detectedCodes[0].rawValue;
    if (!scannedText) return;

    closeScanner();

    let authToken = scannedText;
    if (scannedText.includes('/')) {
        const urlParts = scannedText.split('/');
        authToken = urlParts[urlParts.length - 1];
    } else if (scannedText.includes('|')) {
        authToken = scannedText.split('|')[1];
    }

    try {
        const response = await axios.post(route('auth.qr.authorize'), {
            token: authToken
        });

        toast.add({
            severity: 'success',
            summary: 'Terminal Liberado',
            detail: response.data?.message || 'Acesso autorizado com sucesso!',
            life: 3000
        });

        await fetchDevices();

    } catch (error) {
        console.error('Erro na autorização do QR Code:', error);
        toast.add({
            severity: 'error',
            summary: 'Acesso Negado',
            detail: error.response?.data?.error || 'QR Code inválido.',
            life: 4000
        });
    }
};

const onCameraError = (error) => {
    closeScanner();
    let errorMsg = 'Erro desconhecido ao abrir a câmera.';
    if (error.name === 'NotAllowedError') errorMsg = 'Permissão de câmera necessária.';
    else if (error.name === 'NotFoundError') errorMsg = 'Nenhuma câmera encontrada no dispositivo.';
    else if (error.name === 'NotSupportedError') errorMsg = 'Acesso à câmera requer conexão segura (HTTPS).';

    toast.add({ severity: 'error', summary: 'Câmera Indisponível', detail: errorMsg, life: 4000 });
};

onMounted(() => {
    fetchDevices();
    pollInterval = setInterval(fetchDevices, 10000);

    // Valida que o container existe no DOM antes de montar o Teleport
    nextTick(() => {
        isMounted.value = !!document.getElementById('team-header-action-portal');
    });
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>
