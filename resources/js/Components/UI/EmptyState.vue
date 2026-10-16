<template>
    <div class="flex flex-col items-center justify-center text-center p-6 w-full h-full min-h-[150px] opacity-60">
        <div v-if="icon" class="w-16 h-16 rounded-full bg-surface-3 flex items-center justify-center mb-4 text-surface-muted">
            <i :class="['pi', icon, 'text-3xl']"></i>
        </div>
        <h3 class="font-black text-lg text-surface-text leading-tight">{{ title }}</h3>
        <p v-if="subtitle" class="text-sm font-medium text-surface-muted mt-1 max-w-xs">{{ subtitle }}</p>

        <div v-if="$slots.action" class="mt-4 pointer-events-auto">
            <slot name="action"></slot>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    icon: { type: String, default: 'pi-inbox' }
});
</script>
