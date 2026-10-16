<template>
    <div class="p-3 bg-warning-500/10 border border-warning-500/30 rounded-2xl flex flex-col gap-1 text-xs">
        <div class="flex justify-between items-center font-bold text-surface-text">
            <span>{{ issue.task_name }}</span>
            <span class="text-[10px] opacity-70">{{ issue.time }}</span>
        </div>
        <p class="text-warning-800 dark:text-warning-300 font-medium">{{ issue.notes }}</p>
        <span class="text-[10px] text-surface-muted font-bold">Reportado por: {{ issue.reported_by }}</span>
    </div>
</template>

<script setup>
defineProps({
    issue: {
        type: Object,
        required: true
    }
});
</script>
