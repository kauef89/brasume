<template>
    <div class="relative group inline-flex shrink-0">
        <button
            type="button"
            :disabled="disabled || loading"
            :class="[
                'w-10 h-10 rounded-full flex items-center justify-center transition-all duration-200 cursor-pointer border-none outline-none select-none relative',
                colorClasses,
                { 'opacity-40 cursor-not-allowed pointer-events-none': disabled || loading }
            ]"
            v-bind="$attrs"
        >
            <!-- ÍCONE OU SPINNER -->
            <i v-if="loading" class="pi pi-spinner pi-spin text-sm"></i>
            <i v-else :class="['pi text-sm transition-transform duration-200 group-hover:scale-110', icon]"></i>

            <!-- BADGE DE NOTIFICAÇÃO / CONTADOR -->
            <slot name="badge">
                <span
                    v-if="badge !== null && badge !== undefined"
                    class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 text-[9px] font-black rounded-full flex items-center justify-center shadow-sm pointer-events-none"
                    :class="badgeClasses[badgeSeverity] || badgeClasses.danger"
                >
                    {{ badge }}
                </span>
            </slot>
        </button>

        <!-- TOOLTIP / DESCRIÇÃO FLUTUANTE NO HOVER -->
        <div
            v-if="label || $slots.label"
            class="absolute top-full mt-2 left-1/2 -translate-x-1/2 pointer-events-none opacity-0 scale-95 group-hover:opacity-100 group-hover:scale-100 transition-all duration-150 z-[100] whitespace-nowrap bg-surface-900/95 dark:bg-surface-800/95 text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg shadow-xl backdrop-blur-md"
        >
            <slot name="label">{{ label }}</slot>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    icon: { type: String, required: true },
    label: { type: String, default: '' },
    color: { type: String, default: 'primary' }, // primary, success, danger, warning, surface
    active: { type: Boolean, default: false },   // Modo liga/desliga: 60% quando ligado
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    badge: { type: [String, Number], default: null },
    badgeSeverity: { type: String, default: 'danger' } // danger, warning, success, primary
});

const colorPalette = {
    primary: {
        idle: 'bg-primary-500/5 text-primary-600 dark:text-primary-400 hover:bg-primary-500/20',
        active: 'bg-primary-500/40 text-white shadow-sm'
    },
    success: {
        idle: 'bg-emerald-500/5 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20',
        active: 'bg-emerald-500/40 text-white shadow-sm'
    },
    danger: {
        idle: 'bg-rose-500/5 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20',
        active: 'bg-rose-500/40 text-white shadow-sm'
    },
    warning: {
        idle: 'bg-amber-500/5 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20',
        active: 'bg-amber-500/40 text-white shadow-sm'
    },
    surface: {
        idle: 'bg-surface-3/50 text-surface-muted hover:bg-surface-3/80 hover:text-surface-text',
        active: 'bg-surface-4 text-surface-text shadow-sm'
    }
};

const badgeClasses = {
    danger: 'bg-danger-500 text-white animate-pulse',
    warning: 'bg-warning-500 text-white',
    success: 'bg-success-smart text-white',
    primary: 'bg-primary-500 text-white'
};

const colorClasses = computed(() => {
    const scheme = colorPalette[props.color] || colorPalette.primary;
    return props.active ? scheme.active : scheme.idle;
});
</script>
