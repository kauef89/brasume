<template>
    <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest border"
          :class="[colorClasses[color] || colorClasses.surface]">
        <i v-if="icon" :class="['pi', icon, 'text-[8px]']"></i>
        {{ label }}
    </span>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    icon: { type: String, default: null }, // Ex: 'pi-check-circle'
    color: { type: String, default: 'surface' } // success, danger, warning, primary, info, surface
});

const colorClasses = {
    success: 'bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20',
    danger: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
    warning: 'bg-warning-500/10 text-warning-700 dark:text-warning-400 border-warning-500/20',
    primary: 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/20',
    info: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
    surface: 'bg-surface-2 text-surface-muted border-surface-border'
};
</script>
