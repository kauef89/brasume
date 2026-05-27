<template>
    <div class="flex flex-col gap-3">
        <SelectButton
            v-model="localOrderType"
            :options="orderTypeOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full custom-select-btn text-sm"
        />

        <div
            v-if="localOrderType === 'table'"
            class="flex flex-col gap-3 mt-1 h-[200px] overflow-y-auto pr-1 hide-scrollbar"
        >
            <div
                v-for="area in tableAreas"
                :key="area.id"
                class="flex flex-col gap-2"
            >
                <h3
                    class="text-[11px] font-black text-gray-400 uppercase tracking-widest"
                >
                    {{ area.name }}
                </h3>
                <div class="grid grid-cols-4 gap-2">
                    <div
                        v-for="table in area.tables"
                        :key="table.id"
                        @click="handleTableClick(table)"
                        class="relative p-2 rounded-xl border-2 transition-all cursor-pointer flex flex-col items-center justify-center text-center h-14 shadow-sm hover:border-blue-400"
                        :class="
                            selectedTable?.id === table.id
                                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 ring-2 ring-blue-500/20'
                                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800'
                        "
                    >
                        <span
                            class="font-bold text-gray-800 dark:text-gray-100 text-lg leading-none"
                            >{{ table.name }}</span
                        >
                        <div
                            v-if="table.status === 'occupied'"
                            class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-orange-500 shadow-sm"
                            title="Mesa Ocupada"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="localOrderType === 'counter'"
            class="flex flex-col gap-1 mt-1"
        >
            <CustomerSearch
                v-model="counterSearch"
                :customers="customers"
                mode="counter"
                @auto-apply="triggerImmediateApply"
            />
        </div>

        <div
            v-if="localOrderType === 'delivery'"
            class="flex flex-col gap-2 mt-1"
        >
            <div class="flex gap-2">
                <div class="flex-1">
                    <CustomerSearch
                        v-model="deliveryCustomer"
                        :customers="customers"
                        mode="delivery"
                        @create-new="handleCreateNewCustomer"
                    />
                </div>
                <Button
                    icon="pi pi-user-plus"
                    severity="secondary"
                    variant="outlined"
                    title="Novo Cliente"
                    @click="handleCreateNewCustomer('')"
                />
            </div>

            <div v-if="deliveryCustomer" class="flex flex-col gap-2 mt-2">
                <h3
                    class="text-[11px] font-black text-gray-400 uppercase tracking-widest"
                >
                    Endereço de Entrega
                </h3>

                <div
                    v-if="
                        deliveryCustomer.addresses &&
                        deliveryCustomer.addresses.length > 0
                    "
                    class="flex flex-col gap-2 max-h-[220px] overflow-y-auto pr-1 hide-scrollbar"
                >
                    <div
                        v-for="addr in deliveryCustomer.addresses"
                        :key="addr.id"
                        @click="handleAddressClick(addr)"
                        class="p-3 rounded-xl border-2 transition-all cursor-pointer flex justify-between items-center shadow-sm group"
                        :class="
                            deliveryAddress?.id === addr.id
                                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 ring-2 ring-blue-500/20'
                                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-blue-400'
                        "
                    >
                        <div class="flex flex-col pr-4">
                            <span
                                class="font-bold text-gray-800 dark:text-gray-100 text-sm leading-tight"
                                >{{ addr.full_address }}</span
                            >
                            <span
                                class="text-xs font-bold mt-1 tracking-wide uppercase"
                                :class="
                                    addr.delivery_fee === 0
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-orange-600 dark:text-orange-400'
                                "
                            >
                                Taxa:
                                {{
                                    addr.delivery_fee === 0
                                        ? "Grátis"
                                        : formatCurrency(addr.delivery_fee)
                                }}
                            </span>
                        </div>

                        <div
                            class="shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors"
                            :class="
                                deliveryAddress?.id === addr.id
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-gray-300 dark:border-gray-600 group-hover:border-blue-400'
                            "
                        >
                            <i
                                v-if="deliveryAddress?.id === addr.id"
                                class="pi pi-check text-[10px] font-black"
                            ></i>
                        </div>
                    </div>

                    <Button
                        label="Adicionar Novo Endereço"
                        icon="pi pi-plus"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="mt-1"
                        @click="alertCreate('Endereço')"
                    />
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center p-5 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-800/50"
                >
                    <span class="text-xs text-gray-500 mb-3 text-center"
                        >Nenhum endereço cadastrado.</span
                    >
                    <Button
                        label="Criar Endereço"
                        icon="pi pi-map-marker"
                        size="small"
                        @click="alertCreate('Endereço')"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, defineProps, defineEmits } from "vue";
import SelectButton from "primevue/selectbutton";
import Button from "primevue/button";
import CustomerSearch from "./CustomerSearch.vue";
import { formatCurrency } from "@/Utils/formatters"; // 🚀

const props = defineProps({
    modelValue: String,
    tableAreas: Array,
    customers: Array,
});
const emit = defineEmits(["update:modelValue", "routing-change", "apply"]);

const localOrderType = ref(props.modelValue);
const orderTypeOptions = ref([
    { label: "Mesa", value: "table" },
    { label: "Balcão", value: "counter" },
    { label: "Delivery", value: "delivery" },
]);

const selectedTable = ref(null);
const counterSearch = ref(null);
const deliveryCustomer = ref(null);
const deliveryAddress = ref(null);

const isLocalValid = computed(() => {
    if (localOrderType.value === "table") return !!selectedTable.value;
    if (localOrderType.value === "counter") {
        return (
            !!counterSearch.value &&
            (typeof counterSearch.value === "string"
                ? counterSearch.value.trim().length > 0
                : true)
        );
    }
    if (localOrderType.value === "delivery")
        return !!deliveryCustomer.value && !!deliveryAddress.value;
    return false;
});

watch(localOrderType, (newVal) => emit("update:modelValue", newVal));
watch(deliveryCustomer, () => {
    deliveryAddress.value = null;
});

const updateParentState = () => {
    let fee = 0;
    let meta = { type: localOrderType.value };

    if (localOrderType.value === "table" && selectedTable.value)
        meta.table = selectedTable.value;
    else if (localOrderType.value === "counter" && counterSearch.value) {
        if (typeof counterSearch.value === "string")
            meta.identifier = counterSearch.value;
        else {
            meta.customer = counterSearch.value;
            meta.identifier = counterSearch.value.name;
        }
    } else if (
        localOrderType.value === "delivery" &&
        deliveryCustomer.value &&
        deliveryAddress.value
    ) {
        fee = deliveryAddress.value.delivery_fee;
        meta.customer = deliveryCustomer.value;
        meta.address = deliveryAddress.value;
    }

    emit("routing-change", {
        isValid: isLocalValid.value,
        deliveryFee: fee,
        meta,
    });
};

watch(
    [
        localOrderType,
        selectedTable,
        counterSearch,
        deliveryCustomer,
        deliveryAddress,
    ],
    updateParentState,
    { deep: true },
);

const handleTableClick = (table) => {
    selectedTable.value = table;
    updateParentState();
    emit("apply");
};
const handleAddressClick = (addr) => {
    deliveryAddress.value = addr;
    updateParentState();
    emit("apply");
};
const triggerImmediateApply = () => {
    updateParentState();
    if (isLocalValid.value) emit("apply");
};

const handleCreateNewCustomer = (query) =>
    alert(`Lógica para modal de Novo Cliente. Nome sugerido: ${query}`);
const alertCreate = (type) => alert(`Modal de Novo ${type} será aberto.`);
</script>

<style>
.custom-select-btn {
    display: flex;
}
.custom-select-btn .p-togglebutton {
    flex: 1;
    justify-content: center;
}
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
