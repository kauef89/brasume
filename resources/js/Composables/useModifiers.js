import { ref } from "vue";

export function useModifiers() {
    const selections = ref({});

    // 1. Inicialização
    const initModifiers = (modifiers) => {
        selections.value = {};
        if (!modifiers) return;
        modifiers.forEach((g) => {
            selections.value[g.id] = { selectedIds: [], qtys: {} };
        });
    };

    // 2. Utilitário do Coringa ("Outro")
    const getOptionsWithJoker = (group) => {
        return [
            ...group.options,
            { id: `outro_${group.id}`, name: "Outro", price: 0, isJoker: true },
        ];
    };

    // 3. Validações de Interface
    const isSelected = (group, opt) =>
        selections.value[group.id]?.selectedIds.includes(opt.id) || false;

    const isDisabled = (group, opt) => {
        const state = selections.value[group.id];
        if (!state) return false;
        if (isSelected(group, opt)) return false;
        if (
            group.max_selections > 0 &&
            state.selectedIds.length >= group.max_selections
        )
            return true;
        return false;
    };

    // 4. Mutações de Estado (Toggle e Qty)
    const toggleSelection = (group, opt) => {
        if (isDisabled(group, opt)) return;
        const state = selections.value[group.id];
        const idx = state.selectedIds.indexOf(opt.id);

        if (idx > -1) {
            state.selectedIds.splice(idx, 1);
        } else {
            state.selectedIds.push(opt.id);
        }
    };

    const getQty = (group, opt) =>
        selections.value[group.id]?.qtys[opt.id] || 0;

    const updateQty = (group, opt, delta) => {
        const state = selections.value[group.id];
        let current = state.qtys[opt.id] || 0;
        current += delta;
        if (current < 0) current = 0;
        state.qtys[opt.id] = current;
    };

    // 5. Matemática e Precificação (A Mágica da Franquia Grátis)
    const getEffectivePrice = (group, opt) => {
        const state = selections.value[group.id];
        const basePrice = Number(opt.price || 0);

        if (group.type === "ingredient" && !opt.isJoker) {
            const increment = Number(group.increment || 0);
            const isSel = isSelected(group, opt);

            if (isSel) {
                const idx = state.selectedIds.indexOf(opt.id);
                if (idx >= group.max_free) return basePrice + increment;
                return 0;
            } else {
                if (state.selectedIds.length >= group.max_free)
                    return basePrice + increment;
                return 0;
            }
        }
        return basePrice;
    };

    const calculateTotal = (basePrice, modifiers) => {
        let total = Number(basePrice || 0);
        if (!modifiers) return total;

        modifiers.forEach((group) => {
            const state = selections.value[group.id];
            if (!state) return;

            if (group.allow_quantity) {
                group.options.forEach((opt) => {
                    total += getQty(group, opt) * Number(opt.price || 0);
                });
            } else {
                state.selectedIds.forEach((optId) => {
                    const opt = getOptionsWithJoker(group).find(
                        (o) => o.id === optId,
                    );
                    if (opt) total += getEffectivePrice(group, opt);
                });
            }
        });
        return total;
    };

    // 6. Validação do Carrinho
    const isValid = (modifiers) => {
        if (!modifiers) return true;
        for (const group of modifiers) {
            const state = selections.value[group.id];
            if (group.min_selections > 0) {
                if (group.allow_quantity) {
                    const totalQty = Object.values(state.qtys).reduce(
                        (a, b) => a + b,
                        0,
                    );
                    if (totalQty < group.min_selections) return false;
                } else {
                    if (state.selectedIds.length < group.min_selections)
                        return false;
                }
            }
        }
        return true;
    };

    // 7. Montagem do Texto para a Cozinha
    const buildModifiersText = (modifiers) => {
        let parts = [];
        if (!modifiers) return "";

        modifiers.forEach((group) => {
            const state = selections.value[group.id];
            if (!state) return;

            if (group.allow_quantity) {
                group.options.forEach((opt) => {
                    const qty = state.qtys[opt.id] || 0;
                    if (qty > 0) parts.push(`${qty}x ${opt.name}`);
                });
            } else {
                state.selectedIds.forEach((optId) => {
                    const opt = getOptionsWithJoker(group).find(
                        (o) => o.id === optId,
                    );
                    if (opt) parts.push(opt.name);
                });
            }
        });
        return parts.join(" • ");
    };

    // 🚀 NOVO: Monta os dados estruturados para o Banco de Dados (Estoque e Fichas Técnicas)
    const buildStructuredModifiers = (modifiers) => {
        let structured = [];
        if (!modifiers) return structured;

        modifiers.forEach((group) => {
            const state = selections.value[group.id];
            if (!state) return;

            if (group.allow_quantity) {
                group.options.forEach((opt) => {
                    const qty = state.qtys[opt.id] || 0;
                    if (qty > 0) {
                        structured.push({
                            id: opt.id,
                            name: opt.name,
                            price: getEffectivePrice(group, opt),
                            qty: qty,
                            is_joker: !!opt.isJoker,
                        });
                    }
                });
            } else {
                state.selectedIds.forEach((optId) => {
                    const opt = getOptionsWithJoker(group).find(
                        (o) => o.id === optId,
                    );
                    if (opt) {
                        structured.push({
                            id: opt.isJoker ? null : opt.id, // Se for a opção "Outro", não tem ID no banco
                            name: opt.name,
                            price: getEffectivePrice(group, opt),
                            qty: 1,
                            is_joker: !!opt.isJoker,
                        });
                    }
                });
            }
        });
        return structured;
    };

    return {
        initModifiers,
        getOptionsWithJoker,
        isSelected,
        isDisabled,
        toggleSelection,
        getQty,
        updateQty,
        getEffectivePrice,
        calculateTotal,
        isValid,
        buildModifiersText,
        buildStructuredModifiers,
    };
}
