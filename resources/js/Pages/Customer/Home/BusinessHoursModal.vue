<template>
    <AdaptiveModal :visible="visible" @update:visible="$emit('update:visible', $event)">
            <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-black text-xl text-surface-text">Horários de Atendimento</h3>
                <button @click="$emit('update:visible', false)" class="w-8 h-8 rounded-full bg-surface-2 hover:bg-surface-3 text-surface-muted flex items-center justify-center transition-colors cursor-pointer border-none outline-none">
                    <i class="pi pi-times text-sm font-bold"></i>
                </button>
            </div>

            <div class="flex flex-col gap-2">
                <div v-for="(day, index) in weekDays" :key="index"
                    class="flex items-center justify-between p-3.5 rounded-xl border transition-colors"
                    :class="isToday(index) ? 'bg-primary-500/10 border-primary-500/30' : 'bg-transparent border-transparent'">

                    <span class="font-bold text-sm flex items-center" :class="isToday(index) ? 'text-primary-600 dark:text-primary-400' : 'text-surface-text'">
                        {{ day.name }}
                        <span v-if="isToday(index)" class="ml-2 text-[9px] font-black uppercase tracking-widest bg-primary-500 text-white px-2 py-0.5 rounded-md">
                            Hoje
                        </span>
                    </span>

                    <span class="text-sm font-black tracking-tight" :class="day.isOpen ? 'text-surface-text' : 'text-danger-500'">
                        {{ day.isOpen ? `${day.open} às ${day.close}` : 'Fechado' }}
                    </span>
                </div>
            </div>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { computed } from 'vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

const props = defineProps({
    visible: Boolean,
    settings: Object
});

defineEmits(['update:visible']);

const isToday = (index) => {
    const today = new Date().getDay(); // 0 = Domingo, 1 = Segunda...
    return today === index;
};

const weekDays = computed(() => {
    const days = [
        { name: 'Domingo', isOpen: false },
        { name: 'Segunda-feira', isOpen: false },
        { name: 'Terça-feira', isOpen: false },
        { name: 'Quarta-feira', isOpen: false },
        { name: 'Quinta-feira', isOpen: false },
        { name: 'Sexta-feira', isOpen: false },
        { name: 'Sábado', isOpen: false }
    ];

    if (props.settings?.business_hours && Array.isArray(props.settings.business_hours)) {
        props.settings.business_hours.forEach(schedule => {
            const dayIndex = parseInt(schedule.day_of_week);
            if (days[dayIndex]) {
                days[dayIndex].isOpen = true;
                days[dayIndex].open = schedule.open_time.substring(0, 5);
                days[dayIndex].close = schedule.close_time.substring(0, 5);
            }
        });
    }
    return days;
});
</script>
