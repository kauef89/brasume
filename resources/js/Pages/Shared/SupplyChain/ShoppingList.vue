<template>
    <!-- A Ilha Bentô -->
    <div class="flex flex-col h-full w-full bg-surface-1 p-4 md:p-6 relative">
        <div class="flex-1 flex flex-col h-full bg-surface-2 rounded-[2rem] overflow-hidden shadow-sm border border-surface-border min-h-0 animate-in fade-in duration-300">

            <!-- HEADER -->
            <div class="p-6 border-b border-surface-border flex justify-between items-center bg-surface-2 shrink-0">
                <div>
                    <h2 class="text-2xl font-black text-surface-text leading-tight">Lista de Compras Inteligente</h2>
                    <p class="text-sm font-medium text-surface-muted mt-1 uppercase tracking-widest">
                        Cotações agrupadas por fornecedor e histórico de preços.
                    </p>
                </div>
                <button @click="fetchShoppingList(true)" class="w-10 h-10 rounded-full bg-surface-3 hover:bg-surface-border transition-colors flex items-center justify-center outline-none cursor-pointer">
                    <i class="pi pi-refresh" :class="{'pi-spin text-primary-500': isLoading}"></i>
                </button>
            </div>

            <!-- CONTEÚDO -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-surface-1/30 hide-scrollbar">

                <!-- Loader -->
                <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 opacity-50">
                    <i class="pi pi-spin pi-spinner text-4xl mb-3 text-primary-500"></i>
                    <span class="text-xs font-bold tracking-widest uppercase text-surface-muted">Processando Cotações...</span>
                </div>

                <!-- Cards -->
                <div v-else-if="Object.keys(localMatrix).length > 0" class="flex flex-col gap-6">
                    <ShoppingSupplierCard
                        v-for="(data, supplierId) in localMatrix"
                        :key="supplierId"
                        :supplier-id="supplierId"
                        :supplier="data"
                        @remove-item="removeItem"
                        @approve-order="approveOrder"
                    />
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center h-full text-surface-muted opacity-60">
                    <i class="pi fa-square-check text-6xl mb-4"></i>
                    <h3 class="text-xl font-bold">Tudo abastecido!</h3>
                    <p class="text-sm mt-1">Não há intenções de compra pendentes.</p>
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
import ShoppingSupplierCard from '@/Pages/Shared/SupplyChain/ShoppingSupplierCard.vue';

// Layout nativo do PC
defineOptions({ layout: DesktopLayout });

const props = defineProps({
    biddingMatrix: { type: Object, default: null }
});

const page = usePage();
const toast = useToast();

const localMatrix = ref(props.biddingMatrix || {});
const isLoading = ref(!props.biddingMatrix);

const fetchShoppingList = async (forceRefresh = false) => {
    if (!forceRefresh && props.biddingMatrix) return;

    isLoading.value = true;
    try {
        const response = await axios.get(route('operations.shopping-list'), {
            headers: { 'X-Inertia': 'true', 'X-Inertia-Version': page.version }
        });

        const fetched = response.data?.props?.biddingMatrix;

        // Blindagem contra bug da string
        localMatrix.value = (typeof fetched === 'object' && fetched !== null) ? fetched : {};
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao buscar intenções de compra.', life: 3000 });
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchShoppingList();
});

const removeItem = async (itemId) => {
    try {
        await axios.post(route('operations.shopping-list.remove'), { id: itemId });
        fetchShoppingList(true);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível remover o item.', life: 3000 });
    }
};

const approveOrder = async (supplierId, items) => {
    try {
        await axios.post(route('operations.shopping-list.approve'), {
            supplier_id: supplierId,
            items: items.map(i => ({ id: i.id, qty: i.suggested_qty }))
        });
        toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Pedido aprovado e enviado ao financeiro.', life: 3000 });
        fetchShoppingList(true);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao aprovar pedido.', life: 3000 });
    }
};
</script>
