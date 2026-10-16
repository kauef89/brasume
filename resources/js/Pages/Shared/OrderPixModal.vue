<template>
    <AdaptiveModal
        v-model:visible="isVisible"
        class="p-6 max-w-sm mx-auto"
    >
        <!-- TOPO: Título e Status -->
        <div class="flex flex-col items-center text-center mb-5">
            <span class="text-[10px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-3 py-1 rounded-full mb-2 flex items-center gap-1.5">
                <i class="pi pi-bolt text-[10px]"></i> PIX Dinâmico Sicredi
            </span>
            <h2 class="text-xl font-black text-surface-text leading-tight">
                Pedido #{{ order?.id }}
            </h2>
            <span class="text-sm font-bold text-surface-muted mt-0.5">
                Valor: <strong class="text-emerald-500 text-base">R$ {{ formatCurrency(pixAmount || order?.total) }}</strong>
            </span>
        </div>

        <!-- CONTEÚDO PRINCIPAL: QR CODE OU SUCESSO -->
        <div class="flex flex-col items-center justify-center relative min-h-[220px]">
            <!-- SUCESSO CONFIRMADO -->
            <div v-if="isPaidSuccess" class="flex flex-col items-center justify-center py-8 animate-in zoom-in-95 duration-300">
                <div class="w-20 h-20 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center mb-3">
                    <i class="pi pi-check text-4xl font-bold"></i>
                </div>
                <h3 class="text-lg font-black text-emerald-500">PIX Confirmado!</h3>
                <p class="text-xs text-surface-muted mt-1 font-medium">Baixa automática realizada com sucesso.</p>
            </div>

            <!-- CARREGANDO / GERANDO -->
            <div v-else-if="isLoading" class="flex flex-col items-center justify-center py-12">
                <i class="pi pi-spinner pi-spin text-4xl text-primary-500 mb-3"></i>
                <span class="text-xs font-bold text-surface-muted">Gerando cobrança no Sicredi...</span>
            </div>

            <!-- ERRO NA GERAÇÃO -->
            <div v-else-if="errorMessage" class="flex flex-col items-center justify-center text-center p-4 bg-danger-500/10 border border-danger-500/20 rounded-2xl">
                <i class="pi pi-exclamation-triangle text-3xl text-danger-500 mb-2"></i>
                <span class="text-xs font-bold text-danger-600">{{ errorMessage }}</span>
                <button
                    @click="generatePix"
                    type="button"
                    class="mt-3 px-4 py-2 rounded-xl bg-danger-500 text-white font-black text-xs uppercase tracking-wider cursor-pointer border-none"
                >
                    Tentar Novamente
                </button>
            </div>

            <!-- QR CODE ATIVO -->
            <div v-else class="flex flex-col items-center w-full">
                <!-- Contador Regressivo -->
                <div class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold tracking-wider mb-3"
                     :class="timeLeft < 60 ? 'bg-danger-500/10 text-danger-500 animate-pulse' : 'bg-surface-2 text-surface-muted'">
                    <i class="pi pi-clock text-xs"></i>
                    <span>Expira em {{ formattedTime }}</span>
                </div>

                <!-- Box do QR Code -->
                <div class="p-4 bg-white rounded-2xl shadow-sm border border-surface-border/50 mb-4">
                    <qrcode-vue
                        v-if="qrCodeString"
                        :value="qrCodeString"
                        :size="180"
                        level="M"
                        class="rounded-lg"
                    />
                </div>

                <!-- Botão Copia e Cola -->
                <button
                    @click="copyCode"
                    type="button"
                    class="w-full h-11 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-text font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-colors border border-surface-border cursor-pointer outline-none"
                >
                    <i class="pi" :class="copied ? 'pi-check text-emerald-500' : 'pi-copy'"></i>
                    <span>{{ copied ? 'Código Copiado!' : 'Copiar PIX Copia e Cola' }}</span>
                </button>
            </div>
        </div>

        <!-- RODAPÉ: CANCELAR / FECHAR -->
        <div class="mt-4 pt-3 border-t border-surface-border/40 flex justify-end">
            <button
                @click="isVisible = false"
                type="button"
                class="w-full h-10 rounded-xl bg-surface-1 text-surface-muted hover:text-surface-text font-bold text-xs uppercase tracking-wider transition-colors border-none cursor-pointer"
            >
                Fechar
            </button>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import axios from 'axios';
import QrcodeVue from 'qrcode.vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import { useTenantEcho } from '@/Composables/useTenantEcho';

const props = defineProps({
    visible: Boolean,
    order: { type: Object, default: () => null }
});

const emit = defineEmits(['update:visible', 'payment-success']);

const isVisible = computed({
    get: () => props.visible,
    set: (v) => emit('update:visible', v)
});

const isLoading = ref(false);
const isPaidSuccess = ref(false);
const qrCodeString = ref('');
const pixAmount = ref(0);
const errorMessage = ref('');
const copied = ref(false);
const timeLeft = ref(600);
let timer = null;

const formattedTime = computed(() => {
    const m = Math.floor(timeLeft.value / 60);
    const s = timeLeft.value % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const startCountdown = () => {
    clearInterval(timer);
    timeLeft.value = 600;
    timer = setInterval(() => {
        if (timeLeft.value > 0) timeLeft.value--;
        else clearInterval(timer);
    }, 1000);
};

const generatePix = async () => {
    if (!props.order?.id) return;
    isLoading.value = true;
    errorMessage.value = '';
    qrCodeString.value = '';
    isPaidSuccess.value = false;

    try {
        const response = await axios.post(route('operations.orders.generate-pix', props.order.id));
        if (response.data.success) {
            qrCodeString.value = response.data.qr_code;
            pixAmount.value = response.data.amount;
            startCountdown();
        } else {
            errorMessage.value = response.data.message || 'Erro ao gerar PIX.';
        }
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Falha ao comunicar com o servidor.';
    } finally {
        isLoading.value = false;
    }
};

const copyCode = async () => {
    if (!qrCodeString.value) return;
    try {
        await navigator.clipboard.writeText(qrCodeString.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 3000);
    } catch (e) {}
};

// 📡 Reconhecimento em Tempo Real via Reverb
const handleOrderUpdated = (incoming) => {
    const o = incoming.order || incoming;
    if (Number(o.id) === Number(props.order?.id) && o.payment_status === 'paid') {
        isPaidSuccess.value = true;
        clearInterval(timer);
        if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
        emit('payment-success', o);
        setTimeout(() => {
            isVisible.value = false;
        }, 2200);
    }
};

watch(isVisible, (val) => {
    if (val && props.order) {
        generatePix();
        const echo = useTenantEcho();
        echo.listenChannel('operations', '.order.updated', handleOrderUpdated);
        echo.listenChannel('operations', '.OrderUpdated', handleOrderUpdated);
    } else {
        clearInterval(timer);
        qrCodeString.value = '';
        isPaidSuccess.value = false;
    }
});

onUnmounted(() => {
    clearInterval(timer);
});

const formatCurrency = (v) => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>
