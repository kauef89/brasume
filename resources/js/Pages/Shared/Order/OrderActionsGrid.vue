<template>
    <div class="flex flex-col gap-2">
        <div class="grid gap-2" :class="isTeamMode ? 'grid-cols-1' : 'grid-cols-2 mt-1'">

            <!-- ==================== GERAIS ==================== -->
            <OrderContextAction
                v-if="!isTeamMode"
                :disabled="order?.status === 'closed' || (isPaid && order?.type !== 'counter')"
                :label="isPaid && order?.type === 'counter' ? 'Finalizar Pedido' : 'Receber Conta'"
                :subtitle="isPaid && order?.type === 'counter' ? 'Entregar ao cliente' : 'Ir para o caixa'"
                :icon="isPaid && order?.type === 'counter' ? 'pi pi-check-circle' : 'pi pi-wallet'"
                :icon-color="isPaid && order?.type === 'counter' ? 'text-success-500' : 'text-primary-500'"
                :is-primary="true"
                @click="handlePrimaryAction"
            />

            <!-- 🚀 Passa o objeto order explicitamente no segundo argumento -->
            <OrderContextAction
                v-if="isTeamMode ? (order?.status !== 'closed' && order?.status !== 'cancelled') : true"
                :disabled="order?.status === 'closed' || order?.status === 'cancelled'"
                label="Adicionar Itens"
                subtitle="Abrir cardápio"
                icon="pi pi-plus-circle"
                icon-color="text-success-500"
                @click="$emit('action', 'add_items', order)"
            />

            <!-- ==================== MESAS ==================== -->
            <OrderContextAction
                v-if="isTeamMode ? (order?.type === 'table' && order?.status !== 'closed') : true"
                :disabled="order?.type !== 'table' || order?.status === 'closed'"
                label="Nova Sub-Conta"
                subtitle="Dividir consumo"
                icon="pi pi-user-plus"
                icon-color="text-purple-500"
                @click="$emit('action', 'new_subaccount', order)"
            />

            <OrderContextAction
                v-if="isTeamMode ? (order?.type === 'table') : true"
                :disabled="order?.type !== 'table'"
                label="Imprimir Parcial"
                subtitle="Conferência de mesa"
                icon="pi pi-print"
                icon-color="text-surface-muted"
                @click="$emit('action', 'print_partial', order)"
            />

            <!-- ⚡ AÇÃO DE COBRANÇA PIX DO ATENDENTE / GARÇOM -->
            <OrderContextAction
                v-if="canChargePix"
                :disabled="order?.payment_status === 'paid' || order?.status === 'closed'"
                label="Cobrar com PIX"
                subtitle="QR Code Sicredi"
                icon="pi pi-qrcode"
                icon-color="text-emerald-500"
                @click="$emit('action', 'charge_pix', order)"
            />

            <!-- ==================== LOGÍSTICA (DESKTOP) ==================== -->
            <OrderContextAction
                v-if="isTeamMode ? (['counter', 'delivery'].includes(order?.type) && hasPhone) : true"
                :disabled="!['counter', 'delivery'].includes(order?.type) || !hasPhone"
                :label="order?.type === 'counter' ? 'Avisar Coleta' : 'Avisar Saída'"
                subtitle="Notificar cliente"
                icon="pi pi-send"
                icon-color="text-primary-500"
                @click="notifyDispatch"
            />

            <template v-if="!isTeamMode">
                <OrderContextAction
                    :disabled="order?.type !== 'delivery'"
                    label="Via do Motoboy"
                    subtitle="Comanda da embalagem"
                    icon="pi pi-truck"
                    icon-color="text-surface-muted"
                    @click="$emit('action', 'print_delivery', order)"
                />

                <OrderContextAction
                    :disabled="order?.type !== 'delivery'"
                    label="Avisar Entregador"
                    subtitle="Enviar para WhatsApp"
                    icon="pi pi-whatsapp"
                    icon-color="text-green-500"
                    @click="$emit('action', 'dispatch_courier', order)"
                />
            </template>

            <OrderContextAction
                :disabled="order?.status === 'closed' || order?.status === 'cancelled'"
                label="Cancelar Pedido"
                subtitle="Estornar e encerrar"
                icon="pi pi-times-circle"
                icon-color="text-danger-500"
                @click="$emit('action', 'cancel_order', order)"
            />
        </div>

        <!-- 🚀 CONTROLE DE REPUTAÇÃO E AVALIAÇÃO DO PEDIDO -->
        <div v-if="!isTeamMode && order?.type !== 'table' && companySettings?.request_order_rating !== false" class="flex items-center justify-between p-3 mt-2 bg-surface-2 border border-surface-border rounded-xl">
            <div class="flex flex-col gap-0.5 min-w-0 pr-4">
                <span class="text-[11px] font-black text-surface-text uppercase tracking-widest leading-tight">Inibir Avaliação App</span>
                <span class="text-[10px] font-medium text-surface-muted leading-snug">Não solicitar feedback caso o cliente esteja insatisfeito.</span>
            </div>

            <button
                type="button"
                @click="toggleRatingInhibition"
                :disabled="isTogglingRating"
                class="w-12 h-7 rounded-full relative transition-colors duration-200 shrink-0 cursor-pointer shadow-inner flex items-center px-1 border-none outline-none"
                :class="[
                    isRatingSkipped ? 'bg-danger-500' : 'bg-surface-3 border border-surface-border',
                    isTogglingRating ? 'opacity-50 cursor-wait' : ''
                ]"
            >
                <div
                    class="w-5 h-5 rounded-full bg-white transition-transform duration-200 shadow-sm flex items-center justify-center"
                    :class="isRatingSkipped ? 'translate-x-[20px]' : 'translate-x-0'"
                >
                    <i v-if="isTogglingRating" class="pi pi-spinner pi-spin text-[10px] text-surface-400"></i>
                    <i v-else-if="isRatingSkipped" class="pi pi-times text-[10px] text-danger-500"></i>
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import OrderContextAction from "./OrderContextAction.vue";
import { useWhatsAppMessages } from "@/Composables/useWhatsAppMessages";

const props = defineProps({
    order: { type: Object, required: true },
    isTeamMode: { type: Boolean, default: false }
});

const emit = defineEmits(['action']);
const page = usePage();
const toast = useToast();
const { notifyDeliveryOut, notifyPickupReady } = useWhatsAppMessages();

const companySettings = computed(() => page.props.companySettings || {});

const customerPhone = computed(() => props.order?.customer?.phone || props.order?.customer_phone || null);
const hasPhone = computed(() => {
    if (!customerPhone.value) return false;
    return customerPhone.value.replace(/\D/g, '').length >= 10;
});

const isPaid = computed(() => props.order?.payment_status === 'paid');

const handlePrimaryAction = () => {
    if (isPaid.value && props.order?.type === 'counter') {
        emit('action', 'finish_order', props.order);
    } else {
        emit('action', 'checkout', props.order);
    }
};

const canChargePix = computed(() => {
    return (
        Boolean(companySettings.value.sicredi_is_active) &&
        companySettings.value.sicredi_enable_waiter_pix !== false &&
        !isPaid.value &&
        props.order?.status !== 'closed'
    );
});

const notifyDispatch = () => {
    if (!hasPhone.value) return;
    if (props.order?.type === 'counter') notifyPickupReady(props.order);
    else notifyDeliveryOut(props.order);
};

const isRatingSkipped = computed(() => {
    return props.order?.rating?.rating === 0;
});

const isTogglingRating = ref(false);

const toggleRatingInhibition = () => {
    if (isTogglingRating.value) return;

    isTogglingRating.value = true;
    const nextState = !isRatingSkipped.value;

    router.post(route('operations.orders.toggle-rating', props.order.id), { preserveState: true,  skip_rating: nextState }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.add({
                severity: nextState ? 'warn' : 'success',
                summary: nextState ? 'Avaliação Inibida' : 'Avaliação Permitida',
                detail: nextState ? 'O cliente não será questionado sobre este pedido.' : 'O widget de avaliação voltará a aparecer.',
                life: 3000
            });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível alterar o status da avaliação.', life: 3000 });
        },
        onFinish: () => {
            isTogglingRating.value = false;
        }
    });
};
</script>
