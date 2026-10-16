import axios from 'axios';
import { ref } from 'vue';

// 🚀 Estado Global Compartilhado
const favorites = ref([]);
const isLoadingFavorites = ref(false);

export function useCustomerFavorites() {
    const fetchFavorites = async (customerId) => {
        // 🚀 Trava de segurança: Se não houver ID ou se for cliente temporário em memória, não chama o backend
        if (!customerId || String(customerId).startsWith('temp_')) {
            favorites.value = [];
            return;
        }

        isLoadingFavorites.value = true;

        try {
            const response = await axios.get(route('api.customers.favorites', customerId));

            // Tenta ler data encapsulado ou raiz; fallback para array vazio
            favorites.value = response.data.data || response.data || [];

        } catch (error) {
            console.error("Erro ao buscar favoritos do cliente:", error);
            favorites.value = [];
        } finally {
            isLoadingFavorites.value = false;
        }
    };

    const clearFavorites = () => {
        favorites.value = [];
    };

    return {
        favorites,
        isLoadingFavorites,
        fetchFavorites,
        clearFavorites
    };
}
