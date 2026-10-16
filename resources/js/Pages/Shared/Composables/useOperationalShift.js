import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { ref } from 'vue';

export function useOperationalShift() {
    const isModalVisible = ref(false);
    const isProcessing = ref(false);
    const toast = useToast();

    const openModal = () => {
        isModalVisible.value = true;
    };

    const closeModal = () => {
        isModalVisible.value = false;
    };

    const submitCloseShift = (closeStore = true, onSuccessCallback = null) => {
        isProcessing.value = true;

        router.post(route('operations.shift.close-operational'), {
            close_store: closeStore
        }, { preserveState: true, 
            preserveScroll: true,
            onSuccess: () => {
                isProcessing.value = false;
                isModalVisible.value = false;

                toast?.add({
                    severity: 'success',
                    summary: 'Turno Operacional Encerrado',
                    detail: closeStore
                        ? 'Turno encerrado e loja fechada no app do cliente!'
                        : 'Turno encerrado! A loja continua aberta no app.',
                    life: 4000
                });

                if (onSuccessCallback) onSuccessCallback();
            },
            onError: (errors) => {
                isProcessing.value = false;
                toast?.add({
                    severity: 'error',
                    summary: 'Erro ao Encerrar Turno',
                    detail: errors.error || 'Não foi possível encerrar o turno operacional.',
                    life: 5000
                });
            }
        });
    };

    return {
        isModalVisible,
        isProcessing,
        openModal,
        closeModal,
        submitCloseShift
    };
}
