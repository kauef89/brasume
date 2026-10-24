<template>
    <div
        class="h-full flex flex-col overflow-y-auto hide-scrollbar pb-32 px-4 space-y-6"
        :style="{ paddingTop: 'calc(var(--app-header-height, 6rem) + 1rem)' }"
    >
        <!-- 🗺️ AÇÃO GLOBAL: GOOGLE MAPS INTELIGENTE -->
        <div v-if="myDeliveries.length > 0" class="shrink-0">
            <button
                @click="openGoogleMaps"
                type="button"
                class="w-full bg-surface-1 text-primary-600 dark:text-primary-400 font-black text-xs uppercase tracking-wider py-3.5 px-4 rounded-2xl shadow-sm border border-surface-border flex items-center justify-center gap-2.5 active:scale-98 transition-all outline-none cursor-pointer hover:border-primary-500/40"
            >
                <i class="pi fa-location-dot text-base text-primary-500"></i>
                <span>Gerar Rota Otimizada no Google Maps ({{ myDeliveries.length }})</span>
            </button>
            <p v-if="myDeliveries.length > 1" class="text-center text-[10px] text-surface-muted mt-1.5 font-medium">
                Os waypoints serão ordenados para o menor trajeto.
            </p>
        </div>

        <!-- 🛵 SEÇÃO 1: COMIGO (NA MOCHILA / EM ROTA) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Comigo (Em Rota)</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500">
                    {{ myDeliveries.length }}
                </span>
            </div>

            <div v-if="myDeliveries.length === 0" class="text-center py-6 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <i class="pi pi-box text-xl opacity-40 mb-1 block"></i>
                <p class="text-xs font-bold">Nenhum pedido na mochila no momento.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in myDeliveries"
                    :key="order.id"
                    :order="order"
                    @charge-pix="openPixModal"
                >
                    <template #actions>
                        <div class="flex gap-2 pt-1">
                            <button
                                v-if="order.status !== 'dispatched'"
                                @click="updateStatus(order.id, 'dispatched')"
                                type="button"
                                class="flex-1 h-11 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 border-none cursor-pointer shadow-sm active:scale-98"
                            >
                                <i class="pi pi-truck text-xs"></i>
                                <span>Sair p/ Entrega</span>
                            </button>

                            <button
                                v-if="order.status === 'dispatched'"
                                @click="updateStatus(order.id, 'closed')"
                                type="button"
                                class="flex-1 h-11 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 border-none cursor-pointer shadow-sm active:scale-98"
                            >
                                <i class="pi pi-check-circle text-xs"></i>
                                <span>Confirmar Entrega</span>
                            </button>
                                <!-- 🚀 MODAL PIX DO MOTOBOY -->
        <OrderPixModal
            v-if="selectedPixOrder"
            v-model:visible="isPixModalOpen"
            :order="selectedPixOrder"
            @payment-success="isPixModalOpen = false"
        />
    </div>
</template>
                </DeliveryDriverCard>
            </div>
        </div>

        <!-- 📦 SEÇÃO 2: PRONTOS NO BALCÃO (AGUARDANDO MOTOBOY) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                    <span>Prontos para Retirada</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-primary-500/10 text-primary-500">
                    {{ readyDeliveries.length }}
                </span>
            </div>

            <div v-if="readyDeliveries.length === 0" class="text-center py-5 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <p class="text-xs font-medium">Nenhum pedido pronto aguardando retirada.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in readyDeliveries"
                    :key="order.id"
                    :order="order"
                    @charge-pix="openPixModal"
                >
                    <template #actions>
                        <button
                            @click="assignToMe(order.id)"
                            type="button"
                            class="w-full h-11 rounded-xl bg-surface-text text-surface-1 font-black text-xs uppercase tracking-wider shadow-sm active:scale-98 transition-all border-none cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <i class="pi pi-plus text-xs"></i>
                            <span>Pegar Pedido</span>
                        </button>
                    </template>
                </DeliveryDriverCard>
            </div>
        </div>

        <!-- 🍳 SEÇÃO 3: NA COZINHA (EM PREPARO) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-muted uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                    <span>Na Cozinha (Em Preparo)</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500">
                    {{ myDeliveries.length }}
                </span>
            </div>

            <div v-if="myDeliveries.length === 0" class="text-center py-6 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <i class="pi pi-box text-xl opacity-40 mb-1 block"></i>
                <p class="text-xs font-bold">Nenhum pedido na mochila no momento.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in myDeliveries"
                    :key="order.id"
                    :order="order"
                >
                    <template #actions>
                        <div class="flex gap-2 pt-1">
                            <button
                                v-if="order.status !== 'dispatched'"
                                @click="updateStatus(order.id, 'dispatched')"
                                type="button"
                                class="flex-1 h-11 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 border-none cursor-pointer shadow-sm active:scale-98"
                            >
                                <i class="pi pi-truck text-xs"></i>
                                <span>Sair p/ Entrega</span>
                            </button>

                            <button
                                v-if="order.status === 'dispatched'"
                                @click="updateStatus(order.id, 'closed')"
                                type="button"
                                class="flex-1 h-11 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 border-none cursor-pointer shadow-sm active:scale-98"
                            >
                                <i class="pi pi-check-circle text-xs"></i>
                                <span>Confirmar Entrega</span>
                            </button>
                                <!-- 🚀 MODAL PIX DO MOTOBOY -->
        <OrderPixModal
            v-if="selectedPixOrder"
            v-model:visible="isPixModalOpen"
            :order="selectedPixOrder"
            @payment-success="isPixModalOpen = false"
        />
    </div>
</template>
                </DeliveryDriverCard>
            </div>
        </div>

        <!-- 📦 SEÇÃO 2: PRONTOS NO BALCÃO (AGUARDANDO MOTOBOY) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                    <span>Prontos para Retirada</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-primary-500/10 text-primary-500">
                    {{ readyDeliveries.length }}
                </span>
            </div>

            <div v-if="readyDeliveries.length === 0" class="text-center py-5 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <p class="text-xs font-medium">Nenhum pedido pronto aguardando retirada.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in readyDeliveries"
                    :key="order.id"
                    :order="order"
                >
                    <template #actions>
                        <button
                            @click="assignToMe(order.id)"
                            type="button"
                            class="w-full h-11 rounded-xl bg-surface-text text-surface-1 font-black text-xs uppercase tracking-wider shadow-sm active:scale-98 transition-all border-none cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <i class="pi pi-plus text-xs"></i>
                            <span>Pegar Pedido</span>
                        </button>
                    </template>
                </DeliveryDriverCard>
            </div>
        </div>

        <!-- 🍳 SEÇÃO 3: NA COZINHA (EM PREPARO) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-muted uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                    <span>Na Cozinha (Em Preparo)</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-surface-3 text-surface-muted">
                    {{ kitchenDeliveries.length }}
                </span>
            </div>

            <div v-if="kitchenDeliveries.length === 0" class="text-center py-4 text-surface-muted text-xs font-medium">
                Nenhum delivery em produção na cozinha.
            </div>

            <div v-else class="space-y-3 opacity-80">
                <DeliveryDriverCard
                    v-for="order in kitchenDeliveries"
                    :key="order.id"
                    :order="order"
                />
            </div>
        </div>
            <!-- 🚀 MODAL PIX DO MOTOBOY -->
        <OrderPixModal
            v-if="selectedPixOrder"
            v-model:visible="isPixModalOpen"
            :order="selectedPixOrder"
            @payment-success="isPixModalOpen = false"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useTenantEcho } from '@/Composables/useTenantEcho';
import DeliveryDriverCard from '@/Pages/Team/DeliveryDriverCard.vue';
import OrderPixModal from '@/Pages/Shared/OrderPixModal.vue';

const isPixModalOpen = ref(false);
const selectedPixOrder = ref(null);

const openPixModal = (order) => {
    selectedPixOrder.value = order;
    isPixModalOpen.value = true;
};

const page = usePage();
const toast = useToast();

const authUser = computed(() => page.props.auth?.user || {});
const settings = computed(() => page.props.companySettings || {});

// 🚀 Lista reativa local alimentada pelo Inertia e mantida viva pelo Reverb
const localOrders = ref([...(page.props.initialOrders || [])]);

watch(() => page.props.initialOrders, (fresh) => {
    if (fresh) localOrders.value = [...fresh];
}, { deep: true });

// 🔄 Atualização em tempo real (Upsert)
const upsertOrder = (incomingOrder) => {
    if (!incomingOrder || incomingOrder.type !== 'delivery') return;

    const idx = localOrders.value.findIndex(o => Number(o.id) === Number(incomingOrder.id));
    if (idx > -1) {
        localOrders.value[idx] = { ...localOrders.value[idx], ...incomingOrder };
    } else {
        localOrders.value.unshift(incomingOrder);
    }

    // Feedback háptico (vibração no smartphone do motoboy quando fica pronto)
    if (incomingOrder.status === 'ready' && typeof navigator !== 'undefined' && navigator.vibrate) {
        navigator.vibrate([200, 100, 200]);
    }
};

onMounted(() => {
    const echo = useTenantEcho();

    // Escuta criação e atualização de pedidos via Reverb no canal operations
    echo.listenChannel('operations', '.order.created', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.OrderCreated', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.order.updated', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.OrderUpdated', (e) => upsertOrder(e.order || e));
});

// 🛵 1. Pedidos atribuídos ao motoboy logado
const myDeliveries = computed(() => {
    const userId = authUser.value.id;
    return localOrders.value.filter(o =>
        o.type === 'delivery' &&
        Number(o.driver_id) === Number(userId) &&
        o.status !== 'closed' &&
        o.status !== 'cancelled'
    );
});

// 📦 2. Pedidos prontos no balcão aguardando retirada
const readyDeliveries = computed(() => {
    return localOrders.value.filter(o =>
        o.type === 'delivery' &&
        !o.driver_id &&
        o.status === 'ready'
    );
});

// 🍳 3. Pedidos em preparo na cozinha
const kitchenDeliveries = computed(() => {
    return localOrders.value.filter(o =>
        o.type === 'delivery' &&
        !o.driver_id &&
        (o.status === 'preparing' || o.status === 'confirmed')
    );
});

const assignToMe = (orderId) => {
    router.post(route('operations.orders.assign-courier', orderId), { driver_id: authUser.value.id }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.add({
            severity: 'success',
            summary: 'Pedido Atribuído',
            detail: `Comanda #${orderId} adicionada à sua rota.`,
            life: 3000
        })
    });
};

const updateStatus = (orderId, status) => {
    router.post(route('operations.orders.update-status', orderId), { status: status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (status === 'closed') {
                toast.add({ severity: 'success', summary: 'Entrega Concluída', detail: `Pedido #${orderId} finalizado com sucesso!`, life: 3000 });
            } else if (status === 'dispatched') {
                toast.add({ severity: 'info', summary: 'Em Rota', detail: `Pedido #${orderId} marcado como em rota de entrega.`, life: 3000 });
            }
        }
    });
};

const resolveDestinationString = (order) => {
    const addr = order.address_snapshot || order.address;
    if (typeof addr === 'object' && addr !== null) {
        return `${addr.street || ''}, ${addr.number || ''}, ${addr.neighborhood || ''}, ${settings.value.city || ''}`;
    }
    return String(order.address || '');
};

const openGoogleMaps = () => {
    if (myDeliveries.value.length === 0) return;

    const origin = `${settings.value.street || ''}, ${settings.value.number || ''}, ${settings.value.city || ''}`;
    const lastOrder = myDeliveries.value[myDeliveries.value.length - 1];
    const destination = resolveDestinationString(lastOrder);

    let url = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(origin)}&destination=${encodeURIComponent(destination)}`;

    if (myDeliveries.value.length > 1) {
        const waypoints = myDeliveries.value.slice(0, -1).map(o => resolveDestinationString(o));
        url += `&waypoints=optimize:true|${waypoints.map(w => encodeURIComponent(w)).join('|')}`;
    }

    window.open(url, '_blank');
};
</script>
