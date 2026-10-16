<template>
    <div class="h-full w-full flex flex-col overflow-hidden relative select-none bg-surface-1">

        <!-- 🚀 1. BARRA DE TOPO DO KDS (Controlo de Praça e Configurações) -->
        <div v-if="!readonly" class="px-4 py-2.5 bg-surface-2 border-b border-surface-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="text-xs font-black uppercase tracking-wider text-surface-muted">
                    {{ isBarMode ? 'Modo Copa & Bar' : (activeKitchenObj?.name || 'Visão Global') }}
                </span>
                <span class="w-1.5 h-1.5 rounded-full bg-success-500 animate-pulse"></span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    v-if="!isBarMode && activeKitchenId !== 'all'"
                    type="button"
                    @click="requestSettingsAccess"
                    class="h-8 px-3 rounded-lg bg-surface-1 hover:bg-surface-3 text-surface-muted hover:text-surface-text border border-surface-border text-xs font-bold transition-colors flex items-center gap-1.5 cursor-pointer outline-none"
                    title="Configurar Colunas da Praça"
                >
                    <i class="pi pi-sliders-h text-xs"></i>
                    <span class="hidden sm:inline">Colunas</span>
                </button>
            </div>
        </div>

        <!-- 🚀 2. CORPO PRINCIPAL -->
        <div class="flex-1 min-h-0 overflow-hidden relative p-3 md:p-4">

            <!-- 🍸 MODO A: VISTA COMPACTA DO BAR (Lista Única Ordenada por Tempo) -->
            <div v-if="isBarMode" class="h-full overflow-y-auto hide-scrollbar flex flex-col gap-3 max-w-2xl mx-auto">
                <div v-if="barItems.length === 0" class="h-full flex flex-col items-center justify-center text-center opacity-40 py-16">
                    <i class="pi pi-check-circle text-4xl text-surface-muted mb-2"></i>
                    <p class="text-sm font-bold text-surface-text">Nenhuma bebida pendente</p>
                    <span class="text-xs text-surface-muted">Aguardando novos pedidos do salão e balcão.</span>
                </div>

                <KdsBarCard
                    v-for="item in barItems"
                    :key="item.id"
                    :item="item"
                    :current-time="currentTime"
                    @complete="handleCompleteBarItem"
                    @print="handlePrintItem"
                    @loss="openLossModal"
                    @cancel="openCancelModal"
                />
            </div>

            <!-- 🍳 MODO B: QUADRO KANBAN DA COZINHA (Colunas Drag & Drop) -->
            <div v-else class="h-full overflow-x-auto hide-scrollbar">
                <div class="flex h-full gap-3 min-w-[750px] lg:min-w-0">
                    <div
                        v-for="col in activeColumns"
                        :key="col.id"
                        class="flex-1 flex flex-col min-w-[260px] max-w-[380px] bg-surface-2 rounded-2xl border border-surface-border overflow-hidden h-full shadow-xs"
                    >
                        <!-- Cabeçalho da Coluna -->
                        <div class="px-4 py-3 bg-surface-3/70 border-b border-surface-border flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <h3 class="font-black text-xs uppercase tracking-wider text-surface-text truncate">
                                    {{ col.label }}
                                </h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-surface-1 text-surface-muted border border-surface-border">
                                    {{ columnsData[col.id]?.length || 0 }}
                                </span>
                            </div>

                            <!-- Botão Limpar Prontos -->
                            <button
                                v-if="col.is_final && !readonly && (columnsData[col.id]?.length || 0) > 0"
                                type="button"
                                @click="clearReadyItems(col.id)"
                                class="text-[10px] font-black uppercase text-success-600 dark:text-success-400 hover:underline bg-transparent border-none cursor-pointer outline-none"
                            >
                                Limpar
                            </button>
                        </div>

                        <!-- Área de Itens da Coluna -->
                        <draggable
                            v-model="columnsData[col.id]"
                            group="kds-items"
                            item-key="id"
                            class="flex-1 overflow-y-auto p-2.5 flex flex-col gap-2.5 hide-scrollbar min-h-0"
                            ghost-class="opacity-40"
                            :disabled="readonly"
                            @change="onDragChange($event, col.id)"
                        >
                            <template #item="{ element: item }">
                                <KdsItemCard
                                    :item="item"
                                    :is-global-view="activeKitchenId === 'all'"
                                    :kitchen-name="getKitchenName(item.kitchen_id)"
                                    :readonly="readonly"
                                    :current-time="currentTime"
                                    @print="handlePrintItem"
                                    @loss="openLossModal"
                                    @cancel="openCancelModal"
                                />
                            </template>
                        </draggable>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🚀 3. MODAIS OPERACIONAIS -->
        <KdsColumnSettingsModal
            v-model:visible="isSettingsOpen"
            :columns="tempColumns"
            @update:columns="tempColumns = $event"
            @add="addColumn"
            @remove="removeColumn"
            @set-final="setFinalColumn"
            @save="saveColumns"
        />

        <KdsLossModal
            v-model:visible="isLossModalVisible"
            :item="selectedKdsItem"
            @confirm="submitLoss"
        />

        <KdsCancelModal
            v-model:visible="isCancelModalVisible"
            :item="selectedKdsItem"
            @confirm="submitCancel"
        />

        <ManagerAuthModal
            v-model:visible="isAuthModalVisible"
            @confirm="handleAuthConfirm"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import draggable from "vuedraggable";
import { useToast } from "primevue/usetoast";

import ManagerAuthModal from "@/Pages/Gateway/Components/ManagerAuthModal.vue";
import KdsItemCard from "@/Pages/Operations/Components/Kds/KdsItemCard.vue";
import KdsBarCard from "@/Pages/Operations/Components/Kds/KdsBarCard.vue";
import KdsColumnSettingsModal from "@/Pages/Operations/Components/Kds/KdsColumnSettingsModal.vue";
import KdsLossModal from "@/Pages/Operations/Components/Kds/KdsLossModal.vue";
import KdsCancelModal from "@/Pages/Operations/Components/Kds/KdsCancelModal.vue";
import { useKds } from "@/Pages/Kds/Composables/useKds";

const props = defineProps({
    kitchens: { type: Array, default: () => [] },
    activeKitchenId: { type: [Number, String], default: 'all' },
    initialItems: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
});

const page = usePage();
const toast = useToast();

const {
    isSettingsOpen, tempColumns, activeColumns, columnsData, currentTime,
    openSettings, addColumn, removeColumn, setFinalColumn, saveColumns,
    onDragChange, clearReadyItems
} = useKds(props);

// Avaliação do Modo Bar vs Modo Cozinha
const activeKitchenObj = computed(() => {
    if (props.activeKitchenId === 'all') return null;
    return props.kitchens.find(k => String(k.id) === String(props.activeKitchenId));
});

const isBarMode = computed(() => activeKitchenObj.value?.type === 'bar');

const barItems = computed(() => {
    let items = [];
    Object.values(columnsData.value).forEach(colArray => {
        items = items.concat(colArray);
    });
    return items.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

// Ações Conectadas às Rotas do Ziggy
const handleCompleteBarItem = (item) => {
    router.post(route('operations.kds.update-status', item.id), { production_status: 'delivered' }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            void(0)
        }
    });
};

const isLossModalVisible = ref(false);
const isCancelModalVisible = ref(false);
const selectedKdsItem = ref(null);

const handlePrintItem = (item) => {
    router.post(route('operations.kds.print-item', item.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            void(0)
        }
    });
};

const openLossModal = (item) => {
    selectedKdsItem.value = item;
    isLossModalVisible.value = true;
};

const openCancelModal = (item) => {
    selectedKdsItem.value = item;
    isCancelModalVisible.value = true;
};

const submitLoss = (lossData) => {
    router.post(route('operations.kds.loss', selectedKdsItem.value.id), lossData, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            isLossModalVisible.value = false;
            toast.add({ severity: 'success', summary: 'Perda Registrada', detail: 'Desperdício debitado do estoque.', life: 3000 });
        }
    });
};

const submitCancel = (cancelData) => {
    router.post(route('operations.kds.cancel', selectedKdsItem.value.id), cancelData, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            isCancelModalVisible.value = false;
            toast.add({ severity: 'success', summary: 'Item Cancelado', detail: 'Comanda atualizada com sucesso.', life: 3000 });
        }
    });
};

const getKitchenName = (id) => {
    if (!id) return 'Sem Praça';
    const kit = props.kitchens?.find(k => String(k.id) === String(id));
    return kit ? kit.name : '';
};

// Autorização do Gestor para Configurar Colunas
const isAuthModalVisible = ref(false);
const requestSettingsAccess = () => { isAuthModalVisible.value = true; };
const handleAuthConfirm = (authData) => {
    isAuthModalVisible.value = false;
    if (authData?.password && authData.password.length >= 3) {
        openSettings();
    }
};

onMounted(() => {
    const tenantId = page.props.tenantId || page.props.tenant?.id || window.Laravel?.tenant_id || 'central';

    if (window.Echo) {
        window.Echo.channel(`tenant.${tenantId}.kds`)
            .listen('.items.created', (e) => {
                (e.items || []).forEach(newItem => {
                    if (props.activeKitchenId !== 'all' && String(newItem.kitchen_id) !== String(props.activeKitchenId)) {
                        return;
                    }

                    const targetStatus = newItem.production_status || 'pending';
                    if (columnsData.value && columnsData.value[targetStatus]) {
                        const exists = columnsData.value[targetStatus].some(item => item.id === newItem.id);
                        if (!exists) {
                            columnsData.value[targetStatus].unshift(newItem);
                        }
                    }
                });
            });
    }
});

onUnmounted(() => {
    const tenantId = page.props.tenantId || page.props.tenant?.id || window.Laravel?.tenant_id || 'central';
    if (window.Echo) {
        window.Echo.leaveChannel(`tenant.${tenantId}.kds`);
    }
});
</script>
