<template>
    <header class="w-full shrink-0 flex flex-col z-10 relative overflow-hidden px-6 py-4">

        <!-- MODO CLIENTE (B2C) -->
        <div v-if="isCustomerMode" class="flex justify-between items-start gap-4 shrink-0 w-full">
            <h1 class="text-2xl font-black text-surface-text leading-tight tracking-tight break-words flex-1 min-w-0">
                {{ title }}
            </h1>
            <div class="flex items-center shrink-0">
                <span class="text-lg font-black leading-none tracking-tight">
                    {{ $formatCurrency(total) }}
                </span>
            </div>
        </div>

        <!-- MODO PDV / EQUIPE (B2B) -->
        <template v-else>
            <!-- LINHA 1: BREADCRUMBS NO TOPO (Alinhado com o catálogo + BOTÃO WHATSAPP) -->
            <div v-if="showBreadcrumbs && normalizedItems.length > 0" class="w-full flex items-center justify-between mb-2 gap-3">
                <Breadcrumb
                    :items="normalizedItems"
                    :is-edit-mode="isEditMode"
                    :last-item-clickable="true"
                    size="sm"
                    @navigate="(item) => $emit('crumb-click', item)"
                />

                <button
                    v-if="product"
                    type="button"
                    @click="handleCopyProductWhatsApp"
                    class="h-7 md:h-8 px-2.5 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 outline-none select-none active:scale-95 shrink-0"
                    title="Copiar texto para o WhatsApp"
                >
                    <i class="pi text-sm font-black" :class="isCopied ? 'pi-check text-emerald-500' : 'pi-whatsapp'"></i>
                    <span class="hidden sm:inline">{{ isCopied ? 'Copiado!' : 'Copiar' }}</span>
                </button>
            </div>

            <!-- LINHA 2: NOME DO PRODUTO & PREÇO -->
            <div class="flex justify-between items-center gap-4 shrink-0 w-full min-h-[40px]">
                <h1 class="text-2xl md:text-3xl font-black text-surface-text leading-tight tracking-tight break-words flex-1 min-w-0">
                    {{ title }}
                </h1>

                <div class="flex items-center shrink-0">
                    <span class="text-3xl font-black leading-none tracking-tight">
                        {{ $formatCurrency(total) }}
                    </span>
                </div>
            </div>
        </template>

        <!-- Barra de Progresso (Combos) -->
        <div v-if="progressMax > 0" class="absolute bottom-0 left-0 right-0">
            <ProgressBar :current="progressCurrent" :max="progressMax" />
        </div>
    </header>
</template>

<script setup>
import { ref, computed } from "vue";
import { usePage } from '@inertiajs/vue3';
import { formatCurrency } from "@/Utils/formatters";
import ProgressBar from "@/Components/UI/ProgressBar.vue";
import Breadcrumb from "@/Components/UI/Breadcrumb.vue";
import { useWhatsAppMenuText } from '@/Pages/Shared/Composables/useWhatsAppMenuText';

const props = defineProps({
    product: { type: Object, default: null },
    title: { type: String, required: true },
    breadcrumbs: { type: [Array, Object], default: () => [] },
    showBreadcrumbs: { type: Boolean, default: false },
    total: { type: Number, required: true },
    isValid: { type: Boolean, default: true },
    isEditMode: { type: Boolean, default: false },
    progressCurrent: { type: Number, default: 0 },
    progressMax: { type: Number, default: 0 },
    hideBackBtn: { type: Boolean, default: false },
    isCustomerMode: { type: Boolean, default: false }
});

defineEmits(['close', 'crumb-click']);

const page = usePage();
const { copyProductOrCombo } = useWhatsAppMenuText();
const isCopied = ref(false);

const normalizedItems = computed(() => {
    if (Array.isArray(props.breadcrumbs)) {
        return props.breadcrumbs.length > 0 ? props.breadcrumbs : [{ label: "Início", id: null }];
    }
    if (props.breadcrumbs && typeof props.breadcrumbs === "object") {
        const items = Array.isArray(props.breadcrumbs.items) ? [...props.breadcrumbs.items] : [];
        if (props.breadcrumbs.active && !["início", "inicio", "home"].includes(String(props.breadcrumbs.active).toLowerCase().trim())) {
            items.push({ label: props.breadcrumbs.active, id: null });
        }
        if (items.length > 0) return items;
    }
    return [{ label: "Início", id: null }];
});

const handleCopyProductWhatsApp = async () => {
    if (!props.product) return;
    const allProducts = page.props.initialProducts || page.props.products || [];
    const ok = await copyProductOrCombo(props.product, allProducts);
    if (ok) {
        isCopied.value = true;
        setTimeout(() => { isCopied.value = false; }, 2000);
    }
};
</script>
