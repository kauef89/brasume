<template>
    <div v-if="favorites && favorites.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
        <CustomerProductCard
            v-for="(item, index) in favorites"
            :key="item.variant_signature || (item.id + '_' + index)"
            :product="item"
            layout="list"
            @click="$emit('favorite-click', $event)"
        />
    </div>

    <!-- Empty State em Glassmorphism Sem Bordas -->
    <div v-else class="c-glass-card text-center py-12 px-6 mt-2 flex flex-col items-center justify-center">
        <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-3">
            <i class="pi pi-heart text-2xl text-white/30"></i>
        </div>
        <h3 class="c-title !text-base mb-1">Ainda não há favoritos</h3>
        <p class="c-text-muted max-w-xs leading-relaxed">
            Os itens que você mais pedir aparecerão aqui automaticamente para agilizar seus próximos pedidos.
        </p>
    </div>
</template>

<script setup>
import CustomerProductCard from '@/Pages/Customer/Menu/CustomerProductCard.vue';

defineProps({
    favorites: { type: Array, required: true }
});

defineEmits(['favorite-click']);
</script>
