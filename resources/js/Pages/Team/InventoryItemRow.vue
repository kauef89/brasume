<template>
    <div
        class="flex flex-col transition-colors relative"
        :class="[
            isExpanded ? 'bg-surface-3/40 ring-1 ring-primary-500/30' : '',
            isTouched && !isExpanded ? 'bg-success-500/5' : ''
        ]"
    >
        <!-- MODO INVENTÁRIO -->
        <template v-if="activeMode === 'inventory'">
            <!-- CABEÇALHO COMPACTO -->
            <div
                @click="$emit('toggle-expand')"
                class="p-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-surface-1/40 active:bg-surface-1/60 transition-colors"
            >
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-surface-text text-sm leading-tight truncate">
                        {{ item.name }}
                    </h4>
                    <span class="text-[10px] text-surface-muted truncate block mt-0.5">
                        {{ item.category?.name || 'Geral' }}
                    </span>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <template v-if="isTouched">
                        <div class="flex flex-col items-end">
                            <span class="text-xs font-black text-surface-text flex items-center gap-1">
                                {{ displayValue }}
                                <span class="text-[9px] font-bold text-surface-muted">{{ item.unit_of_measure }}</span>
                            </span>
                            <span
                                class="text-[9px] font-black uppercase tracking-wider"
                                :class="delta < 0 ? 'text-danger-500' : (delta > 0 ? 'text-emerald-500' : 'text-surface-muted')"
                            >
                                {{ deltaLabel }}
                            </span>
                        </div>
                        <div class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-[10px] font-black">
                            <i class="pi pi-check"></i>
                        </div>
                    </template>

                    <template v-else>
                        <span class="px-2.5 py-1 rounded-lg bg-surface-3 text-surface-muted text-xs font-black border border-surface-border/50">
                            --
                        </span>
                        <i
                            class="pi pi-chevron-down text-[10px] text-surface-muted/60 transition-transform duration-200"
                            :class="{ 'rotate-180 text-primary-smart': isExpanded }"
                        ></i>
                    </template>
                </div>
            </div>

            <!-- CORPO EXPANDIDO -->
            <div
                v-if="isExpanded"
                class="px-3 pb-3.5 pt-1 border-t border-surface-border/40 flex flex-col gap-3 bg-surface-1/40 animate-in fade-in slide-in-from-top-1 duration-150"
            >
                <!-- Saldo Esperado -->
                <div class="flex items-center justify-between p-2 rounded-xl bg-surface-2 border border-surface-border/60 text-xs">
                    <div class="flex flex-col">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-surface-muted">Esperado neste ponto</span>
                        <span class="text-sm font-black text-surface-text">
                            {{ Number(item.local_expected_stock ?? 0) }}
                            <span class="text-[10px] font-bold text-surface-muted">{{ item.unit_of_measure }}</span>
                        </span>
                    </div>

                    <div v-if="item.total_system_stock !== item.local_expected_stock" class="flex flex-col text-right">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-surface-muted">Total na loja</span>
                        <span class="text-xs font-bold text-surface-muted">
                            {{ Number(item.total_system_stock) }} {{ item.unit_of_measure }}
                        </span>
                    </div>
                </div>

                <!-- Input e Controles -->
                <div class="flex flex-col gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-surface-muted">
                        Quantidade física contada:
                    </span>

                    <div class="flex items-center gap-2">
                        <div class="flex-1 flex items-center justify-between rounded-xl bg-surface-2 border border-surface-border h-11 p-1 shadow-inner">
                            <button
                                type="button"
                                @click.stop="decrement"
                                class="w-9 h-9 rounded-lg flex items-center justify-center text-surface-text hover:bg-surface-3 active:scale-90 transition-all border-none bg-transparent cursor-pointer"
                            >
                                <i class="pi pi-minus text-xs font-black"></i>
                            </button>

                            <input
                                type="text"
                                inputmode="numeric"
                                v-model="localInput"
                                class="flex-1 text-center bg-transparent border-none text-base font-black outline-none text-surface-text"
                                placeholder="Digite a qtd"
                                @keyup.enter="handleConfirm"
                                autofocus
                            />

                            <button
                                type="button"
                                @click.stop="increment"
                                class="w-9 h-9 rounded-lg flex items-center justify-center text-surface-text hover:bg-primary-500/10 active:scale-90 transition-all border-none bg-transparent cursor-pointer"
                            >
                                <i class="pi pi-plus text-xs font-black"></i>
                            </button>
                        </div>

                        <!-- Multiplicador de Fardo -->
                        <button
                            v-if="item.conversion_factor && item.conversion_factor > 1"
                            type="button"
                            @click.stop="addPack(item.conversion_factor)"
                            class="h-11 px-3 rounded-xl bg-surface-3 hover:bg-surface-border text-surface-text border border-surface-border text-[11px] font-black uppercase tracking-wider flex items-center gap-1 cursor-pointer transition-all active:scale-95 shrink-0"
                        >
                            <i class="pi pi-box text-xs text-amber-500"></i>
                            <span>+1 Fd ({{ item.conversion_factor }})</span>
                        </button>
                    </div>
                </div>

                <!-- Ações do Card -->
                <div class="flex items-center gap-2 pt-1">
                    <button
                        type="button"
                        @click.stop="$emit('match-expected')"
                        class="h-10 px-3 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text border border-surface-border text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                    >
                        <i class="pi pi-copy text-xs"></i>
                        <span>Bater c/ Sistema ({{ Number(item.local_expected_stock ?? 0) }})</span>
                    </button>

                    <button
                        type="button"
                        @click.stop="handleConfirm"
                        class="flex-1 h-10 px-4 rounded-xl bg-primary-500 hover:bg-primary-600 active:scale-95 text-white font-black text-xs uppercase tracking-wider shadow-md border-none cursor-pointer flex items-center justify-center gap-1.5 transition-all"
                    >
                        <i class="pi pi-check text-xs font-black"></i>
                        <span>Confirmar {{ localInput !== '' ? localInput : '' }}</span>
                    </button>
                </div>
            </div>
        </template>

        <!-- MODO STATUS -->
        <template v-else>
            <div class="p-3 flex items-center justify-between gap-3 w-full">
                <div class="min-w-0 flex-1">
                    <h4 class="font-bold text-surface-text leading-tight text-sm truncate">
                        {{ item.name }}
                    </h4>
                    <div class="flex items-center gap-2 mt-0.5 text-[11px] text-surface-muted">
                        <span class="truncate max-w-[140px]">{{ item.category?.name || 'Geral' }}</span>
                        <span>• Saldo: <strong class="text-surface-text">{{ Number(item.local_expected_stock ?? 0) }}</strong> {{ item.unit_of_measure }}</span>
                    </div>
                </div>

                <div class="shrink-0 flex items-center bg-surface-1/80 p-0.5 rounded-full border border-surface-border/60 gap-0.5">
                    <button
                        type="button"
                        @click="$emit('update-status', 'available')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'available' ? 'bg-emerald-500 text-white shadow-xs' : 'text-surface-muted hover:text-emerald-500 hover:bg-emerald-500/10'"
                    >
                        <i class="pi pi-check text-[10px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="$emit('update-status', 'paused')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'paused' ? 'bg-amber-500 text-white shadow-xs' : 'text-surface-muted hover:text-amber-500 hover:bg-amber-500/10'"
                    >
                        <i class="pi pi-pause text-[10px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="$emit('update-status', 'out_of_stock')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'out_of_stock' ? 'bg-red-500 text-white shadow-xs' : 'text-surface-muted hover:text-red-500 hover:bg-red-500/10'"
                    >
                        <i class="pi pi-times text-[10px] font-bold"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    activeMode: { type: String, default: 'inventory' },
    isExpanded: { type: Boolean, default: false },
    isTouched: { type: Boolean, default: false },
    displayValue: { type: [String, Number], default: '--' },
    delta: { type: Number, default: 0 },
    deltaLabel: { type: String, default: 'Exato' }
});

const emit = defineEmits(['toggle-expand', 'save', 'match-expected', 'update-status']);

const localInput = ref('');

watch(() => props.isExpanded, (expanded) => {
    if (expanded) {
        localInput.value = props.displayValue !== '--' ? String(props.displayValue) : '';
    }
});

const increment = () => {
    const step = (props.item.unit_of_measure === 'kg' || props.item.unit_of_measure === 'l') ? 0.5 : 1;
    const current = localInput.value === '' ? 0 : Number(localInput.value.replace(',', '.'));
    localInput.value = String(Number((current + step).toFixed(3)));
};

const decrement = () => {
    const step = (props.item.unit_of_measure === 'kg' || props.item.unit_of_measure === 'l') ? 0.5 : 1;
    const current = localInput.value === '' ? 0 : Number(localInput.value.replace(',', '.'));
    localInput.value = String(Math.max(0, Number((current - step).toFixed(3))));
};

const addPack = (factor) => {
    const step = Number(factor || 1);
    const current = localInput.value === '' ? 0 : Number(localInput.value.replace(',', '.'));
    localInput.value = String(Number((current + step).toFixed(3)));
};

const handleConfirm = () => {
    const cleanNum = localInput.value === '' ? 0 : Number(String(localInput.value).replace(',', '.'));
    emit('save', isNaN(cleanNum) ? 0 : Math.max(0, cleanNum));
};
</script>
