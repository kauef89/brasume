<template>
    <div
        class="flex flex-col h-full overflow-y-auto hide-scrollbar relative pb-32"
        :style="{ paddingTop: 'var(--app-header-height, 7rem)' }"
    >
        <!-- 🔍 TELEPORT PARA O TEAM HEADER: BUSCA + NOVA RODADA (ZERAR) -->
        <Teleport to="#team-header-action-portal" v-if="isMounted">
            <div class="flex items-center gap-2">
                <ExpandableSearch
                    v-model="searchQuery"
                    placeholder="Buscar item..."
                    class="!w-36 sm:!w-48"
                />
                <button
                    @click="handleResetRound"
                    type="button"
                    class="w-9 h-9 rounded-full bg-surface-1/90 hover:bg-surface-2 text-surface-text border border-surface-border/60 transition-all flex items-center justify-center outline-none cursor-pointer shadow-sm active:scale-90 shrink-0"
                    title="Nova rodada (Zerar contadores)"
                >
                    <i class="pi pi-refresh text-xs"></i>
                </button>
            </div>
        </Teleport>

        <!-- CONTEÚDO PRINCIPAL -->
        <div class="p-4 md:p-6 flex-1 flex flex-col">

            <!-- LOADING -->
            <div v-if="isLoading && locations.length === 0" class="flex flex-col items-center justify-center py-20 opacity-50">
                <i class="pi pi-spin pi-spinner text-3xl mb-3 text-primary-500"></i>
                <span class="text-xs font-bold tracking-widest uppercase text-surface-muted">Carregando itens...</span>
            </div>

            <!-- ========================================================= -->
            <!-- 📋 PASSO 1: PRANCHETA (Apenas no Modo Livre)              -->
            <!-- ========================================================= -->
            <div v-else-if="activeSubTab === 'clipboard' && isFreeMode" class="flex flex-col gap-3">
                <div
                    v-for="loc in filteredClipboardLocations"
                    :key="loc.location_id"
                    class="bg-surface-2 rounded-2xl border border-surface-border overflow-hidden shadow-sm transition-all"
                >
                    <!-- Cabeçalho da Prancheta (Destino) -->
                    <button
                        type="button"
                        @click="toggleClipboardLocation(loc.location_id)"
                        class="w-full px-4 py-3 bg-surface-3/50 hover:bg-surface-3 transition-colors flex items-center justify-between gap-3 text-left outline-none cursor-pointer border-none"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <i class="pi pi-folder text-primary-500 text-sm shrink-0"></i>
                            <h3 class="font-bold text-surface-text uppercase tracking-wider text-xs sm:text-sm truncate">
                                {{ loc.location_name }}
                            </h3>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="bg-surface-border text-surface-text text-[10px] px-2 py-0.5 rounded-full font-black">
                                {{ loc.items?.length || 0 }} itens
                            </span>
                            <i
                                class="pi pi-chevron-down text-xs text-surface-muted transition-transform duration-300"
                                :class="{ 'rotate-180 text-primary-500': activeClipboardId === loc.location_id }"
                            ></i>
                        </div>
                    </button>

                    <!-- Lista de Itens Agrupados por Categoria -->
                    <div
                        v-show="activeClipboardId === loc.location_id"
                        class="flex flex-col divide-y divide-surface-border border-t border-surface-border animate-in fade-in duration-200"
                    >
                        <div
                            v-for="catGroup in groupItemsByCategory(loc.items)"
                            :key="catGroup.category_name"
                            class="flex flex-col"
                        >
                            <!-- 🏷️ SUB-CABEÇALHO DA CATEGORIA -->
                            <div class="px-4 py-2 bg-surface-3/40 border-b border-surface-border/50 flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-widest text-primary-smart flex items-center gap-1.5">
                                    <i class="pi pi-tag text-[9px]"></i>
                                    {{ catGroup.category_name }}
                                </span>
                                <span class="text-[9px] font-bold text-surface-muted bg-surface-2 px-2 py-0.5 rounded-full border border-surface-border/40">
                                    {{ catGroup.items.length }} {{ catGroup.items.length === 1 ? 'item' : 'itens' }}
                                </span>
                            </div>

                            <!-- Linhas de Itens da Categoria -->
                            <div class="flex flex-col divide-y divide-surface-border/40">
                                <div
                                    v-for="item in catGroup.items"
                                    :key="item.rule_id"
                                    class="p-3.5 sm:p-4 flex items-center justify-between gap-3 transition-colors"
                                    :class="getItemQty(item.rule_id) > 0 ? 'bg-success-400/10 hover:bg-success-400/20' : 'hover:bg-surface-1/40'"
                                >
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-bold text-surface-text leading-tight text-sm line-clamp-2">
                                            {{ item.name }}
                                        </h4>
                                        <p v-if="item.max_capacity > 0" class="text-[10px] text-surface-muted mt-0.5">
                                            Capacidade: {{ item.current_stock }} / {{ item.max_capacity }} {{ item.unit_of_measure }}
                                        </p>
                                    </div>

                                    <!-- 🚀 Stepper com limite destravado e mutação reativa -->
                                    <div class="shrink-0">
                                        <QtyStepper
                                            :model-value="getItemQty(item.rule_id)"
                                            @update:model-value="setItemQty(item.rule_id, $event)"
                                            :min="0"
                                            :max="getItemMax(item)"
                                            size="sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botão de avanço inferior -->
                <div v-if="totalBoxItemsCount > 0" class="mt-6 flex justify-center pb-8 animate-in slide-in-from-bottom-4">
                    <button
                        type="button"
                        @click="activeSubTab = 'box'"
                        class="px-6 py-3 rounded-2xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider flex items-center gap-2.5 shadow-lg active:scale-95 transition-all outline-none border-none cursor-pointer"
                    >
                        <i class="pi pi-box"></i>
                        <span>Ir para Coleta ({{ totalBoxItemsCount }} itens)</span>
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- 📦 PASSO 2: CAIXA (Agrupado por Local de Coleta/Origem)    -->
            <!-- ========================================================= -->
            <div v-else-if="activeSubTab === 'box' && filteredBoxGroups.length > 0" class="flex flex-col gap-3">
                <div
                    v-for="group in filteredBoxGroups"
                    :key="group.source_name"
                    class="bg-surface-2 rounded-2xl border border-surface-border overflow-hidden shadow-sm transition-all"
                >
                    <button
                        type="button"
                        @click="toggleBoxGroup(group.source_name)"
                        class="w-full px-4 py-3 bg-surface-3/50 hover:bg-surface-3 transition-colors flex items-center justify-between gap-3 text-left outline-none cursor-pointer border-none"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <i class="pi pi-box text-amber-500 text-sm shrink-0"></i>
                            <h3 class="font-bold text-surface-text uppercase tracking-wider text-xs sm:text-sm truncate">
                                Coletar em: {{ group.source_name }}
                            </h3>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="bg-surface-border text-surface-text text-[10px] px-2 py-0.5 rounded-full font-black">
                                {{ group.items.length }}
                            </span>
                            <i
                                class="pi pi-chevron-down text-xs text-surface-muted transition-transform duration-300"
                                :class="{ 'rotate-180 text-primary-500': activeBoxSource === group.source_name }"
                            ></i>
                        </div>
                    </button>

                    <div
                        v-show="activeBoxSource === group.source_name"
                        class="flex flex-col divide-y divide-surface-border border-t border-surface-border animate-in fade-in duration-200"
                    >
                        <div
                            v-for="catGroup in groupItemsByCategory(group.items)"
                            :key="catGroup.category_name"
                            class="flex flex-col"
                        >
                            <!-- 🏷️ SUB-CABEÇALHO DA CATEGORIA NA CAIXA -->
                            <div class="px-4 py-1.5 bg-surface-3/40 border-b border-surface-border/50 flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-widest text-primary-smart flex items-center gap-1.5">
                                    <i class="pi pi-tag text-[9px]"></i>
                                    {{ catGroup.category_name }}
                                </span>
                                <span class="text-[9px] font-bold text-surface-muted bg-surface-2 px-2 py-0.5 rounded-full border border-surface-border/40">
                                    {{ catGroup.items.length }} {{ catGroup.items.length === 1 ? 'item' : 'itens' }}
                                </span>
                            </div>

                            <div class="flex flex-col divide-y divide-surface-border/40">
                                <div
                                    v-for="item in catGroup.items"
                                    :key="item.rule_id"
                                    class="p-3.5 sm:p-4 flex flex-col gap-3 hover:bg-surface-1/40 transition-colors"
                                >
                                    <!-- LINHA 1: INFORMAÇÕES DO PRODUTO -->
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-surface-text leading-tight text-sm line-clamp-2">
                                                {{ item.name }}
                                            </h4>
                                            <span
                                                v-if="!isFreeMode && item.is_critical"
                                                class="bg-danger-500 text-white text-[9px] font-black px-1.5 py-0.5 rounded tracking-wide shrink-0"
                                            >
                                                ALERTA
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-surface-muted uppercase tracking-wider mt-1 truncate">
                                            Levar para: <span class="font-bold text-primary-500">{{ item.destination_name }}</span>
                                        </p>
                                        <div v-if="!isFreeMode" class="flex items-center gap-3 text-xs mt-1 text-surface-muted font-medium">
                                            <span>Na prateleira: <strong class="text-surface-text font-bold">{{ item.current_stock }}</strong> / {{ item.max_capacity }} {{ item.unit_of_measure }}</span>
                                        </div>
                                    </div>

                                    <!-- LINHA 2: DISTRIBUIÇÃO JUSTIFICADA -->
                                    <div class="flex items-center justify-between pt-3 border-t border-surface-border/40">
                                        <div class="h-[38px] px-3.5 rounded-xl bg-primary-500/10 border border-primary-500/25 text-primary-smart flex items-center justify-center font-black text-sm shrink-0">
                                            +{{ item.qty_to_collect }} <span class="text-[10px] font-bold ml-1 text-surface-muted">{{ item.unit_of_measure }}</span>
                                        </div>

                                        <div class="flex items-center gap-2.5">
                                            <button
                                                @click="handleConfirm(item)"
                                                type="button"
                                                class="h-[38px] px-4 rounded-xl bg-success-500 text-white shadow-sm hover:bg-success-600 active:scale-95 transition-all flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider outline-none cursor-pointer border-none"
                                            >
                                                <i class="pi pi-check text-xs font-black"></i>
                                                <span>Feito</span>
                                            </button>

                                            <button
                                                @click="handleOpenAvailability(item, { location_name: item.destination_name })"
                                                type="button"
                                                title="Marcar como Em Falta no Depósito"
                                                class="w-[38px] h-[38px] rounded-xl bg-surface-2 hover:bg-danger-500 hover:text-white border border-surface-border transition-colors flex items-center justify-center outline-none cursor-pointer shrink-0 text-surface-muted active:scale-95"
                                            >
                                                <i class="pi pi-ban text-xs font-bold"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EMPTY STATE -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-surface-muted opacity-60">
                <i class="pi pi-check-circle text-5xl mb-3 text-success-500/50"></i>
                <h3 class="text-lg font-bold text-center">
                    {{ activeSubTab === 'box' && isFreeMode ? 'Nenhum item na caixa de coleta.' : 'Tudo Abastecido!' }}
                </h3>
                <p class="text-xs mt-1 text-center max-w-xs">
                    <span v-if="activeSubTab === 'box' && isFreeMode">
                        Acesse a <strong>Prancheta</strong> para anotar os produtos que estão faltando no salão.
                    </span>
                    <span v-else>
                        Nenhuma pendência encontrada.
                    </span>
                </p>
                <button
                    v-if="activeSubTab === 'box' && isFreeMode"
                    type="button"
                    @click="activeSubTab = 'clipboard'"
                    class="mt-4 px-4 py-2 rounded-xl bg-surface-3 hover:bg-surface-border text-surface-text text-xs font-bold outline-none cursor-pointer border-none"
                >
                    Voltar para a Prancheta
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRestock as useRestockFallback } from '@/Pages/Shared/Composables/useRestock';
import { useTenantEcho } from '@/Composables/useTenantEcho';
import ExpandableSearch from '@/Components/UI/ExpandableSearch.vue';
import QtyStepper from '@/Components/UI/QtyStepper.vue';

const props = defineProps({
    useRestockData: { type: Object, default: null },
    shiftId: { type: [Number, String], default: null }
});

const emit = defineEmits(['trigger-availability']);

const restock = props.useRestockData || useRestockFallback(props.shiftId);

const {
    locations, isLoading, restockDrafts, fetchRestockBoard, resetForNewRound, confirmRestock,
    openAvailability, activeSubTab, isFreeMode, totalBoxItemsCount
} = restock;

// 🚀 FILTRO: Mantém apenas produtos de revenda (type = resale)
const resaleLocations = computed(() => {
    return (locations.value || []).map(loc => ({
        ...loc,
        items: (loc.items || []).filter(item => item.type === 'resale')
    })).filter(loc => loc.items.length > 0);
});

const { listenChannel } = useTenantEcho();

listenChannel('catalog', '.catalog.updated', () => {
    fetchRestockBoard(true);
});

listenChannel('shift', '.shift.opened', () => {
    resetForNewRound();
    fetchRestockBoard(true, true);
});

const isMounted = ref(false);
const searchQuery = ref('');

const activeClipboardId = ref(null);
const activeBoxSource = ref(null);

onMounted(() => {
    isMounted.value = true;
    fetchRestockBoard();
});

const handleResetRound = () => {
    resetForNewRound();
};

// 🚀 Limite do Stepper: No modo livre, nunca bloqueia no zero
const getItemMax = (item) => {
    if (isFreeMode.value) {
        return 999;
    }
    const diff = Number(item.max_capacity || 0) - Number(item.current_stock || 0);
    return diff > 0 ? diff : 999;
};

// 🚀 Leitura segura que nunca devolve undefined/NaN para o Stepper
const getItemQty = (ruleId) => {
    return Number(restockDrafts.value?.[ruleId] ?? 0);
};

// 🚀 Mutação atômica que força o Vue e o localStorage a dispararem as atualizações
const setItemQty = (ruleId, val) => {
    restockDrafts.value = {
        ...restockDrafts.value,
        [ruleId]: Math.max(0, Number(val || 0))
    };
};

// 🏷️ Agrupa os itens da praça por categoria
const groupItemsByCategory = (items = []) => {
    const groups = {};
    items.forEach(item => {
        const catName = item.category_name || 'Geral';
        if (!groups[catName]) {
            groups[catName] = {
                category_name: catName,
                category_order: item.category_order ?? 999,
                items: []
            };
        }
        groups[catName].items.push(item);
    });

    return Object.values(groups).sort((a, b) => {
        if (a.category_order !== b.category_order) return a.category_order - b.category_order;
        return a.category_name.localeCompare(b.category_name, 'pt-BR');
    });
};

// ==========================================
// 📋 PASSO 1: PRANCHETA
// ==========================================
const filteredClipboardLocations = computed(() => {
    if (!searchQuery.value || !searchQuery.value.trim()) return resaleLocations.value;
    const q = searchQuery.value.toLowerCase().trim();

    return resaleLocations.value.map(loc => {
        const matchesLoc = loc.location_name?.toLowerCase().includes(q);
        const filteredItems = (loc.items || []).filter(item =>
            item.name?.toLowerCase().includes(q) ||
            item.category_name?.toLowerCase().includes(q) ||
            item.source_location?.toLowerCase().includes(q)
        );

        if (matchesLoc) return loc;
        if (filteredItems.length > 0) return { ...loc, items: filteredItems };
        return null;
    }).filter(Boolean);
});

watch(filteredClipboardLocations, (newLocs) => {
    if (newLocs.length > 0 && !newLocs.some(l => l.location_id === activeClipboardId.value)) {
        activeClipboardId.value = newLocs[0].location_id;
    }
}, { immediate: true });

const toggleClipboardLocation = (id) => {
    activeClipboardId.value = activeClipboardId.value === id ? null : id;
};

// ==========================================
// 📦 PASSO 2: CAIXA
// ==========================================
const boxGroups = computed(() => {
    const groups = {};

    resaleLocations.value.forEach(loc => {
        const list = loc.items || [];
        list.forEach(item => {
            const qty = isFreeMode.value
                ? Number(restockDrafts.value[item.rule_id] || 0)
                : Number(item.suggested_restock || 0);

            if (qty > 0 || (!isFreeMode.value && item.is_critical)) {
                const sourceKey = item.source_location || 'Almoxarifado Principal';
                if (!groups[sourceKey]) {
                    groups[sourceKey] = {
                        source_name: sourceKey,
                        items: []
                    };
                }

                groups[sourceKey].items.push({
                    ...item,
                    destination_name: loc.location_name,
                    destination_location_id: loc.location_id,
                    category_name: item.category_name || 'Geral',
                    category_order: item.category_order ?? 999,
                    qty_to_collect: qty
                });
            }
        });
    });

    return Object.values(groups);
});

const filteredBoxGroups = computed(() => {
    if (!searchQuery.value || !searchQuery.value.trim()) return boxGroups.value;
    const q = searchQuery.value.toLowerCase().trim();

    return boxGroups.value.map(group => {
        const matchesSource = group.source_name?.toLowerCase().includes(q);
        const filteredItems = group.items.filter(item =>
            item.name?.toLowerCase().includes(q) ||
            item.category_name?.toLowerCase().includes(q) ||
            item.destination_name?.toLowerCase().includes(q)
        );

        if (matchesSource) return group;
        if (filteredItems.length > 0) return { ...group, items: filteredItems };
        return null;
    }).filter(Boolean);
});

watch(filteredBoxGroups, (newGroups) => {
    if (newGroups.length > 0 && !newGroups.some(g => g.source_name === activeBoxSource.value)) {
        activeBoxSource.value = newGroups[0].source_name;
    }
}, { immediate: true });

const toggleBoxGroup = (sourceName) => {
    activeBoxSource.value = activeBoxSource.value === sourceName ? null : sourceName;
};

const handleConfirm = (item) => {
    confirmRestock(item, isFreeMode.value ? 'free' : 'sales_driven');
};

const handleOpenAvailability = (item, loc) => {
    const payload = { ...item, location_name: loc.location_name };
    if (openAvailability) openAvailability(payload);
    emit('trigger-availability', payload);
};
</script>
