<template>
    <div class="flex items-center justify-center font-black text-white shadow-inner shrink-0" :class="[sizeClasses[size] || sizeClasses.md, colorClass, roundedClass]">
        {{ initials }}
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { getInitials } from '@/Utils/formatters.js';
const props = defineProps({ name: { type: String, required: true }, size: { type: String, default: 'md' }, colorClass: { type: String, default: 'bg-primary-500' }, roundedClass: { type: String, default: 'rounded-full' }});
const initials = computed(() => getInitials(props.name));
const sizeClasses = { sm: 'w-8 h-8 text-[10px]', md: 'w-10 h-10 text-xs', lg: 'w-14 h-14 text-xl', xl: 'w-20 h-20 text-3xl' };
</script>
