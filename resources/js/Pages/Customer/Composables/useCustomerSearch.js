import axios from "axios";
import debounce from "lodash/debounce";
import { ref } from "vue";

export function useCustomerSearch(initialCustomers) {
    const query = ref("");
    const results = ref([]);
    const isLoading = ref(false);
    const showDropdown = ref(false);

    const executeSearch = debounce(async (searchTerm) => {
        const term = (searchTerm || "").trim();
        if (term.length < 2) {
            results.value = [];
            isLoading.value = false;
            return;
        }

        isLoading.value = true;

        try {
            // Sempre busca na API oficial para trazer vínculos reais (employee_id, supplier_id)
            const searchUrl = typeof route === 'function' && route().has('api.customers.search')
                ? route('api.customers.search')
                : (typeof route === 'function' && route().has('operations.customers.search')
                    ? route('operations.customers.search')
                    : '/api/customers/search');

            const response = await axios.get(searchUrl, { params: { q: term } });
            const activeApiResults = (response.data || []).filter(c => c.is_active !== false && c.is_active !== 0);

            if (activeApiResults.length > 0) {
                results.value = activeApiResults.slice(0, 10);
            } else {
                // Fallback local se a API não encontrar nenhum registro
                const lowerQuery = term.toLowerCase();
                const cleanQuery = term.replace(/\D/g, "");
                results.value = (initialCustomers || []).filter((c) => {
                    if (c.is_active === false || c.is_active === 0) return false;
                    const matchName = c.name?.toLowerCase().includes(lowerQuery);
                    const matchPhone = c.phone?.replace(/\D/g, "").includes(cleanQuery);
                    return matchName || (cleanQuery && matchPhone);
                }).slice(0, 5);
            }
        } catch (error) {
            // Em caso de falha de conexão, recorre ao cache local
            const lowerQuery = term.toLowerCase();
            const cleanQuery = term.replace(/\D/g, "");
            results.value = (initialCustomers || []).filter((c) => {
                if (c.is_active === false || c.is_active === 0) return false;
                const matchName = c.name?.toLowerCase().includes(lowerQuery);
                const matchPhone = c.phone?.replace(/\D/g, "").includes(cleanQuery);
                return matchName || (cleanQuery && matchPhone);
            }).slice(0, 5);
        } finally {
            isLoading.value = false;
        }
    }, 250);

    return { query, results, isLoading, showDropdown, executeSearch };
}
