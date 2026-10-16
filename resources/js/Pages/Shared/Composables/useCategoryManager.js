import { router } from "@inertiajs/vue3";
import { onBeforeUnmount, onMounted, ref, watch } from "vue";

export function useCategoryManager(categoryTreeProp) {
    const isCategoryManagerActive = ref(false);
    const localCategories = ref(JSON.parse(JSON.stringify(categoryTreeProp.value || [])));
    const historyStack = ref([]);
    const currentSnapshot = ref(JSON.parse(JSON.stringify(categoryTreeProp.value || [])));

    // Sincroniza quando o servidor atualizar as categorias
    watch(categoryTreeProp, (newVal) => {
        const cloned = JSON.parse(JSON.stringify(newVal || []));
        localCategories.value = cloned;
        currentSnapshot.value = JSON.parse(JSON.stringify(cloned));
        historyStack.value = [];
    }, { deep: true });

    const autoSaveCategories = () => {
        router.post(route('settings.reorder'), { preserveState: true,  type: 'categories', items: localCategories.value }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleCategorySave = () => {
        historyStack.value.push(JSON.parse(JSON.stringify(currentSnapshot.value)));
        if (historyStack.value.length > 25) {
            historyStack.value.shift();
        }
        currentSnapshot.value = JSON.parse(JSON.stringify(localCategories.value));
        autoSaveCategories();
    };

    const undoCategoryChange = () => {
        if (historyStack.value.length === 0) return;
        const previousState = historyStack.value.pop();
        localCategories.value = JSON.parse(JSON.stringify(previousState));
        currentSnapshot.value = JSON.parse(JSON.stringify(previousState));
        autoSaveCategories();
    };

    const handleKeyDownCtrlZ = (e) => {
        if (!isCategoryManagerActive.value) return;

        const isEditingText = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable;
        if (isEditingText) return;

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undoCategoryChange();
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDownCtrlZ);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', handleKeyDownCtrlZ);
    });

    return {
        isCategoryManagerActive,
        localCategories,
        historyStack,
        handleCategorySave,
        undoCategoryChange
    };
}
