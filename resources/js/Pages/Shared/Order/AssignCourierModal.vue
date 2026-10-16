<template>
    <component
        :is="mode === 'inline' ? 'div' : AdaptiveModal"
        v-model:visible="isVisible"
        class="flex flex-col h-full w-full"
        :class="mode === 'inline' ? 'absolute inset-0 bg-surface-2 p-6' : ''"
    >
        <!-- CABEÇALHO -->
        <div class="flex items-center gap-3 mb-6 shrink-0" :class="mode === 'inline' ? '' : 'mt-2'">
            <button v-if="mode === 'inline'" @click="handleCancel" class="w-10 h-10 rounded-full bg-surface-1 border border-surface-border text-surface-muted hover:text-surface-text flex items-center justify-center transition-colors outline-none cursor-pointer shrink-0">
                <i class="pi pi-arrow-left text-sm font-bold"></i>
            </button>
            <div v-else class="w-10 h-10 rounded-xl bg-primary-500/10 text-primary-600 flex items-center justify-center shrink-0">
                <i class="pi pi-motorcycle text-xl"></i>
            </div>

            <div class="flex flex-col">
                <h2 class="text-xl font-black text-surface-text leading-tight">Atribuir Entregador</h2>
                <span v-if="mode === 'inline'" class="text-[10px] font-black uppercase tracking-widest text-primary-500 mt-0.5">Vincular ao Pedido</span>
            </div>
        </div>

        <!-- LISTA DE ENTREGADORES ATIVOS NO TURNO -->
        <div class="flex flex-col gap-2 flex-1 overflow-y-auto pr-1 hide-scrollbar" :class="mode === 'inline' ? '' : 'max-h-[50vh]'">
            <button
                v-for="courier in couriers"
                :key="courier.id"
                @click="handleAssign(courier)"
                class="p-4 border border-surface-border rounded-xl flex items-center justify-between hover:bg-primary-500/10 hover:border-primary-500/30 transition-colors text-left outline-none cursor-pointer"
                :class="mode === 'inline' ? 'bg-surface-1' : 'bg-surface-2'"
            >
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                         :class="order?.driver_id === courier.id ? 'bg-primary-500 text-white shadow-sm' : 'bg-surface-2 border border-surface-border text-surface-muted'">
                        <i class="pi pi-motorcycle text-sm"></i>
                    </div>
                    <div class="flex flex-col flex-1">
                        <span class="font-black text-sm text-surface-text">{{ abbreviateName(courier.name) }}</span>
                        <span v-if="mode === 'inline'" class="text-[10px] font-bold text-surface-muted">{{ courier.phone || 'Sem número' }}</span>
                    </div>
                </div>
                <i v-if="order?.driver_id === courier.id" class="pi pi-check text-primary-500 text-lg"></i>
            </button>

            <div v-if="couriers.length === 0" class="text-center p-4 rounded-xl text-xs font-bold"
                 :class="mode === 'inline' ? 'bg-warning-500/10 text-warning-600 border border-warning-500/20' : 'bg-surface-2 text-surface-muted'">
                Nenhum entregador no turno ativo.
            </div>
        </div>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";
import { useWhatsAppMessages } from "@/Composables/useWhatsAppMessages";

const props = defineProps({
    visible: Boolean,
    order: Object,
    couriers: { type: Array, default: () => [] },
    mode: { type: String, default: 'modal' }
});

const emit = defineEmits(['update:visible', 'cancel', 'assign']);
const { abbreviateName } = useWhatsAppMessages();

const isVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val)
});

const handleCancel = () => {
    emit('cancel');
    if (props.mode !== 'inline') isVisible.value = false;
};

const handleAssign = (courier) => {
    emit('assign', courier);
    if (props.mode !== 'inline') isVisible.value = false;
};
</script>
