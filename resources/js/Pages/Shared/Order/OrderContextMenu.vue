<template>
  <!-- 🚀 A TELA MOBILE DO PEDIDO ("ABA VIRTUAL") -->
  <div v-if="isMobile && isVisible" class="fixed inset-0 z-30 bg-surface-1 flex flex-col animate-in slide-in-from-right-8 duration-300">

        <!-- CABEÇALHO (Assume o papel de Topbar do App) -->
        <div class="flex justify-between items-end px-6 pt-12 pb-4 bg-surface-1/90 backdrop-blur-md border-b border-surface-border sticky top-0 z-10 shrink-0 shadow-sm">
            <div class="flex flex-col min-w-0 pr-4">
                <span class="text-[10px] font-black text-primary-500 uppercase tracking-widest">{{ translatedType }}</span>
                <h2 class="text-2xl font-black text-surface-text leading-tight truncate">{{ formattedOrderTitle }}</h2>
            </div>
            <span class="px-2.5 py-1 rounded-lg font-black uppercase tracking-widest text-[10px] border shrink-0" :class="statusStyle(order?.status)">
                {{ translatedStatus(order?.status) }}
            </span>
        </div>

        <!-- CONTEÚDO (ITENS E TOTAIS) -->

        <OrderPixModal
            v-model:visible="isPixModalOpen"
            :order="order"
        />

        <div class="flex-1 overflow-y-auto hide-scrollbar flex flex-col pb-32 relative">

            <!-- Barra de Totais -->
            <div class="px-6 pt-4 pb-4 flex justify-between items-end shrink-0 border-b border-surface-border/30">
                <span class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Total da Conta</span>
                <span class="text-2xl font-black text-surface-text leading-tight">{{ $formatCurrency(order?.total || 0) }}</span>
            </div>

            <!-- Empty State -->
            <div v-if="!activeItemsGrouped.length" class="text-center text-surface-muted py-12 text-sm font-medium">
                <i class="pi fa-receipt text-4xl mb-4 opacity-20 block"></i>
                Ainda não há itens nesta conta.
            </div>

            <!-- Lista de Itens Ativos -->
            <div v-else class="flex flex-col p-4 pt-2 gap-2">
                <OrderContextItem
                    v-for="item in activeItemsGrouped"
                    :key="item.id"
                    :item="item"
                    :order-type="order?.type"
                    :is-selected="false"
                    :is-team-mode="true"
                    @open-actions="openItemActionsSheet"
                />
            </div>
        </div>
  </div>

  <!-- DIALOG DESKTOP -->
  <Dialog v-else v-model:visible="isVisible" modal class="w-[1050px]" :closable="true" :draggable="false">
    <template #header>
      <div class="flex flex-col gap-3 w-full pr-6">
        <div class="flex items-center gap-4">
          <h2 class="text-2xl font-black text-stone-800 dark:text-stone-100">{{ formattedOrderTitle }}</h2>
          <div class="flex items-center gap-2">
              <span class="text-[10px] md:text-xs font-medium text-stone-500 uppercase tracking-widest bg-stone-100 dark:bg-stone-800 px-2.5 py-1 rounded-md border border-stone-200 dark:border-stone-700">
                  #{{ $formatNumber(order?.id) }}
              </span>
              <span class="text-[10px] md:text-xs font-medium text-stone-500 uppercase tracking-widest bg-stone-100 dark:bg-stone-800 px-2.5 py-1 rounded-md border border-stone-200 dark:border-stone-700">
                  {{ translatedType }}
              </span>
              <span
                  class="px-2.5 py-1 rounded-lg font-black uppercase tracking-widest text-[10px] border"
                  :class="statusStyle(order?.status)"
              >
                  {{ translatedStatus(order?.status) }}
              </span>
              <span v-if="order?.type === 'delivery' && order?.address" class="text-[10px] md:text-xs font-medium text-stone-500 uppercase tracking-widest bg-stone-100 dark:bg-stone-800 px-2.5 py-1 rounded-md flex items-center gap-1 border border-stone-200 dark:border-stone-700">
                  <i class="pi fa-location-dot text-primary-500"></i> {{ formatAddress(order) }}
              </span>
          </div>

          <span v-if="isPaid" class="text-sm font-black text-green-700 bg-green-100 dark:bg-green-900/40 px-2.5 py-1 rounded-md uppercase tracking-widest flex items-center gap-1 border border-green-200 dark:border-green-800">
            <i class="pi pi-check-circle"></i> Pago
          </span>
        </div>
      </div>
    </template>

    <div class="flex gap-6 mt-4 relative min-h-0">
      <div class="flex-1 flex flex-col border-r border-stone-200 dark:border-stone-800 pr-6 overflow-hidden relative max-h-[75vh]">
        <div class="flex justify-between items-center mb-3 border-b border-stone-100 dark:border-stone-800 pb-2 shrink-0">
          <h3 class="text-sm font-black text-stone-500 uppercase tracking-widest">Itens Ativos</h3>
          <button v-if="activeItemsGrouped.length > 0" @click="toggleSelectAllItems" class="text-xs font-medium text-primary-smart hover:underline outline-none cursor-pointer">
            {{ selectedItemIds.length === activeItemsGrouped.length ? 'Desmarcar Todos' : 'Selecionar Todos' }}
          </button>
        </div>

        <div class="flex-1 overflow-y-auto pr-2 flex flex-col gap-2 hide-scrollbar pb-20">
          <div v-if="!activeItemsGrouped.length" class="text-center text-stone-400 py-4 text-sm">Nenhum item ativo neste pedido.</div>

          <OrderContextItem
            v-for="item in activeItemsGrouped"
            :key="item.id"
            :item="item"
            :order-type="order?.type"
            :is-selected="selectedItemIds.includes(item.id)"
            @toggle-selection="toggleItemSelection"
            @reprint="$emit('reprint-item', item)"
            @transfer="openSingleTransfer"
            @duplicate="$emit('duplicate-item', item)"
            @edit="initiateEdit"
            @cancel="initiateCancellation"
            @swap="initiateSwap"
          />
        </div>

        <div v-if="selectedItemIds.length > 0" class="absolute bottom-2 left-0 right-6 bg-stone-900 text-white p-4 rounded-2xl flex items-center justify-between shadow-2xl animate-in slide-in-from-bottom-4 duration-300 z-30">
          <span class="text-sm font-medium">
            <span class="text-primary-smart font-black mr-1">{{ $formatNumber(selectedItemIds.length) }}</span> {{ selectedItemIds.length === 1 ? 'item selecionado' : 'itens selecionados' }}
          </span>
          <div class="flex items-center gap-2">
            <button @click="executeBulkAction('print')" class="px-3 py-2 bg-stone-800 hover:bg-stone-700 rounded-xl text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer"><i class="pi pi-print text-stone-400"></i> Imprimir</button>
            <button @click="executeBulkAction('transfer')" class="px-3 py-2 bg-primary-500 hover:bg-primary-600 rounded-xl text-xs font-black flex items-center gap-1.5 transition-colors cursor-pointer"><i class="pi pi-sync"></i> Transferir</button>
            <button @click="executeBulkAction('delete')" class="px-3 py-2 bg-red-600 hover:bg-red-700 rounded-xl text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer"><i class="pi pi-trash"></i> Excluir</button>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-stone-200 dark:border-stone-800 flex justify-between items-center shrink-0">
          <span class="text-xs font-black text-stone-500 uppercase tracking-widest">Total do Pedido</span>
          <span class="text-xl font-black text-primary-smart">{{ $formatCurrency(order?.total || 0) }}</span>
        </div>
      </div>

      <div class="w-80 shrink-0 flex flex-col overflow-y-auto max-h-[75vh] hide-scrollbar pr-1">
        <h3 class="text-sm font-black text-stone-500 uppercase tracking-widest mb-3 border-b border-stone-100 dark:border-stone-800 pb-2 shrink-0">Ações no Pedido</h3>

        <OrderActionsGrid :order="order" :is-team-mode="false" @action="handleAction" />
      </div>
    </div>
  </Dialog>

  <!-- GAVETA MOBILE DE AÇÕES DO PEDIDO -->
  <Drawer v-if="isMobile" v-model:visible="isOrderActionsOpen" position="bottom" class="h-auto! max-h-[90vh]! rounded-t-[2rem]! bg-surface-1 shadow-2xl" :show-close-icon="false" :pt="{ header: { class: 'hidden' } }">
      <div class="flex flex-col gap-4">
          <div class="flex justify-between items-center bg-transparent shrink-0">
              <h2 class="text-lg font-black text-surface-text leading-tight">{{ getOrderTitle(order) }}</h2>
              <button @click="isOrderActionsOpen = false" class="w-8 h-8 rounded-full bg-surface-2 text-surface-muted hover:text-surface-text flex items-center justify-center outline-none transition-colors cursor-pointer"><i class="pi pi-times text-sm font-medium"></i></button>
          </div>

          <OrderActionsGrid :order="order" :is-team-mode="true" @action="handleAction" />
      </div>
  </Drawer>

  <!-- GAVETA MOBILE DE AÇÕES DO ITEM -->
  <Drawer v-if="isMobile" v-model:visible="isItemActionsOpen" position="bottom" class="h-auto! max-h-[90vh]! rounded-t-[2rem]! bg-surface-1 shadow-2xl" :show-close-icon="false" :pt="{ header: { class: 'hidden' } }">
        <div class="flex flex-col gap-4">
            <div class="flex justify-between items-center bg-transparent shrink-0">
                <div class="flex flex-col min-w-0 pr-4">
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 truncate">Opções do Item</span>
                    <h2 class="text-lg font-black text-surface-text leading-tight line-clamp-2 mt-0.5">{{ activeItem?.product_name || activeItem?.name }}</h2>
                </div>
                <button @click="isItemActionsOpen = false" class="w-8 h-8 shrink-0 rounded-full bg-surface-2 text-surface-muted hover:text-surface-text flex items-center justify-center outline-none transition-colors cursor-pointer"><i class="pi pi-times text-sm font-medium"></i></button>
            </div>

            <KdsStatus
                v-if="activeItem"
                :item="activeItem"
            />

            <div class="overflow-y-auto flex flex-col gap-3 pb-4 px-1">
                <OrderContextAction label="Reimprimir Via" subtitle="Na cozinha" icon="pi pi-print" icon-color="text-surface-muted" @click="executeMobileItemAction('reprint')" />

                <OrderContextAction label="Duplicar Item (+1)" subtitle="Acrescentar igual" icon="pi pi-plus-circle" icon-color="text-success-500" @click="executeMobileItemAction('duplicate')" :class="isDuplicating ? 'opacity-50 pointer-events-none' : ''">
                    <template v-if="isDuplicateSuccess"><span class="text-success-500 font-medium text-xs"><i class="pi pi-check"></i> Sucesso</span></template>
                </OrderContextAction>

                <!-- MENU TURBINADO PARA QUANTIDADES > 1 -->
                <template v-if="activeItem && activeItem.quantity > 1">
                    <div class="grid grid-cols-2 gap-2">
                        <OrderContextAction label="Editar 1" icon="pi pi-pencil" icon-color="text-warning-500" @click="executeMobileItemAction('edit', 1)" />
                        <OrderContextAction label="Editar Tudo" icon="pi pi-pencil" icon-color="text-warning-500" @click="executeMobileItemAction('edit', 'all')" />

                        <OrderContextAction label="Transferir 1" icon="pi pi-sync" icon-color="text-primary-500" @click="executeMobileItemAction('transfer', 1)" />
                        <OrderContextAction label="Transferir Tudo" icon="pi pi-sync" icon-color="text-primary-500" @click="executeMobileItemAction('transfer', 'all')" />

                        <OrderContextAction label="Cancelar 1" icon="pi pi-trash" icon-color="text-red-500" @click="executeMobileItemAction('cancel', 1)" />
                        <OrderContextAction label="Cancelar Tudo" icon="pi pi-trash" icon-color="text-red-500" @click="executeMobileItemAction('cancel', 'all')" />
                    </div>
                </template>

                <!-- MENU PADRÃO PARA QUANTIDADE = 1 -->
                <template v-else>
                    <OrderContextAction label="Editar Produto" subtitle="Alterar adicionais" icon="pi pi-pencil" icon-color="text-warning-500" @click="executeMobileItemAction('edit', 'all')" />
                    <OrderContextAction label="Mudar de Mesa" subtitle="Transferir item" icon="pi pi-sync" icon-color="text-primary-500" @click="executeMobileItemAction('transfer', 'all')" />
                    <OrderContextAction label="Cancelar Item" subtitle="Estornar" icon="pi pi-trash" icon-color="text-red-500" @click="executeMobileItemAction('cancel', 'all')" />
                </template>
            </div>
        </div>
  </Drawer>

  <!-- MODAL DE AUTORIZAÇÃO -->
  <OperationReasonModal
      v-model:visible="isReasonModalVisible"
      :item-name="itemToProcess?.name"
      :action-type="authActionType"
      :requires-pin="authNeedsPin"
      @confirm="handleConfirmAuth"
  />
</template>

<script setup>
import { ref, computed, watch } from "vue";
import Drawer from "primevue/drawer";
import Dialog from "primevue/dialog";
import { formatCurrency, formatNumber } from "@/Utils/formatters";

import KdsStatus from '@/Pages/Operations/Components/Kds/KdsStatus.vue';
import OrderContextItem from "@/Pages/Shared/Order/OrderContextItem.vue";
import OrderContextAction from "@/Pages/Shared/Order/OrderContextAction.vue";
import OrderActionsGrid from "@/Pages/Shared/Order/OrderActionsGrid.vue";
import OperationReasonModal from "@/Pages/Pos/Components/Checkout/OperationReasonModal.vue";
import { useSecureAction } from "@/Composables/useSecureAction";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useViewport } from "@/Composables/useViewport";
import { useWhatsAppMessages } from "@/Composables/useWhatsAppMessages";

const props = defineProps({
    visible: Boolean,
    showActions: Boolean,
    order: Object,
    companySettings: { type: Object, default: () => ({}) }
});

const emit = defineEmits(["update:visible", "update:showActions", "action", "reprint-item", "cancel-item", "transfer-items", "modify-item", "duplicate-item", "swap-item"]);

const { getOrderTitle, groupActiveItems } = useOrderFormatting();
const { isMobile } = useViewport();
const { abbreviateName, formatAddress } = useWhatsAppMessages();

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit("update:visible", value),
});

const isOrderActionsOpen = computed({
  get: () => props.showActions,
  set: (value) => emit("update:showActions", value),
});

const translatedStatus = (status) => {
    const map = {
        pending: 'Pendente',
        preparing: 'Em Preparo',
        ready: 'Pronto / Despachando',
        dispatched: 'Em Rota',
        closed: 'Concluído',
        cancelled: 'Cancelado'
    };
    return map[status] || status;
};

const statusStyle = (status) => {
    const styles = {
        pending: 'bg-warning-500/10 text-warning-600 border-warning-500/20',
        preparing: 'bg-orange-500/10 text-orange-600 border-orange-500/20',
        ready: 'bg-info-500/10 text-info-600 border-info-500/20',
        dispatched: 'bg-purple-500/10 text-purple-600 border-purple-500/20',
        closed: 'bg-success-500/10 text-success-600 border-success-500/20',
        cancelled: 'bg-danger-500/10 text-danger-600 border-danger-500/20'
    };
    return styles[status] || 'bg-surface-2 text-surface-muted border-surface-border';
};

const isItemActionsOpen = ref(false);
const activeItem = ref(null);

const isDuplicating = ref(false);
const isDuplicateSuccess = ref(false);

const openItemActionsSheet = (item) => {
    activeItem.value = item;
    isDuplicating.value = false;
    isDuplicateSuccess.value = false;
    isItemActionsOpen.value = true;
};

const executeMobileItemAction = (type, qty = 'all') => {
    if (type === 'reprint') {
        isItemActionsOpen.value = false;
        emit("reprint-item", activeItem.value);
        return;
    }

    if (type === 'duplicate') {
        isDuplicating.value = true;
        emit('duplicate-item', activeItem.value, (success) => {
            isDuplicating.value = false;
            if (success) {
                isDuplicateSuccess.value = true;
                setTimeout(() => {
                    isDuplicateSuccess.value = false;
                    isItemActionsOpen.value = false;
                }, 1000);
            }
        });
        return;
    }

    isItemActionsOpen.value = false;
    if (type === 'edit') initiateEdit(activeItem.value, qty);
    if (type === 'transfer') openSingleTransfer(activeItem.value, qty);
    if (type === 'cancel') initiateCancellation(activeItem.value, qty);
};

const selectedItemIds = ref([]);
watch(isVisible, () => { selectedItemIds.value = []; });

const orderItems = computed(() => props.order?.items || []);
const activeItemsGrouped = computed(() => groupActiveItems(orderItems.value));

const formattedOrderTitle = computed(() => {
    if (!props.order) return "";
    if (props.order.type === 'table') {
        const displayTable = props.order.table_number?.toLowerCase().includes('mesa') ? props.order.table_number : `Mesa ${props.order.table_number}`;
        if (!props.order.customer_id) return displayTable;
        return `${displayTable} (${abbreviateName(props.order.customer_name)})`;
    }
    if (props.order.customer_id) {
        return abbreviateName(props.order.customer_name);
    }
    return props.order.customer_name;
});

const toggleItemSelection = (id) => {
  const idx = selectedItemIds.value.indexOf(id);
  if (idx > -1) selectedItemIds.value.splice(idx, 1);
  else selectedItemIds.value.push(id);
};

const toggleSelectAllItems = () => {
  if (selectedItemIds.value.length === activeItemsGrouped.value.length) selectedItemIds.value = [];
  else selectedItemIds.value = activeItemsGrouped.value.map(i => i.id);
};

const openSingleTransfer = (item, qty = 'all') => {
  isVisible.value = false;
  emit("transfer-items", { order: props.order, itemIds: [item.id], qty });
};

const executeBulkAction = (actionType) => {
  if (actionType === 'transfer') {
    isVisible.value = false;
    emit("transfer-items", { order: props.order, itemIds: [...selectedItemIds.value] });
  } else if (actionType === 'print') {
    alert(`Reimprimindo produção para os itens selecionados.`);
    selectedItemIds.value = [];
  } else if (actionType === 'delete') {
    if (confirm(`Deseja realmente estornar os ${selectedItemIds.value.length} itens selecionados?`)) {
       alert('Lógica em lote acionada.');
    }
  }
};

const { isReasonModalVisible, itemToProcess, authActionType, authNeedsPin, initiateSecureAction } = useSecureAction(props.companySettings);

const initiateCancellation = (item, qty = 'all') => {
  item.action_qty = qty;
  initiateSecureAction(item, 'cancel');
};

const initiateEdit = (item, qty = 'all') => {
  item.action_qty = qty;
  initiateSecureAction(item, 'edit');
};

const initiateSwap = (item, qty = 'all') => {
  item.action_qty = qty;
  initiateSecureAction(item, 'swap');
};

const handleConfirmAuth = (authData) => {
  const qty = itemToProcess.value?.action_qty || 'all';

  if (authActionType.value === 'edit') {
      emit("modify-item", { item: itemToProcess.value, authData, action_qty: qty });
  } else if (authActionType.value === 'swap') {
      emit("swap-item", { item: itemToProcess.value, authData, action_qty: qty });
  } else {
      emit("cancel-item", {
          item: itemToProcess.value,
          action_qty: qty,
          isLoss: authData.isLoss,
          reason: authData.reason,
          password: authData.password
      });
  }
  isReasonModalVisible.value = false;
};

const isPaid = computed(() => props.order?.payment_status === 'paid');
const translatedType = computed(() => {
  const types = { table: "Mesa", counter: "Balcão", delivery: "Delivery" };
  return props.order ? types[props.order.type] : "";
});

const handleAction = (actionId) => {
  isOrderActionsOpen.value = false;
  isVisible.value = false;
  if (actionId === 'transfer_all') {
      const allItemIds = activeItemsGrouped.value.map(i => i.id);
      emit("transfer-items", { order: props.order, itemIds: allItemIds });
      return;
  }
  emit("action", actionId, props.order);
};
</script>
