<template>
    <AdaptiveModal :visible="visible" @close="handleClose">
        <div class="flex flex-col items-center text-center w-full py-2">

            <div class="w-20 h-20 bg-warning-500/10 text-warning-600 rounded-full flex items-center justify-center mb-4 relative shadow-inner">
                <i class="pi pi-desktop text-3xl"></i>
                <span class="absolute -top-1 -right-1 flex h-4 w-4">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-warning-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-4 w-4 bg-warning-500"></span>
                </span>
            </div>

            <h2 class="text-2xl font-black text-surface-text mb-1">Desbloquear Terminal</h2>

            <div class="w-full bg-surface-2 border border-surface-border rounded-2xl p-4 mb-6 text-left flex flex-col gap-3 min-h-[70px] justify-center relative overflow-hidden">
                <div v-if="isLoadingDevice" class="absolute inset-0 flex items-center justify-center bg-surface-2 z-10">
                    <i class="pi pi-spinner pi-spin text-xl text-primary-500"></i>
                </div>
                <template v-else-if="device">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Máquina</span>
                        <span class="text-sm font-black text-surface-text">{{ device.name }}</span>
                    </div>
                    <div class="pt-2 border-t border-surface-border flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Perfil</span>
                        <span class="text-xs font-bold uppercase tracking-wider" :class="device.type === 'kds' ? 'text-orange-500' : 'text-primary-500'">
                            {{ device.type === 'kds' ? 'Cozinha' : 'Caixa' }}
                        </span>
                    </div>
                </template>
                <div v-else class="flex flex-col items-center justify-center text-center">
                    <span class="text-xs font-bold text-danger-500">Dispositivo não identificado</span>
                </div>
            </div>

            <div class="w-full max-w-xs mb-4" :class="{'opacity-50 pointer-events-none': !device || isLoadingDevice}">
                <SlideToConfirm
                    text="Deslize para Autorizar"
                    success-text="Terminal Liberado!"
                    :is-loading="isSubmitting"
                    :is-success="isSuccess"
                    @confirm="handleConfirm"
                />
            </div>

            <button @click="handleClose" :disabled="isSubmitting" class="text-xs font-black text-surface-muted hover:text-surface-text uppercase tracking-widest outline-none bg-transparent border-none cursor-pointer transition-colors">
                Cancelar
            </button>

            <div class="mt-6 w-full p-3 bg-surface-950 text-success-400 font-mono text-[9px] text-left rounded-lg overflow-hidden break-words max-h-40 overflow-y-auto shadow-inner">
                <div class="font-bold text-white mb-1 border-b border-surface-700 pb-1">Sonda de Diagnóstico:</div>
                <div v-for="(log, idx) in debugLogs" :key="idx" class="mb-1 leading-tight">
                    > {{ log }}
                </div>
            </div>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';

const props = defineProps({
    visible: { type: Boolean, required: true },
    token: { type: String, default: '' } // 🚀 O Token volátil capturado
});

const emit = defineEmits(['update:visible', 'unlocked', 'close']);

const toast = useToast();
const isSubmitting = ref(false);
const isSuccess = ref(false);
const isLoadingDevice = ref(false);
const device = ref(null);

const debugLogs = ref([]);
const addLog = (msg) => { debugLogs.value.push(msg); };

const fetchDeviceDetails = async () => {
    isLoadingDevice.value = true;
    device.value = null;

    const tokenPreview = props.token ? props.token.substring(0,8) : 'vazio';
    addLog(`Iniciando fetch para Sessão: ${tokenPreview}...`);

    try {
        addLog(`Disparando POST para buscar dados da sessão remota...`);

        // 🚀 Busca pelo Token da Sessão ou pelo UUID (Fallback)
        const response = await axios.post(route('auth.qr.validate'), {
            session_token: props.token,
            device_uuid: props.token
        });

        addLog(`Resposta recebida! Status: ${response.status}`);

        if (response.data && response.data.valid) {
            device.value = response.data.device;
            addLog(`Máquina identificada: ${device.value.name}`);
        } else {
            addLog(`Sessão não encontrada ou expirada: ${JSON.stringify(response.data)}`);
        }
    } catch (error) {
        addLog(`ERRO CATCH: ${error.message}`);
        if (error.response) {
            addLog(`HTTP ${error.response.status}: ${JSON.stringify(error.response.data)}`);
        }
    } finally {
        isLoadingDevice.value = false;
        addLog('Fim do fluxo de fetch de segurança.');
    }
};

watch(() => [props.visible, props.token], ([newVisible, newToken]) => {
    addLog(`Watch disparado. Visible: ${newVisible} | Token: ${newToken ? 'Presente' : 'Vazio'}`);
    if (newVisible && newToken) {
        fetchDeviceDetails();
    }
}, { immediate: true });

const handleClose = () => {
    if (isSubmitting.value) return;
    emit('update:visible', false);
    emit('close');
};

const handleConfirm = async () => {
    isSubmitting.value = true;
    addLog('Gestor autorizou. Disparando approve...');

    try {
        // Envia o UUID real da máquina ou o token para aprovação
        await axios.post(route('team.remote-unlock.approve'), {
            device_uuid: device.value?.uuid || props.token,
            session_token: props.token
        });

        addLog('Approve com sucesso!');
        isSubmitting.value = false;
        isSuccess.value = true;

        setTimeout(() => {
            toast.add({ severity: 'success', summary: 'Terminal Liberado', detail: 'A máquina foi desbloqueada!', life: 3000 });
            emit('unlocked');
            emit('update:visible', false);
            setTimeout(() => { isSuccess.value = false; }, 300);
        }, 800);

    } catch (error) {
        isSubmitting.value = false;
        addLog(`ERRO APPROVE: ${error.response ? error.response.status : error.message}`);
        toast.add({ severity: 'error', summary: 'Falha na Autorização', detail: 'Erro ao autorizar terminal.', life: 4000 });
    }
};
</script>
