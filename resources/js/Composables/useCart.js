import { ref, computed } from "vue";

export function useCart() {
    const cart = ref([]);

    const addToCart = ({
        product,
        extraPrice = 0,
        isTakeaway = false,
        comboItems = null,
        structuredModifiers = [], // 🚀 Fonte da Verdade Única agora
        orderType = "table",
    }) => {
        const basePrice =
            orderType === "delivery" && product.delivery_price > 0
                ? product.delivery_price
                : product.price;

        const finalPrice = basePrice + extraPrice;
        const isCombo = comboItems !== null;
        const comboHash = isCombo ? JSON.stringify(comboItems) : null;

        // 🚀 HASH ESTRUTURADO: Se o array tem dados, converte pra JSON. Se vazio, "none".
        const modHash =
            structuredModifiers && structuredModifiers.length > 0
                ? JSON.stringify(structuredModifiers)
                : "none";

        const existingItem = cart.value.find(
            (item) =>
                item.id === product.id &&
                item.modHash === modHash &&
                item.isTakeaway === isTakeaway &&
                item.comboHash === comboHash,
        );

        if (existingItem) {
            existingItem.qty++;
        } else {
            cart.value.push({
                cartId: Date.now() + Math.random(),
                id: product.id,
                name: product.name,
                price: finalPrice,
                qty: 1,
                structuredModifiers: structuredModifiers,
                isTakeaway: isTakeaway,
                productOriginal: product,
                isCombo: isCombo,
                comboItems: comboItems,
                comboHash: comboHash,
                modHash: modHash,
            });
        }
    };

    const updateQty = (index, delta) => {
        if (cart.value[index]) {
            cart.value[index].qty += delta;
            if (cart.value[index].qty <= 0) cart.value.splice(index, 1);
        }
    };

    const updateItemModifiers = (
        index,
        { extraPrice, isTakeaway, orderType, structuredModifiers = [] },
    ) => {
        const item = cart.value[index];
        item.isTakeaway = isTakeaway;

        // 🚀 Atualização perfeita baseada na estrutura real
        item.structuredModifiers = structuredModifiers;
        item.modHash =
            structuredModifiers && structuredModifiers.length > 0
                ? JSON.stringify(structuredModifiers)
                : "none";

        const basePrice =
            orderType === "delivery" && item.productOriginal.delivery_price > 0
                ? item.productOriginal.delivery_price
                : item.productOriginal.price;

        item.price = basePrice + extraPrice;
    };

    const clearCart = () => {
        cart.value = [];
    };

    const cartSubtotal = computed(() =>
        cart.value.reduce((total, item) => total + item.price * item.qty, 0),
    );

    return {
        cart,
        addToCart,
        updateQty,
        updateItemModifiers,
        clearCart,
        cartSubtotal,
    };
}
