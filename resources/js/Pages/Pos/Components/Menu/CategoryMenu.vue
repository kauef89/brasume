<template>
    <div
        ref="menuContainer"
        class="flex shrink-0 hide-scrollbar transition-all scroll-smooth"
        :class="layout === 'vertical'
            ? 'flex-col w-48 overflow-y-auto bg-surface-2 border border-surface-border rounded-2xl py-2 px-2'
            : 'flex-row overflow-x-auto gap-2'
        "
    >
        <!-- 🚀 Agora obedece à prop showAll e repassa o glassMode -->
        <CategoryCard
            v-if="showAll"
            :category="{ id: null, name: 'Todos os Itens', image: null }"
            :is-selected="!selected"
            :layout="layout"
            :glass-mode="glassMode"
            :data-selected="!selected"
            @click="$emit('update:selected', null)"
        />

        <CategoryCard
            v-for="cat in categories"
            :key="cat.id"
            :category="cat"
            :is-selected="selected === cat.id"
            :layout="layout"
            :glass-mode="glassMode"
            :data-selected="selected === cat.id"
            @click="$emit('update:selected', cat.id)"
        />
    </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';
import CategoryCard from "@/Pages/Pos/Components/Menu/CategoryCard.vue";

const props = defineProps({
    categories: { type: Array, required: true },
    selected: { type: [Number, String, null], required: true },
    layout: { type: String, default: 'horizontal' },
    showAll: { type: Boolean, default: true },
    glassMode: { type: Boolean, default: false }
});

const emit = defineEmits(['update:selected']);

// 🚀 LOG DIAGNÓSTICO: Azul
const logMenuOrder = () => {
    if (!props.categories) return;
    const nomes = props.categories.map(c => c.name || c.displayName);
};

onMounted(logMenuOrder);
watch(() => props.categories, logMenuOrder, { deep: true });

const menuContainer = ref(null);

watch(() => props.selected, async () => {
    await nextTick();
    if (menuContainer.value && props.layout === 'horizontal') {
        const activeEl = menuContainer.value.querySelector('[data-selected="true"]');
        if (activeEl) {
            const container = menuContainer.value;
            const scrollLeft = activeEl.offsetLeft - (container.offsetWidth / 2) + (activeEl.offsetWidth / 2);
            container.scrollTo({ left: Math.max(0, scrollLeft), behavior: 'smooth' });
        }
    }
});
</script>
