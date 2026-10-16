<template>
    <button
        @click="$emit('select')"
        type="button"
        :disabled="status === 'loading'"
        class="flex items-center gap-4 p-4 rounded-xl p-option transition-all text-left outline-none w-full"
        :class="[
            status === 'loading' ? 'opacity-70 cursor-wait' : 'cursor-pointer',
            (isSelected || status !== 'idle')
                ? [activeColors.active, 'shadow-sm']
                : 'border-surface-border bg-surface-1 hover:bg-surface-2 hover:border-surface-border/80'
        ]"
    >
        <div v-if="icon || status !== 'idle'" class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 transition-colors"
             :class="(isSelected || status !== 'idle') ? activeColors.icon : 'bg-surface-2 text-surface-muted'">
            <i v-if="status === 'loading'" class="pi pi-spinner pi-spin text-xl"></i>
            <i v-else-if="status === 'success'" class="pi pi-check text-xl"></i>
            <i v-else-if="status === 'error'" class="pi pi-times text-xl"></i>
            <i v-else :class="['pi', icon, 'text-xl']"></i>
        </div>

        <div class="flex flex-col flex-1">
            <span class="font-medium text-sm transition-colors" :class="(isSelected || status !== 'idle') ? activeColors.text : 'text-surface-text'">
                {{ displayTitle }}
            </span>
            <span v-if="displaySubtitle" class="text-xs mt-0.5 transition-colors" :class="(isSelected || status !== 'idle') ? activeColors.subtext : 'text-surface-muted'">
                {{ displaySubtitle }}
            </span>
        </div>

        <div v-if="showCheckmark && (isSelected || status === 'success')" :class="activeColors.check" class="animate-in zoom-in duration-200">
            <i class="pi pi-check-circle text-lg"></i>
        </div>
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    isSelected: { type: Boolean, default: false },
    icon: { type: String, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    color: { type: String, default: 'primary' }, // primary, purple, red, green, warning
    showCheckmark: { type: Boolean, default: false },
    status: { type: String, default: 'idle', validator: v => ['idle', 'loading', 'success', 'error'].includes(v) }
});

defineEmits(['select']);

const colorVariants = {
    primary: {
        active: 'border-primary-500 bg-primary-50 dark:bg-primary-900/20',
        icon: 'bg-primary-500/20 text-primary-600 dark:text-primary-400',
        text: 'text-primary-700 dark:text-primary-300',
        subtext: 'text-primary-600/80 dark:text-primary-400/80',
        check: 'text-primary-500'
    },
    purple: {
        active: 'border-purple-500 bg-purple-50 dark:bg-purple-900/20',
        icon: 'bg-purple-500/20 text-purple-600 dark:text-purple-400',
        text: 'text-purple-700 dark:text-purple-300',
        subtext: 'text-purple-600/80 dark:text-purple-400/80',
        check: 'text-purple-500'
    },
    red: {
        active: 'border-red-500 bg-red-50 dark:bg-red-900/20',
        icon: 'bg-red-500/20 text-red-600 dark:text-red-400',
        text: 'text-red-700 dark:text-red-300',
        subtext: 'text-red-600/80 dark:text-red-400/80',
        check: 'text-red-500'
    },
    green: {
        active: 'border-green-500 bg-green-50 dark:bg-green-900/20',
        icon: 'bg-green-500/20 text-green-600 dark:text-green-400',
        text: 'text-green-700 dark:text-green-300',
        subtext: 'text-green-600/80 dark:text-green-400/80',
        check: 'text-green-500'
    },
    warning: {
        active: 'border-warning-500 bg-warning-50 dark:bg-warning-900/20',
        icon: 'bg-warning-500/20 text-warning-600 dark:text-warning-400',
        text: 'text-warning-700 dark:text-warning-300',
        subtext: 'text-warning-600/80 dark:text-warning-400/80',
        check: 'text-warning-500'
    }
};

// Sobrescreve as cores dependendo do status
const activeColors = computed(() => {
    if (props.status === 'success') return colorVariants.green;
    if (props.status === 'error') return colorVariants.red;
    return colorVariants[props.color] || colorVariants.primary;
});

const displayTitle = computed(() => {
    if (props.status === 'loading') return 'Processando...';
    if (props.status === 'success') return 'Concluído!';
    if (props.status === 'error') return 'Falha na operação';
    return props.title;
});

const displaySubtitle = computed(() => {
    if (props.status !== 'idle') return null; // Esconde subtítulo durante feedback visual
    return props.subtitle;
});
</script>
