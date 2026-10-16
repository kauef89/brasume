import axios from "axios";
import Fuse from "fuse.js";
import { nextTick, ref } from "vue";

export function useAddressLookup(addressLookupMode) {
    const isSearchingCep = ref(false);
    const isLoadingStreets = ref(false);
    const streetSuggestions = ref([]);
    const baseStreetsDataset = ref([]);
    let fuseInstance = null;

    const loadStreetsDataset = async () => {
        if (addressLookupMode.value === 'street' && baseStreetsDataset.value.length === 0) {
            isLoadingStreets.value = true;
            try {
                // Presume que a rota devolve a tabela de streets com os segmentos
                const response = await axios.get(route('api.streets.index'));
                baseStreetsDataset.value = response.data;
                fuseInstance = new Fuse(baseStreetsDataset.value, { keys: ["name"], threshold: 0.3, distance: 100 });
            } catch (error) {
                console.error("Erro ao carregar ruas:", error);
            } finally {
                isLoadingStreets.value = false;
            }
        }
    };

    const fetchCep = async (zipcode, formRef, successCallback, errorCallback) => {
        let clean = zipcode.replace(/\D/g, "");
        if (clean.length === 8) {
            isSearchingCep.value = true;
            try {
                // 🚀 MIGRAÇÃO: BrasilAPI
                const response = await axios.get(`https://brasilapi.com.br/api/cep/v1/${clean}`);
                formRef.value.street = response.data.street || "";
                formRef.value.neighborhood = response.data.neighborhood || "";

                nextTick(() => { if (successCallback) successCallback(); });
            } catch (error) {
                console.error(error);
                if (errorCallback) errorCallback();
            } finally {
                isSearchingCep.value = false;
            }
        }
    };

    const searchStreets = (event) => {
        const query = event.query;
        if (!query || query.trim().length < 2) { streetSuggestions.value = []; return; }

        if (fuseInstance) {
            streetSuggestions.value = fuseInstance.search(query).map(res => res.item);
        } else {
            streetSuggestions.value = [];
        }
    };

        const calculateFuzzyNeighborhood = (formRef) => {
        const form = formRef.value || formRef;
        const street = form.streetObj || null;
        if (!street || !street.segments || street.segments.length === 0) return;

        // Se tem apenas 1 segmento, define direto
        if (street.segments.length === 1) {
            form.neighborhood = street.segments[0].neighborhood?.name || street.segments[0].neighborhood || "";
            form.neighborhood_id = street.segments[0].neighborhood_id || street.segments[0].neighborhood?.id || null;
            return;
        }

        const num = parseInt(form.number);
        let matched = null;

        // Se informou número válido, busca em qual faixa (segmento) se encontra
        if (!form.no_number && !isNaN(num)) {
            matched = street.segments.find(s => {
                const start = parseInt(s.start_number) || 0;
                const end = s.end_number ? parseInt(s.end_number) : Infinity;
                return num >= start && num <= end;
            });
        }

        // Fallback: se não achar faixa compatível ou estiver S/N, pega o primeiro
        if (!matched) matched = street.segments[0];

        if (matched) {
            form.neighborhood = matched.neighborhood?.name || matched.neighborhood || "";
            form.neighborhood_id = matched.neighborhood_id || matched.neighborhood?.id || null;
        }
    };

    return {
        isSearchingCep, isLoadingStreets, streetSuggestions,
        loadStreetsDataset, fetchCep, searchStreets, calculateFuzzyNeighborhood
    };
}
