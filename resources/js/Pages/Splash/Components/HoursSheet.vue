<template>
    <AdaptiveModal v-model:visible="isVisible">
        <div class="p-6">
            <!-- CABEÇALHO -->
            <div class="flex flex-col items-center justify-center gap-3 shrink-0 mb-6 mt-2">
                <div class="w-14 h-14 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center shadow-inner">
                    <i class="pi pi-calendar text-2xl"></i>
                </div>
                <div class="flex flex-col items-center text-center">
                    <h2 class="text-xl font-black text-surface-text leading-tight">Nossos Horários</h2>
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 mt-0.5">Funcionamento</span>
                </div>
            </div>

            <!-- MODO 24 HORAS -->
            <div v-if="settings?.is_24_hours" class="p-6 bg-success-500/10 border border-success-500/20 rounded-2xl flex flex-col items-center justify-center gap-2 mb-4 text-center">
                <i class="pi pi-bolt text-3xl text-success-500"></i>
                <span class="font-black text-success-600 text-lg">Aberto 24 Horas!</span>
                <span class="text-xs text-success-600/80 font-bold">Estamos operando o dia todo, todos os dias.</span>
            </div>

            <!-- MODO FECHADO INDETERMINADAMENTE -->
            <div v-else-if="settings?.is_closed_indefinitely" class="p-6 bg-danger-500/10 border border-danger-500/20 rounded-2xl flex flex-col items-center justify-center gap-2 mb-4 text-center">
                <i class="pi pi-lock text-3xl text-danger-500"></i>
                <span class="font-black text-danger-600 text-lg">Temporariamente Fechados</span>
                <span class="text-xs text-danger-600/80 font-bold">Voltaremos às atividades em breve.</span>
            </div>

            <!-- MATRIZ DE HORÁRIOS (Semana) -->
            <div v-else class="flex flex-col gap-2 pb-4">
                <div v-for="(dayName, index) in dayNames" :key="index"
                    class="flex items-center justify-between p-3 rounded-xl border"
                    :class="isToday(index) ? 'bg-primary-500/10 border-primary-500/30' : 'bg-surface-2 border-surface-border'">

                    <span class="font-bold text-sm" :class="isToday(index) ? 'text-primary-600' : 'text-surface-text'">
                        {{ dayName }} <span v-if="isToday(index)" class="text-[9px] uppercase tracking-widest ml-1 opacity-80">(Hoje)</span>
                    </span>

                    <div class="flex flex-col items-end gap-1">
                        <template v-if="getHoursForDay(index).length > 0">
                            <span v-for="(turn, idx) in getHoursForDay(index)" :key="idx" class="text-xs font-bold text-surface-muted bg-surface-3 px-2 py-0.5 rounded-md">
                                {{ turn.open_time }} - {{ turn.close_time }}
                            </span>
                        </template>
                        <span v-else class="text-xs font-black text-danger-500 uppercase tracking-widest bg-danger-500/10 px-2 py-0.5 rounded-md">
                            Fechado
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

const props = defineProps({
    visible: { type: Boolean, required: true }
});

const emit = defineEmits(['update:visible']);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const page = usePage();
const settings = computed(() => page.props.companySettings || {});

// Laravel formata os dias de 0 a 6 (0 = Domingo, 6 = Sábado)
const dayNames = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

const currentDayOfWeek = new Date().getDay();

const isToday = (dayIndex) => dayIndex === currentDayOfWeek;

const getHoursForDay = (dayIndex) => {
    const hoursArray = settings.value.business_hours || [];

    return hoursArray
        .filter(rule => rule.day_of_week == dayIndex)
        .map(rule => {
            // Remove os segundos (HH:MM:SS -> HH:MM)
            const formatTime = (time) => time ? time.substring(0, 5) : '--:--';
            return {
                open_time: formatTime(rule.open_time),
                close_time: formatTime(rule.close_time)
            };
        });
};
</script>
