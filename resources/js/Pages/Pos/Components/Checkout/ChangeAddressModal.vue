<template>
    <component
        :is="mode === 'inline' ? 'div' : AdaptiveModal"
        v-model:visible="isVisible"
        class="flex flex-col h-full w-full"
        :class="mode === 'inline' ? 'p-6' : ''"
    >
        <div class="flex flex-col items-center justify-center gap-3 shrink-0 mb-4 md:mb-6">
            <div class="w-14 h-14 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center">
                <i class="pi fa-location-dot text-2xl"></i>
            </div>
            <div class="flex flex-col items-center text-center">
                <h2 class="text-xl font-black text-surface-text leading-tight">Trocar Endereço</h2>
                <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 mt-0.5">Local de Entrega</span>
            </div>
        </div>

        <div class="flex flex-col gap-4 flex-1 overflow-y-auto hide-scrollbar">
            <div class="p-4 flex flex-col gap-1 shrink-0 text-center">
                <span class="text-[11px] font-black uppercase tracking-widest text-primary-600">Endereço Atual</span>
                <span class="text-sm font-medium text-surface-text">{{ currentAddressText }}</span>
            </div>

            <div class="flex flex-col gap-2 mt-2">
                <h3 class="text-sm font-black text-surface-muted pl-1 text-center">Selecione outro endereço</h3>

                <div class="flex flex-col gap-2">
                    <div v-if="formattedAddresses.length > 0" class="flex flex-col gap-2">
                        <!-- 🚀 CORRIGIDO: O botão absoluto duplicado foi deletado daqui -->
                        <AddressCard
                            v-for="addr in formattedAddresses"
                            :key="addr.id"
                            :address="addr"
                            :customer-id="order?.customer_id"
                            :is-selected="selectedAddressId === addr.id"
                            @select="selectAddress"
                            @deactivate="$emit('deactivate', $event)"
                            @edit="$emit('edit', $event)"
                        />
                    </div>
                    <div v-else class="text-center p-4 bg-surface-2 rounded-xl border border-surface-border text-xs font-medium text-surface-muted">
                        Este cliente não possui outros endereços salvos.
                    </div>

                    <button @click="$emit('create-new')" class="w-full mt-2 p-4 border border-dashed border-primary-500/40 rounded-xl bg-primary-500/5 text-primary-600 hover:bg-primary-500/10 hover:border-primary-500/60 transition-colors flex items-center justify-center gap-2 outline-none cursor-pointer">
                        <i class="pi pi-plus text-sm"></i>
                        <span class="font-black text-sm uppercase tracking-widest text-[11px]">Novo Endereço</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex gap-2 w-full shrink-0 mt-auto pt-6 border-t border-surface-border/50">
            <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" custom-class="font-medium flex-1" @secure-click="handleCancel" />
            <Button
                label="Confirmar"
                icon="pi pi-check"
                @click="handleConfirm"
                :disabled="!selectedAddressId || isCurrentAddressSelected"
                class="flex-1 font-black shadow-md"
            />
        </div>
    </component>
</template>

<script setup>
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';

import { ref, computed, watch } from "vue";
import Button from "primevue/button";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";
import AddressCard from "@/Pages/Pos/Components/Cart/AddressCard.vue";

const props = defineProps({
    visible: Boolean,
    order: Object,
    mode: { type: String, default: 'modal' }
});

const emit = defineEmits(["update:visible", "confirm", "deactivate", "cancel", "create-new", "edit"]);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const selectedAddressId = ref(null);
const selectedAddressObj = ref(null);

const currentAddressText = computed(() => {
    if (typeof props.order?.address === 'string') return props.order.address;
    if (props.order?.address?.full_address) return props.order.address.full_address;
    return 'Nenhum endereço selecionado';
});

const currentAddressId = computed(() => {
    return props.order?.address_id || props.order?.address?.id || null;
});

const isCurrentAddressSelected = computed(() => {
    return selectedAddressId.value === currentAddressId.value;
});

watch(() => props.visible, (isOpen) => {
    if (isOpen) {
        selectedAddressId.value = currentAddressId.value;
        selectedAddressObj.value = null;
    }
});

const formattedAddresses = computed(() => {
    if (!props.order?.customer?.addresses) return [];

    return props.order.customer.addresses
        .filter(addr => addr.is_active !== false && addr.is_active !== 0 && addr.is_active !== '0')
        .map(addr => {
            let addressText = addr.full_address;
            if (!addressText && addr.street) {
                const streetName = typeof addr.street === 'object' ? addr.street.name : addr.street;
                const neighborhoodName = typeof addr.neighborhood === 'object' ? addr.neighborhood.name : addr.neighborhood;
                addressText = `${streetName}, ${addr.number || 'S/N'} - ${neighborhoodName || 'Bairro Indefinido'}`;
                if (addr.complement) addressText += ` (${addr.complement})`;
            }

            let fee = addr.delivery_fee;
            if ((fee === undefined || fee === null) && addr.neighborhood) {
                fee = typeof addr.neighborhood === 'object' ? addr.neighborhood.delivery_fee : 0;
            }

            return { ...addr, full_address: addressText || 'Endereço sem detalhes', delivery_fee: Number(fee || 0) };
        });
});

const selectAddress = (addr) => {
    selectedAddressId.value = addr.id;
    selectedAddressObj.value = addr;
};

const handleCancel = () => {
    emit("cancel");
    if (props.mode !== 'inline') isVisible.value = false;
};

const handleConfirm = () => {
    if (!selectedAddressObj.value) return;
    emit("confirm", {
        order_id: props.order.id,
        new_address_id: selectedAddressObj.value.id,
        new_address: selectedAddressObj.value.full_address,
        new_delivery_fee: selectedAddressObj.value.delivery_fee
    });
    if (props.mode !== 'inline') isVisible.value = false;
};
</script>
