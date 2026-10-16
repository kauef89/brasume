import { useStoreStatus } from '@/Composables/useStoreStatus';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

// 🚀 SINGLETON GLOBAL: Mantém o valor em memória durante a navegação SPA
const currentBackground = ref(null);

export function useDynamicBackground(forceOpen = false) {
    const page = usePage();
    const { isOpen } = useStoreStatus();

    const pickRandomBackground = (forceRepick = false) => {
        const settings = page.props.publicSettings || page.props.companySettings || {};

        const bgOpen = settings.home_bg_open || [];
        const bgClosed = settings.home_bg_closed || [];

        const currentStatus = (isOpen.value || forceOpen);
        let pool = currentStatus ? bgOpen : (bgClosed.length > 0 ? bgClosed : bgOpen);

        // 🚀 A MÁGICA DA CONTINUIDADE:
        // Lemos do sessionStorage para manter a identidade visual até num F5 (Refresh).
        const savedBg = sessionStorage.getItem('brasume_current_bg');
        const savedStatus = sessionStorage.getItem('brasume_bg_status');

        // Se não estamos forçando a troca, e já temos um fundo salvo que ainda faz sentido pro status atual...
        if (!forceRepick && savedBg && savedStatus === String(currentStatus) && pool.includes(savedBg)) {
            currentBackground.value = savedBg;
            return;
        }

        // Sorteia um novo se for a primeira vez, se não tiver cache, ou se a loja fechou/abriu
        if (pool && pool.length > 0) {
            const picked = pool[Math.floor(Math.random() * pool.length)];
            currentBackground.value = picked;
            sessionStorage.setItem('brasume_current_bg', picked);
            sessionStorage.setItem('brasume_bg_status', String(currentStatus));
        } else {
            currentBackground.value = null;
            sessionStorage.removeItem('brasume_current_bg');
            sessionStorage.removeItem('brasume_bg_status');
        }
    };

    onMounted(() => {
        pickRandomBackground();
    });

    watch(isOpen, () => {
        // 🚀 Passamos "true" para forçar um novo sorteio já que a loja mudou de Aberto pra Fechado
        pickRandomBackground(true);
    });

    // Retorna a string CSS prontinha para ser injetada no :style
    const backgroundStyle = computed(() => {
        return currentBackground.value
            ? `background-image: url('/media/file?path=${currentBackground.value}');`
            : '';
    });

    return {
        currentBackground,
        backgroundStyle,
        pickRandomBackground
    };
}
