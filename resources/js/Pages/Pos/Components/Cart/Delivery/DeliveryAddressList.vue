<template>
    <div class="flex flex-col gap-4 animate-in fade-in duration-200">

        <!-- BADGE DO CLIENTE ATIVO -->
        <div class="flex items-center justify-between p-3.5 bg-surface-3 rounded-2xl">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-surface-muted font-black text-xs shrink-0">
                    {{ getInitials(customer.name) }}
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="font-bold text-sm truncate leading-tight">{{ customer.name }}</span>
                    <span class="text-xs text-surface-muted">{{ formatPhone(customer.phone) || customer.cpf || 'Cliente Selecionado' }}</span>
                </div>
            </div>

            <button
                type="button"
                @click.stop="$emit('change-customer')"
                class="px-3 py-1.5 rounded-full bg-primary-500/40 hover:bg-primary-500/60 text-surface-text font-black text-[10px] uppercase tracking-wider transition-colors cursor-pointer shrink-0 border-none outline-none"
            >
                Trocar
            </button>
        </div>

        <!-- LISTAGEM DE ENDEREÇOS -->
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between px-1">
                <span class="text-xs font-black uppercase tracking-wider text-surface-muted">Endereço de Entrega</span>
                <button
                    type="button"
                    @click="$emit('open-new-address')"
                    class="text-xs font-black text-primary-600 dark:text-primary-400 hover:underline uppercase tracking-wider bg-transparent border-none cursor-pointer flex items-center gap-1"
                >
                    <i class="pi pi-plus text-[10px]"></i>
                    <span>Novo Endereço</span>
                </button>
            </div>

            <div v-if="activeAddresses.length > 0" class="flex flex-col gap-2">
                <div
                    v-for="addr in activeAddresses"
                    :key="addr.id"
                    @click="selectAndConfirm(addr)"
                    class="p-3.5 rounded-2xl border transition-all cursor-pointer flex items-center justify-between group active:scale-[0.99]"
                    :class="address?.id === addr.id
                        ? 'bg-primary-500/10 border-primary-500 shadow-sm'
                        : 'bg-surface-3/50 border-surface-border/60 hover:bg-surface-3 hover:border-primary-500/40'"
                >
                    <div class="flex flex-col min-w-0 pr-3">
                        <span class="text-sm font-bold text-surface-text leading-tight truncate">
                            {{ addr.full_address || addr.street?.name || addr.street }}
                        </span>
                        <span class="text-xs text-surface-muted mt-0.5">
                            {{ addr.neighborhood?.name || addr.neighborhood || 'Bairro' }} — Taxa: {{ $formatCurrency(addr.delivery_fee ?? addr.neighborhood?.delivery_fee ?? 0) }}
                        </span>
                        <span v-if="addr.reference" class="text-[11px] text-surface-muted/70 italic mt-0.5 truncate">
                            Ref: {{ addr.reference }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Lixeira com proteção contra clique de seleção -->
                        <button
                            type="button"
                            @click.stop="handleDeactivateAddress(addr)"
                            class="w-8 h-8 rounded-xl bg-surface-2 hover:bg-red-500/10 text-surface-muted hover:text-red-500 flex items-center justify-center border-none cursor-pointer transition-colors"
                            title="Inativar Endereço"
                        >
                            <i class="pi pi-trash text-xs"></i>
                        </button>

                        <!-- Indicador de Seleção / Ação Direta -->
                        <div
                            class="w-7 h-7 rounded-full flex items-center justify-center border transition-all"
                            :class="address?.id === addr.id
                                ? 'bg-primary-500 border-primary-500 text-white shadow-sm'
                                : 'border-surface-border text-surface-muted group-hover:border-primary-500/50 group-hover:text-primary-500'"
                        >
                            <i :class="address?.id === addr.id ? 'pi pi-check' : 'pi pi-arrow-right'" class="text-[10px] font-black"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEM ENDEREÇOS -->
            <div v-else class="p-5 text-center rounded-2xl border border-dashed border-surface-border flex flex-col items-center gap-2">
                <i class="pi pi-map-marker text-2xl text-surface-muted"></i>
                <p class="text-xs font-medium text-surface-muted">Nenhum endereço ativo para este cliente.</p>
                <button
                    type="button"
                    @click="$emit('open-new-address')"
                    class="mt-1 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider rounded-xl border-none cursor-pointer shadow-sm transition-all"
                >
                    Cadastrar Primeiro Endereço
                </button>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { formatPhone, formatCurrency, getInitials } from '@/Utils/formatters';

const props = defineProps({
    customer: Object,
    address: Object,
    customers: { type: Array, default: () => [] }
});

const emit = defineEmits([
    'change-customer',
    'select-address',
    'open-new-address',
    'apply'
]);

const toast = useToast();

const customerAddresses = computed(() => {
    if (!props.customer) return [];

    const list = [];
    const seen = new Set();
    const addToList = (addr) => {
        if (addr && addr.id && !seen.has(String(addr.id))) {
            seen.add(String(addr.id));
            list.push(addr);
        }
    };

    // Prioridade 1: Endereços do cliente ativo no pedido
    (props.customer.addresses || []).forEach(addToList);
    if (props.customer.address) addToList(props.customer.address);

    // Prioridade 2: Endereço selecionado na rota
    if (props.address) addToList(props.address);

    // Prioridade 3: Endereços da lista global de clientes
    const fresh = props.customers?.find(c => c.id === props.customer.id);
    (fresh?.addresses || []).forEach(addToList);

    return list;
});

const activeAddresses = computed(() => {
    return customerAddresses.value.filter(a => a.is_active !== false && a.is_active !== 0 && a.is_active !== '0');
});

// Ação Direta: Seleciona e Aplica no mesmo clique
const selectAndConfirm = (addr) => {
    emit('select-address', addr);
    emit('apply', {
        type: 'delivery',
        customer: props.customer,
        address: addr
    });
};

// Memória Reativa: Quando um novo endereço é cadastrado, já aplica direto
const knownAddressIds = ref(activeAddresses.value.map(a => String(a.id)));

watch(activeAddresses, (newAddresses) => {
    if (!newAddresses || newAddresses.length === 0) return;

    const currentIds = newAddresses.map(a => String(a.id));
    const addedIds = currentIds.filter(id => !knownAddressIds.value.includes(id));

    if (addedIds.length > 0) {
        const newAddr = newAddresses.find(a => String(a.id) === addedIds[addedIds.length - 1]);
        if (newAddr) selectAndConfirm(newAddr);
    } else if (!props.address && newAddresses.length > 0) {
        // Seleciona o padrão e já valida a rota imediatamente
        const defaultAddr = newAddresses.find(a => a.is_default) || newAddresses[0];
        selectAndConfirm(defaultAddr);
    }

    knownAddressIds.value = currentIds;
}, { deep: true, immediate: true });

const handleDeactivateAddress = (addr) => {
    const addressName = addr.full_address || addr.street?.name || addr.street || 'este endereço';
    if (!confirm(`Deseja realmente inativar ${addressName}?`)) return;

    if (props.address?.id === addr.id) {
        emit('select-address', null);
    }

    if (addr.is_temporary || String(addr.id).startsWith('temp_')) {
        const idx = props.customer.addresses?.findIndex(a => a.id === addr.id);
        if (idx !== undefined && idx > -1) {
            props.customer.addresses.splice(idx, 1);
        }
        toast.add({ severity: 'info', summary: 'Removido', detail: 'Endereço temporário descartado.', life: 3000 });
        return;
    }

    const customerId = props.customer.id;
    router.post(route('operations.customers.addresses.deactivate', [customerId, addr.id]), {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            addr.is_active = false;
            toast.add({ severity: 'success', summary: 'Inativado', detail: 'Endereço removido da lista.', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível inativar o endereço.', life: 4000 });
        }
    });
};
</script>
