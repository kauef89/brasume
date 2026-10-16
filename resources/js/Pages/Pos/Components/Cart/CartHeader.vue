<template>
    <header class="shrink-0 flex flex-col gap-2 z-10 relative" :class="isTeamMode ? 'p-4' : 'p-6 pb-4'">
        <div class="flex justify-between items-center gap-4">
            <h2 class="text-xl font-black text-surface-text tracking-tight truncate flex-1" :title="title">
                {{ title }}
            </h2>
            <div class="flex items-center gap-2 shrink-0">
                <slot name="actions"></slot>
            </div>
        </div>
        <div v-if="subtitle" class="text-xs font-medium text-surface-muted truncate" :title="subtitle">
            {{ subtitle }}
        </div>
    </header>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
    isTeamMode: { type: Boolean, default: false }
});
</script>
