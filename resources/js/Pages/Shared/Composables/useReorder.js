import { useToast } from "primevue/usetoast";

export function useReorder(cartContext, activeTab, initialProductsRef) {
    const toast = useToast();

    const handleReorder = (order) => {
        const { clearCart, addToCart, orderType } = cartContext;

        clearCart();
        orderType.value = order.type || 'delivery';

        let hasMissingItems = false;
        let missingNames = [];

        // 🚀 CORREÇÃO CANÔNICA: Itens principais são aqueles SEM parent_item_id (incluindo combos)
        const parentItems = (order.items || []).filter(i => !i.parent_item_id);

        parentItems.forEach(oldItem => {
            const currentProduct = (initialProductsRef.value || []).find(p => p.id === oldItem.product_id);

            if (!currentProduct || currentProduct.status === 'out_of_stock' || currentProduct.status === 'unavailable') {
                hasMissingItems = true;
                missingNames.push(oldItem.product_name);
                return;
            }

            const itemQty = Math.max(1, Number(oldItem.quantity || 1));

            // Modificadores do item principal
            const structuredMods = (oldItem.modifiers || []).map(m => ({
                id: m.modifier_option_id || m.id,
                name: m.name,
                price: Number(m.price || 0),
                qty: Math.max(1, Number(m.quantity || 1) / itemQty),
                is_joker: !m.modifier_option_id,
                group_id: m.modifier_group_id || m.group_id || null
            }));

            // Subitens vinculados via parent_item_id
            const rawSubItems = (oldItem.sub_items || oldItem.subItems || []).length > 0
                ? (oldItem.sub_items || oldItem.subItems)
                : (order.items || []).filter(i => String(i.parent_item_id) === String(oldItem.id));

            let comboItems = null;

            if (rawSubItems.length > 0) {
                comboItems = rawSubItems.map((sub, idx) => {
                    const subQty = Math.max(1, Number(sub.quantity || 1));
                    const subMods = (sub.modifiers || []).map(m => ({
                        id: m.modifier_option_id || m.id,
                        group_id: m.modifier_group_id || m.group_id || null,
                        name: m.name,
                        price: Number(m.price || 0),
                        qty: Math.max(1, Number(m.quantity || 1) / subQty),
                        is_joker: !m.modifier_option_id
                    }));

                    return {
                        id: sub.product_id || sub.id || idx,
                        product_id: sub.product_id,
                        name: sub.product_name || sub.name,
                        product_name: sub.product_name || sub.name,
                        group_id: sub.group_id || sub.combo_group_id || null,
                        group_name: sub.group_name || '',
                        price: Number(sub.unit_price || sub.price || 0),
                        qty: subQty,
                        structuredModifiers: subMods
                    };
                });
            }

            let extraPrice = structuredMods.reduce((sum, m) => sum + (m.price * m.qty), 0);
            if (comboItems && comboItems.length > 0) {
                comboItems.forEach(c => {
                    extraPrice += Number(c.price || 0);
                    extraPrice += (c.structuredModifiers || []).reduce((sum, m) => sum + (m.price * m.qty), 0);
                });
            }

            const isCombo = Boolean(comboItems && comboItems.length > 0) || Boolean(currentProduct.is_combo);

            addToCart({
                product: currentProduct,
                isCombo: isCombo,
                extraPrice: extraPrice,
                isTakeaway: Boolean(oldItem.is_to_go || oldItem.isTakeaway),
                comboItems: comboItems,
                structuredModifiers: structuredMods,
                orderType: order.type || orderType.value,
                qty: itemQty
            });
        });

        if (hasMissingItems) {
            toast.add({
                severity: 'warn',
                summary: 'Aviso de Estoque',
                detail: `Alguns itens não estão disponíveis hoje e foram ignorados: ${missingNames.join(', ')}`,
                life: 6000
            });
        } else {
            toast.add({
                severity: 'success',
                summary: 'Carrinho Montado!',
                detail: 'O seu pedido anterior foi carregado com sucesso.',
                life: 3000
            });
        }

        activeTab.value = 'cart';
    };

    return { handleReorder };
}
