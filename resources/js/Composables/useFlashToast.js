import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

const consumedNonces = new Set();

export function useFlashToast() {
    const page = usePage();
    const toast = useToast();

    watch(
        () => page.props.flash?.nonce,
        (nonce) => {
            if (!nonce || consumedNonces.has(nonce)) return;
            consumedNonces.add(nonce);

            // Mantém o histórico enxuto em memória
            if (consumedNonces.size > 50) {
                const first = consumedNonces.values().next().value;
                consumedNonces.delete(first);
            }

            const flash = page.props.flash || {};
            if (flash.event === 'silent') return;

            if (flash.error) {
                toast.add({ severity: 'error', summary: 'Atenção', detail: flash.error, life: 5000 });
            }
            if (flash.warning) {
                toast.add({ severity: 'warn', summary: 'Aviso', detail: flash.warning, life: 4500 });
            }
            if (flash.info) {
                toast.add({ severity: 'info', summary: 'Informação', detail: flash.info, life: 4000 });
            }
            if (flash.success) {
                toast.add({ severity: 'success', summary: 'Sucesso', detail: flash.success, life: 3500 });
            }
        },
        { immediate: true }
    );
}
