<template>
    <div
        class="bg-surface-3/50 p-2.5 rounded-3xl transition-all flex items-start justify-between relative group gap-3 min-h-28"
    >
        <!-- SCRIM DE CONFIRMAÇÃO (DESKTOP: BOTÃO GRANDE | MOBILE: SLIDE TO CONFIRM) -->
        <div
            v-if="isConfirmingOutOfStock"
            class="absolute inset-0 z-30 bg-surface-2/95 dark:bg-surface-1/95 backdrop-blur-md p-2 rounded-3xl flex items-center justify-between gap-2 animate-in fade-in zoom-in-95 duration-150"
        >
            <div class="hidden md:flex items-center justify-between w-full h-full gap-2 px-1">
                <div class="flex items-center gap-1.5 min-w-0">
                    <i class="pi pi-exclamation-triangle text-red-500 text-xs shrink-0 animate-pulse"></i>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] font-black text-red-500 uppercase tracking-wider truncate leading-tight">Zerar Estoque?</span>
                        <span class="text-[8px] text-surface-muted truncate">Pausa pratos vinculados</span>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <button
                        type="button"
                        @click.stop="executeOutOfStock"
                        class="h-9 px-4 bg-red-600 hover:bg-red-700 active:scale-95 text-white rounded-xl font-black text-[11px] uppercase tracking-wider flex items-center gap-1.5 shadow-md shadow-red-500/20 border-none cursor-pointer transition-all"
                    >
                        <i class="pi pi-check text-xs"></i> Confirmar
                    </button>
                    <button
                        type="button"
                        @click.stop="isConfirmingOutOfStock = false"
                        class="h-9 w-9 bg-surface-3 hover:bg-surface-border text-surface-muted hover:text-surface-text rounded-xl flex items-center justify-center cursor-pointer transition-colors border-none"
                        title="Cancelar"
                    >
                        <i class="pi pi-times text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="flex md:hidden items-center justify-between w-full h-full gap-1.5">
                <div class="flex-1 min-w-0">
                    <SlideToConfirm
                        text="Zerar"
                        success-text="Esgotado!"
                        color="danger"
                        icon="pi-trash"
                        format="long"
                        @confirm="executeOutOfStock"
                    />
                </div>
                <button
                    type="button"
                    @click.stop="isConfirmingOutOfStock = false"
                    class="w-10 h-10 shrink-0 bg-surface-3 hover:bg-surface-border text-surface-muted hover:text-surface-text rounded-full flex items-center justify-center cursor-pointer transition-colors border-none"
                    title="Cancelar"
                >
                    <i class="pi pi-times text-xs font-bold"></i>
                </button>
            </div>
        </div>

        <!-- COLUNA 1: Textos e Informações -->
        <div class="flex flex-col flex-1 min-w-0">
            <span
                class="text-[9px] font-black uppercase tracking-widest leading-tight truncate"
                :class="statusType === 'available' ? 'text-surface-muted' : (statusType === 'paused' ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400')"
            >
                {{ item.category?.name || 'Sem Categoria' }}
            </span>

            <h3
                class="font-black text-xs md:text-sm leading-snug line-clamp-2 mt-0.5"
                :class="statusType === 'available' ? 'text-surface-text' : (statusType === 'paused' ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-300')"
            >
                {{ item.name }}
            </h3>

            <!-- Estoque atual -->
            <div v-if="item.track_inventory" class="flex items-center gap-1.5 mt-1">
                <span
                    class="inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded-md leading-none border"
                    :class="statusType === 'out_of_stock'
                        ? 'bg-red-500/10 text-red-600 border-red-500/20'
                        : 'bg-surface-2 text-surface-muted border-surface-border/50'"
                >
                    <i class="pi pi-box text-[9px] mr-1"></i>
                    {{ item.current_stock }} {{ item.unit_of_measure }}
                </span>
            </div>
        </div>

        <!-- COLUNA 2: Ações de Disponibilidade -->
        <div class="shrink-0 flex items-center justify-end relative">

            <!-- ESTADO B: Input Rápido de Reabastecimento (Ao clicar no Verde com saldo 0) -->
            <div v-if="isPromptingStock" class="flex flex-col items-center gap-1 animate-in fade-in zoom-in duration-150">
                <input
                    type="number"
                    v-model.number="newStockValue"
                    class="w-14 h-6 text-xs font-black text-center rounded-md border bg-surface-2 text-surface-text focus:border-primary-500 px-1 outline-none"
                    placeholder="Qtd"
                    @keyup.enter="confirmRestock"
                    autofocus
                >
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        @click="confirmRestock"
                        class="w-6 h-6 bg-emerald-500 hover:bg-emerald-600 text-white rounded-md flex items-center justify-center transition-colors cursor-pointer border-none shadow-sm"
                        title="Confirmar saldo"
                    >
                        <i class="pi pi-check text-[9px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="isPromptingStock = false"
                        class="w-6 h-6 bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text rounded-md flex items-center justify-center transition-colors cursor-pointer border border-surface-border/50"
                        title="Cancelar"
                    >
                        <i class="pi pi-times text-[9px]"></i>
                    </button>
                </div>
            </div>

            <!-- ESTADO A: CHIP RETRÁTIL ELEGANTE (Alongamento Vertical) -->
            <div v-else class="relative flex items-center justify-center">

                <div
                    v-if="isExpanded"
                    class="fixed inset-0 z-10"
                    @click.stop="isExpanded = false"
                ></div>

                <div
                    class="transition-all duration-200 ease-out z-20 flex flex-col items-center rounded-full"
                    :class="isExpanded
                        ? 'absolute top-0 right-0 bg-surface-3 p-2 gap-2 shadow-lg'
                        : 'relative p-2'"
                >
                    <button
                        type="button"
                        @click.stop="toggleExpand"
                        class="w-5 h-5 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none shadow-xs active:scale-95"
                        :class="currentStatusObj.color"
                        :title="`${currentStatusObj.label} (Clique para alterar)`"
                    >
                        <i class="pi text-[8px] font-black" :class="currentStatusObj.icon"></i>
                    </button>

                    <template v-if="isExpanded">
                        <button
                            v-for="opt in otherStatusOptions"
                            :key="opt.key"
                            type="button"
                            @click.stop="selectStatus(opt.key)"
                            class="w-5 h-5 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none opacity-85 hover:opacity-100 hover:scale-110 active:scale-95 shadow-xs animate-in fade-in slide-in-from-top-1 duration-150"
                            :class="opt.color"
                            :title="opt.label"
                        >
                            <i class="pi text-[8px] font-black" :class="opt.icon"></i>
                        </button>
                    </template>
                </div>

            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';

const props = defineProps({ item: Object });
const emit = defineEmits(['updateStatus']);

const isExpanded = ref(false);
const isPromptingStock = ref(false);
const isConfirmingOutOfStock = ref(false);
const newStockValue = ref('');

const statusOptions = [
    {
        key: 'available',
        label: 'Disponível',
        icon: 'pi-check',
        color: 'bg-emerald-500 text-white ring-1 ring-emerald-500/40 hover:bg-emerald-600'
    },
    {
        key: 'paused',
        label: 'Pausado',
        icon: 'pi-pause',
        color: 'bg-amber-500 text-white ring-1 ring-amber-500/40 hover:bg-amber-600'
    },
    {
        key: 'out_of_stock',
        label: 'Esgotado',
        icon: 'pi-times',
        color: 'bg-red-500 text-white ring-1 ring-red-500/40 hover:bg-red-600'
    }
];

const statusType = computed(() => {
    if (props.item.status === 'paused') return 'paused';

    if (props.item.track_inventory && parseFloat(props.item.current_stock) <= 0) {
        return 'out_of_stock';
    }

    if (props.item.status === 'out_of_stock') return 'out_of_stock';

    return 'available';
});

const currentStatusObj = computed(() => {
    return statusOptions.find(opt => opt.key === statusType.value) || statusOptions[0];
});

const otherStatusOptions = computed(() => {
    return statusOptions.filter(opt => opt.key !== statusType.value);
});

const toggleExpand = () => {
    isExpanded.value = !isExpanded.value;
};

const selectStatus = (targetStatus) => {
    isExpanded.value = false;

    if (targetStatus === 'available' && props.item.track_inventory && parseFloat(props.item.current_stock) <= 0) {
        newStockValue.value = props.item.par_level || props.item.max_stock || 1;
        isPromptingStock.value = true;
        isConfirmingOutOfStock.value = false;
        return;
    }

    if (targetStatus === 'out_of_stock' && props.item.track_inventory && parseFloat(props.item.current_stock) > 0) {
        isConfirmingOutOfStock.value = true;
        isPromptingStock.value = false;
        return;
    }

    emit('updateStatus', {
        item: props.item,
        action: targetStatus,
        new_stock: targetStatus === 'out_of_stock' ? 0 : null
    });
};

const executeOutOfStock = () => {
    isConfirmingOutOfStock.value = false;
    emit('updateStatus', {
        item: props.item,
        action: 'out_of_stock',
        new_stock: 0
    });
};

const confirmRestock = () => {
    if (newStockValue.value === '' || newStockValue.value === null || newStockValue.value < 0) return;

    emit('updateStatus', {
        item: props.item,
        action: 'available',
        new_stock: Number(newStockValue.value)
    });

    isPromptingStock.value = false;
};
</script>
