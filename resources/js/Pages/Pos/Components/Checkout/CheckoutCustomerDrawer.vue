<template>
    <div class="absolute inset-0 bg-surface-2 z-30 flex flex-col p-6 animate-in fade-in slide-from-right-5 duration-200">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-surface-border/50 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary-500/10 text-primary-500 flex items-center justify-center font-bold">
                    <i class="pi pi-user-plus text-base"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-surface-text leading-tight">Identificar Cliente</h3>
                    <p class="text-xs text-surface-muted">Vincule um cadastro ou nome avulso à comanda</p>
                </div>
            </div>

            <button
                @click="$emit('close')"
                type="button"
                class="w-9 h-9 rounded-full bg-surface-3 hover:bg-surface-4 text-surface-muted hover:text-surface-text flex items-center justify-center transition-colors border-none outline-none cursor-pointer"
                title="Fechar"
            >
                <i class="pi pi-times text-xs"></i>
            </button>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto hide-scrollbar">
            <CustomerSearch
                order-type="counter"
                @customer-selected="handleCustomerSelected"
                @create-new="$emit('create-new')"
            />
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import CustomerSearch from '@/Pages/Pos/Components/Cart/CustomerSearch.vue';

const props = defineProps({
    orderId: [Number, String],
    couponCode: String
});

const emit = defineEmits(['close', 'create-new', 'linked']);
const toast = useToast();

const handleCustomerSelected = (selected) => {
    if (!props.orderId) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'ID da comanda não identificado.', life: 3000 });
        return;
    }

    if (!selected) {
        toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Selecione um cliente válido.', life: 3000 });
        return;
    }

    const payload = {
        customer_id: typeof selected === 'object' ? selected?.id : null,
        customer_name: typeof selected === 'object' ? selected?.name : selected,
        coupon_code: props.couponCode
    };

    router.post(route('operations.orders.link-customer', props.orderId), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('close');
            emit('linked', selected);
            toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Cliente vinculado!', life: 3000 });
        },
        onError: (err) => {
            toast.add({ severity: 'error', summary: 'Atenção', detail: Object.values(err)[0] || 'Falha ao vincular cliente.', life: 4000 });
        }
    });
};
</script>
