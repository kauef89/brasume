<template>
    <div
        class="flex h-screen w-screen bg-gray-50 dark:bg-gray-950 overflow-hidden font-sans text-gray-900 dark:text-gray-100"
    >
        <div class="flex-1 flex flex-col h-full overflow-hidden p-6 gap-6">
            <div
                class="flex justify-between items-center bg-white dark:bg-gray-900 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 shrink-0"
            >
                <div class="flex items-center gap-3 w-1/2">
                    <i class="pi pi-search text-gray-400"></i>
                    <InputText
                        v-model="searchQuery"
                        placeholder="Pesquisar produto pelo nome ou código..."
                        class="w-full p-inputtext-sm border-none bg-transparent focus:shadow-none"
                    />
                </div>
                <div
                    class="flex items-center gap-2 text-sm font-bold text-gray-500"
                >
                    <i class="pi pi-user text-xs"></i>
                    <span>{{ operatorName }}</span>
                </div>
            </div>

            <div
                class="flex gap-2 overflow-x-auto pb-2 shrink-0 hide-scrollbar"
            >
                <button
                    @click="selectedCategory = null"
                    :class="
                        !selectedCategory
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-100 dark:border-gray-800'
                    "
                    class="px-5 py-2.5 rounded-xl font-black text-sm transition-all whitespace-nowrap"
                >
                    Todos os Itens
                </button>
                <button
                    v-for="cat in initialCategories"
                    :key="cat.id"
                    @click="selectedCategory = cat.id"
                    :class="
                        selectedCategory === cat.id
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-100 dark:border-gray-800'
                    "
                    class="px-5 py-2.5 rounded-xl font-black text-sm transition-all whitespace-nowrap"
                >
                    {{ cat.name }}
                </button>
            </div>

            <div class="flex-1 overflow-y-auto pr-2 hide-scrollbar">
                <div
                    class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
                >
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="handleProductClick(product)"
                        class="bg-white dark:bg-gray-900 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] transition-all relative group overflow-hidden"
                    >
                        <div
                            v-if="product.image"
                            class="h-28 w-full rounded-xl overflow-hidden mb-3 bg-gray-50 dark:bg-gray-800"
                        >
                            <img
                                :src="product.image"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                            />
                        </div>
                        <div>
                            <h3
                                class="font-black text-base leading-tight text-gray-800 dark:text-gray-200 line-clamp-2 mb-1"
                            >
                                {{ product.name }}
                            </h3>
                            <span
                                class="text-xs font-bold text-gray-400 uppercase tracking-wider"
                                >{{ product.type }}</span
                            >
                        </div>
                        <div class="flex justify-between items-end mt-4">
                            <span
                                class="text-lg font-black text-blue-600 dark:text-blue-400"
                                >{{ formatCurrency(product.price) }}</span
                            >
                            <div
                                class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors"
                            >
                                <i class="pi pi-plus text-xs font-bold"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="filteredProducts.length === 0"
                    class="flex flex-col items-center justify-center h-64 text-gray-400"
                >
                    <i class="pi pi-box text-6xl mb-4 opacity-50"></i>
                    <p class="font-bold text-lg">Nenhum produto encontrado.</p>
                </div>
            </div>
        </div>

        <CartDrawer
            :cart="cart"
            :cart-subtotal="cartSubtotal"
            :order-type="orderType"
            :table-areas="initialTableAreas"
            :customers="initialCustomers"
            :edit-order-data="editOrderData"
            @update-qty="updateQty"
            @clear-cart="clearCart"
            @edit-item="openEditModifiers"
            @checkout="submitOrder"
            @update:orderType="orderType = $event"
        />

        <OrderEntryModalsWrapper
            v-model:isModifiersVisible="isModifiersVisible"
            v-model:isComboVisible="isComboVisible"
            :active-product="activeProduct"
            :edit-item-index="editItemIndex"
            @confirm-modifiers="handleModifiersConfirm"
            @confirm-combo="handleComboConfirm"
        />

        <DeliveryPaymentIntentModal
            v-model:visible="isDeliveryPaymentModalVisible"
            :order-total="deliveryTotalForModal"
            @confirm="handleDeliveryPaymentConfirm"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "../Layouts/PosLayout.vue";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import { useToast } from "primevue/usetoast"; // 🚀 Import do Toast

import CartDrawer from "./Components/OrderEntry/CartDrawer.vue";
import DeliveryPaymentIntentModal from "./Components/OrderEntry/DeliveryPaymentIntentModal.vue";
import OrderEntryModalsWrapper from "./Components/OrderEntry/OrderEntryModalsWrapper.vue"; // 🚀 Wrapper

import { useCart } from "@/Composables/useCart";
import { formatCurrency } from "@/Utils/formatters";

defineOptions({ layout: AppLayout });

const props = defineProps({
    operatorName: String,
    initialCategories: Array,
    initialProducts: Array,
    initialTableAreas: Array,
    initialCustomers: Array,
    editOrderData: Object,
});

const toast = useToast(); // 🚀 Inicializa o Toast

// Estado de Filtros e Pesquisa
const searchQuery = ref("");
const selectedCategory = ref(null);
const orderType = ref("table");

// Estado do Carrinho via Composable especializado
const {
    cart,
    cartSubtotal,
    addToCart,
    updateQty,
    updateItemModifiers,
    clearCart,
    loadEditOrder,
} = useCart();

// Estado dos Modais de Customização de Produtos
const isModifiersVisible = ref(false);
const isComboVisible = ref(false);
const activeProduct = ref(null);
const editItemIndex = ref(null);

// ==========================================
// 🚀 ESTADO DO MODAL INTERCEPTOR DE DELIVERY
// ==========================================
const isDeliveryPaymentModalVisible = ref(false);
const pendingCheckoutPayload = ref(null);
const deliveryTotalForModal = ref(0);

onMounted(() => {
    if (props.editOrderData) {
        loadEditOrder(props.editOrderData);
        orderType.value = props.editOrderData.type;
    }
});

const filteredProducts = computed(() => {
    if (!props.initialProducts) return [];

    const query = (searchQuery.value || "").toLowerCase();

    return props.initialProducts.filter((p) => {
        const safeName = p.name || "";
        const matchesSearch = safeName.toLowerCase().includes(query);
        const matchesCategory =
            !selectedCategory.value || p.category_id === selectedCategory.value;

        return matchesSearch && matchesCategory;
    });
});

const handleProductClick = (product) => {
    activeProduct.value = product;
    editItemIndex.value = null;

    if (product.type === "combo") {
        isComboVisible.value = true;
    } else if (product.modifiers && product.modifiers.length > 0) {
        isModifiersVisible.value = true;
    } else {
        addToCart({
            product: product,
            structuredModifiers: [],
            orderType: orderType.value,
        });
    }
};

const openEditModifiers = (item, index) => {
    const originalProduct = props.initialProducts.find((p) => p.id === item.id);
    activeProduct.value = { ...originalProduct, cartItem: item };
    editItemIndex.value = index;

    if (item.type === "combo") {
        isComboVisible.value = true;
    } else {
        isModifiersVisible.value = true;
    }
};

const handleModifiersConfirm = (configuredItem) => {
    if (editItemIndex.value !== null) {
        updateItemModifiers(editItemIndex.value, {
            text: configuredItem.text, // Manteve para consistência, se não for usar remover do payload
            extraPrice: configuredItem.extraPrice,
            isTakeaway: configuredItem.isTakeaway,
            structuredModifiers: configuredItem.structuredModifiers,
            orderType: orderType.value,
        });
    } else {
        addToCart({
            product: activeProduct.value,
            modifiersText: configuredItem.text, // Manteve para consistência, se não for usar remover do payload
            extraPrice: configuredItem.extraPrice,
            isTakeaway: configuredItem.isTakeaway,
            structuredModifiers: configuredItem.structuredModifiers,
            orderType: orderType.value,
        });
    }
    isModifiersVisible.value = false;
};

const handleComboConfirm = (configuredCombo) => {
    addToCart({
        product: activeProduct.value,
        comboItems: configuredCombo.structuredItems,
        extraPrice: configuredCombo.extraPrice,
        orderType: orderType.value,
    });
    isComboVisible.value = false;
};

const submitOrder = (checkoutData) => {
    const payload = {
        order_id: props.editOrderData?.id || null,
        items: cart.value,
        routing: checkoutData.meta,
        financials: checkoutData.totals,
    };

    if (checkoutData.meta.type === "delivery" && !payload.order_id) {
        pendingCheckoutPayload.value = payload;
        deliveryTotalForModal.value = Number(checkoutData.totals.finalTotal);
        isDeliveryPaymentModalVisible.value = true;
        return;
    }

    executeSubmit(payload);
};

const handleDeliveryPaymentConfirm = (intentData) => {
    isDeliveryPaymentModalVisible.value = false;
    const payload = pendingCheckoutPayload.value;

    payload.routing.delivery_payment_status =
        intentData.delivery_payment_status;
    payload.routing.delivery_payment_methods =
        intentData.delivery_payment_methods;
    payload.frontend_intent = intentData.intent;

    executeSubmit(payload);
};

const executeSubmit = (payload) => {
    router.post("/pos/checkout", payload, {
        preserveState: false,
        onSuccess: (page) => {
            cart.value = [];

            if (
                payload.frontend_intent === "pay_now" &&
                page.props.flash?.success_order_id
            ) {
                router.visit(
                    `/operations/orders/${page.props.flash.success_order_id}/checkout`,
                );
            } else {
                // 🚀 Substituindo alert por Toast
                toast.add({
                    severity: "success",
                    summary: "Sucesso",
                    detail: payload.order_id
                        ? "Pedido atualizado com sucesso!"
                        : "Pedido enviado para a cozinha com sucesso!",
                    life: 3000,
                });
            }
        },
        onError: () => {
            // 🚀 Substituindo alert por Toast
            toast.add({
                severity: "error",
                summary: "Erro",
                detail: "Erro ao processar o pedido. Por favor, revise as seleções do carrinho.",
                life: 5000,
            });
        },
    });
};
</script>

<style scoped>
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
