<template>
    <div class="setting-card">
        <div class="max-w-md">
            <h3 class="setting-card-title">{{ title }}</h3>
            <p class="setting-card-desc">{{ description }}</p>
        </div>
        <div class="shrink-0 w-full md:w-[320px]">
            <slot></slot>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true }
});
</script>
