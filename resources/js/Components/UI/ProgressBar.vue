<template>
    <div class="flex items-center gap-1.5 shrink-0 flex-wrap" role="progressbar">
        <template v-for="idx in totalSegments" :key="idx">
            <div
                class="rounded-full transition-all duration-500 ease-[cubic-bezier(0.4,0,0.2,1)]"
                :class="[getSegmentClass(idx - 1), isGlass ? 'h-1.5' : 'h-2.5']"
            ></div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    current: { type: [Number, String], default: 0 },
    max: { type: [Number, String], default: 1 },
    freeLimit: { type: [Number, String], default: null },
    mode: { type: String, default: 'wizard' },
    isGlass: { type: Boolean, default: false } // 🚀 Prop ativadora do modo vidro
});

const safeCurrent = computed(() => Number(props.current) || 0);
const safeMax = computed(() => Number(props.max) || 1);
const safeFree = computed(() => props.freeLimit !== null ? Number(props.freeLimit) : null);

const totalSegments = computed(() => {
    return safeMax.value > 20 ? 10 : Math.max(1, Math.ceil(safeMax.value));
});

const currentStep = computed(() => {
    if (safeMax.value > 20) {
        const perc = safeCurrent.value / safeMax.value;
        return Math.min(Math.floor(perc * totalSegments.value), totalSegments.value);
    }
    return Math.floor(safeCurrent.value);
});

const getSegmentClass = (index) => {
    const colorMap = {
        primary: {
            active: props.isGlass ? 'bg-primary-400' : 'bg-primary-500',
            completed: props.isGlass ? 'bg-primary-400/40' : 'bg-primary-500/40'
        },
        success: {
            active: props.isGlass ? 'bg-emerald-400' : 'bg-success-500',
            completed: props.isGlass ? 'bg-emerald-400/40' : 'bg-success-500/40'
        },
        warning: {
            active: props.isGlass ? 'bg-amber-400' : 'bg-warning-500',
            completed: props.isGlass ? 'bg-amber-400/40' : 'bg-warning-500/40'
        },
    };

    let colorKey = 'primary';
    if (safeCurrent.value >= safeMax.value) {
        colorKey = 'success';
    } else if (safeFree.value !== null && safeCurrent.value >= safeFree.value) {
        colorKey = 'warning';
    }

    const palette = colorMap[colorKey];

    if (currentStep.value >= totalSegments.value) {
        return `w-6 ${palette.active}`;
    }

    if (props.mode === 'wizard') {
        if (index === currentStep.value) return `w-12 ${palette.active}`;
        if (index < currentStep.value) return `w-6 ${palette.completed}`;
        return `w-3 ${props.isGlass ? 'bg-white/20' : 'bg-surface-border'}`;
    } else {
        if (index < currentStep.value) return `w-8 ${palette.active}`;
        return `w-3 opacity-40 ${props.isGlass ? 'bg-white/20' : 'bg-surface-border'}`;
    }
};
</script>
