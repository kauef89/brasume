<template>
    <div class="w-full flex-1 flex flex-col justify-center items-center px-6 pb-8 pt-4">

        <div class="flex items-center gap-2 mb-6 px-4 py-2 rounded-full font-black text-sm tracking-widest uppercase transition-colors"
             :class="isExpired ? 'bg-danger-500/20 text-danger-400 animate-pulse' : 'bg-primary-500/20 text-primary-300'">
            <i class="pi pi-clock"></i>
            <span>{{ formattedTime }}</span>
        </div>

        <div class="relative p-6 bg-white rounded-3xl mb-8 shadow-[0_0_40px_rgba(255,255,255,0.1)]">
            <div v-if="resolvedLoading || isExpired" class="absolute inset-0 z-10 bg-black/90 rounded-3xl flex flex-col scale-101 items-center justify-center backdrop-blur-sm">
                <template v-if="resolvedLoading">
                    <i class="pi pi-spinner pi-spin text-4xl text-white mb-2"></i>
                    <span class="text-xs font-bold text-white/70">Gerando PIX...</span>
                </template>
                <template v-else-if="isExpired">
                    <i class="pi pi-times-circle text-4xl text-danger-500 mb-2"></i>
                    <span class="font-bold text-white/80">Código Expirado</span>
                </template>
            </div>

            <!-- QR Code do PIX -->
            <qrcode-vue
                v-if="resolvedQrCode"
                :value="resolvedQrCode"
                :size="180"
                level="M"
                class="rounded-lg"
            />
            <div v-else class="w-[180px] h-[180px] bg-neutral-100 rounded-lg animate-pulse flex items-center justify-center">
                <i class="pi pi-qrcode text-neutral-300 text-4xl"></i>
            </div>
        </div>

        <!-- Botão Copia e Cola -->
        <button
            type="button"
            @click="copyCode"
            :disabled="resolvedLoading || isExpired || !resolvedQrCode"
            class="w-full h-14 rounded-xl c-glass-card-interactive !border-white/20 text-white font-black text-xs uppercase tracking-widest outline-none cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <i class="pi" :class="copied ? 'pi-check text-emerald-400' : 'pi-copy'"></i>
            {{ copied ? 'Copiado!' : 'Copiar PIX Copia e Cola' }}
        </button>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import QrcodeVue from 'qrcode.vue';

const props = defineProps({
    pendingOrderId: [String, Number],
    pixData: [Object, String],
    qrCodeString: String,
    txid: String,
    isLoading: Boolean,
    isGeneratingPix: Boolean
});

const emit = defineEmits(['payment-approved', 'update-header']);
const page = usePage();

const timeLeft = ref(3600);
const timer = ref(null);
const pollInterval = ref(null);
const copied = ref(false);

const resolvedQrCode = computed(() => {
    if (props.qrCodeString) return props.qrCodeString;
    if (!props.pixData) return '';
    if (typeof props.pixData === 'string') return props.pixData;

    return props.pixData.qr_code
        || props.pixData.pixCopiaECola
        || props.pixData.copia_e_cola
        || props.pixData.qrCode
        || '';
});

const resolvedLoading = computed(() => {
    return !!(props.isLoading || props.isGeneratingPix);
});

const isExpired = computed(() => timeLeft.value <= 0);

const formattedTime = computed(() => {
    const minutes = String(Math.floor(timeLeft.value / 60)).padStart(2, '0');
    const seconds = String(timeLeft.value % 60).padStart(2, '0');
    return `${minutes}:${seconds}`;
});

function startTimer() {
    clearInterval(timer.value);
    timeLeft.value = 3600;
    timer.value = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            clearInterval(timer.value);
        }
    }, 1000);
}

async function copyCode() {
    if (!resolvedQrCode.value) return;
    try {
        await navigator.clipboard.writeText(resolvedQrCode.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 3000);
    } catch (err) {}
}

function checkPaymentStatus() {
    if (!props.pendingOrderId) return;

    router.reload({
        only: ['myActiveOrders'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (pageObj) => {
            const orders = pageObj.props.myActiveOrders || [];
            const myOrder = orders.find(o => String(o.id) === String(props.pendingOrderId));
            if (myOrder && myOrder.payment_status === 'paid') {
                clearInterval(timer.value);
                clearInterval(pollInterval.value);
                emit('payment-approved');
            }
        }
    });
}

// Observadores declarados após as funções estarem disponíveis
watch(resolvedQrCode, (newVal) => {
    if (newVal && !timer.value) {
        startTimer();
    }
}, { immediate: true });

watch(() => page.props.myActiveOrders, (orders) => {
    if (!props.pendingOrderId || !orders) return;
    const myOrder = orders.find(o => String(o.id) === String(props.pendingOrderId));
    if (myOrder && myOrder.payment_status === 'paid') {
        clearInterval(timer.value);
        clearInterval(pollInterval.value);
        emit('payment-approved');
    }
}, { deep: true });

onMounted(() => {
    emit('update-header', {
        title: 'Pagamento PIX',
        subtitle: 'Aguardando Recebimento',
        currentProgress: 4,
        maxProgress: 4
    });

    pollInterval.value = setInterval(checkPaymentStatus, 5000);
});

onUnmounted(() => {
    clearInterval(timer.value);
    clearInterval(pollInterval.value);
});
</script>
