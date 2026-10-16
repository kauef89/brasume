import { usePosSecurity } from '@/Pages/Gateway/Composables/usePosSecurity';
import { ref } from 'vue';

export function useSecureAction(companySettings) {
    const isReasonModalVisible = ref(false);
    const itemToProcess = ref(null);
    const authActionType = ref('cancel'); // 'cancel', 'edit' ou 'swap'
    const authNeedsPin = ref(true);

    const { requiresAuth } = usePosSecurity(companySettings || {});

    const initiateSecureAction = (item, type = 'cancel') => {
        itemToProcess.value = item;
        authActionType.value = type;

        // 🚀 Mapeia o Swap para usar a mesma trava de segurança da Edição
        const securityKey = (type === 'edit' || type === 'swap') ? 'edit_item' : (type === 'cancel_order' ? 'cancel_order' : 'cancel_item');
        authNeedsPin.value = requiresAuth(securityKey);

        isReasonModalVisible.value = true;
    };

    return {
        isReasonModalVisible,
        itemToProcess,
        authActionType,
        authNeedsPin,
        initiateSecureAction
    };
}
