<template>
    <form @submit.prevent="submitForm" class="flex flex-col gap-4">

        <!-- 🚀 1. CONFIGURAÇÃO PRINCIPAL -->
        <div class="flex flex-col gap-3">
            <span class="text-xs font-black text-surface-text border-b border-surface-border pb-1">Configuração Principal</span>

            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Código do Cupom</label>
                <div class="flex gap-2">
                    <input v-model="form.code" type="text" placeholder="Ex: NATAL10" class="flex-1 h-11 px-4 rounded-xl bg-surface-1 border border-surface-border text-sm font-bold uppercase placeholder:normal-case focus:border-primary-500 outline-none" required />
                    <button type="button" @click="generateRandomCode" class="h-11 px-4 rounded-xl bg-surface-2 border border-surface-border text-primary-500 hover:bg-primary-500/10 transition-colors outline-none cursor-pointer flex items-center justify-center" title="Gerar código aleatório">
                        <i class="pi pi-sparkles"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Tipo de Desconto</label>
                    <select v-model="form.type" class="w-full h-11 px-3 rounded-xl bg-surface-1 border border-surface-border text-sm font-medium focus:border-primary-500 outline-none">
                        <option value="fixed">Fixo (R$)</option>
                        <option value="percent">Porcentagem (%)</option>
                        <option value="free_delivery">Entrega Grátis</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1" v-if="form.type !== 'free_delivery'">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Valor</label>
                    <input v-model="form.value" type="number" step="0.01" min="0" placeholder="Ex: 10.00" class="w-full h-11 px-4 rounded-xl bg-surface-1 border border-surface-border text-sm font-bold focus:border-primary-500 outline-none" :required="form.type !== 'free_delivery'" />
                </div>
            </div>
        </div>

        <!-- 🚀 2. REGRAS DE EXCLUSIVIDADE E LIMITES -->
        <div class="flex flex-col gap-3 mt-2">
            <span class="text-xs font-black text-surface-text border-b border-surface-border pb-1">Regras e Limites</span>

            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Mínimo no Carrinho (R$)</label>
                    <input v-model="form.min_subtotal" type="number" step="0.01" min="0" class="w-full h-11 px-4 rounded-xl bg-surface-1 border border-surface-border text-sm font-medium focus:border-primary-500 outline-none" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Limite por Cliente</label>
                    <input v-model="form.user_usage_limit" type="number" min="0" class="w-full h-11 px-4 rounded-xl bg-surface-1 border border-surface-border text-sm font-medium focus:border-primary-500 outline-none" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Limite Global (0=Ilimitado)</label>
                    <input v-model="form.global_usage_limit" type="number" min="0" class="w-full h-11 px-4 rounded-xl bg-surface-1 border border-surface-border text-sm font-medium focus:border-primary-500 outline-none" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Limite Mensal</label>
                    <input v-model="form.monthly_usage_limit" type="number" min="0" class="w-full h-11 px-4 rounded-xl bg-surface-1 border border-surface-border text-sm font-medium focus:border-primary-500 outline-none" />
                </div>
            </div>
        </div>

        <!-- 🚀 3. VALIDADE E RESTRIÇÕES -->
        <div class="flex flex-col gap-3 mt-2">
            <span class="text-xs font-black text-surface-text border-b border-surface-border pb-1">Validade e Restrições</span>

            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Início (Opcional)</label>
                    <input v-model="form.starts_at" type="datetime-local" class="w-full h-11 px-3 rounded-xl bg-surface-1 border border-surface-border text-[11px] font-medium focus:border-primary-500 outline-none" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Vencimento (Opcional)</label>
                    <input v-model="form.expires_at" type="datetime-local" class="w-full h-11 px-3 rounded-xl bg-surface-1 border border-surface-border text-[11px] font-medium focus:border-primary-500 outline-none" />
                </div>
            </div>

            <!-- Toggles (Primeira Compra e Ativo) -->
            <div class="flex flex-col gap-3 mt-2 bg-surface-2 p-3 rounded-xl border border-surface-border">
                <label class="relative flex items-center justify-between cursor-pointer">
                    <span class="text-xs font-bold text-surface-text">Apenas Primeira Compra?</span>
                    <input type="checkbox" v-model="form.first_order_only" class="sr-only peer">
                    <div class="w-9 h-5 bg-surface-3 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[18px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary-500"></div>
                </label>

                <hr class="border-surface-border/50" />

                <label class="relative flex items-center justify-between cursor-pointer">
                    <span class="text-xs font-bold text-surface-text">Cupom Ativo?</span>
                    <input type="checkbox" v-model="form.is_active" class="sr-only peer">
                    <div class="w-9 h-5 bg-surface-3 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[18px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-success-500"></div>
                </label>
            </div>
        </div>

        <button type="submit" :disabled="isSubmitting" class="w-full mt-4 h-12 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-widest transition-colors shadow-sm outline-none disabled:opacity-50">
            <i class="pi pi-spinner pi-spin mr-2" v-if="isSubmitting"></i>
            {{ isSubmitting ? 'Salvando...' : 'Criar Cupom' }}
        </button>
    </form>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({ isSubmitting: Boolean });
const emit = defineEmits(['submit']);

const getInitialForm = () => ({
    code: '',
    type: 'fixed',
    value: '',
    min_subtotal: 0,
    global_usage_limit: 0,
    monthly_usage_limit: 0,
    user_usage_limit: 1,
    starts_at: '',
    expires_at: '',
    first_order_only: false,
    is_active: true
});

const form = ref(getInitialForm());

// 🚀 A magia de gerar código aleatório do Filament, agora no Vue!
const generateRandomCode = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < 8; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    form.value.code = result;
};

const submitForm = () => {
    emit('submit', { ...form.value });
    form.value = getInitialForm();
};
</script>
