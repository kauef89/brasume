<template>
    <Toast position="top-right" />
    <slot />

    <FloatingMenu />
</template>

<script setup>
import FloatingMenu from "../Components/FloatingMenu.vue";
import Toast from "primevue/toast"; // Importa o componente Toast
</script>
