import { usePosSecurity } from '@/Pages/Gateway/Composables/usePosSecurity';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

export function useCashier(props, { onShiftStaged } = {}) {

    const { requiresAuth } = usePosSecurity(props.companySettings || {});
    const toast = useToast();

    const showOrders = ref(true);
    const showTransactions = ref(true);
    const activeOrderTypes = ref(['table', 'counter', 'delivery']);
    const selectedRows = ref(new Set());

    const isLogoutPromptVisible = ref(false);

    const translatePayment = (engineType, fallbackName) => {
        const map = {
            'money': 'Dinheiro',
            'cash': 'Dinheiro',
            'credit_card': 'Crédito',
            'debit_card': 'Débito',
            'pix': 'Pix',
            'pix_sicredi': 'Pix'
        };
        return map[engineType] || fallbackName || 'Outro';
    };

    const getOrderTypeInfo = (type) => {
        const map = {
            'table': { label: 'Mesa', icon: 'pi pi-table' },
            'counter': { label: 'Balcão', icon: 'pi pi-shopping-bag' },
            'delivery': { label: 'Delivery', icon: 'pi pi-truck' }
        };
        return map[type] || { label: 'Avulso', icon: 'pi pi-box' };
    };

    const formatTimeOnly = (dateStr) => {
        if (!dateStr) return '--:--';
        try {
            const safeStr = String(dateStr).trim();
            const d = new Date(safeStr.replace(' ', 'T'));
            if (!isNaN(d.getTime())) {
                return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            }
            const match = safeStr.match(/(\d{2}):(\d{2})/);
            if (match) return `${match[1]}:${match[2]}`;
        } catch (e) {}
        return '--:--';
    };

    const formatDateTime = (dateStr) => {
        if (!dateStr) return '';
        try {
            const safeStr = String(dateStr).trim().replace(' ', 'T');
            const d = new Date(safeStr);
            if (isNaN(d.getTime())) return String(dateStr);
            return d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
        } catch (e) {
            return String(dateStr);
        }
    };

    const unifiedHistory = computed(() => {
        let list = [];
        if (showOrders.value && props.ordersHistory) {
            props.ordersHistory.forEach(o => {
                if (!activeOrderTypes.value.includes(o.type)) return;

                const hasPayments = Array.isArray(o.payments) && o.payments.length > 0;
                const isPaidOrSettled = o.payment_status === 'paid' || o.payment_status === 'partial' || ['closed', 'completed'].includes(o.status);

                if (!hasPayments && !isPaidOrSettled) {
                    return;
                }

                const typeInfo = getOrderTypeInfo(o.type);

                const tableName = o.dining_table?.name || o.diningTable?.name || o.table_number;
                if (o.type === 'table' && tableName) {
                    typeInfo.label = `Mesa ${tableName}`;
                }

                const payments = o.payments?.map(p => {
                    const engine = p.engine_type || p.payment_method?.engine_type;
                    return translatePayment(engine, p.method);
                }).join(', ') || 'Não Pago';

                let operatorName = 'Autoatendimento';
                if (o.cashier?.name) {
                    operatorName = o.cashier.name;
                } else if (o.attendant?.name || o.waiter?.name || o.employee?.name) {
                    operatorName = o.attendant?.name || o.waiter?.name || o.employee?.name;
                } else if (o.cashier_id) {
                    operatorName = 'Caixa';
                } else if (o.employee_id) {
                    operatorName = 'Atendente';
                }

                list.push({
                    ...o,
                    _uniqueId: `order_${o.id}`,
                    _type: 'order',
                    _sortTime: o.updated_at || o.created_at || o.time,
                    _typeLabel: typeInfo.label,
                    _typeIcon: typeInfo.icon,
                    _paymentsLabel: payments,
                    type: o.type,
                    status: o.status,
                    time: formatTimeOnly(o.created_at || o.updated_at || o.time),
                    operator: operatorName,
                    amount: Number(o.total || 0),
                    _mathValue: o.status === 'cancelled' ? 0 : Number(o.total || 0)
                });
            });
        }
        if (showTransactions.value && props.transactionsHistory) {
            props.transactionsHistory.forEach(t => {
                list.push({
                    ...t,
                    _uniqueId: `trans_${t.id}`,
                    _type: 'transaction',
                    _sortTime: t.updated_at || t.created_at || t.time,
                    time: formatTimeOnly(t.created_at || t.updated_at || t.time),
                    operator: t.operator_name || t.user?.name || 'Sistema',
                    amount: Number(t.amount || 0),
                    _mathValue: t.type === 'in' ? Number(t.amount || 0) : -Number(t.amount || 0)
                });
            });
        }
        return list.sort((a, b) => {
            if (a._sortTime > b._sortTime) return -1;
            if (a._sortTime < b._sortTime) return 1;
            return 0;
        });
    });

    const dynamicTotal = computed(() => {
        if (selectedRows.value.size > 0) {
            return unifiedHistory.value
                .filter(item => selectedRows.value.has(item._uniqueId))
                .reduce((sum, item) => sum + item._mathValue, 0);
        }
        return unifiedHistory.value.reduce((sum, item) => sum + item._mathValue, 0);
    });

    const expectedInDrawer = computed(() => {
        let totalCash = 0;
        if (props.currentShift && props.currentShift.opening_balance) {
            totalCash += Number(props.currentShift.opening_balance);
        }

        if (props.transactionsHistory) {
            props.transactionsHistory.forEach(t => {
                if (String(t.description || '').toLowerCase().includes('abertura')) return;
                if (t.type === 'in') totalCash += Number(t.amount);
                if (t.type === 'out') totalCash -= Number(t.amount);
            });
        }

        if (props.ordersHistory) {
            props.ordersHistory.forEach(o => {
                if (o.status !== 'cancelled' && o.payments) {
                    o.payments.forEach(p => {
                        const engine = p.engine_type || p.payment_method?.engine_type;
                        const isCash = engine === 'money' || engine === 'cash' || String(p.method || '').toLowerCase().includes('dinheiro');

                        if (isCash) {
                            totalCash += Number(p.amount);
                        }
                    });

                    if (o.change_returned) {
                        totalCash -= Number(o.change_returned);
                    }
                }
            });
        }
        return totalCash;
    });

    const toggleAllRows = (selectAll) => {
        selectAll ? unifiedHistory.value.forEach(item => selectedRows.value.add(item._uniqueId)) : selectedRows.value.clear();
    };
    const toggleRowSelection = (uniqueId) => {
        selectedRows.value.has(uniqueId) ? selectedRows.value.delete(uniqueId) : selectedRows.value.add(uniqueId);
    };
    const toggleOrderTypeFilter = (type) => {
        const index = activeOrderTypes.value.indexOf(type);
        if (index > -1) activeOrderTypes.value.splice(index, 1);
        else activeOrderTypes.value.push(type);
        selectedRows.value.clear();
    };

    const isShiftModalVisible = ref(false);
    const shiftActionType = ref('open');
    const isTransactionModalVisible = ref(false);
    const transactionType = ref('in');
    const isAuthModalVisible = ref(false);
    const authActionType = ref('cash_out');
    const authItemName = ref('');
    const authorizedPin = ref(null);
    const pendingShiftPayload = ref(null);
    const pendingAttendancePayload = ref(null);

    const openShiftModal = (type) => {
        shiftActionType.value = type;
        isShiftModalVisible.value = true;
    };

    const handleLogout = () => {
        isLogoutPromptVisible.value = false;
        router.post(route('logout'));
    };

    const handleStayInPos = () => {
        isLogoutPromptVisible.value = false;
        router.reload({ only: ['cashierData', 'initialOrders'], preserveScroll: true, preserveState: true });
    };

    const executeShiftAction = (finalPayload) => {
        const isClosing = shiftActionType.value === 'close';
        const endpoint = isClosing ? route('operations.cashier.close') : route('operations.cashier.open');

        router.post(endpoint, finalPayload, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                isShiftModalVisible.value = false;
                pendingShiftPayload.value = null;

                try {
                    localStorage.removeItem('pos_shift_cash_open');
                    localStorage.removeItem('pos_shift_cash_close');
                    localStorage.removeItem('pos_shift_obs_open');
                    localStorage.removeItem('pos_shift_obs_close');
                } catch (e) {}

                if (isClosing) {
                    toast.add({ severity: 'success', summary: 'Turno Fechado', detail: 'O turno foi encerrado com sucesso!', life: 4000 });
                    isLogoutPromptVisible.value = true;
                } else {
                    toast.add({ severity: 'success', summary: 'Caixa Aberto', detail: 'Turno aberto com sucesso!', life: 4000 });
                }
            },
            onError: (e) => {
                const firstError = e && typeof e === 'object' ? Object.values(e)[0] : null;
                const message = firstError || e?.error || e?.message || e?.shift || 'Erro ao processar turno.';

                toast.add({ severity: 'error', summary: 'Falha', detail: message, life: 6000 });
            }
        });
    };

    const processShiftAction = (payload, explicitType = null) => {
        if (explicitType) {
            shiftActionType.value = explicitType;
        }

        if (shiftActionType.value === 'close' && props.companySettings?.cash_management_mode === 'employee') {
            const expected = expectedInDrawer.value;
            const informed = Number(payload.amount ?? payload.closing_balance ?? 0);
            const difference = Math.abs(expected - informed);
            const tolerance = Number(props.companySettings?.cash_difference_tolerance || 5);

            if (difference > tolerance) {
                pendingShiftPayload.value = JSON.parse(JSON.stringify(payload));
                authActionType.value = 'close_shift_override';
                authItemName.value = `Diferença de Caixa: R$ ${difference.toFixed(2)}`;
                isShiftModalVisible.value = false;
                isAuthModalVisible.value = true;
                return;
            }
        }

        pendingShiftPayload.value = JSON.parse(JSON.stringify(payload));
        isShiftModalVisible.value = false;

        if (onShiftStaged) onShiftStaged(shiftActionType.value, pendingShiftPayload.value);
    };

    const finalizeShiftWithAttendance = (attendeesArray) => {
        if (!pendingShiftPayload.value) return;

        const finalPayload = {
            ...pendingShiftPayload.value,
            attendees: Array.isArray(attendeesArray) ? attendeesArray : []
        };

        executeShiftAction(finalPayload);
    };

    const openTransactionModal = (type) => {
        if (type === 'out' && requiresAuth('cash_out')) {
            authActionType.value = 'cash_out';
            authItemName.value = 'Sangria de Caixa';
            isAuthModalVisible.value = true;
            return;
        }
        transactionType.value = type;
        isTransactionModalVisible.value = true;
    };

    const processTransaction = (payload) => {
        const data = { ...payload, pin: authorizedPin.value };
        router.post(route('operations.cashier.transaction'), data, {
            preserveState: true, preserveScroll: true,
            onSuccess: () => { isTransactionModalVisible.value = false; authorizedPin.value = null; },
            onError: (e) => { toast.add({ severity: 'error', summary: 'Erro', detail: e.pin || 'Erro ao processar transação.', life: 4000 }); authorizedPin.value = null; }
        });
    };

    const triggerAttendanceSync = (payload, onSuccessCallback) => {
        const userRoles = props.auth?.user?.roles || [];
        const isManagerOrAdmin = userRoles.some(r => {
            const roleName = typeof r === 'string' ? r : r.name;
            return ['admin', 'gerente', 'manager'].includes(roleName);
        });

        if (!isManagerOrAdmin) {
            pendingAttendancePayload.value = { payload, onSuccessCallback };
            authActionType.value = 'shift_attendance';
            authItemName.value = 'Aprovação de Ponto e Escala';
            isAuthModalVisible.value = true;
            return;
        }
        executeAttendanceSync(payload, null, onSuccessCallback);
    };

    const executeAttendanceSync = (payload, pin = null, onSuccessCallback = null) => {
        router.post(route('operations.attendance.sync'), { preserveState: true,  attendees: Array.isArray(payload) ? payload : [], pin: pin }, {
            preserveState: true, preserveScroll: true,
            onSuccess: () => { pendingAttendancePayload.value = null; if (onSuccessCallback) onSuccessCallback(); },
            onError: (errors) => { toast.add({ severity: 'error', summary: 'Falha', detail: errors.pin || 'Erro ao sincronizar ponto.', life: 4000 }); }
        });
    };

    const handleOrderAction = (payload) => {
        if (payload.type === 'manage') {
            window.dispatchEvent(new CustomEvent('pos-open-checkout', { detail: { orderId: payload.order.id } }));
        }
    };

    const handleAuthConfirm = (authData) => {
        isAuthModalVisible.value = false;
        if (authActionType.value === 'cash_out') {
            authorizedPin.value = authData.password;
            transactionType.value = 'out';
            isTransactionModalVisible.value = true;
        } else if (authActionType.value === 'close_shift_override') {
            pendingShiftPayload.value.pin = authData.password;
            if (onShiftStaged) onShiftStaged('close', pendingShiftPayload.value);
        } else if (authActionType.value === 'shift_attendance') {
            if (pendingAttendancePayload.value) {
                executeAttendanceSync(pendingAttendancePayload.value.payload, authData.password, pendingAttendancePayload.value.onSuccessCallback);
            }
        }
    };

    return {
            showOrders, showTransactions, activeOrderTypes, selectedRows, unifiedHistory,
            dynamicTotal, expectedInDrawer, formatDateTime, isShiftModalVisible,
            shiftActionType, isTransactionModalVisible, transactionType, isAuthModalVisible,
            authActionType, authItemName, pendingShiftPayload,
            isLogoutPromptVisible, handleLogout, handleStayInPos,
            toggleAllRows, toggleRowSelection, toggleOrderTypeFilter, openShiftModal, processShiftAction,
            openTransactionModal, processTransaction, handleOrderAction, handleAuthConfirm,
            finalizeShiftWithAttendance, triggerAttendanceSync, executeShiftAction
        };
}
