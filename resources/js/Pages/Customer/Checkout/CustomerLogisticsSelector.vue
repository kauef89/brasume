<template>
    <div class="flex flex-col gap-4">
        <!-- SELETOR DE CANAL (DELIVERY OU RETIRADA) -->
        <div class="grid grid-cols-2 gap-2.5 w-full">
            <button
                v-for="channel in availableChannels"
                :key="channel.value"
                type="button"
                @click="selectChannel(channel.value)"
                class="p-4 rounded-2xl flex items-center justify-center gap-2.5 font-black text-xs uppercase tracking-wider transition-all outline-none border cursor-pointer select-none"
                :class="[
                    currentType === channel.value
                        ? 'bg-primary-500 text-white border-primary-400 shadow-md shadow-primary-500/20 scale-[1.02]'
                        : 'bg-white/10 hover:bg-white/15 text-white/80 border-white/10'
                ]"
            >
                <i :class="['pi text-base', channel.icon]"></i>
                <span>{{ channel.label }}</span>
            </button>
        </div>

        <!-- ESTADO INICIAL: NENHUMA OPÇÃO SELECIONADA -->
        <div v-if="!currentType" class="p-6 text-center c-glass-card flex flex-col items-center gap-2 animate-in fade-in duration-200">
            <i class="pi pi-compass text-2xl text-white/40"></i>
            <p class="text-xs text-white/70 font-medium">
                Selecione acima como deseja receber seu pedido para continuar.
            </p>
        </div>

        <!-- FLUXO DELIVERY: GESTÃO DE ENDEREÇOS -->
        <div v-else-if="currentType === 'delivery'" class="flex flex-col gap-3 mt-1 animate-in fade-in duration-200">
            <div class="flex items-center justify-between px-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-white/50">Endereço de Entrega</span>
                <button
                    type="button"
                    @click="$emit('open-new-address')"
                    class="text-xs font-bold text-primary-300 hover:text-primary-200 flex items-center gap-1 bg-transparent border-none cursor-pointer outline-none"
                >
                    <i class="pi pi-plus text-[10px]"></i>
                    <span>Novo Endereço</span>
                </button>
            </div>

            <!-- LISTAGEM DE ENDEREÇOS SALVOS -->
            <div v-if="customerAddresses.length > 0" class="flex flex-col gap-2">
                <div
                    v-for="addr in customerAddresses"
                    :key="addr.id"
                    @click="chooseAddress(addr)"
                    class="p-4 rounded-2xl border transition-all cursor-pointer flex items-center justify-between select-none"
                    :class="activeAddress?.id === addr.id
                        ? 'bg-primary-500/20 border-primary-400 shadow-sm'
                        : 'bg-white/5 border-white/10 hover:bg-white/10'"
                >
                    <div class="flex flex-col min-w-0 pr-3">
                        <span class="text-sm font-bold text-white leading-tight truncate">
                            {{ addr.full_address || addr.street }}
                        </span>
                        <span class="text-xs text-white/60 mt-0.5">
                            {{ addr.neighborhood || 'Bairro' }}
                            <span v-if="addr.delivery_fee !== undefined"> — Taxa: {{ formatCurrency(addr.delivery_fee) }}</span>
                        </span>
                    </div>

                    <div
                        class="w-6 h-6 rounded-full flex items-center justify-center border shrink-0 transition-colors"
                        :class="activeAddress?.id === addr.id ? 'bg-primary-500 border-primary-400 text-white' : 'border-white/20'"
                    >
                        <i v-if="activeAddress?.id === addr.id" class="pi pi-check text-[10px] font-black"></i>
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="!activeAddress"
                    @click="confirmDeliveryStep"
                    class="w-full c-btn-primary !h-12 !text-xs !uppercase !tracking-widest mt-2 flex items-center justify-center gap-2 border-none cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <span>Continuar para Pagamento</span>
                    <i class="pi pi-arrow-right text-xs"></i>
                </button>
            </div>

            <!-- SEM ENDEREÇOS CADASTRADOS -->
            <div v-else class="p-6 text-center c-glass-card flex flex-col items-center gap-3">
                <i class="pi pi-map-marker text-3xl text-white/40"></i>
                <p class="text-xs text-white/70">Nenhum endereço cadastrado para entrega.</p>
                <button
                    type="button"
                    @click="$emit('open-new-address')"
                    class="c-btn-primary !h-11 !text-xs"
                >
                    Cadastrar Endereço
                </button>
            </div>
        </div>

        <!-- FLUXO RETIRADA NO BALCÃO -->
        <div v-else-if="currentType === 'counter'" class="p-6 c-glass-card flex flex-col gap-4 animate-in fade-in duration-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-500/20 text-primary-300 flex items-center justify-center shrink-0">
                    <i class="pi pi-shopping-bag text-lg"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-bold text-white">Retirada na Lanchonete</span>
                    <span class="text-xs text-white/60 mt-0.5">Retire seu pedido direto no balcão sem taxa de entrega.</span>
                </div>
            </div>

            <button
                type="button"
                @click="confirmCounterStep"
                class="w-full c-btn-primary !h-12 !text-xs !uppercase !tracking-widest flex items-center justify-center gap-2 border-none cursor-pointer"
            >
                <span>Confirmar Retirada e Avançar</span>
                <i class="pi pi-arrow-right text-xs"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    customer: Object,
    modelValue: { type: String, default: null },
    selectedAddress: Object
});

const emit = defineEmits(['update:modelValue', 'routing-change', 'open-new-address', 'apply']);

const page = usePage();
const currentType = ref(props.modelValue || null);
const activeAddress = ref(props.selectedAddress || null);

watch(() => props.modelValue, (val) => {
    currentType.value = val || null;
});

const availableChannels = computed(() => {
    let channels = page.props.storeStatus?.active_channels;
    if (typeof channels === 'string') {
        try { channels = JSON.parse(channels); } catch (e) {}
    }
    if (!Array.isArray(channels) || channels.length === 0) {
        channels = ['delivery', 'counter'];
    }

    const options = [];
    if (channels.includes('delivery')) {
        options.push({ label: 'Delivery', value: 'delivery', icon: 'pi-truck' });
    }
    if (channels.includes('counter') || channels.includes('takeaway')) {
        options.push({ label: 'Retirada', value: 'counter', icon: 'pi-shopping-bag' });
    }
    return options;
});

const customerAddresses = computed(() => {
    return props.customer?.addresses || [];
});

// 🚀 O SEGREDO DO BUG RESOLVIDO: Memória Rastreável de Endereços
const knownAddressIds = ref(props.customer?.addresses?.map(a => a.id) || []);

watch(() => props.customer?.addresses, (newAddresses) => {
    if (!newAddresses) return;

    const currentIds = newAddresses.map(a => a.id);

    // Descobre se algum ID novo e inédito acabou de chegar via Props (cliente recém-salvou)
    const addedIds = currentIds.filter(id => !knownAddressIds.value.includes(id));

    if (currentType.value === 'delivery') {
        if (addedIds.length > 0) {
            // 🚀 Endereço novo criado! Pula nele e seleciona instantaneamente
            const newAddr = newAddresses.find(a => a.id === addedIds[addedIds.length - 1]);
            if (newAddr) chooseAddress(newAddr);
        } else if (!activeAddress.value && newAddresses.length > 0) {
            // Fallback: se não tiver nada ativo (primeiro carregamento), pega o padrão ou o primeiro
            const defaultAddr = newAddresses.find(a => a.is_default) || newAddresses[0];
            chooseAddress(defaultAddr);
        }
    }

    // Sincroniza a memória com o estado atual para não rodar duas vezes
    knownAddressIds.value = currentIds;
}, { deep: true, immediate: true });

const selectChannel = (type) => {
    currentType.value = type;
    emit('update:modelValue', type);

    if (type === 'counter') {
        activeAddress.value = null;
        emit('routing-change', { type: 'counter', address: null });
    } else if (type === 'delivery') {
        if (!activeAddress.value && customerAddresses.value.length > 0) {
            const defaultAddr = customerAddresses.value.find(a => a.is_default) || customerAddresses.value[0];
            chooseAddress(defaultAddr);
        } else {
            emit('routing-change', { type: 'delivery', address: activeAddress.value });
        }
    }
};

const chooseAddress = (addr) => {
    activeAddress.value = addr;
    emit('routing-change', { type: 'delivery', address: addr });
};

const confirmDeliveryStep = () => {
    if (!activeAddress.value) return;
    emit('apply', { type: 'delivery', address: activeAddress.value });
};

const confirmCounterStep = () => {
    emit('apply', { type: 'counter', address: null });
};
</script>
