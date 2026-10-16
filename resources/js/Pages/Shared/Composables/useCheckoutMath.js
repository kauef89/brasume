import { computed, ref, unref, watch } from "vue";

/**
 * Resolve o divisor de partes (denominador) ideal com base no histórico de pagamentos fracionados.
 */
export function resolvePartsPerUnit(paidQty, totalQty, existingParts = 1) {
    const paid = Number(paidQty) || 0;
    const qty = Number(totalQty) || 1;

    if (paid <= 0 || paid >= qty || (paid % 1 === 0)) {
        return (Number(existingParts) > 1) ? Number(existingParts) : 1;
    }

    if (existingParts && Number(existingParts) > 1) {
        const parts = Number(existingParts);
        const remPaid = Math.abs(Math.round(paid * parts) - (paid * parts));
        if (remPaid < 0.01) {
            return parts;
        }
    }

    // Busca o denominador comum que divide perfeitamente o pago e o restante (2, 3, 4, 5, 6, 8, 10, 12)
    const denominators = [2, 3, 4, 5, 6, 8, 10, 12];
    for (const d of denominators) {
        const remPaid = Math.abs(Math.round(paid * d) - (paid * d));
        const remRemaining = Math.abs(Math.round((qty - paid) * d) - ((qty - paid) * d));
        if (remPaid < 0.01 && remRemaining < 0.01) {
            return d;
        }
    }

    const fracRemaining = (qty - paid) % 1;
    if (fracRemaining > 0.001) {
        const d = Math.round(1 / fracRemaining);
        if (d >= 1 && d <= 20) return d;
    }

    const fracPaid = paid % 1;
    const dPaid = Math.round(1 / fracPaid);
    if (dPaid >= 1 && dPaid <= 20) return dPaid;

    return 1;
}

/**
 * Função utilitária pura de rateio proporcional de combos para o frontend.
 */
export function calculateComboProration(parent, children) {
    if (!parent || !children || children.length === 0) return {};

    const parentQty = Number(parent.quantity ?? parent.base_qty ?? 1);
    const parentUnitPrice = Number(parent.unit_price ?? parent.effective_unit_price ?? 0);
    const parentTotalPrice = Number(parent.total_price) || (parentQty * parentUnitPrice);

    let totalCatalogSum = 0;
    const itemsCatalog = [];

    children.forEach(child => {
        const cQty = Number(child.quantity ?? child.base_qty ?? 1);

        let standalonePrice = Number(
            child.catalog_price ||
            child.product?.dine_in_price ||
            child.product?.price ||
            child.unit_price ||
            0
        );

        if (child.modifiers && Array.isArray(child.modifiers)) {
            const modsSum = child.modifiers.reduce((mSum, m) => mSum + (Number(m.price || 0) * Number(m.qty || m.quantity || 1)), 0);
            standalonePrice += (modsSum / cQty);
        }

        const childCatalogTotal = cQty * (standalonePrice > 0 ? standalonePrice : 10);
        totalCatalogSum += childCatalogTotal;

        itemsCatalog.push({
            id: child.id,
            qty: cQty,
            unitCatalog: standalonePrice > 0 ? standalonePrice : 10,
            totalCatalog: childCatalogTotal
        });
    });

    const prorationMap = {};

    if (totalCatalogSum > 0.001) {
        const factor = parentTotalPrice / totalCatalogSum;
        itemsCatalog.forEach(item => {
            const proratedUnit = Math.round((item.unitCatalog * factor) * 100) / 100;
            prorationMap[item.id] = {
                catalogUnitPrice: item.unitCatalog,
                proratedUnitPrice: proratedUnit,
                proratedTotal: Math.round((proratedUnit * item.qty) * 100) / 100
            };
        });
    } else {
        const totalQty = children.reduce((sum, c) => sum + Number(c.quantity ?? c.base_qty ?? 1), 0);
        const equalShare = totalQty > 0 ? Math.round((parentTotalPrice / totalQty) * 100) / 100 : 0;
        children.forEach(c => {
            prorationMap[c.id] = {
                catalogUnitPrice: 0,
                proratedUnitPrice: equalShare,
                proratedTotal: equalShare * Number(c.quantity ?? c.base_qty ?? 1)
            };
        });
    }

    return prorationMap;
}

export function useCheckoutMath(initialItemsRef, isGroupedRef, orderType = ref('table'), customSettings = null) {
    const reactiveItems = ref([]);

    const getRawItems = () => {
        const val = unref(initialItemsRef);
        return Array.isArray(val) ? val : [];
    };

    const initializeItems = (items, existingList = []) => {
        const rawItems = items || [];
        const parents = rawItems.filter(i => !i.parent_item_id);
        const children = rawItems.filter(i => !!i.parent_item_id);

        const mapped = [];

        parents.forEach(parent => {
            const existingParent = existingList.find(e => e.id === parent.id);

            const parentQty = Number(parent.quantity) || 1;
            const parentPaid = Number(parent.paid_qty) || 0;
            const parentUnitPrice = Number(parent.unit_price) || 0;

            const comboChildren = children.filter(c => String(c.parent_item_id) === String(parent.id));
            const isCombo = comboChildren.length > 0;

            // 🚀 Resolução precisa de partes fracionadas
            const parentParts = resolvePartsPerUnit(parentPaid, parentQty, existingParent?.parts_per_unit);

            const parentAvailableParts = Math.max(0, Math.round((parentQty - parentPaid) * parentParts));
            let parentSelected = parentAvailableParts;

            if (existingParent && existingParent.selected_parts !== undefined) {
                parentSelected = Math.min(existingParent.selected_parts, parentAvailableParts);
            } else if (parentParts > 1) {
                parentSelected = Math.min(1, parentAvailableParts);
            }

            mapped.push({
                ...existingParent,
                ...parent,
                unique_id: `grp_${parent.id}`,
                is_combo_parent: isCombo,
                has_exploded_children: isCombo,
                base_qty: parentQty,
                base_paid: parentPaid,
                parts_per_unit: parentParts,
                paid_parts: Math.round(parentPaid * parentParts),
                available_parts: parentAvailableParts,
                selected_parts: parentSelected,
                effective_unit_price: parentUnitPrice,
            });

            if (isCombo) {
                const prorationMap = calculateComboProration(parent, comboChildren);

                comboChildren.forEach(child => {
                    const existingChild = existingList.find(e => e.id === child.id);

                    const cQty = Number(child.quantity) || 1;
                    const cPaid = Number(child.paid_qty) || 0;
                    const proration = prorationMap[child.id] || {};

                    const allocatedPrice = Number(child.allocated_unit_price || child.prorated_unit_price || proration.proratedUnitPrice || 0);
                    const catalogPrice = Number(child.catalog_price || proration.catalogUnitPrice || allocatedPrice);

                    const cParts = resolvePartsPerUnit(cPaid, cQty, existingChild?.parts_per_unit);

                    const cAvailableParts = Math.max(0, Math.round((cQty - cPaid) * cParts));
                    let cSelected = 0;

                    if (existingChild && existingChild.selected_parts !== undefined) {
                        cSelected = Math.min(existingChild.selected_parts, cAvailableParts);
                    }

                    mapped.push({
                        ...existingChild,
                        ...child,
                        unique_id: `grp_${child.id}`,
                        is_combo_child: true,
                        combo_parent_id: parent.id,
                        combo_parent_name: parent.product_name || parent.name,
                        base_qty: cQty,
                        base_paid: cPaid,
                        parts_per_unit: cParts,
                        paid_parts: Math.round(cPaid * cParts),
                        available_parts: cAvailableParts,
                        selected_parts: cSelected,
                        catalog_price: catalogPrice,
                        effective_unit_price: allocatedPrice,
                    });
                });
            }
        });

        return mapped;
    };

    reactiveItems.value = initializeItems(getRawItems());

    watch(() => getRawItems(), (newItems) => {
        reactiveItems.value = initializeItems(newItems, reactiveItems.value);
    }, { deep: true });

    const activeParents = computed(() =>
        reactiveItems.value.filter(
            (i) => !i.parent_item_id && i.available_parts > 0,
        ),
    );

    const isAllSelected = computed(() => {
        if (activeParents.value.length === 0) return false;
        return activeParents.value.every((i) => i.selected_parts > 0);
    });

    const toggleAllSelections = () => {
        const allParentsSelected = activeParents.value.every(i => i.selected_parts > 0);
        const newState = !allParentsSelected;

        reactiveItems.value.forEach((item) => {
            if (!item.parent_item_id) {
                item.selected_parts = newState ? item.available_parts : 0;
            } else {
                item.selected_parts = 0;
            }
        });
        reactiveItems.value = [...reactiveItems.value];
    };

    const displayItems = computed(() => {
        return reactiveItems.value;
    });

    const handleDivideItem = (itemOrDivisor, maybeDivisor) => {
        let item = itemOrDivisor;
        let divisor = maybeDivisor;

        if (typeof itemOrDivisor === 'number' || typeof itemOrDivisor === 'string') {
            if (maybeDivisor && typeof maybeDivisor === 'object') {
                item = maybeDivisor;
                divisor = itemOrDivisor;
            }
        }

        const rawDivisor = parseInt(divisor) || 1;
        const newDivisor = rawDivisor < 1 ? 1 : rawDivisor;

        const targetId = item?.id || item?._parent_ref?.id;
        const target = reactiveItems.value.find(r => String(r.id) === String(targetId)) || item;

        if (!target) return;

        target.parts_per_unit = newDivisor;

        const baseQty = Number(target.base_qty ?? target.quantity ?? 1);
        const basePaid = Number(target.base_paid ?? target.paid_qty ?? 0);

        target.paid_parts = Math.round(basePaid * newDivisor);
        target.available_parts = Math.max(0, Math.round((baseQty - basePaid) * newDivisor));

        if (newDivisor > 1) {
            target.selected_parts = Math.min(1, target.available_parts);
        } else {
            target.selected_parts = target.available_parts;
        }

        if (target.is_combo_child) {
            const parent = reactiveItems.value.find(r => String(r.id) === String(target.combo_parent_id));
            if (parent) parent.selected_parts = 0;
        }

        reactiveItems.value = [...reactiveItems.value];
    };

    const handleToggleSelect = (item) => {
        const targetId = item.id;
        const target = reactiveItems.value.find(r => String(r.id) === String(targetId)) || item;

        if (target.selected_parts > 0) {
            target.selected_parts = 0;
        } else {
            target.selected_parts = target.available_parts;

            if (target.is_combo_child) {
                const parent = reactiveItems.value.find(r => String(r.id) === String(target.combo_parent_id));
                if (parent) parent.selected_parts = 0;
            } else if (target.is_combo_parent) {
                reactiveItems.value.filter(c => String(c.combo_parent_id) === String(target.id)).forEach(c => {
                    c.selected_parts = 0;
                });
            }
        }
        reactiveItems.value = [...reactiveItems.value];
    };

    const increment = (item) => {
        const targetId = item.id;
        const target = reactiveItems.value.find(r => String(r.id) === String(targetId)) || item;

        if (target.selected_parts < target.available_parts) {
            target.selected_parts++;
            if (target.is_combo_child) {
                const parent = reactiveItems.value.find(r => String(r.id) === String(target.combo_parent_id));
                if (parent) parent.selected_parts = 0;
            }
            reactiveItems.value = [...reactiveItems.value];
        }
    };

    const decrement = (item) => {
        const targetId = item.id;
        const target = reactiveItems.value.find(r => String(r.id) === String(targetId)) || item;

        if (target.selected_parts > 0) {
            target.selected_parts--;
            reactiveItems.value = [...reactiveItems.value];
        }
    };

    const selectedSubtotal = computed(() => {
        return reactiveItems.value.reduce((sum, item) => {
            if (item.selected_parts <= 0 || !item.parts_per_unit) return sum;

            const ratio = item.selected_parts / item.parts_per_unit;
            return sum + (ratio * (item.effective_unit_price || 0));
        }, 0);
    });

    const orderTotalItemsValue = computed(() => {
        return reactiveItems.value
            .filter((i) => !i.parent_item_id)
            .reduce((sum, item) => sum + ((item.effective_unit_price || 0) * item.base_qty), 0);
    });

    const calculateServiceFee = (type, settings) => {
        if (!settings || !settings.has_service_tax) return 0;

        let enabled = false;
        if (type === 'table') enabled = settings.service_tax_table !== false && settings.service_tax_table !== 0;
        else if (type === 'counter' || type === 'takeaway' || type === 'pickup') enabled = Boolean(settings.service_tax_counter);
        else if (type === 'delivery') enabled = Boolean(settings.service_tax_delivery);

        if (!enabled) return 0;

        const rate = Number(settings.service_tax_percentage || 0);
        return Math.round((selectedSubtotal.value * (rate / 100)) * 100) / 100;
    };

    return {
        reactiveItems,
        isAllSelected,
        displayItems,
        selectedSubtotal,
        orderTotalItemsValue,
        calculateServiceFee,
        toggleAllSelections,
        handleDivideItem,
        handleToggleSelect,
        increment,
        decrement,
    };
}
