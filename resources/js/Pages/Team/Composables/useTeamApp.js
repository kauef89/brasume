import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useCart } from '@/Pages/Shared/Composables/useCart';

const isStarting = ref(false);
const activeTab = ref('menu');
const contextMenuVisible = ref(false);
const selectedOrderContext = ref(null);

export function useTeamApp() {

    const resolveTableData = (order) => {
        if (!order) return { id: null, name: '' };
        const id = order.dining_table_id
            || order.table_id
            || order.dining_table?.id
            || order.diningTable?.id
            || order.table?.id
            || null;

        const name = order.table_number
            || order.table?.name
            || order.table?.number
            || order.dining_table?.name
            || order.dining_table?.table_number
            || order.diningTable?.name
            || order.diningTable?.table_number
            || (id ? String(id) : '');

        return {
            id: id ? Number(id) : null,
            name: String(name || '').trim()
        };
    };

    const startShift = () => {
        isStarting.value = true;
        router.post(route('team.start-shift'), {}, {
            preserveScroll: true,
            onError: (err) => { if (err.shift) alert(err.shift); },
            onFinish: () => isStarting.value = false
        });
    };

    const openContextMenu = (order) => {
        selectedOrderContext.value = order;
        contextMenuVisible.value = true;
    };

    const handleMenuAction = (action, order) => {
        contextMenuVisible.value = false;

        switch (action) {
            case "add_items": {
                const tableInfo = resolveTableData(order);
                const routingPayload = {
                    id: order.id,
                    order_id: order.id,
                    existing_order_id: order.id,
                    type: order.type || 'table',
                    dining_table_id: tableInfo.id,
                    table_id: tableInfo.id,
                    table: tableInfo.id ? { id: tableInfo.id, name: tableInfo.name } : null,
                    identifier: order.customer_name || order.identifier || (tableInfo.name ? `Mesa ${tableInfo.name}` : ''),
                    customer: order.customer ? { id: order.customer.id, name: order.customer.name, phone: order.customer.phone } : null,
                    customer_phone: order.customer_phone || order.customer?.phone || null,
                    address_id: order.address_id || order.address?.id || null,
                    address: order.address_snapshot || order.address || null,
                    source: 'team_app'
                };

                const { draftRouting, orderType } = useCart();
                if (draftRouting) draftRouting.value = routingPayload;
                if (orderType) orderType.value = order.type || 'table';

                try {
                    localStorage.setItem('pos_draft_routing', JSON.stringify(routingPayload));
                    localStorage.setItem('pos_draft_type', order.type || 'table');
                    localStorage.setItem('pos_return_url', '/team');
                    localStorage.setItem('pos_return_order_id', String(order.id));
                } catch (e) {}

                window.dispatchEvent(new CustomEvent('team-tab-changed', { detail: 'menu' }));
                break;
            }
                        case "finish_order":
                router.post(route('operations.orders.update-status', order.id), {
                    status: 'closed'
                }, {
                    preserveScroll: true,
                    preserveState: true
                });
                break;
            case "checkout":
                window.dispatchEvent(new CustomEvent('team-open-checkout', { detail: { orderId: order.id } }));
                break;
            case "print_partial":
                router.post(route('operations.print-conference', order.id), {}, {
                    preserveScroll: true, preserveState: true,
                    onSuccess: () => alert(`Conferência enviada para a impressora!`)
                });
                break;
            case "transfer_all":
                alert('A transferência de mesas deve ser realizada no Caixa principal.');
                break;
        }
    };

    return {
        isStarting, activeTab,
        contextMenuVisible, selectedOrderContext,
        startShift, openContextMenu, handleMenuAction
    };
}
