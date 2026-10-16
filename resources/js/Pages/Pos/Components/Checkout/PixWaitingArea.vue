<template>
    <div class="flex flex-col items-center w-full animate-in fade-in slide-in-from-bottom-4 duration-500">

        <!-- HEADER COM TIMER -->
        <div class="flex items-center gap-2 mb-6 px-4 py-2 rounded-full font-black text-sm tracking-widest uppercase transition-colors"
             :class="timeLeft < 60 ? 'bg-danger-500/20 text-danger-400 animate-pulse' : 'bg-primary-500/20 text-primary-300'">
            <i class="pi pi-clock"></i>
            <span>{{ formattedTime }}</span>
        </div>

        <div class="text-center mb-8">
            <h3 class="c-title !text-2xl">Realize o Pagamento</h3>
            <p class="c-text-muted mt-1 max-w-[250px] mx-auto">Seu pedido só chegará à cozinha após a confirmação do PIX.</p>
        </div>

        <!-- ÁREA DO QR CODE -->
        <div class="relative p-6 bg-white rounded-3xl mb-8 shadow-[0_0_40px_rgba(255,255,255,0.1)]">
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

            <qrcode-vue
                v-if="qrCodeString"
                :value="qrCodeString"
                :size="180"
                level="M"
                class="rounded-lg"
            />
            <div v-else class="w-[180px] h-[180px] bg-surface-2 rounded-lg animate-pulse"></div>
        </div>

        <!-- BOTÃO COPIA E COLA -->
        <button
            @click="copyCode"
            :disabled="isLoading || isExpired || !qrCodeString"
            class="w-full h-14 mb-4 rounded-xl c-glass-card-interactive !border-white/20 text-white font-black text-xs uppercase tracking-widest outline-none cursor-pointer flex items-center justify-center gap-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <i class="pi" :class="copied ? 'pi-check text-emerald-400' : 'pi-copy'"></i>
            {{ copied ? 'Código Copiado!' : 'Copiar PIX Copia e Cola' }}
        </button>

        <!-- AÇÕES SECUNDÁRIAS (Cancelar) -->
        <div class="flex items-center justify-between w-full gap-3 mt-4 border-t border-white/10 pt-6">
            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" custom-class="px-6 h-10 rounded-full font-black text-sm uppercase tracking-widest" @secure-click="$emit('cancel')" />
            <button
                @click="$emit('refresh')"
                class="flex-1 h-12 rounded-xl text-white bg-white/10 hover:bg-white/20 font-black text-[10px] uppercase tracking-widest transition-colors outline-none cursor-pointer border-none"
            >
                Gerar Novo Código
            </button>
        </div>
    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';

import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import QrcodeVue from 'qrcode.vue';

const props = defineProps({
    orderId: { type: [Number, String], required: true },
    qrCodeString: { type: String, default: null },
    txid: { type: String, default: null },
    isLoading: { type: Boolean, default: false }
});

const emit = defineEmits(['cancel', 'refresh', 'expired']);

// 🚀 EXATAMENTE 10 MINUTOS (600s)
const timeLeft = ref(600);
const timer = ref(null);
const copied = ref(false);

const isExpired = computed(() => timeLeft.value <= 0);

const formattedTime = computed(() => {
    const minutes = Math.floor(timeLeft.value / 60);
    const seconds = timeLeft.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const startTimer = () => {
    clearInterval(timer.value);
    timeLeft.value = 600;

    timer.value = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            clearInterval(timer.value);
            // 🚀 AVISA O PAI QUE O TEMPO ACABOU!
            emit('expired');
        }
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

onMounted(() => { if (props.qrCodeString) startTimer(); });
watch(() => props.qrCodeString, (newVal) => { if (newVal) startTimer(); });
onUnmounted(() => { clearInterval(timer.value); });
</script>
