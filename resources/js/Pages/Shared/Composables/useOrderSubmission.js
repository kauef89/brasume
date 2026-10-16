import { useCart } from "@/Pages/Shared/Composables/useCart";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { unref } from "vue";

export function useOrderSubmission(cartRef, editOrderId, source = 'pos_app') {
    const toast = useToast();
    const page = usePage();
    const { resetDraftState } = useCart();

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
    }

    const executeSubmit = (payload, endpointUrl) => {
        return new Promise((resolve, reject) => {
            router.post(endpointUrl, payload, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (pageResp) => {
                    if (pageResp.props.flash?.error) {
                        toast.add({ severity: "error", summary: "Atenção", detail: pageResp.props.flash.error, life: 5000 });
                        reject(new Error(pageResp.props.flash.error));
                        return;
                    }

                    const createdOrderId = pageResp.props.flash?.order_id
                        || pageResp.props.order_id
                        || pageResp.props.flash?.success_order_id
                        || pageResp.props.flash?.order?.id
                        || payload.order_id;

                    resetDraftState();

                    const resultData = { order: { id: createdOrderId }, id: createdOrderId };

                    const isPayNow = payload.frontend_intent === 'pay_now'
                                  || payload.intent === 'pay_now'
                                  || payload.routing?.payment_intent_status === 'pay_now';

                    if (isPayNow && createdOrderId) {
                        window.dispatchEvent(new CustomEvent('pos-open-checkout', { detail: { orderId: createdOrderId } }));
                        resolve(resultData);
                        return;
                    }

                    window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'orders' }));
                    resolve(resultData);
                },
                onError: (errors) => {
                    const message = Object.values(errors)[0] || "Erro ao processar o pedido. Revise o carrinho.";
                    toast.add({ severity: "error", summary: "Erro", detail: message, life: 5000 });
                    reject(errors);
                }
            });
        });
    };

    const submitOrder = (checkoutData) => {
        const settings = page.props.companySettings || {};
        const meta = checkoutData?.meta || {};
        const currentType = meta.type || 'table';

        // 🚀 O pulo do gato: Identificação direta se estamos anexando a algo existente
        const rawMetaOrderId = unref(meta.order_id) || unref(meta.existing_order_id) || unref(meta.id) || unref(checkoutData?.order_id) || unref(checkoutData?.append_to_order);
        const rawEditOrderId = unref(editOrderId);
        const resolvedOrderId = rawMetaOrderId || rawEditOrderId || null;

        const tableId = meta.table?.id || meta.dining_table_id || meta.table_id || null;
        const tableName = meta.table?.name || meta.table_number || '';
        const addressId = meta.address?.id || meta.address_id || null;

        // Validações exclusivas para "Criação de Comanda"
        if (!resolvedOrderId) {
            if (currentType === 'table' && !tableId) {
                toast.add({ severity: 'warn', summary: 'Mesa Obrigatória', detail: 'Selecione uma mesa válida para lançar o pedido.', life: 4500 });
                return Promise.reject(new Error('Mesa obrigatória'));
            }

            if (currentType === 'counter') {
                const customerName = (meta.customer?.name || meta.customer_name || meta.identifier || '').trim();
                const customerId = meta.customer?.id || meta.customer_id;

                if (!customerName && !customerId) {
                    toast.add({ severity: 'warn', summary: 'Identificação Obrigatória', detail: 'Para pedidos de Balcão, informe o nome ou selecione o cliente.', life: 4500 });
                    return Promise.reject(new Error('Identificação obrigatória'));
                }
            }
        }

        const cleanItems = (cartRef.value || []).map(item => ({
            cartId: item.cartId,
            id: Number(item.id),
            name: item.name,
            price: Number(item.price || 0),
            extra_price: Number(item.extra_price || item.price || 0),
            qty: Number(item.qty),
            notes: item.notes || null,
            isTakeaway: Boolean(item.isTakeaway),
            isCombo: Boolean(item.isCombo),
            replaces_item_id: item.replaces_item_id || null,
            parent_item_id: item.parent_item_id || null,
            is_combo_child: Boolean(item.is_combo_child),
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

        const payload = {
            source: source,
            items: cleanItems,
            routing: {
                ...meta,
                type: currentType,
                dining_table_id: tableId ? Number(tableId) : null,
                table_id: tableId ? Number(tableId) : null,
                table_number: tableName,
                address_id: addressId ? Number(addressId) : null,
                kitchen_print_mode: settings.kitchen_print_mode || 'station',
                kitchen_separate_multiples: settings.kitchen_separate_multiples || false
            },
            financials: checkoutData.totals,
            frontend_intent: checkoutData.frontend_intent || checkoutData.intent || meta.payment_intent_status || null,
            is_internal_operation: true
        };

        // 🚀 ROTEAMENTO: Escolhe a Rota Dedicada
        const endpointUrl = resolvedOrderId
            ? route('operations.orders.append', resolvedOrderId)
            : route('operations.orders.store');

        if (resolvedOrderId) {
            payload.order_id = resolvedOrderId; // Previne warnings em rotinas internas
        }

        return executeSubmit(payload, endpointUrl);
    };

    return { submitOrder };
}
