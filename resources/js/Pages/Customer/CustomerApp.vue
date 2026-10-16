<template>
    <div class="h-[100dvh] max-h-[100dvh] w-full text-surface-text flex flex-col font-sans overflow-hidden select-none relative">

        <div
            class="fixed inset-0 z-0 bg-cover bg-center transition-all duration-1000 pointer-events-none"
            :style="backgroundStyle"
        ></div>

        <CustomerHeader
            :context-title="headerContext.title"
            :context-subtitle="headerContext.subtitle"
            :price="headerContext.price"
            :active-product="isModifiersVisible || isComboVisible ? activeProduct : null"
            :is-home="headerContext.isHome"
            :first-name="headerContext.firstName"
            @close-product="closeConfigurators"
            @open-location="isStoreLocationModalVisible = true"
            @open-hours="isBusinessHoursModalVisible = true"
        />

        <!-- 🚀 INJEÇÃO DO PADDING DINÂMICO AQUI -->
       <main
            class="flex-1 min-h-0 overflow-hidden relative flex flex-col pt-safe z-10 transition-all duration-200"
        >
            <CustomerTabContainer
                v-model:active-tab="activeTab"
                v-model:menu-search="menuSearchQuery"
                v-model:menu-category="menuActiveCategory"
                v-model:menu-view="menuView"
                @update:visibleCategories="activeMenuCategories = $event"
                :is-configurator-open="isModifiersVisible || isComboVisible"
                :my-active-orders="myActiveOrders"
                :my-history="myHistory"
                :is-guest="isGuest"
                :initial-products="initialProducts"
                :initial-categories="initialCategories"
                :cart="cart"
                :customer="customer"
                :cart-subtotal="cartSubtotal"
                :order-type="orderType"
                :login-config="loginConfig"
                :active-combo-product="isComboVisible ? activeProduct : null"
                :is-edit-mode="editItemIndex !== null"
                :append-order="activeAppendOrder"
                @confirmCombo="handleComboConfirm"
                @upgrade-account="isUpgradeModalVisible = true"
                @reorder="handleReorder"
                @redeem-reward="handleRedeemReward"
                @product-click="onProductClick"
                @update-qty="updateQty"
                @clear-cart="clearCart"
                @cancel-append="handleCancelAppend"
                @edit-item="openEditModifiers"
                @checkout="openCheckout"
                @update:orderType="orderType = $event"
                @open-register="showNewCustomerModal = true"
                @add-address="isNewAddressModalVisible = true"
                @logout="handleLogout"
                @delete-account="confirmDeleteAccount"
                @update-profile="handleUpdateProfile"
                @deactivate-address="handleDeactivateAddress"
                @open-location="isStoreLocationModalVisible = true"
                @open-hours="isBusinessHoursModalVisible = true"
            />

            <NewCustomerModal
                v-model:visible="showNewCustomerModal"
                :ask-password="true"
                @save="handleRegisterSubmit"
            />
        </main>

        <Transition name="page-fade">
            <div v-if="isModifiersVisible || isComboVisible" class="fixed inset-0 z-30 flex flex-col bg-surface-1">
                <!-- 🚀 REFERÊNCIA DOS CONFIGURADORES (Sobrepõem a Island) -->
                <CustomerProductModifiers
                    v-if="isModifiersVisible"
                    ref="modifierNavRef"
                    :product="activeProduct"
                    :is-edit-mode="editItemIndex !== null"
                    :is-team-mode="false"
                    :is-customer-mode="true"
                    @update:total="productTotal = $event"
                    @confirm="handleModifiersConfirm"
                    @close="closeConfigurators"
                />

                <CustomerComboWizard
                    v-else-if="isComboVisible"
                    ref="comboNavRef"
                    :product="activeProduct"
                    :global-products-list="initialProducts"
                    :is-edit-mode="editItemIndex !== null"
                    :is-team-mode="false"
                    :is-customer-mode="true"
                    @update:total="productTotal = $event"
                    @update:inner-product="activeComboInnerProduct = $event"
                    @confirm-combo="handleComboConfirm"
                    @close="closeConfigurators"
                />
            </div>
        </Transition>

        <!-- 🚀 REFERÊNCIA DA ILHA PRINCIPAL -->
        <CustomerDynamicIsland
            ref="islandRef"
            v-model:active-tab="activeTab"
            :cart="cart"
            :cart-length="cartTotalItems"
            :customer="customer"
            :has-active-order="hasActiveOrder"
            :can-order="canOrder"
            :configurator-type="isComboVisible ? 'combo' : (isModifiersVisible ? 'modifier' : null)"
            @reset-menu="onResetMenu"
            @trigger-checkout="handleIslandCheckout"
        />

        <CustomerCheckoutModal
            v-if="isCheckoutModalVisible"
            :visible="isCheckoutModalVisible"
            @update:visible="isCheckoutModalVisible = $event"

            :customer="$page.props.auth?.user?.customer || customer"
            :is-guest="!$page.props.auth?.user"
            :payment-methods="$page.props.paymentMethods || []"
            :login-config="loginConfig"

            :state="checkoutState"
            :financials="financials"
            :loyalty="loyalty"
            :is-submitting="isSubmitting"
            :pending-order-id="activeOrderId"
            :pix-data="activePixData"
            :append-order="activeAppendOrder"

            :header-state="headerState || { title: 'Carregando...', current: 1, max: 1 }"
            :go-back="goBack || (() => {})"
            @bind-ref="stepRef = $event"
            @update-substep="(context, val) => {
                if(context === 'auth') checkoutState.authSubStep = val;
                else checkoutState.addressSubStep = val;
            }"

            @save-address="handleSaveNewAddress"
            @checkout-login="handleCheckoutLogin"
            @open-register="isRegisterModalVisible = true"
            @confirm="finalizeOrder"
            @pix-success="handlePixSuccess"
        />

        <UpgradeAccountModal v-model:visible="isUpgradeModalVisible" />
        <NewAddressModal
            v-model:visible="isNewAddressModalVisible"
            :customer-id="customer?.id"
            @save="handleSaveNewAddress"
            :is-customer-mode="true"
        />

        <StoreLocationModal
            v-model:visible="isStoreLocationModalVisible"
            :settings="$page.props.companySettings"
        />

        <BusinessHoursModal
            v-model:visible="isBusinessHoursModalVisible"
            :settings="$page.props.companySettings"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from "primevue/usetoast";
import axios from 'axios';

import MobileLayout from "@/Layouts/MobileLayout.vue";
import CustomerProductModifiers from "@/Pages/Customer/Menu/CustomerProductModifiers.vue";
import CustomerComboWizard from "@/Pages/Customer/Menu/CustomerComboWizard.vue";
import CustomerDynamicIsland from "@/Pages/Customer/CustomerDynamicIsland.vue";
import CustomerCheckoutModal from "@/Pages/Customer/CustomerCheckoutModal.vue";
import UpgradeAccountModal from "@/Pages/Customer/Profile/UpgradeAccountModal.vue";
import NewAddressModal from "@/Components/Modals/NewAddressModal.vue";
import NewCustomerModal from "@/Components/Modals/NewCustomerModal.vue";
import StoreLocationModal from "@/Pages/Customer/Home/StoreLocationModal.vue";
import BusinessHoursModal from "@/Pages/Customer/Home/BusinessHoursModal.vue";

import CustomerHeader from "@/Pages/Customer/CustomerHeader.vue";
import { useDynamicBackground } from '@/Composables/useDynamicBackground';
import CustomerTabContainer from "@/Pages/Customer/CustomerTabContainer.vue";
import { useCart } from "@/Pages/Shared/Composables/useCart";
import { useOrderFlow } from "@/Pages/Shared/Composables/useOrderFlow";
import { useReorder } from "@/Pages/Shared/Composables/useReorder";
import { useStoreStatus } from "@/Composables/useStoreStatus";
import { useCoupons } from '@/Pages/Shared/Composables/useCoupons';
import { useAnalytics } from '@/Composables/useAnalytics';
import { useCustomerCheckoutFlow } from '@/Pages/Customer/Composables/useCustomerCheckoutFlow';
import { useCustomerSync } from '@/Pages/Customer/Composables/useCustomerSync';
import { useTheme } from '@/Composables/useTheme';
import { useCustomerHeaderContext } from '@/Pages/Customer/Composables/useCustomerHeaderContext';
import { useAppLifecycleSync } from '@/Composables/useAppLifecycleSync';
import { useTenantEcho } from '@/Composables/useTenantEcho';
import { useDynamicFooterHeight } from '@/Composables/useDynamicFooterHeight';

defineOptions({ layout: MobileLayout, inheritAttrs: false });

const props = defineProps({
    customer: { type: Object, default: null },
    isGuest: { type: Boolean, default: false },
    initialCategories: { type: Array, default: () => [] },
    initialProducts: { type: Array, default: () => [] },
    myActiveOrders: { type: Array, default: () => [] },
    myHistory: { type: Array, default: () => [] }
});

const page = usePage();
const toast = useToast();
const { trackViewContent } = useAnalytics();
const { backgroundStyle } = useDynamicBackground();
const { setMode } = useTheme();

useAppLifecycleSync({
    only: ['customerOrder', 'catalog', 'companySettings'],
    minSleepSeconds: 15
});

const cartContext = useCart();
const { cart, cartSubtotal, updateQty, clearCart, autoParkCurrent } = cartContext;
const orderType = cartContext.orderType = ref('delivery');

const hasActiveOrder = computed(() => props.myActiveOrders && props.myActiveOrders.length > 0);
const cartTotalItems = computed(() => {
    return (cart.value || []).reduce((acc, item) => acc + (Number(item.qty) || 1), 0);
});

const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const hasDeepLink = searchParams ? (searchParams.has('produto') || searchParams.has('categoria')) : false;
const activeTab = ref(hasDeepLink ? 'menu' : 'home');

const currentAppendOrderId = ref(null);
const activeAppendOrder = computed(() => {
    if (!currentAppendOrderId.value) return null;
    return (props.myActiveOrders || []).find(o => String(o.id) === String(currentAppendOrderId.value)) || null;
});

const handleCancelAppend = () => {
    sessionStorage.removeItem('customer_append_order_id');
    currentAppendOrderId.value = null;
    clearCart();
    toast.add({ severity: 'info', summary: 'Cancelado', detail: 'Modo de adição de itens cancelado.', life: 3000 });
};

const isUpgradeModalVisible = ref(false);
const isNewAddressModalVisible = ref(false);
const showNewCustomerModal = ref(false);
const isStoreLocationModalVisible = ref(false);
const isBusinessHoursModalVisible = ref(false);
const isRegisterModalVisible = ref(false);

const handleOpenAuthModal = () => { showNewCustomerModal.value = true; };

const handleSwitchTabEvent = (event) => {
    if (event.detail && typeof event.detail === 'string') activeTab.value = event.detail;
    currentAppendOrderId.value = sessionStorage.getItem('customer_append_order_id');
};

const couponsContext = useCoupons();

const tryApplyCouponCode = async (couponCode) => {
    if (!couponCode) return;
    const cleanCode = String(couponCode).trim().toUpperCase();

    try {
        const response = await axios.post(route('customer.coupons.validate'), {
            code: cleanCode,
            subtotal: Number(cartSubtotal.value || 0)
        });

        const data = response.data;
        if (data.success) {
            if (typeof couponsContext.applyCoupon === 'function') {
                couponsContext.applyCoupon(data);
            } else if (couponsContext.activeCoupon) {
                couponsContext.activeCoupon.value = data;
            }

            sessionStorage.removeItem('pending_coupon_code');

            toast.add({
                severity: 'success',
                summary: '🎁 Presente Resgatado!',
                detail: `Cupom ${cleanCode} aplicado com sucesso!`,
                life: 6000
            });
        } else {
            toast.add({
                severity: 'warn',
                summary: 'Atenção ao Cupom',
                detail: data.message || 'Cupom inválido para esta compra.',
                life: 5000
            });
        }
    } catch (e) {
        console.warn('[Coupon] Falha ao validar cupom recebido:', e);
    }
};

const sanitizeCustomerSession = () => {
    const storedAppendId = sessionStorage.getItem('customer_append_order_id');
    if (storedAppendId) {
        const orderStillValid = (props.myActiveOrders || []).some(o => String(o.id) === String(storedAppendId));
        if (!orderStillValid) {
            sessionStorage.removeItem('customer_append_order_id');
            currentAppendOrderId.value = null;
        }
    }

    if (orderType.value === 'table' && !page.props.tableMode) {
        orderType.value = 'delivery';
        localStorage.removeItem('pos_draft_routing');
    }
};

onMounted(() => {
    setMode('dark');
    sanitizeCustomerSession();

    const storedAppendId = sessionStorage.getItem('customer_append_order_id');
    if (storedAppendId) {
        currentAppendOrderId.value = storedAppendId;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const incomingCoupon = urlParams.get('cupom') || urlParams.get('coupon');

    if (incomingCoupon) {
        const cleanIncoming = incomingCoupon.trim().toUpperCase();
        const isAuthenticatedUser = Boolean(props.customer?.id || page.props.auth?.user?.id);

        if (isAuthenticatedUser) {
            tryApplyCouponCode(cleanIncoming);
        } else {
            sessionStorage.setItem('pending_coupon_code', cleanIncoming);
            toast.add({
                severity: 'info',
                summary: '🎁 Presente Identificado!',
                detail: `Cupom ${cleanIncoming} reservado! Identifique-se para ativá-lo no seu primeiro pedido.`,
                life: 8000
            });
        }

        urlParams.delete('cupom');
        urlParams.delete('coupon');
        const cleanQuery = urlParams.toString() ? `?${urlParams.toString()}` : '';
        window.history.replaceState({}, document.title, `${window.location.pathname}${cleanQuery}`);
    }

    window.addEventListener('open-customer-auth-modal', handleOpenAuthModal);
    window.addEventListener('customer-switch-tab', handleSwitchTabEvent);
});

onBeforeUnmount(() => {
    window.removeEventListener('open-customer-auth-modal', handleOpenAuthModal);
    window.removeEventListener('customer-switch-tab', handleSwitchTabEvent);
});

useTenantEcho().listenChannel('shift', '.shift.closed', () => {
    sanitizeCustomerSession();
    router.reload({ only: ['myActiveOrders', 'companySettings'], preserveState: true, preserveScroll: true });
});

const menuSearchQuery = ref('');
const menuActiveCategory = ref(null);
const menuView = ref('catalog');
const activeMenuCategories = ref([]);

const productTotal = ref(0);
const activeComboInnerProduct = ref(null);

const loginConfig = computed(() => {
    const methods = page.props.companySettings?.customer_auth_methods ?? page.props.company?.customerAuthMethods ?? [];
    const allowGuestFlag = page.props.companySettings?.customer_allow_guest ?? page.props.company?.allowGuest;
    return {
        allowGuest: allowGuestFlag === true || allowGuestFlag === 1 || allowGuestFlag === '1',
        customerAuthMethods: methods,
        needsEmailFallback: !methods || methods.length === 0
    };
});

watch(() => cart.value.length, (newLength, oldLength) => {
    if (newLength === 0 && oldLength > 0 && activeTab.value === 'cart' && !activeAppendOrder.value) {
        activeTab.value = 'menu';
        toast.add({ severity: 'info', summary: 'Carrinho Vazio', detail: 'O cardápio está te esperando!', life: 3000 });
    }
});

const {
    isModifiersVisible, isComboVisible, activeProduct, editItemIndex,
    initializeFlow, closeConfigurators: originalCloseConfigurators, handleProductClick, openEditModifiers,
    handleModifiersConfirm, handleComboConfirm
} = useOrderFlow(props, cartContext);

// 🚀 CÁLCULO DINÂMICO DO RODAPÉ (AGORA LOGO APÓS A CRIAÇÃO DE isModifiersVisible/isComboVisible)
const islandRef = ref(null);

useDynamicFooterHeight(islandRef, {
    cssVarName: '--app-footer-height',
    fallbackHeight: 96,
    extraOffset: 16
});
// ===============================================

const closeConfigurators = () => {
    activeComboInnerProduct.value = null;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.delete('produto');
        window.history.pushState({}, '', url.toString());
    }
    originalCloseConfigurators();
};

watch(isComboVisible, (visible) => { if (!visible) activeComboInnerProduct.value = null; });

const { handleReorder } = useReorder(cartContext, activeTab, computed(() => props.initialProducts));

const { isOpen, isPreOrder, canOrder, storeAvailabilityText } = useStoreStatus();

const {
    isCheckoutModalVisible, isSubmitting, activePixData, activeOrderId,
    checkoutState, loyalty, financials, headerState, goBack, stepRef,
    openCheckout, finalizeOrder, handlePixSuccess, handleCheckoutLogin
} = useCustomerCheckoutFlow(props, cartContext, activeTab);

useCustomerSync(props, activeTab, couponsContext, { initializeFlow, autoParkCurrent });

const { headerContext } = useCustomerHeaderContext({
    activeTab,
    activeAppendOrder,
    activeCategory: computed(() => (activeMenuCategories.value || []).find(c => c.id === menuActiveCategory.value)),
    activeProduct: computed(() => (isModifiersVisible.value || isComboVisible.value) ? activeProduct.value : null),
    activeComboInnerProduct,
    productTotal,
    menuSearchQuery,
    menuView
});

const handleIslandCheckout = () => {
    if (!canOrder.value) {
        toast.add({
            severity: 'warn',
            summary: 'Loja Fechada',
            detail: 'Não estamos aceitando pedidos no momento.',
            life: 4000
        });
        return;
    }

    const isAppend = !!activeAppendOrder.value;
    openCheckout({ totals: { finalTotal: cartSubtotal.value }, isAppend });
};

const onResetMenu = () => {
    const scrollEl = document.querySelector('.hide-scrollbar');
    if (scrollEl) scrollEl.scrollTo({ top: 0, behavior: 'smooth' });
};

const onProductClick = (product) => {
    productTotal.value = Number(product.price || product.base_price || 0);
    trackViewContent(product);

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        const productParam = product.slug || product.id;
        url.searchParams.set('produto', productParam);
        window.history.pushState({}, '', url.toString());
    }

    handleProductClick(product);
};

watch(menuActiveCategory, (newCatId) => {
    if (newCatId && activeTab.value === 'menu' && !isModifiersVisible.value && !isComboVisible.value) {
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            const cat = (activeMenuCategories.value || []).find(c => c.id === newCatId);
            const catParam = (cat?.slug || cat?.name || newCatId).toString().toLowerCase().trim().replace(/[\s_]+/g, '-');
            url.searchParams.set('categoria', catParam);
            window.history.replaceState({}, document.title, url.toString());
        }
    }
});

const handleRedeemReward = (product) => {
    const rewardProduct = {
        ...product, dine_in_price: 0, delivery_price: 0, promo_price: 0,
        name: `🎁 [Prêmio] ${product.name}`,
    };
    cartContext.addToCart({
        product: rewardProduct, qty: 1, modifiersText: `Custo: ${product.loyalty_points_cost} pts`, extraPrice: 0,
        isTakeaway: false, comboItems: null, structuredModifiers: [], orderType: orderType.value
    });
    cart.value[cart.value.length - 1].is_reward = true;
    cart.value[cart.value.length - 1].points_cost = product.loyalty_points_cost;

    toast.add({ severity: 'success', summary: 'Prêmio Resgatado!', detail: `${product.name} foi adicionado ao carrinho grátis!`, life: 3000 });
    activeTab.value = 'cart';
};

const handleUpdateProfile = (formData, closeEditCallback) => {
    router.post(route('customer.profile.update'), formData, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (closeEditCallback) closeEditCallback();
            toast.add({ severity: 'success', summary: 'Perfil atualizado!', detail: 'Os seus dados foram guardados.', life: 3000 });
        },
        onError: (err) => toast.add({ severity: 'error', summary: 'Atenção', detail: Object.values(err)[0] || 'Verifique os dados.', life: 4000 })
    });
};

const handleDeactivateAddress = (addressId) => {
    router.post(route('customer.profile.deactivate-address', addressId), {}, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => toast.add({ severity: 'success', summary: 'Endereço Removido', detail: 'Endereço removido com sucesso.', life: 3000 })
    });
};

const handleSaveNewAddress = (addressData, callbacks = null) => {
    const isGuestUser = props.isGuest || !page.props.auth?.user;
    const endpoint = isGuestUser ? route('customer.auth.guest-address') : route('customer.profile.add-address');

    router.post(endpoint, addressData, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (pageResp) => {
            isNewAddressModalVisible.value = false;
            if (callbacks && typeof callbacks.onSuccess === 'function') {
                callbacks.onSuccess(pageResp);
            }
        },
        onError: (errors) => {
            if (callbacks && typeof callbacks.onError === 'function') callbacks.onError(errors);
        },
        onFinish: () => {
            if (callbacks && typeof callbacks.onFinish === 'function') callbacks.onFinish();
        }
    });
};

const handleLogout = () => {
    clearCart();
    localStorage.removeItem('brasume_guest_anchor');
    localStorage.removeItem('pos_draft_routing');
    router.post(route('customer.auth.logout'));
};

const confirmDeleteAccount = () => {
    if (confirm('Tem certeza de que deseja apagar a sua conta e todo o seu histórico de pedidos? Esta ação é irreversível.')) {
        localStorage.removeItem('brasume_guest_anchor');
        localStorage.removeItem('pos_draft_routing');
        router.post(route('customer.auth.delete-account'));
    }
};

const handleRegisterSubmit = (formData) => {};

watch(() => props.customer, (newCustomer) => {
    if (newCustomer) {
        localStorage.setItem('brasume_customer_footprint', JSON.stringify({
            name: newCustomer.name,
            email: newCustomer.email,
            method: page.props.auth?.user?.provider_name || 'email'
        }));

        const pendingCode = sessionStorage.getItem('pending_coupon_code');
        if (pendingCode) {
            tryApplyCouponCode(pendingCode);
        }

        if (!props.isGuest) {
            const currentMode = page.props.companySettings?.customer_registration_mode;
            const needsCpf = ['cpf_required', 'customer_only', 'serpro_magic'].includes(currentMode);

            if (!newCustomer.phone || (needsCpf && !newCustomer.cpf)) {
                toast.add({ severity: 'warn', summary: 'Quase lá!', detail: 'Para liberar todas as funções, confirme alguns dados.', life: 6000 });
                isUpgradeModalVisible.value = true;
            }
        } else if (!newCustomer.phone) {
            toast.add({ severity: 'warn', summary: 'Quase lá!', detail: 'Por favor, informe seu WhatsApp para liberar as opções de entrega.', life: 8000 });
            activeTab.value = 'account';
        }
    }
}, { immediate: true });
</script>
