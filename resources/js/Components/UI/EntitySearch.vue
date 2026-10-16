<template>
    <div class="relative w-full z-50" ref="containerRef">

        <!-- 1. ITEM SELECIONADO (BADGE/CARD) -->
        <div v-if="hasSelection" class="relative group">
            <slot
                name="selected"
                :item="modelValue"
                :display-label="displayLabel"
                :display-subtitle="displaySubtitle"
                :clear="handleClear"
            >
                <!-- Layout Padrão do Item Selecionado -->
                <div
                    class="w-full flex items-center justify-between p-3 rounded-2xl transition-all cursor-pointer bg-primary-500/10 hover:bg-primary-500/20 border border-primary-500/20"
                    @click="$emit('confirm', modelValue)"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-primary-500 flex items-center justify-center text-white font-black shrink-0 text-xs shadow-sm">
                            <i v-if="resolvedIcon" :class="`pi ${resolvedIcon} text-sm`"></i>
                            <span v-else>{{ getInitials(displayLabel) }}</span>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="font-bold text-primary-700 dark:text-primary-300 leading-tight truncate text-sm">
                                {{ displayLabel }}
                            </span>
                            <span v-if="displaySubtitle" class="text-xs font-medium uppercase text-primary-600/70 dark:text-primary-400/70 truncate">
                                {{ displaySubtitle }}
                            </span>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click.stop="handleClear"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-primary-400 hover:bg-red-500/10 hover:text-red-500 transition-all outline-none border-none bg-transparent cursor-pointer shrink-0 ml-2"
                        title="Limpar seleção"
                    >
                        <i class="pi pi-times text-xs font-medium"></i>
                    </button>
                </div>
            </slot>
        </div>

        <!-- 2. CAMPO DE PESQUISA & DROPDOWN -->
        <div
            v-else
            class="transition-all duration-300 ease-in-out bg-surface-3 rounded-2xl overflow-hidden border border-surface-border/40"
            :class="{
                'shadow-2xl bg-surface-2 -translate-y-0.5 border-primary-500/40 ring-2 ring-primary-500/20': showDropdown && query.length >= minSearchLength,
                'ring-2 ring-primary-500/30': showDropdown
            }"
        >
            <div class="relative flex items-center">
                <span class="absolute left-4 pointer-events-none">
                    <i class="pi text-surface-muted text-xs" :class="isLoading ? 'pi-spinner pi-spin' : searchIcon"></i>
                </span>

                <input
                    ref="inputRef"
                    :value="query"
                    @input="handleInput"
                    @focus="showDropdown = true"
                    @keydown.enter.prevent="handleEnter"
                    @keydown.esc="showDropdown = false"
                    type="text"
                    class="w-full pl-11 pr-10 py-3.5 bg-transparent border-none outline-none text-sm text-surface-text placeholder:text-surface-muted/50"
                    :placeholder="placeholder"
                    autocomplete="off"
                />

                <button
                    v-if="query"
                    type="button"
                    @click="query = ''; $emit('search', '')"
                    class="absolute right-3 w-6 h-6 rounded-full flex items-center justify-center text-surface-muted hover:text-surface-text border-none bg-transparent cursor-pointer"
                >
                    <i class="pi pi-times text-xs"></i>
                </button>
            </div>

            <!-- RESULTADOS -->
            <div
                v-if="showDropdown && query.length >= minSearchLength"
                class="border-t border-surface-border/20 max-h-72 overflow-y-auto hide-scrollbar animate-expand-vertical"
            >
                <ul>
                    <!-- Itens Retornados -->
                    <li
                        v-for="(item, index) in results"
                        :key="resolveKey(item, index)"
                        @click="handleSelect(item)"
                        class="px-5 py-3 hover:bg-surface-3 cursor-pointer flex items-center justify-between transition-colors border-b border-surface-border/5"
                    >
                        <slot name="item" :item="item" :select="() => handleSelect(item)">
                            <div class="flex flex-col min-w-0">
                                <span class="font-medium text-surface-text text-sm truncate">
                                    {{ resolveValue(item, labelKey) }}
                                </span>
                                <span v-if="resolveValue(item, subtitleKey)" class="text-xs font-medium text-surface-muted mt-0.5 truncate">
                                    {{ resolveValue(item, subtitleKey) }}
                                </span>
                            </div>
                        </slot>
                        <i class="pi pi-chevron-right text-surface-muted/20 text-[10px] shrink-0 ml-2"></i>
                    </li>

                    <!-- Ação: Usar Texto Digitado / Valor Avulso -->
                    <li
                        v-if="allowCustom && query.trim().length > 0"
                        @click="handleCustomSelect"
                        class="px-5 py-3.5 bg-primary-500/5 hover:bg-primary-500/10 cursor-pointer flex items-center gap-3 transition-colors border-t border-surface-border/10 group"
                    >
                        <div class="w-8 h-8 rounded-full bg-primary-500/20 text-primary-600 flex items-center justify-center group-hover:bg-primary-500 group-hover:text-white transition-all shrink-0">
                            <i class="pi pi-check text-xs"></i>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-xs font-black text-primary-600 uppercase">{{ customActionLabel }}</span>
                            <span class="text-sm font-medium text-surface-text truncate">"{{ query }}"</span>
                        </div>
                    </li>

                    <!-- Ação: Cadastrar Novo Registro -->
                    <li
                        v-if="allowCreate && query.trim().length > 0"
                        @click="handleCreate"
                        class="px-5 py-3.5 bg-primary-500/5 hover:bg-primary-500/10 cursor-pointer flex items-center gap-3 transition-colors border-t border-surface-border/10 group"
                    >
                        <div class="w-8 h-8 rounded-full bg-primary-500/20 text-primary-600 dark:text-primary-300 flex items-center justify-center group-hover:bg-primary-500/70 group-hover:text-white transition-all shrink-0">
                            <i class="pi pi-plus text-xs"></i>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-xs font-black text-primary-700 dark:text-primary-300 uppercase">{{ createActionLabel }}</span>
                            <span class="text-sm font-medium text-surface-text truncate">"{{ query }}"</span>
                        </div>
                    </li>

                    <!-- Estado Vazio -->
                    <li
                        v-if="results.length === 0 && !allowCustom && !allowCreate"
                        class="px-5 py-4 text-center text-xs text-surface-muted font-medium"
                    >
                        <slot name="empty" :query="query">
                            Nenhum resultado encontrado para "{{ query }}".
                        </slot>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";

const props = defineProps({
    modelValue: { type: [Object, String, Number, null], default: null },
    results: { type: Array, default: () => [] },
    isLoading: { type: Boolean, default: false },

    // Chaves de resolução de dados
    labelKey: { type: [String, Function], default: "name" },
    subtitleKey: { type: [String, Function], default: null },
    itemKey: { type: [String, Function], default: "id" },
    iconKey: { type: [String, Function], default: null },

    // Configurações de Input
    placeholder: { type: String, default: "Digite para pesquisar..." },
    searchIcon: { type: String, default: "pi-search" },
    minSearchLength: { type: Number, default: 2 },
    debounceTime: { type: Number, default: 250 },

    // Habilidades Opcionais
    allowCustom: { type: Boolean, default: false },
    customActionLabel: { type: String, default: "Usar termo digitado" },
    allowCreate: { type: Boolean, default: false },
    createActionLabel: { type: String, default: "Cadastrar novo registro" },
});

const emit = defineEmits([
    "update:modelValue",
    "select",
    "search",
    "custom",
    "create",
    "confirm",
    "clear"
]);

const containerRef = ref(null);
const inputRef = ref(null);
const query = ref("");
const showDropdown = ref(false);
let debounceTimeout = null;

const hasSelection = computed(() => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== "");

const resolveValue = (item, resolver) => {
    if (!item) return "";
    if (typeof resolver === "function") return resolver(item);
    if (typeof item === "object" && resolver) return item[resolver] ?? "";
    return String(item);
};

const resolveKey = (item, index) => {
    const key = resolveValue(item, props.itemKey);
    return key !== "" ? key : index;
};

const displayLabel = computed(() => {
    if (!props.modelValue) return "";
    return resolveValue(props.modelValue, props.labelKey);
});

const displaySubtitle = computed(() => {
    if (!props.modelValue) return "";
    return resolveValue(props.modelValue, props.subtitleKey);
});

const resolvedIcon = computed(() => {
    if (!props.modelValue) return null;
    return resolveValue(props.modelValue, props.iconKey);
});

const getInitials = (text) => {
    if (!text || typeof text !== "string") return "?";
    return text.trim().split(" ").slice(0, 2).map(w => w[0]).join("").toUpperCase();
};

const handleInput = (event) => {
    query.value = event.target.value;
    showDropdown.value = true;

    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        emit("search", query.value);
    }, props.debounceTime);
};

const handleSelect = (item) => {
    showDropdown.value = false;
    query.value = "";
    emit("update:modelValue", item);
    emit("select", item);
};

const handleCustomSelect = () => {
    const customValue = query.value.trim();
    showDropdown.value = false;
    query.value = "";
    emit("update:modelValue", customValue);
    emit("custom", customValue);
};

const handleCreate = () => {
    const value = query.value.trim();
    showDropdown.value = false;
    emit("create", value);
};

const handleEnter = () => {
    if (props.results.length > 0) {
        handleSelect(props.results[0]);
    } else if (props.allowCustom && query.value.trim()) {
        handleCustomSelect();
    }
};

const handleClear = () => {
    query.value = "";
    emit("update:modelValue", null);
    emit("clear");
    setTimeout(() => {
        showDropdown.value = true;
        inputRef.value?.focus();
    }, 50);
};

const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        showDropdown.value = false;
    }
};

onMounted(() => document.addEventListener("click", handleClickOutside));
onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
    clearTimeout(debounceTimeout);
});
</script>

<style scoped>
.animate-expand-vertical {
    animation: expandVertical 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    transform-origin: top;
}
@keyframes expandVertical {
    from { opacity: 0; transform: scaleY(0.96); }
    to { opacity: 1; transform: scaleY(1); }
}
</style>
