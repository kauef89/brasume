import { onMounted, onBeforeUnmount, ref } from 'vue';

export function usePosIdleTimer({ timeout = 60000, onIdle, isEnabled = () => true }) {
    let timer = null;
    let lastActivity = Date.now();

    const resetTimer = () => {
        // Throttling: ignora chamadas repetidas em menos de 1 segundo
        const now = Date.now();
        if (now - lastActivity < 1000) return;
        lastActivity = now;

        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        if (isEnabled()) {
            timer = setTimeout(() => {
                if (isEnabled()) {
                    onIdle();
                }
            }, timeout);
        }
    };

    const stopTimer = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    };

    const handleUserActivity = () => {
        resetTimer();
    };

    onMounted(() => {
        const events = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'];
        events.forEach(evt => window.addEventListener(evt, handleUserActivity, { passive: true }));
        resetTimer();
    });

    onBeforeUnmount(() => {
        stopTimer();
        const events = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'];
        events.forEach(evt => window.removeEventListener(evt, handleUserActivity));
    });

    return {
        resetTimer,
        stopTimer
    };
}
