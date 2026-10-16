import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { computed } from 'vue';

export function useTeamBarKds(props, handleItemCancel) {
    const toast = useToast();
    const page = usePage();

    const barItems = computed(() => {
        let kdsQueue = [];
        const routingMode = page.props.companySettings?.bar_kds_routing_mode || 'shared';

        (props.initialOrders || []).forEach(order => {
            if (['closed', 'cancelled', 'completed', 'delivered'].includes(order.status)) return;

            (order.items || []).forEach(item => {
                let itemStatus = item.production_status || item.status || 'pending';
                if (['cancelled', 'returned'].includes(itemStatus)) return;

                const kitchenType = item.kitchen_type;
                const itemName = (item.name || item.product_name || '').toLowerCase();
                const categoryName = (item.category_name || '').toLowerCase();

                const isDrinkFallback =
                    categoryName.includes('bebida') || categoryName.includes('bar') || categoryName.includes('suco') ||
                    itemName.includes('suco') || itemName.includes('coca') || itemName.includes('água') || itemName.includes('agua') ||
                    itemName.includes('chopp') || itemName.includes('cerveja') || itemName.includes('drink') || itemName.includes('espumante') || itemName.includes('refrigerante');

                const isFromBar = kitchenType === 'bar' || (!kitchenType && isDrinkFallback);

                if (isFromBar) {
                    let mappedItem = { ...item, order: order };

                    // 🚀 Resolução precisa do operador/atendente alinhada com a Etapa 6
                    if (routingMode === 'strict' && itemStatus === 'pending') {
                        mappedItem.production_status = 'preparing';
                        mappedItem.operator_id = order.employee_id
                            || order.attendant?.id
                            || order.waiter?.id
                            || order.user_id
                            || order.created_by;

                        mappedItem.operator_name = order.attendant?.name
                            || order.waiter?.name
                            || order.employee?.name
                            || order.user_name
                            || 'Dono do Pedido';
                    }

                    kdsQueue.push(mappedItem);
                }
            });
        });

        return kdsQueue.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    });

    const handleKdsTake = (item) => {
        router.post(route('operations.kds.update-status', item.id), { production_status: 'preparing' }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => void(0),
        });
    };

    const handleKdsComplete = (item) => {
        router.post(route('operations.kds.update-status', item.id), { production_status: 'delivered' }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => void(0),
        });
    };

    const handleKdsPrint = (item) => {
        router.post(route('operations.kds.print-item', item.id), { qty: 'all' }, { preserveScroll: true, preserveState: true });
    };

    const handleKdsCancel = (item) => {
        if (typeof handleItemCancel === 'function') handleItemCancel(item);
    };

    return { barItems, handleKdsTake, handleKdsComplete, handleKdsPrint, handleKdsCancel };
}
