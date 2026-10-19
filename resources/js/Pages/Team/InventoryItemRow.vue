<template>
    <div
        class="flex flex-col transition-colors relative"
        :class="[
            isExpanded ? 'bg-surface-3/40 ring-1 ring-primary-500/30' : '',
            isTouched && !isExpanded ? 'bg-success-500/5' : ''
        ]"
    >
        <!-- ============================================== -->
        <!-- MODO A: INVENTÁRIO (CONTAGEM POR PONTO)         -->
        <!-- ============================================== -->
        <template v-if="activeMode === 'inventory'">
            <!-- 1. CABEÇALHO COMPACTO -->
            <div
                @click="$emit('toggle-expand')"
                class="p-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-surface-1/40 active:bg-surface-1/60 transition-colors select-none"
            >
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-surface-text text-sm leading-tight truncate">
                        {{ item.name }}
                    </h4>
                    <!-- 🏷️ Categoria + Quantidade Esperada -->
                    <span class="text-[10px] text-surface-muted truncate block mt-0.5">
                        {{ item.category?.name || 'Geral' }} <span class="opacity-40 mx-1">|</span> Esperado: <strong class="text-surface-text font-bold">{{ Number(item.local_expected_stock ?? 0) }}</strong> {{ item.unit_of_measure }}
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

            <!-- 2. CORPO EXPANDIDO -->
            <div
                v-if="isExpanded"
                class="px-3 pb-3.5 pt-1 border-t border-surface-border/40 flex flex-col gap-2.5 bg-surface-1/40 animate-in fade-in slide-in-from-top-1 duration-150"
            >
                <!-- Linha Auxiliar (Exibida apenas se houver fardo ou diferença de estoque total) -->
                <div
                    v-if="(item.conversion_factor && item.conversion_factor > 1) || (item.total_system_stock !== item.local_expected_stock)"
                    class="flex items-center justify-between text-xs px-0.5"
                >
                    <span v-if="item.total_system_stock !== item.local_expected_stock" class="text-[10px] text-surface-muted">
                        Total na loja: <strong class="text-surface-text">{{ Number(item.total_system_stock) }}</strong> {{ item.unit_of_measure }}
                    </span>
                    <div v-else></div>

                    <button
                        v-if="item.conversion_factor && item.conversion_factor > 1"
                        type="button"
                        @click.stop="addPack(item.conversion_factor)"
                        class="h-6 px-2 rounded-lg bg-surface-3 hover:bg-surface-border text-surface-text border border-surface-border text-[9px] font-black uppercase tracking-wider flex items-center gap-1 cursor-pointer transition-all active:scale-95"
                        :title="`Adicionar 1 fardo com ${item.conversion_factor} unidades`"
                    >
                        <i class="pi pi-box text-[9px] text-amber-500"></i>
                        <span>+1 Fd ({{ item.conversion_factor }})</span>
                    </button>
                </div>

                <!-- 🚀 LINHA INTEGRADA: QTYSTEPPER + SLIDETOCONFIRM (MODO SUCCESS / "CONFIRMAR") -->
                <div class="flex items-center gap-2 w-full">
                    <!-- Stepper com input manual direto no centro -->
                    <QtyStepper
                        v-model="localCount"
                        :min="0"
                        :step="stepValue"
                        size="md"
                        class="bg-surface-2 border border-surface-border/80 shadow-inner w-32 shrink-0"
                    >
                        <template #value>
                            <input
                                type="text"
                                inputmode="decimal"
                                :value="localCount"
                                @input="handleManualInput($event.target.value)"
                                class="w-full text-center bg-transparent border-none text-sm font-black outline-none text-surface-text p-0 select-text cursor-text"
                                @click.stop
                            />
                        </template>
                    </QtyStepper>

                    <!-- Slider de Confirmação em Modo Success -->
                    <div class="flex-1 min-w-0">
                        <SlideToConfirm
                            text="Confirmar"
                            success-text="Confirmado!"
                            color="success"
                            icon="pi-check"
                            format="long"
                            :is-loading="isSaving"
                            class="!h-11 shadow-sm text-[11px]"
                            @confirm="onSlideConfirm"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- ============================================== -->
        <!-- MODO B: STATUS (DISPONÍVEL / PAUSADO / ESGOT.) -->
        <!-- ============================================== -->
        <template v-else>
            <div class="p-3 flex items-center justify-between gap-3 w-full">
                <div class="min-w-0 flex-1">
                    <h4 class="font-bold text-surface-text leading-tight text-sm truncate">
                        {{ item.name }}
                    </h4>
                    <span class="text-[10px] text-surface-muted truncate block mt-0.5">
                        {{ item.category?.name || 'Geral' }} <span class="opacity-40 mx-1">|</span> Esperado: <strong class="text-surface-text font-bold">{{ Number(item.local_expected_stock ?? 0) }}</strong> {{ item.unit_of_measure }}
                    </span>
                </div>

                <div class="shrink-0 flex items-center bg-surface-1/80 p-0.5 rounded-full border border-surface-border/60 gap-0.5">
                    <button
                        type="button"
                        @click="$emit('update-status', 'available')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'available' ? 'bg-emerald-500 text-white shadow-xs' : 'text-surface-muted hover:text-emerald-500 hover:bg-emerald-500/10'"
                        title="Disponível"
                    >
                        <i class="pi pi-check text-[10px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="$emit('update-status', 'paused')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'paused' ? 'bg-amber-500 text-white shadow-xs' : 'text-surface-muted hover:text-amber-500 hover:bg-amber-500/10'"
                        title="Pausar"
                    >
                        <i class="pi pi-pause text-[10px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="$emit('update-status', 'out_of_stock')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'out_of_stock' ? 'bg-red-500 text-white shadow-xs' : 'text-surface-muted hover:text-red-500 hover:bg-red-500/10'"
                        title="Esgotado"
                    >
                        <i class="pi pi-times text-[10px] font-bold"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import QtyStepper from '@/Components/UI/QtyStepper.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';

const props = defineProps({
    item: { type: Object, required: true },
    locationId: { type: [Number, String], required: true },
    activeMode: { type: String, default: 'inventory' },
    isExpanded: { type: Boolean, default: false },
    isTouched: { type: Boolean, default: false },
    displayValue: { type: [String, Number], default: '--' },
    delta: { type: Number, default: 0 },
    deltaLabel: { type: String, default: 'Exato' },
    isSaving: { type: Boolean, default: false }
});

const emit = defineEmits(['toggle-expand', 'save', 'update-status']);

const localCount = ref(0);

watch(() => props.isExpanded, (expanded) => {
    if (expanded) {
        if (props.displayValue !== '--' && props.displayValue !== null && props.displayValue !== undefined) {
            localCount.value = Number(props.displayValue);
        } else {
            localCount.value = Number(props.item.local_expected_stock ?? 0);
        }
    }
});

const stepValue = computed(() => {
    const uom = (props.item.unit_of_measure || '').toLowerCase();
    return (uom === 'kg' || uom === 'l' || uom === 'litro' || uom === 'quilo') ? 0.5 : 1;
});

const handleManualInput = (val) => {
    if (val === '' || val === null || val === undefined) {
        localCount.value = 0;
        return;
    }
    const clean = Number(val.replace(',', '.'));
    if (!isNaN(clean)) {
        localCount.value = Math.max(0, clean);
    }
};

const addPack = (factor) => {
    localCount.value = Number((Number(localCount.value || 0) + Number(factor || 1)).toFixed(3));
};

const onSlideConfirm = () => {
    emit('save', Number(localCount.value || 0));
};
</script>
