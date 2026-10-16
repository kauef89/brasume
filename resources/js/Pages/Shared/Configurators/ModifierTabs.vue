<template>
    <div class="flex gap-2 overflow-x-auto hide-scrollbar"
         :class="isTeamMode ? 'bg-transparent border-none py-1' : ''">
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            @click="$emit('update:modelValue', tab.id)"
            class="rounded-full font-black whitespace-nowrap transition-all flex items-center gap-2 outline-none cursor-pointer px-4 md:px-5 py-2.5 md:py-3 text-xs md:text-sm select-none border border-transparent"
            :class="[
                modelValue === tab.id
                    ? 'p-option shadow-sm'
                    : 'bg-surface-3 text-surface-muted hover:bg-surface-border/50 hover:text-surface-text'
            ]"
        >
            <i v-if="tab.icon" :class="['pi', tab.icon, 'text-xs']"></i>
            <span>{{ tab.label || tab.display_name || tab.name }}</span>

            <!-- Indicador de Validação Obrigatória -->
            <span
                v-if="tab.hasPendingRequired"
                class="w-2 h-2 rounded-full bg-warning-500 animate-pulse"
                title="Opções obrigatórias pendentes"
            ></span>
            <span
                v-else-if="tab.isComplete && tab.hasRequired"
                class="w-4 h-4 rounded-full bg-success-500/20 text-success-500 flex items-center justify-center text-[10px]"
                title="Obrigatórios preenchidos"
            >
                <i class="pi pi-check text-[8px] font-black"></i>
            </span>
            <span
                v-else-if="tab.groupCount > 1"
                class="text-[10px] opacity-60 font-semibold px-1.5 py-0.5 rounded-full bg-surface-2"
            >
                {{ tab.groupCount }}
            </span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    tabs: Array,
    modelValue: [String, Number],
    isTeamMode: { type: Boolean, default: false }
});

defineEmits(['update:modelValue']);
</script>
