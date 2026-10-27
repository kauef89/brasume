<template>
    <div class="w-full flex-1 overflow-y-auto hide-scrollbar px-4 md:px-6 py-6 relative">
        <CustomerLogisticsSelector
            v-model="selectedType"
            :customer="customer"
            :selected-address="currentAddress"
            @routing-change="handleRoutingChange"
            @open-new-address="$emit('open-new-address')"
            @apply="handleApply"
        />

        <!-- MODAL PROATIVO: CONVITE DE LOCALIZAÇÃO PARA O MOTOBOY -->
        <div
            v-if="showLocationPrompt"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-200"
        >
            <div class="bg-surface-1 border border-surface-border rounded-3xl p-6 max-w-sm w-full shadow-2xl flex flex-col items-center text-center gap-4">
                <div class="w-14 h-14 rounded-full bg-primary-500/10 text-primary-500 flex items-center justify-center text-2xl">
                    <i class="pi pi-map-marker"></i>
                </div>
                <div class="flex flex-col gap-1">
                    <h3 class="font-black text-base text-surface-text">Facilitar a Entrega?</h3>
                    <p class="text-xs text-surface-muted leading-relaxed">
                        A sua morada ainda não tem o ponto exato no mapa. Deseja anexar a sua localização GPS para ajudar o nosso estafeta a chegar mais rápido?
                    </p>
                </div>
                <div class="flex flex-col gap-2 w-full mt-2">
                    <button
                        type="button"
                        @click="handleCaptureLocationForSelectedAddress"
                        :disabled="isLocating"
                        class="w-full h-12 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 border-none cursor-pointer shadow-md"
                    >
                        <i v-if="isLocating" class="pi pi-spinner animate-spin"></i>
                        <i v-else class="pi pi-compass"></i>
                        <span>Sim, Usar o Meu GPS</span>
                    </button>
                    <button
                        type="button"
                        @click="skipLocationPrompt"
                        class="w-full h-11 rounded-xl bg-transparent hover:bg-surface-2 text-surface-muted hover:text-surface-text font-bold text-xs uppercase tracking-wider transition-colors border-none cursor-pointer"
                    >
                        Agora Não, Continuar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import CustomerLogisticsSelector from '@/Pages/Customer/Checkout/CustomerLogisticsSelector.vue';

const props = defineProps({
    customer: Object,
    selectedOrderType: { type: String, default: 'delivery' }
});

const emit = defineEmits(['update:selectedOrderType', 'routing-change', 'open-new-address', 'next', 'update-header']);

const toast = useToast();
const selectedType = ref(props.selectedOrderType);
const currentAddress = ref(null);
const showLocationPrompt = ref(false);
const pendingPayload = ref(null);
const isLocating = ref(false);

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

    // Se for entrega e a morada selecionada não tiver localização guardada, questiona o cliente
    if (payload.type === 'delivery' && payload.address && !payload.address.location && payload.address.id) {
        pendingPayload.value = payload;
        showLocationPrompt.value = true;
        return;
    }

    emit('next');
};

const skipLocationPrompt = () => {
    showLocationPrompt.value = false;
    emit('next');
};

const handleCaptureLocationForSelectedAddress = () => {
    if (!navigator.geolocation) {
        skipLocationPrompt();
        return;
    }

    isLocating.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            isLocating.value = false;
            const { latitude, longitude } = pos.coords;
            const locUrl = `https://www.google.com/maps?q=${latitude},${longitude}`;

            const addrId = pendingPayload.value?.address?.id;
            if (addrId) {
                router.post(`/customer/addresses/${addrId}/location`, { location: locUrl }, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        if (pendingPayload.value?.address) {
                            pendingPayload.value.address.location = locUrl;
                        }
                        toast.add({ severity: 'success', summary: 'Localização Anexada', detail: 'Obrigado! O estafeta receberá o seu ponto exato.', life: 3000 });
                        showLocationPrompt.value = false;
                        emit('next');
                    },
                    onError: () => {
                        skipLocationPrompt();
                    }
                });
            } else {
                skipLocationPrompt();
            }
        },
        () => {
            isLocating.value = false;
            toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Permissão de GPS recusada. A prosseguir...', life: 3000 });
            skipLocationPrompt();
        },
        { enableHighAccuracy: true, timeout: 8000 }
    );
};
</script>