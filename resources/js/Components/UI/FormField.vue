<template>
    <div class="flex flex-col gap-1.5 w-full">
        <label v-if="label" class="text-[11px] font-black uppercase tracking-widest flex justify-between items-center" :class="isGlass ? 'text-white/50' : 'text-surface-muted'">
            <span>
                {{ label }}
                <span v-if="required" class="text-danger-500 ml-0.5">*</span>
            </span>
            <slot name="action"></slot>
        </label>

        <div class="relative w-full">
            <slot></slot>
        </div>

        <span v-if="error" class="text-[10px] text-danger-500 font-medium mt-0.5 leading-tight">
            {{ error }}
        </span>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, default: null },
    required: { type: Boolean, default: false },
    error: { type: String, default: null },
    isGlass: { type: Boolean, default: false } // 🚀 Prop ativadora
});
</script>
