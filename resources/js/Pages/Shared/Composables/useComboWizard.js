import { computed, ref, watch } from "vue";

export function useComboWizard(props, emit) {
    const currentStepIndex = ref(0);
    const selections = ref({});
    const activeInstanceId = ref(null);

    // 🚀 RASCUNHO PENDENTE: Segura o item selecionado até a confirmação explícita dos modificadores
    const pendingDraftItem = ref(null);

    const showMobileSummary = ref(false);
    const showInnerModifiers = ref(false);
    const modifyingProduct = ref(null);
    const isEditingComboItem = ref(false);

    const isAdding = ref(false);
    const isAddSuccess = ref(false);

    const currentGroup = computed(() => props.product?.combo_groups?.[currentStepIndex.value] || null);
    const isLastStep = computed(() => currentStepIndex.value === (props.product?.combo_groups?.length || 1) - 1);
    const currentSelections = computed(() => currentGroup.value ? selections.value[currentGroup.value.id] || [] : []);
    const canAddMore = computed(() => currentGroup.value && currentSelections.value.length < currentGroup.value.max_choices);

    const globalSelectionsArray = computed(() => {
        let all = [];
        Object.keys(selections.value).forEach(groupId => {
            all = all.concat(selections.value[groupId]);
        });
        return all;
    });

    watch(() => props.product, (newVal) => {
        currentStepIndex.value = 0;
        selections.value = {};
        showMobileSummary.value = false;
        pendingDraftItem.value = null;

        if (newVal?.combo_groups) {
            newVal.combo_groups.forEach((g) => { selections.value[g.id] = []; });
        }

        if (newVal?.cartItem?.comboItems && Array.isArray(newVal.cartItem.comboItems)) {
            newVal.cartItem.comboItems.forEach(cItem => {
                let targetGroup = null;
                if (cItem.group_id) {
                    targetGroup = newVal.combo_groups.find(g => String(g.id) === String(cItem.group_id));
                }
                if (!targetGroup) {
                    targetGroup = newVal.combo_groups.find(g => {
                        const currentCount = (selections.value[g.id] || []).length;
                        const max = Number(g.max_choices || 1);
                        return g.items.some(i => Number(i.product_id) === Number(cItem.product_id)) && currentCount < max;
                    }) || newVal.combo_groups.find(g => g.items.some(i => Number(i.product_id) === Number(cItem.product_id)));
                }

                if (targetGroup) {
                    const originalItem = targetGroup.items.find(i => Number(i.product_id) === Number(cItem.product_id));
                    if (originalItem) {
                        const extra = (cItem.structuredModifiers || []).reduce((sum, m) => sum + (Number(m.price) * Number(m.qty || 1)), 0);
                        selections.value[targetGroup.id].push({
                            instanceId: Date.now() + Math.random(),
                            item: originalItem,
                            modifiersText: cItem.modifiersText || "",
                            extraPrice: extra,
                            groupId: Number(targetGroup.id),
                            structuredModifiers: cItem.structuredModifiers || [],
                            qty: 1
                        });
                    }
                }
            });

            // 🚀 ABRE DIRETO NO RESUMO se o combo veio com subitens pré-populados
            if (newVal.cartItem.comboItems.length > 0) {
                showMobileSummary.value = true;
            }
        }
    }, { immediate: true });

    const comboHelpers = {
        hasModifiers: (comboItem) => {
            if (!comboItem) return false;
            const prodId = comboItem.product_id || comboItem.id;
            const fullProd = (props.globalProductsList || []).find((p) => p.id === prodId) || comboItem;
            const groups = fullProd.modifier_groups || fullProd.modifierGroups || fullProd.modifiers || [];
            return Array.isArray(groups) && groups.length > 0;
        }
    };

    // 🛡️ VALIDADOR: Verifica se um subitem cumpriu todos os grupos de modificadores obrigatórios
    const isSelectionValid = (sel) => {
        if (!sel || !sel.item) return false;
        const prodId = sel.item.product_id || sel.item.id;
        const fullProd = (props.globalProductsList || []).find((p) => p.id === prodId) || sel.item;
        const groups = fullProd.modifier_groups || fullProd.modifierGroups || fullProd.modifiers || [];

        for (const group of groups) {
            const min = Number(group.min_choices ?? group.min_quantity ?? (group.is_required ? 1 : 0));
            if (min > 0) {
                const selectedInGroup = (sel.structuredModifiers || []).filter(m =>
                    String(m.group_id || m.modifier_group_id) === String(group.id)
                );
                const totalQty = selectedInGroup.reduce((sum, m) => sum + Number(m.qty || 1), 0);
                if (totalQty < min) return false;
            }
        }
        return true;
    };

    const getSelectionsCountForGroup = (groupId) => {
        return (selections.value[groupId] || []).length;
    };

    const advanceToNextIncompleteStepOrSummary = () => {
        const groups = props.product?.combo_groups || [];
        let nextIncompleteIdx = -1;

        for (let i = 0; i < groups.length; i++) {
            const g = groups[i];
            const groupSelections = selections.value[g.id] || [];
            const minChoices = Number(g.min_choices || 1);

            // Incompleto se tiver menos itens que o mínimo OU se algum item tiver escolha obrigatória pendente
            if (groupSelections.length < minChoices || !groupSelections.every(sel => isSelectionValid(sel))) {
                nextIncompleteIdx = i;
                break;
            }
        }

        if (nextIncompleteIdx > -1) {
            currentStepIndex.value = nextIncompleteIdx;
            showMobileSummary.value = false;
        } else {
            showMobileSummary.value = true;
        }
    };

    const checkAutoAdvance = () => {
        if (!canAddMore.value) {
            setTimeout(() => {
                if (!canAddMore.value && !showInnerModifiers.value && !showMobileSummary.value) {
                    advanceToNextIncompleteStepOrSummary();
                }
            }, 350);
        }
    };

    // 🛑 CLIQUE NO CARD DO GRID
    const addCatalogItemToTray = (comboItem, openModDrawer = false) => {
        if (!currentGroup.value) return;
        if (!canAddMore.value && currentGroup.value.max_choices > 1) return;

        // 1. SE TEM MODIFICADORES: NÃO adiciona nada a selections. Cria apenas um rascunho.
        if (comboHelpers.hasModifiers(comboItem)) {
            pendingDraftItem.value = {
                item: comboItem,
                groupId: Number(currentGroup.value.id)
            };
            openDraftModifiers(comboItem, Number(currentGroup.value.id));
            return;
        }

        // 2. SE NÃO TEM MODIFICADORES: Pode adicionar direto
        commitSelection({
            instanceId: Date.now() + Math.random(),
            item: comboItem,
            modifiersText: "",
            extraPrice: 0,
            groupId: Number(currentGroup.value.id),
            structuredModifiers: [],
            qty: 1
        }, Number(currentGroup.value.id));

        checkAutoAdvance();
    };

    const commitSelection = (newSelection, groupId) => {
        if (!selections.value[groupId]) {
            selections.value[groupId] = [];
        }
        const groupDef = props.product?.combo_groups?.find(g => Number(g.id) === Number(groupId));
        const maxChoices = Number(groupDef?.max_choices || 1);

        if (maxChoices === 1) {
            selections.value[groupId] = [newSelection];
        } else {
            if (selections.value[groupId].length < maxChoices) {
                selections.value[groupId].push(newSelection);
            }
        }
    };

    // Abre os modificadores para um item NOVO (ainda não inserido no combo)
    const openDraftModifiers = (comboItem, groupId) => {
        isEditingComboItem.value = false;
        activeInstanceId.value = null;

        const prodId = comboItem.product_id || comboItem.id;
        const fullProdInfo = (props.globalProductsList || []).find((p) => p.id === prodId) || comboItem;

        const groupDef = props.product?.combo_groups?.find(g => Number(g.id) === Number(groupId));
        let maxAllowed = 1;

        if (groupDef) {
            const currentCount = (selections.value[groupId] || []).length;
            maxAllowed = Math.max(1, (Number(groupDef.max_choices) - currentCount));
        }

        modifyingProduct.value = {
            ...fullProdInfo,
            cartItem: {
                qty: 1,
                structuredModifiers: [],
                maxQty: maxAllowed
            }
        };
        showInnerModifiers.value = true;
    };

    // Abre os modificadores para um item EXISTENTE (edição)
    const openModifiers = (selectionInstance, isEdit = true) => {
        isEditingComboItem.value = isEdit;
        activeInstanceId.value = selectionInstance.instanceId;
        pendingDraftItem.value = null;

        const prodId = selectionInstance.item.product_id || selectionInstance.item.id;
        const fullProdInfo = (props.globalProductsList || []).find((p) => p.id === prodId) || selectionInstance.item;

        const groupDef = props.product?.combo_groups?.find(g => Number(g.id) === Number(selectionInstance.groupId));
        let maxAllowed = 99;

        if (groupDef) {
            const currentCount = (selections.value[selectionInstance.groupId] || []).length;
            maxAllowed = (Number(groupDef.max_choices) - currentCount) + 1;
        }

        modifyingProduct.value = {
            ...fullProdInfo,
            cartItem: {
                qty: selectionInstance.qty || 1,
                structuredModifiers: selectionInstance.structuredModifiers || [],
                maxQty: Math.max(1, maxAllowed)
            }
        };
        showInnerModifiers.value = true;
    };

    // 🚀 CONFIRMAÇÃO DO SLIDETOCONFIRM: O ÚNICO LUGAR QUE EFETIVA A ADIÇÃO DO PRODUTO
    const handleModifierConfirm = (modifiersData) => {
        if (isEditingComboItem.value && activeInstanceId.value) {
            // Modo Edição: Atualiza o item que já pertencia ao combo
            injectModifiersIntoCurrentSelection(modifiersData);
        } else if (pendingDraftItem.value) {
            // Modo Inserção: O cliente confirmou o rascunho! Agora sim entra no combo.
            const draft = pendingDraftItem.value;
            const groupId = draft.groupId;
            const groupDef = props.product?.combo_groups?.find(g => Number(g.id) === Number(groupId));
            const maxChoices = Number(groupDef?.max_choices || 1);

            const newSelection = {
                instanceId: Date.now() + Math.random(),
                item: draft.item,
                modifiersText: modifiersData.text || modifiersData.modifiersText || "",
                extraPrice: Number(modifiersData.extraPrice || 0),
                groupId: groupId,
                structuredModifiers: modifiersData.structuredModifiers || [],
                qty: 1
            };

            commitSelection(newSelection, groupId);

            // Se o usuário selecionou mais de uma unidade no stepper do modificador
            const requestedQty = Number(modifiersData.qty || 1);
            if (requestedQty > 1 && maxChoices > 1) {
                const currentCount = (selections.value[groupId] || []).length;
                const availableSlots = maxChoices - currentCount;
                const clonesToCreate = Math.min(requestedQty - 1, availableSlots);

                for (let i = 0; i < clonesToCreate; i++) {
                    selections.value[groupId].push({
                        instanceId: Date.now() + Math.random() + i,
                        item: draft.item,
                        modifiersText: newSelection.modifiersText,
                        extraPrice: newSelection.extraPrice,
                        groupId: groupId,
                        structuredModifiers: JSON.parse(JSON.stringify(newSelection.structuredModifiers)),
                        qty: 1
                    });
                }
            }

            pendingDraftItem.value = null;
        }

        showInnerModifiers.value = false;
        modifyingProduct.value = null;
        isEditingComboItem.value = false;
        activeInstanceId.value = null;
        checkAutoAdvance();
    };

    // Se a tela de modificadores for fechada sem confirmação, descarta o rascunho
    watch(showInnerModifiers, (isShowing) => {
        if (!isShowing) {
            pendingDraftItem.value = null;
            modifyingProduct.value = null;
            isEditingComboItem.value = false;
            activeInstanceId.value = null;
        }
    });

    const removeSelection = (instanceId) => {
        for (const groupId in selections.value) {
            const groupArray = selections.value[groupId];
            const idx = groupArray.findIndex((s) => s.instanceId === instanceId);
            if (idx > -1) {
                groupArray.splice(idx, 1);
                return;
            }
        }
    };

    const injectModifiersIntoCurrentSelection = (modifiersData) => {
        let target = null;
        let targetGroupId = null;

        for (const groupId in selections.value) {
            target = selections.value[groupId].find((s) => s.instanceId === activeInstanceId.value);
            if (target) {
                targetGroupId = Number(groupId);
                break;
            }
        }

        if (target) {
            target.modifiersText = modifiersData.text || modifiersData.modifiersText || "";
            target.extraPrice = Number(modifiersData.extraPrice || 0);
            target.structuredModifiers = modifiersData.structuredModifiers || [];
            target.qty = Number(modifiersData.qty || 1);
        }
    };

    // O passo só é válido se atingiu o mínimo de escolhas E se todos os itens cumprirem os modificadores obrigatórios
    const isCurrentStepValid = computed(() => {
        if (!currentGroup.value) return false;
        const groupSelections = currentSelections.value;
        const minChoices = Number(currentGroup.value.min_choices || 1);

        if (groupSelections.length < minChoices) return false;
        return groupSelections.every(sel => isSelectionValid(sel));
    });

    // 🚀 VALIDAÇÃO GLOBAL: Todos os passos atingiram o mínimo e todos os itens estão completos
    const isComboComplete = computed(() => {
        if (showInnerModifiers.value) return false;
        const groups = props.product?.combo_groups || [];
        if (groups.length === 0) return false;

        for (const g of groups) {
            const groupSelections = selections.value[g.id] || [];
            const minChoices = Number(g.min_choices || 1);

            if (groupSelections.length < minChoices) return false;
            if (!groupSelections.every(sel => isSelectionValid(sel))) return false;
        }

        return true;
    });

    const comboProgressMax = computed(() => props.product?.combo_groups?.length || 0);
    const comboProgressCurrent = computed(() => showMobileSummary.value ? (props.product?.combo_groups?.length || 0) : currentStepIndex.value);

    const wizardTotal = computed(() => {
        if (!props.product) return 0;
        let total = Number(props.product.price);
        Object.values(selections.value).forEach((groupArray) => {
            groupArray.forEach((sel) => {
                total += Number(sel.item.additional_price || 0) + Number(sel.extraPrice || 0);
            });
        });
        return total;
    });

    const canGoBack = computed(() => {
        if (showMobileSummary.value) {
            const groups = props.product?.combo_groups || [];
            return groups.some(g => {
                const groupSelections = selections.value[g.id] || [];
                return groupSelections.length < Number(g.min_choices || 1) || !groupSelections.every(sel => isSelectionValid(sel));
            });
        }
        return true;
    });

    const handleMobileBack = () => {
        if (showMobileSummary.value) {
            advanceToNextIncompleteStepOrSummary();
        } else {
            handleBackOrCancel();
        }
    };

    const handleBackOrCancel = () => {
        if (showInnerModifiers.value) {
            showInnerModifiers.value = false;
            pendingDraftItem.value = null;
            return;
        }
        if (currentStepIndex.value > 0) {
            currentStepIndex.value--;
            showMobileSummary.value = false;
        } else {
            emit("close");
        }
    };

    const nextStep = () => {
        if (!showMobileSummary.value) {
            if (!isCurrentStepValid.value) return;
            advanceToNextIncompleteStepOrSummary();
        } else {
            // 🛑 IMPEDIMENTO ESTRITO: Não avança se o combo não estiver 100% satisfeito
            if (!isComboComplete.value) {
                advanceToNextIncompleteStepOrSummary();
                return;
            }

            isAdding.value = true;
            setTimeout(() => {
                isAdding.value = false;
                isAddSuccess.value = true;
                setTimeout(() => {
                    let structuredComboItems = [];
                    Object.keys(selections.value).forEach((groupId) => {
                        selections.value[groupId].forEach((sel) => {
                            structuredComboItems.push({
                                product_id: sel.item.product_id || sel.item.id,
                                name: sel.item.product_name || sel.item.name,
                                group_id: Number(groupId),
                                modifiersText: sel.modifiersText,
                                structuredModifiers: sel.structuredModifiers || [],
                                qty: Number(sel.qty || 1),
                                price: Number(sel.item.additional_price || sel.item.price || 0)
                            });
                        });
                    });
                    emit("confirm-combo", {
                        structuredItems: structuredComboItems,
                        extraPrice: wizardTotal.value - Number(props.product.price)
                    });
                }, 500);
            }, 300);
        }
    };

    const handleClearCombo = () => {
        currentStepIndex.value = 0;
        showMobileSummary.value = false;
        pendingDraftItem.value = null;
        if (props.product?.combo_groups) {
            props.product.combo_groups.forEach((g) => { selections.value[g.id] = []; });
        }
    };

    return {
        currentStepIndex, showMobileSummary, showInnerModifiers, modifyingProduct, isAdding, isAddSuccess,
        currentGroup, isLastStep, canAddMore, globalSelectionsArray, comboHelpers, isCurrentStepValid,
        isComboComplete, comboProgressMax, comboProgressCurrent, wizardTotal, addCatalogItemToTray, openModifiers,
        handleModifierConfirm, removeSelection, injectModifiersIntoCurrentSelection, handleMobileBack, handleBackOrCancel, nextStep, handleClearCombo,
        isEditingComboItem, canGoBack, getSelectionsCountForGroup, isSelectionValid
    };
}
