import { useAnalytics } from '@/Composables/useAnalytics';
import { useCoupons } from '@/Pages/Shared/Composables/useCoupons';
import { useStorage } from "@vueuse/core";
import { computed, nextTick, watch } from "vue";

export const createDefaultRouting = (type = 'table') => ({
    order_id: null,
    existing_order_id: null,
    type: type,
    dining_table_id: null,
    table_id: null,
    table: null,
    identifier: '',
    customer: null,
    customer_phone: null,
    address_id: null,
    address: null
});

export function generateModifiersHash(modifiers = []) {
    if (!modifiers || !Array.isArray(modifiers) || modifiers.length === 0) {
        return "none";
    }
    const normalized = [...modifiers]
        .map(m => ({
            id: Number(m.id || m.modifier_option_id || 0),
            group_id: Number(m.group_id || m.modifier_group_id || 0),
            qty: Number(m.qty || m.quantity || 1),
            price: Number(m.price || 0),
            name: String(m.name || '').trim()
        }))
        .sort((a, b) => (a.group_id - b.group_id) || (a.id - b.id) || a.name.localeCompare(b.name));

    return JSON.stringify(normalized);
}

export function generateComboHash(comboItems = []) {
    if (!comboItems || !Array.isArray(comboItems) || comboItems.length === 0) {
        return "none";
    }
    const normalized = [...comboItems].map(c => ({
        product_id: Number(c.product_id || c.id || 0),
        group_id: Number(c.group_id || 0),
        qty: Number(c.qty || c.quantity || 1),
        modHash: generateModifiersHash(c.structuredModifiers || c.modifiers || [])
    })).sort((a, b) => (a.group_id - b.group_id) || (a.product_id - b.product_id));

    return JSON.stringify(normalized);
}

const cart = useStorage("pos_draft_cart", []);
const parkedOrders = useStorage("pos_parked_orders", []);
const globalOrderType = useStorage("pos_draft_type", 'table');
const globalDraftRouting = useStorage("pos_draft_routing", createDefaultRouting('table'));

watch(globalOrderType, (newType) => {
    if (globalDraftRouting.value) {
        globalDraftRouting.value.type = newType;
        if (newType !== 'delivery') {
            globalDraftRouting.value.address = null;
            globalDraftRouting.value.address_id = null;
        }
    }
}, { immediate: true });

export function validateProductModifiers(prod, structuredModifiers = []) {
    if (!prod) return { valid: true };

    const groups = prod.modifier_groups || prod.modifierGroups || [];
    for (const group of groups) {
        const min = Number(group.min_choices ?? group.min_quantity ?? (group.is_required ? 1 : 0));
        if (min > 0) {
            const selectedInGroup = (structuredModifiers || []).filter(m =>
                String(m.group_id || m.modifier_group_id) === String(group.id)
            );
            const totalQty = selectedInGroup.reduce((sum, m) => sum + Number(m.qty || 1), 0);

            if (totalQty < min) {
                return {
                    valid: false,
                    productName: prod.name || 'Produto',
                    groupName: group.name || group.title || 'Modificador Obrigatório',
                    missing: min - totalQty
                };
            }
        }
    }
    return { valid: true };
}

export function useCart() {
    const { trackAddToCart } = useAnalytics();

    const setOrderType = (newType) => {
        globalOrderType.value = newType;
        if (globalDraftRouting.value) {
            globalDraftRouting.value.type = newType;
            if (newType !== 'delivery') {
                globalDraftRouting.value.address = null;
                globalDraftRouting.value.address_id = null;
            }
        }
    };

    const addToCart = ({
        product, extraPrice = 0, isTakeaway = false,
        comboItems = null, structuredModifiers = [], qty = 1,
        replaces_item_id = null, parent_item_id = null, is_combo_child = false,
        auth_reason = null, is_loss = null, isSubItemOfCombo = false, basePriceOverride = null
    }) => {
        const mainCheck = validateProductModifiers(product, structuredModifiers);
        if (!mainCheck.valid) {
            console.error(`[useCart] Inserção bloqueada: "${mainCheck.productName}" requer seleção no grupo "${mainCheck.groupName}".`);
            return false;
        }

        if (comboItems && Array.isArray(comboItems)) {
            for (const sub of comboItems) {
                const subProd = sub.product || sub;
                const subCheck = validateProductModifiers(subProd, sub.structuredModifiers);
                if (!subCheck.valid) {
                    console.error(`[useCart] Combo bloqueado: O item "${subCheck.productName}" requer "${subCheck.groupName}".`);
                    return false;
                }
            }
        }

        let basePrice = 0;
        if (!isSubItemOfCombo && basePriceOverride === null) {
            basePrice = globalOrderType.value === "delivery" && product.delivery_price > 0
                ? Number(product.delivery_price)
                : Number(product.price ?? product.dine_in_price ?? 0);
        }

        const finalPrice = basePrice + Number(extraPrice || 0);
        const isCombo = comboItems !== null;
        const comboHash = isCombo ? generateComboHash(comboItems) : null;
        const modHash = generateModifiersHash(structuredModifiers);

        const totalUnits = Math.max(1, Math.floor(Number(qty || 1)));
        const isAuditReplacement = Boolean(replaces_item_id || is_combo_child);
        const loopCount = isAuditReplacement ? 1 : totalUnits;
        const unitQty = isAuditReplacement ? totalUnits : 1;

        for (let i = 0; i < loopCount; i++) {
            cart.value.push({
                cartId: Date.now() + Math.random() + i,
                id: product.id,
                name: isSubItemOfCombo ? `${product.name} (Adicional Combo)` : product.name,
                price: finalPrice,
                extra_price: Number(extraPrice || 0),
                qty: unitQty,
                structuredModifiers: JSON.parse(JSON.stringify(structuredModifiers || [])),
                isTakeaway: isTakeaway,
                productOriginal: product,
                isCombo: isCombo,
                comboItems: comboItems ? JSON.parse(JSON.stringify(comboItems)) : null,
                comboHash: comboHash,
                modHash: modHash,
                replaces_item_id: replaces_item_id,
                parent_item_id: parent_item_id || product.combo_parent_id || null,
                is_combo_child: is_combo_child || isSubItemOfCombo,
                auth_reason: auth_reason,
                is_loss: is_loss
            });
            trackAddToCart(product, extraPrice + (product.promo_price || product.price || 0));
        }
        return true;
    };

    const updateQty = (index, delta) => {
        const target = cart.value[index];
        if (!target) return;

        const numericDelta = Number(delta);
        if (numericDelta > 0) {
            const copiesToAdd = Math.max(1, Math.floor(numericDelta));
            for (let i = 0; i < copiesToAdd; i++) {
                const clone = {
                    ...target,
                    cartId: Date.now() + Math.random() + i,
                    qty: 1,
                    structuredModifiers: JSON.parse(JSON.stringify(target.structuredModifiers || [])),
                    comboItems: target.comboItems ? JSON.parse(JSON.stringify(target.comboItems)) : null
                };
                cart.value.splice(index + 1 + i, 0, clone);
            }
            return;
        }

        const current = Number(target.qty ?? target.quantity ?? 1);
        const next = current + numericDelta;
        target.qty = next;
        if (target.qty <= 0) cart.value.splice(index, 1);
    };

    const updateItemModifiers = (index, { extraPrice, isTakeaway, structuredModifiers = [] }) => {
        const item = cart.value[index];
        if (!item) return;

        const check = validateProductModifiers(item.productOriginal, structuredModifiers);
        if (!check.valid) {
            console.error(`[useCart] Edição bloqueada: "${check.productName}" requer "${check.groupName}".`);
            return false;
        }

        item.isTakeaway = isTakeaway;
        item.structuredModifiers = structuredModifiers;
        item.modHash = generateModifiersHash(structuredModifiers);

        if (item.is_combo_child) {
            item.price = Number(extraPrice || 0);
            item.extra_price = Number(extraPrice || 0);
        } else {
            const basePrice = globalOrderType.value === "delivery" && item.productOriginal.delivery_price > 0
                ? Number(item.productOriginal.delivery_price)
                : Number(item.productOriginal.price ?? item.productOriginal.dine_in_price ?? 0);
            item.price = basePrice + extraPrice;
        }
        return true;
    };

    const resetDraftState = (type = 'table') => {
        // 1. Esvazia array reativo e reatribui instância limpa
        if (Array.isArray(cart.value)) {
            cart.value.splice(0, cart.value.length);
        }
        cart.value = [];

        // 2. Limpa cupom ativo junto com o rascunho para nunca vazar para o próximo pedido
        try {
            const { removeCoupon } = useCoupons();
            removeCoupon();
        } catch (e) {}

        // 3. Reinicializa o roteamento com o tipo padrão solicitado
        globalOrderType.value = type;
        const freshRouting = createDefaultRouting(type);
        globalDraftRouting.value = freshRouting;

        // 4. Purga imediata e assentamento pós-flush do VueUse (useStorage)
        const flushStorage = () => {
            try {
                localStorage.removeItem('pos_cart');
                localStorage.removeItem('pos_draft_cart');
                localStorage.removeItem('pos_draft_routing');
                localStorage.removeItem('pos_draft_type');
                localStorage.removeItem('pos_swap_context');
                localStorage.removeItem('pos_return_url');
                localStorage.removeItem('pos_return_order_id');
                localStorage.removeItem('pos_magic_coupon');
                sessionStorage.removeItem('pending_coupon_code');
                sessionStorage.removeItem('customer_append_order_id');
                sessionStorage.removeItem('customer_active_pix_order_id');
                sessionStorage.removeItem('customer_active_pix_data');

                localStorage.setItem('pos_draft_cart', '[]');
                localStorage.setItem('pos_draft_routing', JSON.stringify(freshRouting));
                localStorage.setItem('pos_draft_type', type);
            } catch (e) {}
        };

        flushStorage();
        nextTick(flushStorage);

        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('cart-cleared'));
        }
    };

    const clearCart = (type = 'table') => {
        resetDraftState(type);
    };

    const cartSubtotal = computed(() => cart.value.reduce((total, item) => total + item.price * item.qty, 0));

    const parkCurrentOrder = (orderMetadata = null, orderType = null) => {
        if (!Array.isArray(cart.value) || cart.value.length === 0) return;
        const resolvedMeta = orderMetadata || globalDraftRouting.value || {};
        const resolvedType = orderType || globalOrderType.value || 'table';

        parkedOrders.value.push({
            id: Date.now(),
            date: new Date().toISOString(),
            cart: JSON.parse(JSON.stringify(cart.value)),
            type: resolvedType,
            meta: JSON.parse(JSON.stringify(resolvedMeta)),
            total: cartSubtotal.value
        });
        clearCart('table');
    };

    // Parking automático desativado: estacionamento ocorre apenas por solicitação explícita do operador no PosApp
    const autoParkCurrent = () => {};

    const resumeOrder = (parkedId) => {
        const targetOrder = parkedOrders.value.find(p => p.id === parkedId);
        if (!targetOrder) return;

        if (cart.value && cart.value.length > 0) {
            parkCurrentOrder();
        }

        const index = parkedOrders.value.findIndex(p => p.id === parkedId);
        if (index === -1) return;

        const parked = parkedOrders.value[index];
        cart.value = JSON.parse(JSON.stringify(parked.cart || []));
        globalOrderType.value = parked.type || 'table';
        globalDraftRouting.value = JSON.parse(JSON.stringify(parked.meta || createDefaultRouting(parked.type || 'table')));
        parkedOrders.value.splice(index, 1);
    };

    const discardParkedOrder = (parkedId) => {
        const index = parkedOrders.value.findIndex(p => p.id === parkedId);
        if (index > -1) parkedOrders.value.splice(index, 1);
    };

    const loadEditOrder = () => {
        clearCart('table');
    };

    const startNewOrder = () => {
        clearCart('table');
    };

    return {
        cart,
        orderType: globalOrderType,
        draftRouting: globalDraftRouting,
        setOrderType,
        addToCart,
        updateQty,
        updateItemModifiers,
        clearCart,
        cartSubtotal,
        parkedOrders,
        parkCurrentOrder,
        parkOrder: parkCurrentOrder,
        resumeOrder,
        discardParkedOrder,
        loadEditOrder,
        startNewOrder,
        autoParkCurrent,
        resetDraftState
    };
}
