<template>
    <div class="snap-start shrink-0 w-40 bg-surface-2 border border-surface-border rounded-2xl overflow-hidden shadow-sm flex flex-col relative group">

        <!-- Selo de Custo -->
        <div class="absolute top-2 right-2 z-10 bg-white/90 backdrop-blur text-primary-600 text-[10px] font-black px-2 py-1 rounded-lg shadow-sm border border-primary-500/20">
            {{ product.loyalty_points_cost }} pts
        </div>

        <div class="h-28 bg-surface-3 relative overflow-hidden">
            <img v-if="product.image" :src="product.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
            <div v-else class="w-full h-full flex items-center justify-center text-surface-muted bg-surface-1">
                <i class="pi pi-image text-2xl opacity-50"></i>
            </div>
        </div>

        <div class="p-3 flex flex-col flex-1 justify-between gap-3">
            <span class="font-black text-sm leading-tight text-surface-text line-clamp-2">{{ product.name }}</span>

            <button
                @click="emit('redeem', product)"
                :disabled="!canRedeem"
                class="w-full py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all outline-none flex items-center justify-center gap-1.5 cursor-pointer border-none"
                :class="canRedeem ? 'bg-primary-500 text-white hover:bg-primary-600 shadow-md active:scale-95' : 'bg-surface-3 text-surface-muted cursor-not-allowed opacity-70'"
            >
                <i :class="canRedeem ? 'pi pi-check-circle' : 'pi pi-lock'" class="text-[10px]"></i>
                {{ canRedeem ? 'Resgatar' : 'Faltam Pontos' }}
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    product: {
        type: Object,
        required: true
    },
    canRedeem: {
        type: Boolean,
        required: true
    }
});

const emit = defineEmits(['redeem']);
</script>
