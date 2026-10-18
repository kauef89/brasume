import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

export function useInventoryCount(catalogRef) {
    const toast = useToast();

    // Filtros e Modos
    const activeMode = ref('inventory');
    const selectedLocation = ref('all');
    const selectedType = ref('all');
    const searchQuery = ref('');

    // Estado do Balanço
    const expandedItemKey = ref(null);
    const draftCounts = ref({});

    const modeOptions = [
        { label: 'Status', value: 'status', icon: 'pi-bolt' },
        { label: 'Inventário', value: 'inventory', icon: 'pi-clipboard' }
    ];

    const typeFilters = [
        { label: 'Todos', value: 'all', icon: 'pi-th-large' },
        { label: 'Revenda', value: 'resale', icon: 'pi-shopping-bag' },
        { label: 'Insumos', value: 'ingredients', icon: 'pi-apple' },
        { label: 'Suprimentos', value: 'supplies', icon: 'pi-box' },
    ];

    const hasActiveFilters = computed(() => {
        return selectedType.value !== 'all' || selectedLocation.value !== 'all';
    });

    const resetFilters = () => {
        selectedType.value = 'all';
        selectedLocation.value = 'all';
    };

    const makeKey = (locationId, productId) => `${locationId || 0}_${productId}`;

    // Mapeamento por Ponto Físico com Saldo Local
    const locationBlocks = computed(() => {
        const rawCatalog = catalogRef.value || [];
        const term = (searchQuery.value || '').trim().toLowerCase();
        const blocksMap = new Map();

        rawCatalog.forEach(prod => {
            if (activeMode.value === 'inventory' && !prod.track_inventory) return;

            if (term) {
                const matches = (prod.name || '').toLowerCase().includes(term) ||
                                (prod.category?.name || '').toLowerCase().includes(term) ||
                                (prod.sku || '').toLowerCase().includes(term) ||
                                String(prod.id).includes(term);
                if (!matches) return;
            }

            const type = (prod.type || 'resale').toLowerCase();
            const section = (type === 'resale') ? 'resale' : (type === 'ingredient' ? 'ingredients' : 'supplies');
            if (selectedType.value !== 'all' && selectedType.value !== section) return;

            const rawLocations = Array.isArray(prod.storage_locations) && prod.storage_locations.length > 0
                ? prod.storage_locations
                : [{ id: 0, name: 'Estoque Geral / Salão', type: 'storage', current_stock: prod.current_stock }];

            const totalGlobalStock = Number(prod.current_stock || 0);

            rawLocations.forEach(loc => {
                const locId = loc.id || 0;
                if (selectedLocation.value !== 'all' && String(selectedLocation.value) !== String(locId)) return;

                if (!blocksMap.has(locId)) {
                    blocksMap.set(locId, {
                        location_id: locId,
                        location_name: loc.name || 'Estoque Geral',
                        type: loc.type || 'storage',
                        sort_order: Number(loc.sort_order ?? 99),
                        items: []
                    });
                }

                const localStock = Number(
                    loc.pivot?.current_stock ??
                    loc.current_stock ??
                    (rawLocations.length === 1 ? totalGlobalStock : 0)
                );

                blocksMap.get(locId).items.push({
                    ...prod,
                    local_expected_stock: localStock,
                    total_system_stock: totalGlobalStock
                });
            });
        });

        return Array.from(blocksMap.values()).sort((a, b) => {
            if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
            if (a.type !== b.type) return a.type === 'display' ? -1 : 1;
            return a.location_name.localeCompare(b.location_name);
        });
    });

    // Rota de Caminhada
    const routeLocations = computed(() => {
        const rawCatalog = catalogRef.value || [];
        const locMap = new Map();

        rawCatalog.forEach(prod => {
            if (!prod.track_inventory) return;
            const locations = Array.isArray(prod.storage_locations) ? prod.storage_locations : [];
            locations.forEach(loc => {
                if (!loc.id) return;
                if (!locMap.has(loc.id)) {
                    locMap.set(loc.id, {
                        id: loc.id,
                        name: loc.name,
                        type: loc.type || 'storage',
                        sort_order: Number(loc.sort_order ?? 99),
                        totalCount: 0,
                        touchedCount: 0
                    });
                }
                const entry = locMap.get(loc.id);
                entry.totalCount++;
                if (draftCounts.value[makeKey(loc.id, prod.id)] !== undefined && draftCounts.value[makeKey(loc.id, prod.id)] !== null) {
                    entry.touchedCount++;
                }
            });
        });

        return Array.from(locMap.values())
            .sort((a, b) => a.sort_order - b.sort_order)
            .map(loc => ({
                ...loc,
                isComplete: loc.totalCount > 0 && loc.touchedCount >= loc.totalCount
            }));
    });

    const locationOptions = computed(() => {
        const list = [{ label: 'Todos os Locais', value: 'all' }];
        routeLocations.value.forEach(b => {
            list.push({ label: b.name, value: String(b.id) });
        });
        return list;
    });

    // Ações de Contagem
    const isExpanded = (locationId, productId) => expandedItemKey.value === makeKey(locationId, productId);

    const toggleItemExpansion = (locationId, item) => {
        const key = makeKey(locationId, item.id);
        expandedItemKey.value = expandedItemKey.value === key ? null : key;
    };

    const isTouched = (locationId, productId) => {
        const val = draftCounts.value[makeKey(locationId, productId)];
        return val !== undefined && val !== null;
    };

    const getDisplayValue = (locationId, productId) => {
        const val = draftCounts.value[makeKey(locationId, productId)];
        return (val !== undefined && val !== null) ? val : '--';
    };

    const getCountValue = (locationId, productId) => {
        const val = draftCounts.value[makeKey(locationId, productId)];
        return (val !== undefined && val !== null) ? Number(val) : null;
    };

    const getItemDelta = (locationId, item) => {
        const counted = getCountValue(locationId, item.id);
        if (counted === null) return 0;
        const expected = Number(item.local_expected_stock ?? 0);
        return Number((counted - expected).toFixed(3));
    };

    const formatDeltaLabel = (locationId, item) => {
        const delta = getItemDelta(locationId, item);
        if (delta < 0) return `${delta} (Falta)`;
        if (delta > 0) return `+${delta} (Sobra)`;
        return 'Exato';
    };

    const saveCount = (locationId, productId, countedQty) => {
        const key = makeKey(locationId, productId);
        draftCounts.value[key] = Math.max(0, Number(countedQty));
        expandedItemKey.value = null;
    };

    // Submissão
    const touchedItemsCount = computed(() => {
        return Object.keys(draftCounts.value).filter(k => draftCounts.value[k] !== null && draftCounts.value[k] !== undefined).length;
    });

    const divergencesSummary = computed(() => {
        let losses = 0;
        let surpluses = 0;

        locationBlocks.value.forEach(block => {
            block.items.forEach(item => {
                if (isTouched(block.location_id, item.id)) {
                    const delta = getItemDelta(block.location_id, item);
                    if (delta < 0) losses++;
                    if (delta > 0) surpluses++;
                }
            });
        });

        const total = losses + surpluses;
        let text = `${total} divergências`;
        if (losses > 0 && surpluses > 0) text += ` (-${losses} faltas, +${surpluses} sobras)`;
        else if (losses > 0) text += ` (-${losses} faltas)`;
        else if (surpluses > 0) text += ` (+${surpluses} sobras)`;

        return { total, losses, surpluses, text };
    });

    const submitBatchAdjustment = () => {
        if (touchedItemsCount.value === 0) return;

        const payloadItems = [];

        locationBlocks.value.forEach(block => {
            block.items.forEach(item => {
                const counted = getCountValue(block.location_id, item.id);
                if (counted !== null) {
                    payloadItems.push({
                        product_id: item.id,
                        location_id: block.location_id !== 0 ? block.location_id : null,
                        counted_stock: counted,
                    });
                }
            });
        });

        router.post(route('operations.inventory.batch-adjust'), {
            items: payloadItems,
            reason: 'Balanço Físico Operacional (TeamApp)'
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                draftCounts.value = {};
                expandedItemKey.value = null;
                toast.add({
                    severity: 'success',
                    summary: 'Balanço Gravado',
                    detail: `${payloadItems.length} itens tiveram seu estoque físico atualizado com sucesso.`,
                    life: 4000
                });
            },
            onError: (err) => {
                toast.add({
                    severity: 'error',
                    summary: 'Erro na Gravação',
                    detail: Object.values(err)[0] || 'Não foi possível gravar o balanço.',
                    life: 5000
                });
            }
        });
    };

    const updateStatus = (item, targetStatus) => {
        router.post(route('operations.availability.toggle'), {
            product_id: item.id,
            action: targetStatus,
            new_stock: targetStatus === 'out_of_stock' ? 0 : null
        }, { preserveScroll: true, preserveState: true });
    };

    return {
        activeMode,
        selectedLocation,
        selectedType,
        searchQuery,
        modeOptions,
        typeFilters,
        hasActiveFilters,
        resetFilters,
        locationBlocks,
        routeLocations,
        locationOptions,
        isExpanded,
        toggleItemExpansion,
        isTouched,
        getDisplayValue,
        getItemDelta,
        formatDeltaLabel,
        saveCount,
        touchedItemsCount,
        divergencesSummary,
        submitBatchAdjustment,
        updateStatus
    };
}
