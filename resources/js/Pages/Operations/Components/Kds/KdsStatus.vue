<template>
    <div class="flex items-stretch justify-between gap-1 w-full bg-surface-1 rounded-2xl p-3 mb-2 relative overflow-hidden">

        <!-- Fundo de progresso sutil (Barra que avança) -->
        <div class="absolute left-0 top-0 bottom-0 bg-surface-2 transition-all duration-700 ease-out z-0"
             :class="progressWidth"></div>

        <!-- 📦 FASE 1: Solicitado -->
        <div class="flex-1 flex flex-col items-center justify-center transition-all z-10"
             :class="true ? 'opacity-100' : 'opacity-40 grayscale'">
            <div class="w-7 h-7 rounded-full flex items-center justify-center mb-1.5 transition-colors bg-primary-500/50 text-white">
                <i class="pi fa-receipt text-xs"></i>
            </div>
            <span class="text-xs font-black text-surface-text mb-0.5">Solicitado</span>
            <span class="text-sm font-bold text-surface-muted flex items-center gap-1">
                <i class="pi pi-clock text-xs"></i> {{ requestedTime }}m
            </span>
        </div>

        <!-- Setinha indicadora -->
        <div class="flex flex-col justify-center z-10 text-surface-border">
            <i class="pi pi-angle-right text-2xl"></i>
        </div>

        <!-- 🍳 FASE 2: Em Preparo -->
        <div class="flex-1 flex flex-col items-center justify-center transition-all z-10"
             :class="isPreparing || isReady ? 'opacity-100' : 'opacity-40 grayscale'">
            <div class="w-7 h-7 rounded-full flex items-center justify-center mb-1.5 transition-colors"
                 :class="isPreparing || isReady ? 'bg-warning-500/50 text-white' : 'bg-surface-3 text-surface-muted'">
                <i class="pi pi-sync text-xs"></i>
            </div>
            <span class="text-xs font-black mb-0.5 transition-colors"
                  :class="isPreparing || isReady ? 'text-warning-600 dark:text-warning-400' : 'text-surface-muted'">Em preparo</span>
            <span v-if="isPreparing || isReady" class="text-sm font-bold text-warning-600 dark:text-warning-400 flex items-center gap-1">
                <i class="pi pi-clock text-xs"></i> {{ prepTime }}m
            </span>
            <span v-else class="text-sm font-bold text-surface-muted">--</span>
        </div>

        <!-- Setinha indicadora -->
        <div class="flex flex-col justify-center z-10 text-surface-border">
            <i class="pi pi-angle-right text-2xl"></i>
        </div>

        <!-- 🛎️ FASE 3: Pronto/Entregue -->
        <div class="flex-1 flex flex-col items-center justify-center transition-all z-10"
             :class="isReady ? 'opacity-100' : 'opacity-40 grayscale'">
            <div class="w-7 h-7 rounded-full flex items-center justify-center mb-1.5 transition-colors"
                 :class="isReady ? 'bg-success-500 text-white' : 'bg-surface-3 text-surface-muted'">
                <i class="pi pi-check text-xs"></i>
            </div>
            <span class="text-xs font-black mb-0.5 transition-colors"
                  :class="isReady ? 'text-success-600 dark:text-success-400' : 'text-surface-muted'">Concluído</span>
            <span v-if="isReady" class="text-sm font-bold text-success-600 dark:text-success-400 flex items-center gap-1">
                <i class="pi pi-clock text-xs"></i> {{ readyTime }}m
            </span>
            <span v-else class="text-sm font-bold text-surface-muted">--</span>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        required: true
    }
});

// 🚀 MOTOR DE TEMPO REAL
const now = ref(new Date());
let timerInterval;

onMounted(() => {
    timerInterval = setInterval(() => { now.value = new Date(); }, 30000); // Atualiza a cada 30s
});

onUnmounted(() => {
    clearInterval(timerInterval);
});

// Calculadora de minutos
const getElapsedMinutes = (dateString) => {
    if (!dateString) return 0;

    // Corrige fuso horário se vier truncado do banco
    let safeDate = dateString;
    if (typeof safeDate === 'string' && !safeDate.includes('T')) {
        safeDate = safeDate.replace(' ', 'T') + 'Z';
    }

    const past = new Date(safeDate);
    const diff = Math.floor((now.value - past) / 60000);
    return diff < 0 ? 0 : diff;
};

// 🚦 ESTADOS DO ITEM
const status = computed(() => props.item?.production_status || 'pending');

const isPreparing = computed(() => ['preparing', 'in_progress'].includes(status.value));
const isReady = computed(() => ['ready', 'delivered'].includes(status.value));

// ⏱️ TEMPOS
// Há quantos minutos o garçom lançou o item?
const requestedTime = computed(() => getElapsedMinutes(props.item?.created_at));

// Há quantos minutos a cozinha "puxou" o ticket (fired_at)?
const prepTime = computed(() => {
    if (!props.item?.fired_at) return null;
    return getElapsedMinutes(props.item.fired_at);
});

// Há quantos minutos o item foi marcado como pronto?
const readyTime = computed(() => {
    if (!isReady.value) return null;
    // Quando ele muda para "ready", o updated_at reflete esse momento exato.
    return getElapsedMinutes(props.item?.updated_at);
});

// Barra de progresso ao fundo
const progressWidth = computed(() => {
    if (isReady.value) return 'w-full';
    if (isPreparing.value) return 'w-[66%]';
    return 'w-[33%]';
});
</script>
