<template>
    <div class="w-72 shrink-0 flex flex-col h-full bg-surface-2 rounded-2xl border border-surface-border overflow-hidden">
        <!-- CABEÇALHO DO DIA -->
        <div class="p-3 border-b border-surface-border bg-surface-3/40 shrink-0 flex items-center justify-between">
            <div>
                <span class="font-black text-sm text-surface-text uppercase tracking-wide block">
                    {{ formatWeekday(dateObj) }}
                </span>
                <span class="text-[11px] font-bold text-surface-muted">
                    {{ formatShortDate(dateObj) }}
                </span>
            </div>
            <div class="text-[10px] font-bold px-2 py-0.5 rounded bg-surface-1 text-surface-muted border border-surface-border">
                Ativos: {{ getWorkingCount(dateObj) }}
            </div>
        </div>

        <!-- SLOTS DE TRABALHO -->
        <div class="flex-1 min-h-0 overflow-y-auto p-2 flex flex-col gap-2.5 hide-scrollbar">
            <div
                v-for="slot in slots"
                :key="slot.id"
                class="bg-surface-1/70 border border-surface-border/70 rounded-xl p-2 flex flex-col gap-1.5 transition-all duration-300"
                :class="isSlotIncompatible(slot.id) ? 'opacity-25 grayscale pointer-events-none bg-surface-1/30 border-surface-border/30' : 'opacity-100'"
            >
                <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider px-1">
                    <span class="text-surface-text truncate">{{ slot.name }}</span>
                    <span
                        class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                        :class="getSlotCapacityClass(dateObj, slot)"
                    >
                        {{ getSlotAssignedCount(dateObj, slot.id) }}/{{ slot.capacity }}
                    </span>
                </div>

                <!-- DRAGGABLE CONECTADO AO ARRAY DIRETO DO SLOT -->
                <VueDraggable
                    :list="getSlotList(dateObj, slot.id)"
                    group="people"
                    item-key="unique_id"
                    class="min-h-[46px] rounded-lg bg-surface-2/40 border border-dashed border-surface-border/80 p-1 flex flex-col gap-1.5"
                    @change="onSlotChange($event, dateObj, slot.id)"
                    @start="$emit('drag-start-slot', $event, dateObj, slot.id)"
                    @end="$emit('drag-end')"
                >
                    <template #item="{ element }">
                        <ScheduleCard
                            :schedule="element"
                            :days-worked="getEmployeeWeeklyWorkDays(element.employee_id)"
                            :is-day-off="false"
                            @remove="$emit('remove-item', dateObj, element.unique_id)"
                            @change="$emit('change')"
                        />
                    </template>
                </VueDraggable>
            </div>
        </div>

        <!-- BASE DA COLUNA: FOLGAS PROGRAMADAS (NUNCA INCOMPATÍVEL) -->
        <div class="p-2 border-t border-surface-border bg-surface-3/30 shrink-0 flex flex-col gap-1.5">
            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider px-1 text-primary-500">
                <span class="flex items-center gap-1">
                    <i class="pi pi-moon"></i> Folgas Programadas
                </span>
                <span>{{ getDayOffCount(dateObj) }}</span>
            </div>

            <VueDraggable
                :list="getDayOffList(dateObj)"
                group="people"
                item-key="unique_id"
                class="min-h-[48px] max-h-36 overflow-y-auto rounded-lg bg-primary-500/5 border border-dashed border-primary-500/20 p-1 flex flex-col gap-1.5 hide-scrollbar"
                @change="onDayOffChange($event, dateObj)"
                @start="$emit('drag-start-day-off', $event, dateObj)"
                @end="$emit('drag-end')"
            >
                <template #item="{ element }">
                    <ScheduleCard
                        :schedule="element"
                        :days-worked="getEmployeeWeeklyWorkDays(element.employee_id)"
                        :is-day-off="true"
                        @remove="$emit('remove-item', dateObj, element.unique_id)"
                        @change="$emit('change')"
                    />
                </template>
            </VueDraggable>
        </div>
    </div>
</template>

<script setup>
import VueDraggable from 'vuedraggable';
import ScheduleCard from './ScheduleCard.vue';

defineProps({
    dateObj: { type: Date, required: true },
    slots: { type: Array, required: true },
    formatWeekday: { type: Function, required: true },
    formatShortDate: { type: Function, required: true },
    getWorkingCount: { type: Function, required: true },
    getDayOffCount: { type: Function, required: true },
    getSlotList: { type: Function, required: true },
    getDayOffList: { type: Function, required: true },
    onSlotChange: { type: Function, required: true },
    onDayOffChange: { type: Function, required: true },
    getSlotAssignedCount: { type: Function, required: true },
    getSlotCapacityClass: { type: Function, required: true },
    getEmployeeWeeklyWorkDays: { type: Function, required: true },
    isSlotIncompatible: { type: Function, required: true }
});

defineEmits([
    'remove-item',
    'change',
    'drag-start-slot',
    'drag-start-day-off',
    'drag-end'
]);
</script>
