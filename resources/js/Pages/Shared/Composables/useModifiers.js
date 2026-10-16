import { ref } from "vue";

export function useModifiers(isCustomerMode = false) {
    const selections = ref({});

    const initModifiers = (modifiers, existingStructured = []) => {
        selections.value = {};
        if (!modifiers) return;

        modifiers.forEach((g) => {
            selections.value[g.id] = { selectedIds: [], qtys: {} };
        });

        if (existingStructured && existingStructured.length > 0) {
            existingStructured.forEach(sel => {
                let foundGroup = null;
                let foundOpt = null;

                if (sel.group_id) {
                    foundGroup = modifiers.find(g => String(g.id) === String(sel.group_id));
                    if (foundGroup) {
                        foundOpt = getOptionsWithJoker(foundGroup).find(o => String(o.id) === String(sel.id) || (sel.is_joker && o.isJoker));
                    }
                }

                if (!foundGroup) {
                    for (const g of modifiers) {
                        const opt = getOptionsWithJoker(g).find(o => String(o.id) === String(sel.id) || (sel.is_joker && o.isJoker) || (sel.name && o.name === sel.name));
                        if (opt) {
                            foundGroup = g;
                            foundOpt = opt;
                            break;
                        }
                    }
                }

                if (foundGroup && foundOpt) {
                    if (foundGroup.allow_quantity && foundGroup.type !== 'ingredient') {
                        selections.value[foundGroup.id].qtys[foundOpt.id] = sel.qty || 1;
                    } else {
                        selections.value[foundGroup.id].selectedIds.push(foundOpt.id);
                    }
                }
            });
        }
    };

    const getOptionsWithJoker = (group) => {
        if (group.type === "ingredient") {
            if (isCustomerMode) {
                return group.options;
            }
            return [
                ...group.options,
                { id: `outro_${group.id}`, name: "Outro", price: 0, isJoker: true },
            ];
        }
        return group.options;
    };

    const isSelected = (group, opt) => selections.value[group.id]?.selectedIds.includes(opt.id) || false;

    const isDisabled = (group, opt) => {
        const state = selections.value[group.id];
        if (!state) return false;
        if (isSelected(group, opt)) return false;

        if (!opt.isJoker && state.selectedIds.some(id => String(id).startsWith('outro_'))) {
            return true;
        }

        if (group.max_selections > 0 && state.selectedIds.length >= group.max_selections) return true;
        return false;
    };

    const toggleSelection = (group, opt) => {
        if (isDisabled(group, opt)) return;
        const state = selections.value[group.id];
        const idx = state.selectedIds.indexOf(opt.id);

        if (idx > -1) {
            state.selectedIds.splice(idx, 1);
        } else {
            if (opt.isJoker) {
                state.selectedIds = [opt.id];
            } else {
                const jokerIdx = state.selectedIds.findIndex(id => String(id).startsWith('outro_'));
                if (jokerIdx > -1) state.selectedIds.splice(jokerIdx, 1);

                state.selectedIds.push(opt.id);
            }
        }
    };

    const getQty = (group, opt) => selections.value[group.id]?.qtys[opt.id] || 0;

    const updateQty = (group, opt, delta) => {
        const state = selections.value[group.id];
        let current = state.qtys[opt.id] || 0;
        current += delta;
        if (current < 0) current = 0;
        state.qtys[opt.id] = current;
    };

    const evaluateGroup = (group) => {
        const state = selections.value[group.id];
        if (!state) return { total: 0, optionTotals: {} };

        let total = 0;
        let optionTotals = {};
        const increment = Number(group.increment || 0);
        const optionsWithJoker = getOptionsWithJoker(group);

        if (group.type === 'ingredient') {
            let selectedItems = state.selectedIds.map(optId => {
                const opt = optionsWithJoker.find(o => String(o.id) === String(optId));
                return { opt, basePrice: Number(opt?.price || 0) };
            }).filter(i => i.opt);

            selectedItems.sort((a, b) => b.basePrice - a.basePrice);

            let freeRemaining = group.max_free || 0;

            selectedItems.forEach(item => {
                let finalItemPrice = item.basePrice;

                if (!item.opt.isJoker) {
                    if (freeRemaining > 0) {
                        freeRemaining--;
                    } else {
                        finalItemPrice += increment;
                    }
                }

                total += finalItemPrice;
                optionTotals[item.opt.id] = { qty: 1, totalCost: finalItemPrice };
            });
        } else {
            if (group.allow_quantity) {
                optionsWithJoker.forEach(opt => {
                    const qty = state.qtys[opt.id] || 0;
                    if (qty > 0) {
                        const cost = Number(opt.price || 0) * qty;
                        total += cost;
                        optionTotals[opt.id] = { qty, totalCost: cost };
                    }
                });
            } else {
                state.selectedIds.forEach(optId => {
                    const opt = optionsWithJoker.find(o => String(o.id) === String(optId));
                    if (opt) {
                        const unitPrice = Number(opt.price || 0);
                        total += unitPrice;
                        optionTotals[opt.id] = { qty: 1, totalCost: unitPrice };
                    }
                });
            }
        }

        return { total, optionTotals };
    };

    const getEffectivePrice = (group, opt) => {
        if (opt.isJoker) return 0;
        const basePrice = Number(opt.price || 0);

        if (group.type !== 'ingredient') return basePrice;

        const state = selections.value[group.id];
        if (!state) return basePrice;

        const isSel = isSelected(group, opt);

        if (isSel) {
            const evaluated = evaluateGroup(group);
            return evaluated.optionTotals[opt.id]?.totalCost ?? basePrice;
        } else {
            const currentCount = state.selectedIds.filter(id => !String(id).startsWith('outro_')).length;
            const increment = Number(group.increment || 0);

            if (currentCount >= group.max_free) {
                return basePrice + increment;
            }
            return basePrice;
        }
    };

    const calculateTotal = (basePrice, modifiers) => {
        let total = Number(basePrice || 0);
        if (!modifiers) return total;
        modifiers.forEach((group) => {
            total += evaluateGroup(group).total;
        });
        return total;
    };

    const hasJokerSelected = (modifiers) => {
        if (!modifiers) return false;
        for (const group of modifiers) {
            const state = selections.value[group.id];
            if (state && state.selectedIds.some(id => String(id).startsWith('outro_'))) {
                return true;
            }
        }
        return false;
    };

    // 🚀 NOVO: Checa se um grupo individual atingiu os requisitos mínimos
    const isGroupSatisfied = (group) => {
        if (!group) return true;
        const min = Number(group.min_selections ?? group.min_choices ?? 0);
        if (min <= 0) return true;

        const state = selections.value[group.id];
        if (!state) return false;

        if (group.allow_quantity && group.type !== 'ingredient') {
            const totalQty = Object.values(state.qtys || {}).reduce((a, b) => a + Number(b || 0), 0);
            return totalQty >= min;
        } else {
            return (state.selectedIds || []).length >= min;
        }
    };

    const isValid = (modifiers, noteText = "") => {
        if (!modifiers) return true;
        for (const group of modifiers) {
            if (!isGroupSatisfied(group)) return false;
        }

        if (hasJokerSelected(modifiers) && (!noteText || noteText.trim().length < 3)) {
            return false;
        }

        return true;
    };

    const buildModifiersText = (modifiers) => {
        let parts = [];
        if (!modifiers) return "";
        modifiers.forEach((group) => {
            const state = selections.value[group.id];
            if (!state) return;

            let groupParts = [];
            if (group.allow_quantity && group.type !== 'ingredient') {
                group.options.forEach((opt) => {
                    const qty = state.qtys[opt.id] || 0;
                    if (qty > 0) groupParts.push(`${qty}x ${opt.name}`);
                });
            } else {
                state.selectedIds.forEach((optId) => {
                    const opt = getOptionsWithJoker(group).find((o) => String(o.id) === String(optId));
                    if (opt) groupParts.push(opt.name);
                });
            }

            if (groupParts.length > 0) {
                parts.push(groupParts.join(', '));
            }
        });
        return parts.join(" | ");
    };

    const buildStructuredModifiers = (modifiers) => {
        let structured = [];
        if (!modifiers) return structured;
        modifiers.forEach((group) => {
            const evaluated = evaluateGroup(group);
            const state = selections.value[group.id];
            if (!state) return;

            if (group.allow_quantity && group.type !== 'ingredient') {
                group.options.forEach((opt) => {
                    const qty = state.qtys[opt.id] || 0;
                    if (qty > 0) {
                        structured.push({
                            id: opt.id,
                            name: opt.name,
                            price: evaluated.optionTotals[opt.id]?.totalCost / qty || 0,
                            qty: qty,
                            is_joker: !!opt.isJoker,
                            group_id: group.id,
                        });
                    }
                });
            } else {
                state.selectedIds.forEach((optId) => {
                    const opt = getOptionsWithJoker(group).find((o) => String(o.id) === String(optId));
                    if (opt) {
                        structured.push({
                            id: opt.isJoker ? null : opt.id,
                            name: opt.name,
                            price: evaluated.optionTotals[opt.id]?.totalCost || 0,
                            qty: 1,
                            is_joker: !!opt.isJoker,
                            group_id: group.id,
                        });
                    }
                });
            }
        });
        return structured;
    };

    return {
        initModifiers, getOptionsWithJoker, isSelected, isDisabled, toggleSelection, getQty,
        updateQty, getEffectivePrice, calculateTotal, isValid, hasJokerSelected, isGroupSatisfied,
        buildModifiersText, buildStructuredModifiers,
    };
}
