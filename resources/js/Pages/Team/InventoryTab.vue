<template>
    <div
        class="flex flex-col h-full overflow-y-auto hide-scrollbar relative pb-36"
        :style="{ paddingTop: 'var(--app-header-height, 6rem)' }"
    >
        <!-- 🔍 TELEPORT PARA O TEAM HEADER: BUSCA COMPACTA + BOTÃO DE FILTRO -->
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
                    title="Filtros de Estoque"
                >
                    <i class="pi pi-sliders-h text-xs"></i>
                    <span
                        v-if="hasActiveFilters"
                        class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-primary-smart rounded-full ring-2 ring-surface-1"
                    ></span>
                </button>
            </div>
        </Teleport>

        <!-- CONTEÚDO PRINCIPAL: BLOCOS POR PRAÇA FÍSICA -->
        <div class="p-4 md:p-6 flex-1 flex flex-col gap-5">

            <!-- Pílula de filtros ativos -->
            <div v-if="hasActiveFilters" class="flex items-center justify-between gap-2 p-2 rounded-xl bg-surface-2/60 border border-surface-border/40 text-[10px]">
                <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar text-surface-muted font-bold">
                    <span class="uppercase tracking-widest text-[9px] text-surface-muted/80">Ativos:</span>
                    <span v-if="activeMode === 'inventory'" class="px-2 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-500 font-black">
                        Inventário
                    </span>
                    <span v-if="selectedType !== 'all'" class="px-2 py-0.5 rounded-full bg-surface-3 border border-surface-border text-surface-text">
                        {{ typeFilters.find(t => t.value === selectedType)?.label }}
                    </span>
                    <span v-if="selectedLocation !== 'all'" class="px-2 py-0.5 rounded-full bg-surface-3 border border-surface-border text-surface-text">
                        {{ locationOptions.find(l => l.value === selectedLocation)?.label }}
                    </span>
                </div>

                <button
                    type="button"
                    @click="resetFilters"
                    class="text-[10px] font-bold text-surface-muted hover:text-danger-500 underline border-none bg-transparent cursor-pointer shrink-0"
                >
                    Limpar
                </button>
            </div>

            <!-- Blocos de Locais Físicos (Exibição vs Armazenamento) -->
            <template v-if="locationBlocks.length > 0">
                <div
                    v-for="block in locationBlocks"
                    :key="block.location_id"
                    class="bg-surface-2 rounded-2xl border border-surface-border overflow-hidden shadow-sm transition-all flex flex-col"
                >
                    <!-- Cabeçalho do Local Físico -->
                    <div class="px-4 py-3 bg-surface-3/50 border-b border-surface-border flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <i class="pi text-sm shrink-0"
                               :class="block.type === 'display' ? 'pi-eye text-primary-500' : 'pi-box text-amber-500'"></i>
                            <div>
                                <h3 class="font-bold text-surface-text uppercase tracking-wider text-xs sm:text-sm truncate">
                                    {{ block.location_name }}
                                </h3>
                                <span class="text-[9px] font-black uppercase tracking-widest text-surface-muted">
                                    {{ block.type === 'display' ? 'Local de Exibição / Ponto de Venda' : 'Local de Armazenamento / Estoque' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="bg-surface-border text-surface-text text-[10px] px-2 py-0.5 rounded-full font-black">
                                {{ block.items.length }} {{ block.items.length === 1 ? 'item' : 'itens' }}
                            </span>
                        </div>
                    </div>

                    <!-- Lista corrida de itens daquele local -->
                    <div class="flex flex-col divide-y divide-surface-border/40">
                        <div
                            v-for="item in block.items"
                            :key="`${block.location_id}_${item.id}`"
                            class="p-3.5 flex flex-col gap-2 transition-colors relative"
                            :class="activeMode === 'inventory' && isTouched(block.location_id, item.id)
                                ? 'bg-success-500/5 dark:bg-emerald-500/5'
                                : 'hover:bg-surface-1/40'"
                        >
                            <!-- ============================================== -->
                            <!-- A. MODO INVENTÁRIO (CONTAGEM POR LOCAL)        -->
                            <!-- ============================================== -->
                            <template v-if="activeMode === 'inventory'">
                                <!-- ROW 1: 80% Nome e Categoria | 20% Saldo Atual em Destaque -->
                                <div class="flex items-baseline justify-between gap-2 w-full">
                                    <div class="w-[80%] min-w-0">
                                        <h4 class="font-bold text-surface-text leading-tight text-sm truncate" :title="item.name">
                                            {{ item.name }}
                                        </h4>
                                        <span class="text-[10px] text-surface-muted truncate block mt-0.5">
                                            {{ item.category?.name || 'Geral' }}
                                        </span>
                                    </div>

                                    <div class="w-[20%] shrink-0 text-right">
                                        <span class="text-[8px] font-bold uppercase tracking-wider block text-surface-muted/70 leading-none">Estoque</span>
                                        <span class="font-black text-surface-text text-sm">{{ Number(item.total_system_stock ?? 0) }}</span>
                                        <span class="text-[9px] font-bold text-surface-muted ml-0.5">{{ item.unit_of_measure }}</span>
                                    </div>
                                </div>

                                <!-- ROW 2: Stepper (com traço '--') + Badge de Divergência -->
                                <div class="flex items-center justify-between gap-2 w-full pt-1">
                                    <div class="flex items-center justify-between rounded-full bg-surface-2 border border-surface-border/60 h-8 w-28 p-0.5 shrink-0 select-none">
                                        <button
                                            type="button"
                                            @click.stop="decrementCount(block.location_id, item)"
                                            class="w-7 h-7 rounded-full flex items-center justify-center text-surface-text hover:bg-surface-3 active:scale-90 transition-all border-none bg-transparent cursor-pointer"
                                            title="Diminuir contagem"
                                        >
                                            <i class="pi pi-minus text-[9px] font-black"></i>
                                        </button>

                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            :value="getDisplayValue(block.location_id, item.id)"
                                            @input="handleInputCount(block.location_id, item, $event.target.value)"
                                            class="w-12 text-center bg-transparent border-none text-xs font-black outline-none p-0 cursor-text"
                                            :class="isTouched(block.location_id, item.id) ? 'text-surface-text font-black' : 'text-surface-muted font-bold'"
                                            placeholder="--"
                                            @click.stop
                                        />

                                        <button
                                            type="button"
                                            @click.stop="incrementCount(block.location_id, item)"
                                            class="w-7 h-7 rounded-full flex items-center justify-center text-surface-text hover:bg-primary-500/10 active:scale-90 transition-all border-none bg-transparent cursor-pointer"
                                            title="Aumentar contagem"
                                        >
                                            <i class="pi pi-plus text-[9px] font-black"></i>
                                        </button>
                                    </div>

                                    <!-- Badge indicando divergência calculada contra o estoque real -->
                                    <div class="flex-1 min-w-0 flex items-center justify-end">
                                        <span
                                            v-if="isTouched(block.location_id, item.id)"
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1 truncate max-w-full"
                                            :class="getItemDelta(block.location_id, item) < 0
                                                ? 'bg-danger-500/15 text-danger-500 border border-danger-500/30'
                                                : (getItemDelta(block.location_id, item) > 0
                                                    ? 'bg-emerald-500/15 text-emerald-500 border border-emerald-500/30'
                                                    : 'bg-surface-3 text-surface-muted border border-surface-border/50')"
                                        >
                                            <i v-if="getItemDelta(block.location_id, item) !== 0"
                                               :class="getItemDelta(block.location_id, item) < 0 ? 'pi pi-arrow-down' : 'pi pi-arrow-up'" class="text-[8px]"></i>
                                            <i v-else class="pi pi-check text-[8px]"></i>
                                            <span class="truncate">{{ formatDeltaLabel(block.location_id, item) }}</span>
                                        </span>

                                        <span
                                            v-else
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold text-surface-muted/60 bg-surface-3/30 border border-surface-border/30"
                                        >
                                            Não contado (—)
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <!-- ============================================== -->
                            <!-- B. MODO STATUS (DISPONÍVEL/PAUSADO/ESGOTADO)   -->
                            <!-- ============================================== -->
                            <template v-else>
                                <div class="flex items-center justify-between gap-3 w-full">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-bold text-surface-text leading-tight text-sm truncate" :title="item.name">
                                            {{ item.name }}
                                        </h4>
                                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-surface-muted">
                                            <span class="truncate max-w-[140px]">{{ item.category?.name || 'Geral' }}</span>
                                            <span>• Estoque: <strong class="text-surface-text">{{ Number(item.total_system_stock ?? 0) }}</strong> {{ item.unit_of_measure }}</span>
                                        </div>
                                    </div>

                                    <div class="shrink-0 flex items-center bg-surface-1/80 p-0.5 rounded-full border border-surface-border/60 gap-0.5 shadow-2xs">
                                        <button
                                            type="button"
                                            @click="updateStatus(item, 'available')"
                                            class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                                            :class="item.status === 'available' ? 'bg-emerald-500 text-white shadow-xs' : 'text-surface-muted hover:text-emerald-500 hover:bg-emerald-500/10'"
                                            title="Disponível"
                                        >
                                            <i class="pi pi-check text-[10px] font-bold"></i>
                                        </button>
                                        <button
                                            type="button"
                                            @click="updateStatus(item, 'paused')"
                                            class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                                            :class="item.status === 'paused' ? 'bg-amber-500 text-white shadow-xs' : 'text-surface-muted hover:text-amber-500 hover:bg-amber-500/10'"
                                            title="Pausar"
                                        >
                                            <i class="pi pi-pause text-[10px] font-bold"></i>
                                        </button>
                                        <button
                                            type="button"
                                            @click="updateStatus(item, 'out_of_stock')"
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
                    </div>
                </div>
            </template>

            <div v-else class="flex flex-col items-center justify-center py-20 text-surface-muted opacity-60">
                <i class="pi pi-box text-5xl mb-3 text-primary-500/40"></i>
                <h3 class="text-lg font-bold text-center">Nenhum item encontrado</h3>
                <p class="text-xs mt-1 text-center max-w-xs">Tente ajustar os filtros ou o termo de busca.</p>
            </div>
        </div>

        <!-- 🚀 BARRA FIXA INFERIOR COM SLIDETOCONFIRM GERAL (MODO INVENTÁRIO) -->
        <Transition name="slide-up">
            <div
                v-if="activeMode === 'inventory' && touchedItemsCount > 0"
                class="fixed bottom-0 inset-x-0 z-40 bg-surface-1/95 border-t border-surface-border/60 backdrop-blur-xl p-3 px-4 flex flex-col gap-2 shadow-2xl animate-in slide-in-from-bottom-6"
            >
                <div class="flex items-center justify-between text-xs font-bold px-1">
                    <span class="flex items-center gap-1.5 text-surface-text">
                        <i class="pi pi-clipboard text-primary-smart"></i>
                        <span><strong>{{ touchedItemsCount }}</strong> {{ touchedItemsCount === 1 ? 'item contado' : 'itens contados' }}</span>
                    </span>

                    <span v-if="divergencesSummary.total !== 0" class="text-[11px] font-black" :class="divergencesSummary.losses > 0 ? 'text-danger-500' : 'text-emerald-500'">
                        {{ divergencesSummary.text }}
                    </span>
                    <span v-else class="text-[11px] font-black text-surface-muted">
                        Tudo exato com o sistema
                    </span>
                </div>

                <SlideToConfirm
                    text="Deslize para Confirmar Balanço"
                    success-text="Gravando Balanço..."
                    :color="divergencesSummary.losses > 0 ? 'danger' : 'primary'"
                    icon="pi-check"
                    format="long"
                    class="!h-11 text-xs w-full shadow-lg"
                    @confirm="submitBatchAdjustment"
                />
            </div>
        </Transition>

        <!-- 🎛️ ADAPTIVEMODAL: FILTROS DE ESTOQUE -->
        <AdaptiveModal v-model:visible="isFilterModalOpen">
            <div class="flex flex-col w-full text-surface-text select-none">
                <div class="flex items-center justify-between p-4 border-b border-surface-border/40">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-primary-500/10 text-primary-smart flex items-center justify-center font-black">
                            <i class="pi pi-sliders-h text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black tracking-tight leading-tight">Filtros de Estoque</h3>
                            <p class="text-[10px] font-bold text-surface-muted uppercase tracking-wider mt-0.5">Modo e escopo da lista</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isFilterModalOpen = false"
                        class="w-8 h-8 rounded-full bg-surface-2 text-surface-muted hover:text-surface-text flex items-center justify-center border-none cursor-pointer"
                    >
                        <i class="pi pi-times text-xs"></i>
                    </button>
                </div>

                <div class="p-4 flex flex-col gap-5 overflow-y-auto max-h-[70dvh] hide-scrollbar">
                    <!-- 1. Modo de Operação -->
                    <div class="flex flex-col gap-2 items-start">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted flex items-center gap-1.5">
                            <i class="pi pi-bolt text-primary-smart text-xs"></i> Modo de Operação
                        </span>
                        <SegmentedControl
                            v-model="activeMode"
                            :options="modeOptions"
                            class="!h-9 text-xs w-fit !mx-0"
                        />
                    </div>

                    <!-- 2. Local / Praça -->
                    <div class="flex flex-col gap-2 items-start w-full">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted flex items-center gap-1.5">
                            <i class="pi pi-map-marker text-primary-smart text-xs"></i> Local / Praça
                        </span>
                        <div class="w-full overflow-x-auto hide-scrollbar pb-1">
                            <SegmentedControl
                                v-model="selectedLocation"
                                :options="formattedLocationOptions"
                                class="!h-9 text-xs w-fit shrink-0 !mx-0"
                            />
                        </div>
                    </div>

                    <!-- 3. Tipo de Item -->
                    <div class="flex flex-col gap-2 items-start w-full">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted flex items-center gap-1.5">
                            <i class="pi pi-tag text-primary-smart text-xs"></i> Tipo de Item
                        </span>
                        <div class="w-full overflow-x-auto hide-scrollbar pb-1">
                            <SegmentedControl
                                v-model="selectedType"
                                :options="typeFilters"
                                class="!h-9 text-xs w-fit shrink-0 !mx-0"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 p-4 border-t border-surface-border/40 bg-surface-2/30">
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        @click="resetFilters"
                        class="text-xs font-bold text-surface-muted hover:text-surface-text underline bg-transparent border-none cursor-pointer"
                    >
                        Limpar Filtros
                    </button>
                    <div v-else></div>

                    <button
                        type="button"
                        @click="isFilterModalOpen = false"
                        class="h-10 px-6 rounded-2xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider shadow-md border-none cursor-pointer flex items-center gap-2 active:scale-95"
                    >
                        <i class="pi pi-check text-xs font-bold"></i>
                        <span>Aplicar</span>
                    </button>
                </div>
            </div>
        </AdaptiveModal>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

import ExpandableSearch from '@/Components/UI/ExpandableSearch.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

const props = defineProps({
    catalog: Array,
    isActive: { type: Boolean, default: false }
});

const toast = useToast();
const isMounted = ref(false);
const isFilterModalOpen = ref(false);

const activeMode = ref('inventory');
const selectedLocation = ref('all');
const selectedType = ref('all');
const searchQuery = ref('');

const draftCounts = ref({});

onMounted(() => { isMounted.value = true; });

const modeOptions = [
    { label: 'Status', value: 'status', icon: 'pi-bolt' },
    { label: 'Inventário', value: 'inventory', icon: 'pi-clipboard' }
];

const typeFilters = [
    { label: 'Todos', value: 'all', icon: 'pi-th-large' },
    { label: 'Revenda', value: 'resale', icon: 'pi-shopping-bag' },
    { label: 'Insumos', value: 'ingredients', icon: 'pi-apple' },
    { label: 'Embalagens', value: 'supplies', icon: 'pi-box' },
];

const hasActiveFilters = computed(() => {
    return selectedType.value !== 'all' || selectedLocation.value !== 'all';
});

const resetFilters = () => {
    selectedType.value = 'all';
    selectedLocation.value = 'all';
};

// =========================================================================
// 🗂️ PROCESSADOR DE LOCAIS E PRODUTOS (EXIBIÇÃO VS ARMAZENAMENTO)
// =========================================================================
const locationBlocks = computed(() => {
    const rawCatalog = props.catalog || [];
    const term = (searchQuery.value || '').trim().toLowerCase();
    const blocksMap = new Map();

    rawCatalog.forEach(prod => {
        if (activeMode.value === 'inventory' && !prod.track_inventory) return;

        if (term) {
            const matches = (prod.name || '').toLowerCase().includes(term) ||
                            (prod.category?.name || '').toLowerCase().includes(term) ||
                            (prod.sku || '').toLowerCase().includes(term) ||
                            String(prod.id).includes(term);
            if (!matches) return;
        }

        const type = (prod.type || 'resale').toLowerCase();
        const section = (type === 'resale') ? 'resale' : (type === 'ingredient' ? 'ingredients' : 'supplies');
        if (selectedType.value !== 'all' && selectedType.value !== section) return;

        const locations = Array.isArray(prod.storage_locations) && prod.storage_locations.length > 0
            ? prod.storage_locations
            : [{ id: 0, name: 'Estoque Geral / Salão', type: 'storage' }];

        const realStock = Number(prod.current_stock || 0);

        locations.forEach(loc => {
            const locId = loc.id || 0;
            if (selectedLocation.value !== 'all' && String(selectedLocation.value) !== String(locId)) return;

            if (!blocksMap.has(locId)) {
                blocksMap.set(locId, {
                    location_id: locId,
                    location_name: loc.name || 'Estoque Geral',
                    type: loc.type || 'storage',
                    items: []
                });
            }

            blocksMap.get(locId).items.push({
                ...prod,
                local_expected_stock: realStock,
                total_system_stock: realStock
            });
        });
    });

    return Array.from(blocksMap.values()).sort((a, b) => {
        if (a.type !== b.type) return a.type === 'display' ? -1 : 1;
        return a.location_name.localeCompare(b.location_name);
    });
});

const locationOptions = computed(() => {
    const list = [{ label: 'Todos os Locais', value: 'all' }];
    locationBlocks.value.forEach(b => {
        list.push({ label: b.location_name, value: String(b.location_id) });
    });
    return list;
});

const formattedLocationOptions = computed(() => {
    return locationOptions.value.map(loc => ({
        label: loc.label,
        value: loc.value,
        icon: loc.value === 'all' ? 'pi-globe' : 'pi-map-marker'
    }));
});

// =========================================================================
// 🧮 LÓGICA DE CONTAGEM: TRAÇO '--' (NÃO MEXIDO) VS ZERO REAL (0)
// =========================================================================
const makeKey = (locationId, productId) => `${locationId || 0}_${productId}`;

const isTouched = (locationId, productId) => {
    return draftCounts.value[makeKey(locationId, productId)] !== undefined &&
           draftCounts.value[makeKey(locationId, productId)] !== null;
};

const getDisplayValue = (locationId, productId) => {
    const val = draftCounts.value[makeKey(locationId, productId)];
    return (val !== undefined && val !== null) ? val : '--';
};

const getCountValue = (locationId, productId) => {
    const val = draftCounts.value[makeKey(locationId, productId)];
    return (val !== undefined && val !== null) ? Number(val) : null;
};

const incrementCount = (locationId, item) => {
    const key = makeKey(locationId, item.id);
    const current = draftCounts.value[key];

    if (current === undefined || current === null) {
        draftCounts.value[key] = 1;
    } else {
        const step = (item.unit_of_measure === 'kg' || item.unit_of_measure === 'l') ? 0.5 : 1;
        draftCounts.value[key] = Number((current + step).toFixed(3));
    }
};

const decrementCount = (locationId, item) => {
    const key = makeKey(locationId, item.id);
    const current = draftCounts.value[key];

    if (current === undefined || current === null) {
        draftCounts.value[key] = 0;
    } else {
        const step = (item.unit_of_measure === 'kg' || item.unit_of_measure === 'l') ? 0.5 : 1;
        draftCounts.value[key] = Math.max(0, Number((current - step).toFixed(3)));
    }
};

const handleInputCount = (locationId, item, rawVal) => {
    const key = makeKey(locationId, item.id);
    if (rawVal === '' || rawVal === null || rawVal === undefined || rawVal === '--') {
        delete draftCounts.value[key];
        return;
    }
    const cleanNum = Number(rawVal.replace(',', '.'));
    if (!isNaN(cleanNum) && cleanNum >= 0) {
        draftCounts.value[key] = cleanNum;
    }
};

const getItemDelta = (locationId, item) => {
    const counted = getCountValue(locationId, item.id);
    if (counted === null) return 0;
    const expected = Number(item.total_system_stock ?? 0);
    return Number((counted - expected).toFixed(3));
};

const formatDeltaLabel = (locationId, item) => {
    const delta = getItemDelta(locationId, item);
    if (delta < 0) return `${delta} ${item.unit_of_measure} (Perda)`;
    if (delta > 0) return `+${delta} ${item.unit_of_measure} (Sobra)`;
    return 'Exato';
};

// =========================================================================
// 🚀 RESUMO DA RODADA & SUBMISSÃO EM LOTE VIA SLIDETOCONFIRM
// =========================================================================
const touchedItemsCount = computed(() => {
    return Object.keys(draftCounts.value).filter(k => draftCounts.value[k] !== null && draftCounts.value[k] !== undefined).length;
});

const divergencesSummary = computed(() => {
    let losses = 0;
    let surpluses = 0;

    locationBlocks.value.forEach(block => {
        block.items.forEach(item => {
            if (isTouched(block.location_id, item.id)) {
                const delta = getItemDelta(block.location_id, item);
                if (delta < 0) losses++;
                if (delta > 0) surpluses++;
            }
        });
    });

    const total = losses + surpluses;
    let text = `${total} divergências`;
    if (losses > 0 && surpluses > 0) text += ` (-${losses} perdas, +${surpluses} sobras)`;
    else if (losses > 0) text += ` (-${losses} perdas)`;
    else if (surpluses > 0) text += ` (+${surpluses} sobras)`;

    return { total, losses, surpluses, text };
});

const submitBatchAdjustment = () => {
    if (touchedItemsCount.value === 0) return;

    const payloadItems = [];

    locationBlocks.value.forEach(block => {
        block.items.forEach(item => {
            const counted = getCountValue(block.location_id, item.id);
            if (counted !== null) {
                payloadItems.push({
                    product_id: item.id,
                    location_id: block.location_id !== 0 ? block.location_id : null,
                    counted_stock: counted,
                });
            }
        });
    });

    router.post(route('operations.inventory.batch-adjust'), {
        items: payloadItems,
        reason: 'Balanço Físico Geral (TeamApp)'
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            draftCounts.value = {};
            toast.add({
                severity: 'success',
                summary: 'Balanço Gravado',
                detail: `${payloadItems.length} itens tiveram seu estoque físico atualizado.`,
                life: 4000
            });
        },
        onError: (err) => {
            toast.add({
                severity: 'error',
                summary: 'Falha no Balanço',
                detail: Object.values(err)[0] || 'Erro ao processar contagem.',
                life: 5000
            });
        }
    });
};

const updateStatus = (item, targetStatus) => {
    router.post(route('operations.availability.toggle'), {
        product_id: item.id,
        action: targetStatus,
        new_stock: targetStatus === 'out_of_stock' ? 0 : null
    }, { preserveScroll: true, preserveState: true });
};
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
