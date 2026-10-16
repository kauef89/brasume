import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

// Variável Global: Cache Client-Side (Evita múltiplos downloads na mesma sessão)
const backendMatrix = ref(null);
const isLoadingMatrix = ref(false);

export function usePredictiveModifiers(explicitContext = null) {
    const page = usePage();

    const currentContext = computed(() => {
        if (explicitContext) return explicitContext;
        return typeof window !== 'undefined' && window.location.pathname.startsWith('/team')
            ? 'waiter'
            : 'customer';
    });

    const isPredictiveEnabled = computed(() => {
        const mode = page.props.companySettings?.predictive_modifiers_mode || 'disabled';

        if (mode === 'all') return true;
        if (mode === 'customer') return currentContext.value === 'customer';
        if (mode === 'waiter') return currentContext.value === 'waiter';

        return false;
    });

    const fetchMatrix = async () => {
        if (!isPredictiveEnabled.value || backendMatrix.value !== null || isLoadingMatrix.value) return;

        isLoadingMatrix.value = true;
        try {
            const response = await axios.get(route('api.predictive-matrix'));
            backendMatrix.value = response.data || {};
        } catch (e) {
            console.error('Erro ao carregar IA Preditiva', e);
            backendMatrix.value = {};
        } finally {
            isLoadingMatrix.value = false;
        }
    };

    onMounted(() => {
        fetchMatrix();
    });

    const getRecommendations = (availableOptions, selectedOptions, maxChoices = 3) => {
        if (!isPredictiveEnabled.value || availableOptions.length === 0) return [];

        const selectedIds = selectedOptions.map(o => o.id);
        const unselectedOpts = availableOptions.filter(o => !selectedIds.includes(o.id) && !o.isJoker);

        if (unselectedOpts.length === 0 || selectedIds.length === 0) return [];

        const matrix = backendMatrix.value || {};

        const scores = unselectedOpts.map(opt => {
            let score = 0;
            let validConnections = 0;

            selectedIds.forEach(sId => {
                const coOccurrence = matrix[sId]?.[opt.id] || 0;

                if (coOccurrence > 0) {
                    const optionPopularity = opt.historical_weight > 0 ? opt.historical_weight : 1;
                    const affinity = coOccurrence / (optionPopularity + 10);

                    score += affinity;
                    validConnections++;
                }
            });

            // Regra AND: precisa ter saído junto com todas as seleções atuais
            if (validConnections < selectedIds.length) {
                score = 0;
            } else {
                score += (opt.historical_weight || 0) * 0.00001;
            }

            return { id: opt.id, score };
        });

        scores.sort((a, b) => b.score - a.score);
        const topScores = scores.filter(s => s.score > 0);

        if (topScores.length > 0) {
            return topScores.slice(0, maxChoices).map(s => s.id);
        }

        return [];
    };

    return { isPredictiveEnabled, getRecommendations };
}
