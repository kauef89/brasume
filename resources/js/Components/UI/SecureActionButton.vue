<template>
    <button
        type="button"
        @click="handleClick"
        :disabled="disabled || isLoading"
        class="relative flex items-center justify-center gap-2 transition-all outline-none cursor-pointer overflow-hidden select-none"
        :class="[
            baseClasses,
            customClass,
            { 'opacity-50 cursor-not-allowed': disabled || isLoading },
            { 'animate-shake ring-2 shadow-[0_0_15px_rgba(239,68,68,0.5)] ring-danger-500': isShaking }
        ]"
    >
        <!-- Overlay de carregamento -->
        <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-black/20 backdrop-blur-sm z-10 rounded-inherit">
            <i class="pi pi-spinner animate-spin text-current"></i>
        </div>

        <i v-if="icon && !isLoading" :class="['pi', icon]"></i>
        <span v-if="label && !isLoading" class="font-bold whitespace-nowrap">{{ label }}</span>

        <!-- Renderiza o slot (para cards e layouts complexos) ocultando durante o loading se necessário -->
        <div v-if="$slots.default && !isLoading" class="contents">
            <slot></slot>
        </div>
    </button>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    actionCode: { type: String, required: true },
    label: { type: String, default: '' },
    icon: { type: String, default: '' },
    severity: { type: String, default: 'primary' }, // primary, danger, warning, secondary, success
    variant: { type: String, default: 'solid' }, // solid, outlined, text
    disabled: { type: Boolean, default: false },
    isLoading: { type: Boolean, default: false },
    customClass: { type: String, default: '' } // Padding e border-radius agora são flexíveis
});

const emit = defineEmits(['secure-click']);
const isShaking = ref(false);

const baseClasses = computed(() => {
    let classes = '';

    // Se não for passado um customClass com padding específico, aplica um padrão
    if (!props.customClass.includes('p-') && !props.customClass.includes('px-') && !props.customClass.includes('w-') && !props.label && props.icon) {
        // Estilo botão apenas ícone (quadrado/redondo)
        classes += ' w-11 h-11 rounded-xl ';
    } else if (!props.customClass.includes('px-') && !props.customClass.includes('p-')) {
        // Estilo botão normal
        classes += ' px-4 h-11 rounded-xl text-sm ';
    }

    // Variantes e Severidades
    if (props.variant === 'solid') {
        switch (props.severity) {
            case 'danger': return classes + 'bg-danger-500 hover:bg-danger-400 text-white border-none shadow-sm';
            case 'warning': return classes + 'bg-warning-500 hover:bg-warning-400 text-black border-none shadow-sm';
            case 'secondary': return classes + 'bg-surface-2 hover:bg-surface-3 text-surface-text border-none shadow-sm';
            case 'success': return classes + 'bg-success-500 hover:bg-success-400 text-white border-none shadow-sm';
            default: return classes + 'bg-primary-500 hover:bg-primary-400 text-white border-none shadow-sm';
        }
    }

    if (props.variant === 'outlined') {
        classes += ' border border-solid bg-transparent hover:bg-surface-1 ';
        switch (props.severity) {
            case 'danger': return classes + 'border-danger-500 text-danger-500';
            case 'warning': return classes + 'border-warning-500 text-warning-600';
            case 'secondary': return classes + 'border-surface-border text-surface-text';
            case 'success': return classes + 'border-success-500 text-success-500';
            default: return classes + 'border-primary-500 text-primary-500';
        }
    }

    if (props.variant === 'text') {
        classes += ' bg-transparent border-none hover:bg-surface-1 ';
        switch (props.severity) {
            case 'danger': return classes + 'text-danger-500';
            case 'warning': return classes + 'text-warning-600';
            case 'secondary': return classes + 'text-surface-muted hover:text-surface-text';
            case 'success': return classes + 'text-success-500';
            default: return classes + 'text-primary-500';
        }
    }

    return classes;
});

const triggerShake = () => {
    isShaking.value = true;
    setTimeout(() => { isShaking.value = false; }, 500);
};

const handleClick = (e) => {
    if (props.disabled || props.isLoading) return;
    emit('secure-click', { actionCode: props.actionCode, event: e, fail: triggerShake });
};

defineExpose({ triggerShake });
</script>

<style scoped>
.animate-shake {
    animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;
}
@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-3px, 0, 0); }
    40%, 60% { transform: translate3d(3px, 0, 0); }
}
</style>
