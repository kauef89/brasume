<template>
    <div class="h-full flex flex-col overflow-y-auto hide-scrollbar pb-32">
        <!-- Cabeçalho -->
        <div class="bg-primary-600 dark:bg-primary-800 p-6 text-white shadow-md rounded-b-[2rem] shrink-0">
            <h1 class="text-xl font-bold mb-1">Painel do Entregador</h1>
            <p class="text-primary-100 text-sm">Gerencie suas rotas e entregas</p>
        </div>

        <!-- Ação Global: Roteamento Inteligente (Aparece se tiver > 1 pedido em rota) -->
        <div class="px-4 -mt-5 relative z-10 shrink-0" v-if="myDeliveries.length > 0">
            <button
                @click="openGoogleMaps"
                class="w-full bg-surface-0 dark:bg-surface-2 text-primary-600 dark:text-primary-400 font-black text-sm py-4 rounded-2xl shadow-lg border border-surface-border flex items-center justify-center gap-3 active:scale-95 transition-transform outline-none"
            >
                <i class="pi fa-location-dot text-lg"></i>
                Gerar Rota no Google Maps
            </button>
            <p v-if="myDeliveries.length > 1" class="text-center text-[10px] text-surface-muted mt-2 font-medium">A rota será otimizada automaticamente.</p>
        </div>

        <div class="p-4 mt-2 flex-1 space-y-6">

            <!-- COLUNA 1: COMIGO (EM ROTA) -->
            <div>
                <h3 class="font-black text-surface-text mb-3 px-2 uppercase tracking-widest text-xs opacity-50">
                    Comigo (Em Rota) - {{ myDeliveries.length }}
                </h3>
                <div v-if="myDeliveries.length === 0" class="text-center py-6 border-2 border-dashed border-surface-border rounded-2xl text-surface-muted">
                    <p class="text-xs font-medium">Nenhum pedido na mochila.</p>
                </div>
                <div class="space-y-3">
                    <div v-for="order in myDeliveries" :key="order.id"
                         class="bg-surface-0 dark:bg-surface-2 p-4 rounded-2xl shadow-sm border"
                         :class="hasAttentionItems(order) ? 'border-warning-500 bg-warning-50 dark:bg-warning-900/20' : 'border-surface-border'">

                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="text-xs font-black text-primary-500 uppercase">#{{ $formatNumber(order.id) }}</span>
                                <h4 class="font-bold text-surface-text leading-tight mt-0.5">{{ order.customer_name }}</h4>
                            </div>
                            <span v-if="hasAttentionItems(order)" class="bg-warning-500 text-white text-[9px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider">
                                Atenção (Bebida)
                            </span>
                        </div>

                        <p class="text-xs text-surface-muted font-medium mb-3 leading-snug">
                            <i class="pi fa-location-dot mr-1"></i> {{ order.address }}
                        </p>

                        <div class="flex gap-2">
                            <button v-if="order.status !== 'dispatched'" @click="updateStatus(order.id, 'dispatched')" class="flex-1 py-2.5 rounded-xl bg-primary-100 text-primary-700 font-bold text-xs active:scale-95 transition-all">
                                <i class="pi pi-motorcycle mr-1"></i> Sair p/ Entrega
                            </button>
                            <button v-if="order.status === 'dispatched'" @click="updateStatus(order.id, 'closed')" class="flex-1 py-2.5 rounded-xl bg-success-500 text-white font-bold text-xs shadow-sm active:scale-95 transition-all">
                                <i class="pi pi-check-circle mr-1"></i> Entregue
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUNA 2: AGUARDANDO MOTOBOY (NA LOJA) -->
            <div>
                <h3 class="font-black text-surface-text mb-3 px-2 uppercase tracking-widest text-xs opacity-50">
                    Aguardando Retirada - {{ availableDeliveries.length }}
                </h3>
                <div v-if="availableDeliveries.length === 0" class="text-center py-6 text-surface-muted">
                    <p class="text-xs font-medium">Nenhum pedido pronto no balcão.</p>
                </div>
                <div class="space-y-3">
                    <div v-for="order in availableDeliveries" :key="order.id"
                         class="bg-surface-0 dark:bg-surface-2 p-4 rounded-2xl shadow-sm border border-surface-border opacity-75">

                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-black text-surface-muted uppercase">#{{ $formatNumber(order.id) }} • {{ order.neighborhood }}</span>
                            <span class="text-[10px] font-bold" :class="order.minutes_active > 30 ? 'text-danger-500' : 'text-surface-muted'">
                                {{ order.minutes_active }} min
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-surface-text">{{ order.customer_name }}</h4>
                            <button @click="assignToMe(order.id)" class="py-2 px-4 rounded-xl bg-surface-800 dark:bg-surface-100 text-white dark:text-surface-900 font-bold text-xs shadow-sm active:scale-95 transition-all">
                                Pegar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

const page = usePage();
const toast = useToast();

const authUser = computed(() => page.props.auth.user);
const settings = computed(() => page.props.companySettings || {});
const allOrders = computed(() => page.props.initialOrders || []);

// 🚀 As Categorias que merecem atenção visual
const attentionCategories = computed(() => {
    return Array.isArray(settings.value.attention_categories)
        ? settings.value.attention_categories.map(id => parseInt(id))
        : [];
});

const hasAttentionItems = (order) => {
    if (!order.items) return false;
    return order.items.some(item => attentionCategories.value.includes(parseInt(item.category_id)));
};

// 📦 Pedidos que estão comigo (Atribuídos ao meu ID e não finalizados)
const myDeliveries = computed(() => {
    return allOrders.value.filter(o =>
        o.type === 'delivery' &&
        o.driver_id === authUser.value.id &&
        o.status !== 'closed' &&
        o.status !== 'cancelled'
    );
});

// ⏳ Pedidos prontos no balcão que ainda não têm motoboy
const availableDeliveries = computed(() => {
    return allOrders.value.filter(o =>
        o.type === 'delivery' &&
        !o.driver_id &&
        (o.status === 'ready' || o.status === 'preparing')
    ).sort((a, b) => b.minutes_active - a.minutes_active); // Mais antigos primeiro
});

// 🚀 AÇÕES DE RETAGUARDA (CHAMADAS AOS CONTROLLERS)
    const assignToMe = (orderId) => {
        // 🚀 CORREÇÃO: Uso de rota nomeada
        router.post(route('operations.orders.assign-courier', orderId), { driver_id: authUser.value.id }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast.add({ severity: 'success', summary: 'Atribuído', detail: 'Pedido adicionado à sua rota.', life: 3000 })
        });
    };

    const updateStatus = (orderId, status) => {
        // 🚀 CORREÇÃO: Uso de rota nomeada
        router.post(route('operations.orders.update-status', orderId), { status: status }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (status === 'closed') {
                    toast.add({ severity: 'success', summary: 'Entregue!', detail: 'Finalizado com sucesso.', life: 3000 });
                }
            }
        });
    };

// 🗺️ O TRUQUE DE OURO: Roteamento Inteligente no Google Maps!
const openGoogleMaps = () => {
    if (myDeliveries.value.length === 0) return;

    // A Origem é o endereço da Loja
    const origin = `${settings.value.street || ''}, ${settings.value.number || ''}, ${settings.value.city || ''}`;

    // O Destino é o último pedido
    const lastOrder = myDeliveries.value[myDeliveries.value.length - 1];
    const destination = lastOrder.address;

    let url = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(origin)}&destination=${encodeURIComponent(destination)}`;

    // Se tiver mais de um, usamos Waypoints Otimizados!
    if (myDeliveries.value.length > 1) {
        // Pega os endereços do meio do caminho
        const waypoints = myDeliveries.value.slice(0, -1).map(o => o.address);
        url += `&waypoints=optimize:true|${waypoints.map(w => encodeURIComponent(w)).join('|')}`;
    }

    // Abre o Maps
    window.open(url, '_blank');
};
</script>
