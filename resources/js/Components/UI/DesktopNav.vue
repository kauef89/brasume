<template>
    <nav ref="navContainer" class="bg-surface-2 rounded-full flex items-center p-1.5 gap-1 overflow-x-auto hide-scrollbar h-14 w-max max-w-full">

        <!-- 1. BOTÃO DE IDENTIDADE DO TENANT -->
        <button @click="navigate('home')"
                class="flex items-center justify-center w-11 h-11 rounded-full transition-all outline-none cursor-pointer border-none bg-transparent hover:bg-surface-3 shrink-0 overflow-hidden relative border border-surface-border/40"
                :class="isCurrent('home') ? 'ring-2 ring-primary-500/50 bg-surface-3 text-primary-500' : 'text-surface-muted'"
                title="Início">
            <img v-if="$page.props.companySettings?.icon_192_path"
                 :src="'/media/file?path=' + $page.props.companySettings.icon_192_path"
                 class="w-full h-full object-cover" alt="Ícone 192" />
            <img v-else-if="$page.props.companySettings?.icon_path"
                 :src="'/media/file?path=' + $page.props.companySettings.icon_path"
                 class="w-full h-full object-cover" alt="Ícone Base" />
            <div v-else class="w-full h-full bg-primary-500 text-white flex items-center justify-center font-black text-xs uppercase">
                {{ ($page.props.companySettings?.company_name || 'P')[0] }}
            </div>
        </button>

        <div class="h-6 w-[1px] bg-surface-border/50 mx-1 shrink-0"></div>

        <!-- 2. GRUPO VISÍVEL -->
        <button v-for="item in visibleGroup" :key="item.id"
                @click="navigate(item.action, item.isSpaTab)"
                class="relative flex items-center gap-2 h-11 rounded-full transition-all duration-200 outline-none cursor-pointer border-none shrink-0 overflow-hidden select-none"
                :title="item.label"
                :class="[
                    isCurrent(item.action)
                        ? 'text-primary-800 dark:text-primary-200 bg-primary-500/10 px-5'
                        : 'bg-transparent text-surface-muted hover:text-surface-text hover:bg-surface-3 w-11 justify-center'
                ]">
            <i class="shrink-0 transition-transform duration-200" :class="[item.icon + ' text-sm', isCurrent(item.action) ? 'scale-110' : '']"></i>
            <span v-if="isCurrent(item.action)" class="text-[10px] font-black uppercase tracking-widest mt-0.5 whitespace-nowrap animate-in fade-in duration-200">
                {{ item.label }}
            </span>

            <span v-if="item.id === 'spooler' && hasPrintErrors && !isCurrent(item.action)" class="absolute top-0.5 right-0.5 min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-danger-500/80 text-white text-[9px] font-black px-1 border-2 border-surface-2 animate-pulse z-10">
                {{ $page.props.failedJobsCount || $page.props.failedPrintJobsCount }}
            </span>
        </button>

        <div v-if="hiddenGroup.length > 0" class="h-6 w-[1px] bg-surface-border/50 mx-1 shrink-0"></div>

        <!-- 3. GRUPO ESCONDIDO (Expansível) -->
        <template v-if="isExpanded">
            <button v-for="item in hiddenGroup" :key="item.id"
                    @click="navigate(item.action, item.isSpaTab)"
                    class="relative flex items-center gap-2 h-11 rounded-full transition-all duration-200 outline-none cursor-pointer border-none shrink-0 overflow-hidden select-none animate-in fade-in slide-in-from-left-2"
                    :title="item.label"
                    :class="[
                        isCurrent(item.action)
                            ? 'text-primary-500 bg-primary-500/10 px-5'
                            : 'bg-transparent text-surface-muted hover:text-surface-text hover:bg-surface-3 w-11 justify-center'
                    ]">
                <i class="shrink-0 transition-transform duration-200" :class="[item.icon + ' text-sm', isCurrent(item.action) ? 'scale-110' : '']"></i>
                <span v-if="isCurrent(item.action)" class="text-[10px] font-black uppercase tracking-widest mt-0.5 whitespace-nowrap animate-in fade-in duration-200">
                    {{ item.label }}
                </span>

                <span v-if="item.id === 'spooler' && hasPrintErrors" class="absolute top-0.5 right-0.5 min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-danger-500/80 text-white text-[9px] font-black px-1 border-2 border-surface-2 animate-pulse z-10">
                    {{ $page.props.failedJobsCount || $page.props.failedPrintJobsCount }}
                </span>
            </button>
        </template>

        <!-- 4. CHEVRON (Expandir / Recolher) -->
        <button v-if="hiddenGroup.length > 0" @click="isExpanded = !isExpanded"
                class="flex items-center justify-center w-11 h-11 rounded-full transition-all outline-none cursor-pointer border-none bg-transparent hover:bg-surface-3 text-surface-muted shrink-0 relative"
                title="Mais Módulos">
            <i class="text-sm transition-transform duration-300" :class="isExpanded ? 'pi pi-chevron-left' : 'pi pi-chevron-right'"></i>
            <span v-if="!isExpanded && hasHiddenAlert" class="absolute top-2 right-2 w-2 h-2 rounded-full bg-danger-500 border border-surface-2 animate-pulse"></span>
        </button>

        <!-- 5. PEDIDOS ESTACIONADOS -->
        <div v-if="parkedOrders.length > 0" class="w-px h-6 bg-surface-border/50 mx-1 shrink-0"></div>
        <div v-if="parkedOrders.length > 0" class="relative shrink-0 h-11 w-11">
            <button @click="toggleParkedMenu" class="flex items-center justify-center w-11 h-11 rounded-full transition-all outline-none cursor-pointer border-none bg-transparent text-warning-500 hover:bg-warning-500/20">
                <i class="pi pi-folder-open text-sm"></i>
                <span class="absolute top-0.5 right-0.5 min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-danger-500/50 text-white text-[9px] font-black px-1 border-2 border-surface-2 animate-in zoom-in">
                    {{ parkedOrders.length }}
                </span>
            </button>
            <ParkedOrdersPopover ref="parkedPopover" />
        </div>
    </nav>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useCart } from "@/Pages/Shared/Composables/useCart";
import ParkedOrdersPopover from "@/Pages/Pos/Components/Cart/ParkedOrdersPopover.vue";

const props = defineProps({
    modelValue: { type: String, default: 'new' }
});

const emit = defineEmits(['update:modelValue', 'expanded-change']);

const page = usePage();
const { parkedOrders } = useCart();
const parkedPopover = ref(null);
const isExpanded = ref(false);
const navContainer = ref(null);

const navItems = [
    { id: 'novo-pedido', label: 'Caixa', icon: 'pi pi-shopping-cart', action: 'new', isSpaTab: true, defaultVisible: true },
    { id: 'pedidos', label: 'Operação', icon: 'pi pi-objects-column', action: 'orders', isSpaTab: true, defaultVisible: true },
    { id: 'crm', label: 'CRM', icon: 'pi pi-users', action: 'customers', isSpaTab: true, defaultVisible: true },
    { id: 'caixa', label: 'Turno', icon: 'pi pi-wallet', action: 'shift', isSpaTab: true, defaultVisible: true },
    { id: 'tarefas', label: 'Tarefas', icon: 'pi pi-check-square', action: 'tasks', isSpaTab: true, defaultVisible: false },
    { id: 'estoque', label: 'Estoque', icon: 'pi pi-box', action: 'availability', isSpaTab: true, defaultVisible: false },
    { id: 'producao', label: 'Produção', icon: 'pi pi-file-plus', action: 'production', isSpaTab: true, defaultVisible: false },
    { id: 'compras', label: 'Compras', icon: 'pi pi-shopping-bag', action: 'purchases', isSpaTab: true, defaultVisible: false },
    { id: 'spooler', label: 'Spooler', icon: 'pi pi-print', action: 'printers', isSpaTab: true, defaultVisible: false },
    { id: 'ajustes', label: 'Ajustes', icon: 'pi pi-cog', action: 'settings', isSpaTab: true, defaultVisible: false },
];

const isCurrent = (action) => {
    return props.modelValue === action;
};

const visibleGroup = computed(() => navItems.filter(item => item.defaultVisible || isCurrent(item.action)));
const hiddenGroup = computed(() => navItems.filter(item => !item.defaultVisible && !isCurrent(item.action)));

const hasPrintErrors = computed(() => page.props.failedJobsCount > 0 || page.props.failedPrintJobsCount > 0);
const hasHiddenAlert = computed(() => hasPrintErrors.value && hiddenGroup.value.some(item => item.id === 'spooler'));

watch(isExpanded, (newValue) => {
    emit('expanded-change', newValue);
});

const navigate = (action, isSpaTab = false) => {
    isExpanded.value = false;

    if (action === 'home') {
        emit('update:modelValue', 'new');
        window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'new' }));
        return;
    }

    if (isSpaTab) {
        if (props.modelValue === action) {
            window.dispatchEvent(new CustomEvent('pos-tab-reset', { detail: action }));
            return;
        }

        emit('update:modelValue', action);
        window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: action }));
        return;
    }

    if (window.location.pathname !== action) {
        router.visit(action);
    }
};

const toggleParkedMenu = (event) => parkedPopover.value.toggle(event);

const handleClickOutside = (event) => {
    if (isExpanded.value && navContainer.value && !navContainer.value.contains(event.target)) {
        isExpanded.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>
