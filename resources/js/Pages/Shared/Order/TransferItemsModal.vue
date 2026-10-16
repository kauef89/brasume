<template>
  <component
    :is="mode === 'inline' ? 'div' : AdaptiveModal"
    v-model:visible="isVisible"
    class="flex flex-col h-full w-full"
    :class="mode === 'inline' ? 'p-6' : ''"
  >
    <!-- CABEÇALHO CENTRALIZADO -->
    <div class="flex flex-col items-center justify-center gap-3 shrink-0 mb-4 md:mb-6">
        <div class="w-14 h-14 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center">
            <i class="pi pi-sync text-2xl"></i>
        </div>
        <div class="flex flex-col items-center text-center">
            <h2 class="text-xl font-black text-surface-text leading-tight">Transferir Itens</h2>
            <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 mt-0.5">Transferência Inteligente</span>
        </div>
    </div>

    <!-- CORPO DO FORMULÁRIO -->
    <div class="flex flex-col gap-4 flex-1 overflow-y-auto hide-scrollbar">

      <div class="p-3 bg-surface-2 border border-surface-border rounded-xl text-xs font-medium text-surface-muted flex justify-between items-center shrink-0">
        <span>Origem: <strong class="text-surface-text">{{ sourceOrderTitle }}</strong></span>
        <span>Mover <strong class="text-primary-smart font-black">{{ itemIds?.length }} item(ns)</strong></span>
      </div>

      <SegmentedControl
        v-model="destType"
        :options="[
            { label: 'Para Mesa', value: 'table', icon: 'pi-th-large' },
            { label: 'Novo Balcão / Delivery', value: 'counter_delivery', icon: 'pi-shopping-bag' }
        ]"
        @update:modelValue="setDestinationType"
        class="shrink-0"
      />

      <div v-if="destType === 'table'" class="flex flex-col gap-3 flex-1 overflow-y-auto hide-scrollbar border border-surface-border/50 p-3 rounded-2xl bg-surface-2/30">
        <div v-if="!selectedTable" v-for="area in tableAreas" :key="area.id" class="flex flex-col gap-2">
          <span class="micro-label text-center block mt-1">{{ area.name }}</span>
          <div class="flex flex-wrap gap-2 justify-center">
            <button
              v-for="table in area.tables"
              :key="table.id"
              @click="handleSelectTable(table)"
              class="w-11 h-11 rounded-full text-xs font-black transition-all flex items-center justify-center relative shadow-sm border border-transparent outline-none cursor-pointer"
              :class="table.status === 'occupied'
                ? 'bg-warning-500/10 text-warning-700 hover:bg-warning-500/20'
                : 'bg-primary-500/10 text-primary-700 hover:bg-primary-500/20'"
            >
              {{ table.name }}
              <div v-if="table.status === 'occupied'" class="absolute top-0 right-0 w-2 h-2 rounded-full bg-orange-500 border border-white"></div>
            </button>
          </div>
        </div>

        <div v-if="selectedTable" class="flex flex-col gap-3 animate-in fade-in zoom-in-95 duration-200">
          <div class="flex items-center justify-between border-b border-surface-border pb-2">
            <span class="text-sm font-black text-surface-text">Destino: <strong class="text-warning-500">Mesa {{ selectedTable.name }}</strong></span>
            <button @click="selectedTable = null; selectedDestOrder = null" class="text-xs font-medium text-primary-smart hover:underline outline-none bg-transparent border-none cursor-pointer">
              Voltar para mesas
            </button>
          </div>

          <div v-if="selectedTable.active_orders?.length > 0" class="flex flex-col gap-2">
            <span class="text-[10px] font-black text-surface-muted uppercase tracking-widest text-center">Escolha a comanda de destino:</span>

            <div class="grid grid-cols-1 gap-2">
              <button v-for="order in selectedTable.active_orders" :key="order.id"
                @click="selectedDestOrder = order.id"
                class="p-3 border rounded-xl text-xs font-medium text-left transition-all flex items-center gap-2 outline-none cursor-pointer"
                :class="selectedDestOrder === order.id ? 'bg-warning-500/10 border-warning-500 text-warning-700' : 'bg-surface-1 border-surface-border hover:border-stone-400 text-surface-text'"
              >
                <i class="pi fa-receipt text-[10px]"></i> {{ order.identifier || `Pedido #${$formatNumber(order.id)}` }}
              </button>

              <button @click="selectedDestOrder = 'new_general'"
                class="p-3 border rounded-xl text-xs font-medium text-left transition-all flex items-center gap-2 outline-none cursor-pointer"
                :class="selectedDestOrder === 'new_general' ? 'bg-primary-500/10 border-primary-500 text-primary-700' : 'bg-surface-1 border-surface-border hover:border-stone-400 text-surface-text'"
              >
                <i class="pi pi-th-large text-[10px]"></i> Mover para a Comanda Principal
              </button>

              <button @click="selectedDestOrder = 'new_subaccount'"
                class="p-3 border rounded-xl text-xs font-medium text-left transition-all flex items-center gap-2 outline-none cursor-pointer"
                :class="selectedDestOrder === 'new_subaccount' ? 'bg-purple-500/10 border-purple-500 text-purple-700' : 'bg-surface-1 border-surface-border hover:border-stone-400 text-surface-text'"
              >
                <i class="pi fa-user-plus text-[10px]"></i> Criar Nova Sub-conta...
              </button>
            </div>

            <div v-if="selectedDestOrder === 'new_subaccount'" class="mt-2 animate-in slide-in-from-top-2 duration-200">
              <input
                v-model="newSubAccountName"
                type="text"
                placeholder="Nome do cliente (Ex: João)..."
                class="w-full h-10 px-3 rounded-xl bg-surface-1 border border-surface-border text-sm font-medium focus:ring-2 focus:ring-primary-500/30 outline-none text-center"
              />
            </div>
          </div>

          <div v-else class="text-center py-6 text-xs text-surface-muted bg-surface-1 border border-surface-border border-dashed rounded-xl">
            <i class="pi pi-info-circle text-primary-500 mb-1 block text-lg"></i>
            Esta mesa está livre. Uma comanda será aberta para receber os itens.
          </div>
        </div>
      </div>

      <div v-if="destType === 'counter_delivery'" class="p-4 bg-surface-2 border border-surface-border border-dashed rounded-xl flex flex-col gap-2 shrink-0 mt-auto mb-auto">
        <span class="text-xs font-medium text-surface-text text-center">Transformar em um novo atendimento avulso:</span>
        <div class="grid grid-cols-2 gap-3 mt-1">
          <button @click="selectedDestOrder = 'new_counter'" class="p-3 rounded-xl border text-center font-black text-xs uppercase tracking-wider transition-all outline-none cursor-pointer"
                  :class="selectedDestOrder === 'new_counter' ? 'bg-primary-500 text-white shadow-md' : 'bg-surface-1 text-surface-muted border-surface-border hover:bg-surface-3'">
             Balcão
          </button>
          <button @click="selectedDestOrder = 'new_delivery'" class="p-3 rounded-xl border text-center font-black text-xs uppercase tracking-wider transition-all outline-none cursor-pointer"
                  :class="selectedDestOrder === 'new_delivery' ? 'bg-primary-500 text-white shadow-md' : 'bg-surface-1 text-surface-muted border-surface-border hover:bg-surface-3'">
             Delivery
          </button>
        </div>
      </div>
    </div>

    <!-- RODAPÉ LADO A LADO -->
    <div class="flex gap-2 w-full shrink-0 mt-auto pt-6 border-t border-surface-border/50">
        <Button label="Cancelar" variant="text" severity="secondary" @click="handleCancel" class="flex-1 font-medium" />
        <Button
            label="Confirmar"
            icon="pi pi-check"
            @click="handleConfirm"
            :disabled="!canSubmit"
            class="flex-1 font-black shadow-md"
            :severity="destType === 'table' && selectedTable?.status === 'occupied' ? 'warning' : 'primary'"
        />
    </div>
  </component>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import Button from "primevue/button";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";
import SegmentedControl from "@/Components/UI/SegmentedControl.vue";
import { formatNumber } from "@/Utils/formatters";

const props = defineProps({
    visible: Boolean,
    sourceOrder: Object,
    itemIds: Array,
    tableAreas: Array,
    mode: { type: String, default: 'modal' }
});

const emit = defineEmits(["update:visible", "confirm", "cancel"]);

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit("update:visible", value),
});

const destType = ref("table");
const selectedTable = ref(null);
const selectedDestOrder = ref(null);
const newSubAccountName = ref("");

watch(isVisible, (newVal) => {
  if (newVal) {
    selectedTable.value = null;
    selectedDestOrder.value = null;
    newSubAccountName.value = "";
  }
});

const sourceOrderTitle = computed(() => {
  if (!props.sourceOrder) return "";
  return props.sourceOrder.type === 'table'
    ? `Mesa ${props.sourceOrder.table_number || ''} (${props.sourceOrder.customer_name || 'Geral'})`
    : props.sourceOrder.customer_name || `Pedido #${formatNumber(props.sourceOrder.id)}`;
});

const setDestinationType = (type) => {
    destType.value = type;
    selectedTable.value = null;
    selectedDestOrder.value = null;
};

const handleSelectTable = (table) => {
  selectedTable.value = table;
  if (!table.active_orders || table.active_orders.length === 0) {
    selectedDestOrder.value = "new_general";
  } else {
    selectedDestOrder.value = null;
  }
};

const canSubmit = computed(() => {
  if (destType.value === 'counter_delivery') return ['new_counter', 'new_delivery'].includes(selectedDestOrder.value);
  if (destType.value === 'table') {
    if (!selectedTable.value) return false;
    if (selectedDestOrder.value === 'new_subaccount') return newSubAccountName.value.trim().length > 0;
    return selectedDestOrder.value !== null;
  }
  return false;
});

const handleCancel = () => {
  emit("cancel");
  if (props.mode !== 'inline') isVisible.value = false;
};

const handleConfirm = () => {
  if (!canSubmit.value) return;

  emit("confirm", {
    source_order_id: props.sourceOrder.id,
    item_ids: props.itemIds,
    destination_type: destType.value,
    destination_table_id: selectedTable.value?.id || null,
    destination_dining_table_id: selectedTable.value?.id || null,
    destination_table_name: selectedTable.value?.name || null,
    destination_order_intent: selectedDestOrder.value,
    new_subaccount_name: newSubAccountName.value
  });

  if (props.mode !== 'inline') isVisible.value = false;
};
</script>
