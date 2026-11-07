<template>
    <div class="bg-surface-1 rounded-3xl p-5 md:p-6 border border-surface-border/60 flex flex-col gap-4 shadow-xs select-none">
        <!-- CABEÇALHO -->
        <div class="flex items-center justify-between pb-3 border-b border-surface-border/40 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-primary-500/10 text-primary-500 flex items-center justify-center text-lg shrink-0">
                    <i class="pi pi-credit-card"></i>
                </div>
                <div>
                    <h4 class="text-sm font-black text-surface-text leading-tight">Conferência de Maquininhas (SiTef / APOS)</h4>
                    <p class="text-[11px] text-surface-muted leading-tight mt-0.5">Valores do relatório sintético impresso em cada terminal</p>
                </div>
            </div>

            <div class="text-right shrink-0">
                <span class="text-[10px] font-black uppercase tracking-wider text-surface-muted block">Total Maquininhas</span>
                <span class="text-base font-black text-primary-smart">{{ formatCurrency(grandTotal) }}</span>
            </div>
        </div>

        <!-- MENSAGEM SE NÃO HOUVER TERMINAIS -->
        <div v-if="terminals.length === 0" class="py-8 text-center text-xs text-surface-muted flex flex-col items-center gap-2">
            <i class="pi pi-info-circle text-base text-surface-muted"></i>
            <span>Nenhuma maquininha com código TID configurada no estabelecimento.</span>
        </div>

        <!-- LISTA DE MAQUININHAS COM 3 CAMPOS NATIVOS CADA -->
        <div v-else class="flex flex-col gap-4">
            <div
                v-for="term in terminals"
                :key="term.terminal_code"
                class="bg-surface-2 p-4 rounded-2xl border border-surface-border/50 flex flex-col gap-3 transition-colors hover:border-surface-border"
            >
                <!-- CABEÇALHO DO TERMINAL -->
                <div class="flex items-center justify-between pb-2 border-b border-surface-border/30">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-surface-3 flex items-center justify-center text-xs text-surface-text font-bold shrink-0">
                            <i class="pi pi-calculator text-xs"></i>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-xs font-black text-surface-text truncate">{{ term.name }}</span>
                            <span class="text-[10px] text-surface-muted font-mono truncate">TID: {{ term.terminal_code }}</span>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <span class="text-[10px] text-surface-muted font-bold block uppercase tracking-wider">Subtotal Terminal</span>
                        <span class="text-xs font-black text-surface-text">{{ formatCurrency(terminalSubtotal(term.terminal_code)) }}</span>
                    </div>
                </div>

                <!-- 3 CAMPOS DO RELATÓRIO SINTÉTICO (HTML5 NATIVO COM MIN-W-0) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                    
                    <!-- 1. BANDEIRAS -->
                    <div class="flex flex-col gap-1 min-w-0">
                        <label class="text-[10px] font-bold text-surface-muted uppercase tracking-wider truncate">
                            1. Bandeiras (Déb/Créd)
                        </label>
                        <div class="relative w-full min-w-0">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-surface-muted pointer-events-none">R$</span>
                            <input
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                placeholder="0,00"
                                :value="rawInputs[term.terminal_code]?.cards ?? ''"
                                @input="onInputChange(term.terminal_code, 'cards', $event.target.value)"
                                @blur="onInputBlur(term.terminal_code, 'cards')"
                                class="w-full h-9 pl-8 pr-2.5 text-xs font-black text-right bg-surface-1 text-surface-text rounded-xl border border-surface-border/80 focus:border-primary-500 focus:outline-none transition-colors shadow-inner box-border"
                            />
                        </div>
                    </div>

                    <!-- 2. PIX COMPRA -->
                    <div class="flex flex-col gap-1 min-w-0">
                        <label class="text-[10px] font-bold text-surface-muted uppercase tracking-wider truncate">
                            2. Pix Compra (Maq.)
                        </label>
                        <div class="relative w-full min-w-0">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-surface-muted pointer-events-none">R$</span>
                            <input
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                placeholder="0,00"
                                :value="rawInputs[term.terminal_code]?.pix ?? ''"
                                @input="onInputChange(term.terminal_code, 'pix', $event.target.value)"
                                @blur="onInputBlur(term.terminal_code, 'pix')"
                                class="w-full h-9 pl-8 pr-2.5 text-xs font-black text-right bg-surface-1 text-surface-text rounded-xl border border-surface-border/80 focus:border-primary-500 focus:outline-none transition-colors shadow-inner box-border"
                            />
                        </div>
                    </div>

                    <!-- 3. VOUCHER -->
                    <div class="flex flex-col gap-1 min-w-0">
                        <label class="text-[10px] font-bold text-surface-muted uppercase tracking-wider truncate">
                            3. Voucher (VR / VA)
                        </label>
                        <div class="relative w-full min-w-0">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-surface-muted pointer-events-none">R$</span>
                            <input
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                placeholder="0,00"
                                :value="rawInputs[term.terminal_code]?.voucher ?? ''"
                                @input="onInputChange(term.terminal_code, 'voucher', $event.target.value)"
                                @blur="onInputBlur(term.terminal_code, 'voucher')"
                                class="w-full h-9 pl-8 pr-2.5 text-xs font-black text-right bg-surface-1 text-surface-text rounded-xl border border-surface-border/80 focus:border-primary-500 focus:outline-none transition-colors shadow-inner box-border"
                            />
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';

const props = defineProps({
    devices: {
        type: Array,
        default: () => []
    },
    modelValue: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['update:modelValue', 'update:total']);

const terminals = computed(() => {
    return (props.devices || []).filter(d => Boolean(d.terminal_code) && d.is_active !== false);
});

const settlements = ref({});
const rawInputs = ref({});

const parseNumber = (str) => {
    if (!str) return 0;
    const clean = String(str).replace(/[^\d.,]/g, '').replace(',', '.');
    const val = parseFloat(clean);
    return isNaN(val) ? 0 : val;
};

const formatNumberToLocale = (val) => {
    if (val === null || val === undefined || val === 0 || isNaN(val)) return '';
    return Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const initData = () => {
    terminals.value.forEach(t => {
        const tid = t.terminal_code;
        const existing = props.modelValue[tid] || {};

        settlements.value[tid] = {
            device_id: t.id,
            name: t.name,
            terminal_code: tid,
            cards: existing.cards ? Number(existing.cards) : null,
            pix: existing.pix ? Number(existing.pix) : null,
            voucher: existing.voucher ? Number(existing.voucher) : null,
        };

        rawInputs.value[tid] = {
            cards: existing.cards ? formatNumberToLocale(existing.cards) : '',
            pix: existing.pix ? formatNumberToLocale(existing.pix) : '',
            voucher: existing.voucher ? formatNumberToLocale(existing.voucher) : '',
        };
    });
};

onMounted(() => {
    initData();
});

watch(() => props.devices, () => {
    initData();
}, { deep: true });

const onInputChange = (tid, field, val) => {
    if (!rawInputs.value[tid]) rawInputs.value[tid] = {};
    rawInputs.value[tid][field] = val;

    if (!settlements.value[tid]) settlements.value[tid] = {};
    settlements.value[tid][field] = parseNumber(val);

    propagate();
};

const onInputBlur = (tid, field) => {
    const num = settlements.value[tid]?.[field];
    if (num !== null && num !== undefined && num > 0) {
        rawInputs.value[tid][field] = formatNumberToLocale(num);
    } else {
        rawInputs.value[tid][field] = '';
    }
    propagate();
};

const terminalSubtotal = (tid) => {
    const item = settlements.value[tid];
    if (!item) return 0;
    return (Number(item.cards) || 0) + (Number(item.pix) || 0) + (Number(item.voucher) || 0);
};

const grandTotal = computed(() => {
    return Object.keys(settlements.value).reduce((sum, tid) => {
        return sum + terminalSubtotal(tid);
    }, 0);
});

const propagate = () => {
    const payload = {};
    Object.keys(settlements.value).forEach(tid => {
        const t = settlements.value[tid];
        const subtotal = terminalSubtotal(tid);
        payload[tid] = {
            ...t,
            total: subtotal
        };
    });

    emit('update:modelValue', payload);
    emit('update:total', grandTotal.value);
};

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};
</script>
