<template>
    <div class="flex-1 w-full h-full flex flex-col overflow-hidden select-none">
        <div
            class="flex-1 overflow-y-auto hide-scrollbar px-4 pb-4 flex flex-col gap-3 relative"
            :style="{ top: 'var(--app-header-height, 7rem)' }"
        >

            <!-- 👻 NÍVEL 2: GUEST USER (Conversão) -->
            <div v-if="isGuest" class="c-glass-card p-8 m-2 text-center flex flex-col items-center justify-center">
                <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mb-4">
                    <i class="pi pi-history text-3xl text-white/50"></i>
                </div>
                <h2 class="c-title text-xl mb-2">Histórico Oculto</h2>
                <p class="c-text-muted mb-6 leading-relaxed max-w-sm">
                    Você está comprando como visitante. Crie uma conta gratuita para salvar seus pedidos e repetir compras com apenas um toque!
                </p>

                <div class="flex flex-col gap-2.5 w-full max-w-xs mx-auto">
                    <button @click="$emit('open-register')" class="c-btn-primary !h-12 !text-xs">
                        <i class="pi pi-user-plus text-xs"></i> Criar Conta Grátis
                    </button>
                    <button @click="$emit('open-login')" class="c-btn-ghost !h-11 !text-xs">
                        Já tenho conta
                    </button>
                </div>
            </div>

            <!-- 👤 NÍVEL 3: CLIENTE LOGADO -->
            <template v-else>
                <template v-if="myHistory && myHistory.length > 0">
                    <CustomerOrderHistoryCard
                        v-for="order in visibleHistory"
                        :key="order.id"
                        :order="order"
                        class="shrink-0"
                        @reorder="$emit('reorder', $event)"
                    />

                    <!-- Botão de Carregar Mais -->
                    <button
                        v-if="hasMoreOrders"
                        @click="loadMore"
                        class="c-btn-ghost p-6! my-2 text-white/70 active:text-white"
                    >
                        <i class="pi pi-history text-xs"></i> Carregar Mais Antigos
                    </button>

                    <div class="h-20 shrink-0 w-full pointer-events-none"></div>
                </template>

                <!-- EMPTY STATE -->
                <div v-else class="c-glass-card text-center py-12 px-6 mt-10 flex flex-col items-center justify-center">
                    <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-4">
                        <i class="pi pi-receipt text-3xl text-white/30"></i>
                    </div>
                    <h3 class="c-title !text-base mb-1">Nenhum pedido anterior</h3>
                    <p class="c-text-muted max-w-xs leading-relaxed mb-6">
                        Parece que você ainda não fez nenhum pedido conosco. Que tal experimentar hoje?
                    </p>
                    <button
                        @click="$emit('go-to-menu')"
                        class="c-btn-primary max-w-xs !h-12 !text-xs"
                    >
                        Explorar Cardápio
                    </button>
                </div>
            </template>

        </div>
    </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import CustomerOrderHistoryCard from '@/Pages/Customer/Orders/CustomerOrderHistoryCard.vue';

const props = defineProps({
    myHistory: { type: Array, default: () => [] },
    customer: { type: Object, default: null },
    isGuest: { type: Boolean, default: false } // 🚀 Adicionamos a prop do Nível 2
});

defineEmits(['reorder', 'go-to-menu', 'open-login', 'open-register']);

const displayLimit = ref(10);

const sortedHistory = computed(() => {
    if (!props.myHistory) return [];
    return [...props.myHistory].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

const visibleHistory = computed(() => {
    return sortedHistory.value.slice(0, displayLimit.value);
});

const hasMoreOrders = computed(() => {
    return displayLimit.value < sortedHistory.value.length;
});

const loadMore = () => {
    displayLimit.value += 10;
};
</script>
