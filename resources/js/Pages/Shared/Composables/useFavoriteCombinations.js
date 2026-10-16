import axios from 'axios';
import { onMounted, ref } from 'vue';

// 🚀 ESTADO GLOBAL EM CACHE
const topCombinations = ref([]);
const isLoading = ref(true);
const hasFetched = ref(false);

export function useFavoriteCombinations() {
    const fetchTopCombinations = async (force = false) => {
        // Se já buscou nesta sessão, não busca de novo!
        if (hasFetched.value && !force) return;

        try {
            isLoading.value = true;
            const response = await axios.get(route('api.top-combinations'));
            topCombinations.value = response.data.data || [];
            hasFetched.value = true;
        } catch (error) {
            console.error('Falha ao carregar as combinações favoritas:', error);
            topCombinations.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    onMounted(() => {
        fetchTopCombinations();
    });

    return {
        topCombinations,
        isLoading,
        refresh: () => fetchTopCombinations(true)
    };
}
