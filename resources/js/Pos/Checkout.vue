<template>
    <div
        class="flex h-screen w-screen bg-gray-50 dark:bg-gray-950 overflow-hidden font-sans text-gray-900 dark:text-gray-100"
    >
        <div
            class="w-3/5 flex flex-col h-full border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900"
        >
            <div
                class="p-6 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center bg-white dark:bg-gray-900 z-10 shadow-sm"
            >
                <div class="flex items-center gap-4">
                    <Button
                        icon="pi pi-arrow-left"
                        variant="text"
                        rounded
                        severity="secondary"
                        @click="goBack"
                    />
                    <div>
                        <h1
                            class="text-2xl font-black leading-tight text-gray-800 dark:text-white"
                        >
                            Pagamento #{{ order.id }}
                        </h1>
                        <span
                            class="text-sm font-bold text-gray-500 uppercase tracking-widest"
                            >{{ order.customer_name }}</span
                        >
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <label
                        class="flex items-center gap-2 cursor-pointer bg-gray-100 dark:bg-gray-800 px-4 py-1.5 rounded-lg transition-all hover:bg-gray-200 dark:hover:bg-gray-700"
                    >
                        <div
                            class="flex items-center justify-center w-4 h-4 rounded border transition-colors"
                            :class="
                                isAllSelected
                                    ? 'bg-blue-600 border-blue-600 text-white'
                                    : 'bg-white dark:bg-gray-900 border-gray-400'
                            "
                        >
                            <i
                                v-if="isAllSelected"
                                class="pi pi-check text-[10px] font-bold"
                            ></i>
                        </div>
                        <span
                            class="text-sm font-bold text-gray-700 dark:text-gray-300 select-none"
                            >{{ isAllSelected ? "Todos" : "Nenhum" }}</span
                        >
                        <input
                            type="checkbox"
                            class="hidden"
                            :checked="isAllSelected"
                            @change="toggleAllSelections"
                        />
                    </label>

                    <div
                        class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg"
                    >
                        <button
                            @click="isGrouped = true"
                            class="px-4 py-1.5 rounded-md text-sm font-bold transition-all"
                            :class="
                                isGrouped
                                    ? 'bg-white dark:bg-gray-700 shadow text-blue-600'
                                    : 'text-gray-500'
                            "
                        >
                            Agrupado
                        </button>
                        <button
                            @click="isGrouped = false"
                            class="px-4 py-1.5 rounded-md text-sm font-bold transition-all"
                            :class="
                                !isGrouped
                                    ? 'bg-white dark:bg-gray-700 shadow text-purple-600'
                                    : 'text-gray-500'
                            "
                        >
                            Separado
                        </button>
                    </div>
                </div>
            </div>

            <CheckoutItemsList
                class="w-full xl:flex-1 shrink-0"
                :display-items="displayItems"
                :order-payments="order.payments"
                @increment="increment"
                @decrement="decrement"
                @divide-item="handleDivideItem"
                @toggle-select="handleToggleSelect"
                @quick-add="handleQuickAdd"
                @quick-cancel="handleQuickCancel"
            />
        </div>

        <CheckoutPostPaymentModal
            :visible="isPostPaymentModalVisible"
            @action="handlePostPaymentAction"
        />

        <div
            class="w-2/5 flex flex-col h-full bg-gray-100 dark:bg-gray-950 relative"
        >
            <CheckoutPaymentPanel
                class="w-full xl:w-[450px] shrink-0 border-l border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900"
                :order-subtotal="orderTotalItemsValue"
                :delivery-fee="deliveryFee"
                :service-fee-amount="serviceFeeAmount"
                :dynamic-total="dynamicOrderTotal"
                :total-paid="
                    props.order.payments.reduce((sum, p) => sum + p.amount, 0)
                "
                :pending-to-stage="pendingToStage"
                :change-amount="changeAmount"
                :payment-methods="paymentMethods"
                :company-settings="companySettings"
                :staged-payments="stagedPayments"
                v-model:is-service-tax-enabled="isServiceTaxEnabled"
                v-model:current-amount="currentPaymentAmount"
                @divide="divideBy"
                @set-remaining="setAmountToRemaining"
                @add-payment="handleAddPayment"
                @remove-payment="handleRemovePayment"
            />
            <div
                class="p-6 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 shrink-0 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.05)]"
            >
                <Button
                    label="Confirmar Recebimento"
                    icon="pi pi-check-circle"
                    class="w-full h-16 text-xl font-black shadow-xl"
                    :severity="canSubmit ? 'success' : 'secondary'"
                    :disabled="!canSubmit"
                    @click="processPayment"
                />
            </div>
        </div>
    </div>

    <ManagerAuthModal
        v-model:visible="isAuthModalVisible"
        :item-name="itemToProcess?.name"
        @confirm="confirmCancellation"
    />
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "../Layouts/PosLayout.vue";
import Button from "primevue/button";
import { useToast } from "primevue/usetoast"; // 🚀 Import do Toast

import CheckoutItemsList from "./Components/Checkout/CheckoutItemsList.vue";
import CheckoutPaymentPanel from "./Components/Checkout/CheckoutPaymentPanel.vue";
import CheckoutPostPaymentModal from "./Components/Checkout/CheckoutPostPaymentModal.vue";
import ManagerAuthModal from "@/Components/ManagerAuthModal.vue";

import { useCheckoutMath } from "@/Composables/useCheckoutMath";
import { useOrderFinancials } from "@/Composables/useOrderFinancials"; // 🚀 Novo Composable
import { formatCurrency } from "@/Utils/formatters";

defineOptions({ layout: AppLayout });
const props = defineProps({
    order: Object,
    paymentMethods: Array,
    companySettings: Object,
});

const toast = useToast(); // 🚀 Inicializa o Toast

const isGrouped = ref(true);
const currentPaymentAmount = ref(0);
const isServiceTaxEnabled = ref(
    props.companySettings?.has_service_tax || false,
);
const isPostPaymentModalVisible = ref(false);

const stagedPayments = ref([]);

const {
    reactiveItems,
    isAllSelected,
    displayItems,
    toggleAllSelections,
    handleDivideItem,
    handleToggleSelect,
    increment,
    decrement,
} = useCheckoutMath(props.order.items, isGrouped);

// MATEMÁTICA PROPORCIONAL
const orderTotalItemsValue = computed(() =>
    reactiveItems.value
        .filter((i) => !i.parent_item_id && !i.is_combo_item)
        .reduce(
            (sum, item) => sum + item.effective_unit_price * item.base_qty,
            0,
        ),
);

const selectedItemsValue = computed(() =>
    reactiveItems.value
        .filter((i) => !i.parent_item_id && !i.is_combo_item)
        .reduce(
            (sum, item) =>
                sum +
                item.selected_parts *
                    (item.effective_unit_price / item.parts_per_unit),
            0,
        ),
);

const selectionRatio = computed(() =>
    orderTotalItemsValue.value === 0
        ? 0
        : selectedItemsValue.value / orderTotalItemsValue.value,
);

// 🚀 USANDO O NOVO COMPOSABLE PARA OS CÁLCULOS TOTAIS
const {
    deliveryFee,
    discount,
    serviceFeeAmount,
    dynamicOrderTotal,
    feesAndDiscounts,
} = useOrderFinancials(
    props.order,
    orderTotalItemsValue,
    isServiceTaxEnabled,
    props.companySettings,
);

const intendedPaymentTotal = computed(
    () =>
        selectedItemsValue.value +
        feesAndDiscounts.value * selectionRatio.value,
);

const stagedTotal = computed(() =>
    stagedPayments.value.reduce((sum, p) => sum + p.amount, 0),
);
const pendingToStage = computed(() =>
    Math.max(0, intendedPaymentTotal.value - stagedTotal.value),
);
const changeAmount = computed(() =>
    Math.max(0, stagedTotal.value - intendedPaymentTotal.value),
);

watch(
    pendingToStage,
    (newVal) => {
        if (newVal > 0)
            currentPaymentAmount.value = parseFloat(newVal.toFixed(2));
        else currentPaymentAmount.value = 0;
    },
    { immediate: true },
);

const handleAddPayment = (method) => {
    let val = parseFloat(currentPaymentAmount.value);
    if (isNaN(val) || val <= 0) return;

    if (method.engine_type !== "money" && val > pendingToStage.value) {
        val = pendingToStage.value;
        // 🚀 Substituindo alert por Toast (Warning)
        toast.add({
            severity: "warn",
            summary: "Atenção",
            detail: `Como não é dinheiro, o valor foi ajustado para o limite de ${formatCurrency(val)}`,
            life: 4000,
        });
    }

    stagedPayments.value.push({
        id: Date.now() + Math.random(),
        method_id: method.id,
        name: method.name,
        engine_type: method.engine_type,
        amount: val,
    });
};

const handleRemovePayment = (id) => {
    stagedPayments.value = stagedPayments.value.filter((p) => p.id !== id);
};
const canSubmit = computed(
    () =>
        intendedPaymentTotal.value > 0 &&
        stagedTotal.value >= intendedPaymentTotal.value,
);
const divideBy = (num) => {
    currentPaymentAmount.value = parseFloat(
        (pendingToStage.value / num).toFixed(2),
    );
};
const setAmountToRemaining = () => {
    currentPaymentAmount.value = pendingToStage.value;
};

// ---------------------------------------------------------
// COMUNICAÇÃO COM O BACKEND
// ---------------------------------------------------------

const handleQuickAdd = (item) => {
    const target = item._parent_ref;
    router.post(
        `/operations/orders/${props.order.id}/quick-add`,
        { item_id: target.id },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const isAuthModalVisible = ref(false);
const itemToProcess = ref(null);

const handleQuickCancel = (item) => {
    itemToProcess.value = item._parent_ref;
    isAuthModalVisible.value = true;
};

const confirmCancellation = (authData) => {
    router.post(
        `/operations/orders/${props.order.id}/cancel-item`,
        {
            item_id: itemToProcess.value.id,
            is_loss: authData.isLoss,
            reason: authData.reason,
            pin: authData.password,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                isAuthModalVisible.value = false;
                // 🚀 Substituindo alert por Toast
                toast.add({
                    severity: "success",
                    summary: "Sucesso",
                    detail: "Item estornado com sucesso!",
                    life: 3000,
                });
            },
            onError: (e) => {
                // 🚀 Substituindo alert por Toast
                toast.add({
                    severity: "error",
                    summary: "Erro",
                    detail: e.pin
                        ? `Erro de Segurança: ${e.pin}`
                        : "Ocorreu um erro interno ao estornar.",
                    life: 5000,
                });
            },
        },
    );
};

const processPayment = () => {
    const itemsToPay = reactiveItems.value
        .filter(
            (i) =>
                !i.parent_item_id && !i.is_combo_item && i.selected_parts > 0,
        )
        .map((i) => ({
            id: i.id,
            pay_qty: i.selected_parts / i.parts_per_unit,
        }));

    const paymentsToSend = stagedPayments.value.map((p) => ({
        method_id: p.method_id,
        amount: p.amount,
    }));

    router.post(
        `/operations/orders/${props.order.id}/pay`,
        {
            payments: paymentsToSend,
            items: itemsToPay,
            service_fee: serviceFeeAmount.value,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                stagedPayments.value = [];
                const totalAlreadyPaid = props.order.payments.reduce(
                    (sum, p) => sum + p.amount,
                    0,
                );
                if (dynamicOrderTotal.value - totalAlreadyPaid <= 0) {
                    isPostPaymentModalVisible.value = true;
                } else {
                    // 🚀 Substituindo alert por Toast
                    toast.add({
                        severity: "info",
                        summary: "Pagamento Parcial",
                        detail: "Pagamento parcial processado com sucesso!",
                        life: 3000,
                    });
                }
            },
        },
    );
};

const handlePostPaymentAction = ({ type, cpf }) => {
    isPostPaymentModalVisible.value = false;
    // 🚀 Aqui fará sentido um Toast informativo rápido antes do redirecionamento
    toast.add({
        severity: "success",
        summary: `Finalizado: ${type}`,
        detail: `CPF: ${cpf || "Consumidor Final"}`,
        life: 2000,
    });
    setTimeout(() => {
        router.visit("/operations");
    }, 500); // Dá tempo para o utilizador ler o toast
};

const goBack = () => router.visit("/operations");
</script>
