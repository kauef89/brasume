<template>
    <div class="h-full w-full bg-surface-1 text-surface-text flex flex-col font-sans overflow-hidden select-none relative">

        <!-- 🔒 FATIA 1: BLOQUEIO DE TURNO -->
        <TeamShiftLockOverlay
            v-if="!isOnDuty"
            :is-starting="isStarting"
            @start-shift="startShift"
        />

        <div v-else class="flex-1 flex flex-col h-full overflow-hidden relative bg-surface-2">

            <!-- 👑 FATIA 2: HEADER GLOBAL COM CONTEXTO CONDICIONAL -->
            <TeamHeader
                :active-tab="activeTab"
                :active-category="activeMenuCategory"
                :active-product="isModifiersVisible || isComboVisible ? activeProduct : null"
                :current-total="Number(productTotal)"
                :context-title="headerContext.title"
                :context-subtitle="headerContext.subtitle"
                @open-notifications="() => {}"
            />

            <!-- 🔍 TELEPORT DIRETO DA BUSCA DO CARDÁPIO (Protegido por isMounted) -->
            <Teleport to="#team-header-action-portal" v-if="isMounted && activeTab === 'menu' && !activeProduct">
                <div class="flex items-center">
                    <ExpandableSearch
                        v-model="searchQuery"
                        placeholder="Buscar no Cardápio..."
                        class="!w-36 sm:!w-48 md:!w-64 animate-in fade-in duration-300"
                    />
                </div>
            </Teleport>

            <div
                class="flex-1 flex flex-col min-h-0 relative transition-all duration-200"
            >
                <!-- 📖 FATIA 3: RENDERIZADOR DE ABAS DA OPERAÇÃO -->
                <TeamTabContainer
                    ref="tabContainerRef"
                    v-model:active-tab="activeTab"
                    :category-layout="categoryLayout"
                    :mobile-category-layout="mobileCategoryLayout"
                    :product-card-layout="productCardLayout"
                    :visible-menu-categories="visibleMenuCategories"
                    :active-menu-category="selectedCategory"
                    :visible-grid-categories="visibleGridCategories"
                    :should-show-category-grid="shouldShowCategoryGrid"
                    :should-show-product-grid="shouldShowProductGrid"
                    :filtered-products="filteredProducts"
                    :on-site-orders="onSiteOrders"
                    :initial-table-areas="initialTableAreas"
                    :restock-data="restockData"
                    :catalog="catalog || initialProducts"
                    :cart-component-key="cartComponentKey"
                    :cart="cart"
                    :cart-subtotal="cartSubtotal"
                    v-model:order-type="orderType"
                    :initial-customers="initialCustomers"
                    :edit-order-data="editOrderData"
                    :is-allowance-mode="isAllowanceMode"
                    :cart-routing-meta="cartRoutingMeta"
                    :bar-items="barItems"
                    @category-select="selectedCategory = $event"
                    @product-click="onProductClick"
                    @open-menu="openContextMenu"
                    @create-order="handleCreateOrderOnTable"
                    @start-allowance-order="handleStartAllowanceOrder"
                    @update-qty="updateQty"
                    @clear-cart="handleClearCart"
                    @edit-item="openEditModifiers"
                    @checkout="handleCheckoutWrapper"
                    @routing-valid="handleRoutingValid"
                    @routing-meta="handleRoutingMeta"
                    @kds-take="handleKdsTake"
                    @kds-complete="handleKdsComplete"
                    @kds-print="handleKdsPrint"
                    @kds-loss="handleKdsCancel"
                    @kds-cancel="handleKdsCancel"
                />
            </div>

            <!-- 🏝 DYNAMIC ISLAND DA OPERAÇÃO -->
            <TeamDynamicIsland
                ref="islandRef"
                v-model:active-tab="activeTab"
                :cart-length="cart.length"
                :selected-category="selectedCategory"
                :category-layout="categoryLayout"
                :breadcrumbs="breadcrumbs"
                :order-context="contextMenuVisible ? selectedOrderContext : null"
                :cart-routing-valid="cartRoutingValid"
                :cart-routing-meta="cartRoutingMeta"
                :is-submitting="isSubmittingOrder"
                :is-success="isOrderSuccess"
                :restock-count="restockData.totalItemsToRestock.value"
                :is-allowance-mode="isAllowanceMode"
                :bar-items-count="pendingBarItemsCount"
                :configurator-type="isComboVisible ? 'combo' : (isModifiersVisible ? 'modifier' : null)"
                @open-more="showMoreMenu = true"
                @go-back-category="goBackCategory"
                @navigate="activeTab = 'operations'"
                @reset-menu="selectedCategory = null; searchQuery = ''"
                @close-context="contextMenuVisible = false"
                @show-more-actions="showOrderActionsSheet = true"
                @toggle-routing="tabContainerRef?.toggleCartRouting()"
                @submit-order="triggerCheckout"
                @clear-cart="handleClearCart"
            />

            <!-- 🎛️ MODAL DE MÓDULOS -->
            <TeamModulesModal
                v-model:visible="showMoreMenu"
                :manager-data="$page.props.managerData"
                :restock-count="restockData.totalItemsToRestock.value"
                @select-tab="selectMenuTab"
            />

            <!-- 🧩 OVERLAYS (CONFIGURADORES E AÇÕES) -->
            <TeamOverlays
                ref="overlaysRef"
                :is-modifiers-visible="isModifiersVisible"
                :is-combo-visible="isComboVisible"
                :active-product="activeProduct"
                :is-edit-mode="editItemIndex !== null"
                :initial-products="initialProducts"
                v-model:contextMenuVisible="contextMenuVisible"
                v-model:showOrderActionsSheet="showOrderActionsSheet"
                :selected-order-context="selectedOrderContext"
                :company-settings="$page.props.companySettings"
                @update:total="productTotal = Number($event || 0)"
                @confirm-modifiers="handleModifiersConfirm"
                @confirm-combo="handleComboConfirm"
                @close-configurators="closeConfigurators"
                @action="(actionId, orderObj) => customHandleMenuAction(actionId, orderObj)"
                @duplicate-item="(item, cb) => customHandleItemDuplicate(item, cb)"
                @modify-item="handleItemModify"
                @swap-item="handleItemSwap"
                @cancel-item="handleItemCancel"
                @reprint-item="handleItemReprint"
            />

            <!-- 🚀 BOTTOM SHEET DE DESBLOQUEIO POR QR CODE -->
            <QuickUnlockSheet
                v-model:visible="showQuickUnlockSheet"
                :token="quickUnlockToken"
                @unlocked="quickUnlockToken = ''"
            />

            <!-- 🔔 MODAL DE ALERTA DE IMPRESSÃO -->
            <PrintAlertNotice
                v-if="activePrintAlert"
                :alert="activePrintAlert"
                mode="modal"
                @dismiss="activePrintAlert = null"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, defineAsyncComponent, watch, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from "primevue/usetoast";

import MobileLayout from "@/Layouts/MobileLayout.vue";
import TeamDynamicIsland from "@/Pages/Team/TeamDynamicIsland.vue";
import TeamModulesModal from "@/Pages/Team/TeamModulesModal.vue";
import TeamOverlays from "@/Pages/Team/TeamOverlays.vue";
import TeamHeader from "@/Pages/Team/TeamHeader.vue";
import TeamShiftLockOverlay from "@/Pages/Team/TeamShiftLockOverlay.vue";
import TeamTabContainer from "@/Pages/Team/TeamTabContainer.vue";
import PrintAlertNotice from '@/Components/UI/PrintAlertNotice.vue';
import ExpandableSearch from '@/Components/UI/ExpandableSearch.vue';

const QuickUnlockSheet = defineAsyncComponent(() => import('@/Pages/Gateway/QuickUnlockSheet.vue'));

import { useAppLifecycleSync } from '@/Composables/useAppLifecycleSync';
import { useCart } from "@/Pages/Shared/Composables/useCart";
import { useMenuNavigation } from "@/Pages/Shared/Composables/useMenuNavigation";
import { usePosSettings } from "@/Pages/Pos/Composables/usePosSettings";
import { useTeamApp } from "@/Pages/Team/Composables/useTeamApp";
import { usePushNotifications } from "@/Composables/usePushNotifications";
import { useOrderFlow } from "@/Pages/Shared/Composables/useOrderFlow";
import { useItemActions } from "@/Pages/Shared/Composables/useItemActions";
import { useRestock } from "@/Pages/Shared/Composables/useRestock";
import { useTeamSubmit } from "@/Pages/Team/Composables/useTeamSubmit";
import { useTeamBarKds } from "@/Pages/Team/Composables/useTeamBarKds";
import { useTeamHeaderContext } from "@/Pages/Team/Composables/useTeamHeaderContext";
import { useTeamSync } from "@/Pages/Team/Composables/useTeamSync";
import { useTenantEcho } from '@/Composables/useTenantEcho';

// 🚀 IMPORTAÇÃO DO NOVO COMPOSABLE DE HEIGHT
import { useDynamicFooterHeight } from '@/Composables/useDynamicFooterHeight';

defineOptions({ layout: MobileLayout });

const props = defineProps({
    defaultTab: { type: String, default: 'menu' },
    initialCategories: { type: Array, default: () => [] },
    initialProducts: { type: Array, default: () => [] },
    catalog: { type: Array, default: () => [] },
    initialTableAreas: { type: Array, default: () => [] },
    initialCustomers: { type: Array, default: () => [] },
    initialOrders: { type: Array, default: () => [] },
    editOrderData: Object,
    operatorName: { type: String, default: 'Equipe' },
    isOnDuty: { type: Boolean, default: false }
});

const toast = useToast();
const page = usePage();
const settings = usePosSettings();
const { categoryLayout, productCardLayout } = settings;
const { requestPushPermission, checkSubscriptionStatus, subscribeToWebPush } = usePushNotifications();

const tabContainerRef = ref(null);
const cartComponentKey = ref(0);
const showOrderActionsSheet = ref(false);
const cartRoutingValid = ref(false);
const cartRoutingMeta = ref(null);
const activePrintAlert = ref(null);
const isMounted = ref(false);

const handleClearCart = () => {
    isAllowanceMode.value = false;
    clearCart();
    cartRoutingValid.value = false;
    cartRoutingMeta.value = null;

    setTimeout(() => {
        localStorage.removeItem("pos_draft_routing");
        localStorage.removeItem('pos_draft_cart');
        localStorage.removeItem('pos_return_order_id');
        localStorage.removeItem('pos_return_url');
        localStorage.removeItem('pos_append_title');
    }, 50);

    if (window.location.search) {
        window.history.replaceState({}, '', window.location.pathname);
        router.visit(window.location.pathname);
    } else {
        cartComponentKey.value++;
        activeTab.value = 'menu';
    }
};

useAppLifecycleSync({
    only: ['initialOrders', 'initialTableAreas', 'cashierData', 'initialProducts'],
    minSleepSeconds: 10,
    onWake: (freshPage) => {
        const targetOrderId = cartRoutingMeta.value?.id || cartRoutingMeta.value?.order_id;
        if (targetOrderId) {
            const freshOrders = freshPage.props?.initialOrders || [];
            const isStillActive = freshOrders.some(o =>
                Number(o.id) === Number(targetOrderId) &&
                !['closed', 'cancelled', 'completed', 'delivered'].includes(o.status) &&
                o.payment_status !== 'paid'
            );

            if (!isStillActive) {
                toast.add({
                    severity: 'warn',
                    summary: 'Comanda Encerrada',
                    detail: 'Esta mesa foi quitada ou finalizada no caixa enquanto o aparelho estava em repouso.',
                    life: 6000
                });
                handleClearCart();
            }
        }
    }
});

const handleRoutingMeta = (newMeta) => {
    if (!newMeta && !cartRoutingMeta.value) return;
    try {
        if (JSON.stringify(newMeta) === JSON.stringify(cartRoutingMeta.value)) return;
    } catch (e) {}
    cartRoutingMeta.value = newMeta;
};

const handleRoutingValid = (isValid) => {
    if (cartRoutingValid.value !== isValid) {
        cartRoutingValid.value = isValid;
    }
};

const showMoreMenu = ref(false);
const showQuickUnlockSheet = ref(false);
const quickUnlockToken = ref('');

const restockData = useRestock();
const isAllowanceMode = ref(false);

const onSiteOrders = computed(() => {
    return (props.initialOrders || []).filter(o =>
        (o.type === 'table' || o.type === 'counter') &&
        !['closed', 'cancelled', 'completed', 'delivered'].includes(o.status)
    );
});

const {
    isStarting, activeTab, contextMenuVisible, selectedOrderContext,
    startShift: originalStartShift, openContextMenu, handleMenuAction
} = useTeamApp();

activeTab.value = props.defaultTab;

const cartContext = useCart();
const { cart, cartSubtotal, updateQty, clearCart, autoParkCurrent } = cartContext;
const orderType = cartContext.orderType = ref('table');

const { headerContext } = useTeamHeaderContext({
    activeTab,
    contextMenuVisible,
    selectedOrderContext,
    isAllowanceMode,
    cartRoutingMeta,
    cart,
    orderType,
    operatorName: computed(() => props.operatorName)
});

watch(activeTab, (newTab, oldTab) => {
    if (!newTab || newTab === oldTab) return;
    const targetUrl = route('team.app', { tab: newTab });
    if (window.location.href !== targetUrl) {
        window.history.pushState({ tab: newTab }, '', targetUrl);
    }
});

watch(showQuickUnlockSheet, (isVisible) => {
    if (!isVisible && window.location.search.includes('quick_unlock_token')) {
        router.visit(route('team.app', { tab: activeTab.value }), {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    }
});

const handlePopState = (event) => {
    if (event.state && event.state.tab) {
        activeTab.value = event.state.tab;
    } else {
        const path = window.location.pathname;
        const match = path.match(/\/team\/app\/([^/?#]+)/) || path.match(/\/waiter\/([^/?#]+)/);
        activeTab.value = match ? match[1] : 'menu';
    }
};

const handleTabChange = (e) => {
    activeTab.value = e.detail;
};

const selectMenuTab = (tab) => {
    activeTab.value = tab;
    showMoreMenu.value = false;
};

const openCheckout = (orderId) => {
    router.visit(route('operations.checkout', orderId));
};

const handleOpenCheckoutEvent = (e) => openCheckout(e.detail.orderId || e.detail);

const startShift = async () => {
    isStarting.value = true;
    const hasPermission = await requestPushPermission();
    if (hasPermission && page.props.vapidPublicKey) {
        await subscribeToWebPush(page.props.vapidPublicKey);
    }
    originalStartShift();
    restockData.clearRestock();
    restockData.fetchRestockBoard(true);
};

const {
    isSubmittingOrder, isOrderSuccess, handleCreateOrderOnTable,
    customHandleMenuAction, handleSendOrder, triggerCheckout, customHandleItemDuplicate
} = useTeamSubmit({
    props, cart, cartSubtotal, clearCart, activeTab,
    cartRoutingMeta, cartRoutingValid, orderType, cartComponentKey,
    selectedOrderContext, contextMenuVisible, showOrderActionsSheet, openContextMenu, handleMenuAction,
    isAllowanceMode,
    source: 'team_app'
});

const handleStartAllowanceOrder = () => {
    isAllowanceMode.value = true;
    clearCart();
    activeTab.value = 'menu';
    cartRoutingValid.value = true;
    cartRoutingMeta.value = { type: 'counter', identifier: 'Consumo Interno' };
    toast.add({ severity: 'info', summary: 'Modo Consumo Interno', detail: 'Monte seu pedido. O pagamento será debitado da sua carteira.', life: 4000 });
};

const handleCheckoutWrapper = (checkoutPayload) => {
    if (isAllowanceMode.value) {
        checkoutPayload.routing = checkoutPayload.routing || {};
        checkoutPayload.routing.payment_engine = 'internal';
        checkoutPayload.routing.payment_method_name = 'Consumo Interno';
    }
    handleSendOrder(checkoutPayload);
    setTimeout(() => { isAllowanceMode.value = false; }, 1000);
};

const {
    isModifiersVisible, isComboVisible, activeProduct, editItemIndex,
    initializeFlow, closeConfigurators, handleProductClick, openEditModifiers,
    handleModifiersConfirm, handleComboConfirm
} = useOrderFlow(props, cartContext, triggerCheckout);

// 🚀 CÁLCULO DINÂMICO DO RODAPÉ (AGORA LOGO APÓS A CRIAÇÃO DE isModifiersVisible/isComboVisible)
const islandRef = ref(null);
const overlaysRef = ref(null);

useDynamicFooterHeight(islandRef, {
    cssVarName: '--app-footer-height',
    fallbackHeight: 96,
    extraOffset: 16
});
// ===============================================

const { handleItemModify, handleItemSwap, handleItemCancel } = useItemActions(
    selectedOrderContext,
    contextMenuVisible,
    'team.app'
);

const productTotal = ref(0);

const handleKdsCancelWrapper = (item) => {
    selectedOrderContext.value = item.order;
    handleItemCancel(item);
};

const {
    barItems,
    pendingBarItemsCount,
    handleKdsTake,
    handleKdsComplete,
    handleKdsPrint,
    handleKdsCancel
} = useTeamBarKds(props, handleKdsCancelWrapper);

const handleItemReprint = (item) => {
    router.post(route('operations.kds.print-item', item.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { toast.add({ severity: 'success', summary: 'Reimpressão', detail: 'Via enviada para a cozinha!', life: 3000 }); }
    });
};

const mobileCategoryLayout = computed(() => categoryLayout.value === 'list' || productCardLayout.value === 'list' ? 'list' : 'grid');

const mobileSettings = {
    categoryHierarchy: settings.categoryHierarchy,
    productCardLayout: settings.productCardLayout,
    outOfStockMode: settings.outOfStockMode,
    categoryLayout: mobileCategoryLayout
};

const {
    selectedCategory, activeMenuCategory, visibleMenuCategories,
    filteredProducts, shouldShowProductGrid, breadcrumbs, goBackCategory,
    visibleGridCategories, shouldShowCategoryGrid, searchQuery
} = useMenuNavigation(() => props.initialCategories, () => props.initialProducts, mobileSettings);

useTeamSync({
    selectedOrderContext,
    activeTab,
    onShiftClosed: handleClearCart
});

useTenantEcho().listenChannel('operations', '.print.alert', (data) => {
    if (data.source === 'team_app') {
        activePrintAlert.value = data;
    }
});

onMounted(() => {
    checkSubscriptionStatus();
    window.addEventListener('team-tab-changed', handleTabChange);
    window.addEventListener('team-open-checkout', handleOpenCheckoutEvent);
    window.addEventListener('popstate', handlePopState);

    if (!props.isOnDuty) {
        handleClearCart();
    } else {
        restockData.fetchRestockBoard();
    }

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('quick_unlock_token');

    if (token) {
        quickUnlockToken.value = token;
        showQuickUnlockSheet.value = true;
        urlParams.delete('quick_unlock_token');
        const cleanSearch = urlParams.toString() ? `?${urlParams.toString()}` : '';
        window.history.replaceState({ tab: activeTab.value }, document.title, `${window.location.pathname}${cleanSearch}`);
    }

    initializeFlow(urlParams);
    if (urlParams.get('action') === 'new') cartComponentKey.value++;
    if (props.editOrderData) activeTab.value = 'menu';

    if (!token) {
        window.history.replaceState({ tab: activeTab.value }, '', route('team.app', { tab: activeTab.value }));
    }
    nextTick(() => {
        isMounted.value = true;
    });
});

onBeforeUnmount(() => {
    autoParkCurrent();
    window.removeEventListener('team-tab-changed', handleTabChange);
    window.removeEventListener('team-open-checkout', handleOpenCheckoutEvent);
    window.removeEventListener('popstate', handlePopState);
});

const onProductClick = (product) => {
    searchQuery.value = '';
    productTotal.value = Number(product?.price ?? product?.base_price ?? 0);
    handleProductClick(product);
};
</script>
