<template>
    <div
        class="h-full flex flex-col overflow-y-auto hide-scrollbar pb-32 px-4 space-y-6"
        :style="{ paddingTop: 'calc(var(--app-header-height, 6rem) + 1rem)' }"
    >
        <!-- 🧭 CONTROLO DE ABAS VIA TELEPORT PARA O TOPO (SegmentedControl) -->
        <Teleport to="#team-header-actions" :disabled="!hasTeleportTarget">
            <SegmentedControl
                v-model="activeTab"
                :options="tabOptions"
            />
        </Teleport>

        <!-- 🗺️ ROTA OTIMIZADA NO GOOGLE MAPS -->
        <div v-if="myDeliveries.length > 0 && (activeTab === 'all' || activeTab === 'route')" class="shrink-0">
            <button
                @click="openGoogleMaps"
                type="button"
                class="w-full bg-surface-1 text-primary-600 dark:text-primary-400 font-black text-xs uppercase tracking-wider py-3.5 px-4 rounded-2xl shadow-sm border border-surface-border flex items-center justify-center gap-2.5 active:scale-98 transition-all outline-none cursor-pointer hover:border-primary-500/40"
            >
                <i class="pi fa-location-dot text-base text-primary-500"></i>
                <span>Gerar Rota Otimizada no Google Maps ({{ myDeliveries.length }})</span>
            </button>
            <p v-if="myDeliveries.length > 1" class="text-center text-[10px] text-surface-muted mt-1.5 font-medium">
                Os pontos com GPS e moradas serão ordenados para o menor trajeto.
            </p>
        </div>

        <!-- 🛵 SECÇÃO 1: COMIGO (EM ROTA) -->
        <div v-if="activeTab === 'all' || activeTab === 'route'" class="space-y-3">
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
                <p class="text-xs font-bold">Nenhum pedido na mochila de momento.</p>
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
                        </div>
                    </template>
                </DeliveryDriverCard>
            </div>
        </div>

        <!-- 📦 SECÇÃO 2: EM DESPACHO (PRONTOS NO BALCÃO) -->
        <div v-if="activeTab === 'all' || activeTab === 'dispatch'" class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                    <span>Em Despacho (Prontos no Balcão)</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-primary-500/10 text-primary-500">
                    {{ readyDeliveries.length }}
                </span>
            </div>

            <div v-if="readyDeliveries.length === 0" class="text-center py-5 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <p class="text-xs font-medium">Nenhum pedido pronto a aguardar retirada.</p>
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
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';

const activeTab = ref('all');
const hasTeleportTarget = ref(false);

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
    hasTeleportTarget.value = typeof document !== 'undefined' && Boolean(document.getElementById('team-header-actions'));

    const echo = useTenantEcho();
    echo.listenChannel('operations', '.order.created', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.OrderCreated', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.order.updated', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.OrderUpdated', (e) => upsertOrder(e.order || e));
});

// Pedidos atribuídos ao estafeta (Em Rota)
const myDeliveries = computed(() => {
    const userId = authUser.value.id;
    return localOrders.value.filter(o =>
        o.type === 'delivery' &&
        Number(o.driver_id) === Number(userId) &&
        o.status !== 'closed' &&
        o.status !== 'cancelled'
    );
});

// Pedidos prontos no balcão (Em Despacho)
const readyDeliveries = computed(() => {
    return localOrders.value.filter(o =>
        o.type === 'delivery' &&
        !o.driver_id &&
        o.status === 'ready'
    );
});

const tabOptions = computed(() => [
    { value: 'all', label: `Todos (${myDeliveries.value.length + readyDeliveries.value.length})` },
    { value: 'route', label: `Em Rota (${myDeliveries.value.length})`, icon: 'pi-truck' },
    { value: 'dispatch', label: `Em Despacho (${readyDeliveries.value.length})`, icon: 'pi-box' },
]);

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
        if (addr.location) {
            const match = String(addr.location).match(/(-?\d{1,2}\.\d+)\s*[,/]\s*(-?\d{1,3}\.\d+)/);
            if (match) return `${match[1]},${match[2]}`;
        }
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