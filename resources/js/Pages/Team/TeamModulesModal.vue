<template>
    <AdaptiveModal
        v-model:visible="isVisible"
        class="p-6"
    >
        <!-- CABEÇALHO COMPACTO E MONOCROMÁTICO -->
        <div class="flex flex-col items-center justify-center gap-2 shrink-0 mb-4 mt-1 select-none">
            <div class="flex flex-col items-center text-center">
                <h2 class="text-base font-black text-surface-text leading-tight">Módulos da Operação</h2>
                <span class="text-[9px] font-bold uppercase tracking-widest text-surface-muted">Navegação Rápida</span>
            </div>
        </div>

        <!-- GRELHA COMPACTA (3 COLUNAS) -->
        <div class="grid grid-cols-3 gap-2 pb-2 select-none">

            <!-- 1. GESTOR (Visível se houver métricas ou permissão de frota) -->
            <button
                v-if="canManageFleet || managerData"
                type="button"
                @click="handleSelect('manager')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none transition-all active:scale-95 cursor-pointer border border-surface-border/40"
            >
                <i class="pi pi-chart-line text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Gestor</span>
            </button>

            <!-- 2. TERMINAIS (Gestão e/ou Caixa escalado/ativo) -->
            <button
                v-if="canManageFleet"
                type="button"
                @click="handleSelect('fleet')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none transition-all active:scale-95 cursor-pointer border border-primary-500/30"
            >
                <i class="pi pi-desktop text-xl text-primary-smart"></i>
                <span class="text-[10px] font-black uppercase tracking-wider text-primary-smart">Terminais</span>
            </button>

            <!-- 3. REPOSIÇÃO (WMS PRATELEIRA) -->
            <button
                type="button"
                @click="handleSelect('restock')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none relative transition-all active:scale-95 cursor-pointer border border-surface-border/40"
            >
                <span
                    v-if="restockCount > 0"
                    class="absolute top-1.5 right-1.5 bg-danger-500 text-white text-[9px] font-black w-4 h-4 flex items-center justify-center rounded-full shadow-xs"
                >
                    {{ restockCount }}
                </span>
                <i class="pi pi-objects-column text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Reposição</span>
            </button>

            <!-- 4. ESTOQUE / INVENTÁRIO (WMS GERAL) -->
            <button
                type="button"
                @click="handleSelect('inventory')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none transition-all active:scale-95 cursor-pointer border border-surface-border/40"
            >
                <i class="pi pi-box text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Estoque</span>
            </button>

            <!-- 5. TAREFAS / CHECKLISTS -->
            <button
                type="button"
                @click="handleSelect('checklists')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none transition-all active:scale-95 cursor-pointer border border-surface-border/40"
            >
                <i class="pi pi-list-check text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Tarefas</span>
            </button>

            <!-- 6. PRODUÇÃO (KDS / MRP) -->
            <button
                type="button"
                @click="handleSelect('production')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none transition-all active:scale-95 cursor-pointer border border-surface-border/40"
            >
                <i class="pi pi-clipboard text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Produção</span>
            </button>

            <!-- 7. COMPRAS -->
            <button
                type="button"
                @click="handleSelect('shopping')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none transition-all active:scale-95 cursor-pointer border border-surface-border/40"
            >
                <i class="pi pi-shopping-bag text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Compras</span>
            </button>

            <!-- 8. MOTOBOY -->
            <button
                type="button"
                @click="handleSelect('driver')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none transition-all active:scale-95 cursor-pointer border border-surface-border/40"
            >
                <i class="pi pi-truck text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Motoboy</span>
            </button>

            <!-- 9. PERFIL / CARTEIRA -->
            <button
                type="button"
                @click="handleSelect('wallet')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 hover:bg-surface-2 text-surface-text outline-none transition-all active:scale-95 cursor-pointer border border-surface-border/40"
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

const canManageFleet = computed(() => {
    return Boolean(page.props.canManageDevices);
});

const handleSelect = (tab) => {
    emit('select-tab', tab);
    isVisible.value = false;
};
</script>
