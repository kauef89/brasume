<template>
    <main class="flex-1 flex flex-col min-h-0 bg-surface-2 relative overflow-hidden pt-safe">

        <!-- 🏠 ABA: HOME DA EQUIPA -->
        <div v-if="activeTab === 'home'" class="flex-1 h-full min-h-0 flex flex-col overflow-hidden animate-in fade-in">
            <TeamHomeTab />
        </div>

        <!-- 📖 ABA: CARDÁPIO -->
        <TeamMenuTab
            v-show="activeTab === 'menu'"
            :is-active="activeTab === 'menu'"
            :search-query="searchQuery"
            @update:search-query="$emit('update:searchQuery', $event)"
            :category-layout="categoryLayout"
            :mobile-category-layout="mobileCategoryLayout"
            :product-card-layout="productCardLayout"
            :visible-menu-categories="visibleMenuCategories"
            :active-menu-category="activeMenuCategory"
            :visible-grid-categories="visibleGridCategories"
            :should-show-category-grid="shouldShowCategoryGrid"
            :should-show-product-grid="shouldShowProductGrid"
            :filtered-products="filteredProducts"
            @category-select="$emit('categorySelect', $event)"
            @product-click="$emit('productClick', $event)"
        />

        <!-- 🎛️ ABA: COMANDAS -->
        <div
            v-if="activeTab === 'operations'"
            class="flex-1 px-4 pb-4 flex flex-col min-h-0 animate-in fade-in"
            :style="{ paddingTop: 'var(--app-header-height, 7rem)' }"
        >
            <OpenTabs
                :orders="onSiteOrders"
                :table-areas="initialTableAreas"
                :is-team-mode="true"
                @open-menu="(...args) => $emit('openMenu', ...args)"
                @create-order="(...args) => $emit('createOrder', ...args)"
            />
        </div>

        <!-- 📦 ABA: REPOSIÇÃO -->
        <div v-if="activeTab === 'restock'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <RestockTab :use-restock-data="restockData" />
        </div>

        <!-- 📦 ABA: ESTOQUE E INVENTÁRIO (WMS MOBILE) -->
        <div v-if="activeTab === 'inventory'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <InventoryTab
                :catalog="catalog || initialProducts"
                :is-active="activeTab === 'inventory'"
            />
        </div>

        <!-- 📊 ABA: GESTOR -->
        <div v-if="activeTab === 'manager'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <ManagerShiftDashboard />
        </div>

        <!-- 🖥️ NOVO: ABA: TERMINAIS (FROTA) -->
        <div v-if="activeTab === 'fleet'" class="flex-1 overflow-y-auto hide-scrollbar p-0 pb-32 animate-in fade-in">
            <DeviceFleetPanel />
        </div>

        <!-- 🍹 ABA: MINI KDS DO BAR -->
        <div v-if="activeTab === 'bar'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <TeamBarTab
                :pending-items="barItems"
                @take-item="$emit('kds-take', $event)"
                @complete-item="$emit('kds-complete', $event)"
                @print-item="$emit('kds-print', $event)"
                @loss-item="$emit('kds-loss', $event)"
            />
        </div>

        <!-- ⚙️ ABA: CONFIGURAÇÕES -->
        <div v-show="activeTab === 'settings'" class="flex-1 overflow-y-auto hide-scrollbar p-4 pb-32 animate-in fade-in">
            <AppearanceSettings :is-team-mode="true" />
        </div>

        <!-- 👛 ABA: CARTEIRA (Perfil Corporativo) -->
        <div v-if="activeTab === 'wallet'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <CorporateProfileTab
                :employee-data="$page.props.employeeWalletData"
                @start-allowance-order="$emit('startAllowanceOrder')"
                @open-settings="$emit('update:activeTab', 'settings')"
            />
        </div>

        <!-- 🛵 ABA: MOTOBOY -->
        <div v-if="activeTab === 'driver'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <DriverBoard />
        </div>

        <!-- ✅ ABA: CHECKLISTS -->
        <div v-if="activeTab === 'checklists'" class="flex-1 overflow-y-auto p-4 pb-32 hide-scrollbar animate-in fade-in">
            <TeamChecklists />
        </div>

        <!-- 🏭 ABA: PRODUÇÃO -->
        <div v-if="activeTab === 'production'" class="flex-1 overflow-hidden flex flex-col min-h-0 pb-20 animate-in fade-in">
            <ProductionBoard :orders="$page.props.productionOrders || []" />
        </div>

        <!-- 🛒 ABA: COMPRAS -->
        <div v-if="activeTab === 'shopping'" class="flex-1 overflow-hidden flex flex-col min-h-0 pb-20 animate-in fade-in">
            <ShoppingList :bidding-matrix="$page.props.biddingMatrix || {}" />
        </div>

        <!-- 🛒 CARRINHO DA EQUIPE -->
        <TeamCart
            ref="cartRef"
            :key="cartComponentKey"
            v-show="activeTab === 'cart'"
            class="!border-none !rounded-none !shadow-none h-full animate-in slide-in-from-right-4"
            :cart="cart"
            :cart-subtotal="cartSubtotal"
            :order-type="orderType"
            :table-areas="initialTableAreas"
            :customers="initialCustomers"
            :edit-order-data="editOrderData"
            :is-allowance-mode="isAllowanceMode"
            :cart-routing-meta="cartRoutingMeta"
            @update-qty="(idx, delta) => $emit('update-qty', idx, delta)"
            @clear-cart="$emit('clearCart')"
            @edit-item="(item, idx) => $emit('edit-item', item, idx)"
            @checkout="$emit('checkout', $event)"
            @update:orderType="$emit('update:orderType', $event)"
            @routing-valid="$emit('routingValid', $event)"
            @routing-meta="$emit('routingMeta', $event)"
        />
    </main>
</template>

<script setup>
import { ref, defineAsyncComponent } from 'vue';
import TeamCart from "@/Pages/Team/TeamCart.vue";
import AppearanceSettings from "@/Pages/Settings/Components/AppearanceSettings.vue";
import OpenTabs from "@/Pages/Operations/Components/Dashboard/OpenTabs.vue";
import TeamMenuTab from "@/Pages/Team/TeamMenuTab.vue";

// Abas Assíncronas (Lazy Load)
const InventoryTab = defineAsyncComponent(() => import('@/Pages/Team/InventoryTab.vue'));
const TeamHomeTab = defineAsyncComponent(() => import('@/Pages/Team/TeamHomeTab.vue'));
const RestockTab = defineAsyncComponent(() => import('@/Pages/Team/RestockTab.vue'));
const ManagerShiftDashboard = defineAsyncComponent(() => import('@/Pages/Team/ManagerShiftDashboard.vue'));
const CorporateProfileTab = defineAsyncComponent(() => import('@/Pages/Team/CorporateProfileTab.vue'));
const DriverBoard = defineAsyncComponent(() => import('@/Pages/Team/DriverBoard.vue'));
const TeamChecklists = defineAsyncComponent(() => import('@/Pages/Team/TeamChecklists.vue'));
const ProductionBoard = defineAsyncComponent(() => import('@/Pages/Shared/SupplyChain/ProductionBoard.vue'));
const ShoppingList = defineAsyncComponent(() => import('@/Pages/Shared/SupplyChain/ShoppingList.vue'));
const DeviceFleetPanel = defineAsyncComponent(() => import('@/Pages/Team/DeviceFleetPanel.vue'));
const TeamBarTab = defineAsyncComponent(() => import('@/Pages/Team/TeamBarTab.vue'));

const props = defineProps({
    activeTab: String,
    searchQuery: { type: String, default: '' },
    categoryLayout: String,
    mobileCategoryLayout: String,
    productCardLayout: String,
    visibleMenuCategories: Array,
    activeMenuCategory: [String, Number],
    visibleGridCategories: Array,
    shouldShowCategoryGrid: Boolean,
    shouldShowProductGrid: Boolean,
    filteredProducts: Array,
    onSiteOrders: Array,
    initialTableAreas: Array,
    restockData: Object,
    catalog: Array,
    initialProducts: Array,
    cartComponentKey: Number,
    cart: Array,
    cartSubtotal: Number,
    orderType: String,
    initialCustomers: Array,
    editOrderData: Object,
    isAllowanceMode: Boolean,
    cartRoutingMeta: Object,
    barItems: { type: Array, default: () => [] }
});

defineEmits([
    'update:activeTab', 'update:searchQuery', 'categorySelect', 'productClick', 'openMenu',
    'createOrder', 'startAllowanceOrder', 'update-qty', 'clearCart',
    'edit-item', 'checkout', 'update:orderType', 'routingValid', 'routingMeta',
    'kds-take', 'kds-complete', 'kds-print', 'kds-loss', 'kds-cancel'
]);

const cartRef = ref(null);

const toggleCartRouting = () => {
    cartRef.value?.toggleRouting();
};

defineExpose({
    toggleCartRouting
});
</script>
