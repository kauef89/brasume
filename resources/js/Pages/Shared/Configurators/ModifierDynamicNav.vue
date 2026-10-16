<template>
    <Teleport to="#island-portal-target" v-if="isMounted && !hide">

        <!-- 📱 TEAM APP: 2 LINHAS COM LARGURA ORGÂNICA -->
        <div v-if="isTeamApp" class="w-72 sm:w-80 flex flex-col gap-2 select-none py-1">
            <!-- ROW 1: Voltar + Stepper Centralizado + Sacolinha de Viagem -->
            <div class="w-full flex items-center justify-between gap-2">
                <button
                    @click="$emit('back')"
                    type="button"
                    class="w-11 h-11 shrink-0 rounded-full bg-surface-1/50 hover:bg-surface-2 text-surface-text flex items-center justify-center transition-colors border-none outline-none cursor-pointer shadow-sm active:scale-90"
                    title="Voltar"
                >
                    <i class="pi pi-arrow-left text-sm"></i>
                </button>

                <div class="shrink-0 flex items-center justify-center">
                    <slot name="stepper"></slot>
                </div>

                <button
                    v-if="showTakeaway"
                    type="button"
                    @click="$emit('toggle-takeaway')"
                    class="h-11 px-3.5 shrink-0 rounded-full transition-all flex items-center justify-center gap-1.5 outline-none cursor-pointer border-none shadow-sm active:scale-95"
                    :class="isTakeaway
                        ? 'bg-warning-500 text-white shadow-sm'
                        : 'bg-warning-500/10 text-warning-600 dark:text-warning-400 hover:bg-warning-500/20'"
                    :title="isTakeaway ? 'Item marcado para viagem' : 'Marcar para viagem'"
                >
                    <i class="pi pi-shopping-bag text-sm"></i>
                    <span class="font-black text-xs uppercase tracking-wider">Viagem</span>
                </button>
            </div>

            <!-- ROW 2: SlideToConfirm tipo LONG sozinho na segunda linha -->
            <div class="w-full">
                <SlideToConfirm
                    :text="isEditMode ? 'Atualizar Item' : 'Adicionar Item'"
                    :success-text="isEditMode ? 'Atualizado!' : 'Adicionado!'"
                    :icon="isEditMode ? 'pi-check' : 'pi-shopping-cart'"
                    format="long"
                    :disabled="!isValid"
                    :is-loading="isAdding"
                    :is-success="isAddSuccess"
                    @confirm="$emit('confirm')"
                    class="w-full !h-11 !shadow-none"
                />
            </div>
        </div>

        <!-- 👤 CUSTOMER APP: 1 LINHA COMPACTA (SlideToConfirm tipo SHORT abraçando o conteúdo) -->
        <div v-else class="flex items-center gap-2 select-none px-1 whitespace-nowrap">
            <button
                @click="$emit('back')"
                type="button"
                class="w-11 h-11 shrink-0 rounded-full bg-surface-1/50 hover:bg-surface-2 text-surface-text flex items-center justify-center transition-colors border-none outline-none cursor-pointer shadow-sm active:scale-90"
                title="Voltar"
            >
                <i class="pi pi-arrow-left text-sm"></i>
            </button>

            <div class="shrink-0 flex items-center">
                <slot name="stepper"></slot>
            </div>

            <SlideToConfirm
                :text="isEditMode ? 'Atualizar' : 'Adicionar'"
                :success-text="isEditMode ? 'Atualizado!' : 'Adicionado!'"
                :icon="isEditMode ? 'pi-check' : 'pi-shopping-cart'"
                format="short"
                :disabled="!isValid"
                :is-loading="isAdding"
                :is-success="isAddSuccess"
                @confirm="$emit('confirm')"
                class="!h-11 !shadow-none"
            />
        </div>

    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import SlideToConfirm from "@/Components/UI/SlideToConfirm.vue";
import { dynamicIslandTypeOverride } from '@/Pages/Shared/Composables/useDynamicIsland';

defineProps({
    hide: Boolean,
    showTakeaway: { type: Boolean, default: true },
    isTakeaway: { type: Boolean, default: false },
    isEditMode: Boolean,
    isValid: Boolean,
    isAdding: Boolean,
    isAddSuccess: Boolean
});

defineEmits(['back', 'toggle-takeaway', 'confirm']);

const isTeamApp = computed(() => {
    if (typeof window === 'undefined') return false;
    const path = window.location.pathname;
    return path.startsWith('/team') || path.startsWith('/pos') || path.startsWith('/waiter');
});

const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

// 🚀 Notifica a Ilha Dinâmica: 2 rows no TeamApp (rounded-3xl) e 1 row no CustomerApp (rounded-full)
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
