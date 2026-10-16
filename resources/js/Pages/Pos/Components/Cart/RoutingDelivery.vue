<template>
    <div class="flex flex-col gap-4">

        <!-- FASE 1: NENHUM CLIENTE SELECIONADO -->
        <div v-if="!customer" class="flex flex-col gap-3">
            <div class="w-full flex justify-center mb-1">
                <SegmentedControl
                    v-model="activeSubTab"
                    :options="deliverySubTabOptions"
                />
            </div>

            <!-- 1. PESQUISAR CLIENTE EXISTENTE -->
            <DeliveryCustomerSearch
                v-if="activeSubTab === 'search'"
                :customers="customers"
                @select="handleCustomerSelected"
                @go-to-new="handleGoToNew"
            />

            <!-- 2. CADASTRO RÁPIDO -->
            <DeliveryCustomerQuick
                v-else-if="activeSubTab === 'quick'"
                :company-settings="companySettings"
                :customers="customers"
                order-type="delivery"
                @success="handleCustomerSelected"
            />

            <!-- 3. CADASTRO NOVO COMPLETO (Serpro / Receita / CPF) -->
            <DeliveryCustomerNew
                v-else-if="activeSubTab === 'new'"
                :company-settings="companySettings"
                :customers="customers"
                :initial-name="newCustomerSeedName"
                order-type="delivery"
                @success="handleCustomerSelected"
            />
        </div>

        <!-- FASE 2: CLIENTE SELECIONADO -> GESTÃO DE ENDEREÇOS -->
        <DeliveryAddressList
            v-else
            :customer="customer"
            :address="address"
            :customers="customers"
            @change-customer="handleChangeCustomer"
            @select-address="$emit('update:address', $event)"
            @open-new-address="$emit('open-new-address')"
            @open-edit-address="$emit('open-edit-address', $event)"
            @apply="$emit('apply', $event)"
        />

    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import SegmentedControl from "@/Components/UI/SegmentedControl.vue";
import DeliveryCustomerSearch from "@/Pages/Pos/Components/Cart/Delivery/DeliveryCustomerSearch.vue";
import DeliveryCustomerQuick from "@/Pages/Pos/Components/Cart/Delivery/DeliveryCustomerQuick.vue";
import DeliveryCustomerNew from "@/Pages/Pos/Components/Cart/Delivery/DeliveryCustomerNew.vue";
import DeliveryAddressList from "@/Pages/Pos/Components/Cart/Delivery/DeliveryAddressList.vue";

const props = defineProps({
    customer: Object,
    address: Object,
    customers: { type: Array, default: () => [] }
});

const emit = defineEmits([
    'update:customer',
    'update:address',
    'apply',
    'customer-selected',
    'open-new-address',
    'open-edit-address',
    'open-new-customer'
]);

const page = usePage();
const companySettings = computed(() => page.props.companySettings || {});

const activeSubTab = ref('search');
const newCustomerSeedName = ref('');

const deliverySubTabOptions = [
    { label: 'Pesquisar', value: 'search', icon: 'pi-search' },
    { label: 'Rápido',    value: 'quick',  icon: 'pi-bolt' },
    { label: 'Novo',      value: 'new',    icon: 'pi-user-plus' }
];

const handleGoToNew = (name = '') => {
    newCustomerSeedName.value = name;
    activeSubTab.value = 'new';
};

const handleCustomerSelected = (c) => {
    emit('update:customer', c);
    emit('customer-selected', c);

    if (c.addresses && c.addresses.length > 0) {
        emit('update:address', c.addresses[0]);
    } else {
        emit('update:address', null);
    }
};

const handleChangeCustomer = () => {
    emit('update:customer', null);
    emit('update:address', null);
    newCustomerSeedName.value = '';
    activeSubTab.value = 'search';
};
</script>
