import Fuse from 'fuse.js';
import {  computed, onMounted, ref  } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useCustomerMenu(props) {
    const searchQuery = ref('');
    const activeCategoryId = ref(null);
    const scrollArea = ref(null);
    const categoryRefs = ref({});
    const autoOpenProduct = ref(null);

    const setCategoryRef = (el, id) => {
        if (el) categoryRefs.value[id] = el;
    };

    const getOrderedFlatCategories = (flatList) => {
        if (!Array.isArray(flatList)) return [];

        const list = flatList.map(c => ({
            ...c,
            _order: Number(c.sort_order ?? c.order ?? c.position ?? 0),
            _id: Number(c.id),
            _parentId: c.parent_id ? Number(c.parent_id) : null
        }));

        const roots = list.filter(c => !c._parentId).sort((a, b) => (a._order - b._order) || (a._id - b._id));

        const childrenMap = {};
        list.filter(c => c._parentId).forEach(c => {
            if (!childrenMap[c._parentId]) childrenMap[c._parentId] = [];
            childrenMap[c._parentId].push(c);
        });

        Object.values(childrenMap).forEach(children => {
            children.sort((a, b) => (a._order - b._order) || (a._id - b._id));
        });

        const result = [];
        const addedIds = new Set();

        const traverse = (node) => {
            if (addedIds.has(node._id)) return;
            result.push(node);
            addedIds.add(node._id);

            if (childrenMap[node._id]) {
                childrenMap[node._id].forEach(child => traverse(child));
            }
        };

        roots.forEach(root => traverse(root));

        list.forEach(c => {
            if (!addedIds.has(c._id)) {
                result.push(c);
                addedIds.add(c._id);
            }
        });

        return result;
    };

    const validProducts = computed(() => {
        return (props.products || []).filter(p => {
            const isNotArchived = p.status !== 'archived' && p.status !== 'arquivado';
            const isAvailableForDelivery = p.show_on_delivery !== false && p.show_on_delivery !== 0;

            return isNotArchived && isAvailableForDelivery;
        });
    });

    const menuProductFuse = computed(() => {
        return new Fuse(validProducts.value, {
            keys: [
                { name: 'name', weight: 0.7 },
                { name: 'description', weight: 0.3 }
            ],
            threshold: 0.35,
            distance: 100,
            ignoreLocation: true,
            minMatchCharLength: 2
        });
    });

    const groupedMenu = computed(() => {
        const query = searchQuery.value.trim();
        const result = [];

        const orderedCategories = getOrderedFlatCategories(props.categories || [])
            .filter(c => c.status !== 'archived' && c.status !== 'arquivado' && c.is_active !== false && c.is_active !== 0);

        if (orderedCategories.length === 0) return result;

        let matchedProductIds = null;
        if (query.length >= 2) {
            matchedProductIds = new Set(menuProductFuse.value.search(query).map(hit => hit.item.id));
        }

        orderedCategories.forEach(category => {
            let catProducts = validProducts.value.filter(p => Number(p.category_id) === Number(category.id));

            if (matchedProductIds) {
                catProducts = catProducts.filter(p => matchedProductIds.has(p.id));
            }

            if (catProducts.length > 0) {
                const page = usePage();
                const outOfStockMode = page?.props?.companySettings?.out_of_stock_mode || 'disable_bottom';

                const isBlocked = (p) => {
                    if (!p) return false;
                    const s = String(p.status || '').toLowerCase();
                    if (['out_of_stock', 'unavailable', 'paused'].includes(s)) return true;
                    if (p.track_inventory && parseFloat(p.current_stock ?? 0) <= 0) return true;
                    return false;
                };

                if (outOfStockMode === 'hide') {
                    catProducts = catProducts.filter(p => !isBlocked(p));
                }

                catProducts.sort((a, b) => {
                    if (outOfStockMode === 'disable_bottom') {
                        const aB = isBlocked(a) ? 1 : 0;
                        const bB = isBlocked(b) ? 1 : 0;
                        if (aB !== bB) return aB - bB;
                    }

                    const pOrderA = Number(a.sort_order ?? a.order ?? a.position ?? 0);
                    const pOrderB = Number(b.sort_order ?? b.order ?? b.position ?? 0);
                    if (pOrderA !== pOrderB) return pOrderA - pOrderB;

                    return a.name.localeCompare(b.name);
                });

                let displayName = category.name;
                if (category._parentId) {
                    const parent = props.categories.find(c => Number(c.id) === category._parentId);
                    if (parent) displayName = `${parent.name} • ${category.name}`;
                }

                result.push({
                    category: { ...category, displayName },
                    products: catProducts
                });
            }
        });

        return result;
    });

    const visibleCategories = computed(() => groupedMenu.value.map(g => g.category));

    let isProgrammaticScroll = false;

    const scrollToCategory = (categoryId) => {
        activeCategoryId.value = categoryId;
        const targetEl = categoryRefs.value[categoryId];
        if (targetEl && scrollArea.value) {
            isProgrammaticScroll = true;
            scrollArea.value.scrollTo({ top: targetEl.offsetTop - 20, behavior: 'smooth' });
            setTimeout(() => { isProgrammaticScroll = false; }, 600);
        }
    };

    const handleScroll = () => {
        if (isProgrammaticScroll || !scrollArea.value || groupedMenu.value.length === 0) return;

        const containerTop = scrollArea.value.scrollTop;
        const containerCenter = containerTop + (scrollArea.value.clientHeight / 3);

        let currentActiveId = null;

        for (const group of groupedMenu.value) {
            const el = categoryRefs.value[group.category.id];
            if (el && (el.offsetTop - 25) <= containerCenter) {
                currentActiveId = group.category.id;
            }
        }

        if (currentActiveId && currentActiveId !== activeCategoryId.value) {
            activeCategoryId.value = currentActiveId;
        }
    };

    onMounted(() => {
        if (visibleCategories.value.length > 0) {
            activeCategoryId.value = visibleCategories.value[0].id;
        }

        setTimeout(() => {
            const urlParams = new URLSearchParams(window.location.search);
            const targetProductSlug = urlParams.get('produto');
            const targetCategorySlug = urlParams.get('categoria');

            // 1. PRIORIDADE: Deep-link de produto (Abre os modificadores e posiciona a categoria ao fundo)
            if (targetProductSlug && validProducts.value.length > 0) {
                const targetProduct = validProducts.value.find(p => {
                    const slug = (p.slug || p.name || '').toString().toLowerCase().trim().replace(/[\s_]+/g, '-');
                    const paramSlug = String(targetProductSlug).toLowerCase().trim().replace(/[\s_]+/g, '-');
                    return slug === paramSlug || String(p.id) === String(targetProductSlug);
                });

                if (targetProduct) {
                    autoOpenProduct.value = targetProduct;
                    scrollToCategory(targetProduct.category_id);
                    return;
                }
            }

            // 2. FALLBACK: Deep-link de categoria (Rola a página até o grupo)
            if (targetCategorySlug && visibleCategories.value.length > 0) {
                const targetCategory = visibleCategories.value.find(c => {
                    const slug = (c.slug || c.name || '').toString().toLowerCase().trim().replace(/[\s_]+/g, '-');
                    const paramSlug = String(targetCategorySlug).toLowerCase().trim().replace(/[\s_]+/g, '-');
                    return slug === paramSlug || String(c.id) === String(targetCategorySlug);
                });

                if (targetCategory) {
                    scrollToCategory(targetCategory.id);
                }
            }
        }, 300);
    });

    return {
        searchQuery,
        activeCategoryId,
        scrollArea,
        setCategoryRef,
        groupedMenu,
        visibleCategories,
        scrollToCategory,
        handleScroll,
        autoOpenProduct
    };
}
