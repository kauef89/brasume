<template>
  <div class="flex flex-col bg-surface-1 border-b border-surface-border/50 transition-all group"
       :class="[
            isSelected ? 'border-primary-500 bg-primary-500/5' : 'hover:bg-surface-2',
            isTeamMode ? 'p-3' : 'p-3 rounded-xl border mb-2 shadow-sm'
       ]">

    <div class="flex items-center gap-3">
        <!-- Checkbox de Seleção -->
        <div v-if="!isTeamMode" @click="$emit('toggle-selection', item.id)" class="w-5 h-5 rounded border-2 flex items-center justify-center cursor-pointer shrink-0 transition-colors"
             :class="isSelected ? 'bg-primary-500 border-primary-500 text-white' : 'border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-800'">
          <i v-if="isSelected" class="pi pi-check text-[10px] font-medium"></i>
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex justify-between items-start">
              <h4 class="font-medium text-surface-text text-base leading-tight pr-2 flex items-start gap-1">
                  <span class="text-primary-500 font-bold shrink-0">{{ item.quantity || 1 }}x</span>
                  <span class="leading-tight">{{ item.name || item.product_name }}</span>
              </h4>
              <span class="font-black text-surface-text whitespace-nowrap shrink-0">{{ $formatCurrency(item.unit_price) }}</span>
          </div>

          <!-- Árvore de Filhos do Combo com botão de Edição individual -->
          <div v-if="hasChildren" class="flex flex-col gap-1.5 mt-2">
              <div v-for="child in childrenList" :key="child.id" class="flex flex-col bg-surface-2/40 p-2 rounded-lg border border-surface-border/40">
                  <div class="flex items-center justify-between gap-1.5 text-xs">
                      <div class="flex items-center gap-1.5 min-w-0 truncate text-surface-text font-medium">
                          <span class="text-primary-500 font-black">└</span>
                          <span class="truncate">{{ child.name || child.product_name }}</span>
                      </div>

                      <!-- 🚀 Botão Exclusivo de Edição para o Filho do Combo (Regra 1 e 2) -->
                      <button
                          type="button"
                          @click.stop="$emit('edit-child', child)"
                          class="px-2 py-0.5 rounded bg-warning-500/10 text-warning-600 hover:bg-warning-500 hover:text-white transition-colors text-[10px] font-bold flex items-center gap-1 shrink-0 outline-none border-none cursor-pointer"
                          title="Alterar adicionais deste item do combo"
                      >
                          <i class="pi pi-pencil text-[9px]"></i> Editar
                      </button>
                  </div>

                  <div v-if="getChildModsLines(child).length > 0" class="flex flex-col ml-3 border-l-2 border-surface-border/40 pl-2 mt-1">
                      <span v-for="(line, idx) in getChildModsLines(child)" :key="idx" class="text-[11px] text-surface-muted italic leading-tight">
                          {{ line }}
                      </span>
                  </div>
              </div>
          </div>

          <!-- Modificadores de Item Comum -->
          <div v-else-if="parsedModifiersLines.length > 0" class="flex flex-col gap-0.5 mt-1 pl-1">
              <span v-for="(line, idx) in parsedModifiersLines" :key="idx" class="text-[11px] text-surface-muted/80 italic leading-tight">
                  {{ line }}
              </span>
          </div>
        </div>

        <!-- AÇÕES DO ITEM -->
        <div class="flex items-center gap-1.5 shrink-0 pl-2">
            <!-- Modo Mobile ou Item com Qtd > 1: abre o menu com as abas de escopo -->
            <template v-if="isTeamMode || (item.quantity && item.quantity > 1)">
                <button
                    @click="$emit('open-actions', item)"
                    class="w-9 h-9 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text flex items-center justify-center transition-colors outline-none border border-surface-border/60 cursor-pointer"
                    :title="(item.quantity > 1) ? 'Opções (Quantidade Múltipla)' : 'Opções do Item'"
                >
                    <i class="pi pi-ellipsis-v text-sm"></i>
                </button>
            </template>

            <!-- Modo Desktop com Quantidade = 1 -->
            <template v-else>
                <IconButton icon="pi-print" color="surface" title="Reimprimir" @click="$emit('reprint', item)" />

                <!-- 🚀 Não duplica filho de combo (Regra 1) -->
                <IconButton v-if="!isComboChild" icon="pi-plus-circle" color="success" title="Adicionar Igual" @click="$emit('duplicate', item)" />

                <!-- 🚀 Não edita o combo pai diretamente (Regra 2) -->
                <IconButton v-if="!hasChildren" icon="pi-pencil" color="warning" title="Editar" @click="$emit('edit', item)" />

                <IconButton v-if="orderType !== 'delivery'" icon="pi-sync" color="primary" title="Transferir" @click="$emit('transfer', item)" />

                <!-- 🚀 Não cancela filho de combo (Regra 1) -->
                <IconButton v-if="!isComboChild" icon="pi-trash" color="danger" title="Estornar" @click="$emit('cancel', item)" />
            </template>
        </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { formatCurrency } from "@/Utils/formatters";
import IconButton from "@/Components/UI/IconButton.vue";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

const props = defineProps({
    item: Object,
    orderType: String,
    isSelected: Boolean,
    isTeamMode: { type: Boolean, default: false }
});

const emit = defineEmits([
    'toggle-selection', 'reprint', 'duplicate', 'edit',
    'transfer', 'cancel', 'open-actions', 'edit-child'
]);

const { formatModifiersList } = useOrderFormatting();

const hasChildren = computed(() => {
    return Boolean(props.item.hasChildren || (props.item.comboChildren && props.item.comboChildren.length > 0));
});

const childrenList = computed(() => {
    return props.item.comboChildren || props.item.sub_items || props.item.subItems || [];
});

const isComboChild = computed(() => {
    return Boolean(props.item.parent_item_id || props.item.is_combo_child);
});

const parsedModifiersLines = computed(() => formatModifiersList(props.item));
const getChildModsLines = (child) => formatModifiersList(child);
</script>
