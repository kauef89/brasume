<template>
    <div class="bg-surface-2 border border-surface-border rounded-2xl p-4 flex justify-between items-center shadow-sm">
        <div class="flex flex-col">
            <span class="font-black text-sm text-surface-text flex items-center gap-2">
                <i :class="['pi', icon, iconColorClass]"></i> {{ title }}
            </span>
            <span class="text-xs font-medium text-surface-muted mt-0.5">{{ description }}</span>
        </div>
        <button
            type="button"
            @click="$emit('toggle')"
            class="w-12 h-6 rounded-full transition-colors relative outline-none shadow-inner cursor-pointer"
            :class="isActive ? 'bg-primary-500' : 'bg-surface-border'"
        >
            <div class="absolute top-1 bottom-1 w-4 bg-white rounded-full transition-all shadow-sm"
                 :class="isActive ? 'left-[26px]' : 'left-1'"></div>
        </button>
    </div>
</template>

<script setup>
defineProps({
    title: String,
    description: String,
    icon: String,
    iconColorClass: String,
    isActive: Boolean
});
defineEmits(['toggle']);
</script>
