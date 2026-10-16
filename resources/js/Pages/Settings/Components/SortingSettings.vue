<template>
    <div class="flex flex-col gap-6 animate-in fade-in slide-in-from-bottom-2 duration-300">

        <div class="relative flex items-center w-full bg-surface-3 p-4 rounded-2xl border border-surface-border/50 shadow-sm">
            <i class="pi pi-search absolute left-8 text-surface-muted text-sm pointer-events-none"></i>
            <input type="text" v-model="searchQuery" placeholder="Digite o nome de uma categoria para localizar..." class="w-full h-12 pl-11 pr-10 rounded-xl bg-surface-1 border border-surface-border text-surface-text text-sm font-medium focus:ring-2 focus:ring-warning-500/30 focus:border-warning-500/50 outline-none transition-all" />
            <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-8 text-surface-muted hover:text-danger-smart transition-colors cursor-pointer outline-none border-none bg-transparent">
                <i class="pi fa-circle-xmark text-sm"></i>
            </button>
        </div>

        <div class="flex flex-col gap-4">
            <SettingCard title="Ordenação de Categorias" description="Defina como as categorias são listadas na tela inicial.">
                <SegmentedControl v-model="categorySort" :options="[{ label: 'A-Z', value: 'alpha' }, { label: 'Minha Ordem', value: 'custom' }]" />
            </SettingCard>

            <div v-if="categorySort === 'custom'" class="flex flex-col p-6 bg-surface-2 rounded-[1.5rem] border border-surface-border/50 animate-in fade-in slide-in-from-top-2 shadow-sm max-h-[calc(100vh-16rem)] overflow-y-auto">

                <div class="flex items-center justify-between mb-4 shrink-0">
                    <span class="text-sm font-medium text-surface-text flex items-center gap-2">
                        <i class="pi pi-braille text-surface-muted"></i> Arraste para organizar e encadear
                    </span>
                    <span class="text-[10px] bg-success-500/10 text-success-smart px-2 py-1 rounded-md font-black uppercase tracking-widest">Salva Automático</span>
                </div>

                <CategoryCloudNode
                    v-model="localCategories"
                    :search-query="searchQuery"
                    @save="autoSave('categories')"
                />
            </div>
        </div>

        <!-- SESSÃO: PRODUTOS (Apenas Filtros Pré-definidos) -->
        <div class="flex flex-col gap-4 mt-4">
            <SettingCard title="Ordenação de Produtos" description="Como os produtos aparecem dentro das categorias.">
                <SegmentedControl
                    v-model="productSort"
                    :options="[
                        { label: 'A-Z', value: 'alpha' },
                        { label: 'Maior R$', value: 'price_desc' },
                        { label: 'Menor R$', value: 'price_asc' }
                    ]"
                />
            </SettingCard>
        </div>

    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import SettingCard from "@/Pages/Settings/Components/SettingCard.vue";
import CategoryCloudNode from "@/Pages/Pos/Components/Menu/CategoryCloudNode.vue";
import { router } from '@inertiajs/vue3';
import { usePosSettings } from "@/Pages/Pos/Composables/usePosSettings";

const props = defineProps({ categories: { type: Array, default: () => [] } });
const { categorySort, productSort } = usePosSettings();
const localCategories = ref(JSON.parse(JSON.stringify(props.categories)));

watch(() => props.categories, (newVal) => {
    localCategories.value = JSON.parse(JSON.stringify(newVal));
}, { deep: true });

const searchQuery = ref('');

const autoSave = (type) => {
    router.post(route('settings.reorder'), { type: type, items: localCategories.value }, {
        preserveScroll: true,
        onSuccess: () => console.log("✅ Categorias reordenadas com sucesso!")
    });
};
</script>
