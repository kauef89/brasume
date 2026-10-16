<template>
    <div class="flex flex-col gap-6 animate-in fade-in slide-in-from-bottom-2 duration-300">

        <SettingCard title="Busca de Endereços" v-if="!isTeamMode" description="Como o sistema se comportará ao cadastrar novos endereços de entrega.">
            <SegmentedControl
                v-model="addressLookupMode"
                :options="[
                    { label: 'Por Rua', value: 'street', icon: 'pi-search' },
                    { label: 'Por CEP', value: 'cep', icon: 'pi-map' }
                ]"
            />
        </SettingCard>

        <SettingCard title="Estrutura do Cardápio" description="Como as categorias e subcategorias são organizadas na tela de vendas.">
            <SegmentedControl
                v-model="categoryHierarchy"
                :options="[
                    { label: 'Corrido', value: 'flat', icon: 'pi-align-justify' },
                    { label: 'Em Níveis', value: 'nested', icon: 'pi-sitemap' }
                ]"
            />
        </SettingCard>

        <SettingCard v-if="!isTeamMode" title="Posição do Menu de Categorias" description="Escolha como a lista de categorias será exibida.">
            <SegmentedControl
                v-model="categoryLayout"
                :options="categoryLayoutOptions"
            />
        </SettingCard>

        <SettingCard title="Layout dos Produtos" description="Prefere imagens grandes ou uma lista densa para agilidade?">
            <SegmentedControl
                v-model="productCardLayout"
                :options="[
                    { label: 'Grade', value: 'square', icon: 'pi-image' },
                    { label: 'Lista', value: 'list', icon: 'pi-list' }
                ]"
            />
        </SettingCard>

        <SettingCard title="Itens Esgotados na Tela de Vendas" description="Como o sistema deve tratar os produtos que estão sem estoque.">
            <SegmentedControl
                v-model="outOfStockMode"
                :options="[
                    { label: 'Ocultar', value: 'hide', icon: 'pi-eye-slash' },
                    { label: 'No Fim', value: 'disable_bottom', icon: 'pi-sort-amount-down' },
                    { label: 'Manter', value: 'disable_inline', icon: 'pi-minus-circle' }
                ]"
            />
        </SettingCard>

        <SettingCard title="Tema do Sistema" description="Defina o esquema de cores para este terminal.">
            <SegmentedControl
                v-model="theme"
                :options="[
                    { label: 'Claro', value: 'light', icon: 'pi-sun' },
                    { label: 'Escuro', value: 'dark', icon: 'pi-moon' },
                    { label: 'Auto', value: 'system', icon: 'pi-desktop' }
                ]"
            />
        </SettingCard>

    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import SettingCard from "./SettingCard.vue";
import { usePosSettings } from "@/Pages/Pos/Composables/usePosSettings";

const props = defineProps({
    isTeamMode: { type: Boolean, default: false }
});

const { categoryLayout, productCardLayout, theme, categoryHierarchy, addressLookupMode, outOfStockMode } = usePosSettings();

const categoryLayoutOptions = computed(() => {
    const opts = [{ label: 'Topo', value: 'horizontal', icon: 'pi-bars' }];
    if (!props.isTeamMode) opts.push({ label: 'Lateral', value: 'vertical', icon: 'pi-align-left' });
    opts.push({ label: 'Grade', value: 'grid', icon: 'pi-th-large' });
    opts.push({ label: 'Lista', value: 'list', icon: 'pi-list' });
    return opts;
});

// 🚀 A MÁGICA DA SINCRONIZAÇÃO DO LAYOUT (USANDO FUNÇÕES DE GETTER BLINDADAS)
watch(() => productCardLayout?.value, (newVal) => {
    if (newVal === 'list' && categoryLayout?.value === 'grid') {
        categoryLayout.value = 'list';
    }
    else if (newVal === 'square' && categoryLayout?.value === 'list') {
        categoryLayout.value = 'grid';
    }
});

// 🚀 A MÁGICA DA SINCRONIZAÇÃO DO TEMA (USANDO FUNÇÕES DE GETTER BLINDADAS)
watch(() => theme?.value, (newMode) => {
    if (!newMode) return;
    localStorage.setItem('pos_color_scheme', newMode);

    if (newMode === 'dark' || (newMode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
});
</script>
