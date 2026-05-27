<template>
    <div
        class="flex items-center p-3 rounded-xl border transition-all"
        :class="
            item.selected_parts > 0
                ? 'bg-white dark:bg-gray-800 border-blue-500 ring-1 ring-blue-500 shadow-md'
                : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-800 shadow-sm opacity-60'
        "
    >
        <div
            @click.stop="$emit('toggle-select', item)"
            class="flex items-center justify-center w-6 h-6 rounded border-2 transition-colors cursor-pointer shrink-0 mr-3"
            :class="
                item.selected_parts > 0
                    ? 'bg-blue-500 border-blue-500 text-white'
                    : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-blue-400'
            "
        >
            <i
                v-if="item.selected_parts > 0"
                class="pi pi-check text-[12px] font-bold"
            ></i>
        </div>

        <div
            class="flex items-center justify-between shrink-0 bg-gray-100 dark:bg-gray-700/50 rounded-lg p-1"
        >
            <Button
                icon="pi pi-minus"
                @click.stop="$emit('decrement', item)"
                variant="text"
                severity="secondary"
                class="w-8 h-8 p-0"
                :disabled="item.selected_parts === 0"
            />
            <span
                class="font-black text-sm w-10 text-center text-gray-900 dark:text-white"
                title="Quantidade selecionada"
            >
                {{ formatFraction(item.selected_parts, item.parts_per_unit) }}
            </span>
            <Button
                icon="pi pi-plus"
                @click.stop="$emit('increment', item)"
                variant="text"
                severity="secondary"
                class="w-8 h-8 p-0"
                :disabled="item.selected_parts >= item.available_parts"
            />
        </div>

        <div class="flex items-center justify-center shrink-0 w-14 ml-3">
            <input
                type="number"
                min="2"
                max="20"
                placeholder="÷ X"
                v-model="localDivisor"
                @keyup.enter="handleInput"
                @blur="handleInput"
                class="w-full h-9 text-center text-sm font-bold border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 focus:ring-2 focus:ring-blue-500 outline-none transition-all"
                title="Dividir este item (Pressione Enter)"
            />
        </div>

        <div class="flex-1 flex flex-col pl-3 ml-3 border-l border-gray-200 dark:border-gray-700 justify-center">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-base leading-tight" :class="item.selected_parts > 0 ? 'text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'">
                            {{ item.name }}
                        </h3>
                        <button
                            v-if="isCombo" @click.stop="isComboExpanded = !isComboExpanded"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors rounded-full p-1 flex items-center justify-center bg-gray-100 dark:bg-gray-800"
                        >
                            <i class="pi text-[10px] font-black" :class="isComboExpanded ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 mt-1 mb-0.5">
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-gray-200 dark:bg-gray-700 text-[10px] font-bold text-gray-600 dark:text-gray-300">
                            <i class="pi pi-clock text-[8px]"></i> {{ item.time }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-blue-50 dark:bg-blue-900/30 text-[10px] font-bold text-blue-600 dark:text-blue-400 truncate max-w-[100px]">
                            <i class="pi pi-user text-[8px]"></i> {{ item.operator }}
                        </span>
                    </div>

                    <div v-if="!isCombo && item.modifiers_text" class="text-xs text-gray-500 dark:text-gray-400 italic">
                        {{ item.modifiers_text }}
                    </div>

                    <div v-if="isCombo" v-show="isComboExpanded" class="flex flex-col gap-0.5 mt-1 animate-fade-in">
                <div
                    v-for="child in item.comboChildren"
                    :key="child.id"
                    class="text-xs text-gray-600 dark:text-gray-400 flex items-start gap-1"
                >
                    <span class="text-gray-300 dark:text-gray-600">└</span>
                    <div class="flex flex-col">
                        <span class="font-medium">{{ child.name }}</span>
                        <span
                            v-if="child.modifiers_text"
                            class="text-[10px] text-gray-400"
                            >{{ child.modifiers_text }}</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end shrink-0 w-24">
            <span
                class="text-lg font-black"
                :class="
                    item.selected_parts > 0 ? 'text-blue-600' : 'text-gray-500'
                "
            >
                {{
                    formatCurrency(
                        item.selected_parts *
                            (item.effective_unit_price / item.parts_per_unit),
                    )
                }}
            </span>
        </div>

        <div
            class="shrink-0 flex items-center justify-center w-8 ml-2 border-l border-dashed border-gray-200 dark:border-gray-700"
        >
            <Button
                v-if="item._is_parent_ref"
                icon="pi pi-ellipsis-v"
                variant="text"
                severity="secondary"
                class="w-8 h-8 p-0"
                rounded
                @click.stop="$emit('toggle-menu', $event, item)"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, defineProps, defineEmits } from "vue";
import Button from "primevue/button";
import { formatCurrency, formatFraction } from "@/Utils/formatters";

const props = defineProps({ item: Object });
const emit = defineEmits([
    "increment",
    "decrement",
    "divide-item",
    "toggle-menu",
    "toggle-select",
]);

const isCombo = computed(
    () => props.item.comboChildren && props.item.comboChildren.length > 0,
);
const isComboExpanded = ref(false);

const localDivisor = ref(""); // Estado local para não manchar os clones!

const handleInput = () => {
    const val = parseInt(localDivisor.value);
    if (val && val > 1) {
        emit("divide-item", props.item, val);
    }
    localDivisor.value = ""; // Limpa a caixinha logo a seguir!
};
</script>

<style scoped>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in {
    animation: fade-in 0.2s ease-out forwards;
}
</style>
