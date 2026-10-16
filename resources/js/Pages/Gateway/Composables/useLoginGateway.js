import { router } from '@inertiajs/vue3';
import { useToast } from "primevue/usetoast";
import { computed, ref } from 'vue';

export function useLoginGateway(companyProp) {
    const toast = useToast();

    // ==========================================
    // REGRAS DE NEGÓCIO COMPUTADAS
    // ==========================================
    const allowGuest = computed(() => companyProp?.allowGuest ?? true);
    const customerMethods = computed(() => companyProp?.customerAuthMethods || []);
    const teamMethods = computed(() => companyProp?.teamAuthMethods || []);

    const customerNeedsEmailFallback = computed(() => !customerMethods.value.includes('google') && !customerMethods.value.includes('whatsapp'));
    const teamNeedsEmailFallback = computed(() => !teamMethods.value.includes('pin') && !teamMethods.value.includes('google'));

    // ==========================================
    // CHAMADAS DE AUTENTICAÇÃO (API)
    // ==========================================
    const isCustomerLoggingIn = ref(false);

    const loginCustomer = (payload) => {
        isCustomerLoggingIn.value = true;

        router.post(route('customer.auth.login'), payload, {
            preserveScroll: true,
            onFinish: () => {
                isCustomerLoggingIn.value = false;
            },
            onError: (errors) => {
                toast.add({ severity: 'error', summary: 'Acesso Negado', detail: errors.email || 'E-mail ou senha incorretos.', life: 4000 });
            }
        });
    };

    const isRegisteringCustomer = ref(false);
    const registerCustomer = (formData, onSuccessCallback) => {
        isRegisteringCustomer.value = true;
        router.post(route('customer.auth.register'), formData, {
            onSuccess: () => {
                isRegisteringCustomer.value = false;
                if (onSuccessCallback) onSuccessCallback();
            },
            onError: (errors) => {
                isRegisteringCustomer.value = false;
                toast.add({ severity: 'error', summary: 'Erro no Cadastro', detail: Object.values(errors)[0] || 'Verifique os dados informados.', life: 4000 });
            }
        });
    };

    const isTeamLoggingIn = ref(false);
    const loginTeam = (form) => {
        isTeamLoggingIn.value = true;
        router.post(route('auth.team-login'), form, {
            onFinish: () => {
                isTeamLoggingIn.value = false;
            },
            onError: (errors) => {
                toast.add({ severity: 'error', summary: 'Acesso Negado', detail: errors.email || 'Credenciais inválidas.', life: 4000 });
            }
        });
    };

    const isPinLoggingIn = ref(false);
        const loginWithPin = (payload, clearPinCallback) => {
            isPinLoggingIn.value = true;
            router.post(route('auth.pin.login'), payload, {
                preserveScroll: true,
                onFinish: () => {
                    isPinLoggingIn.value = false;
                },
                onError: (errors) => {
                    if (clearPinCallback) clearPinCallback();
                    toast.add({ severity: 'error', summary: 'Acesso Negado', detail: errors.pin || 'PIN inválido ou incorreto.', life: 3000 });
                }
            });
        };

    return {
        allowGuest, customerMethods, teamMethods,
        customerNeedsEmailFallback, teamNeedsEmailFallback,
        isCustomerLoggingIn, loginCustomer,
        isRegisteringCustomer, registerCustomer,
        isTeamLoggingIn, loginTeam,
        isPinLoggingIn, loginWithPin
    };
}
