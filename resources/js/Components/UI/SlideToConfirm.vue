<template>
    <div
        ref="track"
        @click="handleTrackInteraction"
        class="relative h-11 rounded-full overflow-hidden items-center select-none transition-all duration-200 shadow-inner touch-none"
        :class="[
            format === 'short' ? 'w-fit inline-flex shrink-0' : 'w-full min-w-[140px] flex',
            disabled ? 'bg-white/5 cursor-not-allowed opacity-50' : [activeTheme.track, 'cursor-pointer backdrop-blur-xl'],
            isSuccess ? '!bg-emerald-500 !border-emerald-500 shadow-lg shadow-emerald-500/30' : activeTheme.border
        ]"
    >
        <!-- Texto de Instrução / Status -->
        <div
            class="h-full w-full flex items-center whitespace-nowrap font-black text-[11px] uppercase tracking-widest pointer-events-none transition-all duration-300 z-0 select-none px-3"
            :class="[
                format === 'short' ? 'justify-start pr-4' : 'justify-center',
                (!isLoading && !isSuccess) ? 'pl-11' : '',
                isSuccess ? 'text-white' : (disabled ? 'text-white/30 opacity-100' : [activeTheme.text, (dragOffset > 20 && !isLoading) ? 'opacity-40' : 'opacity-90'])
            ]"
        >
            <span v-if="isLoading" class="flex items-center gap-2">
                <i class="pi pi-spinner animate-spin"></i> Processando...
            </span>
            <span v-else-if="isSuccess" class="flex items-center gap-2">
                <i class="pi pi-check text-sm"></i> {{ successText }}
            </span>
            <span v-else class="flex items-center gap-1.5 truncate">
                <i v-if="format === 'long'" class="pi pi-angle-double-right animate-pulse text-[10px] shrink-0"></i>
                <span class="truncate">{{ text }}</span>
            </span>
        </div>

        <!-- Rastro de Arraste (Trail) -->
        <div
            v-if="!isLoading && !isSuccess"
            class="absolute left-0 top-0 bottom-0 pointer-events-none z-10"
            :class="[activeTheme.trail, !isDragging ? 'transition-all duration-300 ease-out' : '']"
            :style="{ width: `calc(40px + ${dragOffset}px)` }"
        ></div>

        <!-- Botão Deslizante (Thumb) -->
        <div
            v-if="!isLoading && !isSuccess"
            ref="thumb"
            @touchstart.stop="startDrag"
            @mousedown.stop="startDrag"
            class="absolute left-1 top-1 bottom-1 w-9 rounded-full flex items-center justify-center text-white shadow-md cursor-grab active:cursor-grabbing z-20 touch-none"
            :class="[disabled ? 'bg-white/20' : activeTheme.thumb, !isDragging ? 'transition-transform duration-300 ease-out' : '']"
            :style="{ transform: `translateX(${dragOffset}px)` }"
        >
            <i class="pi text-xs" :class="icon"></i>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useSlideToConfirm } from '@/Pages/Shared/Composables/useSlideToConfirm';

const props = defineProps({
    text: { type: String, default: 'Deslize para Confirmar' },
    successText: { type: String, default: 'Confirmado!' },
    icon: { type: String, default: 'pi-chevron-right' },
    color: { type: String, default: 'primary' },
    disabled: { type: Boolean, default: false },
    isLoading: { type: Boolean, default: false },
    isSuccess: { type: Boolean, default: false },
    format: {
        type: String,
        default: 'long',
        validator: (value) => ['short', 'long'].includes(value)
    }
});

const emit = defineEmits(['confirm']);

const interceptedEmit = (eventName, ...args) => {
    if (eventName === 'confirm') {
        if (props.disabled || props.isLoading || props.isSuccess) return;
        if (navigator.vibrate) navigator.vibrate(50);
        emit('confirm', ...args);
    } else {
        emit(eventName, ...args);
    }
};

const colorThemes = {
    primary: { track: 'bg-primary-500/10', border: 'border-primary-500/20', text: 'text-primary-400', thumb: 'bg-primary-500', trail: 'bg-primary-500/20' },
    success: { track: 'bg-emerald-500/10', border: 'border-emerald-500/20', text: 'text-emerald-400', thumb: 'bg-emerald-500', trail: 'bg-emerald-500/20' },
    danger:  { track: 'bg-red-500/10',     border: 'border-red-500/20',     text: 'text-red-400',     thumb: 'bg-red-500',     trail: 'bg-red-500/20' },
    warning: { track: 'bg-amber-500/10',   border: 'border-amber-500/20',   text: 'text-amber-400',   thumb: 'bg-amber-500',   trail: 'bg-amber-500/20' }
};

const activeTheme = computed(() => colorThemes[props.color] || colorThemes.primary);
const track = ref(null);
const thumb = ref(null);

const { isDragging, dragOffset, handleTrackInteraction, startDrag } = useSlideToConfirm(props, interceptedEmit, track, thumb);
</script>
