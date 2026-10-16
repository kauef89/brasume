import { usePage } from '@inertiajs/vue3';

export function useCustomerCheckoutPayload(props, cartContext, financials, activeCoupon, checkoutState) {
    const page = usePage();
    const { cart } = cartContext;

    const resolveProductId = (productId, id) => {
        for (const candidate of [productId, id]) {
            if (!candidate) continue;
            if (typeof candidate === 'number') return candidate;
            const str = String(candidate);
            const match = str.match(/_prod_(\d+)/) || str.match(/(\d+)$/);
            if (match) return parseInt(match[1], 10);
            const num = parseInt(str, 10);
            if (!isNaN(num)) return num;
        }
        return null;
    };

    const buildCleanItems = () => {
        return (cart.value || []).map(item => ({
            cartId: item.cartId,
            id: Number(item.id),
            name: item.name,
            price: Number(item.price),
            qty: Number(item.qty),
            notes: item.notes || null,
            isTakeaway: Boolean(item.isTakeaway),
            isCombo: Boolean(item.isCombo || (item.comboItems && item.comboItems.length > 0)),
            replaces_item_id: item.replaces_item_id || null,
            auth_reason: item.auth_reason || null,
            is_loss: item.is_loss || null,
            structuredModifiers: (item.structuredModifiers || []).map(mod => ({
                id: mod.id ? Number(mod.id) : null,
                group_id: mod.group_id ? Number(mod.group_id) : null,
                name: mod.name,
                price: Number(mod.price || 0),
                qty: Number(mod.qty || mod.quantity || 1)
            })),
            comboItems: (item.comboItems || []).map(combo => ({
                id: combo.id,
                product_id: resolveProductId(combo.product_id, combo.id),
                name: combo.name || combo.product_name || 'Subitem',
                group_id: combo.group_id ? Number(combo.group_id) : null,
                group_name: combo.group_name ?? null,
                price: Number(combo.price || 0),
                qty: Number(combo.qty || combo.quantity || 1),
                notes: combo.notes || null,
                structuredModifiers: (combo.structuredModifiers || []).map(cMod => ({
                    id: cMod.id ? Number(cMod.id) : null,
                    group_id: cMod.group_id ? Number(cMod.group_id) : null,
                    name: cMod.name,
                    price: Number(cMod.price || 0),
                    qty: Number(cMod.qty || cMod.quantity || 1)
                }))
            }))
        }));
    };

    const buildCleanAddress = (rawAddr) => {
        if (!rawAddr) return null;

        const resolvedDeliveryFee = rawAddr.delivery_fee !== undefined && rawAddr.delivery_fee !== null
            ? Number(rawAddr.delivery_fee)
            : (rawAddr.neighborhood?.delivery_fee !== undefined ? Number(rawAddr.neighborhood.delivery_fee) : financials.value.deliveryFee);

        const isNoNumber = Boolean(rawAddr.no_number || rawAddr.noNumber || rawAddr.number === 'S/N');

        return {
            ...rawAddr,
            id: rawAddr.id || null,
            street_id: rawAddr.street?.id || rawAddr.street_id || null,
            street: typeof rawAddr.street === 'object' && rawAddr.street !== null ? (rawAddr.street.name || '') : (rawAddr.street || ''),
            neighborhood_id: rawAddr.neighborhood?.id || rawAddr.neighborhood_id || null,
            neighborhood: typeof rawAddr.neighborhood === 'object' && rawAddr.neighborhood !== null ? (rawAddr.neighborhood.name || '') : (rawAddr.neighborhood || ''),
            number: isNoNumber ? 'S/N' : (rawAddr.number !== undefined && rawAddr.number !== null ? String(rawAddr.number) : 'S/N'),
            no_number: isNoNumber,
            complement: rawAddr.complement || null,
            reference: rawAddr.reference || null,
            nickname: rawAddr.nickname || null,
            full_address: rawAddr.full_address || null,
            delivery_fee: resolvedDeliveryFee
        };
    };

    const buildPayload = (finalSnapshot) => {
        const tableMode = page.props.tableMode;
        const isTable = Boolean(tableMode) || checkoutState.orderType === 'table';

        const rawRouting = checkoutState.routingMeta?.meta || checkoutState.routingMeta || {};
        const isGuestUser = props.isGuest || !page.props.auth?.user;

        let resolvedCustomer = null;
        if (props.customer && props.customer.id) {
            resolvedCustomer = {
                id: props.customer.id,
                name: props.customer.name,
                phone: props.customer.phone,
                cpf: props.customer.cpf,
                email: props.customer.email
            };
        } else if (isGuestUser) {
            resolvedCustomer = {
                id: 'temp_' + Date.now(),
                name: checkoutState.guestName || 'Visitante',
                phone: checkoutState.guestPhone || null
            };
        }

        const isBankApi = checkoutState.selectedMethod?.engine_type === 'bank_api';

        const routing = {
            ...rawRouting,
            type: isTable ? 'table' : checkoutState.orderType,
            customer: resolvedCustomer,
            customer_id: resolvedCustomer?.id,
            customer_name: resolvedCustomer?.name,
            customer_phone: resolvedCustomer?.phone,
            address_id: isTable ? null : (rawRouting.address_id || null),
            address: isTable ? null : buildCleanAddress(rawRouting.address),
            payment_method_id: checkoutState.selectedMethod?.id || null,
            payment_method_name: checkoutState.selectedMethod?.name || null,
            payment_engine: checkoutState.selectedMethod?.engine_type || 'local',
            payment_intent_status: isBankApi ? 'pix_waiting' : (isTable ? 'pay_on_pickup' : 'pay_on_delivery'),
            cashback_discount: financials.value.cashbackDiscount > 0 ? financials.value.cashbackDiscount : null,
            cash_change_target: checkoutState.cashChangeTarget || null
        };

        if (isTable) {
            const tableId = tableMode?.id || rawRouting.table_id || rawRouting.dining_table_id;
            const tableName = tableMode?.name || rawRouting.table_number || rawRouting.table?.name || '';
            routing.dining_table_id = tableId ? Number(tableId) : null;
            routing.table_id = tableId ? Number(tableId) : null;
            routing.table_number = tableName;
            routing.table = { id: tableId, name: tableName };
        }

        const payload = {
            items: buildCleanItems(),
            routing,
            financials: {
                subtotal: financials.value.subtotal,
                deliveryFee: isTable ? 0 : financials.value.deliveryFee,
                delivery_fee: isTable ? 0 : financials.value.deliveryFee,
                serviceFee: financials.value.serviceFee,
                service_fee: financials.value.serviceFee,
                discount: financials.value.discount,
                finalTotal: finalSnapshot,
                coupon_code: activeCoupon.value?.code || null
            }
        };

        const urlParams = new URLSearchParams(window.location.search);
        const resolvedAppendId = props.editOrderData?.id
            || urlParams.get('append_to_order')
            || sessionStorage.getItem('customer_append_order_id');

        if (resolvedAppendId) {
            payload.append_to_order = Number(resolvedAppendId);
        }

        return payload;
    };

    return { resolveProductId, buildCleanItems, buildCleanAddress, buildPayload };
}
