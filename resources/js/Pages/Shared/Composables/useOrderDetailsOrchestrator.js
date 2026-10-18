import { useViewport } from "@/Composables/useViewport";
import { router } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { ref } from "vue";
import { useCart } from "@/Pages/Shared/Composables/useCart";

export function useOrderDetailsOrchestrator(props, activeItemsGrouped) {
    const toast = useToast();
    const { isMobile } = useViewport();

    const activeSidebarView = ref('menu');
    const selectedItemIds = ref([]);

    const isTransferModalVisible = ref(false);
    const isTransformerVisible = ref(false);
    const isPaymentModalVisible = ref(false);
    const isChangeAddressModalVisible = ref(false);
    const isCourierSwapModalVisible = ref(false);

    const isCheckoutModalVisible = ref(false);
    const checkoutData = ref(null);
    const isFetchingCheckout = ref(false);

    const transferItemIds = ref([]);

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

    const toggleItemSelection = (item) => {
        const id = typeof item === 'object' ? item.id : item;
        const targetItem = typeof item === 'object' ? item : activeItemsGrouped.value.find(i => i.id === id);

        const isSelected = selectedItemIds.value.includes(id);
        let idsToToggle = [id];

        if (targetItem && !targetItem.is_combo_child && targetItem.has_exploded_children) {
            const children = activeItemsGrouped.value.filter(i => i.combo_parent_id === id);
            idsToToggle.push(...children.map(c => c.id));
        }

        if (isSelected) {
            selectedItemIds.value = selectedItemIds.value.filter(selectedId => !idsToToggle.includes(selectedId));
        } else {
            const newIds = idsToToggle.filter(newId => !selectedItemIds.value.includes(newId));
            selectedItemIds.value.push(...newIds);
        }
    };

    const toggleSelectAllItems = () => {
        if (selectedItemIds.value.length === activeItemsGrouped.value.length) {
            selectedItemIds.value = [];
        } else {
            selectedItemIds.value = activeItemsGrouped.value.map(i => i.id);
        }
    };

    const navigateToPosTab = () => {
        window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'pos' }));
    };

    const handleAction = async (actionId) => {
        if (actionId === 'transfer_all') {
            transferItemIds.value = activeItemsGrouped.value.map(i => i.id);
            if (isMobile.value) isTransferModalVisible.value = true;
            else activeSidebarView.value = 'transfer';
            return;
        }

        switch (actionId) {
            case "checkout":
                window.dispatchEvent(new CustomEvent('pos-open-checkout', { detail: { orderId: props.order.id } }));
                break;

            case "add_items": {
                const tableInfo = resolveTableData(props.order);
                const routingPayload = {
                    id: props.order.id,
                    order_id: props.order.id,
                    existing_order_id: props.order.id,
                    type: props.order.type || "table",
                    dining_table_id: tableInfo.id,
                    table_id: tableInfo.id,
                    table: tableInfo.id ? { id: tableInfo.id, name: tableInfo.name } : null,
                    identifier: props.order.customer_name || props.order.identifier || (tableInfo.name ? `Mesa ${tableInfo.name}` : ''),
                    customer: props.order.customer ? { id: props.order.customer.id, name: props.order.customer.name, phone: props.order.customer.phone } : null,
                    customer_phone: props.order.customer_phone || props.order.customer?.phone || null,
                    address_id: props.order.address_id || props.order.address?.id || null,
                    address: props.order.address_snapshot || props.order.address || null,
                };

                const { draftRouting, orderType } = useCart();
                if (draftRouting) draftRouting.value = routingPayload;
                if (orderType) orderType.value = props.order.type || "table";

                localStorage.setItem("pos_draft_type", props.order.type || "table");
                localStorage.setItem("pos_draft_routing", JSON.stringify(routingPayload));
                localStorage.setItem("pos_return_url", "/operations");
                localStorage.setItem("pos_return_order_id", String(props.order.id));
                navigateToPosTab();
                break;
            }

            case "transform_order":
                if (isMobile.value) isTransformerVisible.value = true;
                else activeSidebarView.value = 'transform';
                break;

            case "delivery_payment":
                if (isMobile.value) isPaymentModalVisible.value = true;
                else activeSidebarView.value = 'payment';
                break;

            case "change_address":
                if (isMobile.value) isChangeAddressModalVisible.value = true;
                else activeSidebarView.value = 'address';
                break;

            case "swap_courier":
                if (isMobile.value) isCourierSwapModalVisible.value = true;
                else activeSidebarView.value = 'courier_swap';
                break;

            case "new_subaccount": {
                const tableInfo = resolveTableData(props.order);

                localStorage.setItem("pos_draft_type", "table");
                localStorage.setItem("pos_draft_routing", JSON.stringify({
                    type: 'table',
                    orderType: 'table',
                    dining_table_id: tableInfo.id,
                    table_id: tableInfo.id,
                    selectedTable: { id: tableInfo.id, name: tableInfo.name },
                    table: { id: tableInfo.id, name: tableInfo.name },
                    tableIdentifier: ''
                }));
                navigateToPosTab();
                break;
            }

            case "print_partial":
                router.post(route('operations.print-conference', props.order.id), {}, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => void(0)
                });
                break;
        }
    };

    const openSingleTransfer = (item) => {
        transferItemIds.value = [item.id];
        if (isMobile.value) isTransferModalVisible.value = true;
        else activeSidebarView.value = 'transfer';
    };

    const handleExecuteTransfer = (payload) => {
            router.post(route('operations.transfer-items'), payload, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    isTransferModalVisible.value = false;
                    activeSidebarView.value = 'menu';
                    selectedItemIds.value = [];

                    // Agora o frontend reconhece se a comanda foi transferida
                    if (page.props.order && ['cancelled', 'merged', 'transferred'].includes(page.props.order.status)) {
                        const returnRoute = route().current('team.*') ? route('team.index') : route('operations.index');

                        toast.add({ severity: 'success', summary: 'Mesa Transferida', detail: 'A comanda original foi encerrada.', life: 3000 });
                        router.visit(returnRoute);
                    } else {
                        toast.add({ severity: 'success', summary: 'Transferência Concluída', detail: 'Os itens selecionados foram movidos.', life: 3000 });
                    }
                },
                onError: (errors) => {
                    const message = Object.values(errors)[0] || 'Falha ao transferir os itens.';
                    toast.add({ severity: 'error', summary: 'Transferência Recusada', detail: message, life: 5000 });
                }
            });
        };

    const handleTransformation = (payload) => {
        router.post(route('operations.orders.transform', props.order.id), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isTransformerVisible.value = false;

                const targetType = payload.new_type || payload.newType || payload.type;
                if (targetType === 'delivery' && !props.order.address && !props.order.address_snapshot) {
                    if (isMobile.value) {
                        isChangeAddressModalVisible.value = true;
                    } else {
                        activeSidebarView.value = 'address';
                    }
                } else {
                    activeSidebarView.value = 'menu';
                }
            },
            onError: (errors) => {
                const message = Object.values(errors)[0] || 'Falha ao converter pedido. Verifique os dados.';
                toast.add({ severity: 'error', summary: 'Erro', detail: message, life: 5000 });
            }
        });
    };

    const handleUpdatePayment = (intentData) => {
        router.post(route('operations.orders.update-delivery-payment', props.order.id), intentData, {
            onSuccess: () => {
                isPaymentModalVisible.value = false;
                activeSidebarView.value = 'menu';

                if (intentData.intent === 'pay_now' || intentData.payment_intent_status === 'pay_now') {
                    handleAction('checkout');
                }
            }
        });
    };

    const handleChangeAddressConfirm = (payload) => {
        router.post(route('operations.orders.change-address', payload.order_id), {
            address_id: payload.new_address_id,
            address: payload.new_address,
            delivery_fee: payload.new_delivery_fee
        }, {
            preserveState: true,
            onSuccess: () => {
                isChangeAddressModalVisible.value = false;
                activeSidebarView.value = 'menu';
            }
        });
    };

    const handleDeactivateAddress = (addressId) => {
        router.post(route('operations.customers.addresses.deactivate', [props.order.customer_id, addressId]), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Removido', detail: 'Endereço inativado.', life: 3000 });
                isChangeAddressModalVisible.value = false;
                activeSidebarView.value = 'menu';
            }
        });
    };

    const handleAssignCourier = (courier, onSuccessCallback = null) => {
        router.post(route('operations.orders.assign-courier', props.order.id), {
            driver_id: courier.id
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isCourierSwapModalVisible.value = false;
                activeSidebarView.value = 'menu';

                if (onSuccessCallback) onSuccessCallback(courier);
            }
        });
    };

    // 🚀 EXECUÇÃO DAS AÇÕES EM LOTE
    const executeBulkAction = (actionType) => {
        if (actionType === 'transfer') {
            transferItemIds.value = [...selectedItemIds.value];
            if (isMobile.value) isTransferModalVisible.value = true;
            else activeSidebarView.value = 'transfer';
        }
        else if (actionType === 'print') {
            const ids = [...selectedItemIds.value];
            if (ids.length === 0) {
                toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Nenhum item selecionado.', life: 3000 });
                return;
            }

            router.post(route('operations.kds.bulk-print'), {
                item_ids: ids
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Impressão em Lote',
                        detail: `${ids.length} item(ns) enviado(s) para a cozinha!`,
                        life: 3000
                    });
                    selectedItemIds.value = [];
                },
                onError: (err) => {
                    const msg = err && typeof err === 'object' ? Object.values(err)[0] : 'Falha ao imprimir itens em lote.';
                    toast.add({ severity: 'error', summary: 'Erro de Impressão', detail: msg, life: 5000 });
                }
            });
        }
    };

    return {
        activeSidebarView, selectedItemIds, isTransferModalVisible,
        isTransformerVisible, isPaymentModalVisible, isChangeAddressModalVisible,
        isCourierSwapModalVisible, isCheckoutModalVisible, checkoutData, isFetchingCheckout,
        transferItemIds, toggleItemSelection, toggleSelectAllItems,
        handleAction, openSingleTransfer, handleExecuteTransfer, handleTransformation,
        handleUpdatePayment, handleChangeAddressConfirm, handleDeactivateAddress,
        handleAssignCourier, executeBulkAction
    };
}
