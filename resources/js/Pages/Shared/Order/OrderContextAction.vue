<template>
  <button
    @click="$emit('click')"
    :disabled="disabled"
    class="w-full flex items-center gap-3 rounded-2xl transition-all text-left outline-none p-3 p-option"
    :class="[
      disabled ? 'opacity-40 grayscale cursor-not-allowed' : 'cursor-pointer hover:-translate-y-0.5',
      isPrimary
        ? (disabled ? 'bg-primary-500! text-white border-primary-600' : 'bg-primary-500/60! text-white hover:bg-primary-600 border-primary-600')
        : (disabled ? 'bg-surface-2 text-surface-text border-surface-border' : 'bg-surface-2 text-surface-text hover:bg-surface-3 border-surface-border')
    ]"
  >
    <i :class="[icon, 'text-2xl shrink-0', isPrimary ? 'text-white' : iconColor]"></i>

    <div class="flex flex-col min-w-0 flex-1">
        <span class="font-black text-sm leading-tight truncate" :class="isPrimary ? 'text-white' : 'text-surface-text'">{{ label }}</span>
        <span v-if="subtitle" class="text-xs font-medium truncate mt-0.5" :class="isPrimary ? 'text-white/80' : 'text-surface-muted'">{{ subtitle }}</span>
    </div>
  </button>
</template>

<script setup>
defineProps({
    label: String,
    subtitle: String,
    icon: String,
    iconColor: { type: String, default: 'text-primary-500' },
    isPrimary: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false } // 🚀 A PROPRIEDADE MÁGICA
});
defineEmits(['click']);
</script>
