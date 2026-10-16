import { computed, ref, unref } from 'vue';

export function useAvailability(catalogRef, toast) {
    const selectedLocation = ref('all');
    const selectedType = ref('all');
    const searchQuery = ref('');
    const activeMode = ref('status'); // 'status' | 'inventory'

    const modeOptions = [
        { label: 'Status', value: 'status', icon: 'pi-bolt' },
        { label: 'Inventário', value: 'inventory', icon: 'pi-clipboard' }
    ];

    // 🛑 Trava estrita: itens sem estoque físico (Pratos montados e Combos)
    const isExcludedFromStock = (item) => {
        if (!item) return true;
        if (item.status === 'archived') return true;

        const type = String(item.type || '').toLowerCase();
        if (type === 'dish' || type === 'combo') return true;

        if (item.is_combo === true || item.is_combo === 1 || item.is_combo === '1' || item.is_combo === 'true') {
            return true;
        }
        if (item.isCombo === true || item.isCombo === 1 || item.isCombo === '1' || item.isCombo === 'true') {
            return true;
        }

        return false;
    };

    const getStatusWeight = (item) => {
        if (item.status === 'out_of_stock' || (item.track_inventory && parseFloat(item.current_stock) <= 0)) {
            return 0; // 🔴 Vermelho (Esgotado)
        }
        if (item.status === 'paused') {
            return 1; // 🟡 Amarelo (Pausado)
        }
        return 2; // 🟢 Verde (Disponível)
    };

    const extractLocations = (item) => {
        if (Array.isArray(item.storage_locations) && item.storage_locations.length > 0) {
            return item.storage_locations.map(loc => typeof loc === 'object' ? loc : { id: loc, name: String(loc) });
        }
        if (Array.isArray(item.storage_rules) && item.storage_rules.length > 0) {
            return item.storage_rules.map(rule => rule.storage_location || rule.storageLocation || {
                id: rule.storage_location_id,
                name: rule.location_name || `Local #${rule.storage_location_id}`
            }).filter(Boolean);
        }
        if (item.storage_location && typeof item.storage_location === 'object') {
            return [item.storage_location];
        }
        if (item.location_name || item.storage_location_name) {
            return [{
                id: item.storage_location_id || item.location_id || item.location_name,
                name: item.location_name || item.storage_location_name
            }];
        }
        return [{ id: 'general', name: 'Estoque Central / Geral' }];
    };

    const resolveItemSection = (item) => {
        const type = (item.type || '').toLowerCase();
        if (type === 'resale') return 'resale';
        if (type === 'ingredient') return 'ingredients';
        if (['supply', 'packaging', 'consumable'].includes(type)) return 'supplies';
        if (type === 'manufactured') return 'manufactured';
        return 'resale';
    };

    const availableLocations = computed(() => {
        const items = (unref(catalogRef) || []).filter(i => !isExcludedFromStock(i));
        const map = new Map();

        items.forEach(item => {
            const locs = extractLocations(item);
            locs.forEach(loc => {
                const key = String(loc.id);
                if (!map.has(key)) {
                    map.set(key, { id: loc.id, name: loc.name, total: 0, critical: 0 });
                }
                const entry = map.get(key);
                entry.total++;
                if (getStatusWeight(item) < 2) {
                    entry.critical++;
                }
            });
        });

        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    });

    const locationOptions = computed(() => {
        const list = [{ label: 'Todos os Locais', value: 'all', critical: 0 }];
        let totalCritical = 0;

        availableLocations.value.forEach(loc => {
            totalCritical += loc.critical;
            list.push({
                label: loc.name,
                value: String(loc.id),
                critical: loc.critical
            });
        });

        list[0].critical = totalCritical;
        return list;
    });

    const groupedCatalog = computed(() => {
        const rawItems = (unref(catalogRef) || []).filter(item => !isExcludedFromStock(item));
        const term = searchQuery.value?.trim().toLowerCase();
        const locationsMap = new Map();

        rawItems.forEach(item => {
            // No Modo Inventário, só exibe itens que controlam estoque físico
            if (activeMode.value === 'inventory' && !item.track_inventory) {
                return;
            }

            if (term) {
                const matches = (item.name || '').toLowerCase().includes(term) ||
                                (item.sku || '').toLowerCase().includes(term) ||
                                (item.barcode || '').toLowerCase().includes(term) ||
                                String(item.id).includes(term);
                if (!matches) return;
            }

            const section = resolveItemSection(item);
            if (selectedType.value !== 'all' && selectedType.value !== section) {
                return;
            }

            const itemLocs = extractLocations(item);

            itemLocs.forEach(loc => {
                const locKey = String(loc.id);

                if (selectedLocation.value !== 'all' && selectedLocation.value !== locKey) {
                    return;
                }

                if (!locationsMap.has(locKey)) {
                    locationsMap.set(locKey, {
                        id: loc.id,
                        name: loc.name,
                        criticalCount: 0,
                        totalCount: 0,
                        resale: [],
                        ingredients: [],
                        supplies: [],
                        manufactured: []
                    });
                }

                const locGroup = locationsMap.get(locKey);
                locGroup.totalCount++;

                if (getStatusWeight(item) < 2) {
                    locGroup.criticalCount++;
                }

                locGroup[section].push(item);
            });
        });

        const sortItems = (list) => {
            return list.slice().sort((a, b) => {
                const wA = getStatusWeight(a);
                const wB = getStatusWeight(b);
                if (wA !== wB) return wA - wB;
                return (a.name || '').localeCompare(b.name || '', 'pt-BR');
            });
        };

        const result = Array.from(locationsMap.values()).map(loc => ({
            ...loc,
            resale: sortItems(loc.resale),
            ingredients: sortItems(loc.ingredients),
            supplies: sortItems(loc.supplies),
            manufactured: sortItems(loc.manufactured)
        }));

        return result.sort((a, b) => {
            if (b.criticalCount !== a.criticalCount) return b.criticalCount - a.criticalCount;
            return a.name.localeCompare(b.name);
        });
    });

    return {
        selectedLocation,
        selectedType,
        searchQuery,
        activeMode,
        modeOptions,
        locationOptions,
        groupedCatalog
    };
}
