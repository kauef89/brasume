<template>
    <div class="flex flex-col gap-4 p-4 pb-32 animate-in fade-in zoom-in-95 duration-300">

        <!-- 👤 HEADER: IDENTIDADE DO COLABORADOR -->
        <div class="bg-surface-2 border border-surface-border rounded-[2rem] p-6 flex flex-col items-center text-center shadow-sm relative overflow-hidden">
            <!-- Background Decorativo -->
            <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-primary-500/10 to-transparent"></div>

            <div class="w-20 h-20 bg-surface-1 border-4 border-surface-2 text-primary-500 rounded-full flex items-center justify-center mb-3 text-2xl font-black shadow-md z-10 relative">
                {{ userInitials }}
            </div>

            <h2 class="text-xl font-black text-surface-text z-10">{{ userName }}</h2>
            <p class="text-xs font-medium text-surface-muted z-10">{{ userEmail }}</p>

            <div class="mt-4 px-4 py-1.5 bg-surface-1 rounded-xl border border-surface-border z-10">
                <span class="text-[10px] font-black uppercase tracking-widest text-surface-text">{{ userRole }}</span>
            </div>
        </div>

        <!-- 💳 CARTEIRA: CONSUMO INTERNO (ALLOWANCE) -->
        <div class="bg-surface-2 border border-surface-border rounded-[2rem] p-5 shadow-sm flex flex-col gap-4">
            <div class="flex items-center gap-3 border-b border-surface-border pb-3">
                <div class="w-10 h-10 rounded-full bg-primary-500/10 text-primary-500 flex items-center justify-center">
                    <i class="pi fa-wallet text-lg"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-black text-sm text-surface-text">Carteira Corporativa</span>
                    <span class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">Consumo de Funcionário</span>
                </div>
            </div>

            <!-- Saldo -->
            <div class="flex justify-between items-center bg-surface-1 p-4 rounded-2xl border border-surface-border">
                <div class="flex flex-col">
                    <span class="text-[10px] font-black text-surface-muted uppercase tracking-widest mb-1">Saldo Disponível</span>
                    <span class="text-2xl font-black text-primary-500">{{ formattedBalance }}</span>
                </div>
                <div class="w-12 h-12 rounded-full bg-surface-2 flex items-center justify-center text-surface-muted border border-surface-border">
                    <i class="pi pi-chart-pie text-xl"></i>
                </div>
            </div>

            <!-- Botão de Ação -->
            <button
                @click="$emit('startAllowanceOrder')"
                class="h-14 w-full bg-primary-500 text-white rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-3 hover:bg-primary-600 transition-colors shadow-md active:scale-95 outline-none border-none cursor-pointer mt-1"
            >
                <i class="pi pi-shopping-bag text-lg"></i>
                Lançar Consumo Interno
            </button>
        </div>

        <!-- ⚙️ CONFIGURAÇÕES E PREFERÊNCIAS -->
        <div class="flex flex-col gap-2 mt-2">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-surface-muted px-2 mb-1">Preferências</h3>

            <button
                @click="$emit('openSettings')"
                class="h-14 w-full bg-surface-2 border border-surface-border text-surface-text rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-between px-5 hover:bg-surface-3 transition-colors shadow-sm active:scale-95 outline-none cursor-pointer"
            >
                <span class="flex items-center gap-3">
                    <i class="pi pi-cog text-lg text-surface-muted"></i>
                    Aparência do App
                </span>
                <i class="pi pi-chevron-right text-surface-muted"></i>
            </button>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    employeeData: {
        type: Object,
        default: () => ({ balance: 0 })
    }
});

defineEmits(['startAllowanceOrder', 'openSettings']);

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

const userName = computed(() => user.value.name || 'Colaborador');
const userEmail = computed(() => user.value.email || 'equipe@brasume.com.br');

const userRole = computed(() => {
    if (user.value.roles && user.value.roles.length > 0) {
        return user.value.roles[0].replace('_', ' ');
    }
    return user.value.role || 'Operador';
});

const userInitials = computed(() => {
    const names = userName.value.split(' ');
    if (names.length >= 2) {
        return `${names[0][0]}${names[names.length - 1][0]}`.toUpperCase();
    }
    return userName.value.substring(0, 2).toUpperCase();
});

const formattedBalance = computed(() => {
    const balance = props.employeeData?.balance || 0;
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(balance);
});
</script>
