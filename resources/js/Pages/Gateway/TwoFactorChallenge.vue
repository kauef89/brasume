<template>
    <div class="min-h-screen bg-surface-2 flex items-center justify-center p-4 font-sans text-surface-text select-none">
        <div class="max-w-md w-full bg-surface-1 rounded-3xl shadow-xl border border-surface-border overflow-hidden relative">

            <!-- Barra de Progresso Superior (Estética) -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary-400 to-primary-600"></div>

            <div class="p-8 text-center flex flex-col items-center">

                <div class="w-20 h-20 bg-primary-500/10 text-primary-600 rounded-full flex items-center justify-center mb-6 shadow-inner relative">
                    <i class="pi pi-lock text-4xl"></i>
                    <!-- Insígnia de Segurança -->
                    <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center border-2 border-surface-1">
                        <i class="pi pi-check text-[10px]"></i>
                    </div>
                </div>

                <h1 class="text-2xl font-black mb-2">Verificação em 2 Etapas</h1>
                <p class="text-sm text-surface-muted mb-8 leading-relaxed px-4">
                    Abra o seu aplicativo de autenticação e introduza o código de 6 dígitos para aceder ao painel.
                </p>

                <form @submit.prevent="submit" class="w-full text-left">
                    <div class="relative mb-2">
                        <input
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            placeholder="••••••"
                            class="w-full bg-surface-2 border border-surface-border text-surface-text rounded-2xl px-4 py-5 text-center text-4xl tracking-[0.5em] font-mono focus:ring-2 focus:ring-primary-500 outline-none transition-all placeholder:text-surface-border"
                            required
                            autofocus
                        >
                    </div>

                    <div class="h-6 flex items-center justify-center mb-4">
                        <span v-if="form.errors.code" class="text-red-500 text-xs font-bold animate-in fade-in slide-in-from-bottom-1">
                            {{ form.errors.code }}
                        </span>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || form.code.length < 6"
                        class="w-full bg-primary-500 hover:bg-primary-600 text-white font-black text-sm uppercase tracking-widest py-4 rounded-2xl transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 shadow-lg shadow-primary-500/30"
                    >
                        <i v-if="form.processing" class="pi fa-spinner fa-spin"></i>
                        <span>Validar Acesso</span>
                    </button>
                </form>

                <!-- Voltar / Cancelar (Opcional) -->
                <button @click="logout" class="mt-8 text-xs font-bold text-surface-muted hover:text-red-500 transition-colors uppercase tracking-widest outline-none">
                    Cancelar e Sair
                </button>

            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';

const form = useForm({
    code: ''
});

const submit = () => {
    form.post(route('auth.2fa.verify'), {
        preserveScroll: true,
        onError: () => form.reset('code'),
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>
