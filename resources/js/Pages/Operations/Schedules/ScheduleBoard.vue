<template>
    <div class="grid h-full min-h-0 w-full bg-surface-1 text-surface-text overflow-hidden font-sans p-2 transition-all duration-500 grid-cols-1 lg:grid-cols-[1fr_340px] xl:grid-cols-[1fr_380px] gap-2">

        <!-- DYNAMIC ISLAND (TELEPORT DAS AÇÕES PRINCIPAIS) -->
        <Teleport v-if="isMounted" to="#dynamic-island-portal">
            <div class="flex items-center h-10 md:h-12 shrink-0 mr-1 gap-2">
                <PosTeleportButton
                    icon="pi-arrow-left"
                    color="primary"
                    label="Voltar"
                    @click="backToPos"
                />

                <div class="w-px h-5 bg-surface-border/50 mx-1"></div>

                <PosTeleportButton
                    icon="pi-chevron-left"
                    color="surface"
                    label="Sem. Anterior"
                    @click="navigateWeek(-1)"
                />

                <PosTeleportButton
                    icon="pi-chevron-right"
                    color="surface"
                    label="Próx. Semana"
                    @click="navigateWeek(1)"
                />

                <div class="w-px h-5 bg-surface-border/50 mx-1"></div>

                <PosTeleportButton
                    icon="pi-copy"
                    color="primary"
                    label="Clonar Anterior"
                    :disabled="isSaving || hasUnsavedChanges"
                    @click="clonePreviousWeek"
                />

                <PosTeleportButton
                    icon="pi-save"
                    color="success"
                    :label="isSaving ? 'Salvando...' : 'Salvar Escala'"
                    :disabled="!hasUnsavedChanges || isSaving"
                    @click="saveSchedule"
                />
            </div>
        </Teleport>

        <!-- PAINEL PRINCIPAL: CALENDÁRIO COM AS COLUNAS DOS DIAS -->
        <div class="flex flex-col h-full min-h-0 min-w-0 bg-surface-2 rounded-4xl overflow-hidden relative shadow-sm">
            <div class="flex-1 flex overflow-x-auto p-3 gap-3 hide-scrollbar bg-surface-1/40">
                <ScheduleDayColumn
                    v-for="(dateObj, dIdx) in weekDates"
                    :key="dIdx"
                    :date-obj="dateObj"
                    :slots="localSlots"
                    :format-weekday="formatWeekday"
                    :format-short-date="formatShortDate"
                    :get-working-count="getWorkingCount"
                    :get-day-off-count="getDayOffCount"
                    :get-slot-list="getSlotList"
                    :getDayOffList="getDayOffList"
                    :on-slot-change="onSlotChange"
                    :on-day-off-change="onDayOffChange"
                    :get-slot-assigned-count="getSlotAssignedCount"
                    :get-slot-capacity-class="getSlotCapacityClass"
                    :get-employee-weekly-work-days="getEmployeeWeeklyWorkDays"
                    :is-slot-incompatible="isSlotIncompatible"
                    @drag-start-slot="onDragStartSlot"
                    @drag-start-day-off="onDragStartDayOff"
                    @drag-end="onDragEnd"
                    @remove-item="removeScheduleItem"
                    @change="triggerChange"
                />
            </div>
        </div>

        <!-- PAINEL LATERAL: EQUIPE E GERENCIADOR DE SLOTS COM ROLES -->
        <ScheduleSidebar
            v-model:active-side-tab="activeSideTab"
            :side-tab-options="sideTabOptions"
            :employee-pool="employeePool"
            :slots="localSlots"
            :available-roles="availableRoles"
            :clone-employee="cloneEmployee"
            :get-employee-weekly-work-days="getEmployeeWeeklyWorkDays"
            :format-slot-capacity-text="formatSlotCapacityText"
            @drag-start-pool="onDragStartPool"
            @drag-end="onDragEnd"
            @add-slot="addSlot"
            @update-slot="updateSlot"
            @remove-slot="removeSlot"
            @reorder-slots="handleReorderSlots"
        />

    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import PosTeleportButton from '@/Components/UI/PosTeleportButton.vue';
import ScheduleDayColumn from './ScheduleDayColumn.vue';
import ScheduleSidebar from './ScheduleSidebar.vue';
import { useSchedule } from './Composables/useSchedule';

const props = defineProps({
    startDate: String,
    employees: Array,
    slots: Array,
    availableRoles: Array,
    initialSchedules: Array
});

const isMounted = ref(false);

const {
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
} = useSchedule(props);

onMounted(() => {
    isMounted.value = true;
});

onBeforeUnmount(() => {
    isMounted.value = false;
});
</script>
