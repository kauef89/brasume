<template>
    <button
        type="button"
        :disabled="disabled || loading"
        :class="[
            'w-9 h-9 flex items-center justify-center rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2',
            colorClasses[color] || colorClasses.primary,
            { 'opacity-50 cursor-not-allowed': disabled || loading }
        ]"
        v-bind="$attrs"
    >
        <i v-if="loading" class="pi pi-spinner pi-spin"></i>
        <i v-else :class="['pi', icon]"></i>
    </button>
</template>

<script setup>
defineProps({
    icon: { type: String, required: true }, // Ex: 'pi-trash'
    color: { type: String, default: 'primary' }, // primary, danger, success, warning, surface
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false }
});

// Dicionário de cores padrão do nosso sistema
const colorClasses = {
    primary: 'bg-primary-50 text-primary-600 hover:bg-primary-100 focus:ring-primary-500 dark:bg-primary-900/30 dark:text-primary-400 dark:hover:bg-primary-900/50',
    danger: 'bg-red-50 text-red-600 hover:bg-red-100 focus:ring-red-500 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50',
    success: 'bg-green-50 text-green-600 hover:bg-green-100 focus:ring-green-500 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50',
    warning: 'bg-amber-50 text-amber-600 hover:bg-amber-100 focus:ring-amber-500 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50',
    surface: 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700',
};
</script>
