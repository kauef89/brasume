<template>
    <div class="min-h-screen bg-surface-2 flex items-center justify-center p-4 font-sans text-surface-text select-none">
        <div class="max-w-md w-full bg-surface-1 rounded-3xl shadow-xl border border-surface-border overflow-hidden">
            <div class="p-8 text-center flex flex-col items-center">

                <!-- Ícone de Segurança -->
                <div class="w-16 h-16 bg-primary-500/10 text-primary-600 rounded-full flex items-center justify-center mb-6 shadow-inner">
                    <i class="pi fa-shield-halved text-3xl"></i>
                </div>

                <h1 class="text-2xl font-black mb-2">Configurar 2FA</h1>
                <p class="text-sm text-surface-muted mb-6 leading-relaxed">
                    Proteja a sua conta. Abra o <strong>Google Authenticator</strong> (ou Authy) no seu telemóvel e escaneie o código abaixo.
                </p>

                <!-- QR Code -->
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-6">
                    <qrcode-vue :value="qrCodeUrl" :size="200" level="H" class="mx-auto" />
                </div>

                <!-- Fallback Secret -->
                <div class="w-full bg-surface-2 border border-surface-border rounded-xl p-3 mb-8">
                    <p class="text-xs font-bold text-surface-muted uppercase tracking-wider mb-1">Não consegue escanear?</p>
                    <p class="font-mono text-sm text-primary-600 break-all select-all">{{ secret }}</p>
                </div>

                <!-- Formulário de Confirmação -->
                <form @submit.prevent="submit" class="w-full text-left">
                    <label class="block text-xs font-bold text-surface-muted uppercase tracking-widest mb-2 ml-1">
                        Código de Confirmação
                    </label>
                    <input
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        placeholder="000000"
                        class="w-full bg-surface-2 border border-surface-border text-surface-text rounded-2xl px-4 py-4 text-center text-3xl tracking-[0.5em] font-mono focus:ring-2 focus:ring-primary-500 outline-none transition-all mb-2"
                        required
                        autofocus
                    >
                    <span v-if="form.errors.code" class="text-red-500 text-xs font-bold text-center block mb-4">
                        {{ form.errors.code }}
                    </span>

                    <button
                        type="submit"
                        :disabled="form.processing || form.code.length < 6"
                        class="w-full mt-4 bg-primary-500 hover:bg-primary-600 text-white font-black text-sm uppercase tracking-widest py-4 rounded-2xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <i v-if="form.processing" class="pi fa-spinner fa-spin"></i>
                        <span>Confirmar e Ativar</span>
                    </button>
                </form>

            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import QrcodeVue from 'qrcode.vue';

const props = defineProps({
    qrCodeUrl: { type: String, required: true },
    secret: { type: String, required: true }
});

const form = useForm({
    code: ''
});

const submit = () => {
    form.post(route('auth.2fa.verify'), {
        preserveScroll: true,
        onError: () => form.reset('code'),
    });
};
</script>
