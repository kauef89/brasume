import { computed } from "vue";

export function usePosSecurity(companySettings) {
    const securityMode = computed(() => companySettings?.pos_security_mode || 'standard');
    const customActions = computed(() => companySettings?.pos_secure_actions || []);

    const requiresAuth = (actionType) => {
        if (securityMode.value === 'free') return false;
        if (securityMode.value === 'strict') return true;

        if (securityMode.value === 'standard') {
                    // 🚀 ADICIONADO AQUI TAMBÉM:
                    return ['cancel_item', 'cancel_order', 'apply_discount', 'cash_out', 'close_shift_override'].includes(actionType);
                }

        if (securityMode.value === 'custom') {
            return customActions.value.includes(actionType);
        }

        return false;
    };

    return { requiresAuth, securityMode };
}
