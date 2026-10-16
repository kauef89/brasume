<template>
    <!-- OVERLAYS (MODIFIERS E COMBOS) -->
    <Transition name="page-fade">
        <div v-if="isModifiersVisible || isComboVisible" class="fixed inset-0 z-50 bg-surface-1 flex flex-col">
            <ProductModifiers
                v-if="isModifiersVisible"
                :product="activeProduct"
                :is-edit-mode="isEditMode"
                :is-team-mode="true"
                @update:total="$emit('update:total', $event)"
                @confirm="$emit('confirm-modifiers', $event)"
                @close="$emit('close-configurators')"
            />
            <ComboWizard
                v-else-if="isComboVisible"
                :product="activeProduct"
                :global-products-list="initialProducts"
                :is-edit-mode="isEditMode"
                :is-team-mode="true"
                @update:total="$emit('update:total', $event)"
                @confirm-combo="$emit('confirm-combo', $event)"
                @close="$emit('close-configurators')"
            />
        </div>
    </Transition>

    <OrderContextMenu
        :visible="contextMenuVisible"
        @update:visible="$emit('update:contextMenuVisible', $event)"
        :show-actions="showOrderActionsSheet"
        @update:show-actions="$emit('update:showOrderActionsSheet', $event)"
        :order="selectedOrderContext"
        :company-settings="companySettings"
        @action="(actionId, orderObj) => $emit('action', actionId, orderObj)"
        @duplicate-item="(item, cb) => $emit('duplicate-item', item, cb)"
        @modify-item="$emit('modify-item', $event)"
        @swap-item="$emit('swap-item', $event)"
        @cancel-item="$emit('cancel-item', $event)"
        @reprint-item="$emit('reprint-item', $event)"
    />
</template>

<script setup>
import ProductModifiers from "@/Pages/Shared/Configurators/ProductModifiers.vue";
import ComboWizard from "@/Pages/Shared/Configurators/ComboWizard.vue";
import OrderContextMenu from "@/Pages/Shared/Order/OrderContextMenu.vue";

defineProps({
    isModifiersVisible: Boolean,
    isComboVisible: Boolean,
    activeProduct: Object,
    isEditMode: Boolean,
    initialProducts: Array,
    contextMenuVisible: Boolean,
    showOrderActionsSheet: Boolean,
    selectedOrderContext: Object,
    companySettings: Object
});

defineEmits([
    'update:total',
    'confirm-modifiers', 'confirm-combo', 'close-configurators',
    'update:contextMenuVisible', 'update:showOrderActionsSheet',
    'action', 'duplicate-item', 'modify-item', 'swap-item', 'cancel-item', 'reprint-item'
]);
</script>

<style scoped>
.page-fade-enter-active, .page-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.page-fade-enter-from, .page-fade-leave-to { opacity: 0; transform: scale(0.98); }
</style>
