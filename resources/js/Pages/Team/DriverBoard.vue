<template>
    <div
        class="h-full flex flex-col overflow-y-auto hide-scrollbar pb-32 px-4 space-y-6"
        :style="{ paddingTop: 'calc(var(--app-header-height, 6rem) + 1rem)' }"
    >
        <!-- 1️⃣ SECÇÃO 1: EM ROTA (COMIGO) -->
        <section class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Em Rota</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500">
                    {{ inRouteDeliveries.length }}
                </span>
            </div>

            <!-- Rota Otimizada quando houver pedidos em rota -->
            <div v-if="inRouteDeliveries.length > 0">
                <button
                    @click="openGoogleMaps(inRouteDeliveries)"
                    type="button"
                    class="w-full bg-surface-1 text-primary-600 dark:text-primary-400 font-black text-xs uppercase tracking-wider py-3.5 px-4 rounded-2xl shadow-sm border border-surface-border flex items-center justify-center gap-2.5 active:scale-98 transition-all outline-none cursor-pointer hover:border-primary-500/40"
                >
                    <i class="pi pi-map text-base text-primary-500"></i>
                    <span>
                        {{ inRouteDeliveries.length > 1
                            ? `Gerar Rota Otimizada no Maps (${inRouteDeliveries.length})`
                            : 'Abrir Rota no Google Maps' }}
                    </span>
                </button>
            </div>

            <div v-if="inRouteDeliveries.length === 0" class="text-center py-5 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <i class="pi pi-truck text-xl opacity-40 mb-1 block"></i>
                <p class="text-xs font-bold">Nenhuma entrega em rota no momento.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in inRouteDeliveries"
                    :key="order.id"
                    :order="order"
                    status-badge="Em Rota"
                    status-tone="success"
                    @charge-pix="openPixModal"
                >
                    <template #actions>
                        <button
                            @click="updateStatus(order.id, 'delivered')"
                            :disabled="processingOrderId === order.id"
                            type="button"
                            class="w-full h-11 rounded-xl bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 border-none cursor-pointer shadow-sm active:scale-98"
                        >
                            <i class="pi pi-check-circle text-xs"></i>
                            <span>Confirmar Entrega</span>
                        </button>
                    </template>
                </DeliveryDriverCard>
            </div>
        </section>

        <!-- 2️⃣ SECÇÃO 2: DESPACHANDO (PRONTOS PARA SAIR) -->
        <section class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                    <span>Despachando (Prontos)</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-primary-500/10 text-primary-500">
                    {{ readyDeliveries.length }}
                </span>
            </div>

            <!-- Saída em lote quando houver 2+ pedidos prontos atribuídos a este motoboy -->
            <div v-if="myReadyDeliveries.length > 1">
                <button
                    @click="dispatchAllMyReady"
                    :disabled="isBatchDispatching"
                    type="button"
                    class="w-full h-11 rounded-2xl bg-primary-500 hover:bg-primary-600 disabled:opacity-50 text-white font-black text-xs uppercase tracking-wider shadow-sm active:scale-98 transition-all border-none cursor-pointer flex items-center justify-center gap-2"
                >
                    <i class="pi pi-truck text-xs"></i>
                    <span>Sair com Todos ({{ myReadyDeliveries.length }} Pedidos)</span>
                </button>
            </div>

            <div v-if="readyDeliveries.length === 0" class="text-center py-5 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <p class="text-xs font-medium">Nenhum pedido pronto aguardando saída.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in readyDeliveries"
                    :key="order.id"
                    :order="order"
                    :status-badge="isAssignedToMe(order) ? 'Pronto • Seu' : 'Aguardando caixa atribuir'"
                    :status-tone="isAssignedToMe(order) ? 'info' : 'warning'"
                    @charge-pix="openPixModal"
                >
                    <template #actions>
                        <button
                            v-if="isAssignedToMe(order)"
                            @click="updateStatus(order.id, 'dispatched')"
                            :disabled="processingOrderId === order.id"
                            type="button"
                            class="w-full h-11 rounded-xl bg-primary-500 hover:bg-primary-600 disabled:opacity-50 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 border-none cursor-pointer shadow-sm active:scale-98"
                        >
                            <i class="pi pi-truck text-xs"></i>
                            <span>Sair p/ Entrega</span>
                        </button>

                        <div
                            v-else
                            class="w-full h-11 px-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 font-black text-[10px] uppercase tracking-wider flex items-center justify-center gap-1.5 text-center"
                        >
                            <i class="pi pi-clock text-xs shrink-0"></i>
                            <span>Aguardando caixa atribuir</span>
                        </div>
                    </template>
                </DeliveryDriverCard>
            </div>
        </section>

        <!-- 3️⃣ SECÇÃO 3: NA COZINHA (EM PREPARO / FILA) -->
        <section class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <span>Na Cozinha</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    {{ kitchenDeliveries.length }}
                </span>
            </div>

            <div v-if="kitchenDeliveries.length === 0" class="text-center py-5 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <p class="text-xs font-medium">Nenhum pedido de delivery na cozinha no momento.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in kitchenDeliveries"
                    :key="order.id"
                    :order="order"
                    :status-badge="getKitchenBadge(order)"
                    :status-tone="isAssignedToMe(order) ? 'info' : 'neutral'"
                    @charge-pix="openPixModal"
                />
            </div>
        </section>

        <!-- MODAL PIX DO ESTAFETA -->
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
const processingOrderId = ref(null);
const isBatchDispatching = ref(false);

const openPixModal = (order) => {
    selectedPixOrder.value = order;
    isPixModalOpen.value = true;
};

const page = usePage();
const toast = useToast();

const authUser = computed(() => page.props.auth?.user || {});
const settings = computed(() => page.props.companySettings || {});

const localOrders = ref([...(page.props.initialOrders || [])]);

watch(() => page.props.initialOrders, (fresh) => {
    if (fresh) localOrders.value = [...fresh];
}, { deep: true });

const upsertOrder = (incomingOrder) => {
    if (!incomingOrder || incomingOrder.type !== 'delivery') return;

    const idx = localOrders.value.findIndex(o => Number(o.id) === Number(incomingOrder.id));
    if (idx > -1) {
        localOrders.value[idx] = { ...localOrders.value[idx], ...incomingOrder };
    } else {
        localOrders.value.unshift(incomingOrder);
    }

    if (incomingOrder.status === 'ready' && typeof navigator !== 'undefined' && navigator.vibrate) {
        navigator.vibrate([200, 100, 200]);
    }
};

onMounted(() => {
    const echo = useTenantEcho();
    echo.listenChannel('operations', '.order.created', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.OrderCreated', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.order.updated', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.OrderUpdated', (e) => upsertOrder(e.order || e));
});

const isAssignedToMe = (order) => {
    const userId = Number(authUser.value?.id || 0);
    const empId = Number(authUser.value?.employee_id || 0);

    return (
        (userId > 0 && (Number(order.driver_id) === userId || Number(order.courier?.user_id) === userId)) ||
        (empId > 0 && Number(order.courier_id) === empId)
    );
};

const isUnassigned = (order) => !order.driver_id && !order.courier_id;

// Filtro global: estritamente tipo 'delivery' e ainda ativo
const activeDeliveryOrders = computed(() => {
    return localOrders.value
        .filter(o =>
            o.type === 'delivery' &&
            !['closed', 'cancelled', 'canceled', 'delivered'].includes(o.status)
        )
        .sort((a, b) => Number(a.id) - Number(b.id));
});

// 1️⃣ Em Rota: pedidos atribuídos a este motoboy que já saíram para entrega
const inRouteDeliveries = computed(() => {
    return activeDeliveryOrders.value.filter(o =>
        isAssignedToMe(o) &&
        ['dispatched', 'out_for_delivery'].includes(o.status)
    );
});

// 2️⃣ Despachando: pedidos prontos (atribuídos a este motoboy ou aguardando atribuição do caixa)
const readyDeliveries = computed(() => {
    return activeDeliveryOrders.value.filter(o =>
        ['ready', 'ready_for_pickup'].includes(o.status) &&
        (isAssignedToMe(o) || isUnassigned(o))
    );
});

const myReadyDeliveries = computed(() => {
    return readyDeliveries.value.filter(o => isAssignedToMe(o));
});

// 3️⃣ Na Cozinha: pedidos em fila/preparo (atribuídos a este motoboy ou ainda sem atribuição)
const kitchenDeliveries = computed(() => {
    return activeDeliveryOrders.value.filter(o =>
        ['pending', 'confirmed', 'preparing'].includes(o.status) &&
        (isAssignedToMe(o) || isUnassigned(o))
    );
});

const getKitchenBadge = (order) => {
    const step = order.status === 'preparing' ? 'Em Preparo' : 'Na Fila';
    const owner = isAssignedToMe(order) ? 'Seu' : 'Sem motoboy';
    return `${step} • ${owner}`;
};

const updateStatus = (orderId, status) => {
    if (processingOrderId.value) return;
    processingOrderId.value = orderId;

    router.post(route('operations.orders.update-status', orderId), { status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (status === 'delivered') {
                toast.add({ severity: 'success', summary: 'Entrega Concluída', detail: `Pedido #${orderId} marcado como entregue!`, life: 3000 });
            } else if (status === 'dispatched') {
                toast.add({ severity: 'info', summary: 'Em Rota', detail: `Pedido #${orderId} saiu para entrega.`, life: 3000 });
            }
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Operação Bloqueada',
                detail: errors.status || 'Não foi possível atualizar o status do pedido.',
                life: 4500
            });
        },
        onFinish: () => {
            processingOrderId.value = null;
        }
    });
};

const dispatchAllMyReady = async () => {
    const queue = [...myReadyDeliveries.value];
    if (queue.length === 0 || isBatchDispatching.value) return;

    isBatchDispatching.value = true;
    for (const order of queue) {
        await new Promise((resolve) => {
            router.post(route('operations.orders.update-status', order.id), { status: 'dispatched' }, {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    toast.add({
                        severity: 'error',
                        summary: `Pedido #${order.id}`,
                        detail: errors.status || 'Falha ao despachar pedido.',
                        life: 4000
                    });
                },
                onFinish: () => resolve()
            });
        });
    }
    isBatchDispatching.value = false;
    toast.add({
        severity: 'success',
        summary: 'Saída Confirmada',
        detail: `${queue.length} entregas colocadas em rota.`,
        life: 3000
    });
};

const resolveDestinationString = (order) => {
    const addr = order.address_snapshot || order.address || order.delivery_address;
    if (typeof addr === 'object' && addr !== null) {
        const rawLoc = addr.location_link || addr.location;
        if (rawLoc) {
            const decoded = decodeURIComponent(String(rawLoc));
            const match = decoded.match(/(-?\d{1,2}\.\d+)\s*[,/]\s*(-?\d{1,3}\.\d+)/);
            if (match) return `${match[1]},${match[2]}`;
        }
        if (addr.coordinates && typeof addr.coordinates === 'object') {
            const lat = addr.coordinates.lat || addr.coordinates.latitude;
            const lng = addr.coordinates.lng || addr.coordinates.longitude;
            if (lat && lng) return `${lat},${lng}`;
        }

        const cityStr = settings.value.city ? `, ${settings.value.city}` : '';
        const stateStr = settings.value.state ? ` - ${settings.value.state}` : '';

        return `${addr.street || ''}, ${addr.number || ''}, ${addr.neighborhood || ''}${cityStr}${stateStr}`;
    }

    const cityStrFallback = settings.value.city ? `, ${settings.value.city}` : '';
    const stateStrFallback = settings.value.state ? ` - ${settings.value.state}` : '';
    return String(order.address || '') + cityStrFallback + stateStrFallback;
};

const openGoogleMaps = (ordersList) => {
    if (!ordersList || ordersList.length === 0) return;

    const origin = `${settings.value.street || ''}, ${settings.value.number || ''}, ${settings.value.city || ''}`;
    const lastOrder = ordersList[ordersList.length - 1];
    const destination = resolveDestinationString(lastOrder);

    let url = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(origin)}&destination=${encodeURIComponent(destination)}`;

    if (ordersList.length > 1) {
        const waypoints = ordersList.slice(0, -1).map(o => resolveDestinationString(o));
        url += `&waypoints=optimize:true|${waypoints.map(w => encodeURIComponent(w)).join('|')}`;
    }

    window.open(url, '_blank');
};
</script>