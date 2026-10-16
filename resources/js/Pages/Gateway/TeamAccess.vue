<template>
    <div class="flex flex-col gap-2 transition-all duration-300">
        <button v-if="!showTeamOptions" @click="showTeamOptions = true" class="w-full h-14 rounded-2xl bg-surface-2 border border-surface-border text-surface-muted font-medium hover:text-surface-text transition-colors outline-none">
            Acesso para Equipa
        </button>

        <div v-else class="flex flex-col gap-3">
            <div v-if="!showOperationAuth" class="grid grid-cols-2 gap-3 animate-in fade-in zoom-in-95">
                <button @click="showOperationAuth = true" class="h-28 rounded-2xl bg-primary-500/10 text-primary-600 hover:bg-primary-500/20 border border-primary-500/20 flex flex-col items-center justify-center gap-3 transition-colors outline-none">
                    <i class="pi pi-desktop text-3xl"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Operação</span>
                </button>

                <a href="/admin" class="h-28 rounded-2xl bg-surface-3 text-surface-text hover:bg-surface-border border border-surface-border/50 flex flex-col items-center justify-center gap-3 transition-colors outline-none no-underline cursor-pointer">
                    <i class="pi pi-chart-bar text-3xl"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Gestão</span>
                </a>
            </div>

            <div v-else class="flex flex-col gap-3 animate-in slide-in-from-right-4 duration-300">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary-500">Login Operacional</span>
                    <button @click="showOperationAuth = false" class="micro-label hover:text-surface-text outline-none">
                        <i class="pi pi-arrow-left"></i> Voltar
                    </button>
                </div>

                <button v-if="methods.includes('pin')" @click="$emit('open-pin')" class="w-full h-14 rounded-2xl bg-purple-500/10 text-purple-600 border border-purple-500/20 flex items-center justify-center font-black gap-3 hover:bg-purple-500/20 transition-colors outline-none">
                    <i class="pi pi-users text-lg"></i> Entrar com PIN
                </button>

                <a v-if="methods.includes('google')" :href="route('google.redirect', { intent: 'operation' })" class="w-full h-14 rounded-2xl bg-surface-2 border border-surface-border text-surface-text flex items-center justify-center font-medium gap-3 hover:bg-surface-3 transition-colors no-underline outline-none">
                    <i class="pi pi-google text-primary-500"></i> Entrar com Google
                </a>

                <form @submit.prevent="$emit('login', form)" v-if="needsEmailFallback" class="flex flex-col gap-2 mt-1">
                    <input v-model="form.email" type="email" autocomplete="username" required placeholder="E-mail Corporativo" class="form-input" />
                    <input v-model="form.password" type="password" autocomplete="current-password" required placeholder="Senha" class="form-input" />
                    <button type="submit" :disabled="isLoggingIn" class="w-full h-12 rounded-xl bg-primary-500 text-white font-black uppercase tracking-widest text-xs mt-1 flex items-center justify-center gap-2 outline-none disabled:opacity-50">
                        <i v-if="isLoggingIn" class="pi pi-spinner pi-spin"></i>
                        {{ isLoggingIn ? 'Entrando...' : 'Acessar Operação' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    methods: Array,
    needsEmailFallback: Boolean,
    isLoggingIn: Boolean
});

defineEmits(['open-pin', 'login']);

const showTeamOptions = ref(false);
const showOperationAuth = ref(false);
const form = ref({ email: '', password: '' });
</script>
