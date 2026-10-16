<template>
    <!-- A Ilha Bentô -->
    <div class="flex flex-col h-full w-full bg-surface-1 p-4 md:p-6 relative">
        <div class="flex-1 flex flex-col h-full bg-surface-2 rounded-[2rem] overflow-hidden shadow-sm border border-surface-border min-h-0 animate-in fade-in duration-300">

            <!-- HEADER -->
            <div class="p-6 border-b border-surface-border flex justify-between items-center bg-surface-2 shrink-0">
                <div>
                    <h2 class="text-2xl font-black text-surface-text leading-tight">Quadro de Produção (MRP)</h2>
                    <p class="text-sm font-medium text-surface-muted mt-1 uppercase tracking-widest">
                        Ordens de preparo interno para reabastecimento.
                    </p>
                </div>
                <button @click="fetchOrders(true)" class="w-10 h-10 rounded-full bg-surface-3 hover:bg-surface-border transition-colors flex items-center justify-center outline-none cursor-pointer">
                    <i class="pi pi-refresh" :class="{'pi-spin text-primary-500': isLoading}"></i>
                </button>
            </div>

            <!-- CONTEÚDO -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-surface-1/30 hide-scrollbar">

                <!-- Loader -->
                <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 opacity-50">
                    <i class="pi pi-spin pi-spinner text-4xl mb-3 text-primary-500"></i>
                    <span class="text-xs font-bold tracking-widest uppercase text-surface-muted">Buscando Ordens...</span>
                </div>

                <!-- Cards -->
                <div v-else-if="localOrders.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <ProductionOrderCard
                        v-for="order in localOrders"
                        :key="order.id"
                        :order="order"
                        @update-status="handleUpdateStatus"
                        @finish-order="handleFinishOrder"
                    />
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center h-full text-surface-muted opacity-60">
                    <i class="pi pi-thumbs-up text-6xl mb-4"></i>
                    <h3 class="text-xl font-bold">Produção em dia!</h3>
                    <p class="text-sm mt-1">Nenhuma ordem pendente na cozinha.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { usePage } from '@inertiajs/vue3';
import DesktopLayout from '@/Layouts/DesktopLayout.vue';
import ProductionOrderCard from '@/Pages/Shared/SupplyChain/ProductionOrderCard.vue';

// No Desktop, usa o layout. No Celular (dentro do TeamApp), o Inertia ignora isso. Mágica!
defineOptions({ layout: DesktopLayout });

const props = defineProps({
    // Recebe as ordens via Inertia (se estiver no PC). Se for null, estamos no PWA (Celular).
    orders: { type: Array, default: null }
});

const page = usePage();
const toast = useToast();

const localOrders = ref(props.orders || []);
const isLoading = ref(!props.orders);

const fetchOrders = async (forceRefresh = false) => {
    // Se não for um refresh forçado e as ordens já vieram do PC, não precisa buscar!
    if (!forceRefresh && props.orders) return;

    isLoading.value = true;
    try {
        // X-Inertia: true força o Laravel a devolver JSON limpo em vez de HTML!
        const response = await axios.get(route('operations.production-board'), {
            headers: { 'X-Inertia': 'true', 'X-Inertia-Version': page.version }
        });

        const fetched = response.data?.props?.orders;

        // A blindagem que impede o travamento do computador:
        localOrders.value = Array.isArray(fetched) ? fetched : [];
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao buscar as ordens de produção.', life: 3000 });
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchOrders();
});

const handleUpdateStatus = async ({ id, status }) => {
    try {
        await axios.post(route('operations.production-board.status'), { id, status });
        fetchOrders(true);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível atualizar o status.', life: 3000 });
    }
};

const handleFinishOrder = async (order) => {
    try {
        await axios.post(route('operations.production-board.finish'), {
            id: order.id,
            actual_qty: order.planned_qty
        });
        toast.add({ severity: 'success', summary: 'Boa!', detail: 'Estoque do produto reabastecido.', life: 3000 });
        fetchOrders(true);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível concluir a ordem.', life: 3000 });
    }
};
</script>
