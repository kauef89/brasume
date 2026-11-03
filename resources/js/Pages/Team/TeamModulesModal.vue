<template>
    <div
        v-if="visible"
        class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200 select-none"
        @click.self="close"
    >
        <div
            class="w-full sm:max-w-md bg-surface-1 border border-surface-border rounded-t-3xl sm:rounded-3xl p-5 shadow-2xl flex flex-col gap-4 animate-in slide-in-from-bottom duration-300"
        >
            <!-- CABEÇALHO DO MODAL -->
            <div class="flex items-center justify-between border-b border-surface-border/50 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-primary-500/10 text-primary-500 flex items-center justify-center">
                        <i class="pi pi-th-large text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-surface-text leading-tight">Módulos da Operação</h3>
                        <p class="text-[11px] text-surface-muted leading-tight mt-0.5">Selecione o painel desejado</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="close"
                    class="w-8 h-8 rounded-full bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text flex items-center justify-center transition-colors cursor-pointer border-none"
                >
                    <i class="pi pi-times text-xs"></i>
                </button>
            </div>

            <!-- GRID DE MÓDULOS -->
            <div class="grid grid-cols-3 gap-2.5">
                <!-- 1. CARDÁPIO / PEDIDOS -->
                <button
                    type="button"
                    @click="navigate('menu')"
                    class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-surface-2 border border-surface-border hover:border-primary-500/60 transition-all cursor-pointer group"
                >
                    <div class="w-10 h-10 rounded-xl bg-primary-500/10 text-primary-500 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="pi pi-book text-base"></i>
                    </div>
                    <span class="text-[11px] font-bold text-surface-text">Cardápio</span>
                </button>

                <!-- 2. OPERAÇÕES / KDS COZINHA -->
                <button
                    type="button"
                    @click="navigate('operations')"
                    class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-surface-2 border border-surface-border hover:border-orange-500/60 transition-all cursor-pointer group"
                >
                    <div class="w-10 h-10 rounded-xl bg-orange-500/10 text-orange-500 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="pi pi-receipt text-base"></i>
                    </div>
                    <span class="text-[11px] font-bold text-surface-text">Cozinha</span>
                </button>

                <!-- 3. KDS BAR / COPA -->
                <button
                    type="button"
                    @click="navigate('bar')"
                    class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-surface-2 border border-surface-border hover:border-blue-500/60 transition-all cursor-pointer group"
                >
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="pi pi-bolt text-base"></i>
                    </div>
                    <span class="text-[11px] font-bold text-surface-text">Bar / Copa</span>
                </button>

                <!-- 4. REPOSIÇÃO (COM BADGE SE HOUVER ITENS) -->
                <button
                    type="button"
                    @click="navigate('restock')"
                    class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-surface-2 border border-surface-border hover:border-amber-500/60 transition-all cursor-pointer relative group"
                >
                    <span
                        v-if="restockCount > 0"
                        class="absolute top-2 right-2 px-1.5 py-0.5 rounded-full text-[9px] font-black bg-danger-500 text-white"
                    >
                        {{ restockCount }}
                    </span>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="pi pi-box text-base"></i>
                    </div>
                    <span class="text-[11px] font-bold text-surface-text">Reposição</span>
                </button>

                <!-- 5. LANCHE / VALE-ALIMENTAÇÃO -->
                <button
                    type="button"
                    @click="navigate('meal')"
                    class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-surface-2 border border-surface-border hover:border-emerald-500/60 transition-all cursor-pointer group"
                >
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="pi pi-apple text-base"></i>
                    </div>
                    <span class="text-[11px] font-bold text-surface-text">Lanche</span>
                </button>

                <!-- 6. CARTEIRA CORPORATIVA (VALES E CONSUMOS) -->
                <button
                    type="button"
                    @click="navigate('corporate_wallet')"
                    class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-surface-2 border border-surface-border hover:border-purple-500/60 transition-all cursor-pointer group"
                >
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="pi pi-wallet text-base"></i>
                    </div>
                    <span class="text-[11px] font-bold text-surface-text">Carteira</span>
                </button>

                <!-- 🛡️ 7. FROTA DE DISPOSITIVOS / CAIXA (Exibido apenas para Gerente ou Operador de Caixa escalado/ativo) -->
                <button
                    v-if="$page.props.canManageDevices"
                    type="button"
                    @click="navigate('devices')"
                    class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-surface-2 border border-primary-500/30 hover:border-primary-500 transition-all cursor-pointer group col-span-3 sm:col-span-1"
                >
                    <div class="w-10 h-10 rounded-xl bg-primary-500/15 text-primary-500 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="pi pi-desktop text-base"></i>
                    </div>
                    <span class="text-[11px] font-black text-surface-text">Dispositivos</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    visible: { type: Boolean, default: false },
    managerData: { type: Object, default: () => ({}) },
    restockCount: { type: Number, default: 0 }
});

const emit = defineEmits(['update:visible', 'select-tab']);

const close = () => {
    emit('update:visible', false);
};

const navigate = (tab) => {
    emit('select-tab', tab);
    close();
};
</script>
