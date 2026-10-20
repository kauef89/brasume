import { router } from '@inertiajs/vue3';

export function usePaymentRollback({
    liveOrder,
    props,
    page,
    reactiveItems,
    stagedPayments,
    clearDivisor,
    isPostPaymentPhase,
    isReasonModalVisible,
    itemToProcess,
    toast
}) {
    const rollbackPayment = (payment, authData) => {
        if (!payment?.id) {
            toast.add({ severity: 'error', summary: 'Erro', detail: 'Nenhum pagamento selecionado.', life: 3000 });
            return;
        }

        router.delete(route('operations.orders.payments.rollback', { order: liveOrder.value.id, payment: payment.id }), {
            data: {
                pin: authData?.password || authData?.pin || null,
                reason: authData?.reason || 'Estorno solicitado pelo operador'
            },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isReasonModalVisible.value = false;
                itemToProcess.value = null;

                if (props.order?.payments) {
                    props.order.payments = props.order.payments.filter(p => p.id !== payment.id);
                }

                if (props.order?.items) {
                    props.order.items.forEach(i => { i.paid_qty = 0; });
                }

                if (props.order) {
                    props.order.payment_status = 'pending';
                    if (props.order.status === 'closed' || props.order.status === 'completed') {
                        props.order.status = 'pending';
                    }
                }

                const fromList = (page.props.initialOrders || []).find(o => o.id === liveOrder.value?.id);
                if (fromList) {
                    fromList.payments = (fromList.payments || []).filter(p => p.id !== payment.id);
                    if (fromList.items) fromList.items.forEach(i => { i.paid_qty = 0; });
                    fromList.payment_status = 'pending';
                    fromList.status = 'pending';
                }

                reactiveItems.value.forEach(item => {
                    item.base_paid = 0;
                    item.paid_parts = 0;
                    item.available_parts = (item.base_qty ?? item.quantity ?? 1) * (item.parts_per_unit || 1);
                    item.selected_parts = item.available_parts;
                });

                stagedPayments.value = [];
                clearDivisor();

                toast.add({ severity: 'success', summary: 'Estornado', detail: 'Pagamento revertido com sucesso!', life: 3000 });
                isPostPaymentPhase.value = false;

                router.reload({
                    only: ['cashierData', 'initialOrders'],
                    preserveScroll: true,
                    preserveState: true
                });
            },
            onError: (errors) => {
                toast.add({ severity: 'error', summary: 'Falha no Estorno', detail: errors?.pin || errors?.reason || 'Não foi possível estornar.', life: 4000 });
            }
        });
    };

    const cancelItem = (targetItem, authData) => {
        const targetId = targetItem?._parent_ref?.id || targetItem?.id;
        if (!targetId) {
            toast.add({ severity: 'error', summary: 'Erro', detail: 'Nenhum item selecionado.', life: 3000 });
            return;
        }

        router.post(route('operations.orders.cancel-item', liveOrder.value.id), {
            item_id: targetId,
            pin: authData?.password || authData?.pin || null,
            reason: authData?.reason || 'Ajuste no encerramento de conta',
            is_loss: Boolean(authData?.isLoss || authData?.is_loss)
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isReasonModalVisible.value = false;
                itemToProcess.value = null;
                toast.add({ severity: 'success', summary: 'Item Estornado', detail: 'Item removido do pedido.', life: 2500 });
            },
            onError: (errors) => {
                toast.add({ severity: 'error', summary: 'Falha', detail: errors?.pin || errors?.reason || 'Não foi possível estornar o item.', life: 3000 });
            }
        });
    };

    return {
        rollbackPayment,
        cancelItem
    };
}
