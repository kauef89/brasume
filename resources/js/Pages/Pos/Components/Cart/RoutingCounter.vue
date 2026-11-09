<template>
    <div class="flex flex-col gap-4">
        <!-- FASE 1: CLIENTE NÃO SELECIONADO OU PADRÃO BALCAO -->
        <div v-if="!currentCustomer" class="flex flex-col gap-3">
            <div class="w-full flex justify-center mb-1">
                <SegmentedControl
                    v-model="activeSubTab"
                    :options="counterSubTabOptions"
                />
            </div>

            <!-- 1. PESQUISAR CLIENTE EXISTENTE / CONVÊNIO -->
            <CustomerSearch
                v-if="activeSubTab === 'search'"
                order-type="counter"
                :initial-customers="customers"
                @customer-selected="handleCustomerSelected"
                @create-new="handleGoToNew"
            />

            <!-- 2. CADASTRO RÁPIDO (Pré-preenchido com BALCAO, telefone opcional) -->
            <DeliveryCustomerQuick
                v-else-if="activeSubTab === 'quick'"
                :company-settings="companySettings"
                :customers="customers"
                :initial-name="'BALCAO'"
                order-type="counter"
                :allow-empty-phone="true"
                @success="handleCustomerSelected"
            />

            <!-- 3. CADASTRO NOVO COMPLETO -->
            <DeliveryCustomerNew
                v-else-if="activeSubTab === 'new'"
                :company-settings="companySettings"
                :customers="customers"
                :initial-name="newCustomerSeedName"
                order-type="counter"
                @success="handleCustomerSelected"
            />
        </div>

        <!-- FASE 2: CLIENTE IDENTIFICADO NO BALCÃO -->
        <div v-else class="flex flex-col gap-3 p-4 bg-surface-3 rounded-2xl animate-in fade-in duration-200">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted flex items-center gap-1.5">
                    <i class="pi pi-bolt text-amber-500"></i> Cliente do Balcão
                </span>
                <button
                    @click="handleChangeCustomer"
                    type="button"
                    class="text-xs text-primary-500 hover:text-primary-600 font-bold bg-transparent border-none cursor-pointer outline-none flex items-center gap-1 transition-colors"
                >
                    <i class="pi pi-refresh text-[10px]"></i> Trocar
                </button>
            </div>

            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-black text-sm border border-amber-500/20 shrink-0">
                    {{ getInitials(currentCustomer.name) }}
                </div>

                <div class="flex flex-col min-w-0 flex-1">
                    <span class="font-black text-sm text-surface-text truncate">
                        {{ currentCustomer.name || 'BALCAO' }}
                    </span>
                    <span class="text-xs text-surface-muted truncate flex items-center gap-1 mt-0.5">
                        <template v-if="currentCustomer.phone">
                            <i class="pi pi-whatsapp text-emerald-500 text-[10px]"></i>
                            {{ formatPhone(currentCustomer.phone) }}
                        </template>
                        <template v-else>
                            <i class="pi pi-bolt text-amber-500 text-[10px]"></i>
                            Venda Direta / Sem WhatsApp
                        </template>
                    </span>
                </div>
            </div>

            <div class="pt-2 border-t border-surface-border/50 flex gap-2">
                <button
                    @click="handleChangeCustomer"
                    type="button"
                    class="flex-1 h-10 rounded-xl bg-surface-2 hover:bg-surface-3 border border-surface-border text-surface-muted hover:text-surface-text font-bold text-xs uppercase tracking-wider transition-colors cursor-pointer outline-none"
                >
                    Remover
                </button>
                <button
                    @click="handleConfirm"
                    type="button"
                    class="flex-1 h-10 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider transition-all shadow-sm cursor-pointer outline-none border-none flex items-center justify-center gap-1.5"
                >
                    <i class="pi pi-check text-xs"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import SegmentedControl from "@/Components/UI/SegmentedControl.vue";
import CustomerSearch from "@/Pages/Pos/Components/Cart/CustomerSearch.vue";
import DeliveryCustomerQuick from "@/Pages/Pos/Components/Cart/Delivery/DeliveryCustomerQuick.vue";
import DeliveryCustomerNew from "@/Pages/Pos/Components/Cart/Delivery/DeliveryCustomerNew.vue";
import { formatPhone, getInitials } from "@/Utils/formatters";

const props = defineProps({
    modelValue: [Object, String],
    customer: Object,
    customers: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:modelValue', 'update:customer', 'apply', 'customer-selected']);

const page = usePage();
const companySettings = computed(() => page.props.companySettings || {});

// Balcão abre por padrão na aba "Rápido"
const activeSubTab = ref('quick');
const newCustomerSeedName = ref('');

const counterSubTabOptions = [
    { label: 'Rápido',    value: 'quick',  icon: 'pi-bolt' },
    { label: 'Pesquisar', value: 'search', icon: 'pi-search' },
    { label: 'Novo',      value: 'new',    icon: 'pi-user-plus' }
];

const currentCustomer = computed(() => {
    if (props.customer && typeof props.customer === 'object') return props.customer;
    if (props.modelValue && typeof props.modelValue === 'object') return props.modelValue;
    return null;
});

const handleCustomerSelected = (c) => {
    emit('update:modelValue', c);
    emit('update:customer', c);
    emit('customer-selected', c);
    emit('apply', {
        customer: typeof c === 'object' ? c : null,
        address: null,
        identifier: c?.name || 'BALCAO'
    });
};

const handleGoToNew = (name) => {
    newCustomerSeedName.value = name;
    activeSubTab.value = 'new';
};

const handleChangeCustomer = () => {
    emit('update:modelValue', null);
    emit('update:customer', null);
    emit('customer-selected', null);
    newCustomerSeedName.value = '';
    activeSubTab.value = 'quick';
};

const handleConfirm = () => {
    emit('apply', {
        customer: currentCustomer.value,
        address: null,
        identifier: currentCustomer.value?.name || 'BALCAO'
    });
};
</script>