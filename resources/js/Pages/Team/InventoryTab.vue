<template>
    <div
        class="flex flex-col h-full overflow-y-auto hide-scrollbar relative pb-36 select-none"
        :style="{ paddingTop: 'var(--app-header-height, 6rem)' }"
    >
        <!-- TELEPORT PARA O TEAM HEADER -->
        <Teleport to="#team-header-action-portal" v-if="isMounted">
            <div class="flex items-center gap-1.5 shrink-0">
                <div
                    class="transition-all duration-300 ease-out"
                    :class="searchQuery ? 'w-36 sm:w-44' : 'w-24 sm:w-32 focus-within:w-36 sm:focus-within:w-48'"
                >
                    <ExpandableSearch v-model="searchQuery" placeholder="Buscar..." class="!h-9 !w-full" />
                </div>

                <button
                    type="button"
                    @click="isFilterModalOpen = true"
                    class="w-9 h-9 rounded-full flex items-center justify-center transition-all cursor-pointer outline-none border relative active:scale-95 shrink-0 shadow-xs"
                    :class="hasActiveFilters
                        ? 'bg-primary-500 text-white border-primary-400 shadow-primary-500/20'
                        : 'bg-surface-2 text-surface-muted border-surface-border/60 hover:text-surface-text hover:bg-surface-3'"
                >
                    <i class="pi pi-sliders-h text-xs"></i>
                    <span v-if="hasActiveFilters" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-primary-smart rounded-full ring-2 ring-surface-1"></span>
                </button>
            </div>
        </Teleport>

        <!-- ROTA DE CAMINHADA (CARROSSEL FÍSICO) -->
        <div class="px-4 pt-3 pb-1 shrink-0 overflow-x-auto hide-scrollbar flex items-center gap-2 border-b border-surface-border/30 bg-surface-1/40">
            <button
                type="button"
                @click="selectedLocation = 'all'"
                class="h-8 px-3 rounded-full text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer"
                :class="selectedLocation === 'all'
                    ? 'bg-primary-500 text-white border-primary-400 shadow-sm'
                    : 'bg-surface-2 text-surface-muted border-surface-border/60 hover:text-surface-text'"
            >
                <i class="pi pi-globe text-[10px]"></i>
                <span>Todos</span>
            </button>

            <button
                v-for="loc in routeLocations"
                :key="loc.id"
                type="button"
                @click="selectedLocation = String(loc.id)"
                class="h-8 px-3 rounded-full text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer"
                :class="selectedLocation === String(loc.id)
                    ? 'bg-primary-500 text-white border-primary-400 shadow-sm'
                    : 'bg-surface-2 text-surface-muted border-surface-border/60 hover:text-surface-text'"
            >
                <i class="pi text-[10px]" :class="loc.type === 'display' ? 'pi-eye text-primary-smart' : 'pi-box text-amber-500'"></i>
                <span>{{ loc.name }}</span>
                <span
                    class="text-[10px] px-1.5 py-0.2 rounded-full font-black"
                    :class="loc.isComplete
                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                        : (selectedLocation === String(loc.id) ? 'bg-white/20 text-white' : 'bg-surface-3 text-surface-muted')"
                >
                    <i v-if="loc.isComplete" class="pi pi-check text-[9px]"></i>
                    <template v-else>{{ loc.touchedCount }}/{{ loc.totalCount }}</template>
                </span>
            </button>
        </div>

        <!-- CONTEÚDO PRINCIPAL -->
        <div class="p-3 md:p-6 flex-1 flex flex-col gap-4">
            <!-- Pílula de Filtros Ativos -->
            <div v-if="hasActiveFilters" class="flex items-center justify-between gap-2 p-2 rounded-xl bg-surface-2/60 border border-surface-border/40 text-[10px]">
                <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar text-surface-muted font-bold">
                    <span class="uppercase tracking-widest text-[9px] text-surface-muted/80">Ativos:</span>
                    <span v-if="activeMode === 'inventory'" class="px-2 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-500 font-black">Inventário</span>
                    <span v-if="selectedType !== 'all'" class="px-2 py-0.5 rounded-full bg-surface-3 border border-surface-border text-surface-text">
                        {{ typeFilters.find(t => t.value === selectedType)?.label }}
                    </span>
                    <span v-if="selectedLocation !== 'all'" class="px-2 py-0.5 rounded-full bg-surface-3 border border-surface-border text-surface-text">
                        {{ locationOptions.find(l => l.value === selectedLocation)?.label }}
                    </span>
                </div>
                <button type="button" @click="resetFilters" class="text-[10px] font-bold text-surface-muted hover:text-danger-500 underline border-none bg-transparent cursor-pointer shrink-0">
                    Limpar
                </button>
            </div>

            <!-- BLOCOS DE LOCAIS FÍSICOS -->
            <template v-if="locationBlocks.length > 0">
                <div
                    v-for="block in locationBlocks"
                    :key="block.location_id"
                    class="bg-surface-2 rounded-2xl border border-surface-border overflow-hidden shadow-sm flex flex-col"
                >
                    <div class="px-4 py-2.5 bg-surface-3/50 border-b border-surface-border flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="pi text-xs shrink-0" :class="block.type === 'display' ? 'pi-eye text-primary-500' : 'pi-box text-amber-500'"></i>
                            <h3 class="font-bold text-surface-text uppercase tracking-wider text-xs truncate">
                                {{ block.location_name }}
                            </h3>
                        </div>
                        <span class="bg-surface-border text-surface-text text-[10px] px-2 py-0.5 rounded-full font-black shrink-0">
                            {{ block.items.length }} {{ block.items.length === 1 ? 'item' : 'itens' }}
                        </span>
                    </div>

                    <!-- Lista Modular de Itens -->
                    <div class="flex flex-col divide-y divide-surface-border/40">
                        <InventoryItemRow
                            v-for="item in block.items"
                            :key="`${block.location_id}_${item.id}`"
                            :item="item"
                            :active-mode="activeMode"
                            :is-expanded="isExpanded(block.location_id, item.id)"
                            :is-touched="isTouched(block.location_id, item.id)"
                            :display-value="getDisplayValue(block.location_id, item.id)"
                            :delta="getItemDelta(block.location_id, item)"
                            :delta-label="formatDeltaLabel(block.location_id, item)"
                            @toggle-expand="toggleItemExpansion(block.location_id, item)"
                            @save="qty => saveCount(block.location_id, item.id, qty)"
                            @match-expected="saveCount(block.location_id, item.id, item.local_expected_stock)"
                            @update-status="target => updateStatus(item, target)"
                        />
                    </div>
                </div>
            </template>

            <div v-else class="flex flex-col items-center justify-center py-20 text-surface-muted opacity-60">
                <i class="pi pi-box text-5xl mb-3 text-primary-500/40"></i>
                <h3 class="text-lg font-bold text-center">Nenhum item encontrado</h3>
                <p class="text-xs mt-1 text-center max-w-xs">Ajuste os filtros ou escolha outro local.</p>
            </div>
        </div>

        <!-- BARRA FIXA INFERIOR (GRAVAÇÃO DO BALANÇO) -->
        <Transition name="slide-up">
            <div
                v-if="activeMode === 'inventory' && touchedItemsCount > 0"
                class="fixed bottom-0 inset-x-0 z-40 bg-surface-1/95 border-t border-surface-border/60 backdrop-blur-xl p-3 px-4 flex flex-col gap-2 shadow-2xl"
            >
                <div class="flex items-center justify-between text-xs font-bold px-1">
                    <span class="flex items-center gap-1.5 text-surface-text">
                        <i class="pi pi-clipboard text-primary-smart"></i>
                        <span><strong>{{ touchedItemsCount }}</strong> {{ touchedItemsCount === 1 ? 'item conferido' : 'itens conferidos' }}</span>
                    </span>
                    <span v-if="divergencesSummary.total !== 0" class="text-[11px] font-black" :class="divergencesSummary.losses > 0 ? 'text-danger-500' : 'text-emerald-500'">
                        {{ divergencesSummary.text }}
                    </span>
                    <span v-else class="text-[11px] font-black text-emerald-500">
                        <i class="pi pi-check text-[10px]"></i> Tudo exato com o sistema
                    </span>
                </div>

                <SlideToConfirm
                    text="Deslize para Gravar Balanço"
                    success-text="Atualizando Estoque..."
                    :color="divergencesSummary.losses > 0 ? 'danger' : 'primary'"
                    icon="pi-check"
                    format="long"
                    class="!h-11 text-xs w-full shadow-lg"
                    @confirm="submitBatchAdjustment"
                />
            </div>
        </Transition>

        <!-- MODAL DE FILTROS -->
        <AdaptiveModal v-model:visible="isFilterModalOpen">
            <div class="flex flex-col w-full text-surface-text select-none">
                <div class="flex items-center justify-between p-4 border-b border-surface-border/40">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-primary-500/10 text-primary-smart flex items-center justify-center font-black">
                            <i class="pi pi-sliders-h text-sm"></i>
                        </div>
                        <h3 class="text-sm font-black tracking-tight leading-tight">Configurações de Estoque</h3>
                    </div>
                    <button type="button" @click="isFilterModalOpen = false" class="w-8 h-8 rounded-full bg-surface-2 text-surface-muted hover:text-surface-text flex items-center justify-center border-none cursor-pointer">
                        <i class="pi pi-times text-xs"></i>
                    </button>
                </div>

                <div class="p-4 flex flex-col gap-5">
                    <div class="flex flex-col gap-2 items-start">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Modo de Operação</span>
                        <SegmentedControl v-model="activeMode" :options="modeOptions" class="!h-9 text-xs w-fit !mx-0" />
                    </div>
                    <div class="flex flex-col gap-2 items-start w-full">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Categoria Operacional</span>
                        <div class="w-full overflow-x-auto hide-scrollbar pb-1">
                            <SegmentedControl v-model="selectedType" :options="typeFilters" class="!h-9 text-xs w-fit shrink-0 !mx-0" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 p-4 border-t border-surface-border/40 bg-surface-2/30">
                    <button v-if="hasActiveFilters" type="button" @click="resetFilters" class="text-xs font-bold text-surface-muted hover:text-surface-text underline bg-transparent border-none cursor-pointer">
                        Limpar Filtros
                    </button>
                    <div v-else></div>
                    <button type="button" @click="isFilterModalOpen = false" class="h-10 px-6 rounded-2xl bg-primary-500 text-white font-black text-xs uppercase tracking-wider border-none cursor-pointer">
                        Pronto
                    </button>
                </div>
            </div>
        </AdaptiveModal>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import ExpandableSearch from '@/Components/UI/ExpandableSearch.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

import InventoryItemRow from '@/Pages/Team/InventoryItemRow.vue';
import { useInventoryCount } from '@/Pages/Team/Composables/useInventoryCount';

const props = defineProps({
    catalog: { type: Array, default: () => [] },
    isActive: { type: Boolean, default: false }
});

const isMounted = ref(false);
const isFilterModalOpen = ref(false);

const catalogRef = computed(() => props.catalog);

const {
    activeMode,
    selectedLocation,
    selectedType,
    searchQuery,
    modeOptions,
    typeFilters,
    hasActiveFilters,
    resetFilters,
    locationBlocks,
    routeLocations,
    locationOptions,
    isExpanded,
    toggleItemExpansion,
    isTouched,
    getDisplayValue,
    getItemDelta,
    formatDeltaLabel,
    saveCount,
    touchedItemsCount,
    divergencesSummary,
    submitBatchAdjustment,
    updateStatus
} = useInventoryCount(catalogRef);

onMounted(() => {
    isMounted.value = true;
});
</script>

<style scoped>
.slide-up-enter-active, .slide-up-leave-active {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
}
.slide-up-enter-from, .slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
</style>
