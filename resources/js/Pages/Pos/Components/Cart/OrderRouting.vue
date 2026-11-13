<template>
    <div class="flex flex-col gap-3">
        <!-- SELETOR DE CANAIS EM PÍLULAS EXPANSÍVEIS -->
        <div class="flex items-center justify-center gap-1.5 md:gap-2 w-full">
            <button
                v-for="option in filteredOrderTypeOptions"
                :key="option.value"
                type="button"
                @click="handleTabChange(option.value)"
                class="group flex items-center justify-center rounded-full transition-all duration-300 outline-none cursor-pointer overflow-hidden border-none select-none py-2.5"
                :class="[
                    localOrderType === option.value
                        ? 'bg-primary-500 text-white z-10 shadow-sm px-4'
                        : 'bg-primary-500/15 text-surface-text hover:bg-primary-500/25',
                    isCompactMode && localOrderType !== option.value
                        ? 'px-3 hover:px-4'
                        : 'px-4',
                    !isCompactMode ? 'flex-1' : ''
                ]"
                :title="option.label"
            >
                <i :class="[
                    'pi text-base md:text-lg shrink-0 transition-transform duration-300',
                    option.icon || (option.value === 'table' ? 'pi-th-large' : option.value === 'pickup' ? 'pi-shopping-bag' : option.value === 'counter' ? 'pi-bolt' : 'pi-truck'),
                    localOrderType === option.value ? 'scale-110' : 'group-hover:scale-110'
                ]"></i>

                <span
                    class="font-black text-[10px] md:text-xs uppercase tracking-widest whitespace-nowrap transition-all duration-300 ease-in-out overflow-hidden"
                    :class="[
                        isCompactMode && localOrderType !== option.value
                            ? 'max-w-0 opacity-0 group-hover:max-w-32 group-hover:opacity-100 group-hover:ml-2'
                            : 'max-w-32 opacity-100 ml-2'
                    ]"
                >
                    {{ option.label }}
                </span>
            </button>
        </div>

        <!-- CONTEÚDO ESPECÍFICO DO CANAL SELECIONADO -->
        <div v-show="isOpen" class="mt-4 transition-all duration-300">
            <RoutingTable
                v-if="localOrderType === 'table'"
                :model-value="selectedTable"
                @update:model-value="setSelectedTable"
                :identifier="tableIdentifier"
                @update:identifier="setTableIdentifier"
                :table-areas="tableAreas"
                :reset-trigger="resetTableTrigger"
                @apply="handleApply"
                @load-order="$emit('load-order', $event)"
            />

            <RoutingPickup
                v-if="localOrderType === 'pickup'"
                :model-value="sharedCustomer"
                @update:model-value="setSharedCustomer"
                :customer="sharedCustomer"
                @update:customer="setSharedCustomer"
                :customers="customers"
                @apply="handleApply($event)"
            />

            <RoutingCounter
                v-if="localOrderType === 'counter'"
                :model-value="sharedCustomer"
                @update:model-value="setSharedCustomer"
                :customer="sharedCustomer"
                @update:customer="setSharedCustomer"
                :customers="customers"
                @apply="triggerImmediateApply"
            />

            <RoutingDelivery
                v-if="localOrderType === 'delivery'"
                :customer="sharedCustomer"
                @update:customer="handleCustomerChange"
                :address="deliveryAddress"
                @update:address="handleAddressChange"
                :customers="customers"
                @apply="handleApply($event)"
                @open-new-address="$emit('open-new-address')"
                @open-edit-address="$emit('open-edit-address', $event)"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, unref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

import RoutingTable from "@/Pages/Pos/Components/Cart/RoutingTable.vue";
import RoutingPickup from "@/Pages/Pos/Components/Cart/RoutingPickup.vue";
import RoutingCounter from "@/Pages/Pos/Components/Cart/RoutingCounter.vue";
import RoutingDelivery from "@/Pages/Pos/Components/Cart/RoutingDelivery.vue";

import { useOrderRouting } from "@/Pages/Shared/Composables/useOrderRouting";
import { useCart } from "@/Pages/Shared/Composables/useCart";

const props = defineProps({
    modelValue: { type: String, default: 'table' },
    isOpen: { type: Boolean, default: false },
    tableAreas: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    isWaiterMode: { type: Boolean, default: false },
    isTeamMode: { type: Boolean, default: false },
    routingMeta: { type: Object, default: null }
});

const emit = defineEmits([
    "update:modelValue",
    "routing-change",
    "apply",
    "load-order",
    "open-new-address",
    "open-edit-address",
    "toggle-open",
    "open"
]);

const page = usePage();
const companySettings = computed(() => page.props.companySettings || {});

const {
    localOrderType, resetTableTrigger, orderTypeOptions, selectedTable, tableIdentifier,
    sharedCustomer, deliveryAddress, selectOrderType, handleApply,
    triggerImmediateApply,
    setSelectedTable, setTableIdentifier, setSharedCustomer, setDeliveryAddress
} = useOrderRouting(props, emit);

const { draftRouting } = useCart();

// PosApp: 4 canais. TeamApp (Garçom): Mesa e Retirada.
const allowedChannels = computed(() => {
    if (!props.isTeamMode && !props.isWaiterMode) {
        return ['table', 'delivery', 'pickup', 'counter'];
    }
    return ['table', 'pickup'];
});

const filteredOrderTypeOptions = computed(() => {
    const all = unref(orderTypeOptions) || [];
    return all.filter(opt => allowedChannels.value.includes(opt.value));
});

const isCompactMode = computed(() => filteredOrderTypeOptions.value.length > 2);

watch(() => props.routingMeta, (newMeta) => {
    if (newMeta) {
        if (newMeta.table !== undefined) setSelectedTable(newMeta.table);
        if (newMeta.identifier !== undefined) setTableIdentifier(newMeta.identifier);
        if (newMeta.customer !== undefined) setSharedCustomer(newMeta.customer);
        if (newMeta.address !== undefined) setDeliveryAddress(newMeta.address);
        if (newMeta.type && newMeta.type !== localOrderType.value) selectOrderType(newMeta.type);
    }
}, { deep: true, immediate: true });

watch(() => props.modelValue, (newVal) => {
    if (newVal && newVal !== localOrderType.value) {
        selectOrderType(newVal);
    }
});

const handleCustomerChange = (newCustomer) => {
    setSharedCustomer(newCustomer);
    if (!newCustomer && draftRouting.value) {
        draftRouting.value.customer = null;
    }
};

const handleAddressChange = (newAddress) => {
    setDeliveryAddress(newAddress);
    if (!newAddress && draftRouting.value) {
        draftRouting.value.address = null;
    }
};

const handleTabChange = (type) => {
    if (type === localOrderType.value) {
        emit('toggle-open');
        return;
    }

    if (type !== 'delivery') {
        setDeliveryAddress(null);
        if (draftRouting.value) draftRouting.value.address = null;
    }

    if (type !== 'table') {
        setSelectedTable(null);
        if (draftRouting.value) draftRouting.value.table = null;
    }

    selectOrderType(type);
    localOrderType.value = type;

    if (draftRouting.value) {
        draftRouting.value.type = type;
    }

    emit('update:modelValue', type);
    if (type === 'counter') {
        handleApply();
    } else {
        emit('open');
    }
};

watch(allowedChannels, (channels) => {
    if (props.isTeamMode || props.isWaiterMode) {
        if (channels && channels.length > 0 && !channels.includes(localOrderType.value)) {
            handleTabChange(channels[0]);
        }
    }
}, { immediate: true });
</script>