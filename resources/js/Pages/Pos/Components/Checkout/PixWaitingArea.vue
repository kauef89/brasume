<template>
    <div class="flex flex-col items-center w-full animate-in fade-in slide-in-from-bottom-4 duration-500">

        <div class="flex items-center gap-2 mb-6 px-4 py-2 rounded-full font-black text-sm tracking-widest uppercase transition-colors"
             :class="timeLeft < 60 ? 'bg-danger-500/20 text-danger-400 animate-pulse' : 'bg-primary-500/20 text-primary-300'">
            <i class="pi pi-clock"></i>
            <span>{{ formattedTime }}</span>
        </div>

        <div class="text-center mb-6">
            <h3 class="c-title !text-2xl">Recebimento via PIX</h3>
            <p class="c-text-muted mt-1 max-w-[250px] mx-auto">Apresente o QR Code ao cliente.</p>
        </div>

        <div class="relative p-6 bg-white rounded-3xl mb-6 shadow-[0_0_40px_rgba(255,255,255,0.1)]">
            <div v-if="isLoading || isExpired" class="absolute inset-0 z-10 bg-black/90 rounded-3xl flex flex-col scale-101 items-center justify-center backdrop-blur-sm">
                <template v-if="isLoading">
                    <i class="pi pi-spinner pi-spin text-4xl text-white mb-2"></i>
                    <span class="font-bold text-white/80">Gerando cobrança...</span>
                </template>
                <template v-else-if="isExpired">
                    <i class="pi pi-times-circle text-4xl text-danger-500 mb-2"></i>
                    <span class="font-bold text-white/80">O código expirou.</span>
                </template>
            </div>

            <qrcode-vue v-if="qrCodeString" :value="qrCodeString" :size="180" level="M" class="rounded-lg" />
            <div v-else class="w-[180px] h-[180px] bg-surface-2 rounded-lg flex items-center justify-center">
                <i class="pi pi-spinner pi-spin text-4xl text-surface-muted"></i>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 w-full mb-4">
            <button @click="copyCode" :disabled="isLoading || isExpired || !qrCodeString" class="h-12 rounded-xl c-glass-card-interactive !border-white/20 text-white font-black text-[10px] uppercase tracking-widest outline-none cursor-pointer flex items-center justify-center gap-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="pi" :class="copied ? 'pi-check text-emerald-400' : 'pi-copy'"></i>
                {{ copied ? 'Copiado!' : 'Copia e Cola' }}
            </button>
            <button @click="printPixCode" :disabled="isLoading || isExpired || !qrCodeString" class="h-12 rounded-xl bg-primary-500/20 text-primary-400 font-black text-[10px] uppercase tracking-widest hover:bg-primary-500/30 transition-colors border border-primary-500/30 outline-none cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="pi pi-print"></i>
                Imprimir Guia
            </button>
        </div>

        <div class="flex items-center justify-between w-full gap-3 mt-2 border-t border-white/10 pt-4">
            <button @click="$emit('hide')" class="px-6 h-10 rounded-full text-surface-muted hover:text-white font-black text-sm uppercase tracking-widest transition-colors outline-none cursor-pointer border-none bg-transparent">
                Voltar / Minimizar
            </button>
            <button @click="$emit('refresh')" class="flex-1 h-10 rounded-xl text-white bg-white/10 hover:bg-white/20 font-black text-[10px] uppercase tracking-widest transition-colors outline-none cursor-pointer border-none">
                Gerar Novo Código
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import QrcodeVue from 'qrcode.vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    orderId: { type: [Number, String], required: true },
    qrCodeString: { type: String, default: null },
    txid: { type: String, default: null },
    expiresAt: { type: [String, Date], default: null },
    isLoading: { type: Boolean, default: false }
});

const emit = defineEmits(['hide', 'refresh', 'expired']);
const toast = useToast();

const calculateRemainingSeconds = () => {
    if (props.expiresAt) {
        // 🚀 Remove Timezone Clash: Corta o 'Z' ou '-03:00' para o JS forçar a leitura local
        const safeStr = String(props.expiresAt).replace(/Z|[+-]\d{2}:\d{2}$/, '');
        const expiry = new Date(safeStr).getTime();
        const diff = Math.floor((expiry - Date.now()) / 1000);
        if (diff > 0 && diff <= 3600) return diff; // Se for válido (até 1 hora), retorna
    }
    return 600; // Fallback robusto de 10 min
};

const timeLeft = ref(calculateRemainingSeconds());
const timer = ref(null);
const copied = ref(false);
const isExpired = computed(() => timeLeft.value <= 0);

const formattedTime = computed(() => {
    const m = Math.floor(timeLeft.value / 60);
    const s = timeLeft.value % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const startTimer = () => {
    clearInterval(timer.value);
    timeLeft.value = calculateRemainingSeconds();
    if (timeLeft.value <= 0) { emit('expired'); return; }

    timer.value = setInterval(() => {
        if (timeLeft.value > 0) timeLeft.value--;
        else { clearInterval(timer.value); emit('expired'); }
    }, 1000);
};

const copyCode = async () => {
    if (!props.qrCodeString) return;
    try {
        await navigator.clipboard.writeText(props.qrCodeString);
        copied.value = true;
        setTimeout(() => copied.value = false, 3000);
    } catch (err) {}
};

const printPixCode = () => {
    router.post(route('operations.orders.print-pix', props.orderId), {
        qr_code: props.qrCodeString,
        txid: props.txid,
        expires_at: props.expiresAt
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => toast.add({ severity: 'success', summary: 'Enviado', detail: 'Guia PIX enviada para impressão.', life: 3000 })
    });
};

onMounted(() => { if (props.qrCodeString) startTimer(); });
watch(() => props.qrCodeString, (n) => { if (n) startTimer(); });
onUnmounted(() => clearInterval(timer.value));
</script>
