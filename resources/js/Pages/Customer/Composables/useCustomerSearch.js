import axios from "axios";
import debounce from "lodash/debounce";
import { ref } from "vue";

export function useCustomerSearch(initialCustomers) {
    const query = ref("");
    const results = ref([]);
    const isLoading = ref(false);
    const showDropdown = ref(false);

    const executeSearch = debounce(async (searchTerm) => {
        if (!searchTerm || searchTerm.length < 2) {
            results.value = [];
            return;
        }

        isLoading.value = true;
        const lowerQuery = searchTerm.toLowerCase();
        const cleanQuery = searchTerm.replace(/\D/g, "");

        // 1. 🚀 Busca Local Instantânea (Zero Delay) com Barreira de Ativação
        let localResults = (initialCustomers || []).filter((c) => {
            // Se o cliente foi explicitamente desativado no Filament, ignora e barra no front-end
            if (c.is_active === false || c.is_active === 0) return false;

            const matchName = c.name?.toLowerCase().includes(lowerQuery);
            const matchPhone = c.phone?.replace(/\D/g, "").includes(cleanQuery);
            return matchName || (cleanQuery && matchPhone);
        });

        if (localResults.length > 0) {
            results.value = localResults.slice(0, 5);
            isLoading.value = false;
            return;
        }

        // 2. Busca na API se não achar localmente (Ajustado para capturar apenas registros ativos)
        try {
            const response = await axios.get(route('api.customers.search'), { params: { q: searchTerm } });

            // 🚀 Proteção extra: Filtra também os resultados que venham da chamada assíncrona da API
            const activeApiResults = (response.data || []).filter(c => c.is_active !== false && c.is_active !== 0);

            results.value = activeApiResults.slice(0, 5);
        } catch (error) {
            results.value = [];
        } finally {
            isLoading.value = false;
        }
    }, 300);

    return { query, results, isLoading, showDropdown, executeSearch };
}
