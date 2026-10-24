<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-stretch justify-center gap-3 w-full">
            <button
                v-for="option in filteredOrderTypeOptions"
                :key="option.value"
                type="button"
                @click="handleTabChange(option.value)"
                class="justify-center-safe flex flex-row gap-2 p-4 rounded-full transition-all duration-300 outline-none cursor-pointer overflow-hidden border-none"
                :class="[
                    localOrderType === option.value
                        ? 'bg-primary-500/90 text-surface-text z-10 shadow-xs'
                        : 'bg-primary-500/20 text-surface-text hover:bg-primary-500/30'
                ]"
            >
                <i :class="[
                    'pi text-2xl transition-transform duration-300 relative z-10',
                    option.icon || (option.value === 'table' ? 'pi-table' : option.value === 'counter' ? 'pi-shopping-bag' : 'pi-truck'),
                ]"></i>

                <span class="font-black text-[10px] md:text-xs uppercase tracking-widest relative z-10 text-center">
                    {{ option.label }}
                </span>
            </button>
        </div>

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

            <RoutingCounter
                v-if="localOrderType === 'counter'"
                :is-team-mode="isTeamMode"
                :is-waiter-mode="isWaiterMode"
                :model-value="sharedCustomer"
                @update:model-value="setSharedCustomer"
                :customer="sharedCustomer"
                @update:customer="setSharedCustomer"
                :customers="customers"
                @apply="triggerImmediateApply"
                @create-new="handleCreateNewCustomer"
                @open-new-customer="handleCreateNewCustomer"
            />

            <RoutingDelivery
                v-if="localOrderType === 'delivery'"
                :customer="sharedCustomer"
                @update:customer="handleCustomerChange"
                :address="deliveryAddress"
                @update:address="handleAddressChange"
                :customers="customers"
                @apply="handleApply($event)"
                @create-new="handleCreateNewCustomer"
                @open-new-customer="handleCreateNewCustomer"
                @customer-selected="updateParentState"
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
    "open-new-customer",
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
    triggerImmediateApply, updateParentState,
    setSelectedTable, setTableIdentifier, setSharedCustomer, setDeliveryAddress
} = useOrderRouting(props, emit);

const { draftRouting } = useCart();

// 🚀 CANAIS PERMITIDOS:
// No POS (desktop), TODOS os canais estão sempre liberados ('table', 'counter', 'delivery').
// No TeamApp (garçom), respeita rigorosamente a configuração team_order_channels do tenant.
const allowedChannels = computed(() => {
    if (!props.isTeamMode && !props.isWaiterMode) {
        return ['table', 'counter', 'delivery'];
    }

    const channels = companySettings.value?.team_order_channels;
    if (Array.isArray(channels) && channels.length > 0) {
        return channels;
    }
    return ['table', 'counter'];
});

const filteredOrderTypeOptions = computed(() => {
    const all = unref(orderTypeOptions) || [];
    return all.filter(opt => allowedChannels.value.includes(opt.value));
});

// Sincronização direta caso o TeamApp envie atualizações via props.routingMeta
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

watch(() => draftRouting.value?.type, (newType) => {
    if (newType && newType !== localOrderType.value) {
        selectOrderType(newType);
    }
});

watch(() => draftRouting.value?.table, (newTable) => {
    if (newTable) setSelectedTable(newTable);
}, { deep: true });

watch(() => draftRouting.value?.customer, (newCustomer) => {
    if (newCustomer !== undefined) setSharedCustomer(newCustomer);
}, { deep: true });

watch(() => draftRouting.value?.address, (newAddress) => {
    if (newAddress !== undefined) setDeliveryAddress(newAddress);
}, { deep: true });

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

    if (type === 'table') {
        setSharedCustomer(null);
        setDeliveryAddress(null);
        if (draftRouting.value) {
            draftRouting.value.customer = null;
            draftRouting.value.address = null;
        }
    } else if (type === 'counter') {
        setSelectedTable(null);
        setDeliveryAddress(null);
        if (draftRouting.value) {
            draftRouting.value.table = null;
            draftRouting.value.address = null;
        }
    } else if (type === 'delivery') {
        setSelectedTable(null);
        setTableIdentifier("");
        if (draftRouting.value) {
            draftRouting.value.table = null;
            draftRouting.value.identifier = "";
        }
    }

    selectOrderType(type);
    localOrderType.value = type;

    if (draftRouting.value) {
        draftRouting.value.type = type;
    }

    emit('update:modelValue', type);
    emit('open');
};

// Se o canal ativo não estiver entre os permitidos para o garçom, comuta automaticamente
watch(allowedChannels, (channels) => {
    if (props.isTeamMode || props.isWaiterMode) {
        if (channels && channels.length > 0 && !channels.includes(localOrderType.value)) {
            handleTabChange(channels[0]);
        }
    }
}, { immediate: true });

const handleCreateNewCustomer = (query = '') => {
    emit('open-new-customer', query);
};
</script>