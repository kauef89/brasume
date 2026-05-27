import { ref, computed } from "vue";

export function useCheckoutMath(initialItems, isGroupedRef) {
    const reactiveItems = ref(
        initialItems.map((item) => ({
            ...item,
            unique_id: `grp_${item.id}`,
            base_qty: item.quantity,
            base_paid: item.paid_qty,
            parts_per_unit: 1,
            paid_parts: item.paid_qty,
            available_parts: item.quantity - item.paid_qty,
            selected_parts: item.quantity - item.paid_qty,
            effective_unit_price: Number(item.unit_price), // Preço mestre que vem do banco
        })),
    );

    const activeParents = computed(() =>
        reactiveItems.value.filter(
            (i) =>
                !i.parent_item_id && !i.is_combo_item && i.available_parts > 0,
        ),
    );

    const isAllSelected = computed(() => {
        if (activeParents.value.length === 0) return false;
        return activeParents.value.every((i) => i.selected_parts > 0);
    });

    const toggleAllSelections = () => {
        const newState = !isAllSelected.value;
        activeParents.value.forEach((item) => {
            item.selected_parts = newState ? item.available_parts : 0;
        });
    };

    const displayItems = computed(() => {
        let baseItems = reactiveItems.value.filter(
            (i) => !i.parent_item_id && !i.is_combo_item,
        );

        baseItems.forEach((parent) => {
            parent.comboChildren = reactiveItems.value.filter(
                (child) => child.parent_item_id === parent.id,
            );
            parent._parent_ref = parent;
            parent._is_parent_ref = true;
        });

        if (isGroupedRef.value) return baseItems;

        let exploded = [];
        baseItems.forEach((item) => {
            for (let i = 0; i < item.base_qty; i++) {
                let paidForThisUnit = Math.max(
                    0,
                    Math.min(
                        item.parts_per_unit,
                        item.paid_parts - i * item.parts_per_unit,
                    ),
                );
                let selectedForThisUnit = Math.max(
                    0,
                    Math.min(
                        item.parts_per_unit,
                        item.selected_parts - i * item.parts_per_unit,
                    ),
                );

                exploded.push({
                    ...item,
                    unique_id: `exp_${item.id}_${i}`,
                    base_qty: 1,
                    paid_parts: paidForThisUnit,
                    available_parts: item.parts_per_unit - paidForThisUnit,
                    selected_parts: selectedForThisUnit,
                    _parent_ref: item,
                    _is_parent_ref: true,
                });
            }
        });
        return exploded;
    });

    const handleDivideItem = (item, divisor) => {
        let target = item._parent_ref;
        let factor = parseInt(divisor) || 1;
        if (factor < 1) factor = 1;

        target.parts_per_unit *= factor; // Multiplica a resolução da unidade inteira

        let base_available = target.base_qty - target.base_paid;
        target.available_parts = base_available * target.parts_per_unit;
        target.paid_parts = target.base_paid * target.parts_per_unit;
        target.selected_parts = base_available; // Seleciona automaticamente apenas 1 Fração da nova divisão!
    };

    const handleToggleSelect = (item) => {
        let target = item._parent_ref;

        if (isGroupedRef.value) {
            target.selected_parts =
                target.selected_parts > 0 ? 0 : target.available_parts;
        } else {
            // Em modo separado, liga ou desliga apenas a fração da linha clicada!
            if (item.selected_parts > 0) {
                target.selected_parts = Math.max(
                    0,
                    target.selected_parts - target.parts_per_unit,
                );
            } else {
                target.selected_parts = Math.min(
                    target.available_parts,
                    target.selected_parts + target.parts_per_unit,
                );
            }
        }
    };

    const increment = (item) => {
        let target = item._parent_ref;
        if (target.selected_parts < target.available_parts)
            target.selected_parts++;
    };

    const decrement = (item) => {
        let target = item._parent_ref;
        if (target.selected_parts > 0) target.selected_parts--;
    };

    return {
        reactiveItems,
        isAllSelected,
        displayItems,
        toggleAllSelections,
        handleDivideItem,
        handleToggleSelect,
        increment,
        decrement,
    };
}
