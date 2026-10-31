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
            close_store: closeStore,
            accepting_orders: !closeStore
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                isProcessing.value = false;
                isModalVisible.value = false;

                toast?.add({
                    severity: 'success',
                    summary: closeStore ? 'Loja Online Pausada' : 'Loja Online Aberta',
                    detail: closeStore
                        ? 'Novos pedidos bloqueados no app do cliente. O salão continua operando normalmente!'
                        : 'Cardápio digital aberto para novos pedidos no app do cliente!',
                    life: 4000
                });

                if (onSuccessCallback) onSuccessCallback();
            },
            onError: (errors) => {
                isProcessing.value = false;
                toast?.add({
                    severity: 'error',
                    summary: 'Erro ao Atualizar Loja',
                    detail: errors.error || 'Não foi possível atualizar o status da loja online.',
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
