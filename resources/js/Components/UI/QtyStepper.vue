<template>
    <div
        class="flex items-center justify-between rounded-full shrink-0 transition-all select-none"
        :class="[
            sizeClasses[size]?.container || sizeClasses.md.container,
            variant === 'glass'
                ? 'p-option'
                : 'p-option text-surface-text'
        ]"
    >
        <button
            @click.stop.prevent="decrement"
            :disabled="numValue <= numMin"
            type="button"
            class="aspect-square h-full rounded-full flex items-center justify-center transition-all outline-none border-none cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
            :class="variant === 'glass' ? 'text-white/90 active:scale-90 active:bg-white/15' : 'text-surface-text hover:bg-surface-3 active:scale-90'"
        >
            <i class="pi pi-minus font-black" :class="sizeClasses[size]?.icon || sizeClasses.md.icon"></i>
        </button>

        <span
            class="flex-1 text-center font-black select-none"
            :class="[
                sizeClasses[size]?.text || sizeClasses.md.text,
                variant === 'glass' ? 'text-white' : 'text-surface-text'
            ]"
        >
            <slot name="value">{{ numValue }}</slot>
        </span>

        <button
            @click.stop.prevent="increment"
            :disabled="numMax !== null && numValue >= numMax"
            type="button"
            class="aspect-square h-full rounded-full flex items-center justify-center transition-all outline-none border-none cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
            :class="variant === 'glass' ? 'text-white/90 active:scale-90 active:bg-white/15' : 'text-surface-text hover:bg-primary-500/10 active:scale-90'"
        >
            <i class="pi pi-plus font-black" :class="sizeClasses[size]?.icon || sizeClasses.md.icon"></i>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], required: true },
    min: { type: [Number, String], default: 1 },
    max: { type: [Number, String], default: null },
    step: { type: [Number, String], default: 1 },
    size: { type: String, default: 'md' },
    variant: { type: String, default: 'default' }
});

const emit = defineEmits(['update:modelValue', 'increment', 'decrement']);

const sizeClasses = {
    sm: { container: 'h-8 w-24 p-0.5', text: 'text-xs', icon: 'text-[10px]' },
    md: { container: 'h-11 w-28 p-1', text: 'text-sm font-black', icon: 'text-xs' },
    lg: { container: 'h-14 w-36 p-1.5', text: 'text-xl font-black', icon: 'text-base' }
};

const numMin = computed(() => {
    const val = Number(props.min);
    return isNaN(val) ? 1 : val;
});

const numMax = computed(() => {
    if (props.max === null || props.max === undefined || props.max === '') return null;
    const val = Number(props.max);
    return isNaN(val) ? null : val;
});

const numStep = computed(() => {
    const val = Number(props.step);
    return isNaN(val) || val <= 0 ? 1 : val;
});

const numValue = computed(() => {
    const val = Number(props.modelValue);
    return isNaN(val) ? numMin.value : val;
});

const increment = () => {
    if (numMax.value === null || numValue.value < numMax.value) {
        const next = numValue.value + numStep.value;
        emit('update:modelValue', next);
        emit('increment', next);
    }
};

const decrement = () => {
    if (numValue.value > numMin.value) {
        const prev = Math.max(numMin.value, numValue.value - numStep.value);
        emit('update:modelValue', prev);
        emit('decrement', prev);
    }
};
</script>
