<template>
    <div
        class="w-[400px] bg-white dark:bg-gray-900 shadow-xl flex flex-col !z-[2000] shrink-0 border-l border-gray-200 dark:border-gray-800 h-full relative"
    >
        <div
            class="p-4 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center bg-gray-50 dark:bg-gray-950 min-h-[80px]"
        >
            <div
                class="flex flex-col justify-center flex-1 pr-2 overflow-hidden"
            >
                <h2
                                    class="text-xl font-bold text-gray-900 dark:text-white truncate"
                                    :title="headerTitle"
                                >
                                    {{ headerTitle }}
                                </h2>
                <div
                    v-if="headerSubtitle"
                    class="text-sm text-gray-500 truncate"
                    :title="headerSubtitle"
                >
                    {{ headerSubtitle }}
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <OrderSummaryCopier
                    v-if="
                        isRoutingValid &&
                        (orderMetadata?.type === 'delivery' ||
                            orderMetadata?.type === 'counter') &&
                        cart.length > 0
                    "
                    :cart="cart"
                    :metadata="orderMetadata"
                    :totals="{
                        subtotal: cartSubtotal,
                        deliveryFee: deliveryFee,
                        discount: discount,
                        finalTotal: finalTotal,
                    }"
                />

                <Button
                    :label="
                        isRoutingValid && !showRoutingConfig
                            ? 'Trocar'
                            : showRoutingConfig
                              ? 'Cancelar'
                              : 'Definir'
                    "
                    :icon="
                        isRoutingValid && !showRoutingConfig
                            ? 'pi pi-refresh'
                            : showRoutingConfig
                              ? 'pi pi-times'
                              : 'pi pi-map-marker'
                    "
                    size="small"
                    :severity="showRoutingConfig ? 'secondary' : 'primary'"
                    :variant="showRoutingConfig ? 'outlined' : 'filled'"
                    @click="toggleRouting"
                />

                <Button
                    icon="pi pi-trash"
                    severity="danger"
                    variant="text"
                    rounded
                    @click="$emit('clear-cart')"
                    :disabled="cart.length === 0"
                />
            </div>
        </div>

        <div
            v-show="showRoutingConfig"
            class="p-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shrink-0 shadow-inner"
        >
            <OrderRouting
                :modelValue="orderType"
                @update:modelValue="$emit('update:orderType', $event)"
                :table-areas="tableAreas"
                :customers="customers"
                @routing-change="handleRoutingChange"
                @apply="showRoutingConfig = false"
            />
        </div>

        <div
            class="flex-1 overflow-y-auto p-4 flex flex-col gap-3 bg-gray-50/50 dark:bg-gray-900/50"
        >
            <div
                v-if="cart.length === 0"
                class="text-center text-gray-400 my-auto flex flex-col items-center"
            >
                <i class="pi pi-shopping-cart text-5xl mb-4 opacity-50"></i>
                <p>Adicione produtos ao pedido</p>
            </div>

            <div
                v-for="(item, index) in cart"
                :key="item.cartId"
                class="flex flex-col gap-2 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm relative group"
            >
                <div
                    class="flex justify-between font-bold text-gray-900 dark:text-gray-100 items-start pr-8"
                >
                    <span class="truncate">
                        {{ item.name }}
                        <span
                            v-if="item.isTakeaway"
                            class="ml-1.5 text-[10px] font-black bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-400 px-2 py-0.5 rounded-full uppercase tracking-wider"
                            >Viagem</span
                        >
                    </span>
                    <span>{{ formatCurrency(item.price * item.qty) }}</span>
                </div>

                <div
                    v-if="item.isCombo && item.comboItems"
                    class="flex flex-col gap-1 text-[13px] text-gray-600 dark:text-gray-300 pr-8 font-mono tracking-tight mt-1"
                >
                    <div
                        v-for="(cItem, idx) in item.comboItems"
                        :key="idx"
                        class="flex flex-col"
                    >
                        <div class="flex items-start gap-1">
                            <span class="text-gray-400">|</span
                            ><span class="font-semibold">{{ cItem.name }}</span>
                        </div>
                        <div
                            v-if="cItem.modifiersText"
                            class="flex flex-col ml-3"
                        >
                            <div
                                v-for="(mod, mIdx) in cItem.modifiersText.split(
                                    ' • ',
                                )"
                                :key="mIdx"
                                class="flex items-start gap-1"
                            >
                                <span class="text-gray-400 font-light">||</span
                                ><span class="italic text-gray-500">{{
                                    mod.trim()
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-else-if="item.modifiersText"
                    class="text-xs text-gray-500 dark:text-gray-400 italic leading-tight pr-8 mt-1"
                >
                    {{ item.modifiersText }}
                </div>

                <div
                    class="flex justify-between items-center mt-2 pt-2 border-t border-gray-100 dark:border-gray-700/50"
                >
                    <div
                        class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-1 shadow-inner"
                    >
                        <Button
                            icon="pi pi-minus"
                            variant="text"
                            size="small"
                            class="w-7 h-7 p-0"
                            @click="$emit('update-qty', index, -1)"
                        />
                        <span
                            class="w-6 text-center font-bold text-gray-900 dark:text-white text-sm"
                            >{{ item.qty }}</span
                        >
                        <Button
                            icon="pi pi-plus"
                            variant="text"
                            size="small"
                            class="w-7 h-7 p-0"
                            @click="$emit('update-qty', index, 1)"
                        />
                    </div>
                    <Button
                        icon="pi pi-pencil"
                        severity="secondary"
                        variant="text"
                        rounded
                        size="small"
                        class="w-8 h-8 opacity-60 group-hover:opacity-100 transition-opacity"
                        title="Editar customizações"
                        @click="$emit('edit-item', item, index)"
                    />
                </div>
            </div>
        </div>

        <div
            class="p-5 bg-white dark:bg-gray-950 border-t border-gray-200 dark:border-gray-800 flex flex-col gap-3 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.05)]"
        >
            <div
                class="flex flex-col gap-1.5 text-sm text-gray-600 dark:text-gray-400 font-medium px-1"
            >
                <div class="flex justify-between items-center">
                    <span>Subtotal</span>
                    <span>{{ formatCurrency(cartSubtotal) }}</span>
                </div>

                <div
                    v-if="orderType === 'delivery'"
                    class="flex justify-between items-center"
                >
                    <span>Taxa de Entrega</span>
                    <span v-if="deliveryFee === 0" class="text-green-600"
                        >Grátis</span
                    >
                    <span v-else>{{ formatCurrency(deliveryFee) }}</span>
                </div>

                <div
                    class="flex flex-col border-t border-dashed border-gray-200 dark:border-gray-700 mt-1 pt-2"
                >
                    <div
                        class="flex justify-between items-center"
                        :class="discount > 0 ? 'text-red-500 font-bold' : ''"
                    >
                        <span>{{
                            appliedCouponName
                                ? `Descontos (Cupom ${appliedCouponName})`
                                : "Desconto"
                        }}</span>
                        <span>{{
                            discount > 0
                                ? "- " + formatCurrency(discount)
                                : formatCurrency(0)
                        }}</span>
                    </div>
                    <div class="flex mt-1">
                        <a
                            href="#"
                            @click.prevent="toggleCouponInput"
                            class="text-[11px] text-blue-500 hover:text-blue-700 uppercase font-bold tracking-wide"
                        >
                            {{
                                showCouponInput ? "Cancelar" : "Adicionar Cupom"
                            }}
                        </a>
                    </div>
                    <div
                        v-if="showCouponInput"
                        class="flex gap-2 mt-2 transition-all"
                    >
                        <InputText
                            v-model="couponCode"
                            placeholder="Insira o código..."
                            class="flex-1 p-inputtext-sm text-center uppercase"
                        />
                        <Button
                            label="Aplicar"
                            icon="pi pi-check"
                            size="small"
                            severity="secondary"
                            @click="applyCoupon"
                        />
                    </div>
                </div>
            </div>

            <div
                class="flex justify-between text-2xl font-black mt-1 mb-2 text-gray-900 dark:text-white px-1"
            >
                <span>Total</span>
                <span class="text-blue-600 dark:text-blue-400">{{
                    formatCurrency(finalTotal)
                }}</span>
            </div>

            <Button
                label="Enviar Para a Cozinha"
                icon="pi pi-send"
                size="large"
                class="w-full h-14 text-lg"
                :disabled="!canSubmit"
                @click="processCheckout"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import OrderRouting from "./OrderRouting.vue";
import OrderSummaryCopier from "../Checkout/OrderSummaryCopier.vue";

// 🚀 IMPORTAÇÃO DOS NOSSOS FORMATADORES PADRÃO-OURO
import { formatCurrency, formatName } from "@/Utils/formatters";

const props = defineProps({
    operatorName: String,
    cart: Array,
    cartSubtotal: Number,
    orderType: String,
    tableAreas: Array,
    customers: Array,
    editOrderData: Object,
});

const emit = defineEmits([
    "update-qty",
    "clear-cart",
    "checkout",
    "edit-item",
    "update:orderType",
]);

onMounted(() => {
    if (props.editOrderData) {
        emit("update:orderType", props.editOrderData.type);
        orderMetadata.value = {
            id: props.editOrderData.id,
            type: props.editOrderData.type,
            customer: props.editOrderData.customer,
            address: props.editOrderData.address,
            identifier: props.editOrderData.customer_name,
            table: { name: props.editOrderData.table_number },
        };
        deliveryFee.value = Number(props.editOrderData.delivery_fee || 0);
        isRoutingValid.value = true;
        showRoutingConfig.value = false;
    }
});

const showRoutingConfig = ref(true);
const isRoutingValid = ref(false);
const deliveryFee = ref(0);
const orderMetadata = ref({});

const discount = ref(0);
const couponCode = ref("");
const showCouponInput = ref(false);
const appliedCouponName = ref(null);

const headerTitle = computed(() => {
    if (
        !isRoutingValid.value ||
        !orderMetadata.value ||
        showRoutingConfig.value
    )
        return "Pedido Atual";

    if (orderMetadata.value.type === "table")
        return `Pedido Mesa ${orderMetadata.value.table.name}`;

    if (orderMetadata.value.type === "counter") return "Pedido Balcão";

    if (orderMetadata.value.type === "delivery")
        return formatName(orderMetadata.value.customer?.name || "Delivery"); // 🚀 Aplicação do Formatter!

    return "Pedido Atual";
});

const headerSubtitle = computed(() => {
    if (
        !isRoutingValid.value ||
        !orderMetadata.value ||
        showRoutingConfig.value
    )
        return null;

    if (orderMetadata.value.type === "counter") {
        if (orderMetadata.value.customer)
            return formatName(orderMetadata.value.customer.name); // 🚀 Aplicação do Formatter!
        if (orderMetadata.value.identifier)
            return formatName(orderMetadata.value.identifier); // 🚀 Aplicação do Formatter!
    }

    if (orderMetadata.value.type === "delivery") {
        return orderMetadata.value.address?.full_address || null;
    }

    return null;
});

const toggleRouting = () => {
    if (showRoutingConfig.value && isRoutingValid.value) {
        showRoutingConfig.value = false;
    } else {
        showRoutingConfig.value = true;
    }
};

const handleRoutingChange = (state) => {
    isRoutingValid.value = state.isValid;
    deliveryFee.value = state.deliveryFee;
    orderMetadata.value = state.meta;
};

const toggleCouponInput = () => {
    showCouponInput.value = !showCouponInput.value;
    if (!showCouponInput.value && !appliedCouponName.value)
        couponCode.value = "";
};

const applyCoupon = () => {
    if (couponCode.value.toUpperCase() === "NATIVA10") {
        discount.value = 10;
        appliedCouponName.value = couponCode.value.toUpperCase();
        showCouponInput.value = false;
    } else {
        alert("Cupom inválido ou expirado.");
        discount.value = 0;
        appliedCouponName.value = null;
    }
};

const finalTotal = computed(() => {
    let total = props.cartSubtotal;
    if (props.orderType === "delivery") total += deliveryFee.value;
    total -= discount.value;
    return total < 0 ? 0 : total;
});

const canSubmit = computed(
    () =>
        isRoutingValid.value &&
        props.cart.length > 0 &&
        !showRoutingConfig.value,
);

const processCheckout = () => {
    emit("checkout", {
        meta: orderMetadata.value,
        totals: {
            subtotal: props.cartSubtotal,
            deliveryFee: deliveryFee.value,
            discount: discount.value,
            finalTotal: finalTotal.value,
        },
    });
};
</script>

<style scoped>
.font-mono {
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        "Liberation Mono", "Courier New", monospace;
}
</style>
