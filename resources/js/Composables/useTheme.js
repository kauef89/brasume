import { onMounted, ref } from 'vue';

export function useTheme() {
    // Trava o estado sempre no dark por enquato, até ajustar o light mode
    const currentMode = ref('dark');

    const setMode = () => {
        currentMode.value = 'dark';
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark'); // Sobrescreve qualquer cache antigo do usuário
    };

    onMounted(() => {
        // Toda vez que o app montar, garante o dark mode
        setMode();
    });

    return {
        currentMode,
        setMode
    };
}
