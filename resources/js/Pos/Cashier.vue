<template>
    <div class="flex h-screen w-screen bg-gray-50 dark:bg-gray-950 overflow-hidden font-sans text-gray-900 dark:text-gray-100">

        <div class="w-1/3 flex flex-col h-full border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xl z-10">
            <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex items-center gap-4">
                <Button icon="pi pi-home" variant="text" rounded severity="secondary" @click="goHome" />
                <div>
                    <h1 class="text-2xl font-black leading-tight">Fluxo de Caixa</h1>
                    <span class="text-sm font-bold text-gray-500 uppercase tracking-widest">Controle Operacional</span>
                </div>
            </div>

            <div class="p-8 flex-1 flex flex-col gap-6 overflow-y-auto">
                <div class="p-6 rounded-2xl flex flex-col items-center justify-center text-center transition-all border-2"
                    :class="currentShift ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'">
                    <i class="pi text-5xl mb-2" :class="currentShift ? 'pi-lock-open' : 'pi-lock'"></i>
                    <h2 class="text-2xl font-black uppercase tracking-widest">
                        {{ currentShift ? 'Caixa Aberto' : 'Caixa Fechado' }}
                    </h2>
                    <p v-if="currentShift" class="text-sm font-bold mt-1 opacity-80">Aberto em: {{ formatDateTime(currentShift.opened_at) }}</p>
                </div>

                <div class="flex flex-col gap-3 mt-4">
                    <template v-if="!currentShift">
                        <Button label="Realizar Abertura" icon="pi pi-play" size="large" @click="openShiftModal('open')" class="w-full h-16 text-lg font-black" />
                    </template>
                    <template v-else>
                        <Button label="Sangria (Retirada)" icon="pi pi-minus-circle" severity="warning" variant="outlined" @click="openTransactionModal('out')" class="w-full h-14 font-bold" />
                        <Button label="Suprimento (Reforço)" icon="pi pi-plus-circle" severity="info" variant="outlined" @click="openTransactionModal('in')" class="w-full h-14 font-bold" />
                        <div class="border-t border-gray-200 my-2"></div>
                        <Button label="Fechar o Caixa" icon="pi pi-stop" severity="danger" @click="openShiftModal('close')" class="w-full h-16 text-lg font-black" />
                    </template>
                </div>
            </div>
        </div>

        <div class="flex-1 flex flex-col h-full bg-gray-100 dark:bg-gray-950 p-8 overflow-hidden">
            <div class="flex justify-between items-end mb-6">
                <div>
                    <h2 class="text-3xl font-black text-gray-800 dark:text-white">Extrato do Turno</h2>
                    <div class="flex bg-gray-200 dark:bg-gray-800 p-1 rounded-lg w-max mt-3">
                        <button @click="activeTab = 'orders'" :class="activeTab === 'orders' ? 'bg-white dark:bg-gray-600 shadow text-blue-600 dark:text-blue-400' : 'text-gray-500'" class="px-5 py-2 rounded-md font-black text-sm transition-all flex items-center gap-2">
                            <i class="pi pi-receipt"></i> Pedidos Pagos
                        </button>
                        <button @click="activeTab = 'transactions'" :class="activeTab === 'transactions' ? 'bg-white dark:bg-gray-600 shadow text-purple-600 dark:text-purple-400' : 'text-gray-500'" class="px-5 py-2 rounded-md font-black text-sm transition-all flex items-center gap-2">
                            <i class="pi pi-sort-alt"></i> Movimentações
                        </button>
                    </div>
                </div>
                <div class="bg-white px-4 py-2 rounded-xl shadow-sm border font-black text-lg text-gray-700">
                    Total Faturado: {{ formatCurrency(totalShiftRevenue) }}
                </div>
            </div>

            <div v-if="activeTab === 'orders'" class="flex-1 flex flex-col min-h-0 animate-fade-in">
                <div class="flex items-center gap-4 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                    <div class="w-16">ID</div>
                    <div class="flex-[1.5]">Cliente / Identificador</div>
                    <div class="w-20">Hora</div>
                    <div class="w-32">Operador</div>
                    <div class="flex-1">Pagamento</div>
                    <div class="w-28 text-right">Total</div>
                    <div class="w-10"></div>
                </div>

                <div class="flex-1 overflow-y-auto space-y-2 pr-2 hide-scrollbar">
                    <OrderListItem
                        v-for="order in ordersHistory"
                        :key="order.id"
                        :order="order"
                        @action="handleOrderAction"
                    />

                    <div v-if="ordersHistory.length === 0" class="flex flex-col items-center justify-center h-40 text-gray-400 mt-10">
                        <i class="pi pi-receipt text-5xl mb-3 opacity-50"></i>
                        <p class="font-bold">Nenhum pedido neste turno.</p>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'transactions'" class="flex-1 flex flex-col min-h-0 animate-fade-in">
                <div class="flex items-center gap-4 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                    <div class="w-12 text-center">Tipo</div>
                    <div class="w-40">Operação</div>
                    <div class="flex-[2]">Motivo / Descrição</div>
                    <div class="w-32">Responsável</div>
                    <div class="w-20">Hora</div>
                    <div class="w-32 text-right">Valor</div>
                    <div class="w-10"></div>
                </div>

                <div class="flex-1 overflow-y-auto space-y-2 pr-2 hide-scrollbar">
                    <TransactionListItem
                        v-for="t in transactionsHistory"
                        :key="'t_'+t.id"
                        :transaction="t"
                    />

                    <div v-if="transactionsHistory.length === 0" class="flex flex-col items-center justify-center h-40 text-gray-400 mt-10">
                        <i class="pi pi-sort-alt text-5xl mb-3 opacity-50"></i>
                        <p class="font-bold">Nenhuma movimentação avulsa.</p>
                    </div>
                </div>
            </div>
        </div>

        <ShiftManagementModal
            v-model:visible="isShiftModalVisible"
            :action-type="shiftActionType"
            :company-settings="companySettings"
            @confirm="processShiftAction"
        />

        <TransactionModal
            v-model:visible="isTransactionModalVisible"
            :type="transactionType"
            @confirm="processTransaction"
        />

        <ManagerAuthModal
            v-model:visible="isAuthModalVisible"
            :item-name="'Pedido #' + (activeOrder?.id || '')"
            @confirm="processRefundAndClone"
        />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../Layouts/PosLayout.vue';
import Button from 'primevue/button';

import ShiftManagementModal from './Components/Cashier/ShiftManagementModal.vue';
import TransactionModal from './Components/Cashier/TransactionModal.vue';
import ManagerAuthModal from '@/Components/ManagerAuthModal.vue';

// Componentes da Listagem
import OrderListItem from './Components/Cashier/OrderListItem.vue';
import TransactionListItem from './Components/Cashier/TransactionListItem.vue';

import { formatCurrency } from '@/Utils/formatters';

defineOptions({ layout: AppLayout });

const props = defineProps({
    companySettings: Object,
    currentShift: Object,
    ordersHistory: Array,
    transactionsHistory: Array
});

const activeTab = ref('orders');

// Estado - Modal de Turno
const isShiftModalVisible = ref(false);
const shiftActionType = ref('open');

// Estado - Modal de Transação (Sangria/Suprimento)
const isTransactionModalVisible = ref(false);
const transactionType = ref('in');

// Estado - Modal de Autorização (Estorno)
const isAuthModalVisible = ref(false);
const activeOrder = ref(null);

const totalShiftRevenue = computed(() => {
    return props.ordersHistory
        .filter(o => o.status !== 'cancelled')
        .reduce((sum, order) => sum + order.total, 0);
});

const formatDateTime = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
};

const goHome = () => router.visit('/operations');

// ==========================================
// ABERTURA E FECHAMENTO
// ==========================================
const openShiftModal = (type) => {
    shiftActionType.value = type;
    isShiftModalVisible.value = true;
};

const processShiftAction = (payload) => {
    const endpoint = shiftActionType.value === 'open' ? '/operations/cashier/open' : '/operations/cashier/close';
    router.post(endpoint, payload, {
        preserveState: false,
        onSuccess: () => {
            isShiftModalVisible.value = false;
        }
    });
};

// ==========================================
// SANGRIA E SUPRIMENTO
// ==========================================
const openTransactionModal = (type) => {
    transactionType.value = type;
    isTransactionModalVisible.value = true;
};

const processTransaction = (payload) => {
    router.post('/operations/cashier/transaction', payload, {
        preserveState: false,
        onSuccess: () => {
            isTransactionModalVisible.value = false;
            activeTab.value = 'transactions';
        }
    });
};

// ==========================================
// AÇÕES EMITIDAS PELO ORDER LIST ITEM
// ==========================================
const handleOrderAction = (payload) => {
    activeOrder.value = payload.order;

    if (payload.type === 'refund') {
        isAuthModalVisible.value = true;
    } else if (payload.type === 'print-nfce') {
        alert(`A enviar a NFC-e do pedido #${payload.order.id} para a impressora...`);
    } else if (payload.type === 'print-conference') {
        alert(`A enviar a Conferência do pedido #${payload.order.id} para a impressora...`);
    }
};

const processRefundAndClone = (authData) => {
    router.post(`/operations/orders/${activeOrder.value.id}/refund-and-clone`, {
        pin: authData.password,
        reason: authData.reason,
        is_loss: authData.isLoss
    }, {
        preserveState: false,
        onSuccess: () => {
            isAuthModalVisible.value = false;
        },
        onError: (e) => alert(e.pin ? `Erro de Segurança: ${e.pin}` : 'Ocorreu um erro interno ao estornar.')
    });
};
</script>

<style scoped>
.hide-scrollbar::-webkit-scrollbar { display: none; }
.animate-fade-in { animation: fadeIn 0.3s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>
