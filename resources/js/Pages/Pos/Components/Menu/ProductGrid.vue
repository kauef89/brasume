<template>
    <div class="flex flex-col w-full h-full">
        <div
            v-if="products.length > 0"
            :class="[
                'grid pb-4 pt-1',
                layout !== 'list'
                    ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5 md:gap-3'
                    : 'grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 md:gap-3'
            ]"
        >
            <ProductCard
                v-for="product in products"
                :key="product.id"
                :product="product"
                :layout="layout"
                @click="$emit('product-click', product)"
            />
        </div>
        <EmptyState
            v-else
            icon="pi pi-box"
            title="Nenhum produto encontrado."
            class="min-h-[300px]"
        />
    </div>
</template>

<script setup>
import ProductCard from "@/Pages/Pos/Components/Menu/ProductCard.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";

defineProps({
    products: { type: Array, required: true },
    layout: { type: String, default: 'square' }
});

defineEmits(['product-click']);
</script>
