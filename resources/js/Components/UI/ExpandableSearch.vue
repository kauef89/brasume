<template>
    <div class="relative w-full md:w-64 shrink-0 transition-all duration-300">
        <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-surface-muted text-xs pointer-events-none"></i>
        <input
            ref="searchInput"
            :value="modelValue"
            @input="handleInput"
            @focus="resetInactivityTimer"
            :placeholder="placeholder"
            class="w-full h-9 pl-9 pr-8 rounded-full bg-surface-1/90 text-surface-text text-xs font-medium border border-surface-border/60 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all placeholder:text-surface-muted/60"
        />
        <button
            v-if="modelValue"
            @click="clear"
            type="button"
            class="absolute inset-y-0 right-1 w-8 flex items-center justify-center text-surface-muted hover:text-surface-text transition-colors outline-none cursor-pointer border-none bg-transparent"
        >
            <i class="pi pi-times-circle text-sm"></i>
        </button>
    </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: String,
    placeholder: { type: String, default: 'Buscar...' }
});

const emit = defineEmits(['update:modelValue']);
const searchInput = ref(null);
let typingTimer = null;

const resetInactivityTimer = () => {
    if (typingTimer) clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        searchInput.value?.blur();
    }, 3000);
};

const handleInput = (event) => {
    emit('update:modelValue', event.target.value);
    resetInactivityTimer();
};

const clear = () => {
    emit('update:modelValue', '');
    if (typingTimer) clearTimeout(typingTimer);
    searchInput.value?.focus();
};

onUnmounted(() => {
    if (typingTimer) clearTimeout(typingTimer);
});
</script>
