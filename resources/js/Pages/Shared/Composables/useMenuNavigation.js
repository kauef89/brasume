import { computed, ref, unref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useMenuNavigation(getCategories, getProducts, settings = {}) {
    const selectedCategory = ref(null);
    const searchQuery = ref('');

    // 🛡️ 1. Critério de produto disponível
    const isProductAvailable = (p) => {
        if (!p) return false;
        if (p.is_available === false || p.is_available === 0) return false;
        if (p.is_active === false || p.is_active === 0) return false;
        if (p.status === 'inactive' || p.status === 'disabled') return false;
        return true;
    };

    // 📦 2. Lista de produtos disponíveis no catálogo
    const availableProductsList = computed(() => {
        const productsList = typeof getProducts === 'function' ? getProducts() : unref(getProducts);
        if (!productsList || !Array.isArray(productsList)) return [];

        return productsList.filter(isProductAvailable);
    });

    // 🎯 3. Set com IDs das categorias que possuem produtos diretamente
    const directCategoryIdsWithProducts = computed(() => {
        const set = new Set();
        for (const p of availableProductsList.value) {
            if (p.category_id !== null && p.category_id !== undefined && p.category_id !== '' && p.category_id !== 0) {
                set.add(String(p.category_id));
            }
        }
        return set;
    });

    // 🌳 4. Mapa Pai -> Filhos para varredura em árvore
    const categoryTreeChildrenMap = computed(() => {
        const cats = typeof getCategories === 'function' ? getCategories() : unref(getCategories);
        if (!cats || !Array.isArray(cats)) return new Map();

        const map = new Map();
        for (const c of cats) {
            if (!c || c.id === null || c.id === undefined) continue;
            const pId = c.parent_id ? String(c.parent_id) : null;
            if (!map.has(pId)) {
                map.set(pId, []);
            }
            map.get(pId).push(String(c.id));
        }
        return map;
    });

    // 🔍 5. Função recursiva: Checa se a categoria OU algum descendente tem produtos
    const hasAvailableProducts = (categoryId, directIdsSet, childrenMap, visited = new Set()) => {
        const strId = String(categoryId);
        if (visited.has(strId)) return false;
        visited.add(strId);

        if (directIdsSet.has(strId)) return true;

        const children = childrenMap.get(strId) || [];
        for (const childId of children) {
            if (hasAvailableProducts(childId, directIdsSet, childrenMap, visited)) {
                return true;
            }
        }

        return false;
    };

    // 🚀 6. Categorias finais higienizadas (Apenas as que contêm produtos disponíveis)
    const availableCategories = computed(() => {
        const cats = typeof getCategories === 'function' ? getCategories() : unref(getCategories);
        if (!cats || !Array.isArray(cats)) return [];

        const directSet = directCategoryIdsWithProducts.value;
        const childrenMap = categoryTreeChildrenMap.value;

        return cats.filter(c => {
            if (!c || c.id === null || c.id === undefined || c.id === '' || c.id === 'all' || c.id === 0) {
                return false;
            }
            const nameLower = String(c.name || '').toLowerCase().trim();
            if (nameLower === 'todos os itens' || nameLower === 'todos') {
                return false;
            }

            return hasAvailableProducts(c.id, directSet, childrenMap);
        });
    });

    const rootCategories = computed(() => {
        return availableCategories.value.filter(c => !c.parent_id);
    });

    const childCategories = computed(() => {
        if (!selectedCategory.value) return [];
        return availableCategories.value.filter(c => String(c.parent_id) === String(selectedCategory.value));
    });

    const activeMenuCategory = computed(() => {
        if (!selectedCategory.value) return null;
        const cats = typeof getCategories === 'function' ? getCategories() : unref(getCategories);
        if (!cats || !Array.isArray(cats)) return null;
        return cats.find(c => String(c.id) === String(selectedCategory.value));
    });

    watch(availableCategories, (validCats) => {
        if (selectedCategory.value) {
            const stillValid = validCats.some(c => String(c.id) === String(selectedCategory.value));
            if (!stillValid) {
                selectedCategory.value = null;
            }
        }
    });

    const breadcrumbs = computed(() => {
        const cats = typeof getCategories === 'function' ? getCategories() : unref(getCategories);
        if (!cats || !Array.isArray(cats) || !selectedCategory.value) return [];

        const path = [];
        let currentId = String(selectedCategory.value);

        while (currentId) {
            const cat = cats.find(c => String(c.id) === currentId);
            if (!cat) break;
            path.unshift(cat);
            currentId = cat.parent_id ? String(cat.parent_id) : null;
        }
        return path;
    });

    const goBackCategory = () => {
        if (!selectedCategory.value) return;
        const currentCat = activeMenuCategory.value;
        if (currentCat && currentCat.parent_id) {
            selectedCategory.value = currentCat.parent_id;
        } else {
            selectedCategory.value = null;
        }
    };

    // 🚀 O QUE APARECE NO MENU DE CATEGORIAS (BARRA OU LATERAL)?
    const visibleMenuCategories = computed(() => {
        const cats = availableCategories.value;
        if (!cats || cats.length === 0) return [];

        // Se estiver pesquisando, oculta a barra para dar espaço total aos resultados
        if (searchQuery.value && searchQuery.value.trim() !== '') {
            return [];
        }

        if (settings.categoryHierarchy === 'flat') {
            return cats;
        }

        if (!selectedCategory.value) return rootCategories.value;
        if (childCategories.value.length > 0) return childCategories.value;

        const currentCat = activeMenuCategory.value;
        if (currentCat) {
            return cats.filter(c => String(c.parent_id) === String(currentCat.parent_id));
        }

        return rootCategories.value;
    });

    // 🚀 O QUE APARECE NO CATEGORY GRID CENTRAL?
    const visibleGridCategories = computed(() => {
        if (unref(settings.categoryLayout) !== 'grid' && unref(settings.categoryLayout) !== 'list') {
            return [];
        }
        if (searchQuery.value && searchQuery.value.trim() !== '') return [];

        if (!selectedCategory.value) {
            return rootCategories.value;
        }

        return childCategories.value;
    });

    const shouldShowCategoryGrid = computed(() => {
        return visibleGridCategories.value.length > 0 && !searchQuery.value;
    });

    // 🚀 QUAIS PRODUTOS APARECEM NO GRID? (Com busca sem distinção de acentos)
    const filteredProducts = computed(() => {
        const list = availableProductsList.value;
        if (!list || list.length === 0) return [];

        // 1. Busca por termo: ignora pastas e pesquisa em todo o catálogo
        if (searchQuery.value && searchQuery.value.trim() !== '') {
            const cleanStr = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            const query = cleanStr(searchQuery.value);

            return list.filter(p => {
                const name = cleanStr(p.name);
                const code = cleanStr(p.code || p.id);
                const sku = cleanStr(p.sku);
                return name.includes(query) || code.includes(query) || sku.includes(query);
            });
        }

        // 2. Raiz do menu (Nenhuma categoria selecionada)
        if (!selectedCategory.value) {
            if (shouldShowCategoryGrid.value) {
                return list.filter(p => !p.category_id || p.category_id === 0);
            }
            return list;
        }

        const currentCatId = String(selectedCategory.value);

        // 3. Dentro de uma categoria: apenas os produtos vinculados a ela
        let result = list.filter(p => String(p.category_id) === currentCatId);

        const page = usePage();
        const mode = unref(settings?.outOfStockMode) || page?.props?.companySettings?.out_of_stock_mode || 'disable_bottom';

        const isUnavailable = (p) => {
            if (!p) return false;
            const s = String(p.status || '').toLowerCase();
            if (['out_of_stock', 'unavailable', 'paused'].includes(s)) return true;
            if (p.track_inventory && parseFloat(p.current_stock ?? 0) <= 0) return true;
            return false;
        };

        if (mode === 'hide') {
            return result.filter(p => !isUnavailable(p));
        }

        if (mode === 'disable_bottom') {
            return [...result].sort((a, b) => {
                const aBlocked = isUnavailable(a);
                const bBlocked = isUnavailable(b);
                if (aBlocked && !bBlocked) return 1;
                if (!aBlocked && bBlocked) return -1;
                return 0;
            });
        }

        return result;
    });

    const shouldShowProductGrid = computed(() => {
        return filteredProducts.value.length > 0;
    });

    return {
        selectedCategory,
        activeMenuCategory,
        searchQuery,
        breadcrumbs,
        goBackCategory,
        visibleMenuCategories,
        visibleGridCategories,
        shouldShowCategoryGrid,
        filteredProducts,
        shouldShowProductGrid
    };
}
