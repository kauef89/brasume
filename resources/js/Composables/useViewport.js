import { ref } from 'vue';

// 🚀 SINGLETON GLOBAIS
const isMobile = ref(false);
let isListening = false;

export function useViewport(breakpoint = 768) {
    if (typeof window !== 'undefined' && !isListening) {
        const checkMobile = () => {
            isMobile.value = window.innerWidth <= breakpoint;
        };

        // Checa a primeira vez e anexa o ouvinte ÚNICO
        checkMobile();
        window.addEventListener('resize', checkMobile);
        isListening = true;
    }

    return { isMobile };
}
