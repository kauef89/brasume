<template>
    <AdaptiveModal
    v-model:visible="isVisible"
    class="p-6"
    >

        <!-- CABEÇALHO COMPACTO E MONOCROMÁTICO -->
        <div class="flex flex-col items-center justify-center gap-2 shrink-0 mb-4 mt-1">
            <div class="flex flex-col items-center text-center">
                <h2 class="text-base font-black text-surface-text leading-tight">Módulos da Operação</h2>
                <span class="text-[9px] font-bold uppercase tracking-widest text-surface-muted">Navegação Rápida</span>
            </div>
        </div>

        <!-- GRELHA COMPACTA -->
        <div class="grid grid-cols-3 gap-2 pb-2">

            <!-- GESTOR (Apenas se houver métricas de turno ativas) -->
            <button
                v-if="managerData"
                @click="handleSelect('manager')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-chart-line text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Gestor</span>
            </button>

            <!-- 🚀 TERMINAIS (Liberado para Gestores/Admins independentemente do caixa estar aberto) -->
            <button
                v-if="canManageFleet"
                @click="handleSelect('fleet')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-unlock text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Terminais</span>
            </button>

            <!-- REPOSIÇÃO (WMS PRATELEIRA) -->
            <button
                @click="handleSelect('restock')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none relative transition-all active:scale-95 cursor-pointer"
            >
                <span
                    v-if="restockCount > 0"
                    class="absolute top-1.5 right-1.5 bg-surface-text text-surface-1 text-[9px] font-black w-4 h-4 flex items-center justify-center rounded-full shadow-xs"
                >
                    {{ restockCount }}
                </span>
                <i class="pi pi-objects-column text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Reposição</span>
            </button>

            <!-- ESTOQUE / INVENTÁRIO (WMS GERAL) -->
            <button
                @click="handleSelect('inventory')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-box text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Estoque</span>
            </button>

            <!-- TAREFAS / CHECKLISTS -->
            <button
                @click="handleSelect('checklists')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-list-check text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Tarefas</span>
            </button>

            <!-- PRODUÇÃO (KDS / MRP) -->
            <button
                @click="handleSelect('production')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-clipboard text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Produção</span>
            </button>

            <!-- COMPRAS -->
            <button
                @click="handleSelect('shopping')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-shopping-bag text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Compras</span>
            </button>

            <!-- MOTOBOY -->
            <button
                @click="handleSelect('driver')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-truck text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Motoboy</span>
            </button>

            <!-- PERFIL / CARTEIRA -->
            <button
                @click="handleSelect('wallet')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-id-card text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Perfil</span>
            </button>

        </div>
    </AdaptiveModal>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

const props = defineProps({
    visible: { type: Boolean, required: true },
    managerData: { type: [Object, Boolean], default: false },
    restockCount: { type: Number, default: 0 }
});

const emit = defineEmits(['update:visible', 'select-tab']);

const page = usePage();

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

// 🛡️ Avaliação de perfil: Libera para gestores mesmo sem turno ativo
const canManageFleet = computed(() => {
    if (props.managerData) return true;

    const user = page.props.auth?.user;
    if (!user) return false;

    const role = (user.role || '').toLowerCase();
    const roles = Array.isArray(user.roles)
        ? user.roles.map(r => (typeof r === 'string' ? r : r.name || '').toLowerCase())
        : [];

    const authorizedRoles = ['admin', 'gerente', 'manager', 'owner', 'gestor'];
    return authorizedRoles.includes(role) || roles.some(r => authorizedRoles.includes(r));
});

const handleSelect = (tab) => {
    emit('select-tab', tab);
    isVisible.value = false;
};
</script>
