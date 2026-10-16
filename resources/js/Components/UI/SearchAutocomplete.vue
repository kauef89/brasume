<template>
    <div class="relative w-full" ref="containerRef">
        <input
            ref="inputRef"
            type="text"
            :value="modelValue"
            :placeholder="placeholder"
            :autofocus="autofocus"
            class="w-full h-14 px-4 rounded-xl font-bold text-sm outline-none transition-all"
            :class="isCustomerMode
                ? 'bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15'
                : 'bg-surface-1 border border-transparent text-surface-text focus:border-primary-500'"
            @input="handleInput"
            @focus="handleFocus"
        />

        <!-- TELEPORT COM POSICIONAMENTO INTELIGENTE (CIMA / BAIXO) -->
        <Teleport to="body">
            <Transition name="fade-up">
                <div
                    v-if="isOpen && suggestions.length > 0"
                    ref="dropdownRef"
                    :style="dropdownStyle"
                    class="fixed z-[9999] overflow-y-auto hide-scrollbar rounded-2xl p-2 flex flex-col gap-1 border shadow-2xl"
                    :class="isCustomerMode
                        ? 'bg-surface-ground/95 backdrop-blur-2xl border-white/10 text-white'
                        : 'bg-surface-2 border-surface-border text-surface-text'"
                >
                    <button
                        v-for="(item, idx) in suggestions"
                        :key="item.id || idx"
                        type="button"
                        @click="selectItem(item)"
                        class="w-full text-left p-3 rounded-xl transition-colors cursor-pointer border-none outline-none flex flex-col"
                        :class="isCustomerMode
                            ? 'hover:bg-white/10 active:bg-white/15 text-white'
                            : 'hover:bg-surface-3 active:bg-surface-4 text-surface-text'"
                    >
                        <slot name="option" :option="item">
                            <span class="font-bold text-sm">{{ item[field] || item }}</span>
                            <span v-if="item.neighborhood?.name" class="text-xs text-surface-muted">
                                {{ item.neighborhood.name }}
                            </span>
                        </slot>
                    </button>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    suggestions: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    field: { type: String, default: 'name' },
    isCustomerMode: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue', 'search', 'select']);

const inputRef = ref(null);
const containerRef = ref(null);
const dropdownRef = ref(null);
const isOpen = ref(false);
const dropdownStyle = ref({});

const updatePosition = () => {
    if (!inputRef.value || !isOpen.value) {
        console.log("🛸 [SONDA 7 - AUTOCOMPLETE] updatePosition abortado. inputRef:", !!inputRef.value, "isOpen:", isOpen.value);
        return;
    }

    const rect = inputRef.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;

    const openUpwards = spaceBelow < 220 && spaceAbove > spaceBelow;

    if (openUpwards) {
        dropdownStyle.value = {
            position: 'fixed',
            left: `${rect.left}px`,
            width: `${rect.width}px`,
            bottom: `${window.innerHeight - rect.top + 8}px`,
            top: 'auto',
            maxHeight: `${Math.min(spaceAbove - 24, 280)}px`
        };
    } else {
        dropdownStyle.value = {
            position: 'fixed',
            left: `${rect.left}px`,
            width: `${rect.width}px`,
            top: `${rect.bottom + 8}px`,
            bottom: 'auto',
            maxHeight: `${Math.min(spaceBelow - 24, 280)}px`
        };
    }

    console.log("🛸 [SONDA 8 - AUTOCOMPLETE] Estilo calculado para a lista flutuante:", dropdownStyle.value);
};

const handleInput = (e) => {
    const val = e.target.value;
    console.log("🛸 [SONDA 5 - AUTOCOMPLETE] handleInput disparado. Valor atual:", val);

    emit('update:modelValue', val);
    emit('search', val);

    if (val.trim().length >= 1) {
        isOpen.value = true;
        nextTick(updatePosition);
    } else {
        isOpen.value = false;
    }
};

const handleFocus = () => {
    if (props.modelValue.trim().length >= 1 && props.suggestions.length > 0) {
        isOpen.value = true;
        nextTick(updatePosition);
    }
};

const selectItem = (item) => {
    isOpen.value = false;
    emit('select', item);
};

const handleClickOutside = (e) => {
    if (!isOpen.value) return;

    const clickedInput = containerRef.value && containerRef.value.contains(e.target);
    const clickedDropdown = dropdownRef.value && dropdownRef.value.contains(e.target);

    if (!clickedInput && !clickedDropdown) {
        isOpen.value = false;
    }
};

watch(() => props.suggestions, (newSuggestions) => {
    console.log("🛸 [SONDA 6 - AUTOCOMPLETE] Recebeu 'suggestions' atualizadas. Total:", newSuggestions?.length);
    if (isOpen.value) {
        nextTick(updatePosition);
    }
}, { deep: true });

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', updatePosition, true);
    window.removeEventListener('resize', updatePosition);
});
</script>

<style scoped>
.fade-up-enter-active, .fade-up-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-up-enter-from, .fade-up-leave-to {
    opacity: 0;
    transform: translateY(4px);
}
</style>
