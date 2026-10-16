<template>
    <div class="flex-1 min-h-0 overflow-hidden flex flex-col relative">
        <!-- 🏠 ABA: HOME -->
        <CustomerHomeTab
            v-show="activeTab === 'home'"
            :my-active-orders="myActiveOrders"
            :my-history="myHistory"
            :is-guest="isGuest"
            :initial-products="initialProducts"
            :cart="cart"
            :customer="customer"
            @retry-pix="$emit('retry-pix', $event)"
            @change-payment="$emit('change-payment', $event)"
            @upgrade-account="$emit('upgrade-account')"
            @reorder="$emit('reorder', $event)"
            @redeem-reward="$emit('redeem-reward', $event)"
            @navigate-to-menu="$emit('update:activeTab', 'menu')"
            @go-to-orders="$emit('update:activeTab', 'orders')"
            @open-register="isAuthModalVisible = true"
            @product-click="$emit('product-click', $event)"
        />

        <!-- 📖 ABA: CARDÁPIO -->
        <CustomerMenuTab
            v-show="activeTab === 'menu'"
            :is-active="activeTab === 'menu'"
            :is-configurator-open="isConfiguratorOpen"
            :categories="initialCategories"
            :products="initialProducts"
            :menu-search="menuSearch"
            :menu-view="menuView"
            :customer="customer"
            :is-guest="isGuest"
            @update:menu-category="$emit('update:menuCategory', $event)"
            @update:menu-search="$emit('update:menuSearch', $event)"
            @update:menu-view="$emit('update:menuView', $event)"
            @update:visible-categories="$emit('update:visibleCategories', $event)"
            @product-click="$emit('product-click', $event)"
            @open-recommended="$emit('product-click', $event)"
            @open-login="isAuthModalVisible = true"
        />

        <!-- 🛒 ABA: CARRINHO (Agora repassa corretamente o appendOrder e cancel-append) -->
        <CustomerCartTab
            v-show="activeTab === 'cart'"
            class="border-none! rounded-none! shadow-none! h-full pb-20"
            :cart="cart"
            :cart-subtotal="cartSubtotal"
            :is-guest="isGuest"
            :customer="customer"
            :append-order="appendOrder"
            @update-qty="(index, delta) => $emit('update-qty', index, delta)"
            @edit-item="(item, index) => $emit('edit-item', item, index)"
            @go-to-menu="$emit('update:activeTab', 'menu')"
            @checkout="$emit('checkout', $event)"
            @clear-cart="$emit('clear-cart')"
            @cancel-append="$emit('cancel-append')"
            @open-register="openStrictAuthModal"
        />

        <!-- 👤 ABA: MINHA CONTA -->
        <CustomerAccountTab
            v-show="activeTab === 'account'"
            :customer="customer"
            :is-guest="isGuest"
            :login-config="loginConfig"
            @open-register="handleSwitchToRegister"
            @open-login="isAuthModalVisible = true"
            @guest-action="$emit('update:activeTab', 'menu')"
            @upgrade-account="$emit('upgrade-account')"
            @add-address="$emit('add-address')"
            @logout="$emit('logout')"
            @delete-account="$emit('delete-account')"
            @update-profile="$emit('update-profile', $event)"
            @deactivate-address="$emit('deactivate-address', $event)"
        />

        <!-- 🍽️ ABA: CONTA DA MESA (Autoatendimento) -->
        <CustomerOpenTabs
            v-if="$page.props.tableMode"
            v-show="activeTab === 'orders' || activeTab === 'open_tabs'"
            :my-active-orders="myActiveOrders"
            :table-mode="$page.props.tableMode"
            :payment-methods="$page.props.paymentMethods || []"
            @go-to-menu="$emit('update:activeTab', 'menu')"
        />

        <!-- 📜 ABA: PEDIDOS (Apenas fora do Modo Mesa) -->
        <CustomerOrdersTab
            v-else
            v-show="activeTab === 'orders'"
            :my-history="myHistory"
            :customer="customer"
            :is-guest="isGuest"
            @reorder="$emit('reorder', $event)"
            @go-to-menu="$emit('update:activeTab', 'menu')"
            @open-login="isAuthModalVisible = true"
            @open-register="handleSwitchToRegister"
        />

        <!-- 🚀 MODAL DE ACESSO -->
        <CustomerAuthModal
            v-model:visible="isAuthModalVisible"
            :is-cart-mode="false"
            :is-guest="isGuest"
            :force-registration="forceAuthModalRegistration"
            @open-register="handleSwitchToRegister"
            @authenticated="handleAuthSuccess"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

import CustomerCartTab from "@/Pages/Customer/CustomerCartTab.vue";
import CustomerHomeTab from "@/Pages/Customer/CustomerHomeTab.vue";
import CustomerMenuTab from "@/Pages/Customer/CustomerMenuTab.vue";
import CustomerOrdersTab from "@/Pages/Customer/CustomerOrderHistoryTab.vue";
import CustomerAccountTab from "@/Pages/Customer/CustomerAccountTab.vue";
import CustomerAuthModal from "@/Pages/Customer/CustomerAuthModal.vue";
import CustomerOpenTabs from "@/Pages/Customer/CustomerOpenTabs.vue";

const props = defineProps({
    activeTab: String,
    isConfiguratorOpen: { type: Boolean, default: false },
    myActiveOrders: { type: Array, default: () => [] },
    myHistory: { type: Array, default: () => [] },
    isGuest: { type: Boolean, default: false },
    initialProducts: { type: Array, default: () => [] },
    initialCategories: { type: Array, default: () => [] },
    cart: { type: Array, default: () => [] },
    customer: { type: Object, default: null },
    cartSubtotal: Number,
    orderType: String,
    loginConfig: Object,
    appendOrder: { type: Object, default: null },

    menuSearch: { type: String, default: '' },
    menuCategory: { type: [String, Number], default: null },
    menuView: { type: String, default: 'catalog' }
});

const emit = defineEmits([
    'update:activeTab', 'retry-pix', 'change-payment', 'upgrade-account',
    'reorder', 'redeem-reward', 'product-click', 'update-qty', 'clear-cart',
    'edit-item', 'checkout', 'update:orderType', 'open-register', 'add-address',
    'logout', 'delete-account', 'update-profile', 'deactivate-address', 'open-location', 'open-hours',
    'update:menuSearch', 'update:menuCategory', 'update:menuView', 'update:visibleCategories',
    'cancel-append'
]);

const page = usePage();
const toast = useToast();
const isAuthModalVisible = ref(false);
const forceAuthModalRegistration = ref(false);

const openStrictAuthModal = () => {
    forceAuthModalRegistration.value = true;
    isAuthModalVisible.value = true;
};

watch(isAuthModalVisible, (newVal) => {
    if (!newVal) {
        setTimeout(() => { forceAuthModalRegistration.value = false; }, 300);
    }
});

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.has('open_login')) {
        isAuthModalVisible.value = true;
    }

    if (urlParams.has('open_upgrade')) {
        emit('upgrade-account');
    }
});

watch(() => props.customer, (newCustomer) => {
    if (newCustomer) {
        localStorage.setItem('brasume_customer_footprint', JSON.stringify({
            name: newCustomer.name,
            email: newCustomer.email,
            method: page.props.auth?.user?.provider_name || 'email'
        }));

        if (!props.isGuest) {
            const currentMode = page.props.companySettings?.customer_registration_mode;
            const needsCpf = ['cpf_required', 'customer_only', 'serpro_magic'].includes(currentMode);

            if (!newCustomer.phone || (needsCpf && !newCustomer.cpf)) {
                toast.add({ severity: 'warn', summary: 'Quase lá!', detail: 'Para liberar todas as funções, confirme alguns dados.', life: 6000 });
                emit('upgrade-account');
            }
        } else if (!newCustomer.phone) {
            toast.add({ severity: 'warn', summary: 'Quase lá!', detail: 'Por favor, informe seu WhatsApp para liberar as opções de entrega.', life: 8000 });
            emit('update:activeTab', 'account');
        }
    }
}, { immediate: true });

const handleSwitchToRegister = () => {
    isAuthModalVisible.value = false;
    setTimeout(() => emit('open-register'), 300);
};

const handleAuthSuccess = () => { isAuthModalVisible.value = false; };
</script>
