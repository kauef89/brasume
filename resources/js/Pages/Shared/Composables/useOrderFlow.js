import { router } from "@inertiajs/vue3";
import { ref } from "vue";

export function useOrderFlow(props, cartContext, autoCheckoutCallback = null) {
    const { cart, addToCart, updateItemModifiers, loadEditOrder, startNewOrder, orderType } = cartContext;

    const isModifiersVisible = ref(false);
    const isComboVisible = ref(false);
    const activeProduct = ref(null);
    const editItemIndex = ref(null);

    const hasComboGroups = (prod) => {
        if (!prod || !prod.combo_groups) return false;
        return Array.isArray(prod.combo_groups)
            ? prod.combo_groups.length > 0
            : Object.keys(prod.combo_groups).length > 0;
    };

    const getStructuredMods = (item) => {
        if (item.cartItem?.structuredModifiers && Array.isArray(item.cartItem.structuredModifiers)) {
            return item.cartItem.structuredModifiers;
        }

        if (item.structuredModifiers && Array.isArray(item.structuredModifiers)) {
            return item.structuredModifiers;
        }

        const raw = item.favorite_selections
            || item.structured_modifiers
            || item.meta?.modifiers
            || (Array.isArray(item.modifiers) && !item.modifiers[0]?.options ? item.modifiers : []);

        if (!Array.isArray(raw)) return [];

        return raw.map(m => ({
            id: m.modifier_option_id || m.id,
            name: m.name,
            price: Number(m.price || 0),
            qty: Math.max(1, (m.quantity || m.qty || 1) / (item.quantity || item.qty || 1)),
            is_joker: m.is_joker !== undefined ? !!m.is_joker : (m.modifier_option_id === null),
            group_id: m.modifier_group_id || m.group_id || null
        }));
    };

    const initializeFlow = (urlParams) => {
        if (urlParams.get('action') === 'new') startNewOrder();

        if (props.editOrderData) {
            loadEditOrder(props.editOrderData);
            orderType.value = props.editOrderData.type;

            const duplicateItemId = urlParams.get('duplicate_item');
            const editItemId = urlParams.get('edit_item');
            const swapItemId = urlParams.get('swap_item');
            const originalQty = Number(urlParams.get('original_qty')) || 1;
            const isComboChild = urlParams.get('is_combo_child') === 'true';
            const parentItemId = urlParams.get('parent_item_id');

            const targetItemId = duplicateItemId || editItemId || swapItemId;

            if (targetItemId && props.editOrderData.items && props.editOrderData.items.length > 0) {
                const targetItem = props.editOrderData.items.find(i => String(i.id) === String(targetItemId));

                if (targetItem) {
                    const catalogProductId = targetItem.product_id || targetItem.item_id || targetItem.id;
                    const originalProduct = props.initialProducts.find(p => String(p.id) === String(catalogProductId));

                    if (originalProduct && !swapItemId) {
                        const structuredMods = getStructuredMods(targetItem);
                        const childItems = props.editOrderData.items.filter(i => String(i.parent_item_id) === String(targetItem.id));
                        const comboItems = targetItem.comboChildren || childItems.map(c => ({
                            product_id: c.product_id || c.item_id,
                            name: c.name || c.product_name,
                            modifiersText: c.modifiers_text,
                            structuredModifiers: getStructuredMods(c)
                        }));

                        const isSubItem = isComboChild || Boolean(targetItem.parent_item_id) || Boolean(parentItemId);

                        activeProduct.value = {
                            ...originalProduct,
                            is_combo_child: isSubItem,
                            combo_parent_id: parentItemId || targetItem.parent_item_id || null,
                            cartItem: {
                                qty: targetItem.quantity || 1,
                                structuredModifiers: structuredMods,
                                comboItems: (!isSubItem && comboItems.length > 0) ? comboItems : null,
                                isSubItem: isSubItem
                            }
                        };

                        if (editItemId) {
                            activeProduct.value.editContext = {
                                replaces_item_id: editItemId,
                                parent_item_id: parentItemId || targetItem.parent_item_id || null,
                                is_combo_child: isSubItem,
                                original_qty: originalQty,
                                auth_reason: urlParams.get('auth_reason'),
                                is_loss: urlParams.get('is_loss') === 'true'
                            };
                        }

                        if (isSubItem) {
                            isModifiersVisible.value = true;
                            isComboVisible.value = false;
                        } else {
                            (originalProduct.type === 'combo' || comboItems.length > 0)
                                ? isComboVisible.value = true
                                : isModifiersVisible.value = true;
                        }
                    }
                    else if (swapItemId) {
                        const swapContext = {
                            replaces_item_id: swapItemId,
                            original_qty: originalQty,
                            auth_reason: urlParams.get('auth_reason'),
                            is_loss: urlParams.get('is_loss') === 'true'
                        };
                        localStorage.setItem('pos_swap_context', JSON.stringify(swapContext));
                    }

                    localStorage.setItem('pos_return_url', '/operations');
                    localStorage.setItem('pos_return_order_id', props.editOrderData.id);
                }
            }
        }
    };

    const closeConfigurators = () => {
        const returnUrl = localStorage.getItem('pos_return_url');

        isModifiersVisible.value = false;
        isComboVisible.value = false;

        if (returnUrl === '/operations') {
            const orderId = localStorage.getItem('pos_return_order_id');
            localStorage.removeItem('pos_return_url');
            localStorage.removeItem('pos_return_order_id');
            router.visit(`/operations?open_order=${orderId}`);
            return;
        }

        setTimeout(() => { if (!isModifiersVisible.value && !isComboVisible.value) activeProduct.value = null; }, 300);
    };

    const handleProductClick = (product) => {
        let productWithContext = { ...product };

        const swapContext = localStorage.getItem('pos_swap_context');
        if (swapContext) {
            productWithContext.editContext = JSON.parse(swapContext);
            localStorage.removeItem('pos_swap_context');
        }

        activeProduct.value = productWithContext;
        editItemIndex.value = null;

        const typeStr = String(product.type || '').toLowerCase();
        const isActuallyACombo = typeStr === 'combo' || hasComboGroups(product) || product.isCombo === true;

        if (isActuallyACombo) {
            isComboVisible.value = true;
        } else {
            isModifiersVisible.value = true;
        }
    };

    const handleFavoriteClick = (favorite) => {
        const originalProduct = props.initialProducts.find(p => p.id === (favorite.product_id || favorite.id));

        if (!originalProduct) {
            alert("Este produto favorito não está mais disponível no cardápio atual.");
            return;
        }

        const structuredMods = getStructuredMods(favorite);
        let comboItems = null;
        const childItems = favorite.combo_items || favorite.subItems || [];

        if (childItems.length > 0) {
            comboItems = childItems.map(c => ({
                product_id: c.product_id || c.item_id,
                name: c.name || c.product_name,
                modifiersText: c.modifiers_text || c.modifiersText || "",
                structuredModifiers: getStructuredMods(c)
            }));
        }

        activeProduct.value = {
            ...originalProduct,
            cartItem: {
                qty: 1,
                structuredModifiers: structuredMods,
                comboItems: comboItems && comboItems.length > 0 ? comboItems : null
            }
        };

        editItemIndex.value = null;

        (originalProduct.type === 'combo' || comboItems) ? isComboVisible.value = true : isModifiersVisible.value = true;
    };

    const openEditModifiers = (item, index) => {
        const originalProduct = props.initialProducts.find((p) => p.id === item.id);
        activeProduct.value = { ...originalProduct, cartItem: item };
        editItemIndex.value = index;

        const typeStr = String(originalProduct?.type || '').toLowerCase();
        const isActuallyACombo = item.isCombo === true || typeStr === 'combo' || hasComboGroups(originalProduct);

        if (isActuallyACombo) {
            isComboVisible.value = true;
        } else {
            isModifiersVisible.value = true;
        }
    };

    const _finalizeContextualAction = (itemToAdd) => {
        if (activeProduct.value.editContext) {
            itemToAdd.replaces_item_id = activeProduct.value.editContext.replaces_item_id;
            itemToAdd.parent_item_id   = activeProduct.value.editContext.parent_item_id;
            itemToAdd.is_combo_child   = activeProduct.value.editContext.is_combo_child;
            itemToAdd.auth_reason      = activeProduct.value.editContext.auth_reason;
            itemToAdd.is_loss          = activeProduct.value.editContext.is_loss;
            itemToAdd.qty              = activeProduct.value.editContext.original_qty;

            if (itemToAdd.is_combo_child) {
                itemToAdd.isSubItemOfCombo = true;
                itemToAdd.basePriceOverride = 0;
            }

            addToCart(itemToAdd);

            isModifiersVisible.value = false;
            isComboVisible.value = false;

            if (autoCheckoutCallback) autoCheckoutCallback();
            return true;
        }
        return false;
    };

    const handleModifiersConfirm = (configuredItem) => {
        if (editItemIndex.value !== null) {
            updateItemModifiers(editItemIndex.value, { ...configuredItem, orderType: orderType.value });
            cart.value[editItemIndex.value].qty = configuredItem.qty;
        } else {
            let itemToAdd = { product: activeProduct.value, ...configuredItem, orderType: orderType.value };
            if (_finalizeContextualAction(itemToAdd)) return;
            addToCart(itemToAdd);
        }
        closeConfigurators();
    };

    const handleComboConfirm = (configuredCombo) => {
        let itemToAdd = {
            product: activeProduct.value,
            comboItems: configuredCombo.structuredItems,
            extraPrice: configuredCombo.extraPrice,
            orderType: orderType.value
        };
        if (_finalizeContextualAction(itemToAdd)) return;

        addToCart(itemToAdd);
        closeConfigurators();
    };

    return {
        isModifiersVisible, isComboVisible, activeProduct, editItemIndex,
        initializeFlow, closeConfigurators, handleProductClick, handleFavoriteClick, openEditModifiers,
        handleModifiersConfirm, handleComboConfirm
    };
}
