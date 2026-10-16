<template>
    <div class="relative w-full z-50" ref="searchContainer">

        <div v-if="selectedCustomer" class="relative group">
            <div
                class="w-full flex items-center justify-between p-3 rounded-2xl transition-all cursor-pointer hover:bg-primary-500/20"
                @click="$emit('confirm')"
            >
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary-500 flex items-center justify-center text-white font-black shrink-0 text-xs tracking-tighter">
                        {{ getInitials(typeof selectedCustomer === 'string' ? selectedCustomer : selectedCustomer.name) }}
                    </div>
                    <div class="flex flex-col">
                        <span class="font-medium text-primary-700 dark:text-primary-300 leading-tight">
                            {{ abbreviateName(typeof selectedCustomer === 'string' ? selectedCustomer : selectedCustomer.name) }}
                        </span>
                        <span class="text-xs font-medium uppercase text-primary-500/70">
                            {{ typeof selectedCustomer === 'string' ? 'Identificador Avulso' : (formatPhone(selectedCustomer.phone) || 'Cliente Cadastrado') }}
                        </span>
                    </div>
                </div>
                <button
                    @click.stop="clearSelection"
                    type="button"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-primary-400 hover:bg-red-500/10 hover:text-red-500 transition-all outline-none border-none bg-transparent cursor-pointer"
                    title="Remover cliente"
                >
                    <i class="pi pi-times text-xs font-medium"></i>
                </button>
            </div>
        </div>

        <div
            v-else
            class="transition-all duration-300 ease-in-out bg-surface-3 rounded-2xl overflow-hidden"
            :class="{
                'shadow-2xl bg-surface-2 -translate-y-1 scale-[1.01]': showDropdown && query.length >= 2,
                'ring-3 ring-primary-600/40 dark:ring-primary-400/40': showDropdown
            }"
        >
            <div class="relative flex items-center">
                <span class="absolute left-4">
                    <i class="pi pi-search text-surface-muted text-xs" :class="{ 'pi-spin pi-spinner': isLoading }"></i>
                </span>
                <input
                    :value="query"
                    @input="handleInputEvent"
                    type="text"
                    class="w-full pl-11 pr-4 py-3.5 bg-transparent border-none outline-none text-sm text-surface-text placeholder:text-surface-muted/50 "
                    :placeholder="orderType === 'counter' ? 'Identificar cliente...' : 'Buscar cliente (Nome, Tel, CPF)...'"
                    @focus="showDropdown = true"
                    @keyup.enter="orderType === 'counter' && query.trim().length > 0 ? useSimpleId() : null"
                />
            </div>

            <div
                v-if="showDropdown && query.length >= 2"
                class="border-t border-surface-border/20 max-h-72 overflow-y-auto hide-scrollbar animate-expand-vertical"
            >
                <ul>
                    <li v-for="customer in results" :key="customer.id"
                        class="px-5 py-3 hover:bg-surface-3 cursor-pointer flex items-center justify-between transition-colors border-b border-surface-border/5"
                        @click="selectCustomer(customer)">
                        <div class="flex flex-col">
                            <span class="font-medium text-surface-text text-sm">{{ customer.name }}</span>
                            <div class="flex gap-4 mt-0.5">
                                <span v-if="customer.phone" class="text-xs font-medium text-surface-muted uppercase flex items-center gap-1">
                                    <i class="pi pi-phone text-xs"></i>
                                    {{ formatPhone(customer.phone) }}
                                </span>
                            </div>
                        </div>
                        <i class="pi pi-chevron-right text-surface-muted/20 text-[10px]"></i>
                    </li>

                    <li v-if="orderType === 'counter'"
                        @click="useSimpleId"
                        class="px-5 py-4 bg-primary-500/5 hover:bg-primary-500/10 cursor-pointer flex items-center gap-3 transition-colors group">
                        <div class="w-8 h-8 rounded-full bg-primary-500/20 text-muted flex items-center justify-center group-hover:bg-primary-500 group-hover:text-white transition-all">
                            <i class="pi fa-user text-xs"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-primary-600 uppercase">Usar identificador avulso</span>
                            <span class="text-sm font-medium text-surface-text">"{{ query }}"</span>
                        </div>
                    </li>

                    <li v-if="orderType === 'delivery'"
                        @click="$emit('create-new', query)"
                        class="px-5 py-4 bg-primary-500/5 hover:bg-primary-500/10 cursor-pointer flex items-center gap-3 transition-colors group">
                        <div class="w-8 h-8 rounded-full bg-primary-500/20 text-primary-600 dark:text-primary-300 flex items-center justify-center group-hover:bg-primary-500/70 group-hover:text-white transition-all">
                            <i class="pi fa-user-plus text-xs"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-primary-700 dark:text-primary-300">Cadastrar novo cliente</span>
                            <span class="text-sm font-medium text-surface-text">"{{ query }}"</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, watch, ref } from "vue";
import { formatPhone, abbreviateName } from "@/Utils/formatters";
import { useCustomerSearch } from "@/Pages/Customer/Composables/useCustomerSearch";
import { getInitials } from "@/Utils/formatters.js"

const props = defineProps({
    modelValue: { type: [Object, Number, String], default: null },
    initialCustomers: { type: Array, default: () => [] },
    orderType: { type: String, default: 'counter' }
});

const emit = defineEmits(["update:modelValue", "customer-selected", "create-new", "confirm"]);

const searchContainer = ref(null);
const selectedCustomer = ref(null);

const { query, results, isLoading, showDropdown, executeSearch } = useCustomerSearch(props.initialCustomers);

const handleClickOutside = (event) => {
    if (searchContainer.value && !searchContainer.value.contains(event.target)) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
    if (props.modelValue) selectedCustomer.value = props.modelValue;
});
onUnmounted(() => document.removeEventListener("click", handleClickOutside));

// 🚀 O GATILHO NATIVO ANTI-BUGS
const handleInputEvent = (event) => {
    query.value = event.target.value;
    showDropdown.value = true;
    executeSearch(query.value);
};

const selectCustomer = (customer) => {
    selectedCustomer.value = customer;
    showDropdown.value = false;
    query.value = "";
    emit("update:modelValue", customer);
    emit("customer-selected", customer);
};

const useSimpleId = () => {
    const simpleName = query.value.trim();
    selectedCustomer.value = simpleName;
    showDropdown.value = false;
    query.value = "";
    emit("update:modelValue", simpleName);
    emit("customer-selected", simpleName);
};

const clearSelection = () => {
    selectedCustomer.value = null;
    emit("update:modelValue", null);
    emit("customer-selected", null);
    setTimeout(() => { showDropdown.value = true; }, 10);
};

watch(() => props.modelValue, (newVal) => { selectedCustomer.value = newVal; });
</script>

<style scoped>
.animate-expand-vertical {
    animation: expandVertical 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    transform-origin: top;
}
@keyframes expandVertical {
    from { opacity: 0; transform: scaleY(0.95); }
    to { opacity: 1; transform: scaleY(1); }
}
</style>
