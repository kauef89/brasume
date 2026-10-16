import { computed } from 'vue';

export function useKdsItemTimer(itemRef, currentTimeRef) {

    // 🚀 Helper matemático encapsulado e à prova de balas
    const getElapsedMinutes = (dateString) => {
            if (!dateString) return 0;

            let safeDate = dateString;
            if (typeof dateString === 'string') {
                safeDate = safeDate.replace(' ', 'T');
                // 🚀 Se a string de data não tiver a terminação 'Z' ou um fuso '-03', nós forçamos o Z (UTC).
                // Como no Laravel a DB já salva em UTC, isso vai estabilizar o fuso no mundo inteiro!
                if (!safeDate.endsWith('Z') && !safeDate.includes('-') && !safeDate.includes('+')) {
                    safeDate += 'Z';
                }
            }

            const start = new Date(safeDate).getTime();
            const now = new Date(currentTimeRef.value).getTime();

            if (isNaN(start) || isNaN(now)) return 0;

            const diffMs = now - start;
            return Math.max(0, Math.floor(diffMs / 60000));
        };

    // OS DOIS TEMPOS DISTINTOS
    const totalTime = computed(() => getElapsedMinutes(itemRef.value.created_at));
    const phaseTime = computed(() => getElapsedMinutes(itemRef.value.updated_at));

    // Avalia a cor de alerta usando o fired_at (se já estiver no fogo) ou o tempo total
    const timerState = computed(() => {
        if (!itemRef.value.prep_time_minutes) return 'normal';

        const elapsed = itemRef.value.fired_at
            ? getElapsedMinutes(itemRef.value.fired_at)
            : totalTime.value;

        const target = itemRef.value.prep_time_minutes;

        if (elapsed >= target) return 'late';
        if (elapsed >= (target * 0.75)) return 'warning';
        return 'normal';
    });

    const timerColorClass = computed(() => {
        if (timerState.value === 'late') return 'border-l-red-500';
        if (timerState.value === 'warning') return 'border-l-orange-500';
        return 'border-l-primary-500';
    });

    const timerTextColorClass = computed(() => {
        if (timerState.value === 'late') return 'text-red-500';
        if (timerState.value === 'warning') return 'text-orange-500';
        return 'text-primary-500';
    });

    return {
        totalTime,
        phaseTime,
        timerColorClass,
        timerTextColorClass
    };
}
