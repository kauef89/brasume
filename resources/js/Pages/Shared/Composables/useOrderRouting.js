import { computed, ref, watch } from 'vue';

export function useOrderRouting(props, emit) {
    const initialMeta = props.routingMeta || null;

    const localOrderType = ref(props.modelValue || initialMeta?.type || 'table');
    const resetTableTrigger = ref(0);
    const selectedTable = ref(initialMeta?.table || null);
    const tableIdentifier = ref(initialMeta?.identifier || '');
    const sharedCustomer = ref(initialMeta?.customer || null);
    const deliveryAddress = ref(initialMeta?.address || null);

    // 🚀 Sincroniza se o tipo de pedido for alterado externamente pelo pai
    watch(() => props.modelValue, (newType) => {
        if (newType && newType !== localOrderType.value) {
            localOrderType.value = newType;
            if (newType !== 'delivery') {
                deliveryAddress.value = null;
            }
        }
    });

    const setSelectedTable = (val) => selectedTable.value = val;
    const setTableIdentifier = (val) => tableIdentifier.value = val;
    const setSharedCustomer = (val) => sharedCustomer.value = val;
    const setDeliveryAddress = (val) => deliveryAddress.value = val;

    // 🚀 O endereço só é hidratado automaticamente se o tipo ATIVO for Delivery
    watch(sharedCustomer, (newCustomer) => {
        if (localOrderType.value === 'delivery' && newCustomer && !deliveryAddress.value) {
            if (newCustomer.address) {
                deliveryAddress.value = newCustomer.address;
            } else if (Array.isArray(newCustomer.addresses) && newCustomer.addresses.length > 0) {
                deliveryAddress.value = newCustomer.addresses.find(a => a.is_default) || newCustomer.addresses[0];
            }
        } else if (localOrderType.value !== 'delivery') {
            deliveryAddress.value = null;
        }
    }, { deep: true });

    const orderTypeOptions = computed(() => [
        { label: "Mesa", value: "table", icon: "pi pi-th-large" },
        { label: "Delivery", value: "delivery", icon: "pi pi-truck" },
        { label: "Balcão", value: "counter", icon: "pi pi-shopping-bag" }
    ]);

    const selectOrderType = (type) => {
        if (localOrderType.value !== type) {
            localOrderType.value = type;
            if (type !== 'delivery') {
                deliveryAddress.value = null;
            }
            emit('update:modelValue', type);
        }
    };

    const isLocalValid = computed(() => {
        if (!localOrderType.value) return false;

        if (localOrderType.value === 'delivery') {
            const hasAddress = !!(
                deliveryAddress.value ||
                sharedCustomer.value?.address ||
                (Array.isArray(sharedCustomer.value?.addresses) && sharedCustomer.value.addresses.length > 0)
            );

            const hasCustomer = !!(
                sharedCustomer.value?.id ||
                (typeof sharedCustomer.value === 'string' && sharedCustomer.value.trim().length > 0) ||
                (typeof sharedCustomer.value?.name === 'string' && sharedCustomer.value.name.trim().length > 0) ||
                (typeof tableIdentifier.value === 'string' && tableIdentifier.value.trim().length > 0)
            );

            return hasAddress && hasCustomer;
        }

        if (localOrderType.value === 'counter') {
            const hasCustomer = !!(
                sharedCustomer.value?.id ||
                (typeof sharedCustomer.value === 'string' && sharedCustomer.value.trim().length > 0) ||
                (typeof sharedCustomer.value?.name === 'string' && sharedCustomer.value.name.trim().length > 0) ||
                (typeof tableIdentifier.value === 'string' && tableIdentifier.value.trim().length > 0)
            );
            return hasCustomer;
        }

        if (localOrderType.value === 'table') {
            return !!(selectedTable.value && (selectedTable.value.id || selectedTable.value.dining_table_id));
        }

        return false;
    });

    watch([localOrderType, selectedTable, tableIdentifier, sharedCustomer, deliveryAddress], () => {
        let resolvedIdentifier = tableIdentifier.value || '';

        if (localOrderType.value === 'counter' && sharedCustomer.value) {
            resolvedIdentifier = typeof sharedCustomer.value === 'string'
                ? sharedCustomer.value
                : (sharedCustomer.value.name || tableIdentifier.value || '');
        } else if (localOrderType.value === 'delivery' && sharedCustomer.value) {
            resolvedIdentifier = typeof sharedCustomer.value === 'string'
                ? sharedCustomer.value
                : (sharedCustomer.value.name || '');
        }

        const resolvedPhone = typeof sharedCustomer.value === 'object'
            ? (sharedCustomer.value?.phone || null)
            : null;

        // 🛑 TRAVA DE SEGURANÇA: Endereço é NULO em qualquer tipo que não seja Delivery
        const isDelivery = localOrderType.value === 'delivery';

        const finalAddress = isDelivery
            ? (deliveryAddress.value ||
                sharedCustomer.value?.address ||
                (Array.isArray(sharedCustomer.value?.addresses) ? (sharedCustomer.value.addresses.find(a => a.is_default) || sharedCustomer.value.addresses[0]) : null))
            : null;

        const resolvedTableId = localOrderType.value === 'table'
            ? (selectedTable.value?.id || selectedTable.value?.dining_table_id || null)
            : null;

        emit('routing-change', {
            isValid: isLocalValid.value,
            meta: {
                type: localOrderType.value,
                dining_table_id: resolvedTableId,
                table_id: resolvedTableId,
                table: localOrderType.value === 'table' ? selectedTable.value : null,
                identifier: resolvedIdentifier,
                customer: sharedCustomer.value,
                customer_phone: resolvedPhone,
                address_id: finalAddress?.id || null,
                address: finalAddress
            }
        });
    }, { deep: true, immediate: true });

    const handleApply = (override = null) => {
        const finalCustomer = override?.customer || sharedCustomer.value;
        const isDelivery = localOrderType.value === 'delivery';

        let finalAddress = null;
        if (isDelivery) {
            finalAddress = override?.address || deliveryAddress.value ||
                (Array.isArray(finalCustomer?.addresses) ? (finalCustomer.addresses.find(a => a.is_default) || finalCustomer.addresses[0]) : null);
        }

        if (override?.customer) sharedCustomer.value = override.customer;
        deliveryAddress.value = finalAddress;

        let resolvedIdentifier = tableIdentifier.value || '';
        if (localOrderType.value === 'counter' && finalCustomer) {
            resolvedIdentifier = typeof finalCustomer === 'string'
                ? finalCustomer
                : (finalCustomer.name || tableIdentifier.value || '');
        }

        const resolvedTableId = localOrderType.value === 'table'
            ? (selectedTable.value?.id || selectedTable.value?.dining_table_id || null)
            : null;

        emit('apply', {
            type: localOrderType.value,
            dining_table_id: resolvedTableId,
            table_id: resolvedTableId,
            table: localOrderType.value === 'table' ? selectedTable.value : null,
            identifier: resolvedIdentifier,
            customer: finalCustomer,
            customer_phone: typeof finalCustomer === 'object' ? (finalCustomer?.phone || null) : null,
            address_id: finalAddress?.id || null,
            address: finalAddress
        });
    };

    const triggerImmediateApply = () => {
        handleApply();
    };

    const updateParentState = () => {};

    return {
        localOrderType,
        resetTableTrigger,
        orderTypeOptions,
        selectedTable,
        tableIdentifier,
        sharedCustomer,
        deliveryAddress,
        isLocalValid,
        selectOrderType,
        handleApply,
        triggerImmediateApply,
        updateParentState,
        setSelectedTable,
        setTableIdentifier,
        setSharedCustomer,
        setDeliveryAddress
    };
}
