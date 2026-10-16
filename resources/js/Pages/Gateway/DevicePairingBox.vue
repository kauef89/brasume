<template>
    <div class="flex flex-col items-center justify-center p-6 bg-surface-0 border border-surface-border rounded-3xl shadow-sm max-w-sm mx-auto text-center w-full animate-in fade-in zoom-in duration-300">

        <div class="bg-warning-500/10 p-3 rounded-2xl text-warning-600 mb-4">
            <i class="pi pi-desktop text-3xl"></i>
        </div>

        <h3 class="text-lg font-black text-surface-text mb-1">Máquina Desconhecida</h3>
        <p class="text-xs font-medium text-surface-muted mb-6">
            Este terminal ainda não foi cadastrado nos dispositivos confiáveis. Cole o UUID gerado no painel administrativo para autorizar esta máquina.
        </p>

        <div class="w-full relative mb-4">
            <InputText
                v-model="deviceUuid"
                placeholder="Ex: 9b2d45c1-e8..."
                class="w-full text-center font-mono text-sm py-3 rounded-xl border-surface-border focus:border-primary-500"
                :disabled="isLoading"
                @keyup.enter="verifyDevice"
            />
        </div>

        <button
            @click="verifyDevice"
            :disabled="isLoading || !deviceUuid"
            class="w-full bg-primary-500 hover:bg-primary-600 disabled:opacity-50 text-white font-black text-sm py-3.5 rounded-xl shadow-md transition-all outline-none flex items-center justify-center gap-2"
        >
            <i :class="isLoading ? 'pi pi-spin pi-spinner' : 'pi pi-check-circle'"></i>
            Vincular Dispositivo
        </button>

        <!-- 🚀 NOVO: Atalho Rápido com QR Code -->
        <div class="w-full mt-6 pt-6 border-t border-surface-border flex flex-col items-center">
            <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted mb-3">Atalho para o Gestor</span>
            <div class="bg-white p-2 rounded-xl shadow-sm border border-surface-border mb-3">
                <qrcode-vue :value="adminUrl" :size="120" level="M" render-as="svg" />
            </div>
            <p class="text-[11px] text-surface-muted font-medium px-4">
                Escaneie pelo celular para acessar rapidamente a rota <strong class="text-surface-text">/admin/devices</strong> e cadastrar esta máquina na hora.
            </p>
        </div>

        <p v-if="errorMessage" class="text-danger-500 text-xs font-bold mt-4 animate-in fade-in slide-in-from-top-2">
            <i class="pi pi-exclamation-triangle"></i> {{ errorMessage }}
        </p>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import InputText from 'primevue/inputtext';
import QrcodeVue from 'qrcode.vue';

const emit = defineEmits(['paired']);
const deviceUuid = ref('');
const isLoading = ref(false);
const errorMessage = ref('');

// 🚀 A URL é dinâmica, baseada no domínio atual da lanchonete
const adminUrl = computed(() => window.location.origin + '/admin/devices');

const verifyDevice = async () => {
    if (!deviceUuid.value) return;

    isLoading.value = true;
    errorMessage.value = '';

    try {
        // 🚀 Rota Ziggy mapeada corretamente
        const response = await axios.post(route('auth.qr.validate'), {
            device_uuid: deviceUuid.value.trim()
        });

        if (response.data.valid) {
            // Salva no cofre do navegador (localStorage) permanentemente
            localStorage.setItem('brasume_device_uuid', deviceUuid.value.trim());
            localStorage.setItem('brasume_device_name', response.data.device.name);
            localStorage.setItem('brasume_device_type', response.data.device.type);

            emit('paired', response.data.device);
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'UUID inválido ou máquina não autorizada.';
    } finally {
        isLoading.value = false;
    }
};
</script>
