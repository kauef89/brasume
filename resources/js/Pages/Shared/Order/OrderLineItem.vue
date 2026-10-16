<template>
    <div
        ref="cardElement"
        @click="isExpanded = !isExpanded"
        class="flex flex-col gap-1 -mx-2 transition-all surface-card-ghost cursor-pointer"
        :class="{ 'is-expanded': isExpanded }"
    >
        <div class="flex justify-between items-center gap-3">
            <span class="font-medium text-surface-text flex-1 gap-2 ">
                <span class="text-surface-muted font-medium shrink-0">{{ qty || 1 }} ×</span>
                <span class="pl-1">{{ title }}</span>

                <span v-if="isTakeaway" class="badge badge-warning translate-y-[-1px] ml-1 shrink-0">
                    <i class="pi pi-shopping-bag text-[8px]"></i>
                </span>
            </span>

            <span v-if="price > 0" class="font-black text-base text-surface-text shrink-0">
                {{ isAddition ? '+ ' : '' }}{{ $formatCurrency(price) }}
            </span>
        </div>

        <div v-if="comboItems && comboItems.length > 0" class="flex flex-col gap-1 text-xs text-surface-muted mt-1">
            <div v-for="(cItem, idx) in comboItems" :key="idx" class="flex flex-col">
                <div class="flex items-start gap-1.5">
                    <span class="text-surface-border/50">└</span>
                    <span class=" text-surface-text">{{ cItem.name || cItem.product_name }}</span>
                </div>
                <div v-if="getModsLines(cItem.structuredModifiers || cItem.modifiers, cItem.quantity || cItem.qty).length > 0" class="flex flex-col ml-4 border-l-2 border-surface-border/20 pl-2 mt-0.5 mb-1">
                    <div v-for="(line, mIdx) in getModsLines(cItem.structuredModifiers || cItem.modifiers, cItem.quantity || cItem.qty)" :key="mIdx" class="text-[10px] text-surface-muted/80">
                        {{ line }}
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="parsedModifiersLines.length > 0" class="flex flex-col gap-0.5 mt-0.5 pl-1">
            <span v-for="(line, idx) in parsedModifiersLines" :key="idx" class="text-sm text-surface-muted">
                {{ line }}
            </span>
        </div>

        <div v-show="isExpanded" class="flex flex-col animate-in fade-in slide-in-from-top-1 duration-200 mt-2">
            <div class="flex justify-between items-center pt-2 border-t-2 border-surface-border/30 cursor-default" @click.stop>
                <slot name="actions"></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from "vue";
import { formatCurrency } from "@/Utils/formatters";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

const props = defineProps({
    title: String,
    qty: { type: Number, default: 1 },
    price: { type: Number, default: 0 },
    structuredModifiers: { type: Array, default: () => [] },
    comboItems: Array,
    isAddition: { type: Boolean, default: false },
    isTakeaway: { type: Boolean, default: false }
});

const emit = defineEmits(['expanded-change']);

const { formatModifiersList } = useOrderFormatting();

const isExpanded = ref(false);
const cardElement = ref(null);

watch(isExpanded, (newVal) => { emit('expanded-change', newVal); });

const parsedModifiersLines = computed(() => formatModifiersList({ structuredModifiers: props.structuredModifiers, qty: props.qty }));
const getModsLines = (rawArray, parentQty) => formatModifiersList({ structuredModifiers: rawArray, qty: parentQty || props.qty });

const handleClickOutside = (event) => {
    if (isExpanded.value && cardElement.value && !cardElement.value.contains(event.target)) {
        isExpanded.value = false;
    }
};

onMounted(() => document.addEventListener("click", handleClickOutside));
onUnmounted(() => document.removeEventListener("click", handleClickOutside));
</script>
