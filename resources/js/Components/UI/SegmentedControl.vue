<template>
    <div class="inline-flex w-fit mx-auto bg-primary-500/10 p-1 rounded-full backdrop-blur-xl gap-1 items-center overflow-x-auto hide-scrollbar">
        <button
            v-for="opt in options"
            :key="opt.value"
            @click="!opt.disabled && $emit('update:modelValue', opt.value)"
            type="button"
            :disabled="opt.disabled"
            class="px-2.5 h-7 rounded-full text-[11px] font-black transition-all outline-none border-none flex items-center justify-center gap-1.5 whitespace-nowrap"
            :class="[
                modelValue === opt.value
                    ? 'bg-primary-500/40 text-surface-text shadow-xs'
                    : 'bg-transparent text-surface-muted hover:bg-primary-500/20 hover:text-surface-text',
                opt.disabled
                    ? 'opacity-30 cursor-not-allowed hover:bg-transparent hover:text-surface-muted pointer-events-none'
                    : 'cursor-pointer'
            ]"
        >
            <i v-if="opt.icon" :class="['pi', opt.icon, 'text-[9px]']"></i>
            <span>{{ opt.label }}</span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    modelValue: [String, Number, Boolean],
    options: Array
});
defineEmits(['update:modelValue']);
</script>
