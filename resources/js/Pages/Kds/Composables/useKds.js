import { router } from "@inertiajs/vue3";
import { useStorage } from '@vueuse/core';
import { computed, ref, watch } from "vue";

// ==========================================
// 🚀 1. SINGLETON DO SMART TIMER (Relógio Global Único)
// ==========================================
const currentTime = ref(new Date());
let timerInterval = null;

// Só inicia o relógio uma vez na vida do navegador
if (!timerInterval && typeof window !== 'undefined') {
    timerInterval = setInterval(() => { currentTime.value = new Date(); }, 10000);
}

export function useKds(props) {
    const getElapsedMinutes = (item) => {
        if (!item.fired_at) return 0;
        const fired = new Date(item.fired_at);
        const diffMs = currentTime.value - fired;
        return Math.max(0, Math.floor(diffMs / 60000)); // 🚀 Blinda contra números negativos
    };

    const getElapsedTime = (item) => {
        return `${getElapsedMinutes(item)} min`;
    };

    const getTimerState = (item) => {
        if (item.production_status === 'pending' || !item.prep_time_minutes) return 'normal';
        const elapsed = getElapsedMinutes(item);
        const target = item.prep_time_minutes;

        if (elapsed >= target) return 'late';
        if (elapsed >= (target * 0.75)) return 'warning';
        return 'normal';
    };

    const getTimerColorClass = (item) => {
        const state = getTimerState(item);
        if (state === 'late') return 'border-l-red-500';
        if (state === 'warning') return 'border-l-orange-500';
        return 'border-l-primary-500';
    };

    const getTimerTextColor = (item) => {
        const state = getTimerState(item);
        if (state === 'late') return 'text-red-500';
        if (state === 'warning') return 'text-orange-500';
        return 'text-primary-500';
    };

    // ==========================================
    // 2. GESTÃO DE COLUNAS E "MODO TODAS"
    // ==========================================
    const isSettingsOpen = ref(false);
    const tempColumns = ref([]);
    const savedSettings = useStorage('pos_kds_settings', {});

    const activeColumns = computed(() => {
        if (props.activeKitchenId === 'all') {
            return [
                { id: "pending", label: "Entrada (Todas)", is_final: false },
                { id: "preparing", label: "Em Preparo (Geral)", is_final: false },
                { id: "ready", label: "Pronto (Finalização)", is_final: true },
            ];
        }

        const kId = String(props.activeKitchenId || 'default');
        if (!savedSettings.value[kId] || savedSettings.value[kId].length === 0) {
            return [
                { id: "pending", label: "Fila de Entrada", is_final: false },
                { id: "preparing", label: "Em Preparo", is_final: false },
                { id: "ready", label: "Pronto", is_final: true },
            ];
        }
        return savedSettings.value[kId];
    });

    const openSettings = () => {
        tempColumns.value = JSON.parse(JSON.stringify(activeColumns.value));
        isSettingsOpen.value = true;
    };

    const addColumn = () => { tempColumns.value.push({ id: 'col_' + Date.now(), label: 'Nova Coluna', is_final: false }); };
    const removeColumn = (index) => { tempColumns.value.splice(index, 1); };
    const setFinalColumn = (index) => { tempColumns.value.forEach((col, i) => col.is_final = (i === index)); };

    const saveColumns = () => {
        const kId = String(props.activeKitchenId || 'default');
        savedSettings.value = {
            ...savedSettings.value,
            [kId]: JSON.parse(JSON.stringify(tempColumns.value))
        };
        isSettingsOpen.value = false;
    };

    // ==========================================
    // 3. FLUXO DE DADOS (O Mapeamento Mágico)
    // ==========================================
    const columnsData = ref({});

    watch(() => [props.initialItems, activeColumns.value], () => {
        const newData = {};
        activeColumns.value.forEach(col => { newData[col.id] = []; });

        (props.initialItems || []).forEach(item => {
            if (props.activeKitchenId === 'all') {
                let targetCol = 'preparing';
                const kId = String(item.kitchen_id || 'default');
                const kitchenCols = savedSettings.value[kId];

                if (!kitchenCols || kitchenCols.length === 0) {
                    if (item.production_status === 'pending') targetCol = 'pending';
                    else if (item.production_status === 'ready') targetCol = 'ready';
                } else {
                    if (item.production_status === kitchenCols[0].id) {
                        targetCol = 'pending';
                    } else {
                        const colDef = kitchenCols.find(c => c.id === item.production_status);
                        if (colDef && colDef.is_final) targetCol = 'ready';
                    }
                }
                if (newData[targetCol]) newData[targetCol].push(item);
            } else {
                if (newData[item.production_status]) {
                    newData[item.production_status].push(item);
                } else if (activeColumns.value.length > 0) {
                    newData[activeColumns.value[0].id].push(item);
                }
            }
        });
        columnsData.value = newData;
    }, { immediate: true, deep: true });

    const changeKitchen = (id) => {
        router.visit(route('operations.kds', { kitchen_id: id }), { preserveState: false });
    };

    const onDragChange = (event, newStatus) => {
        if (event.added) {
            const item = event.added.element;
            let finalStatusToSend = newStatus;

            if (props.activeKitchenId === 'all') {
                const kId = String(item.kitchen_id || 'default');
                const savedSettingsValue = JSON.parse(localStorage.getItem('pos_kds_settings') || '{}');
                const kitchenCols = savedSettingsValue[kId];

                if (kitchenCols && kitchenCols.length >= 3) {
                    if (newStatus === 'pending') {
                        finalStatusToSend = kitchenCols[0].id;
                    } else if (newStatus === 'ready') {
                        const finalCol = kitchenCols.find(c => c.is_final);
                        finalStatusToSend = finalCol ? finalCol.id : kitchenCols[kitchenCols.length - 1].id;
                    } else {
                        finalStatusToSend = kitchenCols[1].id;
                    }
                }
            }

            router.post(route('operations.kds.update-status', item.id), {
                production_status: finalStatusToSend
            }, {
                preserveScroll: true,
                preserveState: true
            });
        }
    };

    const clearReadyItems = (columnId) => {
        const readyItems = columnsData.value[columnId] || [];
        readyItems.forEach(item => {
            router.post(route('operations.kds.update-status', item.id), { production_status: 'delivered' }, { preserveScroll: true, preserveState: true });
        });
    };

    return {
        currentTime,
        isSettingsOpen, tempColumns, activeColumns, columnsData,
        openSettings, addColumn, removeColumn, setFinalColumn, saveColumns,
        changeKitchen, onDragChange, clearReadyItems,
        getElapsedTime, getTimerColorClass, getTimerTextColor
    };
}
