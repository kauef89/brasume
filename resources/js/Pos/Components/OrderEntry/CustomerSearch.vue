<template>
    <div class="relative w-full" ref="searchContainer">
        <div v-if="selectedCustomer" class="relative group">
            <div
                class="w-full flex items-center justify-between bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 rounded-xl transition-all cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/40"
                @click="clearSelection"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-black shrink-0"
                    >
                        {{ getInitials(selectedCustomer.name) }}
                    </div>
                    <div class="flex flex-col">
                        <span
                            class="font-bold text-blue-900 dark:text-blue-100 leading-tight"
                        >
                            {{ abbreviateName(selectedCustomer.name) }}
                        </span>
                        <span class="text-xs text-blue-600 dark:text-blue-400">
                            {{
                                formatPhone(selectedCustomer.phone) ||
                                formatCpfCnpj(selectedCustomer.document) ||
                                "Sem telefone/doc"
                            }}
                        </span>
                    </div>
                </div>

                <button
                    class="text-blue-400 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-white dark:hover:bg-gray-800 opacity-0 group-hover:opacity-100"
                    title="Remover cliente"
                >
                    <i class="pi pi-times font-bold"></i>
                </button>
            </div>
        </div>

        <div v-else class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                <i
                    class="pi pi-search text-gray-400"
                    :class="{ 'pi-spin pi-spinner': isLoading }"
                ></i>
            </span>
            <input
                v-model="query"
                type="text"
                class="w-full pl-10 pr-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:text-white transition-all shadow-sm"
                placeholder="Nome, Telefone ou CPF do cliente..."
                @input="handleInput"
                @focus="showDropdown = true"
            />

            <div
                v-if="showDropdown && (results.length > 0 || query.length >= 3)"
                class="absolute z-50 w-full mt-2 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl shadow-xl max-h-64 overflow-y-auto overflow-x-hidden origin-top animate-fade-in-down"
            >
                <ul v-if="results.length > 0" class="py-1">
                    <li
                        v-for="customer in results"
                        :key="customer.id"
                        class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer flex items-center justify-between border-b border-gray-50 dark:border-gray-800/50 last:border-0 transition-colors"
                        @click="selectCustomer(customer)"
                    >
                        <div class="flex flex-col">
                            <span
                                class="font-bold text-gray-800 dark:text-gray-200"
                                >{{ customer.name }}</span
                            >
                            <div class="flex gap-3 mt-0.5">
                                <span
                                    v-if="customer.phone"
                                    class="text-xs text-gray-500"
                                >
                                    <i class="pi pi-phone text-[10px] mr-1"></i>
                                    {{ formatPhone(customer.phone) }}
                                </span>
                                <span
                                    v-if="customer.document"
                                    class="text-xs text-gray-400"
                                >
                                    <i
                                        class="pi pi-id-card text-[10px] mr-1"
                                    ></i>
                                    {{ formatCpfCnpj(customer.document) }}
                                </span>
                            </div>
                        </div>
                        <i
                            class="pi pi-chevron-right text-gray-300 text-xs"
                        ></i>
                    </li>
                </ul>

                <div
                    v-else-if="!isLoading && query.length >= 3"
                    class="p-4 text-center"
                >
                    <div
                        class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-2 text-gray-400"
                    >
                        <i class="pi pi-user-plus text-xl"></i>
                    </div>
                    <p
                        class="text-sm font-bold text-gray-600 dark:text-gray-300"
                    >
                        Cliente não encontrado
                    </p>
                    <p class="text-xs text-gray-400 mt-1 mb-3">
                        Deseja cadastrar "{{ query }}"?
                    </p>
                    <button
                        class="px-4 py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg text-xs font-bold shadow-md hover:scale-105 transition-transform"
                    >
                        Cadastrar Novo Cliente
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from "vue";
import debounce from "lodash/debounce";
import axios from "axios";
// 🚀 Importar os nossos formatadores novinhos em folha!
import { formatPhone, formatCpfCnpj, abbreviateName } from "@/Utils/formatters";

const props = defineProps({
    modelValue: {
        type: [Object, Number, String],
        default: null,
    },
    initialCustomers: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update:modelValue", "customer-selected"]);

const query = ref("");
const results = ref([]);
const isLoading = ref(false);
const showDropdown = ref(false);
const selectedCustomer = ref(null);
const searchContainer = ref(null);

const handleClickOutside = (event) => {
    if (
        searchContainer.value &&
        !searchContainer.value.contains(event.target)
    ) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
    if (props.modelValue && typeof props.modelValue === "object") {
        selectedCustomer.value = props.modelValue;
    } else if (props.modelValue && props.initialCustomers.length > 0) {
        selectedCustomer.value =
            props.initialCustomers.find((c) => c.id === props.modelValue) ||
            null;
    }
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});

const searchCustomer = debounce(async (searchTerm) => {
    if (!searchTerm || searchTerm.length < 2) {
        results.value = [];
        return;
    }

    isLoading.value = true;

    const lowerQuery = searchTerm.toLowerCase();
    const cleanQuery = searchTerm.replace(/\D/g, "");

    let localResults = props.initialCustomers.filter((c) => {
        const matchName = c.name?.toLowerCase().includes(lowerQuery);
        const matchPhone = c.phone?.replace(/\D/g, "").includes(cleanQuery);
        const matchDoc = c.document?.replace(/\D/g, "").includes(cleanQuery);
        return matchName || (cleanQuery && (matchPhone || matchDoc));
    });

    if (localResults.length > 0 && localResults.length < 10) {
        results.value = localResults.slice(0, 5);
        isLoading.value = false;
        return;
    }

    try {
        const response = await axios.get("/api/customers/search", {
            params: { q: searchTerm },
        });
        results.value = response.data.slice(0, 5);
    } catch (error) {
        console.error("Erro ao buscar clientes", error);
        results.value = localResults.slice(0, 5);
    } finally {
        isLoading.value = false;
    }
}, 300);

const handleInput = () => {
    showDropdown.value = true;
    searchCustomer(query.value);
};

const selectCustomer = (customer) => {
    selectedCustomer.value = customer;
    showDropdown.value = false;
    query.value = "";
    emit("update:modelValue", customer);
    emit("customer-selected", customer);
};

const clearSelection = () => {
    selectedCustomer.value = null;
    emit("update:modelValue", null);
    emit("customer-selected", null);
    setTimeout(() => {
        showDropdown.value = true;
    }, 10);
};

const getInitials = (name) => {
    if (!name) return "?";
    const parts = name.trim().split(" ");
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

watch(
    () => props.modelValue,
    (newVal) => {
        if (!newVal) {
            selectedCustomer.value = null;
        } else if (
            typeof newVal === "object" &&
            newVal.id !== selectedCustomer.value?.id
        ) {
            selectedCustomer.value = newVal;
        }
    },
);
</script>

<style scoped>
.animate-fade-in-down {
    animation: fadeInDown 0.2s ease-out forwards;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: scaleY(0.95);
    }
    to {
        opacity: 1;
        transform: scaleY(1);
    }
}
</style>
