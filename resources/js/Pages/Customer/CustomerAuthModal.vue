<template>
    <AdaptiveModal v-model:visible="isVisible" :content-class="'!p-0 !bg-transparent'">
        <div class="w-full flex flex-col overflow-hidden p-6 max-h-[85vh] shadow-2xl">

            <!-- CABEÇALHO DO MODAL -->
            <div class="shrink-0 bg-z-10 text-center relative pt-5 flex flex-col gap-2 pb-2">
                <h2 class="text-xl font-bold text-surface-text">
                    {{ forceRegistration ? 'Crie sua Conta' : 'Oi de novo!' }}
                </h2>
                <p class="text-sm font-medium text-surface-muted pb-6">
                    {{ forceRegistration ? 'Para receber a notificação, crie uma conta grátis.' : 'Como você deseja continuar?' }}
                </p>
                <p v-if="!forceRegistration" class="text-xs font-medium text-surface-text pb-6">
                    Selecione um aplicativo que você já usa para se conectar sem precisar criar uma senha.
                </p>
            </div>

            <!-- CORPO -->
            <div class="flex-1 overflow-y-auto hide-scrollbar flex flex-col px-4 relative">
                <div v-if="!showGuestForm" class="flex flex-col gap-4 animate-in fade-in duration-300">
                    <CustomerAccess
                        :allow-guest="allowGuest"
                        :methods="customerAuthMethods"
                        :needs-email-fallback="needsEmailFallback"
                        :is-logging-in="isSubmitting"
                        :is-cart-mode="isCartMode"
                        @login="handleLogin"
                        @open-register="$emit('open-register')"
                        @guest-action="showGuestForm = true"
                    />
                </div>

                <div v-else class="animate-in slide-in-from-right-4 duration-300">
                    <button @click="showGuestForm = false" class="micro-label hover:text-surface-text outline-none mb-4 flex items-center gap-1">
                        <i class="pi pi-arrow-left"></i> Voltar
                    </button>
                    <GuestRegistrationForm @registered="handleGuestRegistered" />
                </div>
            </div>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import CustomerAccess from "@/Pages/Gateway/CustomerAccess.vue";
import GuestRegistrationForm from "@/Pages/Pos/Components/Cart/GuestRegistrationForm.vue";

const props = defineProps({
    visible: Boolean,
    isCartMode: { type: Boolean, default: false },
    isGuest: { type: Boolean, default: false },
    forceRegistration: { type: Boolean, default: false }
});

const emit = defineEmits(['update:visible', 'open-register', 'authenticated']);
const page = usePage();

const isSubmitting = ref(false);
const showGuestForm = ref(false);

const allowGuest = computed(() => {
    if (props.forceRegistration) return false;
    if (props.isGuest) return false;
    const flag = page.props.companySettings?.customer_allow_guest ?? page.props.company?.allowGuest;
    return flag === true || flag === 1 || flag === '1';
});

const customerAuthMethods = computed(() => {
    return page.props.companySettings?.customer_auth_methods ?? page.props.company?.customerAuthMethods ?? [];
});

const needsEmailFallback = computed(() => {
    return !customerAuthMethods.value || customerAuthMethods.value.length === 0;
});

const isVisible = computed({
    get: () => props.visible,
    set: (value) => {
        emit('update:visible', value);
        if (!value) setTimeout(() => showGuestForm.value = false, 300);
    }
});

// 🚀 Marca o sessionStorage ao abrir em modo carrinho
watch(() => props.visible, (val) => {
    if (val && props.isCartMode) {
        sessionStorage.setItem('customer_pending_checkout', 'true');
    }
});

const handleLogin = (credentials) => {
    isSubmitting.value = true;
    router.post(route('customer.auth.login'), credentials, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            isSubmitting.value = false;
            if (Object.keys(page.props.errors).length === 0) {
                isVisible.value = false;
                emit('authenticated');
            }
        },
        onError: () => { isSubmitting.value = false; }
    });
};

const handleGuestRegistered = () => {
    isVisible.value = false;
    emit('authenticated');
};
</script>
