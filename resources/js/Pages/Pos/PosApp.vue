<template>
    <div class="flex flex-col h-full w-full relative bg-surface-1 text-surface-text font-sans">

        <!-- OVERLAY DE BLOQUEIO INTERNO -->
        <div v-if="isTerminalLocked" class="fixed inset-0 z-[9999] bg-surface-900/95 backdrop-blur-xl flex flex-col items-center justify-center text-white animate-in fade-in duration-300">
            <div class="w-24 h-24 bg-surface-800 rounded-full flex items-center justify-center mb-6 shadow-inner border border-surface-700">
                <i class="pi pi-lock text-5xl text-primary-500"></i>
            </div>
            <h2 class="text-3xl font-black mb-2">Terminal Bloqueado</h2>
            <p class="text-surface-400 font-medium mb-8 text-center max-w-sm">
                Sessão pausada por segurança. Solicite a liberação do gestor pelo aplicativo para retornar.
            </p>

            <div class="flex items-center gap-3 text-warning-400 bg-warning-500/10 border border-warning-500/20 px-6 py-3 rounded-full font-bold text-sm tracking-widest uppercase">
                <i class="pi pi-spinner pi-spin"></i>
                Radar Ativo
            </div>

            <button @click="handleLogout" class="mt-12 text-xs font-bold text-surface-500 hover:text-white uppercase tracking-widest underline outline-none transition-colors cursor-pointer border-none bg-transparent">
                Trocar de Usuário (Sair)
            </button>
        </div>

        <div class="px-2 pt-2 shrink-0 z-50 transition-all duration-300">
            <header class="flex items-center justify-between gap-2 h-14 w-full">
                <div class="h-full flex items-center shrink-0">
                    <DesktopNav v-model="activeTab" @expanded-change="isNavExpanded = $event" />
                </div>

                <div class="flex-1 flex items-center justify-end gap-2 h-full min-w-0">
                    <div id="dynamic-island-portal"
                         class="empty:hidden h-full bg-surface-2 rounded-full flex items-center gap-2 p-1.5 transition-all duration-300 justify-start overflow-hidden hide-scrollbar"
                         :class="isNavExpanded ? 'max-w-0 opacity-0 px-0 border-none' : 'max-w-full'">
                    </div>

                    <div class="h-full bg-surface-2 rounded-full flex items-center p-1.5 gap-2 shrink-0">
                        <div class="flex flex-col text-right hidden xl:flex">
                            <span class="text-sm font-medium leading-none text-surface-text">
                                {{ abbreviateName($page.props.auth?.user?.name) || 'Operador' }}
                            </span>
                            <span v-if="$page.props.cashierData?.currentShift" class="text-[10px] text-success-smart font-black uppercase mt-1 flex items-center justify-end gap-1">
                                <i class="pi pi-circle-fill text-[8px] animate-pulse"></i> Aberto
                            </span>
                            <span v-else class="text-[10px] text-danger-smart font-black uppercase mt-1 flex items-center justify-end gap-1">
                                <i class="pi pi-circle-fill text-[8px] animate-pulse"></i> Fechado
                            </span>
                        </div>

                        <div class="flex items-center gap-2 pl-2 md:pl-0 md:border-none">
                            <button @click="toggleTheme" class="w-10 h-10 rounded-full bg-surface-1 flex items-center justify-center text-surface-muted hover:text-primary-smart hover:bg-primary-500/10 transition-colors cursor-pointer outline-none shrink-0 border-none" title="Alternar Tema">
                                <i class="pi font-medium" :class="themeIcon"></i>
                            </button>

                            <button class="w-10 h-10 rounded-full bg-surface-1 flex items-center justify-center text-surface-muted hover:text-primary-smart hover:bg-primary-500/10 transition-colors cursor-pointer outline-none shrink-0 border-none" title="Perfil">
                                <i class="pi pi-user font-medium"></i>
                            </button>

                            <button @click="lockTerminal" class="w-10 h-10 rounded-full bg-surface-1 flex items-center justify-center text-warning-500 hover:text-white hover:bg-warning-500/80 transition-colors cursor-pointer outline-none shrink-0 border-none" title="Bloquear Terminal">
                                <i class="pi pi-lock font-medium"></i>
                            </button>

                            <button @click="handleLogout" class="w-10 h-10 rounded-full bg-surface-1 flex items-center justify-center text-danger-500 hover:text-white hover:bg-danger-500/80 transition-colors cursor-pointer outline-none shrink-0 border-none" title="Encerrar Sessão">
                                <i class="pi pi-sign-out font-medium"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </header>
        </div>

        <main class="flex-1 min-h-0 overflow-hidden relative p-0">
            <div v-show="activeTab === 'new' || activeTab === 'pos'" class="h-full w-full animate-in fade-in duration-300">
                <OrderEntry
                    v-bind="$page.props"
                    :is-active="activeTab === 'new' || activeTab === 'pos'"
                    @open-checkout="goToCheckout"
                    @order-created="goToOperations"
                />
            </div>

            <div v-show="activeTab === 'orders'" class="h-full w-full animate-in fade-in duration-300">
                <ControlPanel v-bind="$page.props" :table-areas="$page.props.initialTableAreas" :is-active="activeTab === 'orders'" @open-checkout="goToCheckout" />
            </div>

            <div v-show="activeTab === 'customers'" class="h-full w-full animate-in fade-in duration-300">
                <CRM v-bind="$page.props" :is-active="activeTab === 'customers'" />
            </div>

            <div v-show="activeTab === 'shift'" class="h-full w-full animate-in fade-in duration-300">
                <Cashier v-bind="$page.props.cashierData" :company-settings="$page.props.companySettings" :is-active="activeTab === 'shift'" />
            </div>

            <div v-show="activeTab === 'tasks'" class="h-full w-full animate-in fade-in duration-300">
                <ChecklistDashboard v-bind="$page.props" :is-active="activeTab === 'tasks'" />
            </div>

            <div v-show="activeTab === 'availability'" class="h-full w-full animate-in fade-in duration-300">
                <Inventory :catalog="$page.props.catalog" :is-active="activeTab === 'availability'" />
            </div>

            <div v-show="activeTab === 'production'" class="h-full w-full animate-in fade-in duration-300">
                <ProductionBoard :orders="$page.props.productionOrders" :is-active="activeTab === 'production'" />
            </div>

            <div v-show="activeTab === 'purchases'" class="h-full w-full animate-in fade-in duration-300">
                <ShoppingList :biddingMatrix="$page.props.biddingMatrix" :is-active="activeTab === 'purchases'" />
            </div>

            <div v-if="defaultTab === 'production'" class="flex-1 min-h-0 relative bg-surface-1/50 rounded-3xl overflow-hidden border border-surface-border">
                <div class="h-14 px-6 bg-surface-1 border-b border-surface-border flex items-center gap-4 shrink-0">
                    <span class="font-black text-sm uppercase tracking-widest text-surface-muted"><i class="pi pi-eye mr-2"></i> Visão da Cozinha</span>
                    <select v-model="activeKdsTab" class="h-9 px-3 rounded-lg bg-surface-2 border border-surface-border text-xs font-bold outline-none cursor-pointer">
                        <option value="all">Todas as Praças</option>
                        <option v-for="k in kitchens" :key="k.id" :value="k.id">{{ k.name }}</option>
                    </select>
                </div>
                <div class="flex-1 h-[calc(100%-3.5rem)] relative">
                    <KdsBoard :readonly="true" :kitchens="kitchens" :active-kitchen-id="activeKdsTab" :initial-items="kdsItems || []" />
                </div>
            </div>

            <div v-show="activeTab === 'printers'" class="h-full w-full animate-in fade-in duration-300">
                <Printers v-bind="$page.props.printerData" :is-active="activeTab === 'printers'" />
            </div>

            <div v-if="activeTab === 'checkout'" class="h-full w-full animate-in slide-in-from-right-4 duration-300">
                <Checkout
                    v-if="checkoutData"
                    :order="checkoutData.order"
                    :payment-methods="checkoutData.paymentMethods"
                    :company-settings="checkoutData.companySettings"
                    @close="closeCheckout"
                />
                <div v-else class="flex-1 flex flex-col items-center justify-center h-full text-surface-muted gap-4">
                    <i class="pi pi-spinner pi-spin text-4xl text-primary-500"></i>
                    <span class="font-black uppercase tracking-widest text-xs">Preparando Conta...</span>
                </div>
            </div>

            <!-- 🚀 ABA NOVA: GESTÃO DE ESCALAS 5x2 -->
            <div v-if="activeTab === 'schedules'" class="h-full w-full animate-in slide-in-from-bottom-4 duration-300">
                <ScheduleBoard
                    :start-date="$page.props.scheduleStartDate || $page.props.startDate"
                    :employees="$page.props.employees"
                    :slots="$page.props.slots"
                    :initial-schedules="$page.props.initialSchedules"
                />
            </div>
        </main>

        <!-- 🔔 TOASTS DE ALERTA DE IMPRESSÃO (CANTO INFERIOR DIREITO) -->
        <div class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none">
            <PrintAlertNotice
                v-for="(alert, idx) in printAlerts"
                :key="alert.id || idx"
                :alert="alert"
                mode="toast"
                @dismiss="removePrintAlert(idx)"
            />
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { ref, computed, onMounted, onBeforeUnmount, defineAsyncComponent } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

import DesktopLayout from '@/Layouts/DesktopLayout.vue';
import DesktopNav from "@/Components/UI/DesktopNav.vue";
import { useTheme } from '@/Composables/useTheme';
import { abbreviateName } from '@/Utils/formatters';

import Checkout from '@/Pages/Pos/Checkout.vue';
import OrderEntry from '@/Pages/Pos/OrderEntry.vue';
import ControlPanel from '@/Pages/Operations/ControlPanel.vue';
import Cashier from '@/Pages/Pos/Cashier.vue';
import CRM from '@/Pages/CRM/CRM.vue';
import ChecklistDashboard from '@/Pages/Operations/Checklists/ChecklistDashboard.vue';
import Inventory from '@/Pages/Operations/Inventory.vue';
import ProductionBoard from '@/Pages/Shared/SupplyChain/ProductionBoard.vue';
import ShoppingList from '@/Pages/Shared/SupplyChain/ShoppingList.vue';
import Printers from '@/Pages/Operations/Printers/Printers.vue';
import KdsBoard from '@/Pages/Operations/KdsBoard.vue';
import PrintAlertNotice from '@/Components/UI/PrintAlertNotice.vue';

// 🚀 IMPORT PREGUIÇOSO DO NOVO MÓDULO PARA NÃO PESAR O PDV (Lazy Loading)
const ScheduleBoard = defineAsyncComponent(() => import('@/Pages/Operations/Schedules/ScheduleBoard.vue'));

import { usePosTerminalLock } from '@/Pages/Pos/Composables/usePosTerminalLock';
import { usePosSync } from '@/Pages/Pos/Composables/usePosSync';
import { useTenantEcho } from '@/Composables/useTenantEcho';
import { usePosIdleTimer } from '@/Pages/Pos/Composables/usePosIdleTimer';

defineOptions({ layout: DesktopLayout });

const props = defineProps({
    defaultTab: { type: String, default: 'new' },
    kitchens: Array,
    kdsItems: Array,
});

const page = usePage();
const toast = useToast();

const resolveInitialTab = () => {
    if (typeof window !== 'undefined') {
        const parts = window.location.pathname.split('/').filter(Boolean);
        if (parts.length > 1 && parts[0] === 'pos') {
            const urlTab = parts[1];
            const validTabs = ['new', 'pos', 'orders', 'customers', 'shift', 'tasks', 'availability', 'production', 'purchases', 'printers', 'checkout', 'schedules'];
            if (validTabs.includes(urlTab)) return urlTab === 'pos' ? 'new' : urlTab;
        }
    }
    const initial = props.defaultTab || page.props.activeTab || page.props.tab || 'new';
    return initial === 'pos' ? 'new' : initial;
};

const activeTab = ref(resolveInitialTab());
const isNavExpanded = ref(false);
const activeKdsTab = ref('all');
const checkoutData = ref(null);
const printAlerts = ref([]);

const removePrintAlert = (index) => {
    printAlerts.value.splice(index, 1);
};

const { currentMode, setMode } = useTheme();
const { isTerminalLocked, lockTerminal } = usePosTerminalLock();

const toggleTheme = () => {
    if (currentMode.value === 'light') setMode('dark');
    else if (currentMode.value === 'dark') setMode('system');
    else setMode('light');
};

const themeIcon = computed(() => {
    if (currentMode.value === 'light') return 'pi-sun';
    if (currentMode.value === 'dark') return 'pi-moon';
    return 'pi-desktop';
});

const purgeShiftData = (notify = false) => {
    localStorage.removeItem('pos_parked_orders');
    localStorage.removeItem('pos_draft_cart');
    localStorage.removeItem('pos_draft_routing');
    checkoutData.value = null;

    if (notify) {
        toast.add({
            severity: 'info',
            summary: 'Turno Atualizado',
            detail: 'Rascunhos e pedidos estacionados do turno anterior foram limpos.',
            life: 5000
        });
    }
};

const checkShiftIntegrity = () => {
    const currentShift = page.props.cashierData?.currentShift;
    const storedShiftId = localStorage.getItem('pos_active_shift_id');

    if (!currentShift) {
        if (storedShiftId || localStorage.getItem('pos_parked_orders')) {
            purgeShiftData(false);
            localStorage.removeItem('pos_active_shift_id');
        }
    } else {
        if (storedShiftId && String(storedShiftId) !== String(currentShift.id)) {
            purgeShiftData(true);
        }
        localStorage.setItem('pos_active_shift_id', String(currentShift.id));
    }
};

const goToCheckout = async (target) => {
    const orderId = (typeof target === 'object' && target !== null) ? (target.id || target.orderId) : target;
    if (!orderId) return;

    activeTab.value = 'checkout';
    checkoutData.value = null;
    window.history.pushState({ tab: 'checkout' }, '', `/pos/orders?checkout=${orderId}`);

    try {
        const response = await axios.get(route('operations.checkout', orderId), {
            headers: {
                'Accept': 'application/json'
            }
        });

        const payload = response.data?.props || response.data;
        checkoutData.value = {
            order: payload.order,
            paymentMethods: payload.paymentMethods || payload.payment_methods || [],
            companySettings: payload.companySettings || payload.company_settings || page.props.companySettings
        };
    } catch (e) {
        console.error('Erro ao abrir checkout:', e);
        activeTab.value = 'orders';
        window.history.pushState({ tab: 'orders' }, '', `/pos/orders`);
        toast.add({
            severity: 'error',
            summary: 'Erro',
            detail: 'Não foi possível carregar a conta deste pedido.',
            life: 4000
        });
    }
};

const goToOperations = () => {
    activeTab.value = 'orders';
    window.history.pushState({ tab: 'orders' }, '', `/pos/orders`);
    router.reload({
        only: ['initialOrders', 'initialTableAreas', 'cashierData', 'printerData'],
        preserveState: true,
        preserveScroll: true
    });
};

const handleTabChange = (e) => {
    let targetTab = e.detail;
    if (targetTab === 'operations') targetTab = 'orders';
    if (targetTab === 'pos') targetTab = 'new';
    if (targetTab.includes('schedules')) targetTab = 'schedules';

    if (activeTab.value === 'checkout' && targetTab !== 'checkout') {
        checkoutData.value = null;
    }

    activeTab.value = targetTab;

    // 🚀 A rota agora é sempre mantida sob a SPA do /pos
    const newUrl = targetTab === 'new' ? '/pos/new' : `/pos/${targetTab}`;

    if (window.location.pathname !== newUrl && !window.location.pathname.includes(newUrl)) {
        window.history.pushState({ tab: targetTab }, '', newUrl);
    }
};

usePosIdleTimer({
    timeout: 60000,
    isEnabled: () => {
        const isFinishedCheckout = activeTab.value === 'checkout' &&
            (checkoutData.value?.order?.payment_status === 'paid' ||
             checkoutData.value?.order?.status === 'closed' ||
             checkoutData.value?.order?.status === 'completed');

        const isShiftView = activeTab.value === 'shift';

        return isFinishedCheckout || isShiftView;
    },
    onIdle: () => {
        if (activeTab.value === 'checkout') {
            closeCheckout();
            activeTab.value = 'new';
        }
        else if (activeTab.value === 'shift') {
            activeTab.value = 'new';
            const newUrl = '/pos/new';
            if (window.location.pathname !== newUrl) {
                window.history.pushState({ tab: 'new' }, '', newUrl);
            }
        }
    }
});

const closeCheckout = () => {
    checkoutData.value = null;
    activeTab.value = 'orders';

    const url = new URL(window.location.href);
    url.searchParams.delete('order');
    url.searchParams.delete('checkout');
    window.history.replaceState({ tab: 'orders' }, '', '/pos/orders');

    router.reload({
        only: ['initialOrders', 'initialTableAreas', 'cashierData'],
        preserveState: true,
        preserveScroll: true
    });
};

const handlePopState = (event) => {
    if (event.state && event.state.tab) {
        activeTab.value = event.state.tab === 'pos' ? 'new' : event.state.tab;
    } else {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const tab = parts.length > 1 ? parts[1] : 'new';
        activeTab.value = (tab === 'new' || tab === 'pos') ? 'new' : tab;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order') || urlParams.get('checkout');
    if (activeTab.value === 'checkout' && orderId) {
        goToCheckout(orderId);
    }
};

const handleOpenCheckoutEvent = (e) => {
    const orderId = e.detail?.orderId || e.detail;
    goToCheckout(orderId);
};

const { initPosSync } = usePosSync(activeTab, checkoutData);
initPosSync();

useTenantEcho().listenChannel('operations', '.print.alert', (data) => {
    printAlerts.value.push(data);
});

useTenantEcho().listenChannel('shift', '.shift.closed', () => {
    purgeShiftData(false);
    localStorage.removeItem('pos_active_shift_id');
});

onMounted(() => {
    checkShiftIntegrity();

    if (window.location.pathname === '/pos' || window.location.pathname === '/pos/') {
        window.history.replaceState({ tab: 'new' }, '', '/pos/new');
    }

    window.addEventListener('pos-tab-changed', handleTabChange);
    window.addEventListener('popstate', handlePopState);
    window.addEventListener('pos-open-checkout', handleOpenCheckoutEvent);

    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order') || urlParams.get('checkout');
    if (orderId && (activeTab.value === 'checkout' || window.location.pathname.includes('/checkout'))) {
        goToCheckout(orderId);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('pos-tab-changed', handleTabChange);
    window.removeEventListener('popstate', handlePopState);
    window.removeEventListener('pos-open-checkout', handleOpenCheckoutEvent);
});

const handleLogout = () => {
    router.post(route('logout'), {}, {
        onFinish: () => { window.location.href = '/pos/login'; }
    });
};
</script>
