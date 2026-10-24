<template>
    <div class="flex flex-col gap-4">

        <!-- FASE 1: NENHUM CLIENTE SELECIONADO NO BALCÃO -->
        <div v-if="!currentCustomer" class="flex flex-col gap-3">
            <div v-if="!isSimpleMode" class="w-full flex justify-center mb-1">
                <SegmentedControl
                    v-model="activeSubTab"
                    :options="counterSubTabOptions"
                />
            </div>

            <!-- 1. PESQUISAR CLIENTE EXISTENTE -->
            <DeliveryCustomerSearch
                v-if="!isSimpleMode && activeSubTab === 'search'"
                :customers="customers"
                @select="handleCustomerSelected"
                @go-to-new="handleGoToNew"
            />

            <!-- 2. CADASTRO RÁPIDO (WhatsApp Opcional no Balcão) -->
            <DeliveryCustomerQuick
                v-else-if="isSimpleMode || activeSubTab === 'quick'"
                :simple-mode="isSimpleMode"
                :company-settings="companySettings"
                :customers="customers"
                order-type="counter"
                :allow-empty-phone="true"
                @success="handleCustomerSelected"
            />

            <!-- 3. CADASTRO NOVO COMPLETO -->
            <DeliveryCustomerNew
                v-else-if="!isSimpleMode && activeSubTab === 'new'"
                :company-settings="companySettings"
                :customers="customers"
                :initial-name="newCustomerSeedName"
                order-type="counter"
                @success="handleCustomerSelected"
            />
        </div>

        <!-- FASE 2: CLIENTE SELECIONADO NO BALCÃO -->
        <div v-else class="flex flex-col gap-3 p-4 bg-surface-3 rounded-2xl animate-in fade-in duration-200">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted flex items-center gap-1.5">
                    <i class="pi pi-shopping-bag text-primary-500"></i> Cliente do Balcão
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
                <div class="w-12 h-12 rounded-2xl bg-primary-500/10 text-primary-500 flex items-center justify-center font-black text-lg border border-primary-500/20 shrink-0">
                    {{ currentCustomer.name ? currentCustomer.name.charAt(0).toUpperCase() : 'B' }}
                </div>

                <div class="flex flex-col min-w-0 flex-1">
                    <span class="font-black text-sm text-surface-text truncate">
                        {{ currentCustomer.name || 'Cliente Balcão' }}
                    </span>
                    <span class="text-xs text-surface-muted truncate flex items-center gap-1 mt-0.5">
                        <template v-if="currentCustomer.phone">
                            <i class="pi pi-whatsapp text-emerald-500 text-[10px]"></i>
                            {{ formatPhone(currentCustomer.phone) }}
                        </template>
                        <template v-else>
                            <i class="pi pi-map-marker text-amber-500 text-[10px]"></i>
                            Atendimento no local (Sem WhatsApp)
                        </template>
                    </span>
                </div>

                <!-- BADGE DE PONTOS DE FIDELIDADE (Se ativo) -->
                <div v-if="currentCustomer.loyalty_points > 0" class="flex flex-col items-end shrink-0 bg-purple-500/10 border border-purple-500/20 px-2.5 py-1 rounded-xl">
                    <span class="text-[9px] font-black uppercase text-purple-400">Pontos</span>
                    <span class="text-xs font-black text-purple-400">{{ currentCustomer.loyalty_points }} pts</span>
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
import DeliveryCustomerSearch from "@/Pages/Pos/Components/Cart/Delivery/DeliveryCustomerSearch.vue";
import DeliveryCustomerQuick from "@/Pages/Pos/Components/Cart/Delivery/DeliveryCustomerQuick.vue";
import DeliveryCustomerNew from "@/Pages/Pos/Components/Cart/Delivery/DeliveryCustomerNew.vue";

const props = defineProps({
    modelValue: [Object, String],
    customer: Object,
    customers: { type: Array, default: () => [] },
    isCustomerMode: { type: Boolean, default: false },
    isTeamMode: { type: Boolean, default: false },
    isWaiterMode: { type: Boolean, default: false }
});

const emit = defineEmits([
    'update:modelValue',
    'update:customer',
    'apply',
    'customer-selected',
    'create-new'
]);

const page = usePage();
const companySettings = computed(() => page.props.companySettings || {});

const isSimpleMode = computed(() => {
    if (!props.isTeamMode && !props.isWaiterMode) return false;
    const mode = companySettings.value?.team_counter_mode || 'simple';
    return mode === 'simple';
});

const activeSubTab = ref('search');
const newCustomerSeedName = ref('');

const counterSubTabOptions = [
    { label: 'Pesquisar', value: 'search', icon: 'pi-search' },
    { label: 'Rápido',    value: 'quick',  icon: 'pi-bolt' },
    { label: 'Novo',      value: 'new',    icon: 'pi-user-plus' }
];

const currentCustomer = computed(() => {
    if (props.customer && typeof props.customer === 'object') return props.customer;
    if (props.modelValue && typeof props.modelValue === 'object') return props.modelValue;
    return null;
});

const formatPhone = (phone) => {
    if (!phone) return '';
    const p = String(phone).replace(/\D/g, '');
    if (p.length === 11) return p.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    if (p.length === 10) return p.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    return phone;
};

const handleCustomerSelected = (c) => {
    emit('update:modelValue', c);
    emit('update:customer', c);
    emit('customer-selected', c);
    emit('apply', { customer: c, address: null, identifier: c?.name || '' });
};

const handleGoToNew = (name) => {
    newCustomerSeedName.value = name;
    activeSubTab.value = 'new';
};

const handleChangeCustomer = () => {
    emit('update:modelValue', null);
    emit('update:customer', null);
    emit('customer-selected', null);
    emit('apply', { customer: null, address: null, identifier: 'Balcão' });
    newCustomerSeedName.value = '';
    activeSubTab.value = 'search';
};

const handleConfirm = () => {
    emit('apply', { customer: currentCustomer.value, address: null, identifier: currentCustomer.value?.name || 'Balcão' });
};
</script>
