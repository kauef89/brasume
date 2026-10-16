<template>
    <div class="flex flex-col bg-surface-1/60 rounded-3xl items-center justify-center text-center p-8 mt-4 mx-4 border border-surface-border/50">
        <div class="w-20 h-20 bg-surface-2/60 rounded-full flex items-center justify-center mb-6">
            <i :class="icon" class="text-3xl text-surface-muted"></i>
        </div>
        <h2 class="text-xl font-black text-surface-text mb-2">{{ title }}</h2>
        <p class="text-sm font-medium text-surface-muted mb-8 leading-relaxed max-w-xs mx-auto">
            {{ description }}
        </p>

        <div class="flex flex-col gap-3 w-full max-w-xs mx-auto">
            <button @click="$emit('open-login')" class="w-full h-12 rounded-full bg-primary-500 text-white font-bold transition-transform active:scale-95 hover:bg-primary-600 outline-none border-none cursor-pointer flex items-center justify-center gap-2 shadow-sm">
                <i class="pi pi-sign-in"></i> Entrar ou Criar Conta
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, default: 'Área do Cliente' },
    description: { type: String, default: 'Você não está autenticado. Entre para gerenciar seus endereços, ver o histórico e muito mais!' },
    icon: { type: String, default: 'pi pi-user' }
});
defineEmits(['open-login']);
</script>
