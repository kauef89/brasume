<template>
    <!-- Um canvas absolutamente limpo, ocupando a tela inteira -->
    <div class="h-screen w-screen bg-surface-1 text-surface-text overflow-hidden">
        <slot />
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useTheme } from '@/Composables/useTheme';

// Garante que o tema de cores do restaurante é aplicado até mesmo aqui!
useTheme();
</script>
