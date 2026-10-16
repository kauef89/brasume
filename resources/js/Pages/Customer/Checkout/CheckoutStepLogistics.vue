<template>
    <div class="w-full flex-1 overflow-y-auto hide-scrollbar px-4 md:px-6 py-6">
        <CustomerLogisticsSelector
            v-model="selectedType"
            :customer="customer"
            :selected-address="currentAddress"
            @routing-change="handleRoutingChange"
            @open-new-address="$emit('open-new-address')"
            @apply="handleApply"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import CustomerLogisticsSelector from '@/Pages/Customer/Checkout/CustomerLogisticsSelector.vue';

const props = defineProps({
    customer: Object,
    selectedOrderType: { type: String, default: 'delivery' }
});

const emit = defineEmits(['update:selectedOrderType', 'routing-change', 'open-new-address', 'next', 'update-header']);

const selectedType = ref(props.selectedOrderType);
const currentAddress = ref(null);

onMounted(() => {
    emit('update-header', { title: 'Como quer receber?', subtitle: 'Forma de Entrega', currentProgress: 2, maxProgress: 4 });
});

const handleRoutingChange = (payload) => {
    if (!payload) return;
    currentAddress.value = payload.address || null;
    emit('update:selectedOrderType', payload.type);
    emit('routing-change', payload);
};

const handleApply = (payload) => {
    handleRoutingChange(payload);
    emit('next');
};
</script>
