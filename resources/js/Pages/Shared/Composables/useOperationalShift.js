import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { ref } from 'vue';

export function useOperationalShift() {
    const isModalVisible = ref(false);
    const isProcessing = ref(false);
    const toast = useToast();
    const confirm = useConfirm();

    const openModal = () => {
        isModalVisible.value = true;
    };

    const closeModal = () => {
        isModalVisible.value = false;
    };

    const submitCloseShift = (closeStore = true, onSuccessCallback = null, unpaidCount = 0) => {
        const executeClose = () => {
            isProcessing.value = true;

            router.post(route('operations.shift.close-operational'), {
                close_store: closeStore
            }, {
                preserveState: true,
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

        // 🚀 Interceptador com o PrimeVue ConfirmDialog
        if (unpaidCount > 0) {
            confirm.require({
                header: 'Pagamentos Pendentes',
                message: `Você possui ${unpaidCount} colaborador(es) sem pagamento liquidado. Deseja acumular as diárias no extrato deles e encerrar o turno mesmo assim?`,
                icon: 'pi pi-exclamation-triangle text-warning-500',
                acceptClass: 'p-button-warning px-4 py-2',
                rejectClass: 'p-button-secondary p-button-text px-4 py-2',
                acceptLabel: 'Sim, rolar dívidas',
                rejectLabel: 'Voltar e Pagar',
                accept: () => {
                    executeClose();
                }
            });
        } else {
            executeClose();
        }
    };

    return {
        isModalVisible,
        isProcessing,
        openModal,
        closeModal,
        submitCloseShift
    };
}
