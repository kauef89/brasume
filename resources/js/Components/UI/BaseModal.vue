<template>
<Dialog
        :visible="modelValue"
        @update:visible="$emit('update:modelValue', $event)"
        :modal="true"
        :dismissableMask="dismissableMask"
        :style="{ width: width }"
        class="p-fluid border-none shadow-2xl"
        :pt="{
            root: { class: 'bg-surface-1 border-none rounded-[2rem]' },
            header: { class: 'pb-2 bg-surface-1 rounded-t-[2rem] border-none' },
            content: { class: 'pt-2 bg-surface-1 border-none' },
            footer: { class: 'pt-2 bg-surface-1 rounded-b-[2rem] border-none' }
        }"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <div v-if="icon" :class="[
                    'w-10 h-10 rounded-full flex items-center justify-center',
                    iconBgColors[iconColor] || iconBgColors.primary
                ]">
                    <i :class="['pi', icon, iconTextColors[iconColor] || iconTextColors.primary, 'text-xl']"></i>
                </div>
                <span class="text-xl font-medium text-gray-900 dark:text-white">{{ title }}</span>
            </div>
        </template>

        <slot></slot>

        <template #footer v-if="$slots.footer">
            <div class="flex justify-end gap-2 w-full mt-4">
                <slot name="footer"></slot>
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';

defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, required: true },
    icon: { type: String, default: null }, // Ex: 'pi pi-lock'
    iconColor: { type: String, default: 'primary' },
    width: { type: String, default: '30rem' },
    dismissableMask: { type: Boolean, default: true }
});

defineEmits(['update:modelValue']);

const iconBgColors = {
    primary: 'bg-primary-50 dark:bg-primary-900/30',
    danger: 'bg-red-50 dark:bg-red-900/30',
    success: 'bg-green-50 dark:bg-green-900/30',
    warning: 'bg-amber-50 dark:bg-amber-900/30',
    surface: 'bg-gray-100 dark:bg-gray-800'
};

const iconTextColors = {
    primary: 'text-primary-smart',
    danger: 'text-red-600 dark:text-red-400',
    success: 'text-green-600 dark:text-green-400',
    warning: 'text-amber-600 dark:text-amber-400',
    surface: 'text-gray-600 dark:text-gray-400'
};
</script>
