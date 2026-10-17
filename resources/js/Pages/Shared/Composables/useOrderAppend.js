import { computed } from 'vue';

export function useOrderAppend(draftRouting, cartSubtotal, initialOrdersRef = null) {
    const targetOrderId = computed(() => {
        return draftRouting.value?.order_id
            || draftRouting.value?.id
            || draftRouting.value?.existing_order_id
            || draftRouting.value?.append_to_order
            || null;
    });

    const isAppending = computed(() => Boolean(targetOrderId.value));

    // Busca o objeto completo da encomenda original na lista de pedidos ativos
    const originalOrder = computed(() => {
        if (!isAppending.value || !initialOrdersRef) return null;
        const orders = initialOrdersRef.value || [];
        return orders.find(o => String(o.id) === String(targetOrderId.value)) || null;
    });

    const alreadyPaidAmount = computed(() => {
        if (!originalOrder.value) return 0.00;
        if (originalOrder.value.total_paid !== undefined) {
            return Number(originalOrder.value.total_paid);
        }
        const payments = originalOrder.value.payments || [];
        return payments.filter(p => p.is_paid || p.status === 'paid').reduce((sum, p) => sum + Number(p.amount || 0), 0);
    });

    const originalTotal = computed(() => {
        return originalOrder.value ? Number(originalOrder.value.total || 0) : 0.00;
    });

    const newItemsAmount = computed(() => {
        return Number(cartSubtotal.value || 0);
    });

    const projectedTotal = computed(() => {
        return Number((originalTotal.value + newItemsAmount.value).toFixed(2));
    });

    const balanceDue = computed(() => {
        const diff = projectedTotal.value - alreadyPaidAmount.value;
        return diff > 0 ? Number(diff.toFixed(2)) : 0.00;
    });

    const wasOriginallyPaid = computed(() => {
        return originalOrder.value?.payment_status === 'paid'
            || (alreadyPaidAmount.value > 0 && alreadyPaidAmount.value >= (originalTotal.value - 0.05));
    });

    // 🚀 LÊ E EXPORTA AS PROMESSAS PRÉVIAS
    const preExistingPaymentIntents = computed(() => {
        if (!originalOrder.value) return [];
        let methods = originalOrder.value.payment_intent_methods;
        if (!methods) return [];

        while (typeof methods === 'string') {
            try { methods = JSON.parse(methods); } catch (e) { break; }
        }
        return Array.isArray(methods) ? methods : [];
    });

    // 🚀 INFLA O ÚLTIMO PAGAMENTO PARA COBRIR EXATAMENTE O SALDO A PAGAR
        const autoScaledPaymentIntents = computed(() => {
            const baseMethods = [...preExistingPaymentIntents.value];
            if (baseMethods.length === 0) return [];

            // Soma os métodos anteriores caso haja mais de um (ex: Dinheiro + Cartão)
            const otherMethodsSum = baseMethods.slice(0, -1).reduce((sum, m) => sum + Number(m.amount || 0), 0);
            const lastMethod = { ...baseMethods[baseMethods.length - 1] };

            // Cravamos que o último método absorva exatamente a diferença para bater com o balanceDue
            lastMethod.amount = Math.max(0, Number((balanceDue.value - otherMethodsSum).toFixed(2)));
            lastMethod.change_target = null;

            baseMethods[baseMethods.length - 1] = lastMethod;
            return baseMethods;
        });

    const changeAnalysis = computed(() => {
        const target = draftRouting.value?.cash_change_target
            || draftRouting.value?.change_target
            || originalOrder.value?.change_target
            || null;

        if (!target) {
            return { hasChange: false, targetAmount: 0, calculatedChange: 0, isExceeded: false, warning: null };
        }

        const targetFloat = Number(target);
        const change = Number((targetFloat - projectedTotal.value).toFixed(2));
        const isExceeded = projectedTotal.value > targetFloat;

        return {
            hasChange: true,
            targetAmount: targetFloat,
            calculatedChange: Math.max(0, change),
            isExceeded,
            warning: isExceeded
                ? `O novo total (R$ ${projectedTotal.value.toFixed(2)}) excede o valor para troco informado (R$ ${targetFloat.toFixed(2)})!`
                : `Troco recalculado: Levar R$ ${change.toFixed(2)} de volta.`
        };
    });

    return {
        targetOrderId,
        isAppending,
        originalOrder,
        originalTotal,
        alreadyPaidAmount,
        newItemsAmount,
        projectedTotal,
        balanceDue,
        wasOriginallyPaid,
        preExistingPaymentIntents,
        autoScaledPaymentIntents,
        changeAnalysis
    };
}
