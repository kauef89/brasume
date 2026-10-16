import { computed, ref } from 'vue';

export function useCheckoutStaging(intendedPaymentTotal) {
    const stagedPayments = ref([]);

    // 🚀 ESTADO DO DIVISOR (LIGA / DESLIGA)
    const activeDivisor = ref(null);
    const divisionBaseAmount = ref(0);

    const currentStagedTotal = computed(() => {
        return stagedPayments.value.reduce((acc, curr) => acc + curr.amount, 0);
    });

    const pendingToStage = computed(() => {
        const remaining = intendedPaymentTotal.value - currentStagedTotal.value;
        return remaining > 0 ? remaining : 0;
    });

    const changeAmount = computed(() => {
        const diff = currentStagedTotal.value - intendedPaymentTotal.value;
        return diff > 0 ? diff : 0;
    });

    // Calcula o valor teórico de cada fatia com base no saldo quando a divisão foi ligada
    const splitSliceAmount = computed(() => {
        if (!activeDivisor.value || activeDivisor.value <= 1 || divisionBaseAmount.value <= 0) {
            return null;
        }
        return parseFloat((divisionBaseAmount.value / activeDivisor.value).toFixed(2));
    });

    // 🚀 Próximo valor sugerido para o input
    const suggestedAmount = computed(() => {
        const remaining = pendingToStage.value;
        if (remaining <= 0) return 0;

        if (!activeDivisor.value || !splitSliceAmount.value) {
            return parseFloat(remaining.toFixed(2));
        }

        const slice = splitSliceAmount.value;

        // Se a fatia restante for menor ou igual à fatia (proteção de centavos de arredondamento)
        if (remaining <= slice + 0.05) {
            return parseFloat(remaining.toFixed(2));
        }

        return slice;
    });

    // 🚀 TOGGLE LIGA / DESLIGA DA DIVISÃO
    const divideBy = (divisor) => {
        if (activeDivisor.value === divisor) {
            // Se já estava ativo no mesmo número, DESLIGA
            clearDivisor();
        } else {
            // LIGA a divisão
            activeDivisor.value = divisor;
            divisionBaseAmount.value = pendingToStage.value > 0 ? pendingToStage.value : intendedPaymentTotal.value;
        }
    };

    const clearDivisor = () => {
        activeDivisor.value = null;
        divisionBaseAmount.value = 0;
    };

    const addPayment = (method, toast) => {
        const remainingAmount = pendingToStage.value;

        if (remainingAmount <= 0) {
            toast?.add({
                severity: "warn",
                summary: "Conta Paga",
                detail: "A conta já está totalmente paga.",
                life: 3000,
            });
            return;
        }

        if (method.engine_type === "internal_loyalty") {
            const hasLoyalty = stagedPayments.value.some((p) => p.engine_type === "internal_loyalty");
            if (hasLoyalty) {
                toast?.add({
                    severity: "warn",
                    summary: "Resgate Único",
                    detail: "Só é possível usar o saldo de Cashback uma vez por venda.",
                    life: 3000,
                });
                return;
            }
        }

        const resolvedId = method.method_id || method.id;
        const amountToAdd = method.amount
            ? parseFloat(Number(method.amount).toFixed(2))
            : parseFloat((suggestedAmount.value || remainingAmount).toFixed(2));

        stagedPayments.value.push({
            id: Date.now() + Math.random(),
            real_method_id: resolvedId,
            method_name: method.method_name || method.name,
            name: method.method_name || method.name,
            engine_type: method.engine_type,
            amount: amountToAdd,
            change_target: method.change_target || null
        });

        // Se a conta for zerada, desliga a divisão automaticamente
        if (pendingToStage.value <= 0.01) {
            clearDivisor();
        }
    };

    const removePayment = (index) => {
        stagedPayments.value.splice(index, 1);
        if (stagedPayments.value.length === 0 && activeDivisor.value) {
            divisionBaseAmount.value = intendedPaymentTotal.value;
        }
    };

    const setAmountToRemaining = (index) => {
        clearDivisor();
        const payment = stagedPayments.value[index];
        if (!payment) return;

        const otherPaymentsTotal = currentStagedTotal.value - payment.amount;
        let newAmount = intendedPaymentTotal.value - otherPaymentsTotal;
        if (newAmount < 0) newAmount = 0;

        stagedPayments.value[index].amount = parseFloat(newAmount.toFixed(2));
    };

    const autoStagePayments = (order, paymentMethods) => {
        if (!order || stagedPayments.value.length > 0) return;

        let deliveryMethods = order.payment_intent_methods;
        if (typeof deliveryMethods === 'string') {
            try {
                deliveryMethods = JSON.parse(deliveryMethods);
            } catch (e) {
                deliveryMethods = null;
            }
        }

        if (!Array.isArray(deliveryMethods) || deliveryMethods.length === 0) return;

        const shouldAdjustSingle = deliveryMethods.length === 1;

        deliveryMethods.forEach((dm) => {
            const rawName = String(dm.method_name || dm.name || dm.method || dm.method_id || '').trim().toLowerCase();

            const matched = (paymentMethods || []).find(pm => {
                if (dm.method_id && !isNaN(Number(dm.method_id)) && Number(pm.id) === Number(dm.method_id)) return true;
                const pmName = String(pm.name).trim().toLowerCase();
                if (pmName === rawName) return true;
                if ((rawName.includes('débito') || rawName.includes('debito')) && (pmName.includes('débito') || pmName.includes('debito'))) return true;
                if ((rawName.includes('crédito') || rawName.includes('credito')) && (pmName.includes('crédito') || pmName.includes('credito'))) return true;
                if ((rawName.includes('dinheiro') || rawName.includes('cash')) && (pm.engine_type === 'money' || pmName.includes('dinheiro'))) return true;
                if (rawName.includes('pix') && (pm.engine_type === 'pix' || pm.engine_type === 'bank_api' || pmName.includes('pix'))) return true;
                return false;
            });

            const methodId = matched?.id || (isNaN(Number(dm.method_id)) ? paymentMethods?.[0]?.id : Number(dm.method_id));
            const methodName = matched?.name || dm.method_name || dm.name || 'Pagamento';
            const engineType = matched?.engine_type || (dm.is_cash ? 'money' : 'card');

            let val = Number(dm.amount) || 0;
            if (shouldAdjustSingle && intendedPaymentTotal.value > 0) {
                val = intendedPaymentTotal.value;
            } else if (val <= 0) {
                val = intendedPaymentTotal.value;
            }

            stagedPayments.value.push({
                id: Date.now() + Math.random(),
                real_method_id: methodId,
                method_name: methodName,
                name: methodName,
                engine_type: engineType,
                amount: parseFloat(val.toFixed(2)),
                change_target: dm.change_target ? Number(dm.change_target) : null
            });
        });
    };

    return {
        stagedPayments,
        currentStagedTotal,
        pendingToStage,
        suggestedAmount,
        activeDivisor,
        changeAmount,
        addPayment,
        removePayment,
        divideBy,
        clearDivisor,
        setAmountToRemaining,
        autoStagePayments
    };
}
