<template>
    <div class="flex flex-col gap-3 p-0 rounded-none">

        <!-- CABEÇALHO DO GRUPO (Com status de obrigatoriedade) -->
        <div class="flex flex-col gap-2 pb-2">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <h3 class="font-black text-surface-text uppercase tracking-widest leading-tight text-sm truncate">
                        {{ group.display_name || group.name }}
                    </h3>
                    <span
                        v-if="group.min_selections > 0"
                        class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full shrink-0"
                        :class="currentProgress >= group.min_selections
                            ? 'bg-success-500/15 text-success-600 dark:text-success-400'
                            : 'bg-warning-500/15 text-warning-600 dark:text-warning-400'"
                    >
                        {{ currentProgress >= group.min_selections ? '✓ Atendido' : `Obrigatório (${group.min_selections})` }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span v-if="group.max_free > 0" class="text-[10px] font-medium bg-green-500/10 text-green-600 px-2 py-0.5 rounded-full">{{ group.max_free }} Inclusos</span>
                    <span v-if="group.max_selections > 0" class="text-[10px] font-medium text-surface-muted bg-surface-2 px-2 py-0.5 rounded-full">Máx: {{ group.max_selections }}</span>
                </div>
            </div>
            <ProgressBar
                v-if="progressMax > 0"
                :current="currentProgress"
                :max="progressMax"
                :free-limit="group.max_free"
            />
        </div>

        <!-- LISTA EM STEPPERS (Para adicionais com quantidade) -->
        <div v-if="!isCloudRender" class="flex flex-col gap-1 mt-1">
            <div
                v-for="opt in processedStepperOptions"
                :key="opt.id"
                class="flex items-center gap-3 border-b border-surface-border/30 last:border-0 py-2"
                :class="opt.is_active === false ? 'opacity-40 grayscale pointer-events-none' : ''"
            >
                <QtyStepper
                    :model-value="getQtyDisplay(opt)"
                    :min="0"
                    :max="getMaxQty(opt)"
                    :disabled="opt.is_active === false"
                    @update:model-value="(val) => handleStepperUpdate(opt, val)"
                    size="sm"
                />

                <div class="flex-1 min-w-0 flex flex-col justify-center pl-1">
                    <span class="font-medium text-surface-text leading-tight text-sm flex items-center gap-1.5">
                        {{ opt.name }}
                        
                    </span>
                </div>

                <div class="shrink-0 text-right flex justify-end">
                    <span v-if="getCalculatedPrice(opt) > 0" class="font-black text-primary-smart text-sm">
                        +{{ $formatCurrency(getCalculatedPrice(opt)) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- BOTÕES EM NUVEM (Pills) -->
        <div v-else class="flex flex-wrap mt-1 gap-1.5">
            <button
                v-for="opt in sortedCloudOptions"
                :key="opt.id"
                type="button"
                :disabled="helpers.isDisabled(group, opt) || opt.is_active === false"
                class="font-medium transition-all flex items-center justify-center gap-1.5 cursor-pointer rounded-full px-3 py-1.5 text-sm outline-none border border-transparent"
                :class="[
                    {
                        'bg-warning-500/20 font-black border-warning-500/30 text-surface-text': helpers.isSelected(group, opt) && opt.isJoker,
                        'bg-primary-500 text-white font-bold border-primary-500 shadow-xs': helpers.isSelected(group, opt) && !opt.isJoker,
                        'bg-warning-500/10 hover:bg-warning-500/20 text-warning-600 font-bold': !helpers.isSelected(group, opt) && !opt.isJoker && isPredicted(opt.id) && opt.is_active !== false,
                        'bg-warning-500/10 hover:bg-warning-500/40 text-surface-text': !helpers.isSelected(group, opt) && opt.isJoker,
                        'bg-surface-3 text-surface-text hover:bg-surface-border/60': !helpers.isSelected(group, opt) && !opt.isJoker && !isPredicted(opt.id) && opt.is_active !== false,
                        'opacity-40 grayscale cursor-not-allowed': helpers.isDisabled(group, opt) || opt.is_active === false
                    }
                ]"
                @click="opt.is_active !== false && helpers.toggleSelection(group, opt)"
            >
                <span class="whitespace-nowrap">{{ opt.name }}</span>
                

                <span v-if="group.type !== 'observation'"
                      class="text-xs font-black transition-opacity"
                      :class="{
                          'opacity-40 text-current': helpers.getEffectivePrice(group, opt) === 0,
                          'text-white': helpers.getEffectivePrice(group, opt) > 0 && helpers.isSelected(group, opt) && !opt.isJoker,
                          'text-warning-500': helpers.getEffectivePrice(group, opt) > 0 && !helpers.isSelected(group, opt) && !opt.isJoker && isPredicted(opt.id),
                          'text-primary-500': helpers.getEffectivePrice(group, opt) > 0 && !helpers.isSelected(group, opt) && !opt.isJoker && !isPredicted(opt.id)
                      }">
                    +{{ formatCleanCurrency(helpers.getEffectivePrice(group, opt)) }}
                </span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { formatCurrency } from "@/Utils/formatters";
import ProgressBar from "@/Components/UI/ProgressBar.vue";
import QtyStepper from "@/Components/UI/QtyStepper.vue";
import { usePredictiveModifiers } from '@/Pages/Shared/Composables/usePredictiveModifiers';

const props = defineProps({
    group: Object,
    helpers: Object,
    isTeamMode: { type: Boolean, default: false }
});

const page = usePage();
const { getRecommendations } = usePredictiveModifiers();

const outOfStockMode = computed(() => {
    return page?.props?.companySettings?.out_of_stock_mode || 'disable_bottom';
});

const isOptionUnavailable = (opt) => {
    if (!opt) return false;
    if (opt.isJoker) return false;
    return opt.is_active === false || opt.is_active === 0 || opt.is_active === '0';
};

const processOptionsByStock = (optionsList) => {
    if (!Array.isArray(optionsList)) return [];
    const mode = outOfStockMode.value;

    if (mode === 'hide') {
        return optionsList.filter(opt => !isOptionUnavailable(opt));
    }

    if (mode === 'disable_bottom') {
        return [...optionsList].sort((a, b) => {
            const aBlocked = isOptionUnavailable(a);
            const bBlocked = isOptionUnavailable(b);
            if (aBlocked && !bBlocked) return 1;
            if (!aBlocked && bBlocked) return -1;
            return 0;
        });
    }

    return optionsList;
};

const isCloudRender = computed(() => {
    return !props.group.allow_quantity || props.group.type === 'observation';
});

const progressMax = computed(() => {
    return props.group.max_selections > 0 ? props.group.max_selections : props.group.max_free;
});

const currentProgress = computed(() => {
    let total = 0;
    const allOpts = props.helpers.getOptionsWithJoker(props.group);
    allOpts.forEach(opt => {
        if (props.group.allow_quantity && !isCloudRender.value) {
            total += props.helpers.getQty(props.group, opt) || 0;
        } else {
            if (props.helpers.isSelected(props.group, opt)) total += 1;
        }
    });
    return total;
});

const sortedCloudOptionsBase = computed(() => {
    if (!isCloudRender.value) return [];
    const opts = [...props.helpers.getOptionsWithJoker(props.group)];
    return opts.sort((a, b) => {
        if (a.isJoker) return 1;
        if (b.isJoker) return -1;
        return a.name.localeCompare(b.name);
    });
});

const processedStepperOptions = computed(() => {
    return processOptionsByStock(props.helpers.getOptionsWithJoker(props.group));
});

const processedCloudOptions = computed(() => {
    return processOptionsByStock(sortedCloudOptionsBase.value);
});

// Retrocompatibilidade no template:
const sortedCloudOptions = computed(() => processedCloudOptions.value);

const predictedOptionIds = computed(() => {
    if (!isCloudRender.value || props.group.type !== 'ingredient') return [];

    const available = props.helpers.getOptionsWithJoker(props.group);
    const selected = available.filter(opt => props.helpers.isSelected(props.group, opt));
    const limit = props.group.max_selections > 0 ? props.group.max_selections : 3;

    if (props.group.max_selections > 0 && selected.length >= props.group.max_selections) {
        return [];
    }

    return getRecommendations(available, selected, limit);
});

const isPredicted = (optId) => {
    return predictedOptionIds.value.some(id => String(id) === String(optId));
};

const getQtyDisplay = (opt) => {
    if (props.group.allow_quantity) {
        return props.helpers.getQty(props.group, opt);
    }
    return props.helpers.isSelected(props.group, opt) ? 1 : 0;
};

const getMaxQty = (opt) => {
    if (!props.group.allow_quantity) return 1;
    if (isOptionUnavailable(opt)) return 0;
    if (props.helpers.isDisabled(props.group, opt)) return getQtyDisplay(opt);
    return null;
};

const handleStepperUpdate = (opt, newVal) => {
    if (isOptionUnavailable(opt)) return;
    const current = getQtyDisplay(opt);
    if (newVal > current) {
        if (props.group.allow_quantity) props.helpers.updateQty(props.group, opt, 1);
        else if (!props.helpers.isSelected(props.group, opt)) props.helpers.toggleSelection(props.group, opt);
    } else if (newVal < current) {
        if (props.group.allow_quantity) props.helpers.updateQty(props.group, opt, -1);
        else if (props.helpers.isSelected(props.group, opt)) props.helpers.toggleSelection(props.group, opt);
    }
};

const getCalculatedPrice = (opt) => {
    const qty = getQtyDisplay(opt);
    const unitPrice = props.helpers.getEffectivePrice(props.group, opt);
    if (qty > 1 && props.group.allow_quantity) return unitPrice * qty;
    return unitPrice;
};

const formatCleanCurrency = (value) => {
    const formatted = formatCurrency(value);
    return formatted.replace(',00', '').replace('.00', '');
};
</script>