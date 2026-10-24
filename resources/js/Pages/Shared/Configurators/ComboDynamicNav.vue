<template>
    <Teleport to="#island-portal-target" v-if="isMounted && !hide">

        <!-- 📱 ESTRUTURA 1: TEAM APP (2 ROWS ORGANIZADAS) -->
        <div
            v-if="isTeamApp"
            class="w-72 sm:w-80 flex flex-col gap-2 select-none py-1"
        >
            <!-- ROW 1: Voltar + Sacolinha (Viagem) + Cancelar (Lixeira) -->
            <div class="w-full flex items-center justify-between gap-2">
                <button
                    v-if="canGoBack"
                    @click="$emit('back')"
                    type="button"
                    class="w-11 h-11 shrink-0 rounded-full text-surface-text flex items-center justify-center transition-colors border-none outline-none cursor-pointer shadow-sm active:scale-90"
                    title="Voltar"
                >
                    <i class="pi pi-arrow-left text-sm"></i>
                </button>

                <div class="flex items-center gap-2">
                    <button
                        v-if="showTakeaway"
                        @click="$emit('toggle-takeaway')"
                        type="button"
                        class="h-11 px-3.5 shrink-0 rounded-full transition-all flex items-center justify-center gap-1.5 outline-none cursor-pointer border-none shadow-sm active:scale-95"
                        :class="isTakeaway
                            ? 'bg-warning-500 text-white shadow-sm'
                            : 'bg-warning-500/10 text-warning-600 dark:text-warning-400 hover:bg-warning-500/20'"
                        :title="isTakeaway ? 'Combo marcado para viagem' : 'Marcar para viagem'"
                    >
                        <i class="pi pi-shopping-bag text-sm"></i>
                        <span class="font-black text-xs uppercase tracking-wider">Viagem</span>
                    </button>

                    <button
                        @click="$emit('cancel')"
                        type="button"
                        class="w-11 h-11 shrink-0 rounded-full bg-danger-500/10 text-danger-500 hover:bg-danger-500 hover:text-white transition-all outline-none cursor-pointer border-none flex items-center justify-center active:scale-90 shadow-sm"
                        title="Cancelar Combo"
                    >
                        <i class="pi pi-trash text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- ROW 2: Ação Principal (Conferir Itens ou Deslize Final Long) -->
            <div class="w-full">
                <button
                    v-if="!showSummary"
                    @click="$emit('toggle-summary')"
                    type="button"
                    class="w-full h-11 px-4 rounded-2xl font-black text-xs uppercase tracking-widest transition-all outline-none cursor-pointer border-none flex items-center justify-center gap-2 active:scale-98 bg-surface-2 hover:bg-surface-3 text-surface-text shadow-sm"
                >
                    <i class="pi pi-list text-sm"></i>
                    <span>Conferir Itens</span>
                    <span class="bg-primary-500 text-white px-2 py-0.5 rounded-full text-[10px] min-w-[20px] text-center font-black">
                        {{ itemsCountDisplay }}
                    </span>
                </button>

                <SlideToConfirm
                    v-else
                    class="w-full !h-11 !shadow-none"
                    text="Finalizar Combo"
                    success-text="Pronto!"
                    icon="pi-check"
                    format="long"
                    :disabled="!isValid"
                    :is-loading="isAdding"
                    :is-success="isAddSuccess"
                    @confirm="$emit('next')"
                />
            </div>
        </div>

        <!-- 👤 ESTRUTURA 2: CUSTOMER APP (1 ROW COMPACTA - LARGURA ORGÂNICA GARANTIDA) -->
        <div
            v-else
            class="flex items-center gap-2 select-none whitespace-nowrap"
        >
            <!-- Botão Voltar -->
            <button
                v-if="canGoBack"
                @click="$emit('back')"
                type="button"
                class="w-11 h-11 shrink-0 rounded-full text-surface-text flex items-center justify-center transition-colors border-none outline-none cursor-pointer shadow-sm active:scale-90"
                title="Voltar"
            >
                <i class="pi pi-arrow-left text-sm"></i>
            </button>

            <!-- Ação Central: Sempre presente com largura (resolve o bug da bolinha) -->
            <button
                v-if="!showSummary"
                @click="$emit('toggle-summary')"
                type="button"
                class="h-11 px-4 rounded-full font-black text-[11px] uppercase tracking-widest transition-all outline-none cursor-pointer border-none flex items-center justify-center gap-2 active:scale-95 bg-primary-500/10 text-surface-text hover:bg-surface-2 shadow-sm whitespace-nowrap"
            >
                <span>Conferir</span>
                <span class="bg-primary-500/60 text-white p-1 rounded-full leading-none text-[10px] min-w-[18px] text-center font-black">
                    {{ itemsCountDisplay }}
                </span>
            </button>

            <!-- SlideToConfirm tipo SHORT no resumo do CustomerApp -->
            <SlideToConfirm
                v-else
                class="!h-11 !shadow-none"
                text="Finalizar Combo"
                success-text="Pronto!"
                icon="pi-check"
                format="short"
                :disabled="!isValid"
                :is-loading="isAdding"
                :is-success="isAddSuccess"
                @confirm="$emit('next')"
            />

            <!-- Botão Cancelar -->
            <button
                @click="$emit('cancel')"
                type="button"
                class="w-11 h-11 shrink-0 rounded-full bg-danger-500/10 text-danger-500 hover:bg-danger-500 hover:text-white transition-all outline-none cursor-pointer border-none flex items-center justify-center active:scale-90 shadow-sm"
                title="Cancelar Combo"
            >
                <i class="pi pi-trash text-sm"></i>
            </button>
        </div>

    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import SlideToConfirm from "@/Components/UI/SlideToConfirm.vue";
import { dynamicIslandTypeOverride } from '@/Pages/Shared/Composables/useDynamicIsland';

const props = defineProps({
    hide: Boolean,
    showSummary: Boolean,
    showTakeaway: { type: Boolean, default: true },
    isTakeaway: { type: Boolean, default: false },
    progress: { type: Number, default: 0 },
    totalItems: { type: Number, default: 0 },
    hasItems: { type: Boolean, default: null },
    isLastStep: Boolean,
    isValid: Boolean,
    isAdding: Boolean,
    isAddSuccess: Boolean,
    canGoBack: { type: Boolean, default: true }
});

defineEmits(['back', 'toggle-takeaway', 'toggle-summary', 'next', 'cancel']);

const isTeamApp = computed(() => {
    if (typeof window === 'undefined') return false;
    const path = window.location.pathname;
    return path.startsWith('/team') || path.startsWith('/pos') || path.startsWith('/waiter');
});

const isMounted = ref(false);

onMounted(() => {
    // 🚀 Timer protetor de montagem do Teleport
    setTimeout(() => {
        nextTick(() => {
            isMounted.value = true;
        });
    }, 100);
});

const itemsCountDisplay = computed(() => {
    return props.totalItems > 0 ? props.totalItems : props.progress;
});

watch(
    isTeamApp,
    (isTeam) => {
        dynamicIslandTypeOverride.value = isTeam ? 'two-rows' : 'single-row';
    },
    { immediate: true }
);

onUnmounted(() => {
    dynamicIslandTypeOverride.value = null;
});
</script>
