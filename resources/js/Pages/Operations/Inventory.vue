<template>
    <div class="flex flex-col h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans p-2 relative">

        <!-- 🚀 TELEPORT DYNAMIC ISLAND (DESKTOP POS) -->
        <Teleport v-if="isMounted && isActive && portalTarget" :to="portalTarget">
            <div class="flex items-center h-full px-1 gap-2 animate-in fade-in duration-200">
                <ExpandableSearch v-model="searchQuery" placeholder="Buscar insumo ou produto..." class="flex-1 min-w-[140px]" />
                <SegmentedControl
                    v-model="selectedType"
                    :options="typeFilters"
                    class="!mx-0 shrink-0"
                />
            </div>
        </Teleport>

        <div class="flex-1 flex flex-col h-full bg-surface-2 rounded-[2rem] overflow-hidden min-h-0 animate-in fade-in duration-300">

            <!-- 📍 BARRA DE PRAÇAS / LOCAIS FÍSICOS NO TOPO DO ARQUIVO -->
            <div class="p-3 md:px-6 md:pt-4 md:pb-2 border-b border-surface-border/40 shrink-0 flex items-center gap-2 overflow-x-auto hide-scrollbar">
                <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted shrink-0 flex items-center gap-1 mr-1">
                    <i class="pi pi-map-marker text-primary-smart text-xs"></i> Local:
                </span>

                <SegmentedControl
                    v-model="selectedLocation"
                    :options="formattedLocationOptions"
                    class="!mx-0 shrink-0"
                />
            </div>

            <!-- CONTEÚDO PRINCIPAL: BLOCOS HIERÁRQUICOS DE CARDS -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-surface-1/30 hide-scrollbar flex flex-col gap-6">

                <template v-if="groupedCatalog.length > 0">
                    <div
                        v-for="locGroup in groupedCatalog"
                        :key="locGroup.id"
                        class="flex flex-col gap-4 bg-surface-2/40 border border-surface-border/50 p-4 md:p-5 rounded-3xl"
                    >
                        <!-- CABEÇALHO DO LOCAL -->
                        <div class="flex items-center justify-between border-b border-surface-border/40 pb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary-500/10 text-primary-smart flex items-center justify-center font-black">
                                    <i class="pi pi-compass text-sm"></i>
                                </div>
                                <div>
                                    <h2 class="font-black text-base text-surface-text leading-tight">{{ locGroup.name }}</h2>
                                    <p class="text-[10px] font-bold text-surface-muted uppercase tracking-wider mt-0.5">
                                        {{ locGroup.totalCount }} itens monitorados
                                    </p>
                                </div>
                            </div>

                            <span
                                v-if="locGroup.criticalCount > 0"
                                class="bg-danger-500/10 border border-danger-500/20 text-danger-500 text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full flex items-center gap-1"
                            >
                                <i class="pi pi-exclamation-triangle text-xs"></i> {{ locGroup.criticalCount }} com atenção
                            </span>
                        </div>

                        <!-- 🥤 SEÇÃO 1: PRODUTOS DE REVENDA -->
                        <div v-if="locGroup.resale.length > 0" class="flex flex-col gap-2">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-surface-text">
                                <i class="pi pi-shopping-bag text-primary-smart"></i>
                                <span>Produtos de Revenda</span>
                                <span class="text-[10px] text-surface-muted font-bold">({{ locGroup.resale.length }})</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                <InventoryCard
                                    v-for="item in locGroup.resale"
                                    :key="item.id"
                                    :item="item"
                                    @updateStatus="handleStatusUpdate"
                                />
                            </div>
                        </div>

                        <!-- 🥩 SEÇÃO 2: INSUMOS DA COZINHA -->
                        <div v-if="locGroup.ingredients.length > 0" class="flex flex-col gap-2">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-surface-text">
                                <i class="pi pi-apple text-amber-500"></i>
                                <span>Insumos & Ingredientes</span>
                                <span class="text-[10px] text-surface-muted font-bold">({{ locGroup.ingredients.length }})</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                <InventoryCard
                                    v-for="item in locGroup.ingredients"
                                    :key="item.id"
                                    :item="item"
                                    @updateStatus="handleStatusUpdate"
                                />
                            </div>
                        </div>

                        <!-- 📦 SEÇÃO 3: EMBALAGENS & SUPRIMENTOS -->
                        <div v-if="locGroup.supplies.length > 0" class="flex flex-col gap-2">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-surface-text">
                                <i class="pi pi-box text-blue-500"></i>
                                <span>Embalagens & Materiais</span>
                                <span class="text-[10px] text-surface-muted font-bold">({{ locGroup.supplies.length }})</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                <InventoryCard
                                    v-for="item in locGroup.supplies"
                                    :key="item.id"
                                    :item="item"
                                    @updateStatus="handleStatusUpdate"
                                />
                            </div>
                        </div>

                        <!-- 👨‍🍳 SEÇÃO 4: PREPAROS INTERNOS (PRODUÇÃO) -->
                        <div v-if="locGroup.manufactured.length > 0" class="flex flex-col gap-2">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-surface-text">
                                <i class="pi pi-cog text-purple-500"></i>
                                <span>Preparos da Casa (Produção)</span>
                                <span class="text-[10px] text-surface-muted font-bold">({{ locGroup.manufactured.length }})</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                <InventoryCard
                                    v-for="item in locGroup.manufactured"
                                    :key="item.id"
                                    :item="item"
                                    @updateStatus="handleStatusUpdate"
                                />
                            </div>
                        </div>

                    </div>
                </template>

                <EmptyState
                    v-else
                    icon="pi-box"
                    title="Nenhum item encontrado"
                    subtitle="Tente alterar o local selecionado ou o termo de busca."
                    class="mt-10"
                />

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { router } from '@inertiajs/vue3';
import DesktopLayout from '@/Layouts/DesktopLayout.vue';

import ExpandableSearch from "@/Components/UI/ExpandableSearch.vue";
import SegmentedControl from "@/Components/UI/SegmentedControl.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";
import InventoryCard from "@/Pages/Operations/Components/Catalog/InventoryCard.vue";

import { useAvailability } from "@/Pages/Shared/Composables/useAvailability";

defineOptions({ layout: DesktopLayout });

const props = defineProps({
    catalog: Array,
    isActive: {
        type: Boolean,
        default: false
    }
});

const toast = useToast();
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

const portalTarget = computed(() => {
    if (typeof document === 'undefined') return null;
    return document.getElementById('dynamic-island-portal') ? '#dynamic-island-portal' : null;
});

const typeFilters = [
    { label: 'Revenda', value: 'resale', icon: 'pi-shopping-bag' },
    { label: 'Insumos', value: 'ingredients', icon: 'pi-apple' },
    { label: 'Suprimentos', value: 'supplies', icon: 'pi-box' },
];

const catalogRef = computed(() => props.catalog || []);

const {
    selectedLocation,
    selectedType,
    searchQuery,
    locationOptions,
    groupedCatalog
} = useAvailability(catalogRef, toast);

if (selectedType.value === 'all') {
    selectedType.value = 'resale';
}

const formattedLocationOptions = computed(() => {
    return (locationOptions.value || []).map(loc => ({
        label: loc.critical > 0 ? `${loc.label} (${loc.critical})` : loc.label,
        value: loc.value,
        icon: loc.critical > 0 ? 'pi-exclamation-triangle' : (loc.value === 'all' ? 'pi-globe' : 'pi-map-marker')
    }));
});

const handleStatusUpdate = (payload) => {
    router.post(route('operations.availability.toggle'), {
        product_id: payload.item.id,
        action: payload.action,
        new_stock: payload.new_stock
    }, {
        preserveScroll: true,
        preserveState: true
    });
};
</script>
