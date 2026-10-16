<template>
    <header ref="headerEl" class="fixed top-0 inset-x-0 z-[60] flex flex-col shrink-0 pointer-events-none pb-6 pt-[env(safe-area-inset-top,0px)]">

        <!-- Blur Progressivo -->
        <div class="absolute inset-0 backdrop-blur-xl [mask-image:linear-gradient(to_bottom,black_65%,transparent_100%)] -z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-surface-1/95 via-surface-1/70 to-transparent -z-10"></div>

        <div class="relative z-10 flex flex-col pointer-events-auto w-full">

            <!-- LINHA 1: Logo e Ações -->
            <div class="flex items-center justify-between w-full px-4 py-3">
                <div class="flex items-center gap-3">
                    <img v-if="hasLogo"
                         :src="logoUrl"
                         alt="Logo"
                         class="h-6 md:h-7 w-auto object-contain drop-shadow-sm dark:brightness-0 dark:invert" />

                    <span v-else class="text-[11px] font-black uppercase tracking-widest text-surface-text">
                        {{ storeName }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <div v-if="tableMode" class="px-3 py-1 rounded-full flex items-center gap-1.5 bg-primary-500/15 border border-primary-500/30 text-primary-smart shadow-sm">
                        <i class="pi pi-table text-xs"></i>
                        <span class="text-[10px] font-black uppercase tracking-wider leading-none">{{ tableMode.name }}</span>
                    </div>

                    <div v-else class="px-3 py-1 rounded-full flex items-center gap-1.5 transition-colors" :class="statusBadgeProps.colorClass">
                        <div class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusBadgeProps.dotClass"></div>
                        <span class="text-[10px] font-black uppercase tracking-wider leading-none">{{ statusBadgeProps.label }}</span>
                    </div>

                    <button v-if="hasSupport" @click="openSupport('general')" class="w-9 h-9 rounded-full text-surface-muted flex items-center justify-center hover:text-green-500 hover:bg-surface-3 transition-all outline-none cursor-pointer shadow-sm active:scale-90" title="Ajuda via WhatsApp">
                        <i class="pi pi-whatsapp text-sm"></i>
                    </button>

                    <template v-if="!tableMode">
                        <button @click="$emit('open-location')" class="w-9 h-9 rounded-full text-surface-muted flex items-center justify-center hover:text-primary-500 hover:bg-surface-3 transition-all outline-none cursor-pointer shadow-sm active:scale-90" title="Endereço">
                            <i class="pi pi-map-marker text-sm"></i>
                        </button>

                        <button @click="$emit('open-hours')" class="w-9 h-9 rounded-full text-surface-muted flex items-center justify-center hover:text-primary-500 hover:bg-surface-3 transition-all outline-none cursor-pointer shadow-sm active:scale-90 relative" title="Horários">
                            <i class="pi pi-clock text-sm"></i>
                            <span v-if="!isOpen" class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full bg-danger-500 border-2 border-surface-1"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- LINHA 2: Títulos Dinâmicos & Ação Lateral (Busca Expandida) -->
            <div class="px-4 flex flex-col gap-1 pb-2 min-h-[48px] justify-center">

                <!-- PRODUTO OU COMBO EM CONFIGURAÇÃO -->
                <template v-if="activeProduct">
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 leading-tight animate-in fade-in">
                        {{ contextSubtitle || activeProduct.category_name || 'Personalizando Item' }}
                    </span>
                    <div class="flex items-center justify-between gap-4 animate-in fade-in">
                        <h1 class="text-xl font-black text-surface-text leading-none tracking-tight truncate">
                            {{ contextTitle || activeProduct.name }}
                        </h1>
                        <span class="font-black text-base text-surface-text whitespace-nowrap">
                            R$ {{ Number(price ?? activeProduct.price ?? activeProduct.base_price ?? 0).toFixed(2).replace('.', ',') }}
                        </span>
                    </div>
                </template>

                <!-- ESTADO DA HOME: Saudação em linha única -->
                <template v-else-if="isHome">
                    <h1 class="text-[20px] font-black text-surface-text leading-none tracking-tight truncate animate-in fade-in">
                        E aí <template v-if="firstName"><span class="text-primary-400">{{ firstName }}</span></template>, qual é a boa de hoje?
                    </h1>
                </template>

                <!-- DEMAIS ESTADOS: Título Fixo + Alvo Teleport para a Busca -->
                <template v-else>
                    <div class="flex items-center justify-between gap-3 w-full">
                        <div class="flex flex-col gap-0.5 min-w-0">
                            <span v-if="contextSubtitle" class="text-[10px] font-black uppercase tracking-widest text-primary-500 leading-tight animate-in fade-in truncate">
                                {{ contextSubtitle }}
                            </span>
                            <h1 v-if="contextTitle" class="text-2xl font-black text-surface-text leading-none tracking-tight truncate animate-in fade-in">
                                {{ contextTitle }}
                            </h1>
                        </div>

                        <!-- 🚀 ALVO TELEPORT PARA EXPANDABLESEARCH (Ao lado do título Cardápio) -->
                        <div id="customer-header-actions-target" class="shrink-0 flex items-center justify-end empty:hidden"></div>
                    </div>
                </template>

            </div>

            <slot name="bottom-bar"></slot>
        </div>

        <!-- 🚀 PORTAL DINÂMICO PARA CONTROLES DE SUB-HEADER (Pílulas de Categoria, SegmentedControl) -->
        <div id="customer-header-portal-target" class="w-full pointer-events-auto relative z-50"></div>
    </header>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useStoreStatus } from '@/Composables/useStoreStatus';
import { useWhatsAppSupport } from '@/Pages/Customer/Composables/useWhatsAppSupport';
import { useDynamicHeaderHeight } from '@/Composables/useDynamicHeaderHeight';

const props = defineProps({
    contextTitle: { type: String, default: null },
    contextSubtitle: { type: String, default: null },
    price: { type: Number, default: null },
    activeProduct: { type: Object, default: null },
    isHome: { type: Boolean, default: false },
    firstName: { type: String, default: '' }
});

defineEmits(['open-location', 'open-hours', 'close-product']);

const page = usePage();
const { isOpen, isClosedIndefinitely, isOpeningSoon, isPreOrder, status } = useStoreStatus();
const { hasSupport, openSupport } = useWhatsAppSupport();

const headerEl = ref(null);
useDynamicHeaderHeight(headerEl);

const tableMode = computed(() => page.props.tableMode || null);
const companySettings = computed(() => page.props.companySettings || {});

const hasLogo = computed(() => {
    return !!(companySettings.value.logo_svg_path || companySettings.value.logo_path);
});

const logoUrl = computed(() => {
    const path = companySettings.value.logo_svg_path || companySettings.value.logo_path;
    return path ? `/media/file?path=${path}` : '';
});

const storeName = computed(() => {
    return companySettings.value.trade_name || companySettings.value.company_name || 'Lanchonete';
});

const statusBadgeProps = computed(() => {
    if (isClosedIndefinitely.value) {
        return { label: 'Fechado', colorClass: 'text-danger-500 bg-danger-500/10', dotClass: 'bg-danger-500' };
    }
    if (isOpen.value) {
        return { label: 'Aberto', colorClass: 'text-success-500 bg-success-500/10', dotClass: 'bg-success-500' };
    }
    if (isOpeningSoon.value || isPreOrder.value || status.value?.reason === 'cashier_closed') {
        return { label: 'Abrindo', colorClass: 'text-warning-500 bg-warning-500/10', dotClass: 'bg-warning-500' };
    }
    return { label: 'Fechado', colorClass: 'text-surface-400 bg-surface-400/10', dotClass: 'bg-surface-400' };
});
</script>
