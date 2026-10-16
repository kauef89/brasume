cat << 'EOF' > resources/js/Pages/Pos/Cashier.vue
<template>
    <div class="grid h-full min-h-0 w-full bg-surface-1 text-surface-text overflow-hidden font-sans p-2 transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] grid-cols-1 lg:grid-cols-[1fr_360px] xl:grid-cols-[1fr_400px] gap-2">

        <!-- 1. DYNAMIC ISLAND (CONTROLE REMOTO DO TURNO) -->
        <Teleport v-if="isTeleportActive" to="#dynamic-island-portal">
            <div class="flex items-center h-10 md:h-12 shrink-0 mr-1 gap-2 md:gap-3">

                <!-- FILTROS (Liga/desliga quando a tabela está visível) -->
                <PosTeleportButton
                    v-if="leftView === 'table'"
                    icon="pi-filter"
                    color="primary"
                    label="Filtros da Tabela"
                    :active="showOrders || showTransactions"
                    @click="toggleFilterMenu"
                />

                <div class="w-px h-6 bg-surface-border/50 mx-1"></div>

                <!-- ABRIR CAIXA (Cor: Success) -->
                <PosTeleportButton
                    v-if="!currentShift"
                    icon="pi-lock-open"
                    color="success"
                    label="Abrir Caixa"
                    @click="openShiftView('open')"
                />

                <!-- BOTÕES DE AÇÃO DO TURNO -->
                <template v-else>
                    <!-- SUPRIMENTO (ENTRADA) -->
                    <PosTeleportButton
                        icon="pi-arrow-down"
                        color="primary"
                        label="Suprimento (Entrada)"
                        @click="openTransactionView('in')"
                    />

                    <!-- SANGRIA (RETIRADA) -->
                    <PosTeleportButton
                        icon="pi-arrow-up"
                        color="primary"
                        label="Sangria (Retirada)"
                        @click="openTransactionView('out')"
                    />

                    <!-- PONTO / EQUIPE DO TURNO -->
                    <PosTeleportButton
                        icon="pi-users"
                        color="primary"
                        label="Equipe do Turno (Ponto)"
                        @click="openMidShiftAttendance"
                    />

                    <!-- REEMISSÃO FISCAL EM LOTE -->
                    <PosTeleportButton
                        v-if="companySettings?.is_fiscal_emitter"
                        icon="pi-bolt"
                        color="primary"
                        :loading="isRetryingFiscal"
                        :disabled="isRetryingFiscal || pendingFiscalOrders.length === 0"
                        :badge="pendingFiscalOrders.length > 0 && !isRetryingFiscal ? pendingFiscalOrders.length : null"
                        badge-severity="danger"
                        :label="pendingFiscalOrders.length > 0 ? `Emitir ${pendingFiscalOrders.length} NFC-e pendente(s)` : 'Todas as NFC-e foram autorizadas'"
                        @click="retryFailedFiscal"
                    />

                    <!-- ENCERRAR TURNO OPERACIONAL -->
                    <PosTeleportButton
                        icon="pi-history"
                        color="primary"
                        label="Encerrar Turno Operacional"
                        @click="openOperationalView"
                    />

                    <!-- FECHAR CAIXA FINANCEIRO (Cor: Success) -->
                    <PosTeleportButton
                        icon="pi-lock"
                        color="success"
                        label="Fechar Caixa (Financeiro)"
                        @click="openShiftView('close')"
                    />
                </template>
            </div>
        </Teleport>

        <!-- 2. ÁREA PRINCIPAL (ESQUERDA) -->
        <div class="flex flex-col h-full min-h-0 min-w-0 bg-surface-2 rounded-4xl overflow-hidden relative">
            <Transition name="fade" mode="out-in">
                <CashierTable
                    v-if="leftView === 'table'"
                    class="w-full h-full"
                    :unified-history="unifiedHistory"
                    :selected-rows="selectedRows"
                    @toggle-selection="toggleRowSelection"
                    @toggle-all="toggleAllRows"
                    @action="handleOrderAction"
                />

                <!-- CONTAGEM DE CAIXA: ABERTURA / FECHAMENTO -->
                <ShiftCashForm
                    v-else-if="leftView === 'open_shift' || leftView === 'close_shift'"
                    ref="shiftCashFormRef"
                    :mode="leftView === 'open_shift' ? 'open' : 'close'"
                    class="w-full h-full"
                    :company-settings="companySettings"
                    :expected-total="expectedInDrawer"
                    @update:data="shiftFormData = $event"
                    @submit="triggerSidebarSubmit"
                />

                <!-- ESCALA E PONTO: GERENCIA EQUIPE E LIQUIDAÇÃO DE FREELANCERS -->
                <ShiftAttendance
                    v-else-if="leftView === 'attendance'"
                    class="w-full h-full"
                    :users="users"
                    :current-attendees="currentAttendees"
                    :flow-mode="currentAttendanceFlowMode"
                    @cancel="handleAttendanceCancel"
                    @confirm="handleAttendanceConfirm"
                    @pay-freelancer="openFreelancerPayment"
                />
            </Transition>
        </div>

        <!-- 3. ÁREA SECUNDÁRIA (DIREITA) -->
        <div class="flex flex-col h-full min-h-0 bg-surface-2 rounded-4xl overflow-hidden relative shadow-sm shrink-0">
            <Transition name="fade" mode="out-in">

                <!-- SIDEBAR DE CONFIRMAÇÃO DO TURNO (TOTAL, OBSERVAÇÃO, AÇÃO) -->
                <ShiftCashSidebar
                    v-if="rightView === 'shift_action'"
                    ref="shiftSidebarRef"
                    class="w-full h-full"
                    :mode="leftView === 'open_shift' ? 'open' : 'close'"
                    :company-settings="companySettings"
                    :amount="shiftFormData.amount"
                    :coins-total="shiftFormData.coinsTotal"
                    :is-valid="shiftFormData.isValid"
                    :has-active-shift="Boolean(currentShift)"
                    :has-staged-attendees="stagedAttendees.length > 0"
                    @cancel="resetViews"
                    @back-to-attendance="handleBackToAttendanceFromOpen"
                    @confirm="handleShiftSidebarConfirm"
                />

                <!-- EMPTY STATE QUANDO CAIXA ESTÁ FECHADO -->
                <div v-else-if="rightView === 'empty'" class="flex-1 flex flex-col items-center justify-center text-center p-8 opacity-70">
                    <div class="w-16 h-16 rounded-full bg-primary-500/10 text-primary-500 flex items-center justify-center mb-4">
                        <i class="pi pi-lock-open text-2xl font-black"></i>
                    </div>
                    <h3 class="text-xl font-black mb-1 uppercase tracking-wider text-surface-text">Abertura de Turno</h3>
                    <p class="text-xs text-surface-muted max-w-xs leading-relaxed">
                        Passo 1: Confirme a equipe presente para liberar o acesso ao sistema. Em seguida, informe o fundo de troco da gaveta.
                    </p>
                </div>

                <!-- PAINEL DE ACERTO DE FREELANCER -->
                <FreelancerPayment
                    v-else-if="rightView === 'freelancer_payment'"
                    class="w-full h-full"
                    :user="freelancerToPay"
                    :payout-details="freelancerPayoutDetails"
                    :bank-accounts="bankAccounts"
                    @cancel="handleFreelancerCancel"
                    @confirm="handleFreelancerConfirm"
                />

                <ShiftDashboard
                    v-else-if="rightView === 'dashboard'"
                    class="w-full h-full"
                    :current-shift="currentShift"
                    :manager-data="managerData"
                    :dynamic-total="dynamicTotal"
                    :expected-in-drawer="expectedInDrawer"
                    :selected-count="selectedRows.size"
                    :unified-history="unifiedHistory"
                />

                <TransactionForm
                    v-else-if="rightView === 'transaction'"
                    class="w-full h-full"
                    :type="transactionType"
                    @cancel="resetViews"
                    @confirm="handleTransactionConfirm"
                />

                <!-- PAINEL DE FECHAMENTO OPERACIONAL -->
                <CloseOperationalShift
                    v-else-if="rightView === 'close_operational'"
                    class="w-full h-full"
                    :is-processing="isOperationalProcessing"
                    @cancel="resetViews"
                    @confirm="handleConfirmOperationalClose"
                />
            </Transition>
        </div>

        <CashierFilterPanel
            ref="filterPanelRef"
            v-model:show-orders="showOrders"
            v-model:show-transactions="showTransactions"
            :active-types="activeOrderTypes"
            @toggle-type="toggleOrderTypeFilter"
        />

        <ShiftAuthModal
            v-model:visible="isAuthModalVisible"
            :action-type="authActionType"
            :item-name="authItemName"
            @confirm="handleAuthConfirm"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, onActivated, onDeactivated, watch } from 'vue';
import DesktopLayout from '@/Layouts/DesktopLayout.vue';

import CashierFilterPanel from '@/Pages/Pos/Components/Cashier/CashierFilterPanel.vue';
import CashierTable from '@/Pages/Pos/Components/Cashier/CashierTable.vue';
import ShiftCashForm from '@/Pages/Pos/Components/Cashier/ShiftCashForm.vue';
import ShiftCashSidebar from '@/Pages/Pos/Components/Cashier/ShiftCashSidebar.vue';
import ShiftAttendance from '@/Pages/Pos/Components/Cashier/ShiftAttendance.vue';
import ShiftDashboard from '@/Pages/Pos/Components/Cashier/ShiftDashboard.vue';
import TransactionForm from '@/Pages/Pos/Components/Cashier/TransactionForm.vue';
import ShiftAuthModal from '@/Pages/Pos/Components/Cashier/ShiftAuthModal.vue';
import FreelancerPayment from '@/Pages/Pos/Components/Cashier/FreelancerPayment.vue';
import CloseOperationalShift from '@/Pages/Pos/Components/Cashier/CloseOperationalShift.vue';
import PosTeleportButton from '@/Components/UI/PosTeleportButton.vue';

import { useCashier } from '@/Pages/Pos/Composables/useCashier';
import { useFiscalBatchEmitter } from '@/Pages/Pos/Composables/useFiscalBatchEmitter';
import { useShiftAttendance } from '@/Pages/Shared/Composables/useShiftAttendance';
import { useOperationalShift } from '@/Pages/Shared/Composables/useOperationalShift';

defineOptions({ layout: DesktopLayout });

const props = defineProps({
    currentShift: Object,
    ordersHistory: Array,
    transactionsHistory: Array,
    managerData: Object,
    bankAccounts: Array,
    users: Array,
    currentAttendees: Array,
    companySettings: Object,
    isActive: {
        type: Boolean,
        default: false
    }
});

const isTeleportActive = ref(false);

onMounted(() => { isTeleportActive.value = props.isActive; });
onActivated(() => { isTeleportActive.value = true; });
onDeactivated(() => { isTeleportActive.value = false; });
onBeforeUnmount(() => { isTeleportActive.value = false; });
watch(() => props.isActive, (newVal) => { isTeleportActive.value = newVal; });

const leftView = ref('table');
const rightView = ref('dashboard');
const isStagedAttendance = ref(false);
const stagedAttendees = ref([]);

const shiftFormData = ref({
    amount: 0,
    retainedAmount: 0,
    coinsTotal: 0,
    details: null,
    isValid: true
});

const shiftSidebarRef = ref(null);
const shiftCashFormRef = ref(null);

// 🚀 1. INICIALIZAÇÃO DE COMPOSABLES PRIMÁRIOS (Antes dos Watchers)
const {
    showOrders, showTransactions, activeOrderTypes, selectedRows, unifiedHistory,
    dynamicTotal, expectedInDrawer, isAuthModalVisible, authActionType, authItemName,
    shiftActionType, pendingShiftPayload,
    toggleAllRows, toggleRowSelection, toggleOrderTypeFilter, processTransaction,
    handleAuthConfirm, triggerAttendanceSync, processShiftAction,
    finalizeShiftWithAttendance, executeShiftAction
} = useCashier(props, {
    onShiftStaged: (type, payload) => {
        if (type === 'close') {
            isStagedAttendance.value = true;
            leftView.value = 'attendance';
            rightView.value = 'dashboard';
        }
    }
});

const {
    processFreelancerPayment,
    freelancerToPay,
    freelancerPayoutDetails,
    confirmFreelancerPayment
} = useShiftAttendance();

const { isRetryingFiscal, pendingFiscalOrders, retryFailedFiscal } = useFiscalBatchEmitter(props, unifiedHistory);

const {
    isProcessing: isOperationalProcessing,
    submitCloseShift: submitOperationalCloseShift
} = useOperationalShift();

// 🚀 2. COMPUTEDS E MÉTODOS DE ESTADO DE VIEW
const currentAttendanceFlowMode = computed(() => {
    if (!props.currentShift || shiftActionType.value === 'open') {
        return 'open';
    }
    if (isStagedAttendance.value && shiftActionType.value === 'close') {
        return 'close';
    }
    return 'mid_shift';
});

const resetViews = () => {
    isStagedAttendance.value = false;
    stagedAttendees.value = [];

    if (!props.currentShift) {
        shiftActionType.value = 'open';
        leftView.value = 'attendance';
        rightView.value = 'empty';
    } else {
        leftView.value = 'table';
        rightView.value = 'dashboard';
    }
};

// 🚀 3. WATCHER DE TURNO (Agora shiftActionType já existe com segurança)
watch(() => props.currentShift, () => {
    resetViews();
}, { immediate: true });

const filterPanelRef = ref(null);
const toggleFilterMenu = (event) => { filterPanelRef.value?.toggle(event); };

const openFreelancerPayment = (user, rate) => {
    processFreelancerPayment(user, rate);
    rightView.value = 'freelancer_payment';
};

const handleFreelancerConfirm = (payload) => {
    confirmFreelancerPayment(payload);
    rightView.value = 'dashboard';
};

const handleFreelancerCancel = () => {
    rightView.value = 'dashboard';
};

const handleOrderAction = (payload) => {
    if (payload.type === 'manage' && payload.order?.id) {
        window.dispatchEvent(new CustomEvent('pos-open-checkout', {
            detail: {
                orderId: payload.order.id,
                mode: 'payment'
            }
        }));
    }
};

const openShiftView = (type) => {
    shiftActionType.value = type;

    if (type === 'open') {
        stagedAttendees.value = [];
        leftView.value = 'attendance';
        rightView.value = 'empty';
    } else {
        leftView.value = 'close_shift';
        rightView.value = 'shift_action';
    }
};

const triggerSidebarSubmit = () => {
    shiftSidebarRef.value?.triggerConfirmation();
};

const handleBackToAttendanceFromOpen = () => {
    leftView.value = 'attendance';
    rightView.value = 'empty';
};

const handleShiftSidebarConfirm = ({ observation }) => {
    const isOpening = shiftActionType.value === 'open';

    if (isOpening) {
        executeShiftAction({
            amount: shiftFormData.value.amount,
            retained_amount: shiftFormData.value.retainedAmount,
            observation: observation,
            details: shiftFormData.value.details,
            attendees: stagedAttendees.value
        });
    } else {
        processShiftAction({
            amount: shiftFormData.value.amount,
            retained_amount: shiftFormData.value.retainedAmount,
            observation: observation,
            details: shiftFormData.value.details
        }, 'close');
    }
};

const handleAttendanceConfirm = (attendeesPayload) => {
    const teamArray = Array.isArray(attendeesPayload)
        ? attendeesPayload
        : (attendeesPayload?.attendees || []);

    if (shiftActionType.value === 'open' && !props.currentShift) {
        stagedAttendees.value = teamArray;
        leftView.value = 'open_shift';
        rightView.value = 'shift_action';
    } else if (isStagedAttendance.value && shiftActionType.value === 'close') {
        finalizeShiftWithAttendance(teamArray);
    } else {
        triggerAttendanceSync(teamArray, () => {
            leftView.value = 'table';
        });
    }
};

const handleAttendanceCancel = () => {
    resetViews();
};

const handleTransactionConfirm = (payload) => {
    processTransaction(payload);
    resetViews();
};

const openMidShiftAttendance = () => {
    isStagedAttendance.value = false;
    shiftActionType.value = 'mid_shift';
    leftView.value = 'attendance';
};

const transactionType = ref('in');
const openTransactionView = (type) => {
    transactionType.value = type;
    rightView.value = 'transaction';
};

const openOperationalView = () => {
    leftView.value = 'table';
    rightView.value = 'close_operational';
};

const handleConfirmOperationalClose = (closeStoreChoice) => {
    const unpaidCount = (props.currentAttendees || []).filter(a => Number(a.daily_rate) > 0 && !a.account_payable_id).length;
    submitOperationalCloseShift(closeStoreChoice, () => {
        resetViews();
    }, unpaidCount);
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from {
    opacity: 0;
    transform: scale(0.98);
}
.fade-leave-to {
    opacity: 0;
    transform: scale(0.98);
}
</style>
