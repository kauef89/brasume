<template>
    <div class="flex-1 flex flex-col items-center justify-center p-6 text-center bg-surface-2 z-50 absolute inset-0 select-none">
        <div class="w-24 h-24 bg-red-500/10 text-red-500 rounded-full flex items-center justify-center mb-6 shadow-inner">
            <i class="pi pi-lock text-4xl"></i>
        </div>
        <h1 class="text-2xl font-black text-surface-text mb-2">Turno Fechado</h1>
        <p class="text-surface-muted mb-8 max-w-xs mx-auto text-sm font-medium">O seu ponto ainda não foi registado.</p>
        <button
            @click="$emit('startShift')"
            :disabled="isStarting"
            class="w-full max-w-xs bg-primary-500 text-white font-black py-4 rounded-2xl cursor-pointer hover:bg-primary-600 transition-colors shadow-md active:scale-95 border-none outline-none flex items-center justify-center"
        >
            <i v-if="isStarting" class="pi pi-spinner pi-spin mr-2"></i>
            <span>{{ isStarting ? 'A registar...' : 'Iniciar Turno Agora' }}</span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    isStarting: { type: Boolean, default: false }
});

defineEmits(['startShift']);
</script>
