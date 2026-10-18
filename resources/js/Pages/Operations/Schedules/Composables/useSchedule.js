import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

export function useSchedule(props) {
    const toast = useToast();

    const activeSideTab = ref('team');
    const sideTabOptions = [
        { label: 'Equipe', value: 'team', icon: 'pi-users' },
        { label: 'Slots', value: 'slots', icon: 'pi-th-large' }
    ];

    const employeePool = ref([]);
    const localSlots = ref([]);
    const availableRoles = ref([]);
    const weeklySchedules = ref({});
    const hasUnsavedChanges = ref(false);
    const isSaving = ref(false);

    const draggingEmployeeId = ref(null);
    const draggingEmployeeRoles = ref([]);

    const activeStartDate = computed(() => {
        return props.startDate || new Date().toISOString().split('T')[0];
    });

    const weekDates = computed(() => {
        const dates = [];
        const start = new Date(activeStartDate.value + 'T00:00:00');
        for (let i = 0; i < 7; i++) {
            const d = new Date(start);
            d.setDate(start.getDate() + i);
            dates.push(d);
        }
        return dates;
    });

    const formatShortDate = (d) => d ? d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : '';
    const formatWeekday = (d) => d ? d.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '') : '';

    const formatSlotCapacityText = (capacity) => {
        const num = Number(capacity) || 0;
        return `${num} ${num === 1 ? 'vaga planejada' : 'vagas planejadas'}`;
    };

    const triggerChange = () => {
        hasUnsavedChanges.value = true;
    };

    const populateData = () => {
        employeePool.value = props.employees || [];
        localSlots.value = props.slots && props.slots.length > 0 ? [...props.slots] : [];
        availableRoles.value = props.availableRoles || [
            { value: 'kitchen', label: 'Cozinha' },
            { value: 'waiter', label: 'Atendente / Garçom' },
            { value: 'cashier', label: 'Caixa' },
            { value: 'manager', label: 'Gerente' },
            { value: 'driver', label: 'Entregador / Motoboy' },
            { value: 'cleaning', label: 'Limpeza' }
        ];

        const map = {};
        weekDates.value.forEach(d => {
            const dateStr = d.toISOString().split('T')[0];
            const daySlots = {};
            localSlots.value.forEach(slot => {
                daySlots[slot.id] = [];
            });
            map[dateStr] = {
                slots: daySlots,
                dayOff: []
            };
        });

        if (props.initialSchedules) {
            props.initialSchedules.forEach(sch => {
                const dStr = typeof sch.date === 'string' ? sch.date.split('T')[0] : '';
                if (!map[dStr]) return;

                const employeeRoles = sch.employee?.active_roles || sch.employee?.user?.roles?.map(r => r.name) || [];

                const item = {
                    unique_id: Math.random().toString(36).substring(2, 9),
                    employee_id: sch.employee_id,
                    name: sch.employee?.name || 'Colaborador',
                    employment_type: sch.employee?.employment_type || 'clt',
                    employment_type_label: sch.employee?.employment_type_label || 'Geral',
                    active_roles: employeeRoles,
                    schedule_slot_id: sch.schedule_slot_id,
                    assigned_role: sch.assigned_role,
                    is_day_off: Boolean(sch.is_day_off),
                    is_extra_shift: Boolean(sch.is_extra_shift)
                };

                if (sch.is_day_off) {
                    map[dStr].dayOff.push(item);
                } else if (sch.schedule_slot_id && map[dStr].slots[sch.schedule_slot_id]) {
                    map[dStr].slots[sch.schedule_slot_id].push(item);
                }
            });
        }

        weeklySchedules.value = map;
        hasUnsavedChanges.value = false;
    };

    onMounted(populateData);
    watch(() => props.initialSchedules, populateData, { deep: true });
    watch(() => props.startDate, populateData);

    const cloneEmployee = (employee) => {
        return {
            unique_id: Math.random().toString(36).substring(2, 9),
            employee_id: employee.id,
            name: employee.name,
            employment_type: employee.employment_type,
            employment_type_label: employee.employment_type_label || 'Geral',
            active_roles: employee.active_roles || [],
            schedule_slot_id: null,
            assigned_role: 'Operação',
            is_day_off: false,
            is_extra_shift: false
        };
    };

    const onDragStartPool = (evt) => {
        const item = employeePool.value[evt.oldIndex];
        if (item) {
            draggingEmployeeId.value = item.id;
            draggingEmployeeRoles.value = item.active_roles || [];
        }
    };

    const onDragStartSlot = (evt, dateObj, slotId) => {
        const list = getSlotList(dateObj, slotId);
        const item = list[evt.oldIndex];
        if (item) {
            draggingEmployeeId.value = item.employee_id;
            draggingEmployeeRoles.value = item.active_roles || [];
        }
    };

    const onDragStartDayOff = (evt, dateObj) => {
        const list = getDayOffList(dateObj);
        const item = list[evt.oldIndex];
        if (item) {
            draggingEmployeeId.value = item.employee_id;
            draggingEmployeeRoles.value = item.active_roles || [];
        }
    };

    const onDragEnd = () => {
        draggingEmployeeId.value = null;
        draggingEmployeeRoles.value = [];
    };

    const isSlotIncompatible = (slotId) => {
        if (!draggingEmployeeId.value) return false;

        const slot = localSlots.value.find(s => s.id === slotId);
        if (!slot || !slot.allowed_roles || slot.allowed_roles.length === 0) return false;

        const empRoles = draggingEmployeeRoles.value;
        if (!empRoles || empRoles.length === 0) return true;

        const hasMatch = empRoles.some(r => slot.allowed_roles.includes(r));
        return !hasMatch;
    };

    const getSlotList = (dateObj, slotId) => {
        const key = dateObj.toISOString().split('T')[0];
        if (!weeklySchedules.value[key]) return [];
        if (!weeklySchedules.value[key].slots[slotId]) {
            weeklySchedules.value[key].slots[slotId] = [];
        }
        return weeklySchedules.value[key].slots[slotId];
    };

    const getDayOffList = (dateObj) => {
        const key = dateObj.toISOString().split('T')[0];
        if (!weeklySchedules.value[key]) return [];
        return weeklySchedules.value[key].dayOff;
    };

    const onSlotChange = (evt, dateObj, slotId) => {
        if (evt.added) {
            const item = evt.added.element;
            item.schedule_slot_id = slotId;
            item.is_day_off = false;
            const slotObj = localSlots.value.find(s => s.id === slotId);
            item.assigned_role = slotObj?.name || 'Operação';
        }
        triggerChange();
    };

    const onDayOffChange = (evt, dateObj) => {
        if (evt.added) {
            const item = evt.added.element;
            item.schedule_slot_id = null;
            item.is_day_off = true;
            item.assigned_role = 'Folga';
            item.is_extra_shift = false;
        }
        triggerChange();
    };

    const removeScheduleItem = (dateObj, uniqueId) => {
        const key = dateObj.toISOString().split('T')[0];
        const dayData = weeklySchedules.value[key];
        if (!dayData) return;

        Object.keys(dayData.slots).forEach(slotId => {
            dayData.slots[slotId] = dayData.slots[slotId].filter(s => s.unique_id !== uniqueId);
        });
        dayData.dayOff = dayData.dayOff.filter(s => s.unique_id !== uniqueId);
        triggerChange();
    };

    const getEmployeeWeeklyWorkDays = (employeeId) => {
        let count = 0;
        Object.values(weeklySchedules.value).forEach(dayData => {
            const workedInAnySlot = Object.values(dayData.slots || {}).some(list =>
                list.some(s => s.employee_id === employeeId)
            );
            if (workedInAnySlot) count++;
        });
        return count;
    };

    const getWorkingCount = (dateObj) => {
        const key = dateObj.toISOString().split('T')[0];
        const dayData = weeklySchedules.value[key];
        if (!dayData) return 0;
        return Object.values(dayData.slots).reduce((total, list) => total + list.length, 0);
    };

    const getDayOffCount = (dateObj) => {
        const key = dateObj.toISOString().split('T')[0];
        return weeklySchedules.value[key]?.dayOff?.length || 0;
    };

    const getSlotAssignedCount = (dateObj, slotId) => {
        const key = dateObj.toISOString().split('T')[0];
        return weeklySchedules.value[key]?.slots[slotId]?.length || 0;
    };

    const getSlotCapacityClass = (dateObj, slot) => {
        const count = getSlotAssignedCount(dateObj, slot.id);
        if (count === slot.capacity) return 'bg-success-500/20 text-success-600';
        if (count > slot.capacity) return 'bg-danger-500/20 text-danger-600 animate-pulse';
        return 'bg-warning-500/20 text-warning-600';
    };

    const addSlot = ({ name, capacity, allowed_roles }) => {
        if (!name.trim()) return;
        const newId = Date.now();
        localSlots.value.push({
            id: newId,
            name: name.trim(),
            capacity: Number(capacity) || 1,
            sort_order: localSlots.value.length + 1,
            allowed_roles: allowed_roles || []
        });
        Object.values(weeklySchedules.value).forEach(dayData => {
            dayData.slots[newId] = [];
        });
        triggerChange();
    };

    const updateSlot = ({ id, name, capacity, allowed_roles }) => {
        const slot = localSlots.value.find(s => s.id === id);
        if (!slot || !name.trim()) return;

        slot.name = name.trim();
        slot.capacity = Number(capacity) || 1;
        slot.allowed_roles = allowed_roles || [];

        Object.values(weeklySchedules.value).forEach(dayData => {
            (dayData.slots[id] || []).forEach(item => {
                item.assigned_role = slot.name;
            });
        });
        triggerChange();
    };

    const removeSlot = (index) => {
        const removed = localSlots.value[index];
        if (removed) {
            Object.values(weeklySchedules.value).forEach(dayData => {
                delete dayData.slots[removed.id];
            });
        }
        localSlots.value.splice(index, 1);
        triggerChange();
    };

    const handleReorderSlots = () => {
        localSlots.value.forEach((slot, index) => {
            slot.sort_order = index + 1;
        });
        triggerChange();
    };

    const backToPos = () => {
        if (hasUnsavedChanges.value && !confirm('Existem alterações não salvas. Sair mesmo assim?')) return;
        window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'new' }));
    };

    const navigateWeek = (direction) => {
        if (hasUnsavedChanges.value && !confirm('Existem alterações não salvas. Mudar de semana mesmo assim?')) return;
        const d = new Date(activeStartDate.value + 'T00:00:00');
        d.setDate(d.getDate() + (direction * 7));
        const newStart = d.toISOString().split('T')[0];

        window.history.pushState({ tab: 'schedules' }, '', `/pos/schedules?start_date=${newStart}`);

        router.reload({
            data: { start_date: newStart },
            only: ['employees', 'slots', 'availableRoles', 'initialSchedules', 'scheduleStartDate'],
            preserveState: true,
            preserveScroll: true
        });
    };

    const clonePreviousWeek = () => {
        if (!confirm('Isso irá clonar a escala da semana anterior para esta. Continuar?')) return;

        router.post(route('operations.schedules.clone'), {
            start_date: activeStartDate.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({
                    data: { start_date: activeStartDate.value },
                    only: ['employees', 'slots', 'availableRoles', 'initialSchedules', 'scheduleStartDate'],
                    preserveState: true,
                    preserveScroll: true
                });
                toast.add({
                    severity: 'success',
                    summary: 'Clonado',
                    detail: 'Escala da semana anterior carregada!',
                    life: 3000
                });
            }
        });
    };

    const saveSchedule = () => {
        isSaving.value = true;
        const payload = [];

        Object.entries(weeklySchedules.value).forEach(([dateStr, dayData]) => {
            Object.entries(dayData.slots || {}).forEach(([slotId, items]) => {
                items.forEach(sch => {
                    payload.push({
                        employee_id: sch.employee_id,
                        date: dateStr,
                        schedule_slot_id: Number(slotId),
                        assigned_role: sch.assigned_role,
                        is_day_off: false,
                        is_extra_shift: sch.is_extra_shift
                    });
                });
            });

            (dayData.dayOff || []).forEach(sch => {
                payload.push({
                    employee_id: sch.employee_id,
                    date: dateStr,
                    schedule_slot_id: null,
                    assigned_role: 'Folga',
                    is_day_off: true,
                    is_extra_shift: false
                });
            });
        });

        router.post(route('operations.schedules.batch'), {
            start_date: activeStartDate.value,
            schedules: payload,
            slots: localSlots.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                hasUnsavedChanges.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Escala Salva',
                    detail: 'A escala semanal e slots foram salvos com sucesso!',
                    life: 3000
                });
            },
            onFinish: () => {
                isSaving.value = false;
            }
        });
    };

    return {
        activeSideTab,
        sideTabOptions,
        employeePool,
        localSlots,
        availableRoles,
        hasUnsavedChanges,
        isSaving,
        weekDates,
        formatShortDate,
        formatWeekday,
        formatSlotCapacityText,
        triggerChange,
        cloneEmployee,
        onDragStartPool,
        onDragStartSlot,
        onDragStartDayOff,
        onDragEnd,
        isSlotIncompatible,
        getSlotList,
        getDayOffList,
        onSlotChange,
        onDayOffChange,
        removeScheduleItem,
        getEmployeeWeeklyWorkDays,
        getWorkingCount,
        getDayOffCount,
        getSlotAssignedCount,
        getSlotCapacityClass,
        addSlot,
        updateSlot,
        removeSlot,
        handleReorderSlots,
        backToPos,
        navigateWeek,
        clonePreviousWeek,
        saveSchedule
    };
}
