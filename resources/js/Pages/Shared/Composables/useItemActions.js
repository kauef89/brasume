import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

export function useItemActions(selectedOrderContext, contextMenuVisible, routeName = 'pos.index') {
    const toast = useToast();

    const getOrderId = (item) => {
        return selectedOrderContext?.value?.id || item?.order_id || item?.order?.id;
    };

    const handleItemDuplicate = (item) => {
        if (contextMenuVisible && 'value' in contextMenuVisible) {
            contextMenuVisible.value = false;
        }

        router.post(route('operations.quick-add', getOrderId(item)), {
            item_id: item.id,
            should_print: true
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Item Duplicado', detail: 'Nova comanda enviada para produção (+1).', life: 3000 });
            },
            onError: () => {
                toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível duplicar o item.', life: 4000 });
            }
        });
    };

    const handleItemModify = (payload) => {
        if (contextMenuVisible && 'value' in contextMenuVisible) {
            contextMenuVisible.value = false;
        }

        const rawItem = payload.item || payload;
        const qty = payload.action_qty || rawItem?.action_qty || 'all';
        const originalQty = qty === 'all' ? rawItem.quantity : qty;

        const isSubItem = Boolean(rawItem.parent_item_id || rawItem.combo_parent_id);
        const resolvedParentId = rawItem.parent_item_id || rawItem.combo_parent_id || null;
        const isFullCombo = !isSubItem && Boolean(rawItem.has_exploded_children || rawItem.is_combo_item || rawItem.sub_items?.length);

        const targetUrl = route(routeName, {
            tab: routeName === 'team.app' ? 'menu' : 'pos',
            append_to_order: getOrderId(rawItem),
            edit_item: rawItem.id,
            parent_item_id: resolvedParentId || undefined,
            is_combo_child: isSubItem ? 'true' : undefined,
            is_combo: isFullCombo ? 'true' : undefined,
            edit_qty: qty,
            original_qty: originalQty,
            auth_reason: payload.authData?.reason || '',
            is_loss: payload.authData?.isLoss ? 'true' : 'false'
        });

        router.visit(targetUrl);
    };

    const handleItemSwap = (payload) => {
        if (contextMenuVisible && 'value' in contextMenuVisible) {
            contextMenuVisible.value = false;
        }

        const qty = payload.action_qty || payload.item?.action_qty || 'all';
        const originalQty = qty === 'all' ? payload.item.quantity : qty;

        const targetUrl = route(routeName, {
            tab: routeName === 'team.app' ? 'menu' : 'pos',
            append_to_order: getOrderId(payload.item),
            swap_item: payload.item.id,
            swap_qty: qty,
            original_qty: originalQty,
            auth_reason: payload.authData?.reason || '',
            is_loss: payload.authData?.isLoss ? 'true' : 'false'
        });

        router.visit(targetUrl);
    };

    const handleItemCancel = (payload) => {
        const itemToCancel = payload.item || payload;
        const orderId = getOrderId(itemToCancel);
        const actionQty = payload.action_qty || itemToCancel.action_qty || 'all';

        router.post(route('operations.orders.cancel-item', orderId), {
            item_id: itemToCancel.id,
            qty: actionQty,
            is_loss: payload.isLoss || false,
            reason: payload.reason || 'Cancelado pela Copa/Bar',
            pin: payload.password || payload.pin || null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Item estornado!', life: 3000 }),
            onError: (e) => toast.add({ severity: 'error', summary: 'Acesso Negado', detail: e.pin || e.reason || 'Erro ao estornar.', life: 5000 }),
        });
    };

    return {
        handleItemDuplicate,
        handleItemModify,
        handleItemSwap,
        handleItemCancel
    };
}
