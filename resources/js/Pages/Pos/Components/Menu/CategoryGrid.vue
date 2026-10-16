<template>
    <div class="flex flex-col w-full shrink-0 mb-4">
        <div :class="[
            'grid pt-1 auto-rows-max',
            layout === 'grid'
                ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5 md:gap-3'
                : 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2 md:gap-2.5'
        ]">
            <CategoryCard
                v-for="cat in categories"
                :key="cat.id"
                :category="cat"
                :layout="layout"
                @click="$emit('category-click', cat.id)"
            />
        </div>
    </div>
</template>

<script setup>
import { onMounted, watch } from 'vue';
import CategoryCard from "@/Pages/Pos/Components/Menu/CategoryCard.vue";

const props = defineProps({
    categories: Array,
    layout: { type: String, default: 'grid' },
    isTeamMode: { type: Boolean, default: false }
});

defineEmits(['category-click']);

const logGridOrder = () => {
    if (!props.categories) return;
    const nomes = props.categories.map(c => c.name || c.displayName);
};

onMounted(logGridOrder);
watch(() => props.categories, logGridOrder, { deep: true });
</script>
