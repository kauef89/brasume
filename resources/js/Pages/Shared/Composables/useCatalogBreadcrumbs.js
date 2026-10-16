import { computed } from 'vue';

export function useCatalogBreadcrumbs(breadcrumbs, initialCategories, closeConfigurators, handleCategorySelect) {

    const catalogBreadcrumbs = computed(() => {
        const crumbs = breadcrumbs.value || [];

        if (crumbs.length === 0) {
            return { items: [], active: 'Início' };
        }

        const items = [{ label: 'Início', id: null }];
        for (let i = 0; i < crumbs.length - 1; i++) {
            items.push({
                label: crumbs[i].name || crumbs[i].label,
                id: crumbs[i].id
            });
        }

        const active = crumbs[crumbs.length - 1].name || crumbs[crumbs.length - 1].label;
        return { items, active };
    });

    const handleBreadcrumbNavigate = (item) => {
        if (typeof closeConfigurators === 'function') {
            closeConfigurators();
        }

        if (!item || item.id === null) {
            handleCategorySelect(null);
            return;
        }

        if (typeof item === 'string') {
            const normalized = item.toLowerCase().trim();
            if (['início', 'inicio', 'home'].includes(normalized)) {
                handleCategorySelect(null);
                return;
            }

            const rawCategories = typeof initialCategories === 'function' ? initialCategories() : initialCategories;
            const foundCat = (rawCategories || []).find(
                c => (c.name || c.label || '').toLowerCase().trim() === normalized
            );
            handleCategorySelect(foundCat ? foundCat.id : null);
            return;
        }

        if (item.id !== undefined) {
            handleCategorySelect(item.id);
        }
    };

    return {
        catalogBreadcrumbs,
        handleBreadcrumbNavigate
    };
}
