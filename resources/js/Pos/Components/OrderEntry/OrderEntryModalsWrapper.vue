<template>
    <ProductModifiersDrawer
        v-model:visible="internalIsModifiersVisible"
        :product="activeProduct"
        :edit-index="editItemIndex"
        @confirm="handleConfirm"
    />

    <ComboWizardDrawer
        v-model:visible="internalIsComboVisible"
        :product="activeProduct"
        :edit-index="editItemIndex"
        @confirm-combo="handleComboConfirm"
    />
</template>

<script setup>
import { computed } from "vue";
import ProductModifiersDrawer from "./ProductModifiersDrawer.vue";
import ComboWizardDrawer from "./ComboWizardDrawer.vue";

const props = defineProps({
    isModifiersVisible: Boolean,
    isComboVisible: Boolean,
    activeProduct: Object,
    editItemIndex: Number,
});

const emit = defineEmits([
    "update:isModifiersVisible",
    "update:isComboVisible",
    "confirm-modifiers",
    "confirm-combo",
]);

const internalIsModifiersVisible = computed({
    get: () => props.isModifiersVisible,
    set: (value) => emit("update:isModifiersVisible", value),
});

const internalIsComboVisible = computed({
    get: () => props.isComboVisible,
    set: (value) => emit("update:isComboVisible", value),
});

const handleConfirm = (configuredItem) => {
    emit("confirm-modifiers", configuredItem);
};

const handleComboConfirm = (configuredCombo) => {
    emit("confirm-combo", configuredCombo);
};
</script>
