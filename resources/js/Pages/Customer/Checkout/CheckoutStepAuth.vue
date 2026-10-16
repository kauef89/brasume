<template>
    <div class="w-full flex-1 flex flex-col overflow-y-auto hide-scrollbar px-6 pt-6 pb-8">
        <!-- 1. MÉTODOS DE LOGIN -->
        <CustomerAccess
            v-if="subStep === 'methods'"
            :allow-guest="allowGuest"
            :methods="authMethods"
            :needs-email-fallback="needsEmailFallback"
            :is-logging-in="isSubmittingAuth"
            :is-cart-mode="true"
            @login="$emit('login', $event)"
            @open-register="$emit('open-register')"
            @guest-action="$emit('update:sub-step', 'guest_name')"
        />

        <!-- 2. FORMULÁRIO DO VISITANTE (NOME OU WHATSAPP) -->
        <div v-else class="animate-in slide-in-from-right-4 duration-300 flex flex-col h-full w-full pt-2">
            <GuestRegistrationForm
                :is-customer-mode="true"
                :step="subStep"
                :initial-name="guestName"
                :initial-phone="guestPhone"
                @update:name="$emit('update:guest-name', $event)"
                @update:phone="$emit('update:guest-phone', $event)"
                @advance="handleGuestAdvance"
                @submit="handleGuestAdvance"
                @registered="$emit('guest-registered')"
            />
        </div>
    </div>
</template>

<script setup>
import CustomerAccess from '@/Pages/Gateway/CustomerAccess.vue';
import GuestRegistrationForm from '@/Pages/Pos/Components/Cart/GuestRegistrationForm.vue';

const props = defineProps({
    allowGuest: Boolean,
    authMethods: Array,
    needsEmailFallback: Boolean,
    isSubmittingAuth: Boolean,
    subStep: { type: String, default: 'methods' },
    guestName: { type: String, default: '' },
    guestPhone: { type: String, default: '' }
});

const emit = defineEmits([
    'login',
    'open-register',
    'guest-registered',
    'update:sub-step',
    'update:guest-name',
    'update:guest-phone'
]);

const handleGuestAdvance = () => {
    // Se está no nome, avança para o telefone
    if (props.subStep === 'guest_name') {
        emit('update:sub-step', 'guest_phone');
    }
    // Se já está no telefone (ou em outro passo final), conclui o Auth e libera o fluxo
    else if (props.subStep === 'guest_phone') {
        emit('guest-registered');
    }
};
</script>
