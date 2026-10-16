import { useStorage } from '@vueuse/core';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

// Estado global compartilhado
const locations = ref([]);
const isLoading = ref(false);
const hasFetched = ref(false);
const activeAvailabilityProduct = ref(null);

// Sub-aba ativa compartilhada entre a Ilha e a Aba: 'clipboard' | 'box'
const activeSubTab = ref('clipboard');

export function useRestock(shiftId = null) {
    const toast = useToast();

    const storageKey = `team_restock_draft_shift_${shiftId || 'active'}`;
    const restockDrafts = useStorage(storageKey, {});

    const isFreeMode = computed(() => {
        return (locations.value[0]?.restock_mode || 'free') === 'free';
    });

    // No modo puxado por vendas (sales_driven), vai direto para a Caixa de coleta
    watch(isFreeMode, (free) => {
        if (!free) {
            activeSubTab.value = 'box';
        }
    }, { immediate: true });

    // Contagem reativa de itens pendentes na Caixa de Coleta
    const totalBoxItemsCount = computed(() => {
        let count = 0;
        locations.value.forEach(loc => {
            const list = loc.items || [];
            list.forEach(item => {
                const qty = isFreeMode.value
                    ? Number(restockDrafts.value[item.rule_id] || 0)
                    : Number(item.suggested_restock || 0);

                if (qty > 0 || (!isFreeMode.value && item.is_critical)) {
                    count++;
                }
            });
        });
        return count;
    });

    const fetchRestockBoard = async (force = false, forceZero = false) => {
        if (hasFetched.value && !force) return;

        isLoading.value = true;
        try {
            const response = await axios.get(route('operations.restock-board'), {
                headers: { 'Accept': 'application/json' }
            });

            const payload = response.data?.data || response.data || [];
            locations.value = Array.isArray(payload) ? payload : [];
            hasFetched.value = true;

            locations.value.forEach(loc => {
                const list = loc.items || loc.tasks || [];
                list.forEach(item => {
                    const id = item.rule_id || item.product_id;
                    if (loc.restock_mode === 'free') {
                        // 🚀 No modo free, a quantidade inicial é SEMPRE ZERO
                        if (forceZero || restockDrafts.value[id] === undefined) {
                            restockDrafts.value[id] = 0;
                        }
                    } else {
                        restockDrafts.value[id] = Number(item.suggested_restock || 0);
                    }
                });
            });
        } catch (e) {
            console.error("Erro ao buscar reposição WMS:", e);
            toast?.add({
                severity: 'error',
                summary: 'Erro',
                detail: 'Falha ao buscar quadro de reposição.',
                life: 3000
            });
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * 🚀 Zera todos os rascunhos para iniciar uma nova rodada limpa de reposição
     */
    const resetForNewRound = () => {
        const freshDrafts = {};
        locations.value.forEach(loc => {
            const list = loc.items || loc.tasks || [];
            list.forEach(item => {
                const id = item.rule_id || item.product_id;
                freshDrafts[id] = 0;
            });
        });

        restockDrafts.value = freshDrafts;
        activeSubTab.value = 'clipboard';

        toast?.add({
            severity: 'info',
            summary: 'Nova Rodada',
            detail: 'Contadores zerados para nova rodada de reposição.',
            life: 2500
        });
    };

    const confirmRestock = async (item, mode = 'free') => {
        const id = item.rule_id || item.product_id;
        const addedQty = Number(item.qty_to_collect || restockDrafts.value[id] || item.suggested_restock || 0);

        if (addedQty <= 0) {
            restockDrafts.value[id] = 0;
            return;
        }

        try {
            const response = await axios.post(route('operations.restock-board.stock'), {
                rule_id: item.rule_id,
                added_stock: addedQty
            });

            if (response.data?.success) {
                locations.value.forEach(loc => {
                    if (loc.items) {
                        loc.items = loc.items.filter(i => i.rule_id !== item.rule_id);
                    }
                });
                locations.value = locations.value.filter(loc => loc.items && loc.items.length > 0);
                restockDrafts.value[id] = 0;

                toast?.add({
                    severity: 'success',
                    summary: 'Reposição Concluída',
                    detail: `${item.name} abastecido com +${addedQty} ${item.unit_of_measure}.`,
                    life: 2500
                });
            }
        } catch (e) {
            console.error("Erro ao confirmar reposição:", e);
            toast?.add({
                severity: 'error',
                summary: 'Erro',
                detail: 'Não foi possível salvar a reposição no servidor.',
                life: 3000
            });
        }
    };

    const markTaskDone = (locationId, productId) => {
        const loc = locations.value.find(l => l.location_id === locationId);
        if (!loc) return;

        if (loc.items) {
            loc.items = loc.items.filter(i => i.product_id !== productId);
            if (loc.items.length === 0) locations.value = locations.value.filter(l => l.location_id !== locationId);
        }
    };

    const openAvailability = (item) => {
        activeAvailabilityProduct.value = {
            id: item.product_id,
            name: item.name,
            status: item.status,
            current_stock: item.total_stock,
            location_stock: item.current_stock,
            rule_id: item.rule_id,
            location_name: item.location_name
        };
    };

    const closeAvailability = () => {
        activeAvailabilityProduct.value = null;
    };

    const clearRestock = () => {
        locations.value = [];
        hasFetched.value = false;
        restockDrafts.value = {};
    };

    const totalItemsToRestock = computed(() => {
        return locations.value.reduce((total, loc) => {
            const list = loc.items || [];
            return total + list.filter(item => (item.suggested_restock > 0 || item.is_critical)).length;
        }, 0);
    });

    const totalCriticalItems = computed(() => {
        return locations.value.reduce((total, loc) => total + (loc.critical_count || 0), 0);
    });

    return {
        locations,
        isLoading,
        restockDrafts,
        activeAvailabilityProduct,
        activeSubTab,
        isFreeMode,
        totalBoxItemsCount,
        fetchRestockBoard,
        resetForNewRound,
        confirmRestock,
        markTaskDone,
        openAvailability,
        closeAvailability,
        clearRestock,
        totalItemsToRestock,
        totalCriticalItems
    };
}
