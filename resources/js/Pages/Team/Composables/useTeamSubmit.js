import { useCart } from '@/Pages/Shared/Composables/useCart';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { ref, nextTick } from 'vue';

export function useTeamSubmit(state) {
    const toast = useToast();
    const isSubmittingOrder = ref(false);
    const isOrderSuccess = ref(false);
    const { draftRouting } = useCart();

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

    const handleCreateOrderOnTable = (table, isOccupied, existingOrder) => {
        if (isOccupied && existingOrder) {
            state.openContextMenu(existingOrder);
            return;
        }

        state.clearCart();

        const resolvedTableId = Number(table.dining_table_id || table.id);

        const routingPayload = {
            order_id: null,
            existing_order_id: null,
            type: 'table',
            dining_table_id: resolvedTableId,
            table_id: resolvedTableId,
            table: { id: resolvedTableId, name: table.name || '', status: table.status || '' },
            identifier: '',
            customer: null,
            customer_phone: null,
            address_id: null,
            address: null,
            source: 'team_app'
        };

        if (draftRouting) {
            draftRouting.value = routingPayload;
        }

        state.cartRoutingMeta.value = routingPayload;
        state.cartRoutingValid.value = true;
        state.orderType.value = 'table';

        try {
            localStorage.setItem('pos_draft_routing', JSON.stringify(routingPayload));
            localStorage.setItem('pos_draft_type', 'table');
        } catch (e) {}

        nextTick(() => {
            state.cartComponentKey.value++;
            state.activeTab.value = 'menu';
        });
    };

    const customHandleMenuAction = (actionId, order) => {
        // 🛡️ Garante a recuperação da comanda mesmo se o evento não passar o objeto
        const targetOrder = order || state.selectedOrderContext?.value;
        if (!targetOrder) return;

        const resolvedTableId = targetOrder.dining_table_id
            || targetOrder.table_id
            || targetOrder.dining_table?.id
            || targetOrder.diningTable?.id
            || targetOrder.table?.id
            || null;

        if (actionId === 'new_subaccount') {
            if (state.contextMenuVisible) state.contextMenuVisible.value = false;
            if (state.showOrderActionsSheet) state.showOrderActionsSheet.value = false;
            state.clearCart();

            const routingPayload = {
                order_id: null,
                existing_order_id: null,
                type: 'table',
                is_subaccount: true,
                dining_table_id: resolvedTableId ? Number(resolvedTableId) : null,
                table_id: resolvedTableId ? Number(resolvedTableId) : null,
                table: {
                    id: resolvedTableId ? Number(resolvedTableId) : null,
                    name: targetOrder.table_number || targetOrder.table?.name || ''
                },
                identifier: `Sub-conta ${targetOrder.table_number || ''}`,
                customer: null,
                customer_phone: null,
                address_id: null,
                address: null,
                source: 'team_app'
            };

            if (draftRouting) draftRouting.value = routingPayload;
            state.cartRoutingMeta.value = routingPayload;
            state.cartRoutingValid.value = Boolean(resolvedTableId);
            state.orderType.value = 'table';

            try {
                localStorage.setItem('pos_draft_routing', JSON.stringify(routingPayload));
                localStorage.setItem('pos_draft_type', 'table');
            } catch (e) {}

            nextTick(() => {
                state.cartComponentKey.value++;
                state.activeTab.value = 'menu';
            });

            void(0)

        } else if (actionId === 'add_items') {
            // 🛑 1. FECHA TODOS OS MODAIS E SHEETS PARA EVITAR RETENÇÃO DE MEMÓRIA
            if (state.contextMenuVisible) state.contextMenuVisible.value = false;
            if (state.showOrderActionsSheet) state.showOrderActionsSheet.value = false;
            state.clearCart();

            const orderTitle = targetOrder.type === 'table' ? `Mesa ${targetOrder.table_number}` : (targetOrder.customer_name || `Comanda #${targetOrder.id}`);

            const resolvedAddressId = targetOrder.address_id
                || targetOrder.address?.id
                || targetOrder.deliveryAddress?.id
                || null;

            // 🛑 2. PAYLOAD HIGIENIZADO (APENAS TIPOS PRIMITIVOS E ESSENCIAIS)
            const routingPayload = {
                id: targetOrder.id,
                order_id: targetOrder.id,
                existing_order_id: targetOrder.id,
                type: targetOrder.type || 'table',
                dining_table_id: resolvedTableId ? Number(resolvedTableId) : null,
                table_id: resolvedTableId ? Number(resolvedTableId) : null,
                table: {
                    id: resolvedTableId ? Number(resolvedTableId) : null,
                    name: targetOrder.table_number || targetOrder.table?.name || ''
                },
                identifier: targetOrder.customer_name || targetOrder.identifier || '',
                customer: targetOrder.customer ? { id: targetOrder.customer.id, name: targetOrder.customer.name, phone: targetOrder.customer.phone } : null,
                customer_phone: targetOrder.customer_phone || targetOrder.customer?.phone || null,
                address_id: resolvedAddressId,
                address: targetOrder.address_snapshot || null,
                source: 'team_app'
            };

            if (draftRouting) draftRouting.value = routingPayload;
            state.cartRoutingMeta.value = routingPayload;
            state.cartRoutingValid.value = true;
            state.orderType.value = targetOrder.type || 'table';

            try {
                localStorage.setItem('pos_draft_routing', JSON.stringify(routingPayload));
                localStorage.setItem('pos_draft_type', targetOrder.type || 'table');
            } catch (e) {}

            // 🛑 3. QUEBRA DA PILHA SÍNCRONA: Aguarda a desmontagem do menu de contexto
            nextTick(() => {
                state.cartComponentKey.value++;
                state.activeTab.value = 'menu';
            });

            void(0)

        } else {
            state.handleMenuAction(actionId, targetOrder);
        }
    };

    const handleSendOrder = (checkoutData) => {
        isSubmittingOrder.value = true;

        const cleanItems = (state.cart.value || []).map(item => ({
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

        const resolvedOrderId = state.props.editOrderData?.id
            || checkoutData.meta?.id
            || checkoutData.meta?.order_id
            || state.cartRoutingMeta.value?.id
            || draftRouting?.value?.id
            || null;

        const rawRouting = checkoutData.meta || state.cartRoutingMeta.value || draftRouting?.value || {};
        const tableId = rawRouting.table?.id || rawRouting.dining_table_id || rawRouting.table_id || null;
        const addressId = rawRouting.address?.id || rawRouting.address_id || null;

        const resolvedRouting = {
            ...rawRouting,
            dining_table_id: tableId ? Number(tableId) : null,
            table_id: tableId ? Number(tableId) : null,
            address_id: addressId ? Number(addressId) : null,
            source: 'team_app'
        };

        if (state.isAllowanceMode?.value) {
            resolvedRouting.payment_engine = 'internal';
            resolvedRouting.payment_method_name = 'Consumo Interno';
        }

        const resolvedFinancials = {
            subtotal: Number(checkoutData.totals?.subtotal || state.cartSubtotal.value || 0),
            deliveryFee: Number(checkoutData.totals?.deliveryFee || 0),
            serviceFee: Number(checkoutData.totals?.serviceFee || 0),
            discount: Number(checkoutData.totals?.discount || 0),
            finalTotal: Number(checkoutData.totals?.finalTotal || state.cartSubtotal.value || 0),
            coupon_code: checkoutData.totals?.coupon_code || null,
            coupon_id: checkoutData.totals?.coupon_id || null,
        };

        const payload = {
            source: 'team_app',
            order_id: resolvedOrderId,
            items: cleanItems,
            routing: resolvedRouting,
            financials: resolvedFinancials,
        };

        const backupCart = state.cart.value.map(item => ({ ...item }));

        if (window.location.search) {
            window.history.replaceState({}, '', window.location.pathname);
        }

        router.post(route('operations.orders.store'), payload, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.props.flash && (page.props.flash.error || page.props.flash.warning)) {
                    isSubmittingOrder.value = false;
                    state.cart.value = backupCart;
                    const errorMsg = page.props.flash.error || page.props.flash.warning;
                    toast.add({ severity: "error", summary: "Erro no Servidor", detail: errorMsg, life: 8000 });
                    return;
                }

                isSubmittingOrder.value = false;
                isOrderSuccess.value = true;

                if (state.isAllowanceMode) state.isAllowanceMode.value = false;
                state.clearCart();
                state.cartRoutingValid.value = false;
                state.cartRoutingMeta.value = null;
                if (draftRouting) draftRouting.value = null;
                localStorage.removeItem('pos_draft_routing');

                state.cartComponentKey.value++;

                setTimeout(() => {
                    isOrderSuccess.value = false;
                    state.activeTab.value = 'operations';

                    router.reload({
                        only: ['initialOrders'],
                        preserveState: true,
                        preserveScroll: true
                    });
                }, 1200);
            },
            onError: (errors) => {
                isSubmittingOrder.value = false;
                state.cart.value = backupCart;

                const errorMsg = errors && Object.values(errors).length > 0
                    ? Object.values(errors)[0]
                    : "Falha de comunicação com o servidor.";

                toast.add({ severity: "error", summary: "Erro de Validação", detail: errorMsg, life: 6000 });
            },
        });
    };

    const triggerCheckout = () => {
        if (state.cart.value.length === 0) {
            isSubmittingOrder.value = true;
            setTimeout(() => isSubmittingOrder.value = false, 50);

            toast.add({ severity: 'warn', summary: 'Comanda Vazia', detail: 'Adicione itens antes de confirmar o pedido.', life: 3000 });
            return;
        }

        const currentMeta = state.cartRoutingMeta.value || draftRouting?.value || {};
        const currentType = state.orderType.value || currentMeta.type || 'table';

        const tableId = currentMeta.dining_table_id || currentMeta.table_id || currentMeta.table?.id;
        const hasTable = currentType === 'table' && Boolean(tableId);
        const hasCounter = currentType === 'counter' && Boolean(currentMeta.customer || currentMeta.customer_name || currentMeta.identifier);
        const hasDelivery = currentType === 'delivery' && Boolean(currentMeta.address || currentMeta.address_id);

        if (!state.cartRoutingValid.value && !hasTable && !hasCounter && !hasDelivery) {
            isSubmittingOrder.value = true;
            setTimeout(() => isSubmittingOrder.value = false, 50);

            const detailMsg = currentType === 'table'
                ? 'Por favor, selecione uma mesa para despachar o pedido.'
                : 'Defina o destino do pedido antes de confirmar.';
            toast.add({ severity: 'warn', summary: 'Destino Indefinido', detail: detailMsg, life: 4000 });
            return;
        }

        handleSendOrder({
            meta: currentMeta,
            totals: {
                subtotal: state.cartSubtotal.value,
                deliveryFee: 0,
                serviceFee: 0,
                discount: 0,
                finalTotal: state.cartSubtotal.value
            }
        });
    };

    const customHandleItemDuplicate = (item, doneCallback) => {
        const targetId = item._parent_ref ? item._parent_ref.id : item.id;

        router.post(route('operations.quick-add', state.selectedOrderContext.value.id), {
            item_id: targetId,
            should_print: true,
            source: 'team_app'
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                let updatedOrder = page.props.initialOrders?.find(o => o.id === state.selectedOrderContext.value.id);
                if (updatedOrder) {
                    state.selectedOrderContext.value = updatedOrder;
                    if (typeof doneCallback === 'function') doneCallback(true);
                } else {
                    router.reload({
                        only: ['initialOrders'], preserveState: true, preserveScroll: true,
                        onSuccess: (newPage) => {
                            const freshOrder = newPage.props.initialOrders?.find(o => o.id === state.selectedOrderContext.value.id);
                            if (freshOrder) state.selectedOrderContext.value = freshOrder;
                            if (typeof doneCallback === 'function') doneCallback(true);
                        }
                    });
                }
            },
            onError: () => {
                if (typeof doneCallback === 'function') doneCallback(false);
                toast.add({ severity: 'error', summary: 'Ação Negada', detail: 'Não foi possível duplicar este item.', life: 4000 });
            }
        });
    };

    return {
        isSubmittingOrder, isOrderSuccess, handleCreateOrderOnTable,
        customHandleMenuAction, handleSendOrder, triggerCheckout, customHandleItemDuplicate
    };
}
