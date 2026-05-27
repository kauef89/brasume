<template>
    <Drawer
        v-if="isMobile"
        v-model:visible="isVisible"
        position="bottom"
        class="!h-auto !rounded-t-3xl"
        :show-close-icon="false"
    >
        <template #header>
            <div class="w-full text-center pb-2 border-b border-gray-100 dark:border-gray-800">
                <div class="w-12 h-1.5 bg-gray-300 dark:bg-gray-700 rounded-full mx-auto mb-4"></div>
                <h2 class="text-lg font-black text-gray-800 dark:text-gray-100">
                    Ações: {{ order?.customer_name }}
                </h2>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-widest border border-gray-200 dark:border-gray-700 px-2 py-0.5 rounded-md mt-1 inline-block">
                    #{{ order?.id }} • {{ translatedType }}
                </span>
            </div>
        </template>

        <div class="flex flex-col gap-2 py-2">
            <button
                v-for="action in availableActions"
                :key="action.id"
                @click="handleAction(action.id)"
                class="w-full flex items-center gap-4 p-4 rounded-xl font-bold transition-all text-left"
                :class="action.colorClass"
            >
                <i :class="action.icon" class="text-xl"></i>
                <span class="text-base">{{ action.label }}</span>
            </button>
        </div>
    </Drawer>

    <Dialog
        v-else
        v-model:visible="isVisible"
        modal
        class="w-[450px]"
        :closable="true"
        :draggable="false"
    >
        <template #header>
            <div>
                <h2 class="text-xl font-black text-gray-800 dark:text-gray-100">
                    {{ order?.customer_name }}
                </h2>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">
                    Pedido #{{ order?.id }} • {{ translatedType }}
                </span>
            </div>
        </template>

        <div class="grid grid-cols-2 gap-3 mt-2">
            <button
                v-for="action in availableActions"
                :key="action.id"
                @click="handleAction(action.id)"
                class="flex flex-col items-center justify-center gap-3 p-4 rounded-xl font-bold transition-all border-2 border-transparent hover:border-gray-200 dark:hover:border-gray-700"
                :class="
                    action.colorClass +
                    (action.isPrimary
                        ? ' col-span-2 py-6'
                        : ' bg-gray-50 dark:bg-gray-900/50')
                "
            >
                <i
                    :class="[
                        action.icon,
                        action.isPrimary ? 'text-3xl' : 'text-2xl',
                    ]"
                ></i>

                <span
                    :class="
                        action.isPrimary ? 'text-lg' : 'text-sm text-center'
                    "
                    >{{ action.label }}</span
                >
            </button>
        </div>
    </Dialog>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import Drawer from "primevue/drawer";
import Dialog from "primevue/dialog";

const props = defineProps({
    visible: Boolean,
    order: Object,
});

const emit = defineEmits(["update:visible", "action"]);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const isMobile = ref(false);
const checkMobile = () => {
    isMobile.value = window.innerWidth <= 768;
};

onMounted(() => {
    checkMobile();
    window.addEventListener("resize", checkMobile);
});
onUnmounted(() => window.removeEventListener("resize", checkMobile));

const translatedType = computed(() => {
    const types = { table: "Mesa", counter: "Balcão", delivery: "Delivery" };
    return props.order ? types[props.order.type] : "";
});

const availableActions = computed(() => {
    if (!props.order) return [];

    let actions = [];
    const type = props.order.type;

    // Ações Universais
    actions.push({
        id: "checkout",
        label: "Receber / Fechar Conta",
        icon: "pi pi-dollar",
        colorClass: "bg-green-100 text-green-700 dark:bg-green-900/40 hover:bg-green-200",
        isPrimary: true,
    });

    actions.push({
        id: "add_items",
        label: "Adicionar Itens",
        icon: "pi pi-plus",
        colorClass: "text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20",
    });

    actions.push({
        id: "modify_order",
        label: "Modificar Pedido",
        icon: "pi pi-list",
        colorClass: "text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800",
    });

    actions.push({
        id: "print_partial",
        label: "Imprimir Fechamento",
        icon: "pi pi-print",
        colorClass: "text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800",
    });

    actions.push({
        id: "transform_order",
        label: "Transformar Pedido",
        icon: "pi pi-arrows-alt",
        colorClass: "text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/20",
    });

    // 🚀 Ações Específicas de Delivery
    if (type === "delivery") {
        // Verifica se já existe uma intenção de pagamento definida
        const hasPaymentIntent = props.order.delivery_payment_status && props.order.delivery_payment_status !== 'pending_choice';

        actions.push({
            id: "delivery_payment",
            label: hasPaymentIntent ? "Alterar Pagamento" : "Adicionar Pagamento",
            icon: "pi pi-wallet",
            colorClass: "text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-900/20",
        });

        actions.push({
            id: "print_delivery",
            label: "Relatório Motoboy",
            icon: "pi pi-file",
            colorClass: "text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800",
        });

        actions.push({
            id: "notify_driver",
            label: "Avisar Motoboy",
            icon: "pi pi-whatsapp",
            colorClass: "text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20",
        });

        actions.push({
            id: "notify_customer",
            label: "Avisar Cliente",
            icon: "pi pi-whatsapp",
            colorClass: "text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20",
        });
    }

    return actions;
});

const handleAction = (actionId) => {
    isVisible.value = false;
    emit("action", actionId, props.order);
};
</script>
