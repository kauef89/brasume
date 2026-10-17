<template>
    <div class="w-100 h-full flex flex-col bg-surface-2 overflow-hidden shrink-0" :class="isWaiterMode ? 'pb-24' : ''">

        <!-- MODO EXCLUSIVO: CRIAÇÃO DE ENDEREÇO -->
        <div v-if="isAddressCreateVisible" class="h-full w-full p-4 overflow-y-auto hide-scrollbar animate-in fade-in duration-200">
            <PosAddressCreateModal
                :visible="isAddressCreateVisible"
                :customer-id="selectedCustomerId"
                :customer-name="selectedCustomerName"
                :streets="availableStreets"
                @update:visible="isAddressCreateVisible = $event"
                @success="handleAddressCreated"
            />
        </div>

        <!-- FLUXO NORMAL DO CARRINHO -->
        <template v-else>
            <CartHeader v-if="!isWaiterMode" :title="headerTitle" :subtitle="headerSubtitle" :is-waiter-mode="isWaiterMode">
                <template #actions>
                    <button v-if="isLoyaltyGiftActive && customerPoints > 0" @click="toggleGiftFilter" class="btn-icon btn-icon-primary" :class="{ '!bg-purple-500 !text-white active': isGiftFilterActive }" :title="isGiftFilterActive ? 'Remover Filtro de Prêmios' : 'Ver Prêmios Disponíveis'">
                        <i class="pi pi-gift text-sm font-medium"></i>
                    </button>
                    <button v-if="cart.length > 0" @click="toggleEstimator" class="btn-icon btn-icon-primary" :class="{ 'active': showEstimatorConfig }" title="Copiar para WhatsApp">
                        <i class="pi pi-whatsapp text-sm font-medium"></i>
                    </button>
                    <!-- Só permite sub-conta em pedidos novos de mesa -->
                    <button v-if="!isAppending && isRoutingValid && orderMetadata?.type === 'table'" @click="promptSubAccountName" class="btn-icon btn-icon-primary" title="Identificar Cliente">
                        <i class="pi pi-user-plus text-sm font-medium"></i>
                    </button>

                    <!-- Botão de roteamento/destino: OCULTO durante append -->
                    <SecureActionButton v-if="!isAppending" action-code="toggle_routing" @secure-click="isRoutingProcessOpen = !isRoutingProcessOpen" severity="primary" custom-class="btn-icon btn-icon-primary p-0 w-10 h-10 flex items-center justify-center shrink-0" :class="{ 'active': isRoutingProcessOpen }" title="Alterar Roteamento/Destino">
                        <i class="pi pi-sliders-h text-sm"></i>
                    </SecureActionButton>
                    <button v-if="cart.length > 0" @click="handleParkOrder" class="btn-icon btn-icon-primary" title="Pausar Pedido">
                        <i class="pi pi-folder-open text-sm"></i>
                    </button>
                    <SecureActionButton v-if="cart.length > 0 || isAppending" action-code="clear_cart" @secure-click="handleClearCartAction" severity="danger" custom-class="btn-icon btn-icon-danger p-0 w-10 h-10 flex items-center justify-center shrink-0"  class="btn-icon btn-icon-danger" title="Limpar carrinho / Cancelar Reforço">
                        <i class="pi pi-trash text-sm"></i>
                    </SecureActionButton>
                </template>
            </CartHeader>

            <!-- MÓDULO DE ROTEAMENTO (Mesa, Delivery, Balcão) -->
            <div v-if="!isAppending" class="flex flex-col transition-all duration-300" :class="[
                isWaiterMode ? 'bg-transparent px-4 pb-2 pt-28' : 'p-6 pt-0',
                isRoutingProcessOpen ? 'flex-1 overflow-y-auto hide-scrollbar' : 'shrink-0'
            ]">
                <OrderRouting
                    :modelValue="orderType"
                    :routing-meta="orderMetadata"
                    :is-open="isRoutingProcessOpen"
                    :table-areas="tableAreas"
                    :customers="customers"
                    :is-waiter-mode="isWaiterMode"
                    @update:modelValue="handleOrderTypeUpdate"
                    @routing-change="handleRoutingChange"
                    @apply="handleRoutingApply"
                    @load-order="handleLoadOrder"
                    @open-new-customer="handleOpenNewCustomer"
                    @open-new-address="handleOpenNewAddress"
                    @toggle-open="isRoutingProcessOpen = !isRoutingProcessOpen"
                    @open="isRoutingProcessOpen = true"
                />
            </div>

            <!-- ESTIMADOR WHATSAPP -->
            <div v-show="showEstimatorConfig" class="flex flex-col shrink-0 mx-6 pb-6 border-b border-surface-border/50">
                <WhatsAppEstimator :cart="cart" :metadata="orderMetadata" :totals="{ subtotal: cartSubtotal, deliveryFee: deliveryFee, serviceFee: serviceFee, discount: activeDiscount, finalTotal: finalTotal }" @close="showEstimatorConfig = false" />
            </div>

            <!-- LISTAGEM DE ITENS DO CARRINHO -->
            <div v-show="isWaiterMode || (!isRoutingProcessOpen && !showEstimatorConfig)" class="flex-1 overflow-y-auto px-5 pt-4 flex flex-col gap-0 hide-scrollbar relative">

                <!-- 🚀 BANNER INTELIGENTE DE REFORÇO / ADIÇÃO DE ITENS -->
                <AppendOrderBanner
                    v-if="isAppending"
                    :is-appending="isAppending"
                    :target-order-id="targetOrderId"
                    :original-order="originalOrder"
                    :already-paid-amount="alreadyPaidAmount"
                    :new-items-amount="newItemsAmount"
                    :balance-due="balanceDue"
                    :was-originally-paid="wasOriginallyPaid"
                    :change-analysis="changeAnalysis"
                    class="mb-3"
                />

                <div v-if="cart.length === 0" class="flex-1 flex flex-col items-center justify-center text-surface-muted h-full opacity-60 py-10">
                    <i class="pi pi-shopping-bag text-5xl mb-3"></i>
                    <p class="font-medium text-base">{{ isAppending ? 'Escolha novos itens no cardápio' : 'Carrinho Vazio' }}</p>
                    <p class="text-xs mt-1 text-center px-8">
                        {{ isAppending ? 'Selecione os produtos para complementar esta comanda.' : 'Nenhum produto foi adicionado a esta comanda ainda.' }}
                    </p>
                </div>

                <div v-for="(item, index) in cart" :key="item.cartId || index" class="flex flex-col" @mouseenter="hoveredIndex = index" @mouseleave="hoveredIndex = null">
                    <OrderLineItem
                        :title="item.name"
                        :qty="item.qty"
                        :price="item.price * item.qty"
                        :structured-modifiers="item.structuredModifiers"
                        :combo-items="item.comboItems"
                        :is-takeaway="item.isTakeaway"
                        :is-expanded="expandedStates[item.cartId || index] || false"
                        @expanded-change="setExpanded(item, index, $event)"
                    >
                        <template #actions>
                            <div class="flex items-center justify-between w-full gap-2 cursor-default" @click.stop>
                                <QtyStepper
                                    :model-value="item.qty"
                                    :min="1"
                                    @increment="$emit('update-qty', index, 1)"
                                    @decrement="$emit('update-qty', index, -1)"
                                    size="sm"
                                >
                                    <template #value><span class="text-xs">{{ item.qty }}</span></template>
                                </QtyStepper>

                                <div class="flex items-center gap-2">
                                    <button @click="$emit('edit-item', item, index)" type="button" class="w-8 h-8 rounded-xl p-option transition-all flex items-center justify-center outline-none cursor-pointer" title="Editar Adicionais">
                                        <i class="pi pi-pencil text-xs"></i>
                                    </button>
                                    <button @click="item.isTakeaway = !item.isTakeaway" type="button" class="w-8 h-8 rounded-xl p-option transition-all flex items-center justify-center outline-none cursor-pointer" :class="item.isTakeaway ? 'bg-warning-500/10 border-warning-500/30 text-warning-600' : 'bg-surface-1 border-surface-border text-surface-muted hover:text-surface-text'" title="Para Viagem">
                                        <i class="pi pi-shopping-bag text-xs"></i>
                                    </button>
                                    <button @click="$emit('update-qty', index, -item.qty)" type="button" class="w-8 h-8 rounded-xl bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center outline-none cursor-pointer border-none" title="Remover do carrinho">
                                        <i class="pi pi-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </OrderLineItem>
                    <div v-if="index < cart.length - 1" class="h-[2px] mx-2 bg-surface-border/50 transition-opacity duration-300" :class="{ 'opacity-0': isLineHidden(index) }"></div>
                </div>

                <div v-if="isWaiterMode" class="h-32 shrink-0 w-full pointer-events-none"></div>
            </div>

            <!-- RESUMO FINANCEIRO / SUBMETER PARA PRODUÇÃO -->
            <CartSummary
                v-show="isWaiterMode || (!isRoutingProcessOpen && !showEstimatorConfig)"
                :subtotal="cartSubtotal"
                :delivery-fee="deliveryFee"
                :has-address="hasDeliveryAddress"
                :service-fee="serviceFee"
                :service-tax-percentage="serviceTaxPercentage"
                :show-service-fee-row="hasServiceTaxGlobal"
                :discount="activeDiscount"
                :cashback-discount="0"
                :final-total="finalTotal"
                :order-type="orderType"
                :is-routing-valid="isRoutingValid"
                :can-submit="canSubmit"
                :applied-coupon-name="appliedCouponName"
                :is-customer-mode="false"
                @add-address="openDeliveryRouting"
                @apply-coupon="applyCoupon($event, cartSubtotal)"
                @remove-coupon="removeCoupon"
                @checkout="processCheckout"
            />

            <!-- MODAL DE CRIAÇÃO RÁPIDA DE CLIENTE -->
            <PosCustomerCreateModal
                v-model:visible="isCustomerModalVisible"
                :initial-query="customerQueryForModal"
                @success="handleCustomerCreated"
            />
        </template>
    </div>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';

import { ref, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

import CartHeader from "@/Pages/Pos/Components/Cart/CartHeader.vue";
import OrderRouting from "@/Pages/Pos/Components/Cart/OrderRouting.vue";
import OrderLineItem from "@/Pages/Shared/Order/OrderLineItem.vue";
import CartSummary from "@/Pages/Pos/Components/Cart/CartSummary.vue";
import WhatsAppEstimator from "@/Pages/Pos/Components/Cart/WhatsAppEstimator.vue";
import QtyStepper from "@/Components/UI/QtyStepper.vue";
import AppendOrderBanner from "@/Pages/Pos/Components/Cart/AppendOrderBanner.vue";

import PosCustomerCreateModal from "@/Pages/Pos/Components/Cart/Delivery/PosCustomerCreateModal.vue";
import PosAddressCreateModal from "@/Pages/Pos/Components/Cart/Delivery/PosAddressCreateModal.vue";

import { useCoupons } from "@/Pages/Shared/Composables/useCoupons";
import { abbreviateName } from "@/Utils/formatters";
import { useCart } from "@/Pages/Shared/Composables/useCart";
import { useOrderAppend } from "@/Pages/Shared/Composables/useOrderAppend";

const props = defineProps({
    cart: { type: Array, required: true },
    cartSubtotal: { type: Number, required: true },
    orderType: { type: [String, null], default: 'table' },
    tableAreas: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    streets: { type: Array, default: () => [] },
    editOrderData: { type: Object, default: null },
    isWaiterMode: { type: Boolean, default: false },
    isGiftFilterActive: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update-qty',
    'clear-cart',
    'park-order',
    'edit-item',
    'checkout',
    'update:orderType',
    'routing-valid',
    'routing-meta',
    'toggle-gift-filter',
]);

const page = usePage();
const toast = useToast();

const hoveredIndex = ref(null);
const expandedStates = ref({});
const showEstimatorConfig = ref(false);

const { draftRouting: orderMetadata } = useCart();

// 🚀 COMPOSABLE DEDICADO DE ADIÇÃO DE ITENS (APPEND)
const initialOrdersRef = computed(() => page.props.initialOrders || []);
const {
    targetOrderId,
    isAppending,
    originalOrder,
    alreadyPaidAmount,
    newItemsAmount,
    balanceDue,
    wasOriginallyPaid,
    changeAnalysis
} = useOrderAppend(orderMetadata, computed(() => props.cartSubtotal), initialOrdersRef);

const isRoutingProcessOpen = ref(false);
const isRoutingValid = ref(false);

const isCustomerModalVisible = ref(false);
const customerQueryForModal = ref('');
const isAddressCreateVisible = ref(false);

const availableStreets = computed(() => {
    if (props.streets && props.streets.length > 0) return props.streets;
    return page.props.initialStreets || page.props.streets || [];
});

const selectedCustomerId = computed(() => orderMetadata.value?.customer?.id || null);
const selectedCustomerName = computed(() => orderMetadata.value?.customer?.name || 'Cliente');

watch(
    () => [props.editOrderData, orderMetadata.value?.existing_order_id],
    ([editData, existingId]) => {
        if (editData || existingId) {
            isRoutingProcessOpen.value = false;
            isRoutingValid.value = true;
        }
    },
    { immediate: true }
);

const hasDeliveryAddress = computed(() => {
    const meta = orderMetadata.value || {};
    const edit = props.editOrderData || {};
    return Boolean(
        meta.address?.id ||
        meta.address_id ||
        meta.address?.street_id ||
        (meta.address?.street && String(meta.address.street).trim().length > 0) ||
        edit.address?.id ||
        edit.address_id
    );
});

const openDeliveryRouting = () => {
    isRoutingProcessOpen.value = true;
};

watch(() => props.editOrderData, (editData) => {
    if (editData) {
        let targetType = editData.type || 'table';
        if (!editData.type && editData.table_number) targetType = 'table';

        let tableId = editData.dining_table_id || editData.table_id || editData.table?.id || null;
        let tableName = editData.table_number || editData.table?.name || '';

        if (!tableId && tableName && props.tableAreas?.length > 0) {
            const found = props.tableAreas
                .flatMap(a => a.tables || [])
                .find(t => String(t.name) === String(tableName) || String(t.number) === String(tableName));
            if (found) tableId = found.id;
        }

        let targetTable = targetType === 'table' ? { id: tableId, name: tableName } : null;
        let resolvedIdentifier = editData.identifier || editData.customer_name || '';

        if (targetType === 'table' && !resolvedIdentifier && targetTable?.name) {
            resolvedIdentifier = `Mesa ${targetTable.name}`;
        }

        orderMetadata.value = {
            existing_order_id: editData.id,
            order_id: editData.id,
            type: targetType,
            identifier: resolvedIdentifier,
            customer: editData.customer || (editData.customer_name ? { name: editData.customer_name, phone: editData.customer_phone } : null),
            address: editData.address || null,
            table: targetTable
        };

        isRoutingValid.value = true;
        isRoutingProcessOpen.value = false;

        emit('update:orderType', targetType);
        emit('routing-meta', orderMetadata.value);
    } else {
        if (orderMetadata.value) {
            orderMetadata.value.existing_order_id = null;
            orderMetadata.value.order_id = null;
        }
    }
}, { immediate: true, deep: true });

const handleOpenNewCustomer = (query = '') => {
    customerQueryForModal.value = query;
    isCustomerModalVisible.value = true;
};

const handleCustomerCreated = (newCustomer) => {
    orderMetadata.value = { ...orderMetadata.value, customer: newCustomer };
    emit('routing-meta', orderMetadata.value);

    if (props.orderType === 'delivery') {
        isAddressCreateVisible.value = true;
    }
};

const handleOpenNewAddress = () => {
    if (!selectedCustomerId.value) {
        toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Selecione ou cadastre o cliente antes de adicionar um endereço.', life: 3000 });
        return;
    }
    isAddressCreateVisible.value = true;
};

const handleAddressCreated = (newAddress) => {
    isAddressCreateVisible.value = false;

    const streetName = newAddress.street?.name || newAddress.street || '';
    const neighborhoodName = newAddress.neighborhood?.name || newAddress.neighborhood || '';
    const formattedText = newAddress.full_address || `${streetName}, ${newAddress.number || 'S/N'}${neighborhoodName ? ' - ' + neighborhoodName : ''}`;

    const completeAddress = {
        ...newAddress,
        full_address: formattedText
    };

    orderMetadata.value = {
        ...orderMetadata.value,
        address: completeAddress
    };

    if (orderMetadata.value.customer?.is_temporary) {
        if (!Array.isArray(orderMetadata.value.customer.addresses)) {
            orderMetadata.value.customer.addresses = [];
        }
        orderMetadata.value.customer.addresses.push(completeAddress);
    }

    isRoutingValid.value = true;
    isRoutingProcessOpen.value = false;
    emit('routing-valid', true);
    emit('routing-meta', orderMetadata.value);
};

const isLoyaltyGiftActive = computed(() => page.props.companySettings?.loyalty_is_active && page.props.companySettings?.loyalty_reward_type === 'product');

const customerPoints = computed(() => {
    const metaCustomer = orderMetadata.value?.customer;
    if (!metaCustomer || !metaCustomer.id) return 0;
    const fresh = props.customers.find(c => c.id === metaCustomer.id);
    return fresh ? fresh.loyalty_points : 0;
});

const toggleGiftFilter = () => {
    emit('toggle-gift-filter', { active: !props.isGiftFilterActive, points: customerPoints.value });
};

watch(customerPoints, () => {
    if (props.isGiftFilterActive) {
        emit('toggle-gift-filter', { active: false, points: 0 });
    }
});

watch(isAppending, (appending) => {
    if (appending) {
        isRoutingProcessOpen.value = false;
    }
}, { immediate: true });

const headerTitle = computed(() => {
    const appendPrefix = isAppending.value ? `[Reforço #${targetOrderId.value}] ` : '';

    if (props.orderType === 'table') {
        const table = orderMetadata.value?.table;
        if (!table || !table.name) {
            return `${appendPrefix}Selecionar Mesa...`;
        }

        const tableName = String(table.name || table.number || '');
        const identifier = String(orderMetadata.value?.identifier || props.editOrderData?.customer_name || '');
        const isGenericName = !identifier || identifier.toLowerCase() === tableName.toLowerCase() || identifier.toLowerCase() === `mesa ${tableName.toLowerCase()}` || identifier.toLowerCase() === 'geral' || identifier.toLowerCase() === 'nova conta';
        return `${appendPrefix}${isGenericName ? `Mesa ${tableName}` : `Mesa ${tableName} (${identifier})`}`;
    }
    if (props.orderType === 'counter') {
        const identifier = orderMetadata.value?.identifier || orderMetadata.value?.customer?.name || props.editOrderData?.customer_name;
        return `${appendPrefix}${identifier ? abbreviateName(identifier) : 'Balcão'}`;
    }
    if (props.orderType === 'delivery') {
        const identifier = orderMetadata.value?.identifier || orderMetadata.value?.customer?.name || props.editOrderData?.customer_name;
        return `${appendPrefix}${identifier ? abbreviateName(identifier) : 'Delivery'}`;
    }
    return 'Selecionar Destino...';
});

const headerSubtitle = computed(() => {
    if (isAppending.value) return 'Adicionando produtos ao pedido já em andamento';
    if (props.orderType === 'table') {
        const table = orderMetadata.value?.table;
        return table?.name ? `Mesa ${table.name || table.number}` : 'Nenhuma mesa selecionada';
    }
    if (props.orderType === 'counter') return 'Balcão / Retirada';
    if (props.orderType === 'delivery') {
        const addr = orderMetadata.value?.address;
        if (!addr) return 'Aguardando seleção de endereço...';
        return addr.full_address || `${addr.street || ''}, ${addr.number || 'S/N'}`;
    }
    return 'Selecione o destino do pedido...';
});

const { activeCoupon, applyCoupon, removeCoupon } = useCoupons();

const companySettings = computed(() => page.props.companySettings || {});
const hasServiceTaxGlobal = computed(() => Boolean(companySettings.value.has_service_tax));
const serviceTaxPercentage = computed(() => Number(companySettings.value.service_tax_percentage || 0));

const isTaxEnabledForChannel = computed(() => {
    if (!hasServiceTaxGlobal.value) return false;
    const type = props.orderType || orderMetadata.value?.type;
    if (type === 'table') return companySettings.value.service_tax_table !== false && companySettings.value.service_tax_table !== 0;
    if (type === 'counter' || type === 'takeaway' || type === 'pickup') return Boolean(companySettings.value.service_tax_counter);
    if (type === 'delivery') return Boolean(companySettings.value.service_tax_delivery);
    return false;
});

const deliveryFee = computed(() => {
    if (isAppending.value) return 0;
    return props.orderType === 'delivery' && orderMetadata.value?.address?.delivery_fee
        ? Number(orderMetadata.value.address.delivery_fee)
        : 0;
});

const serviceFee = computed(() => {
    if (!isTaxEnabledForChannel.value) return 0;
    return Math.round((props.cartSubtotal * (serviceTaxPercentage.value / 100)) * 100) / 100;
});

const activeDiscount = computed(() => {
    if (!activeCoupon.value) return 0;
    if (activeCoupon.value.type === 'fixed_total') {
        const totalBruto = props.cartSubtotal + deliveryFee.value + serviceFee.value;
        const descontoAritmetico = totalBruto - Number(activeCoupon.value.value || 0);
        return descontoAritmetico > 0 ? descontoAritmetico : 0;
    }
    return Number(activeCoupon.value.discount || activeCoupon.value.discount_amount || activeCoupon.value.value || 0);
});

const appliedCouponName = computed(() => activeCoupon.value ? (activeCoupon.value.code || activeCoupon.value.name || null) : null);
const finalTotal = computed(() => Math.max(0, props.cartSubtotal + (deliveryFee.value || 0) + (serviceFee.value || 0) - activeDiscount.value));

onMounted(() => {
    const magicCoupon = localStorage.getItem('pos_magic_coupon');
    if (magicCoupon) {
        const unwatch = watch(() => props.cartSubtotal, (newTotal) => {
            if (newTotal > 0 && !activeCoupon.value) {
                applyCoupon(magicCoupon, newTotal).then((success) => {
                    if (success) {
                        localStorage.removeItem('pos_magic_coupon');
                        unwatch();
                    }
                });
            }
        }, { immediate: true });
    }
});

const setExpanded = (item, index, state) => { expandedStates.value[item.cartId || index] = state; };
const isLineHidden = (index) => {
    const nextItem = props.cart[index + 1];
    return expandedStates.value[props.cart[index].cartId || index] || (nextItem && expandedStates.value[nextItem.cartId || index + 1]);
};

const handleOrderTypeUpdate = (newType) => {
    if (newType !== props.orderType) {
        emit('update:orderType', newType);
        if (!isAppending.value) {
            isRoutingProcessOpen.value = true;
        }
        if (newType !== null) showEstimatorConfig.value = false;
    }
};

const handleRoutingChange = (payload) => {
    isRoutingValid.value = isAppending.value ? true : payload.isValid;

    // 🚀 Se a chave 'customer' veio explicitamente na meta (mesmo que seja null para limpar), respeita o que veio!
    const resolvedCustomer = (payload.meta && 'customer' in payload.meta)
        ? payload.meta.customer
        : (orderMetadata.value?.customer
            || props.editOrderData?.customer
            || (props.editOrderData?.customer_name ? { name: props.editOrderData.customer_name } : null));

    // 🚀 Se a chave 'identifier' veio na meta, respeita (mesmo que seja string vazia)
    const resolvedIdentifier = (payload.meta && 'identifier' in payload.meta)
        ? payload.meta.identifier
        : (orderMetadata.value?.identifier
            || props.editOrderData?.customer_name
            || (typeof resolvedCustomer === 'string' ? resolvedCustomer : resolvedCustomer?.name)
            || '');

    // 🚀 Se a chave 'address' veio na meta (mesmo que seja null), respeita a limpeza
    const resolvedAddress = (payload.meta && 'address' in payload.meta)
        ? payload.meta.address
        : (orderMetadata.value?.address
            || props.editOrderData?.address
            || null);

    orderMetadata.value = {
        ...orderMetadata.value,
        ...payload.meta,
        customer: resolvedCustomer,
        identifier: resolvedIdentifier,
        address: resolvedAddress,
        existing_order_id: orderMetadata.value?.existing_order_id || props.editOrderData?.id || null,
        order_id: orderMetadata.value?.order_id || props.editOrderData?.id || null
    };

    emit('routing-valid', isRoutingValid.value);
    emit('routing-meta', orderMetadata.value);
};

const handleRoutingApply = () => {
    isRoutingProcessOpen.value = false;
};

const toggleEstimator = () => {
    showEstimatorConfig.value = !showEstimatorConfig.value;
    if (showEstimatorConfig.value) isRoutingProcessOpen.value = false;
};

const promptSubAccountName = () => {
    const identifier = window.prompt("Digite o nome do cliente para esta sub-conta (Ex: João):");
    if (identifier && identifier.trim() !== "") {
        orderMetadata.value.identifier = identifier.trim();
        emit('routing-meta', orderMetadata.value);
    }
};

const handleLoadOrder = (payload) => {
    const targetId = typeof payload === 'object' ? payload?.id : payload;
    if (targetId) {
        orderMetadata.value = {
            ...orderMetadata.value,
            existing_order_id: targetId,
            order_id: targetId
        };
        isRoutingProcessOpen.value = false;
        isRoutingValid.value = true;
        emit('routing-meta', orderMetadata.value);
    }
};

const handleParkOrder = () => { emit('park-order'); };

const handleClearCartAction = () => {
    if (orderMetadata.value) {
        orderMetadata.value.existing_order_id = null;
        orderMetadata.value.order_id = null;
    }
    emit('clear-cart');
};

const canSubmit = computed(() => {
    if (props.cart.length === 0) return false;

    // Se estiver adicionando itens:
    if (isAppending.value) {
        // Bloqueia se o valor dos novos itens estourar a nota informada para troco
        if (changeAnalysis.value?.isExceeded) {
            return false;
        }
        return true;
    }

    if (!isRoutingValid.value) return false;
    if (props.orderType === 'table' && !orderMetadata.value?.table?.id) return false;
    return true;
});

const processCheckout = () => {
    if (!canSubmit.value) return;

    if (isAppending.value && changeAnalysis.value?.isExceeded) {
        toast.add({
            severity: 'error',
            summary: 'Troco Excedido',
            detail: changeAnalysis.value.warning,
            life: 5000
        });
        return;
    }

    const finalTotals = {
        subtotal: props.cartSubtotal,
        deliveryFee: deliveryFee.value,
        serviceFee: serviceFee.value,
        discount: activeDiscount.value,
        finalTotal: finalTotal.value
    };

    if (activeCoupon.value) {
        finalTotals.coupon_code = activeCoupon.value.code;
    }

    const appendId = targetOrderId.value;

    emit('checkout', {
        order_id: appendId,
        append_to_order: appendId,
        meta: {
            ...orderMetadata.value,
            order_id: appendId,
            existing_order_id: appendId,
            type: props.orderType || orderMetadata.value?.type || 'table'
        },
        totals: finalTotals
    });
};
</script>
