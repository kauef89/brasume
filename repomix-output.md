This file is a merged representation of a subset of the codebase, containing specifically included files and files not matching ignore patterns, combined into a single document by Repomix.

# File Summary

## Purpose
This file contains a packed representation of a subset of the repository's contents that is considered the most important context.
It is designed to be easily consumable by AI systems for analysis, code review,
or other automated processes.

## File Format
The content is organized as follows:
1. This summary section
2. Repository information
3. Directory structure
4. Repository files (if enabled)
5. Multiple file entries, each consisting of:
  a. A header with the file path (## File: path/to/file)
  b. The full contents of the file in a code block

## Usage Guidelines
- This file should be treated as read-only. Any changes should be made to the
  original repository files, not this packed version.
- When processing this file, use the file path to distinguish
  between different files in the repository.
- Be aware that this file may contain sensitive information. Handle it with
  the same level of security as you would the original repository.

## Notes
- Some files may have been excluded based on .gitignore rules and Repomix's configuration
- Binary files are not included in this packed representation. Please refer to the Repository Structure section for a complete list of file paths, including binary files
- Only files matching these patterns are included: resources/js/Pages/Team/**/*.vue, resources/js/Pages/Team/**/*.ts
- Files matching these patterns are excluded: vendor/**, node_modules/**, public/**, storage/**, bootstrap/cache/**, database/migrations/**, database/factories/**, tests/**, .git/**, *.lock, package-lock.json, composer.lock, repomix*.xml
- Files matching patterns in .gitignore are excluded
- Files matching default ignore patterns are excluded
- Files are sorted by Git change count (files with more changes are at the bottom)

# Directory Structure
```
resources/
  js/
    Pages/
      Team/
        ChecklistIssueCard.vue
        ChecklistOverviewWidget.vue
        CorporateProfileTab.vue
        CorporateWalletCard.vue
        DeliveryDriverCard.vue
        DeviceFleetPanel.vue
        DriverBoard.vue
        InventoryItemRow.vue
        InventoryTab.vue
        ManagerShiftDashboard.vue
        MealAllowanceTab.vue
        QrScannerModal.vue
        RestockTab.vue
        TeamApp.vue
        TeamBarCard.vue
        TeamBarTab.vue
        TeamCart.vue
        TeamCartItemCard.vue
        TeamChecklists.vue
        TeamDynamicIsland.vue
        TeamHeader.vue
        TeamHome.vue
        TeamHomeTab.vue
        TeamMenuTab.vue
        TeamModulesModal.vue
        TeamOrderDetails.vue
        TeamOverlays.vue
        TeamShiftLockOverlay.vue
        TeamTabContainer.vue
```

# Files

## File: resources/js/Pages/Team/ChecklistIssueCard.vue
```vue
<template>
    <div class="p-3 bg-warning-500/10 border border-warning-500/30 rounded-2xl flex flex-col gap-1 text-xs">
        <div class="flex justify-between items-center font-bold text-surface-text">
            <span>{{ issue.task_name }}</span>
            <span class="text-[10px] opacity-70">{{ issue.time }}</span>
        </div>
        <p class="text-warning-800 dark:text-warning-300 font-medium">{{ issue.notes }}</p>
        <span class="text-[10px] text-surface-muted font-bold">Reportado por: {{ issue.reported_by }}</span>
    </div>
</template>

<script setup>
defineProps({
    issue: {
        type: Object,
        required: true
    }
});
</script>
```

## File: resources/js/Pages/Team/ChecklistOverviewWidget.vue
```vue
<template>
    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 relative overflow-hidden mb-4">
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>

        <p class="text-sm font-medium opacity-80 uppercase tracking-wider mb-1">Saldo Disponível</p>
        <h2 class="text-4xl font-black mb-1" :class="{'text-danger-200': balance < 0}">
            {{ $formatCurrency(balance) }}
        </h2>
        <p class="text-xs opacity-75">
            Renovação {{ frequency === 'daily' ? 'Diária' : 'Mensal' }}
        </p>
    </div>
</template>

<script setup>
defineProps({
    balance: {
        type: Number,
        required: true
    },
    frequency: {
        type: String,
        default: 'monthly'
    }
});
</script>
```

## File: resources/js/Pages/Team/CorporateProfileTab.vue
```vue
<template>
    <div class="flex flex-col gap-4 p-4 pb-32 animate-in fade-in zoom-in-95 duration-300">

        <!-- 👤 HEADER: IDENTIDADE DO COLABORADOR -->
        <div class="bg-surface-2 border border-surface-border rounded-[2rem] p-6 flex flex-col items-center text-center shadow-sm relative overflow-hidden">
            <!-- Background Decorativo -->
            <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-primary-500/10 to-transparent"></div>

            <div class="w-20 h-20 bg-surface-1 border-4 border-surface-2 text-primary-500 rounded-full flex items-center justify-center mb-3 text-2xl font-black shadow-md z-10 relative">
                {{ userInitials }}
            </div>

            <h2 class="text-xl font-black text-surface-text z-10">{{ userName }}</h2>
            <p class="text-xs font-medium text-surface-muted z-10">{{ userEmail }}</p>

            <div class="mt-4 px-4 py-1.5 bg-surface-1 rounded-xl border border-surface-border z-10">
                <span class="text-[10px] font-black uppercase tracking-widest text-surface-text">{{ userRole }}</span>
            </div>
        </div>

        <!-- 💳 CARTEIRA: CONSUMO INTERNO (ALLOWANCE) -->
        <div class="bg-surface-2 border border-surface-border rounded-[2rem] p-5 shadow-sm flex flex-col gap-4">
            <div class="flex items-center gap-3 border-b border-surface-border pb-3">
                <div class="w-10 h-10 rounded-full bg-primary-500/10 text-primary-500 flex items-center justify-center">
                    <i class="pi fa-wallet text-lg"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-black text-sm text-surface-text">Carteira Corporativa</span>
                    <span class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">Consumo de Funcionário</span>
                </div>
            </div>

            <!-- Saldo -->
            <div class="flex justify-between items-center bg-surface-1 p-4 rounded-2xl border border-surface-border">
                <div class="flex flex-col">
                    <span class="text-[10px] font-black text-surface-muted uppercase tracking-widest mb-1">Saldo Disponível</span>
                    <span class="text-2xl font-black text-primary-500">{{ formattedBalance }}</span>
                </div>
                <div class="w-12 h-12 rounded-full bg-surface-2 flex items-center justify-center text-surface-muted border border-surface-border">
                    <i class="pi pi-chart-pie text-xl"></i>
                </div>
            </div>

            <!-- Botão de Ação -->
            <button
                @click="$emit('startAllowanceOrder')"
                class="h-14 w-full bg-primary-500 text-white rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-3 hover:bg-primary-600 transition-colors shadow-md active:scale-95 outline-none border-none cursor-pointer mt-1"
            >
                <i class="pi pi-shopping-bag text-lg"></i>
                Lançar Consumo Interno
            </button>
        </div>

        <!-- ⚙️ CONFIGURAÇÕES E PREFERÊNCIAS -->
        <div class="flex flex-col gap-2 mt-2">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-surface-muted px-2 mb-1">Preferências</h3>

            <button
                @click="$emit('openSettings')"
                class="h-14 w-full bg-surface-2 border border-surface-border text-surface-text rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-between px-5 hover:bg-surface-3 transition-colors shadow-sm active:scale-95 outline-none cursor-pointer"
            >
                <span class="flex items-center gap-3">
                    <i class="pi pi-cog text-lg text-surface-muted"></i>
                    Aparência do App
                </span>
                <i class="pi pi-chevron-right text-surface-muted"></i>
            </button>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    employeeData: {
        type: Object,
        default: () => ({ balance: 0 })
    }
});

defineEmits(['startAllowanceOrder', 'openSettings']);

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

const userName = computed(() => user.value.name || 'Colaborador');
const userEmail = computed(() => user.value.email || 'equipe@brasume.com.br');

const userRole = computed(() => {
    if (user.value.roles && user.value.roles.length > 0) {
        return user.value.roles[0].replace('_', ' ');
    }
    return user.value.role || 'Operador';
});

const userInitials = computed(() => {
    const names = userName.value.split(' ');
    if (names.length >= 2) {
        return `${names[0][0]}${names[names.length - 1][0]}`.toUpperCase();
    }
    return userName.value.substring(0, 2).toUpperCase();
});

const formattedBalance = computed(() => {
    const balance = props.employeeData?.balance || 0;
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(balance);
});
</script>
```

## File: resources/js/Pages/Team/CorporateWalletCard.vue
```vue
<template>
    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 relative overflow-hidden mb-4">
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>

        <p class="text-sm font-medium opacity-80 uppercase tracking-wider mb-1">Saldo Disponível</p>
        <h2 class="text-4xl font-black mb-1" :class="{'text-danger-200': balance < 0}">
            {{ $formatCurrency(balance) }}
        </h2>
        <p class="text-xs opacity-75">
            Renovação {{ frequency === 'daily' ? 'Diária' : 'Mensal' }}
        </p>
    </div>
</template>

<script setup>
defineProps({
    balance: {
        type: Number,
        required: true
    },
    frequency: {
        type: String,
        default: 'monthly'
    }
});
</script>
```

## File: resources/js/Pages/Team/MealAllowanceTab.vue
```vue
<template>
    <div class="h-full w-full flex flex-col bg-surface-2 overflow-hidden select-none">

        <!-- CABEÇALHO DA ABA -->
        <header class="p-4 md:p-6 bg-surface-1 border-b border-surface-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="$emit('back')"
                    class="w-10 h-10 rounded-full bg-surface-2 hover:bg-surface-3 flex items-center justify-center text-surface-muted hover:text-surface-text transition-colors cursor-pointer border-none outline-none"
                    title="Voltar ao Início"
                >
                    <i class="pi pi-arrow-left"></i>
                </button>
                <div>
                    <h2 class="text-lg md:text-xl font-black text-surface-text leading-tight flex items-center gap-2">
                        <i class="pi pi-sparkles text-amber-500"></i> Lanche da Equipa
                    </h2>
                    <p class="text-xs text-surface-muted mt-0.5">
                        {{ capLabel }}
                    </p>
                </div>
            </div>

            <!-- BADGE RESUMO -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-xl bg-surface-2 border border-surface-border text-surface-text">
                    {{ attendeesWithItemsCount }} / {{ attendees.length }} Lanches
                </span>
            </div>
        </header>

        <!-- LISTA ROLÁVEL DE SLOTS -->
        <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4 hide-scrollbar">

            <div
                v-for="attendee in attendees"
                :key="attendee.id"
                class="rounded-3xl border transition-all duration-200 overflow-hidden shadow-xs"
                :class="[
                    getSlotItems(attendee.id).length > 0
                        ? 'bg-surface-1 border-primary-500/30 ring-1 ring-primary-500/10'
                        : 'bg-surface-1/60 border-surface-border/60'
                ]"
            >
                <!-- CABEÇALHO DO SLOT -->
                <div class="p-4 flex items-center justify-between gap-3 border-b border-surface-border/40">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-2xl bg-primary-500/10 text-primary-500 border border-primary-500/20 flex items-center justify-center font-black text-sm shrink-0">
                            {{ getInitials(attendee.name) }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-black text-surface-text truncate">
                                {{ attendee.name }}
                            </h3>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">
                                {{ attendee.role || 'Colaborador' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col items-end shrink-0">
                        <span class="text-sm font-black text-surface-text">
                            {{ $formatCurrency(getSlotTotal(attendee.id)) }}
                        </span>

                        <span
                            v-if="getSlotItems(attendee.id).length > 0"
                            class="text-[10px] font-bold uppercase tracking-wider"
                            :class="isSlotExceeded(attendee.id) ? (allowTab ? 'text-amber-500' : 'text-danger-500') : 'text-success-smart'"
                        >
                            {{ getSlotStatusText(attendee.id) }}
                        </span>
                    </div>
                </div>

                <!-- ITENS SELECIONADOS NO SLOT -->
                <div v-if="getSlotItems(attendee.id).length > 0" class="p-4 space-y-2 bg-surface-2/40">
                    <div
                        v-for="(item, idx) in getSlotItems(attendee.id)"
                        :key="idx"
                        class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-surface-1 border border-surface-border/50 text-xs"
                    >
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="font-bold text-surface-text flex items-center gap-1.5 truncate">
                                <span>{{ item.qty }}x {{ item.name }}</span>
                                <span
                                    class="text-[9px] px-1.5 py-0.5 rounded-md font-black uppercase tracking-wider shrink-0"
                                    :class="item.is_takeaway ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20' : 'bg-surface-2 text-surface-muted'"
                                >
                                    {{ item.is_takeaway ? 'Para Levar' : 'Mesa' }}
                                </span>
                            </span>

                            <span v-if="item.modifiersText" class="text-[10px] text-surface-muted truncate mt-0.5">
                                {{ item.modifiersText }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-black text-surface-text">
                                {{ $formatCurrency(item.totalPrice) }}
                            </span>
                            <button
                                type="button"
                                @click="removeItemFromSlot(attendee.id, idx)"
                                class="w-7 h-7 rounded-lg bg-danger-500/10 hover:bg-danger-500 hover:text-white text-danger-500 flex items-center justify-center transition-colors cursor-pointer border-none outline-none"
                                title="Remover item"
                            >
                                <i class="pi pi-trash text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- BOTÃO DE AÇÃO DO SLOT -->
                <div class="p-3 bg-surface-1 flex items-center justify-between">
                    <span class="text-xs text-surface-muted pl-1">
                        {{ getSlotItems(attendee.id).length === 0 ? 'Nenhum item escolhido' : `${getSlotItems(attendee.id).length} item(ns)` }}
                    </span>

                    <button
                        type="button"
                        @click="openProductPicker(attendee)"
                        class="h-8 px-3 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all outline-none cursor-pointer border select-none"
                        :class="getSlotItems(attendee.id).length > 0 ? 'bg-surface-2 hover:bg-surface-3 text-surface-text border-surface-border' : 'bg-primary-500/10 hover:bg-primary-500/20 text-primary-500 border-primary-500/30'"
                    >
                        <i class="pi pi-plus text-xs"></i>
                        <span>{{ getSlotItems(attendee.id).length > 0 ? 'Adicionar Mais' : 'Escolher Lanche' }}</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- RODAPÉ COM RESUMO E SLIDETOCONFIRM -->
        <footer class="p-4 md:p-6 bg-surface-1 border-t border-surface-border shrink-0 flex flex-col gap-3">
            <div class="flex items-center justify-between text-xs font-bold text-surface-muted px-1">
                <span>Total dos Lanches:</span>
                <span class="text-base font-black text-surface-text">{{ $formatCurrency(grandTotal) }}</span>
            </div>

            <div v-if="totalExcess > 0 && allowTab" class="text-[11px] font-bold text-amber-500 bg-amber-500/10 border border-amber-500/20 p-2.5 rounded-xl flex items-center gap-2">
                <i class="pi pi-info-circle shrink-0"></i>
                <span>Excedente total de {{ $formatCurrency(totalExcess) }} será debitado na folha dos colaboradores.</span>
            </div>

            <SlideToConfirm
                class="w-full !h-12"
                color="primary"
                :text="confirmButtonText"
                success-text="Lanches Enviados!"
                icon="pi-send"
                format="long"
                :disabled="!canConfirm"
                :is-loading="isSubmitting"
                :is-success="isSuccess"
                @confirm="submitMealOrder"
            />
        </footer>

        <!-- MODAL DE SELEÇÃO DE PRODUTOS PERMITIDOS -->
        <BaseModal
            v-model:visible="isPickerVisible"
            :title="`Lanche de ${activeAttendee?.name || 'Colaborador'}`"
            size="lg"
            @close="isPickerVisible = false"
        >
            <div class="flex flex-col gap-4 max-h-[70vh]">
                <div class="relative">
                    <input
                        v-model="productSearch"
                        type="text"
                        placeholder="Buscar produto liberado..."
                        class="w-full h-11 pl-10 pr-4 rounded-xl bg-surface-2 border border-surface-border text-surface-text text-sm outline-none focus:border-primary-500"
                    />
                    <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-surface-muted text-sm"></i>
                </div>

                <div class="overflow-y-auto space-y-2 flex-1 pr-1 hide-scrollbar">
                    <button
                        v-for="prod in filteredCatalog"
                        :key="prod.id"
                        type="button"
                        @click="handleSelectProduct(prod)"
                        class="w-full p-3 rounded-2xl bg-surface-2 hover:bg-surface-3 border border-surface-border flex items-center justify-between gap-3 text-left transition-colors cursor-pointer outline-none"
                    >
                        <div class="flex flex-col min-w-0">
                            <span class="font-black text-sm text-surface-text truncate">{{ prod.name }}</span>
                            <span class="text-xs text-surface-muted line-clamp-1 mt-0.5">{{ prod.description }}</span>
                        </div>
                        <span class="font-black text-sm text-primary-smart shrink-0">
                            {{ $formatCurrency(prod.price) }}
                        </span>
                    </button>

                    <div v-if="filteredCatalog.length === 0" class="py-8 text-center text-surface-muted text-xs">
                        Nenhum produto permitido encontrado.
                    </div>
                </div>
            </div>
        </BaseModal>

    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { formatCurrency } from '@/Utils/formatters';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';

const props = defineProps({
    attendees: { type: Array, default: () => [] },
    catalog: { type: Array, default: () => [] },
    companySettings: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['back', 'open-modifiers']);

const slotMap = ref({});
const activeAttendee = ref(null);
const isPickerVisible = ref(false);
const productSearch = ref('');
const isSubmitting = ref(false);
const isSuccess = ref(false);

const allowTab = computed(() => Boolean(props.companySettings?.allow_employee_tab ?? true));
const globalCap = computed(() => Number(props.companySettings?.global_allowance_amount || 0));

const capLabel = computed(() => {
    if (props.companySettings?.allowance_scope === 'individual') {
        return 'Teto individual por colaborador cadastrado no RH.';
    }
    return globalCap.value > 0
        ? `Teto de ${formatCurrency(globalCap.value)} por colaborador.`
        : 'Lanche livre conforme cardápio liberado.';
});

const getInitials = (name) => {
    if (!name) return '??';
    const p = name.trim().split(' ');
    return p.length > 1 ? (p[0][0] + p[1][0]).toUpperCase() : p[0].substring(0, 2).toUpperCase();
};

const getSlotItems = (attendeeId) => slotMap.value[attendeeId] || [];

const getAttendeeCap = (attendeeId) => {
    const attendee = props.attendees.find(a => a.id === attendeeId);
    if (props.companySettings?.allowance_scope === 'individual' && attendee?.custom_allowance > 0) {
        return Number(attendee.custom_allowance);
    }
    return globalCap.value;
};

const getSlotTotal = (attendeeId) => {
    const items = getSlotItems(attendeeId);
    return items.reduce((acc, i) => acc + i.totalPrice, 0);
};

const isSlotExceeded = (attendeeId) => {
    const cap = getAttendeeCap(attendeeId);
    if (cap <= 0) return false;
    return getSlotTotal(attendeeId) > cap;
};

const getSlotStatusText = (attendeeId) => {
    const cap = getAttendeeCap(attendeeId);
    const total = getSlotTotal(attendeeId);
    if (cap <= 0) return 'Coberto';
    if (total <= cap) return '100% Coberto';
    const excess = total - cap;
    return allowTab.value
        ? `Excedente: ${formatCurrency(excess)} (Folha)`
        : `Excedeu teto em ${formatCurrency(excess)}`;
};

const removeItemFromSlot = (attendeeId, index) => {
    if (slotMap.value[attendeeId]) {
        slotMap.value[attendeeId].splice(index, 1);
    }
};

const attendeesWithItemsCount = computed(() => {
    return props.attendees.filter(a => getSlotItems(a.id).length > 0).length;
});

const grandTotal = computed(() => {
    return props.attendees.reduce((acc, a) => acc + getSlotTotal(a.id), 0);
});

const totalExcess = computed(() => {
    return props.attendees.reduce((acc, a) => {
        const cap = getAttendeeCap(a.id);
        const tot = getSlotTotal(a.id);
        return acc + (cap > 0 && tot > cap ? tot - cap : 0);
    }, 0);
});

const hasDisallowedExcess = computed(() => {
    if (allowTab.value) return false;
    return props.attendees.some(a => isSlotExceeded(a.id));
});

const canConfirm = computed(() => {
    return attendeesWithItemsCount.value > 0 && !hasDisallowedExcess.value;
});

const confirmButtonText = computed(() => {
    if (hasDisallowedExcess.value) return 'Teto Excedido (Ajuste os itens)';
    if (attendeesWithItemsCount.value === 0) return 'Escolha ao menos um lanche';
    return `Deslize para Confirmar (${attendeesWithItemsCount.value})`;
});

// FILTRAGEM DE PRODUTOS PERMITIDOS
const filteredCatalog = computed(() => {
    const allowedCats = props.companySettings?.meal_allowance_categories || [];
    const allowedProds = props.companySettings?.meal_allowance_products || [];

    let list = props.catalog.filter(p => p.status === 'available');

    // Restrição por categoria/produto se houver
    if (allowedCats.length > 0 || allowedProds.length > 0) {
        list = list.filter(p => {
            const byCat = allowedCats.length > 0 && allowedCats.includes(p.category_id);
            const byProd = allowedProds.length > 0 && allowedProds.includes(p.id);
            if (allowedCats.length > 0 && allowedProds.length > 0) return byCat || byProd;
            return allowedCats.length > 0 ? byCat : byProd;
        });
    }

    if (productSearch.value.trim()) {
        const q = productSearch.value.toLowerCase().trim();
        list = list.filter(p => p.name.toLowerCase().includes(q) || (p.description && p.description.toLowerCase().includes(q)));
    }

    return list;
});

const openProductPicker = (attendee) => {
    activeAttendee.value = attendee;
    productSearch.value = '';
    isPickerVisible.value = true;
};

const handleSelectProduct = (product) => {
    isPickerVisible.value = false;
    emit('open-modifiers', {
        product: product,
        attendee: activeAttendee.value
    });
};

// Chamado por TeamApp.vue ao confirmar os modificadores
const addItemToActiveAttendee = (attendeeId, itemPayload) => {
    if (!slotMap.value[attendeeId]) {
        slotMap.value[attendeeId] = [];
    }
    slotMap.value[attendeeId].push(itemPayload);
};

defineExpose({
    addItemToActiveAttendee
});

// DISPARO DO PEDIDO
const submitMealOrder = () => {
    if (!canConfirm.value) return;

    const slotsPayload = [];
    props.attendees.forEach(a => {
        const items = getSlotItems(a.id);
        if (items.length > 0) {
            slotsPayload.push({
                attendee_id: a.id,
                items: items.map(i => ({
                    product_id: i.product_id,
                    qty: i.qty,
                    price: i.price,
                    extra_price: i.extraPrice,
                    is_takeaway: i.is_takeaway,
                    notes: i.notes,
                    modifiers: i.structuredModifiers
                }))
            });
        }
    });

    isSubmitting.value = true;
    router.post(route('team.meal-allowance'), { slots: slotsPayload }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            isSuccess.value = true;
            setTimeout(() => {
                slotMap.value = {};
                isSuccess.value = false;
                emit('back');
            }, 1200);
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};
</script>
```

## File: resources/js/Pages/Team/QrScannerModal.vue
```vue
<template>
    <BaseModal
        :modelValue="modelValue"
        @update:modelValue="$emit('update:modelValue', $event)"
        title="Escaneie o QR Code"
        icon="pi-camera"
        width="400px"
    >
        <div class="flex flex-col items-center justify-center p-4 min-h-[300px] relative">

            <!-- Leitor de QR Code -->
            <div class="w-full h-64 rounded-2xl overflow-hidden bg-black relative border-2 border-surface-border">
                <qrcode-stream
                    v-if="modelValue"
                    @detect="$emit('detect', $event)"
                    @error="$emit('error', $event)"
                    :track="paintBoundingBox"
                ></qrcode-stream>

                <!-- Tela de carregamento -->
                <div v-if="isAuthorizing" class="absolute inset-0 bg-surface-900/80 backdrop-blur-sm flex flex-col items-center justify-center text-white z-10">
                    <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-3"></i>
                    <span class="font-black tracking-widest uppercase text-xs">Autorizando...</span>
                </div>
            </div>

            <p class="text-xs text-surface-muted mt-4 font-medium text-center">
                Aponte a câmara para a tela do computador que deseja liberar.
            </p>
        </div>
    </BaseModal>
</template>

<script setup>
import { QrcodeStream } from 'vue-qrcode-reader';

defineProps({
    modelValue: Boolean,
    isAuthorizing: Boolean
});

defineEmits(['update:modelValue', 'detect', 'error']);

// A lógica visual de desenhar o quadrado de rastreio fica encapsulada aqui!
const paintBoundingBox = (detectedCodes, ctx) => {
    for (const detectedCode of detectedCodes) {
        const { boundingBox: { x, y, width, height } } = detectedCode;
        ctx.lineWidth = 4;
        ctx.strokeStyle = '#00d492';
        ctx.strokeRect(x, y, width, height);
    }
};
</script>
```

## File: resources/js/Pages/Team/TeamBarCard.vue
```vue
<template>
    <div class="bg-surface-2 p-4 rounded-[1.5rem] flex flex-col gap-2 transition-all">

        <!-- ROW 1: Contexto e Tempo -->
        <div class="flex items-center justify-between gap-2 border-b border-surface-border/50 pb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-surface-muted truncate flex-1">
                {{ formattedTitle }}
            </span>
            <span class="text-[10px] bg-surface-3 text-surface-muted px-2 py-0.5 rounded font-bold shrink-0">
                #{{ $formatNumber(item.order?.id) }}
            </span>
            <span class="text-[11px] font-black flex items-center gap-1 shrink-0" :class="timerTextColorClass">
                <i class="pi pi-clock text-[10px]"></i> {{ totalTime }}m
            </span>
        </div>

        <!-- ROW 2: O Pedido -->
        <div class="flex flex-col py-1">
            <h4 class="font-black text-surface-text text-base leading-tight">
                <span>{{ item.quantity }}</span><span class="text-surface-muted">×</span> {{ item.product_name || item.name }}
            </h4>

            <div v-if="item.notes || (item.modifiers && item.modifiers.length > 0)" class="text-[11px] font-medium text-surface-muted mt-1 flex flex-col gap-0.5">
                <div v-if="item.notes" class="text-warning-500"><i class="pi fa-exclamation-triangle"></i> Obs: {{ item.notes }}</div>
                <div v-for="mod in item.modifiers" :key="mod.id">
                    + {{ mod.quantity > 1 ? mod.quantity+'x ' : '' }}{{ mod.name }}
                </div>
            </div>
        </div>

        <!-- ROW 3: Ações (Botões e Slider Dinâmico) -->
        <div class="flex items-center gap-2 pt-2 border-t border-surface-border/50">

            <template v-if="isDelivered">
                <div class="w-[200px] h-10 bg-success-500/10 text-success-600 rounded-xl flex items-center justify-center gap-2 font-black text-xs border border-success-500/20">
                    <i class="pi pi-check-circle"></i> Finalizado
                </div>
            </template>

            <template v-else-if="isSomeoneElse">
                <div class="w-[200px] h-10 bg-surface-2 text-surface-muted rounded-xl flex items-center justify-center gap-2 font-black text-[10px] uppercase tracking-widest border border-surface-border">
                    <i class="pi pi-user"></i> Prep: {{ operatorName }}
                </div>
            </template>

            <template v-else-if="isPreparing">
                <SlideToConfirm
                    key="slider-complete"
                    class="w-[200px] !h-10"
                    color="success"
                    text="Finalizar Item"
                    success-text="Enviando!"
                    :is-loading="isCompleting"
                    :is-success="isSuccess"
                    @confirm="handleConfirm('complete')"
                />
            </template>

            <template v-else>
                <SlideToConfirm
                    key="slider-take"
                    class="w-[200px] !h-10"
                    color="primary"
                    text="Pegar Item"
                    success-text="Peguei!"
                    :is-loading="isCompleting"
                    :is-success="isSuccess"
                    @confirm="handleConfirm('take')"
                />
            </template>

            <!-- 🚀 ESPAÇADOR MÁGICO: Empurra os ícones para a direita -->
            <div class="flex-1"></div>

            <IconButton icon="pi-print" color="surface" class="!w-10 !h-10 shrink-0" @click="$emit('print', item)" />
            <IconButton v-if="!isDelivered" icon="pi-trash" color="danger" class="!w-10 !h-10 shrink-0" @click="$emit('cancel', item)" />
        </div>
    </div>
</template>

<script setup>
import { ref, toRefs, computed, watch } from "vue";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useKdsItemTimer } from "@/Pages/Kds/Composables/useKdsItemTimer";
import SlideToConfirm from "@/Components/UI/SlideToConfirm.vue";
import IconButton from "@/Components/UI/IconButton.vue";

const props = defineProps({ item: Object, currentTime: Date, currentUserId: [Number, String] });
const emit = defineEmits(['print', 'cancel', 'take', 'complete']);

const { item, currentTime } = toRefs(props);
const { getOrderTitle } = useOrderFormatting();
const formattedTitle = computed(() => getOrderTitle(item.value.order));
const { totalTime, timerTextColorClass } = useKdsItemTimer(item, currentTime);

const itemStatus = computed(() => item.value.production_status || item.value.status || 'pending');
const isPreparing = computed(() => ['preparing', 'in_progress'].includes(itemStatus.value));
const isDelivered = computed(() => ['delivered', 'ready'].includes(itemStatus.value));

const operatorId = computed(() => item.value.operator_id || item.value.prepared_by_id);
const operatorName = computed(() => item.value.operator_name || item.value.prepared_by_name || 'Colega');
const isSomeoneElse = computed(() => isPreparing.value && operatorId.value && Number(operatorId.value) !== Number(props.currentUserId));

// 🚀 SONDA DE REATIVIDADE: Avisa no console quando o status do item for alterado pelo banco!
watch(() => itemStatus.value, (newStatus) => {
    console.log(`📍 [SONDA CARD] O item #${item.value.id} mudou de status para: ${newStatus}`);
    console.log(`📍 [SONDA CARD] Variáveis reativas -> isPreparing: ${isPreparing.value} | isSomeoneElse: ${isSomeoneElse.value}`);
});

const isCompleting = ref(false);
const isSuccess = ref(false);

const handleConfirm = (actionType) => {
    console.log(`📍 [SONDA SLIDER] Ação detectada: ${actionType}`);
    isCompleting.value = true;

    setTimeout(() => {
        isCompleting.value = false;
        isSuccess.value = true;

        setTimeout(() => {
            isSuccess.value = false;
            if (actionType === 'complete') {
                emit('complete', props.item);
            } else {
                emit('take', props.item);
            }
        }, 400);
    }, 250);
};
</script>
```

## File: resources/js/Pages/Team/TeamCart.vue
```vue
<template>
    <div class="flex-1 w-full h-full flex pt-28 flex-col overflow-hidden bg-surface-2">

        <div class="flex-1 overflow-y-auto px-4 pt-4 pb-36 hide-scrollbar flex flex-col gap-6 relative">

            <!-- EMPTY STATE -->
            <div v-if="cart.length === 0" class="flex-1 flex flex-col items-center justify-center text-center opacity-60 mt-10">
                <div class="w-20 h-20 bg-surface-2 rounded-full flex items-center justify-center text-surface-muted mb-4 shadow-inner">
                    <i class="pi pi-shopping-bag text-3xl"></i>
                </div>
                <h3 class="font-black text-lg text-surface-text">Comanda Vazia</h3>
                <p class="text-sm font-medium text-surface-muted mt-1 px-4">
                    Adicione itens do cardápio para iniciar o pedido.
                </p>
            </div>

            <template v-else>
                <!-- ROTEAMENTO NORMAL -->
                <div v-if="!isAppending" class="w-full">
                    <OrderRouting
                        :model-value="orderType"
                        @update:modelValue="$emit('update:orderType',$event)"
                        :is-open="isRoutingOpen"
                        @open="isRoutingOpen = true"
                        @toggle-open="isRoutingOpen = !isRoutingOpen"
                        @apply="isRoutingOpen = false"
                        :routing-meta="cartRoutingMeta"
                        :table-areas="tableAreas"
                        :customers="customers"
                        :is-team-mode="true"
                        :is-allowance-mode="isAllowanceMode"
                        @routing-change="handleRoutingChange"
                    />
                </div>

                <!-- MODO ADIÇÃO EM CONTA EXISTENTE -->
                <div v-else class="bg-primary-500/10 p-4 rounded-[1.5rem] border border-primary-500/30 shadow-sm flex items-center justify-between mb-2">
                    <div class="flex flex-col min-w-0 pr-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-primary-500">Adicionando à Conta</span>
                        <span class="text-surface-text font-black text-sm truncate">{{ appendOrderTitle }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-primary-500/20 text-primary-600 flex items-center justify-center shrink-0">
                        <i class="pi pi-link text-lg"></i>
                    </div>
                </div>

                <!-- LISTA DE ITENS -->
                <div class="flex flex-col gap-3">
                    <h3 class="font-black text-sm uppercase tracking-widest text-surface-muted px-2">Itens Selecionados</h3>

                    <TeamCartItemCard
                        v-for="(item, index) in cart"
                        :key="item.cartId || index"
                        :item="item"
                        :index="index"
                        @update-qty="(idx, delta) => $emit('update-qty', idx, delta)"
                        @edit-item="(it, idx) => $emit('edit-item', it, idx)"
                        @toggle-takeaway="handleToggleTakeaway"
                    />
                </div>

                <!-- RESUMO FINANCEIRO -->
                <div class="mt-4 pt-4 border-t border-surface-border flex flex-col gap-4">
                    <div class="flex justify-between items-center px-2">
                        <span class="text-sm font-black text-surface-muted uppercase tracking-widest">Total</span>
                        <span class="text-2xl font-black">{{ $formatCurrency(cartSubtotal) }}</span>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { formatCurrency } from '@/Utils/formatters';
import { useToast } from 'primevue/usetoast';
import { useCart } from "@/Pages/Shared/Composables/useCart";
import OrderRouting from "@/Pages/Pos/Components/Cart/OrderRouting.vue";
import TeamCartItemCard from "@/Pages/Team/TeamCartItemCard.vue";

const props = defineProps({
    cart: { type: Array, required: true },
    cartSubtotal: { type: Number, required: true },
    orderType: { type: String, default: 'table' },
    tableAreas: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    editOrderData: { type: Object, default: null },
    isAllowanceMode: { type: Boolean, default: false },
    cartRoutingMeta: { type: Object, default: null }
});

const emit = defineEmits([
    'update-qty', 'clear-cart', 'edit-item', 'checkout',
    'update:orderType', 'routingValid', 'routingMeta'
]);

const toast = useToast();
const { updateQty, draftRouting } = useCart();

const isRoutingOpen = ref(!props.cartRoutingMeta?.table && !draftRouting?.value?.table && props.orderType === 'table');
const isAppending = computed(() => !!props.cartRoutingMeta?.id || !!draftRouting?.value?.id || !!props.editOrderData);

const appendOrderTitle = computed(() => {
    const meta = props.cartRoutingMeta || draftRouting?.value || {};
    const edit = props.editOrderData || {};

    const tableName = meta.table?.name
        || meta.table?.number
        || meta.table_number
        || edit.table?.name
        || edit.table?.number
        || edit.table_number
        || '';

    const orderId = edit.id || meta.id || meta.order_id || '';

    if ((meta.type || edit.type || props.orderType) === 'table') {
        if (tableName) return `Mesa ${tableName}`;
        if (orderId) return `Mesa (Pedido #${orderId})`;
        return 'Mesa Salão';
    }

    return meta.identifier || edit.customer_name || (orderId ? `Pedido #${orderId}` : 'Pedido Aberto');
});

watch(
    () => [props.cartRoutingMeta, props.orderType, isAppending.value],
    ([meta, type, isApp]) => {
        if (isApp) {
            emit('routingValid', true);
            const existing = props.cartRoutingMeta || draftRouting?.value || {};
            const merged = {
                ...existing,
                id: props.editOrderData?.id || existing.id,
                type: props.editOrderData?.type || existing.type || 'table'
            };
            emit('routingMeta', merged);
            return;
        }

        if (meta) {
            const hasValidTable = type === 'table' && (meta.table?.id || meta.table_id || meta.dining_table_id || meta.id);
            const hasValidCounter = type === 'counter' && (meta.customer || meta.identifier || meta.customer_name);
            const hasValidDelivery = type === 'delivery' && (meta.address || meta.address_id);

            if (hasValidTable || hasValidCounter || hasValidDelivery) {
                emit('routingValid', true);
                emit('routingMeta', meta);
            }
        }
    },
    { immediate: true, deep: true }
);

const syncTakeawayState = () => {
    const isAlwaysTakeaway = props.orderType === 'delivery' || props.orderType === 'counter';

    props.cart.forEach(item => {
        if (isAlwaysTakeaway) {
            if (item._originalTakeaway === undefined) {
                item._originalTakeaway = !!item.isTakeaway;
            }
            item.isTakeaway = true;
        } else {
            if (item._originalTakeaway !== undefined) {
                item.isTakeaway = item._originalTakeaway;
                delete item._originalTakeaway;
            }
        }
    });
};

watch(() => props.orderType, syncTakeawayState, { immediate: true });
watch(() => props.cart.length, syncTakeawayState, { immediate: true });

const handleRoutingChange = (payload) => {
    emit('routingValid', payload.isValid);
    emit('routingMeta', payload.meta);
};

const handleToggleTakeaway = (index) => {
    if (props.orderType === 'delivery' || props.orderType === 'counter') {
        toast.add({
            severity: 'info',
            summary: 'Destino Fixo',
            detail: 'Todos os itens de Delivery ou Balcão são para viagem.',
            life: 3000
        });
        return;
    }

    if (props.cart[index]) {
        props.cart[index].isTakeaway = !props.cart[index].isTakeaway;
    }
};

defineExpose({
    toggleRouting: () => {
        isRoutingOpen.value = !isRoutingOpen.value;
    }
});
</script>
```

## File: resources/js/Pages/Team/TeamChecklists.vue
```vue
<template>
    <div class="flex flex-col gap-6">

        <!-- 🚀 1. PORTAL NA DYNAMIC ISLAND: ABAS ATIVAS E HISTÓRICO -->
        <Teleport to="#dynamic-island-portal" v-if="isMounted && isActive && !isLoading">
            <div class="flex p-1 rounded-full gap-1 h-10 md:h-12 shrink-0 mr-2">
                <button @click="activeTab = 'pending'" class="px-4 h-full rounded-full text-[11px] md:text-xs font-black transition-all outline-none uppercase tracking-widest cursor-pointer" :class="activeTab === 'pending' ? 'bg-surface-3 shadow-sm text-primary-smart' : 'text-surface-muted hover:bg-surface-2'">
                    Pendentes
                </button>
                <button @click="activeTab = 'history'" class="px-4 h-full rounded-full text-[11px] md:text-xs font-black transition-all outline-none uppercase tracking-widest cursor-pointer" :class="activeTab === 'history' ? 'bg-surface-3 shadow-sm text-primary-smart' : 'text-surface-muted hover:bg-surface-2'">
                    Histórico
                </button>
            </div>
        </Teleport>

        <!-- Loader -->
        <div v-if="isLoading" class="flex flex-col items-center justify-center py-10 opacity-50">
            <i class="pi pi-spin pi-spinner text-3xl mb-2 text-primary-500"></i>
            <span class="text-xs font-bold tracking-widest uppercase text-surface-muted">Carregando rotinas...</span>
        </div>

        <!-- 🚀 2. LISTAGEM FILTRADA POR ABA -->
        <div v-else-if="displayChecklists.length > 0" class="flex flex-col gap-6">
            <div v-for="checklist in displayChecklists" :key="checklist.id" class="bg-surface-2 border border-surface-border rounded-3xl p-5 shadow-sm animate-in fade-in duration-300">

                <div class="flex justify-between items-end mb-4">
                    <div class="flex flex-col gap-1">
                        <h3 class="font-black text-lg text-surface-text leading-tight">{{ checklist.title }}</h3>
                        <p class="text-xs font-medium text-surface-muted">Grupo de tarefas</p>
                    </div>
                    <!-- O progresso continua a calcular sobre o total original! -->
                    <span class="text-xs font-black text-primary-500">{{ checklist.progress }} / {{ checklist.total_tasks }}</span>
                </div>

                <ProgressBar
                    :current="checklist.progress"
                    :max="checklist.total_tasks"
                    class="mb-6 h-3"
                />

                <div class="flex flex-col gap-3">
                    <div v-for="task in checklist.filteredTasks" :key="task.id" class="flex items-center justify-between p-3 rounded-2xl border transition-all"
                         :class="task.status === 'done' ? 'bg-success-500/10 border-success-500/30' : (task.status === 'issue' ? 'bg-warning-500/10 border-warning-500/30' : 'bg-surface-1 border-surface-border hover:border-primary-500/30')">

                        <div class="flex flex-col min-w-0 pr-4 gap-1.5 flex-1">
                            <span class="text-sm font-bold text-surface-text" :class="{'line-through opacity-60': task.status === 'done'}">
                                {{ task.description }}
                            </span>

                            <p v-if="task.instructions" class="text-xs font-medium text-surface-muted leading-snug" :class="{'opacity-60': task.status === 'done'}">
                                {{ task.instructions }}
                            </p>

                            <div class="flex flex-wrap items-center gap-1.5 mt-1" :class="{'opacity-60': task.status === 'done'}">
                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded"
                                      :class="task.trigger_type === 'manual' ? 'bg-surface-3 text-surface-muted' : 'bg-primary-500/10 text-primary-600 dark:text-primary-400'">
                                    {{ translateTrigger(task.trigger_type) }}
                                </span>
                                <span v-if="task.trigger_type !== 'manual'" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded bg-surface-3 text-surface-muted flex items-center gap-1">
                                    <i class="pi pi-clock text-[8px]"></i> {{ formatDeadline(task) }}
                                </span>
                            </div>

                            <span v-if="task.status === 'issue' && task.notes" class="text-[10px] font-medium text-warning-700 dark:text-warning-400 mt-1 line-clamp-2">
                                <i class="pi fa-exclamation-triangle mr-1"></i> {{ task.notes }}
                            </span>
                        </div>

                        <!-- 🚀 3. BOTÕES DINÂMICOS CONFORME A ABA -->
                        <div class="flex items-center gap-2 shrink-0">

                            <!-- 📦 ATALHO DE INVENTÁRIO (Auditar Saldo) -->
                            <button
                                v-if="task.is_inventory_task && task.inventory_item"
                                type="button"
                                @click.stop="openInventoryAudit(task.inventory_item)"
                                class="h-9 px-3 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 transition-all cursor-pointer outline-none active:scale-95 shadow-xs"
                                title="Conferir estoque deste item"
                            >
                                <i class="pi pi-box text-xs"></i>
                                <span class="hidden sm:inline">Auditar Saldo</span>
                            </button>

                            <!-- ABA PENDENTES: Permite reportar erro ou concluir (Slider) -->
                            <template v-if="activeTab === 'pending'">
                                <button @click="openIssueModal(task)" class="w-12 h-12 rounded-full flex items-center justify-center transition-colors outline-none cursor-pointer shrink-0"
                                        :class="task.status === 'issue' ? 'bg-warning-500 text-white shadow-sm' : 'bg-surface-3 text-surface-muted hover:bg-warning-500/20 hover:text-warning-600'">
                                    <i class="pi fa-exclamation-triangle text-sm"></i>
                                </button>

                                <SlideToConfirm
                                    class="w-36 md:w-40"
                                    text="Concluir"
                                    success-text="Feito!"
                                    @confirm="markTask(task.id, 'done')"
                                />
                            </template>

                            <!-- ABA HISTÓRICO: Mostra botão de Desfazer -->
                            <template v-else>
                                <button @click="markTask(task.id, 'pending')" class="h-10 px-4 rounded-full flex items-center gap-2 font-black uppercase tracking-widest text-[10px] bg-surface-3 text-surface-muted hover:bg-surface-border transition-colors outline-none cursor-pointer" title="Reverter para pendente">
                                    <i class="pi pi-undo text-xs"></i> Desfazer
                                </button>
                            </template>

                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 🚀 4. EMPTY STATES SEPARADOS -->
        <div v-else class="text-center py-16 bg-surface-2 border border-surface-border rounded-3xl animate-in fade-in zoom-in-95 duration-300">
            <template v-if="activeTab === 'pending'">
                <i class="pi pi-check-circle text-5xl text-surface-muted mb-4 opacity-50"></i>
                <h3 class="font-black text-surface-text text-lg">Tudo em dia!</h3>
                <p class="text-sm font-medium text-surface-muted">Não há rotinas pendentes para você neste momento.</p>
            </template>
            <template v-else>
                <i class="pi pi-history text-5xl text-surface-muted mb-4 opacity-50"></i>
                <h3 class="font-black text-surface-text text-lg">Histórico Vazio</h3>
                <p class="text-sm font-medium text-surface-muted">Nenhuma tarefa foi concluída neste turno ainda.</p>
            </template>
        </div>

        <!-- Modal de Reporte de Problema -->
        <BaseModal v-model="isIssueModalVisible" title="Reportar Problema" icon="pi-exclamation-triangle" iconColor="warning" width="400px">
            <div class="flex flex-col gap-3 py-2">
                <p class="text-sm font-medium text-surface-text">{{ selectedTask?.description }}</p>
                <textarea v-model="issueNotes" rows="3" placeholder="Descreva o que aconteceu (Ex: Equipamento quebrado, falta de produto...)" class="w-full p-3 rounded-xl bg-surface-1 border border-surface-border text-sm font-medium focus:border-warning-500 outline-none resize-none"></textarea>
            </div>
            <template #footer>
                <Button label="Cancelar" variant="text" severity="secondary" @click="isIssueModalVisible = false" class="flex-1 font-medium h-12" />
                <Button label="Reportar" severity="warning" @click="submitIssue" :disabled="!issueNotes" class="flex-1 font-black h-12" />
            </template>
        </BaseModal>

        <!-- 🚀 5. MODAL DE CONFERÊNCIA COM O INVENTORY CARD -->
        <Dialog
            v-model:visible="isInventoryAuditModalOpen"
            modal
            header="Conferência de Estoque"
            class="w-[95vw] md:w-[480px]"
            :draggable="false"
        >
            <div class="pt-2">
                <InventoryCard
                    v-if="activeInventoryItem"
                    :item="activeInventoryItem"
                    @updateStatus="handleInventoryStatusUpdate"
                />
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useChecklists } from '@/Composables/useChecklists';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InventoryCard from '@/Pages/Operations/Components/Catalog/InventoryCard.vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';

const page = usePage();
const toast = useToast();

const props = defineProps({
    isActive: {
        type: Boolean,
        default: false
    }
});

const activeTab = ref('pending');
const isMounted = ref(false);

// 🚀 Estado da Auditoria de Estoque
const isInventoryAuditModalOpen = ref(false);
const activeInventoryItem = ref(null);

const {
    checklists,
    isLoading,
    isIssueModalVisible,
    selectedTask,
    issueNotes,
    fetchChecklists,
    translateTrigger,
    formatDeadline,
    markTask,
    openIssueModal,
    submitIssue,
    listenForRealTimeUpdates
} = useChecklists();

// 🚀 Abre o modal clonando a referência para reatividade limpa
const openInventoryAudit = (item) => {
    activeInventoryItem.value = { ...item };
    isInventoryAuditModalOpen.value = true;
};

// 🚀 Trata os eventos emitidos pelo InventoryCard (alteração de status ou saldo físico)
const handleInventoryStatusUpdate = async ({ item, action, new_stock }) => {
    try {
        const payload = {
            id: item.id,
            type: item.type || (item.unit_of_measure ? 'product' : 'supply'),
            status: action,
            stock: new_stock
        };

        const targetRoute = typeof route === 'function' && route().has('operations.inventory.update-status')
            ? route('operations.inventory.update-status')
            : '/operations/inventory/update-status';

        await axios.post(targetRoute, payload);

        // Atualiza a reatividade do modal aberto
        if (activeInventoryItem.value) {
            activeInventoryItem.value.status = action;
            if (new_stock !== null && new_stock !== undefined) {
                activeInventoryItem.value.current_stock = new_stock;
            }
        }

        // Atualiza a reatividade da tarefa correspondente na lista
        checklists.value.forEach(list => {
            list.tasks.forEach(t => {
                if (t.inventory_item && t.inventory_item.id === item.id) {
                    t.inventory_item.status = action;
                    if (new_stock !== null && new_stock !== undefined) {
                        t.inventory_item.current_stock = new_stock;
                    }
                }
            });
        });

        toast.add({
            severity: 'success',
            summary: 'Estoque Atualizado',
            detail: `${item.name} foi atualizado com sucesso.`,
            life: 3000
        });
    } catch (e) {
        toast.add({
            severity: 'error',
            summary: 'Erro',
            detail: e.response?.data?.message || 'Falha ao atualizar inventário.',
            life: 4000
        });
    }
};

// 🚀 Filtra tarefas conforme a aba ativa
const displayChecklists = computed(() => {
    return checklists.value.map(list => {
        const filteredTasks = list.tasks.filter(t => {
            if (activeTab.value === 'pending') return t.status !== 'done';
            return t.status === 'done';
        });

        return {
            ...list,
            filteredTasks,
            hasFilteredTasks: filteredTasks.length > 0
        };
    }).filter(list => list.hasFilteredTasks);
});

onMounted(() => {
    isMounted.value = true;
    fetchChecklists();
    listenForRealTimeUpdates();
});

onUnmounted(() => {
    if (window.Echo) {
        const tenantId = page.props.tenantId || page.props.tenant?.id || window.Laravel?.tenant_id || 'central';
        window.Echo.leave(`tenant.${tenantId}.operations`);
    }
});
</script>
```

## File: resources/js/Pages/Team/TeamHeader.vue
```vue
<template>
    <!-- 🚀 CABEÇALHO GLOBAL DA EQUIPE -->
    <header ref="headerEl" class="fixed top-0 inset-x-0 z-[60] flex flex-col shrink-0 pointer-events-none pb-6 pt-safe">

        <!-- Blur Progressivo -->
        <div class="absolute inset-0 backdrop-blur-xl [mask-image:linear-gradient(to_bottom,black_65%,transparent_100%)] -z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-surface-1/95 via-surface-1/70 to-transparent -z-10"></div>

        <div class="relative z-10 flex flex-col pointer-events-auto w-full">

            <!-- LINHA 1: Logo e Ações -->
            <div class="flex items-center justify-between w-full px-4 py-3">
                <div class="flex items-center gap-3">
                    <img v-if="$page.props.companySettings?.logo_svg_path || $page.props.companySettings?.logo_path"
                         :src="`/media/file?path=${$page.props.companySettings.logo_svg_path || $page.props.companySettings.logo_path}`"
                         alt="Logo" class="h-6 md:h-7 w-auto object-contain drop-shadow-sm dark:brightness-0 dark:invert" />
                    <span v-else class="text-[11px] font-black uppercase tracking-widest text-surface-text">
                        {{ $page.props.companySettings?.trade_name || 'Brasume' }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="$emit('open-notifications')" class="w-9 h-9 rounded-full text-surface-muted flex items-center justify-center hover:text-primary-500 hover:bg-surface-3 transition-all outline-none cursor-pointer shadow-sm relative active:scale-90">
                        <i class="pi pi-bell text-sm"></i>
                        <span class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full bg-danger-500 border-2 border-surface-1"></span>
                    </button>
                </div>
            </div>

            <!-- LINHA 2: Título Contextual + Busca Teleportada -->
            <div class="px-4 flex items-center justify-between gap-3 pb-2 min-h-[44px]">
                <div class="flex flex-col gap-1 min-w-0 flex-1">
                    <template v-if="activeProduct">
                        <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 leading-tight animate-in fade-in">
                            {{ activeProduct.category_name || activeCategory?.name || 'Montando Item' }}
                        </span>
                        <div class="flex items-center justify-between gap-4 animate-in fade-in">
                            <h1 class="text-xl font-black text-surface-text leading-none tracking-tight truncate">
                                {{ activeProduct.name }}
                            </h1>
                            <span class="font-black text-base text-surface-text whitespace-nowrap">
                                R$ {{ Number(currentTotal || activeProduct.price || activeProduct.base_price || 0).toFixed(2).replace('.', ',') }}
                            </span>
                        </div>
                    </template>

                    <template v-else-if="contextTitle">
                        <span v-if="contextSubtitle" class="text-[10px] font-black uppercase tracking-widest text-primary-500 leading-tight animate-in fade-in">
                            {{ contextSubtitle }}
                        </span>
                        <h1 class="text-2xl font-black text-surface-text leading-none tracking-tight truncate animate-in fade-in">
                            {{ contextTitle }}
                        </h1>
                    </template>

                    <template v-else>
                        <h1 class="text-2xl font-black text-surface-text leading-none tracking-tight animate-in fade-in truncate">
                            {{ pageTitle }}
                        </h1>
                    </template>
                </div>

                <!-- 🎯 PORTAL RECEPTOR PARA BUSCA E FILTROS -->
                <div v-show="!activeProduct" class="shrink-0 flex items-center justify-end z-10 gap-2">
                    <div id="team-header-action-portal" class="empty:hidden flex items-center gap-2"></div>
                </div>
            </div>
        </div>

        <div id="team-header-portal-target" class="w-full pointer-events-auto relative z-50 px-4 pb-2 empty:hidden"></div>
    </header>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useDynamicHeaderHeight } from '@/Composables/useDynamicHeaderHeight';

const props = defineProps({
    activeTab: String,
    activeCategory: Object,
    activeProduct: Object,
    currentTotal: { type: [Number, String, Function], default: 0 },
    contextTitle: { type: String, default: null },
    contextSubtitle: { type: String, default: null }
});

defineEmits(['open-notifications']);

const headerEl = ref(null);
useDynamicHeaderHeight(headerEl);

const tabTitles = {
    home: 'Início', operations: 'Comandas', restock: 'Reposição',
    inventory: 'Estoque',
    manager: 'Gestor', fleet: 'Terminais', settings: 'Ajustes',
    wallet: 'Sua Carteira', driver: 'Motoboy', checklists: 'Checklists',
    production: 'Produção', shopping: 'Lista de Compras', cart: 'Carrinho',
    bar: 'Copa & Bar'
};

const pageTitle = computed(() => {
    if (props.activeTab === 'menu') {
        return props.activeCategory?.name || props.activeCategory?.displayName || 'Cardápio';
    }
    return tabTitles[props.activeTab] || '';
});
</script>
```

## File: resources/js/Pages/Team/TeamHome.vue
```vue
<template>
    <div class="p-4 md:p-8 flex flex-col h-full w-full bg-surface-1 overflow-y-auto hide-scrollbar pb-32">
        <div class="max-w-2xl w-full mx-auto flex flex-col gap-6">

            <!-- Cabeçalho de Boas-Vindas -->
            <div class="bg-primary-600 dark:bg-primary-800 p-6 rounded-[2rem] text-white shadow-md flex flex-col gap-2 relative overflow-hidden">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                <h1 class="text-2xl font-black tracking-tight relative z-10">Bom turno, {{ firstName }}!</h1>
                <p class="text-primary-100 text-sm relative z-10">Estas são as suas rotinas para hoje.</p>
            </div>

            <!-- Componente Desacoplado das Rotinas -->
            <TeamChecklists />

        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import TeamChecklists from '@/Pages/Team/TeamChecklists.vue';
import DesktopLayout from "@/Layouts/DesktopLayout.vue";

defineOptions({ layout: DesktopLayout });

const page = usePage();
const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] || 'Equipe');
</script>
```

## File: resources/js/Pages/Team/TeamModulesModal.vue
```vue
<template>
    <AdaptiveModal
    v-model:visible="isVisible"
    class="p-6"
    >

        <!-- CABEÇALHO COMPACTO E MONOCROMÁTICO -->
        <div class="flex flex-col items-center justify-center gap-2 shrink-0 mb-4 mt-1">
            <div class="flex flex-col items-center text-center">
                <h2 class="text-base font-black text-surface-text leading-tight">Módulos da Operação</h2>
                <span class="text-[9px] font-bold uppercase tracking-widest text-surface-muted">Navegação Rápida</span>
            </div>
        </div>

        <!-- GRELHA COMPACTA -->
        <div class="grid grid-cols-3 gap-2 pb-2">

            <!-- GESTOR (Apenas se houver métricas de turno ativas) -->
            <button
                v-if="managerData"
                @click="handleSelect('manager')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-chart-line text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Gestor</span>
            </button>

            <!-- 🚀 TERMINAIS (Liberado para Gestores/Admins independentemente do caixa estar aberto) -->
            <button
                v-if="canManageFleet"
                @click="handleSelect('fleet')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-unlock text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Terminais</span>
            </button>

            <!-- REPOSIÇÃO (WMS PRATELEIRA) -->
            <button
                @click="handleSelect('restock')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none relative transition-all active:scale-95 cursor-pointer"
            >
                <span
                    v-if="restockCount > 0"
                    class="absolute top-1.5 right-1.5 bg-surface-text text-surface-1 text-[9px] font-black w-4 h-4 flex items-center justify-center rounded-full shadow-xs"
                >
                    {{ restockCount }}
                </span>
                <i class="pi pi-objects-column text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Reposição</span>
            </button>

            <!-- ESTOQUE / INVENTÁRIO (WMS GERAL) -->
            <button
                @click="handleSelect('inventory')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-box text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Estoque</span>
            </button>

            <!-- TAREFAS / CHECKLISTS -->
            <button
                @click="handleSelect('checklists')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-list-check text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Tarefas</span>
            </button>

            <!-- PRODUÇÃO (KDS / MRP) -->
            <button
                @click="handleSelect('production')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-clipboard text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Produção</span>
            </button>

            <!-- COMPRAS -->
            <button
                @click="handleSelect('shopping')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-shopping-bag text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Compras</span>
            </button>

            <!-- MOTOBOY -->
            <button
                @click="handleSelect('driver')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-truck text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Motoboy</span>
            </button>

            <!-- PERFIL / CARTEIRA -->
            <button
                @click="handleSelect('wallet')"
                class="flex flex-col items-center justify-center gap-1.5 p-2.5 rounded-2xl bg-surface-1/50 text-surface-text outline-none transition-all active:scale-95 cursor-pointer"
            >
                <i class="pi pi-id-card text-xl text-surface-muted"></i>
                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">Perfil</span>
            </button>

        </div>
    </AdaptiveModal>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

const props = defineProps({
    visible: { type: Boolean, required: true },
    managerData: { type: [Object, Boolean], default: false },
    restockCount: { type: Number, default: 0 }
});

const emit = defineEmits(['update:visible', 'select-tab']);

const page = usePage();

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

// 🛡️ Avaliação de perfil: Libera para gestores mesmo sem turno ativo
const canManageFleet = computed(() => {
    if (props.managerData) return true;

    const user = page.props.auth?.user;
    if (!user) return false;

    const role = (user.role || '').toLowerCase();
    const roles = Array.isArray(user.roles)
        ? user.roles.map(r => (typeof r === 'string' ? r : r.name || '').toLowerCase())
        : [];

    const authorizedRoles = ['admin', 'gerente', 'manager', 'owner', 'gestor'];
    return authorizedRoles.includes(role) || roles.some(r => authorizedRoles.includes(r));
});

const handleSelect = (tab) => {
    emit('select-tab', tab);
    isVisible.value = false;
};
</script>
```

## File: resources/js/Pages/Team/TeamOverlays.vue
```vue
<template>
    <!-- OVERLAYS (MODIFIERS E COMBOS) -->
    <Transition name="page-fade">
        <div v-if="isModifiersVisible || isComboVisible" class="fixed inset-0 z-50 bg-surface-1 flex flex-col">
            <ProductModifiers
                v-if="isModifiersVisible"
                :product="activeProduct"
                :is-edit-mode="isEditMode"
                :is-team-mode="true"
                @update:total="$emit('update:total', $event)"
                @confirm="$emit('confirm-modifiers', $event)"
                @close="$emit('close-configurators')"
            />
            <ComboWizard
                v-else-if="isComboVisible"
                :product="activeProduct"
                :global-products-list="initialProducts"
                :is-edit-mode="isEditMode"
                :is-team-mode="true"
                @update:total="$emit('update:total', $event)"
                @confirm-combo="$emit('confirm-combo', $event)"
                @close="$emit('close-configurators')"
            />
        </div>
    </Transition>

    <OrderContextMenu
        :visible="contextMenuVisible"
        @update:visible="$emit('update:contextMenuVisible', $event)"
        :show-actions="showOrderActionsSheet"
        @update:show-actions="$emit('update:showOrderActionsSheet', $event)"
        :order="selectedOrderContext"
        :company-settings="companySettings"
        @action="(actionId, orderObj) => $emit('action', actionId, orderObj)"
        @duplicate-item="(item, cb) => $emit('duplicate-item', item, cb)"
        @modify-item="$emit('modify-item', $event)"
        @swap-item="$emit('swap-item', $event)"
        @cancel-item="$emit('cancel-item', $event)"
        @reprint-item="$emit('reprint-item', $event)"
    />
</template>

<script setup>
import ProductModifiers from "@/Pages/Shared/Configurators/ProductModifiers.vue";
import ComboWizard from "@/Pages/Shared/Configurators/ComboWizard.vue";
import OrderContextMenu from "@/Pages/Shared/Order/OrderContextMenu.vue";

defineProps({
    isModifiersVisible: Boolean,
    isComboVisible: Boolean,
    activeProduct: Object,
    isEditMode: Boolean,
    initialProducts: Array,
    contextMenuVisible: Boolean,
    showOrderActionsSheet: Boolean,
    selectedOrderContext: Object,
    companySettings: Object
});

defineEmits([
    'update:total',
    'confirm-modifiers', 'confirm-combo', 'close-configurators',
    'update:contextMenuVisible', 'update:showOrderActionsSheet',
    'action', 'duplicate-item', 'modify-item', 'swap-item', 'cancel-item', 'reprint-item'
]);
</script>

<style scoped>
.page-fade-enter-active, .page-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.page-fade-enter-from, .page-fade-leave-to { opacity: 0; transform: scale(0.98); }
</style>
```

## File: resources/js/Pages/Team/TeamShiftLockOverlay.vue
```vue
<template>
    <div class="flex-1 flex flex-col items-center justify-center p-6 text-center bg-surface-2 z-50 absolute inset-0 select-none">
        <div class="w-24 h-24 bg-red-500/10 text-red-500 rounded-full flex items-center justify-center mb-6 shadow-inner">
            <i class="pi pi-lock text-4xl"></i>
        </div>
        <h1 class="text-2xl font-black text-surface-text mb-2">Turno Fechado</h1>
        <p class="text-surface-muted mb-8 max-w-xs mx-auto text-sm font-medium">O seu ponto ainda não foi registado.</p>
        <button
            @click="$emit('startShift')"
            :disabled="isStarting"
            class="w-full max-w-xs bg-primary-500 text-white font-black py-4 rounded-2xl cursor-pointer hover:bg-primary-600 transition-colors shadow-md active:scale-95 border-none outline-none flex items-center justify-center"
        >
            <i v-if="isStarting" class="pi pi-spinner pi-spin mr-2"></i>
            <span>{{ isStarting ? 'A registar...' : 'Iniciar Turno Agora' }}</span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    isStarting: { type: Boolean, default: false }
});

defineEmits(['startShift']);
</script>
```

## File: resources/js/Pages/Team/DeviceFleetPanel.vue
```vue
<template>
    <div class="p-4 pt-28 pb-32 max-w-4xl mx-auto flex flex-col gap-4 select-none">

        <!-- 🚀 BOTÃO TELEPORT NA MESMA ROW DO TÍTULO NO TEAMHEADER -->
        <Teleport v-if="isMounted" to="#team-header-action-portal">
            <PosTeleportButton
                icon="pi-camera"
                color="primary"
                label="Escanear QR de Terminal"
                @click="openScanner"
            />
        </Teleport>

        <!-- LOADING -->
        <div v-if="isLoading" class="flex justify-center py-12">
            <i class="pi pi-spinner pi-spin text-3xl text-primary-500"></i>
        </div>

        <!-- ESTADO VAZIO -->
        <div v-else-if="devices.length === 0" class="text-center py-12 bg-surface-2 rounded-2xl border border-surface-border flex flex-col items-center">
            <i class="pi pi-inbox text-4xl text-surface-muted mb-2 opacity-60"></i>
            <p class="text-sm font-bold text-surface-text">Nenhum terminal cadastrado</p>
            <p class="text-xs text-surface-muted mt-0.5">Homologue uma máquina para ela aparecer aqui.</p>
        </div>

        <!-- LISTA DE TERMINAIS (CARDS COMPACTOS COM SLIDETOCONFIRM) -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
            <div
                v-for="device in devices"
                :key="device.uuid"
                class="bg-surface-3 border rounded-3xl p-3.5 flex flex-col justify-between gap-2.5 relative overflow-hidden transition-all duration-200 shadow-xs"
                :class="[
                    device.is_waiting ? 'border-warning-500/60 bg-warning-500/[0.02]' : 'border-transparent',
                    device.status === 'operating' && !device.is_waiting ? 'border-success-500/30' : ''
                ]"
            >
                <!-- Barra luminosa para terminal em espera -->
                <div v-if="device.is_waiting" class="absolute top-0 left-0 right-0 h-1 bg-warning-500 animate-pulse"></div>

                <!-- LINHA SUPERIOR: ÍCONE + IDENTIFICAÇÃO + STATUS -->
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-xs"
                            :class="device.type === 'kds'
                                ? 'bg-orange-500/10 text-orange-500'
                                : 'bg-primary-500/10 text-primary-500'"
                        >
                            <i class="pi text-sm" :class="device.type === 'kds' ? 'pi-receipt' : 'pi-desktop'"></i>
                        </div>

                        <div class="flex flex-col min-w-0">
                            <span class="font-black text-xs text-surface-text truncate leading-tight">
                                {{ device.name }}
                            </span>
                            <div class="flex items-center gap-1.5 text-[10px] text-surface-muted leading-tight mt-0.5 font-medium">
                                <span>{{ device.type === 'kds' ? 'KDS / Cozinha' : 'POS / Caixa' }}</span>
                                <span class="opacity-40">•</span>
                                <span class="font-mono text-[9px] opacity-70" title="UUID do Dispositivo">
                                    {{ device.uuid.split('-')[0] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BADGE DE STATUS -->
                    <span
                        v-if="device.is_waiting"
                        class="text-[9px] font-black uppercase tracking-wider text-warning-500 bg-warning-500/10 border border-warning-500/20 px-2 py-0.5 rounded-full flex items-center gap-1 shrink-0"
                    >
                        <i class="pi pi-bell text-[8px] animate-bounce"></i>
                        Aguardando
                    </span>
                    <span
                        v-else-if="device.status === 'operating'"
                        class="text-[9px] font-black uppercase tracking-wider text-success-500 bg-success-500/10 border border-success-500/20 px-2 py-0.5 rounded-full flex items-center gap-1 shrink-0"
                    >
                        <i class="pi pi-circle-fill text-[6px] animate-pulse"></i>
                        Operando
                    </span>
                    <span
                        v-else
                        class="text-[9px] font-black uppercase tracking-wider text-surface-muted bg-surface-1 border border-surface-border px-2 py-0.5 rounded-full flex items-center gap-1 shrink-0"
                    >
                        <i class="pi pi-lock text-[8px]"></i>
                        Bloqueada
                    </span>
                </div>

                <!-- LINHA DO OPERADOR (COMPACTA) -->
                <div
                    v-if="device.operator"
                    class="flex items-center justify-between px-2.5 py-1.5 bg-surface-1 border border-surface-border/50 rounded-xl"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-5 h-5 rounded-full bg-success-500/15 text-success-500 flex items-center justify-center text-[9px] font-black uppercase shrink-0">
                            {{ device.operator.name.charAt(0) }}
                        </div>
                        <span class="text-[11px] font-bold text-surface-text truncate">
                            {{ formatName(device.operator.name) }}
                        </span>
                    </div>
                    <span class="text-[9px] uppercase tracking-wider text-surface-muted font-bold shrink-0 ml-2">
                        {{ device.operator.role || 'Operador' }}
                    </span>
                </div>

                <!-- AÇÕES COM SLIDETOCONFIRM -->
                <div class="pt-0.5">
                    <!-- 🛡️ 1. SLIDE PARA LIBERAR / AUTORIZAR ACESSO -->
                    <SlideToConfirm
                        v-if="device.is_waiting || device.status !== 'operating'"
                        :text="device.is_waiting ? 'Autorizar' : 'Liberar'"
                        success-text="Liberado!"
                        icon="pi-unlock"
                        :color="device.is_waiting ? 'warning' : 'primary'"
                        :disabled="isSubmitting === device.uuid"
                        :is-loading="isSubmitting === device.uuid"
                        format="long"
                        @confirm="toggleDeviceAccess(device)"
                        class="!h-10 text-[10px] w-full"
                    />

                    <!-- 🛑 2. SLIDE PARA BLOQUEAR SESSÃO OPERANTE -->
                    <SlideToConfirm
                        v-else
                        text="Bloquear"
                        success-text="Bloqueado!"
                        icon="pi-lock"
                        color="danger"
                        :disabled="isSubmitting === device.uuid"
                        :is-loading="isSubmitting === device.uuid"
                        format="long"
                        @confirm="toggleDeviceAccess(device)"
                        class="!h-10 text-[10px] w-full"
                    />
                </div>
            </div>
        </div>

        <!-- MODAL DA CÂMERA (QR SCANNER) -->
        <div v-if="isScanning" class="fixed inset-0 z-[9999] bg-black flex flex-col animate-in fade-in zoom-in-95 duration-300">
            <div class="absolute top-0 w-full p-4 flex justify-between items-center z-10 bg-gradient-to-b from-black/80 to-transparent">
                <div class="text-white">
                    <h3 class="font-black text-lg">Escanear Terminal</h3>
                    <p class="text-xs opacity-80">Aponte para o QR Code na tela do computador</p>
                </div>
                <button
                    type="button"
                    @click="closeScanner"
                    class="w-10 h-10 rounded-full bg-surface-800/80 text-white flex items-center justify-center outline-none border-none cursor-pointer"
                >
                    <i class="pi pi-times"></i>
                </button>
            </div>

            <div class="flex-1 w-full bg-black flex items-center justify-center relative overflow-hidden">
                <qrcode-stream @detect="onDetectQrCode" @error="onCameraError" class="h-full w-full object-cover"></qrcode-stream>
                <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                    <div class="w-64 h-64 border-2 border-white/50 rounded-3xl relative">
                        <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-primary-500 rounded-tl-3xl"></div>
                        <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-primary-500 rounded-tr-3xl"></div>
                        <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-primary-500 rounded-bl-3xl"></div>
                        <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-primary-500 rounded-br-3xl"></div>
                    </div>
                </div>
            </div>
        </div>

        <QuickUnlockSheet
            v-model:visible="isQuickUnlockVisible"
            :device-uuid="scannedDeviceUuid"
            :token="activeSessionToken"
            @unlocked="fetchDevices"
        />

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { QrcodeStream } from 'vue-qrcode-reader';
import QuickUnlockSheet from '@/Pages/Gateway/QuickUnlockSheet.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';
import PosTeleportButton from '@/Components/UI/PosTeleportButton.vue';

const toast = useToast();
const devices = ref([]);
const isLoading = ref(true);
const isSubmitting = ref(null);
const isMounted = ref(false);
let pollInterval = null;

const isScanning = ref(false);
const isQuickUnlockVisible = ref(false);
const activeSessionToken = ref('');
const scannedDeviceUuid = ref('');

const formatName = (name) => {
    if (!name) return 'Operador';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0];
    return `${parts[0]} ${parts[parts.length - 1].charAt(0)}.`;
};

const fetchDevices = async () => {
    try {
        const response = await axios.get(route('auth.remote.fleet'));
        devices.value = response.data;
    } catch (error) {
        console.error('Erro ao buscar frota:', error);
    } finally {
        isLoading.value = false;
    }
};

const toggleDeviceAccess = async (device) => {
    isSubmitting.value = device.uuid;

    try {
        if (device.status === 'operating' && !device.is_waiting) {
            await axios.post(route('team.remote-unlock.lock'), { device_uuid: device.uuid });
            device.status = 'locked';
            device.operator = null;
            toast.add({
                severity: 'info',
                summary: 'Terminal Bloqueado',
                detail: `A sessão de ${device.name} foi encerrada.`,
                life: 3000
            });
        } else {
            await axios.post(route('team.remote-unlock.approve'), { device_uuid: device.uuid });
            device.is_waiting = false;
            device.status = 'operating';
            toast.add({
                severity: 'success',
                summary: 'Terminal Liberado',
                detail: `${device.name} liberado com sucesso.`,
                life: 3000
            });
        }

        await fetchDevices();

    } catch (error) {
        const msg = error.response?.data?.error || 'Falha na comunicação com o terminal.';
        toast.add({ severity: 'error', summary: 'Ação Interrompida', detail: msg, life: 3000 });
    } finally {
        isSubmitting.value = null;
    }
};

const openScanner = () => { isScanning.value = true; };
const closeScanner = () => { isScanning.value = false; };

const onDetectQrCode = async (detectedCodes) => {
    if (!detectedCodes || detectedCodes.length === 0) return;
    const scannedText = detectedCodes[0].rawValue;
    if (!scannedText) return;

    closeScanner();

    let authToken = scannedText;
    if (scannedText.includes('/')) {
        const urlParts = scannedText.split('/');
        authToken = urlParts[urlParts.length - 1];
    } else if (scannedText.includes('|')) {
        authToken = scannedText.split('|')[1];
    }

    try {
        const response = await axios.post(route('auth.qr.authorize'), {
            token: authToken
        });

        toast.add({
            severity: 'success',
            summary: 'Terminal Liberado',
            detail: response.data?.message || 'Acesso autorizado com sucesso!',
            life: 3000
        });

        await fetchDevices();

    } catch (error) {
        console.error('Erro na autorização do QR Code:', error);
        toast.add({
            severity: 'error',
            summary: 'Acesso Negado',
            detail: error.response?.data?.error || 'QR Code inválido.',
            life: 4000
        });
    }
};

const onCameraError = (error) => {
    closeScanner();
    let errorMsg = 'Erro desconhecido ao abrir a câmera.';
    if (error.name === 'NotAllowedError') errorMsg = 'Permissão de câmera necessária.';
    else if (error.name === 'NotFoundError') errorMsg = 'Nenhuma câmera encontrada no dispositivo.';
    else if (error.name === 'NotSupportedError') errorMsg = 'Acesso à câmera requer conexão segura (HTTPS).';

    toast.add({ severity: 'error', summary: 'Câmera Indisponível', detail: errorMsg, life: 4000 });
};

onMounted(() => {
    fetchDevices();
    pollInterval = setInterval(fetchDevices, 10000);

    // Valida que o container existe no DOM antes de montar o Teleport
    nextTick(() => {
        isMounted.value = !!document.getElementById('team-header-action-portal');
    });
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>
```

## File: resources/js/Pages/Team/InventoryItemRow.vue
```vue
<template>
    <div
        class="flex flex-col transition-colors relative"
        :class="[
            isExpanded ? 'bg-surface-3/40 ring-1 ring-primary-500/30' : '',
            isTouched && !isExpanded ? 'bg-success-500/5' : ''
        ]"
    >
        <!-- ============================================== -->
        <!-- MODO A: INVENTÁRIO (CONTAGEM POR PONTO)         -->
        <!-- ============================================== -->
        <template v-if="activeMode === 'inventory'">
            <!-- 1. CABEÇALHO COMPACTO -->
            <div
                @click="$emit('toggle-expand')"
                class="p-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-surface-1/40 active:bg-surface-1/60 transition-colors select-none"
            >
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-surface-text text-sm leading-tight truncate">
                        {{ item.name }}
                    </h4>
                    <!-- 🏷️ Categoria + Quantidade Esperada -->
                    <span class="text-[10px] text-surface-muted truncate block mt-0.5">
                        {{ item.category?.name || 'Geral' }} <span class="opacity-40 mx-1">|</span> Esperado: <strong class="text-surface-text font-bold">{{ Number(item.local_expected_stock ?? 0) }}</strong> {{ item.unit_of_measure }}
                    </span>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <template v-if="isTouched">
                        <div class="flex flex-col items-end">
                            <span class="text-xs font-black text-surface-text flex items-center gap-1">
                                {{ displayValue }}
                                <span class="text-[9px] font-bold text-surface-muted">{{ item.unit_of_measure }}</span>
                            </span>
                            <span
                                class="text-[9px] font-black uppercase tracking-wider"
                                :class="delta < 0 ? 'text-danger-500' : (delta > 0 ? 'text-emerald-500' : 'text-surface-muted')"
                            >
                                {{ deltaLabel }}
                            </span>
                        </div>
                        <div class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-[10px] font-black">
                            <i class="pi pi-check"></i>
                        </div>
                    </template>

                    <template v-else>
                        <span class="px-2.5 py-1 rounded-lg bg-surface-3 text-surface-muted text-xs font-black border border-surface-border/50">
                            --
                        </span>
                        <i
                            class="pi pi-chevron-down text-[10px] text-surface-muted/60 transition-transform duration-200"
                            :class="{ 'rotate-180 text-primary-smart': isExpanded }"
                        ></i>
                    </template>
                </div>
            </div>

            <!-- 2. CORPO EXPANDIDO -->
            <div
                v-if="isExpanded"
                class="px-3 pb-3.5 pt-1 border-t border-surface-border/40 flex flex-col gap-2.5 bg-surface-1/40 animate-in fade-in slide-in-from-top-1 duration-150"
            >
                <!-- Linha Auxiliar (Exibida apenas se houver fardo ou diferença de estoque total) -->
                <div
                    v-if="(item.conversion_factor && item.conversion_factor > 1) || (item.total_system_stock !== item.local_expected_stock)"
                    class="flex items-center justify-between text-xs px-0.5"
                >
                    <span v-if="item.total_system_stock !== item.local_expected_stock" class="text-[10px] text-surface-muted">
                        Total na loja: <strong class="text-surface-text">{{ Number(item.total_system_stock) }}</strong> {{ item.unit_of_measure }}
                    </span>
                    <div v-else></div>

                    <button
                        v-if="item.conversion_factor && item.conversion_factor > 1"
                        type="button"
                        @click.stop="addPack(item.conversion_factor)"
                        class="h-6 px-2 rounded-lg bg-surface-3 hover:bg-surface-border text-surface-text border border-surface-border text-[9px] font-black uppercase tracking-wider flex items-center gap-1 cursor-pointer transition-all active:scale-95"
                        :title="`Adicionar 1 fardo com ${item.conversion_factor} unidades`"
                    >
                        <i class="pi pi-box text-[9px] text-amber-500"></i>
                        <span>+1 Fd ({{ item.conversion_factor }})</span>
                    </button>
                </div>

                <!-- 🚀 LINHA INTEGRADA: QTYSTEPPER + SLIDETOCONFIRM (MODO SUCCESS / "CONFIRMAR") -->
                <div class="flex items-center gap-2 w-full">
                    <!-- Stepper com input manual direto no centro -->
                    <QtyStepper
                        v-model="localCount"
                        :min="0"
                        :step="stepValue"
                        size="md"
                        class="bg-surface-2 border border-surface-border/80 shadow-inner w-32 shrink-0"
                    >
                        <template #value>
                            <input
                                type="text"
                                inputmode="decimal"
                                :value="localCount"
                                @input="handleManualInput($event.target.value)"
                                class="w-full text-center bg-transparent border-none text-sm font-black outline-none text-surface-text p-0 select-text cursor-text"
                                @click.stop
                            />
                        </template>
                    </QtyStepper>

                    <!-- Slider de Confirmação em Modo Success -->
                    <div class="flex-1 min-w-0">
                        <SlideToConfirm
                            text="Confirmar"
                            success-text="Confirmado!"
                            color="success"
                            icon="pi-check"
                            format="long"
                            :is-loading="isSaving"
                            class="!h-11 shadow-sm text-[11px]"
                            @confirm="onSlideConfirm"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- ============================================== -->
        <!-- MODO B: STATUS (DISPONÍVEL / PAUSADO / ESGOT.) -->
        <!-- ============================================== -->
        <template v-else>
            <div class="p-3 flex items-center justify-between gap-3 w-full">
                <div class="min-w-0 flex-1">
                    <h4 class="font-bold text-surface-text leading-tight text-sm truncate">
                        {{ item.name }}
                    </h4>
                    <span class="text-[10px] text-surface-muted truncate block mt-0.5">
                        {{ item.category?.name || 'Geral' }} <span class="opacity-40 mx-1">|</span> Esperado: <strong class="text-surface-text font-bold">{{ Number(item.local_expected_stock ?? 0) }}</strong> {{ item.unit_of_measure }}
                    </span>
                </div>

                <div class="shrink-0 flex items-center bg-surface-1/80 p-0.5 rounded-full border border-surface-border/60 gap-0.5">
                    <button
                        type="button"
                        @click="$emit('update-status', 'available')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'available' ? 'bg-emerald-500 text-white shadow-xs' : 'text-surface-muted hover:text-emerald-500 hover:bg-emerald-500/10'"
                        title="Disponível"
                    >
                        <i class="pi pi-check text-[10px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="$emit('update-status', 'paused')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'paused' ? 'bg-amber-500 text-white shadow-xs' : 'text-surface-muted hover:text-amber-500 hover:bg-amber-500/10'"
                        title="Pausar"
                    >
                        <i class="pi pi-pause text-[10px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="$emit('update-status', 'out_of_stock')"
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
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import QtyStepper from '@/Components/UI/QtyStepper.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';

const props = defineProps({
    item: { type: Object, required: true },
    locationId: { type: [Number, String], required: true },
    activeMode: { type: String, default: 'inventory' },
    isExpanded: { type: Boolean, default: false },
    isTouched: { type: Boolean, default: false },
    displayValue: { type: [String, Number], default: '--' },
    delta: { type: Number, default: 0 },
    deltaLabel: { type: String, default: 'Exato' },
    isSaving: { type: Boolean, default: false }
});

const emit = defineEmits(['toggle-expand', 'save', 'update-status']);

const localCount = ref(0);

watch(() => props.isExpanded, (expanded) => {
    if (expanded) {
        if (props.displayValue !== '--' && props.displayValue !== null && props.displayValue !== undefined) {
            localCount.value = Number(props.displayValue);
        } else {
            localCount.value = Number(props.item.local_expected_stock ?? 0);
        }
    }
});

const stepValue = computed(() => {
    const uom = (props.item.unit_of_measure || '').toLowerCase();
    return (uom === 'kg' || uom === 'l' || uom === 'litro' || uom === 'quilo') ? 0.5 : 1;
});

const handleManualInput = (val) => {
    if (val === '' || val === null || val === undefined) {
        localCount.value = 0;
        return;
    }
    const clean = Number(val.replace(',', '.'));
    if (!isNaN(clean)) {
        localCount.value = Math.max(0, clean);
    }
};

const addPack = (factor) => {
    localCount.value = Number((Number(localCount.value || 0) + Number(factor || 1)).toFixed(3));
};

const onSlideConfirm = () => {
    emit('save', Number(localCount.value || 0));
};
</script>
```

## File: resources/js/Pages/Team/ManagerShiftDashboard.vue
```vue
<template>
    <!-- Adicionamos min-h-screen para mobile ficar elegante -->
    <div class="min-h-screen bg-surface-1 flex flex-col font-sans pb-20">

        <!-- HEADER MOBILE -->
        <div class="bg-surface-900 text-white p-6 rounded-b-4xl shadow-md">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-xl font-black">Visão do Gerente</h1>
                <span :class="currentShift ? 'bg-success-500/20 text-success-400' : 'bg-danger-500/20 text-danger-400'" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                    {{ currentShift ? 'Turno Aberto' : 'Turno Fechado' }}
                </span>
            </div>

            <p v-if="currentShift" class="text-surface-400 text-xs mb-4 font-medium flex items-center gap-2">
                <i class="pi pi-clock"></i> Aberto às {{ formattedShiftStart }}
            </p>

            <div class="bg-surface-800 rounded-2xl p-5 border border-surface-700 shadow-inner">
                <p class="text-[10px] font-black opacity-80 uppercase tracking-widest mb-1 text-primary-400">Faturamento Realizado</p>
                <h2 class="text-3xl font-black mb-1 text-white">
                    {{ $formatCurrency(displayRevenue) }}
                </h2>
                <div class="flex items-center gap-4 mt-3 text-xs">
                    <div><span class="opacity-60">Pedidos: </span><span class="font-bold">{{ displayOrderCount }}</span></div>
                    <div><span class="opacity-60">Ticket Médio: </span><span class="font-bold">{{ $formatCurrency(displayAvgTicket) }}</span></div>
                </div>
            </div>
        </div>

        <!-- CONTEÚDO -->
        <div class="p-4 sm:p-6 mt-2 flex flex-col gap-6">

            <!-- CAIXA E AUDITORIA -->
            <div>
                <h3 class="font-black text-surface-text mb-3 uppercase tracking-widest text-[10px] opacity-50 ml-2">Monitoramento de Caixa</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-surface-2 rounded-2xl p-4 border border-surface-border shadow-sm flex flex-col justify-center">
                        <span class="text-[9px] font-black uppercase tracking-widest text-surface-muted">Saldo Inicial</span>
                        <span class="text-lg font-black text-surface-text mt-1">{{ $formatCurrency(currentShift?.opening_balance || 0) }}</span>
                    </div>

                    <div class="bg-surface-2 rounded-2xl p-4 border border-surface-border shadow-sm flex flex-col justify-center">
                        <span class="text-[9px] font-black uppercase tracking-widest text-surface-muted">Dinheiro Físico Esperado</span>
                        <span class="text-lg font-black text-success-600 mt-1">{{ $formatCurrency(managerData?.expected_cash || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- DISTRIBUIÇÃO DE RECEITA -->
            <div>
                <h3 class="font-black text-surface-text mb-3 uppercase tracking-widest text-[10px] opacity-50 ml-2">Distribuição por Canal</h3>
                <div class="flex flex-col gap-3">
                    <div class="bg-surface-2 p-4 rounded-2xl border border-surface-border flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary-500/10 text-primary-600 flex items-center justify-center shrink-0">
                                <i class="pi pi-truck text-sm font-black"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-text text-sm leading-tight">Delivery</span>
                                <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ byType.delivery.count }} pedidos</span>
                            </div>
                        </div>
                        <span class="font-black text-base text-surface-text">{{ $formatCurrency(byType.delivery.amount) }}</span>
                    </div>

                    <div class="bg-surface-2 p-4 rounded-2xl border border-surface-border flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-success-500/10 text-success-600 flex items-center justify-center shrink-0">
                                <i class="pi pi-table text-sm font-black"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-text text-sm leading-tight">Salão (Mesas)</span>
                                <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ byType.table.count }} pedidos</span>
                            </div>
                        </div>
                        <span class="font-black text-base text-surface-text">{{ $formatCurrency(byType.table.amount) }}</span>
                    </div>

                    <div class="bg-surface-2 p-4 rounded-2xl border border-surface-border flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center shrink-0">
                                <i class="pi pi-shopping-bag text-sm font-black"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-text text-sm leading-tight">Balcão</span>
                                <span class="text-[10px] font-black text-surface-muted uppercase tracking-wider">{{ byType.counter.count }} pedidos</span>
                            </div>
                        </div>
                        <span class="font-black text-base text-surface-text">{{ $formatCurrency(byType.counter.amount) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentShift: { type: Object, default: () => null },
    managerData: { type: Object, default: () => null }
});

const formatMoney = (value) => parseFloat(value || 0).toFixed(2).replace('.', ',');

// Lemos diretamente das métricas consolidadas pelo backend
const displayRevenue = computed(() => props.managerData?.revenue || 0);
const displayOrderCount = computed(() => props.managerData?.order_count || 0);
const displayAvgTicket = computed(() => props.managerData?.avg_ticket || 0);

const byType = computed(() => {
    const defaultData = { amount: 0, count: 0 };
    const backendData = props.managerData?.by_type || {};

    return {
        delivery: backendData.delivery || { ...defaultData },
        table: backendData.table || { ...defaultData },
        counter: backendData.counter || { ...defaultData }
    };
});

const formattedShiftStart = computed(() => {
    const shiftDate = props.currentShift?.opened_at || props.currentShift?.created_at;
    if (!shiftDate) return '--:--';

    const match = String(shiftDate).match(/(?:T|\s|^)([01]\d|2[0-3]):([0-5]\d)/);
    return match ? `${match[1]}:${match[2]}` : '--:--';
});
</script>
```

## File: resources/js/Pages/Team/RestockTab.vue
```vue
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

// 🚀 FILTRO ESTRITO: Apenas produtos de revenda (resale) com status 'available'
const resaleLocations = computed(() => {
    return (locations.value || []).map(loc => ({
        ...loc,
        items: (loc.items || []).filter(item => {
            const isResale = (item.type || '').toString().toLowerCase().trim() === 'resale';
            const isAvailable = (item.status || '').toString().toLowerCase().trim() === 'available';
            return isResale && isAvailable;
        })
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
    // 🚀 force = true para descartar qualquer cache antigo da memória da SPA
    fetchRestockBoard(true);
});

const handleResetRound = () => {
    resetForNewRound();
};

const getItemMax = (item) => {
    if (isFreeMode.value) {
        return 999;
    }
    const diff = Number(item.max_capacity || 0) - Number(item.current_stock || 0);
    return diff > 0 ? diff : 999;
};

const getItemQty = (ruleId) => {
    return Number(restockDrafts.value?.[ruleId] ?? 0);
};

const setItemQty = (ruleId, val) => {
    restockDrafts.value = {
        ...restockDrafts.value,
        [ruleId]: Math.max(0, Number(val || 0))
    };
};

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
```

## File: resources/js/Pages/Team/TeamBarTab.vue
```vue
<template>
    <div
        class="flex flex-col h-full w-full"
        :style="{ paddingTop: 'var(--app-header-height, 7rem)' }"
    >
        <!-- 🚀 TELEPORT DO SEGMENTED CONTROL PARA O HEADER (AO LADO DO TÍTULO) -->
        <Teleport to="#team-header-action-portal" v-if="isMounted && isActive">
            <SegmentedControl
                v-model="activeView"
                :options="barViewOptions"
                class="!h-9 text-xs"
            />
        </Teleport>

        <!-- LISTA DE ITENS -->
        <div class="flex-1 overflow-y-auto px-4 pt-3 pb-32 hide-scrollbar flex flex-col gap-4 relative">

            <EmptyState
                v-if="filteredItems.length === 0"
                :title="activeView === 'pending' ? 'Copa Livre' : 'Sem Histórico'"
                :subtitle="activeView === 'pending' ? 'Nenhuma bebida aguardando preparo.' : 'Nenhum item finalizado no turno.'"
                :icon="activeView === 'pending' ? 'pi-thumbs-up' : 'pi-clock'"
                class="mt-10"
            />

            <TransitionGroup v-else name="list" tag="div" class="flex flex-col gap-3">
                <TeamBarCard
                    v-for="item in filteredItems"
                    :key="item.id"
                    :item="item"
                    :current-time="currentTime"
                    :current-user-id="$page.props.auth.user.id"
                    @take="$emit('take-item', $event)"
                    @complete="$emit('complete-item', $event)"
                    @print="$emit('print-item', $event)"
                    @cancel="openLossModal(item)"
                />
            </TransitionGroup>
        </div>

        <!-- MODAL DE PERDA / CANCELAMENTO -->
        <OperationReasonModal
            v-if="selectedItemForLoss"
            v-model:visible="isLossModalVisible"
            mode="modal"
            :item-name="selectedItemForLoss.product_name || selectedItemForLoss.name"
            action-type="cancel"
            :requires-pin="false"
            :item-to-process="selectedItemForLoss"
            @confirm="handleLossConfirm"
            @cancel="isLossModalVisible = false"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import TeamBarCard from '@/Pages/Team/TeamBarCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import OperationReasonModal from '@/Pages/Pos/Components/Checkout/OperationReasonModal.vue';

const props = defineProps({
    isActive: { type: Boolean, default: true },
    pendingItems: { type: Array, default: () => [] }
});

const emit = defineEmits(['take-item', 'complete-item', 'print-item', 'loss-item']);

const isMounted = ref(false);
const activeView = ref('pending');

const barViewOptions = [
    { label: 'Fila', value: 'pending', icon: 'pi-list' },
    { label: 'Histórico', value: 'history', icon: 'pi-history' }
];

const filteredItems = computed(() => {
    return props.pendingItems.filter(item => {
        const status = item.production_status || item.status || 'pending';
        const isDelivered = ['delivered', 'ready'].includes(status);

        if (activeView.value === 'history') return isDelivered;
        return !isDelivered;
    });
});

const currentTime = ref(new Date());
let timer;

const isLossModalVisible = ref(false);
const selectedItemForLoss = ref(null);

const openLossModal = (item) => {
    selectedItemForLoss.value = item;
    isLossModalVisible.value = true;
};

const handleLossConfirm = (lossData) => {
    isLossModalVisible.value = false;

    emit('loss-item', {
        item: selectedItemForLoss.value,
        isLoss: lossData.isLoss,
        reason: lossData.reason,
        password: lossData.password
    });

    setTimeout(() => { selectedItemForLoss.value = null; }, 300);
};

onMounted(() => {
    isMounted.value = true;
    timer = setInterval(() => { currentTime.value = new Date(); }, 15000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<style scoped>
.list-enter-active, .list-leave-active { transition: all 0.4s ease; }
.list-enter-from { opacity: 0; transform: translateY(20px) scale(0.95); }
.list-leave-to { opacity: 0; transform: scale(0.9); }
</style>
```

## File: resources/js/Pages/Team/TeamCartItemCard.vue
```vue
<template>
    <div class="p-4 bg-surface-3 rounded-[1.5rem] flex flex-col gap-3">
        <!-- CABEÇALHO DO ITEM: Nome, Preço e Badge P/ Viagem -->
        <div class="flex justify-between items-start gap-3">
            <div class="flex flex-col min-w-0 flex-1">
                <span class="font-black text-sm text-surface-text leading-tight">{{ item.name }}</span>
                <span v-if="item.isTakeaway" class="text-[10px] font-bold uppercase tracking-widest text-warning-500 mt-1 flex items-center gap-1">
                    <i class="pi pi-shopping-bag text-[10px]"></i> P/ Viagem
                </span>
            </div>
            <span class="font-black shrink-0">{{ $formatCurrency(itemTotal) }}</span>
        </div>

        <!-- MODIFICADORES E COMBOS -->
        <div
            v-if="(item.structuredModifiers && item.structuredModifiers.length > 0) || (item.comboItems && item.comboItems.length > 0)"
            class="flex flex-col gap-1 pl-2 border-l-2 border-surface-border/50"
        >
            <template v-if="item.comboItems">
                <div v-for="(cItem, cIdx) in item.comboItems" :key="'c' + cIdx" class="flex flex-col gap-0.5">
                    <span class="text-xs font-bold text-surface-text">+ {{ cItem.name || cItem.product_name }}</span>
                    <div v-if="cItem.structuredModifiers && cItem.structuredModifiers.length > 0" class="flex flex-col pl-2">
                        <span v-for="(m, mIdx) in cItem.structuredModifiers" :key="'cm' + mIdx" class="text-[10px] font-medium text-surface-muted">
                            - {{ m.qty > 1 ? m.qty + 'x ' : '' }}{{ m.name }}
                        </span>
                    </div>
                </div>
            </template>

            <template v-else-if="item.structuredModifiers">
                <span v-for="(mod, mIdx) in item.structuredModifiers" :key="'m' + mIdx" class="text-xs font-medium text-surface-muted">
                    + {{ mod.qty > 1 ? mod.qty + 'x ' : '' }}{{ mod.name }}
                </span>
            </template>
        </div>

        <!-- AÇÕES DO ITEM (RODAPÉ) -->
        <div class="flex items-center justify-between mt-2 pt-3 border-t border-surface-border/50">
            <QtyStepper
                :model-value="numericQty"
                :min="1"
                @increment="$emit('update-qty', index, 1)"
                @decrement="$emit('update-qty', index, -1)"
                size="sm"
            />

            <div class="flex items-center gap-2">
                <!-- 🛍️ BOTÃO SACOLINHA (PARA VIAGEM) -->
                <button
                    type="button"
                    @click.prevent="$emit('toggle-takeaway', index)"
                    class="w-10 h-10 rounded-xl flex items-center justify-center transition-colors outline-none cursor-pointer border-none shadow-sm active:scale-95"
                    :class="item.isTakeaway
                        ? 'bg-warning-500 text-white'
                        : 'bg-surface-3 text-surface-muted hover:text-surface-text hover:bg-surface-border'"
                    :title="item.isTakeaway ? 'Item marcado para viagem' : 'Marcar para viagem'"
                >
                    <i class="pi pi-shopping-bag text-sm"></i>
                </button>

                <!-- ✏️ BOTÃO EDITAR -->
                <button
                    type="button"
                    @click.prevent="$emit('edit-item', item, index)"
                    class="w-10 h-10 rounded-xl bg-surface-3 text-surface-text hover:bg-surface-border transition-colors flex items-center justify-center outline-none cursor-pointer border-none shadow-sm active:scale-95"
                    title="Editar produto"
                >
                    <i class="pi pi-pencil text-sm"></i>
                </button>

                <!-- 🗑️ BOTÃO REMOVER -->
                <button
                    type="button"
                    @click.prevent="$emit('update-qty', index, -numericQty)"
                    class="w-10 h-10 rounded-xl bg-danger-500/10 text-danger-500 hover:bg-danger-500 hover:text-white transition-colors flex items-center justify-center outline-none cursor-pointer border-none shadow-sm active:scale-95"
                    title="Remover do carrinho"
                >
                    <i class="pi pi-trash text-sm"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency } from '@/Utils/formatters';
import QtyStepper from "@/Components/UI/QtyStepper.vue";

const props = defineProps({
    item: { type: Object, required: true },
    index: { type: Number, required: true }
});

defineEmits(['update-qty', 'edit-item', 'toggle-takeaway']);

// 🚀 Fallback duplo com blindagem numérica
const numericQty = computed(() => {
    const raw = props.item?.qty ?? props.item?.quantity ?? 1;
    const parsed = Number(raw);
    return isNaN(parsed) || parsed <= 0 ? 1 : parsed;
});

const itemTotal = computed(() => {
    const unitPrice = Number(props.item?.price) || 0;
    return unitPrice * numericQty.value;
});
</script>
```

## File: resources/js/Pages/Team/TeamHomeTab.vue
```vue
<template>
    <div class="h-full w-full relative overflow-hidden flex flex-col">

        <!-- 🖼️ CAMADA FIXA DO FUNDO (NÃO ROLA E NÃO DISTORCE) -->
        <div
            class="absolute inset-0 z-0 bg-cover bg-center bg-no-repeat transition-all duration-1000 pointer-events-none"
            :style="backgroundStyle"
        >
            <div class="absolute inset-0 bg-black/35 pointer-events-none"></div>
        </div>

        <!-- 📜 CAMADA DE CONTEÚDO ROLÁVEL -->
        <div class="relative z-10 flex-1 h-full w-full overflow-y-auto pt-24 pb-32 hide-scrollbar">
            <div class="max-w-2xl w-full mx-auto flex flex-col gap-6 p-4 md:p-6">

                <!-- BANNER DE NOTIFICAÇÃO -->
                <PushNotificationBanner />

                <!-- 🥪 CARTÃO DE AÇÃO RÁPIDA: LANCHE DA EQUIPA -->
                <div
                    v-if="hasMealAllowance"
                    class="bg-surface-1/70 dark:bg-surface-1/50 backdrop-blur-xl border border-surface-border/60 rounded-[2.5rem] p-5 shadow-xl flex items-center justify-between gap-4 transition-all"
                >
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center text-xl font-black shrink-0">
                            <i class="pi pi-sparkles"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-base font-black text-surface-text leading-tight truncate">
                                Lanche da Equipa
                            </h4>
                            <p class="text-xs text-surface-muted mt-0.5 truncate">
                                {{ mealSubtitle }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="!canAccessMeal"
                        @click="handleOpenMealTab"
                        class="h-10 px-4 rounded-xl font-black text-xs uppercase tracking-wider flex items-center gap-2 transition-all outline-none border-none cursor-pointer select-none shrink-0"
                        :class="canAccessMeal
                            ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-md active:scale-95'
                            : 'bg-surface-3 text-surface-muted opacity-50 cursor-not-allowed'"
                    >
                        <i class="pi" :class="canAccessMeal ? 'pi-arrow-right' : 'pi-lock'"></i>
                        <span>{{ canAccessMeal ? 'Lanche' : 'Bloqueado' }}</span>
                    </button>
                </div>

                <!-- CHECKLISTS EM GLASSMORPHISM -->
                <div class="bg-surface-1/60 dark:bg-surface-1/40 backdrop-blur-xl border border-surface-border/50 rounded-[2.5rem] shadow-xl overflow-hidden">
                    <TeamChecklists />
                </div>

            </div>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import TeamChecklists from '@/Pages/Team/TeamChecklists.vue';
import PushNotificationBanner from '@/Components/UI/PushNotificationBanner.vue';
import { useDynamicBackground } from '@/Composables/useDynamicBackground';

const page = usePage();
const { backgroundStyle } = useDynamicBackground();

const hasMealAllowance = computed(() => {
    return Boolean(page.props.companySettings?.has_food_allowance);
});

const isManagerRole = (role) => {
    const r = String(role || '').toLowerCase();
    return ['manager', 'gerente', 'admin', 'supervisor'].includes(r);
};

const isCashierRole = (role) => {
    const r = String(role || '').toLowerCase();
    return ['cashier', 'caixa'].includes(r);
};

// 🛡️ REGRAS HIERÁRQUICAS DE ACESSO
const canAccessMeal = computed(() => {
    const settings = page.props.companySettings;
    if (!settings?.has_food_allowance) return false;

    // Se autoatendimento estiver ativo: todos podem
    if (settings.self_service_enabled) return true;

    const attendees = page.props.currentAttendees || [];
    const hasManagerInShift = attendees.some(a => isManagerRole(a.role));

    const user = page.props.auth?.user;
    const myRole = user?.role || '';

    // Se houver gerente no turno: apenas o gerente pode
    if (hasManagerInShift) {
        return isManagerRole(myRole);
    }

    // Se NÃO houver gerente no turno: o caixa assume
    return isManagerRole(myRole) || isCashierRole(myRole);
});

const mealSubtitle = computed(() => {
    if (!canAccessMeal.value) {
        return 'Restrito ao Gerente / Caixa do turno.';
    }
    const attendeesCount = page.props.currentAttendees?.length || 0;
    return `${attendeesCount} colaborador(es) no turno.`;
});

const handleOpenMealTab = () => {
    if (!canAccessMeal.value) return;
    window.dispatchEvent(new CustomEvent('team-tab-changed', { detail: 'meal' }));
};
</script>
```

## File: resources/js/Pages/Team/TeamMenuTab.vue
```vue
<template>
    <div
        class="flex-1 flex flex-col min-h-0 p-4 pb-0 gap-4 animate-in fade-in duration-300"
        :style="{ paddingTop: 'var(--app-header-height, 7rem)' }"
    >
        <CategoryMenu
            v-if="categoryLayout === 'horizontal' || categoryLayout === 'vertical'"
            :categories="visibleMenuCategories"
            :selected="activeMenuCategory"
            :layout="categoryLayout"
            @update:selected="$emit('category-select', $event)"
            class="shrink-0"
        />

        <div ref="scrollContainerRef" class="flex-1 overflow-y-auto hide-scrollbar -mx-2 px-2">
            <!-- Subcategorias do nível atual -->
            <CategoryGrid
                v-if="shouldShowCategoryGrid"
                :categories="visibleGridCategories"
                :layout="mobileCategoryLayout"
                @category-click="$emit('category-select', $event)"
                :class="shouldShowProductGrid ? 'mb-6' : ''"
            />

            <!-- O divisor "Produtos Soltos" SÓ vai aparecer se houver subcategorias E produtos diretos na mesma pasta -->
            <div v-if="shouldShowCategoryGrid && shouldShowProductGrid" class="flex items-center gap-4 mb-4 opacity-50">
                <div class="h-px bg-surface-border flex-1"></div>
                <span class="micro-label text-[10px] font-bold uppercase tracking-widest">Produtos Soltos</span>
                <div class="h-px bg-surface-border flex-1"></div>
            </div>

            <!-- Grid de produtos da categoria -->
            <ProductGrid
                v-if="shouldShowProductGrid"
                :products="filteredProducts"
                :layout="productCardLayout"
                @product-click="$emit('product-click', $event)"
            />

            <div v-if="!shouldShowProductGrid && !shouldShowCategoryGrid" class="flex flex-col items-center justify-center py-20 text-surface-muted opacity-60">
                <i class="pi pi-search text-5xl mb-3 text-primary-500/40"></i>
                <h3 class="text-lg font-bold text-center">Nenhum produto encontrado</h3>
                <p class="text-xs mt-1 text-center max-w-xs">Não localizamos itens para a sua busca ou não há produtos disponíveis.</p>
            </div>

            <!-- 🚀 Spacer de rolagem que obedece a ilha dinâmica do App da Equipe -->
            <div class="shrink-0 w-full pointer-events-none" :style="{ height: 'var(--app-footer-height, 120px)' }"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import CategoryMenu from "@/Pages/Pos/Components/Menu/CategoryMenu.vue";
import ProductGrid from "@/Pages/Pos/Components/Menu/ProductGrid.vue";
import CategoryGrid from "@/Pages/Pos/Components/Menu/CategoryGrid.vue";

const props = defineProps({
    isActive: { type: Boolean, default: true },
    categoryLayout: String,
    mobileCategoryLayout: String,
    productCardLayout: String,
    visibleMenuCategories: Array,
    activeMenuCategory: [String, Number],
    visibleGridCategories: Array,
    shouldShowCategoryGrid: Boolean,
    shouldShowProductGrid: Boolean,
    filteredProducts: Array
});

defineEmits(['product-click', 'category-select']);

const scrollContainerRef = ref(null);

watch(() => props.activeMenuCategory, () => {
    if (scrollContainerRef.value) {
        scrollContainerRef.value.scrollTop = 0;
    }
});
</script>
```

## File: resources/js/Pages/Team/TeamOrderDetails.vue
```vue
<template>
    <div class="flex flex-col h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans relative">

        <!-- 🚀 1. CABEÇALHO MOBILE / GARÇOM -->
        <header class="bg-surface-2 border-b border-surface-border px-4 py-3 shrink-0 flex flex-col gap-2">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <button @click="emit('close')" class="w-9 h-9 rounded-full bg-surface-3 hover:bg-surface-border text-surface-text flex items-center justify-center transition-colors shrink-0">
                        <i class="pi pi-arrow-left text-sm font-bold"></i>
                    </button>
                    <div class="flex flex-col min-w-0">
                        <h1 class="text-lg font-black text-surface-text truncate leading-tight">
                            {{ formattedOrderTitle }}
                        </h1>
                        <span class="text-[11px] font-bold text-surface-muted">
                            Pedido #{{ $formatNumber(order?.id) }} • {{ translatedType }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    <span v-if="isPaid" class="bg-success-500/20 text-success-600 dark:text-success-400 px-2.5 py-1 rounded-lg font-black text-[10px] uppercase tracking-wider flex items-center gap-1">
                        <i class="pi pi-check-circle text-[9px]"></i> Pago
                    </span>
                    <span v-else class="bg-warning-500/10 text-warning-600 dark:text-warning-400 px-2.5 py-1 rounded-lg font-black text-[10px] uppercase tracking-wider">
                        Aberto
                    </span>
                </div>
            </div>

            <!-- CHIPS DE ATALHO RÁPIDO DO GARÇOM -->
            <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar pt-1">
                <button
                    v-if="companySettings?.sicredi_is_active && companySettings?.sicredi_enable_waiter_pix && !isPaid"
                    @click="isPixModalOpen = true"
                    class="h-8 px-3 rounded-full bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-black flex items-center gap-1.5 shrink-0 transition-colors"
                >
                    <i class="pi pi-qrcode text-[10px]"></i>
                    <span>Cobrar via PIX</span>
                </button>
                <button
                    v-if="order?.type === 'table'"
                    @click="handleOpenTransferAll"
                    class="h-8 px-3 rounded-full bg-surface-3 hover:bg-surface-border text-surface-text text-xs font-bold flex items-center gap-1.5 shrink-0 transition-colors"
                >
                    <i class="pi pi-sync text-[10px] text-primary-500"></i>
                    <span>Transferir Mesa</span>
                </button>

                <button
                    v-if="order?.type === 'table'"
                    @click="handlePrintConference"
                    :disabled="isPrintingConference"
                    class="h-8 px-3 rounded-full bg-surface-3 hover:bg-surface-border text-surface-text text-xs font-bold flex items-center gap-1.5 shrink-0 transition-colors"
                >
                    <i class="pi" :class="isPrintingConference ? 'pi-spinner animate-spin text-primary-500' : 'pi-print text-surface-muted'"></i>
                    <span>Imprimir Parcial</span>
                </button>

                <button
                    v-if="order?.type === 'table'"
                    @click="isTransformerVisible = true"
                    class="h-8 px-3 rounded-full bg-surface-3 hover:bg-surface-border text-surface-text text-xs font-bold flex items-center gap-1.5 shrink-0 transition-colors"
                >
                    <i class="pi pi-arrow-right-arrow-left text-[10px] text-purple-500"></i>
                    <span>Mudar Tipo</span>
                </button>
            </div>
        </header>

        <!-- 🚀 2. LISTA DE ITENS DA MESA -->
        <main class="flex-1 min-h-0 overflow-y-auto bg-surface-1">
            <CheckoutItemsList
                mode="details"
                :display-items="activeItemsGrouped"
                :selected-ids="[]"
                :is-all-selected="false"
                :order-payments="order?.payments || []"
                @action="handleItemAction"
            />
        </main>

        <!-- 🚀 3. BARRA DE AÇÃO FIXA INFERIOR (POLEGAR DO GARÇOM) -->
        <footer class="bg-surface-2 border-t border-surface-border p-3 shrink-0 flex items-center justify-between gap-3 shadow-lg">
            <div class="flex flex-col pl-1">
                <span class="text-[10px] font-black text-surface-muted uppercase tracking-widest">Total</span>
                <span class="text-xl font-black text-primary-smart leading-none">
                    {{ $formatCurrency(order?.total || 0) }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="openOrderOptions = true"
                    class="h-12 w-12 rounded-xl bg-surface-3 text-surface-muted hover:text-surface-text flex items-center justify-center transition-colors border border-surface-border"
                    title="Mais Opções"
                >
                    <i class="pi pi-ellipsis-v text-sm"></i>
                </button>

                <button
                    @click="handleAddItems"
                    :disabled="order?.status === 'closed' || order?.status === 'cancelled'"
                    class="h-12 px-5 rounded-xl bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-black font-black text-xs uppercase tracking-wider flex items-center gap-2 shadow-md transition-all disabled:opacity-50"
                >
                    <i class="pi pi-plus text-xs font-black"></i>
                    <span>Lançar Itens</span>
                </button>
            </div>
        </footer>

        <!-- 🚀 4. BOTTOM-SHEET DE OPÇÕES ADICIONAIS DO GARÇOM -->
        <AdaptiveModal v-model:visible="openOrderOptions">
            <div class="p-6 flex flex-col gap-3">
                <h3 class="text-base font-black text-surface-text mb-1">Ações na Mesa</h3>

                <button
                    @click="handlePrintConference(); openOrderOptions = false;"
                    class="w-full h-12 px-4 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-text font-bold text-xs uppercase tracking-wider flex items-center gap-3 transition-colors border border-surface-border"
                >
                    <i class="pi pi-print text-primary-500"></i>
                    <span>Imprimir Pré-Conta (Conferência)</span>
                </button>

                <button
                    v-if="order?.type === 'table'"
                    @click="handleOpenTransferAll(); openOrderOptions = false;"
                    class="w-full h-12 px-4 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-text font-bold text-xs uppercase tracking-wider flex items-center gap-3 transition-colors border border-surface-border"
                >
                    <i class="pi pi-sync text-purple-500"></i>
                    <span>Transferir Todos os Itens de Mesa</span>
                </button>

                <button
                    @click="triggerSecureAction(order, 'cancel_order'); openOrderOptions = false;"
                    class="w-full h-12 px-4 rounded-xl bg-danger-500/10 text-danger-500 hover:bg-danger-500/20 font-bold text-xs uppercase tracking-wider flex items-center gap-3 transition-colors mt-2"
                >
                    <i class="pi pi-times-circle"></i>
                    <span>Cancelar Pedido Inteiro</span>
                </button>
            </div>
        </AdaptiveModal>

        <!-- 🚀 5. MODAIS OPERACIONAIS COMPARTILHADOS -->
        <TransferItemsModal
            v-model:visible="isTransferModalVisible"
            :source-order="order"
            :item-ids="transferItemIds"
            :table-areas="tableAreas"
            @confirm="handleExecuteTransfer"
        />

        <OrderTransformer
            v-model:visible="isTransformerVisible"
            :order="order"
            :table-areas="tableAreas"
            @transform="handleTransformation"
        />

        <OperationReasonModal
            v-model:visible="isReasonModalVisible"
            mode="modal"
            :item-name="authActionType === 'cancel_order' ? `Pedido #${$formatNumber(itemToProcess?.id)}` : itemToProcess?.name"
            :action-type="authActionType"
            :requires-pin="authNeedsPin"
            :item-to-process="itemToProcess"
            @cancel="isReasonModalVisible = false"
            @confirm="handleConfirmAuth"
        />
            <OrderPixModal
            v-if="order"
            v-model:visible="isPixModalOpen"
            :order="order"
        />
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { formatCurrency, formatNumber } from "@/Utils/formatters";

import CheckoutItemsList from "@/Pages/Pos/Components/Checkout/CheckoutItemsList.vue";
import TransferItemsModal from "@/Pages/Shared/Order/TransferItemsModal.vue";
import OrderTransformer from "@/Pages/Shared/Order/OrderTransformer.vue";
import OperationReasonModal from "@/Pages/Pos/Components/Checkout/OperationReasonModal.vue";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";
import OrderPixModal from "@/Pages/Shared/OrderPixModal.vue";

const isPixModalOpen = ref(false);

import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { useItemActions } from "@/Pages/Shared/Composables/useItemActions";
import { useSecureAction } from "@/Composables/useSecureAction";

const props = defineProps({
    order: { type: Object, required: true },
    tableAreas: { type: Array, default: () => [] },
    companySettings: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["close", "add-items"]);

const toast = useToast();
const { getOrderTitle, groupActiveItems } = useOrderFormatting();

const formattedOrderTitle = computed(() => getOrderTitle(props.order));
const translatedType = computed(() => {
    const t = { table: "Mesa", counter: "Balcão", delivery: "Delivery" };
    return t[props.order?.type] || "Mesa";
});
const isPaid = computed(() => props.order?.payment_status === "paid");
const activeItemsGrouped = computed(() => groupActiveItems(props.order?.items));

// Estados de modais
const openOrderOptions = ref(false);
const isTransferModalVisible = ref(false);
const isTransformerVisible = ref(false);
const transferItemIds = ref([]);
const isPrintingConference = ref(false);

// Ações nos itens (cancelar, duplicar, trocar)
const dummyRef = ref(true);
const { handleItemDuplicate, handleItemModify, handleItemSwap, handleItemCancel } = useItemActions(
    computed(() => props.order),
    dummyRef,
    'team.app'
);

const { isReasonModalVisible, itemToProcess, authActionType, authNeedsPin, initiateSecureAction } = useSecureAction(
    props.companySettings
);

const handleItemAction = (payload) => {
    const { type, item } = payload;
    if (type === "duplicate") handleItemDuplicate(item);
    else if (type === "transfer") {
        transferItemIds.value = [item.id];
        isTransferModalVisible.value = true;
    } else if (type === "edit") {
        triggerSecureAction(item, "edit");
    } else if (type === "cancel") {
        triggerSecureAction(item, "cancel");
    }
};

const triggerSecureAction = (item, type) => {
    initiateSecureAction(item, type);
};

const handleConfirmAuth = (authData) => {
    if (authActionType.value === "edit") {
        handleItemModify({ item: itemToProcess.value, authData });
    } else if (authActionType.value === "swap") {
        handleItemSwap({ item: itemToProcess.value, authData });
    } else if (authActionType.value === "cancel_order") {
        router.post(route('operations.orders.cancel', itemToProcess.value.id),
            {
                reason: authData.reason,
            },
            {
                preserveState: true,
                preserveScroll: true,
                is_loss: authData.isLoss,
                lost_items: authData.lostItems,
                pin: authData.password,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isReasonModalVisible.value = false;
                    toast.add({ severity: "success", summary: "Cancelado", detail: "Pedido cancelado.", life: 3000 });
                    emit("close");
                },
                onError: (err) => {
                    toast.add({ severity: "error", summary: "Erro", detail: err.pin || "Erro ao cancelar.", life: 4000 });
                },
            }
        );
        return;
    } else {
        handleItemCancel({
            item: itemToProcess.value,
            isLoss: authData.isLoss,
            reason: authData.reason,
            password: authData.password,
        });
    }

    isReasonModalVisible.value = false;
};

// Navegação para o cardápio
const handleAddItems = () => {
    localStorage.setItem("pos_draft_type", props.order?.type || "table");
    localStorage.setItem("pos_return_url", "/team");
    localStorage.setItem("pos_return_order_id", props.order?.id);
    emit("add-items", props.order);
};

// Transferências
const handleOpenTransferAll = () => {
    transferItemIds.value = activeItemsGrouped.value.map((i) => i.id);
    isTransferModalVisible.value = true;
};

const handleExecuteTransfer = (payload) => {
    router.post(route("operations.transfer-items"), payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isTransferModalVisible.value = false;
            void(0)
        },
    });
};

const handleTransformation = (payload) => {
    router.post(route("operations.orders.transform", props.order?.id), payload, {
        onSuccess: () => {
            isTransformerVisible.value = false;
            void(0)
        },
    });
};

// Impressão da parcial
const handlePrintConference = () => {
    if (isPrintingConference.value) return;
    isPrintingConference.value = true;

    router.post(
        route("operations.print-conference", props.order?.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isPrintingConference.value = false;
            },
            onSuccess: () => {
                void(0)
            },
        }
    );
};
</script>
```

## File: resources/js/Pages/Team/DeliveryDriverCard.vue
```vue
<template>
    <div
        class="bg-surface-1 rounded-2xl p-4 shadow-sm border transition-all flex flex-col gap-3"
        :class="hasDrink ? 'border-amber-500/50 bg-amber-500/[0.02]' : 'border-surface-border'"
    >
        <!-- TOPO: Identificação (#1), Bairro (#4), Bebida (#6) e Chevron (#7) -->
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-col min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- 1) Número do Pedido -->
                    <span class="text-xs font-black text-primary-500 uppercase tracking-wider">
                        #{{ order.id }}
                    </span>

                    <!-- 4) Bairro em Chip em Destaque -->
                    <span
                        v-if="neighborhood"
                        class="px-2.5 py-0.5 rounded-lg bg-primary-500/10 text-primary-600 dark:text-primary-400 font-black text-[10px] uppercase tracking-wider truncate"
                    >
                        {{ neighborhood }}
                    </span>

                    <!-- 6) Chip "TEM BEBIDA" (Cozinha type = bar) -->
                    <span
                        v-if="hasDrink"
                        class="px-2 py-0.5 rounded-full bg-amber-500 text-white font-black text-[9px] uppercase tracking-wider flex items-center gap-1 shadow-xs shrink-0"
                    >
                        <i class="pi pi-glass text-[9px]"></i>
                        TEM BEBIDA
                    </span>
                </div>

                <!-- 2) Nome do Cliente Abreviado -->
                <h4 class="font-bold text-sm text-surface-text leading-tight truncate mt-1.5">
                    {{ abbreviateName(customerName) }}
                </h4>
            </div>

            <!-- 7) Ícone de Chevron para Expandir os Itens -->
            <button
                @click="isExpanded = !isExpanded"
                type="button"
                class="w-8 h-8 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text transition-colors flex items-center justify-center shrink-0 border-none cursor-pointer outline-none"
                :title="isExpanded ? 'Recolher itens' : 'Ver itens do pedido'"
            >
                <i
                    class="pi text-xs transition-transform duration-200"
                    :class="isExpanded ? 'pi-chevron-up text-primary-500' : 'pi-chevron-down'"
                ></i>
            </button>
        </div>

        <!-- 3) Morada Clicável com Navegação GPS Direta -->
        <a
            :href="navigationUrl"
            target="_blank"
            class="text-xs text-surface-muted font-medium leading-relaxed bg-surface-2/60 hover:bg-surface-2 p-2.5 rounded-xl border border-surface-border/40 hover:border-primary-500/50 flex items-start justify-between gap-2 transition-all no-underline group cursor-pointer"
            :title="hasExactLocation ? 'Abrir Localização GPS Exata' : 'Pesquisar Morada no Google Maps'"
        >
            <div class="flex items-start gap-2 min-w-0">
                <i class="pi fa-location-dot text-xs mt-0.5 shrink-0" :class="hasExactLocation ? 'text-emerald-500' : 'text-primary-500'"></i>
                <div class="flex flex-col min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-surface-text font-semibold group-hover:text-primary-500 transition-colors">
                            {{ formattedAddress.main }}
                        </span>
                        <span v-if="hasExactLocation" class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            GPS EXATO
                        </span>
                    </div>
                    <span v-if="formattedAddress.extra" class="text-[11px] opacity-80 mt-0.5">
                        {{ formattedAddress.extra }}
                    </span>
                </div>
            </div>
            <div class="shrink-0 flex items-center justify-center w-7 h-7 rounded-lg bg-surface-3/50 group-hover:bg-primary-500 group-hover:text-white transition-all text-surface-muted">
                <i class="pi pi-external-link text-[10px]"></i>
            </div>
        </a>

        <!-- 5) Status Financeiro (PAGO vs Cobrar + Forma + Troco + Cobrança PIX) -->
        <div class="flex items-center justify-between text-xs px-1 font-bold">
            <span class="text-surface-muted text-[11px] uppercase tracking-wider">Pagamento:</span>

            <!-- Cenário 1: Já está Pago -->
            <span
                v-if="order.payment_status === 'paid'"
                class="px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-black text-[10px] uppercase tracking-widest"
            >
                PAGO
            </span>

            <!-- Cenário 2: A Cobrar na Entrega -->
            <div v-else class="flex flex-col items-end gap-1.5">
                <span class="text-danger-500 font-black">
                    Cobrar R$ {{ formatCurrency(totalAmount) }}
                </span>
                <span class="text-[10px] text-surface-muted font-semibold">
                    {{ paymentMethodDescription }}
                </span>

                <!-- ⚡ BOTÃO RECEBER COM PIX (Regra Sicredi Dinâmica) -->
                <button
                    v-if="canChargePix"
                    @click.stop="$emit('charge-pix', order)"
                    type="button"
                    class="mt-0.5 px-2.5 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500 text-emerald-600 dark:text-emerald-400 hover:text-white font-black text-[10px] uppercase tracking-wider transition-colors flex items-center gap-1.5 border border-emerald-500/20 cursor-pointer shadow-xs active:scale-95"
                >
                    <i class="pi pi-qrcode text-[10px]"></i>
                    Receber com PIX
                </button>
            </div>
        </div>

        <!-- 7) Itens Expandidos (sem timer de cozinha) -->
        <div
            v-if="isExpanded"
            class="pt-3 border-t border-surface-border/50 flex flex-col gap-2 animate-in fade-in duration-200"
        >
            <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">
                Itens do Pedido ({{ (order.items || []).length }})
            </span>

            <div class="flex flex-col gap-1.5 max-h-48 overflow-y-auto hide-scrollbar pr-1">
                <div
                    v-for="(item, idx) in order.items || []"
                    :key="item.id || idx"
                    class="p-2 rounded-lg bg-surface-2/40 border border-surface-border/30 flex items-start justify-between gap-2 text-xs"
                >
                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-surface-text leading-tight">
                            <span class="text-primary-500 font-black mr-1">{{ item.quantity || 1 }}x</span>
                            {{ item.product_name || item.name }}
                        </span>

                        <span
                            v-if="item.notes"
                            class="text-[10px] text-surface-muted mt-0.5 leading-tight italic"
                        >
                            Obs: {{ item.notes }}
                        </span>
                    </div>

                    <span v-if="isItemFromBar(item)" class="text-[9px] font-black uppercase text-amber-500 bg-amber-500/10 px-1.5 py-0.5 rounded shrink-0">
                        Bebida
                    </span>
                </div>
            </div>
        </div>

        <!-- Slot de Ações (Botões Pegar / Sair p/ Entrega / Entregue) -->
        <div v-if="$slots.actions" class="pt-1 border-t border-surface-border/40">
            <slot name="actions"></slot>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { abbreviateName } from '@/Utils/formatters';

const props = defineProps({
    order: {
        type: Object,
        required: true
    }
});

defineEmits(['charge-pix']);

const page = usePage();
const isExpanded = ref(false);
const hasExactLocation = computed(() => {
    const addr = props.order.address_snapshot || props.order.address;
    if (!addr || typeof addr !== 'object') return false;
    return Boolean(addr.location || addr.location_link || addr.coordinates);
});

const navigationUrl = computed(() => {
    const addr = props.order.address_snapshot || props.order.address;
    if (addr && typeof addr === 'object') {
        const rawLoc = addr.location_link || addr.location;
        if (rawLoc) {
            const decoded = decodeURIComponent(String(rawLoc));
            const match = decoded.match(/(-?\d{1,2}\.\d+)\s*[,/]\s*(-?\d{1,3}\.\d+)/);
            if (match) {
                return `https://www.google.com/maps/dir/?api=1&destination=${match[1]},${match[2]}`;
            }
            if (String(rawLoc).startsWith('http')) return rawLoc;
        }
        if (addr.coordinates && typeof addr.coordinates === 'object') {
            const lat = addr.coordinates.lat || addr.coordinates.latitude;
            const lng = addr.coordinates.lng || addr.coordinates.longitude;
            if (lat && lng) {
                return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
            }
        }
    }
    const query = `${formattedAddress.value.main}${neighborhood.value ? ', ' + neighborhood.value : ''}`;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
});

const customerName = computed(() => {
    return props.order.customer_name || props.order.customer?.name || 'Cliente';
});

// 4) Bairro
const neighborhood = computed(() => {
    return props.order.neighborhood ||
        props.order.address?.neighborhood ||
        props.order.address_snapshot?.neighborhood ||
        '';
});

// 6) Verificação se o item pertence à cozinha do tipo 'bar'
const isItemFromBar = (item) => {
    const kitchenType = item.kitchen?.type ||
        item.kitchen_type ||
        item.product?.kitchen?.type;

    return String(kitchenType || '').toLowerCase() === 'bar';
};

const hasDrink = computed(() => {
    const items = props.order.items;
    if (!Array.isArray(items) || items.length === 0) return false;
    return items.some(item => isItemFromBar(item));
});

// 3) Endereço Completo: Rua + Número + Complemento + Referência
const formattedAddress = computed(() => {
    const addr = props.order.address_snapshot || props.order.address;

    if (typeof addr === 'object' && addr !== null) {
        const street = addr.street || addr.street_name || '';
        const number = addr.number || 'S/N';
        const complement = addr.complement ? `, ${addr.complement}` : '';
        const reference = addr.reference ? ` (Ref: ${addr.reference})` : '';

        const main = `${street}, ${number}${complement}`.trim();
        const extra = reference.trim();

        return {
            main: main || 'Endereço cadastrado',
            extra: extra
        };
    }

    if (typeof props.order.address === 'string' && props.order.address.trim()) {
        return {
            main: props.order.address,
            extra: ''
        };
    }

    return {
        main: 'Endereço não informado',
        extra: ''
    };
});

// 5) Valor total devedor real gravado na tabela
const totalAmount = computed(() => {
    const o = props.order;
    if (!o) return 0;

    const alreadyPaid = Array.isArray(o.payments)
        ? o.payments.reduce((acc, p) => acc + Number(p.amount || 0), 0)
        : 0;

    const grandTotal = Number(o.total ?? o.balance_due ?? o.grand_total ?? o.total_amount ?? 0);
    return Math.max(0, grandTotal - alreadyPaid);
});

// 5) Nome da forma de pagamento e valor do troco
const paymentMethodDescription = computed(() => {
    const o = props.order;
    if (!o) return 'Dinheiro';

    const methods = o.payment_intent_methods;
    let label = '';

    if (Array.isArray(methods) && methods.length > 0) {
        label = methods
            .map(m => m.method_name || m.name || (typeof m === 'string' ? m : ''))
            .filter(Boolean)
            .join(', ');
    } else if (typeof methods === 'string') {
        label = methods;
    } else if (o.payment_method_name) {
        label = o.payment_method_name;
    }

    if (!label) {
        label = 'Dinheiro';
    }

    // Leitura do troco
    const rawTroco = o.change_target ?? (Array.isArray(methods) ? methods[0]?.change_target : null);
    const troco = Number(rawTroco || 0);

    if (troco > 0) {
        label += ` (Troco p/ R$ ${troco.toFixed(2).replace('.', ',')})`;
    }

    return label;
});

// ⚡ Regra de Negócio: Libera cobrança PIX pelo Motoboy
const canChargePix = computed(() => {
    const settings = page.props.companySettings || {};
    return (
        Boolean(settings.sicredi_is_active) &&
        settings.sicredi_enable_driver_pix !== false &&
        props.order.payment_status !== 'paid' &&
        totalAmount.value > 0.01
    );
});

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
```

## File: resources/js/Pages/Team/InventoryTab.vue
```vue
<template>
    <div
        class="flex flex-col h-full overflow-y-auto hide-scrollbar relative pb-28 select-none"
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

                    <!-- Lista com Separadores de Categoria (Prateleiras) -->
                    <div class="flex flex-col divide-y divide-surface-border/40">
                        <template
                            v-for="(item, idx) in block.items"
                            :key="`${block.location_id}_${item.id}`"
                        >
                            <div
                                v-if="idx === 0 || (item.category?.name || 'Geral') !== (block.items[idx - 1]?.category?.name || 'Geral')"
                                class="px-3.5 py-1.5 bg-surface-3/40 border-y border-surface-border/40 flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-primary-smart select-none"
                            >
                                <span class="flex items-center gap-1.5">
                                    <i class="pi pi-tag text-[9px]"></i>
                                    {{ item.category?.name || 'Geral' }}
                                </span>
                                <span class="text-[9px] font-bold text-surface-muted lowercase">
                                    {{ block.items.filter(i => (i.category?.name || 'Geral') === (item.category?.name || 'Geral')).length }} itens
                                </span>
                            </div>

                            <InventoryItemRow
                                :item="item"
                                :location-id="block.location_id"
                                :active-mode="activeMode"
                                :is-expanded="isExpanded(block.location_id, item.id)"
                                :is-touched="isTouched(block.location_id, item.id)"
                                :display-value="getDisplayValue(block.location_id, item.id)"
                                :delta="getItemDelta(block.location_id, item)"
                                :delta-label="formatDeltaLabel(block.location_id, item)"
                                :is-saving="isSaving(block.location_id, item.id)"
                                @toggle-expand="toggleItemExpansion(block.location_id, item)"
                                @save="qty => saveSingleCount(block.location_id, item, qty)"
                                @update-status="target => updateStatus(item, target)"
                            />
                        </template>
                    </div>
                </div>
            </template>

            <div v-else class="flex flex-col items-center justify-center py-20 text-surface-muted opacity-60">
                <i class="pi pi-box text-5xl mb-3 text-primary-500/40"></i>
                <h3 class="text-lg font-bold text-center">Nenhum item encontrado</h3>
                <p class="text-xs mt-1 text-center max-w-xs">Ajuste os filtros ou escolha outro local.</p>
            </div>
        </div>

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
    isSaving,
    saveSingleCount,
    updateStatus
} = useInventoryCount(catalogRef);

onMounted(() => {
    isMounted.value = true;
});
</script>
```

## File: resources/js/Pages/Team/TeamTabContainer.vue
```vue
<template>
    <main class="flex-1 flex flex-col min-h-0 bg-surface-2 relative overflow-hidden pt-safe">

        <!-- 🏠 ABA: HOME DA EQUIPA -->
        <div v-if="activeTab === 'home'" class="flex-1 h-full min-h-0 flex flex-col overflow-hidden animate-in fade-in">
            <TeamHomeTab />
        </div>

        <!-- 🥪 NOVO: ABA LANCHE DA EQUIPA (VALE-ALIMENTAÇÃO) -->
        <div v-if="activeTab === 'meal'" class="flex-1 h-full min-h-0 flex flex-col overflow-hidden animate-in fade-in">
            <MealAllowanceTab
                ref="mealTabRef"
                :attendees="$page.props.currentAttendees || []"
                :catalog="catalog || initialProducts"
                :company-settings="$page.props.companySettings"
                @back="$emit('update:activeTab', 'home')"
                @open-modifiers="$emit('open-meal-modifiers', $event)"
            />
        </div>

        <!-- 📖 ABA: CARDÁPIO -->
        <TeamMenuTab
            v-show="activeTab === 'menu'"
            :is-active="activeTab === 'menu'"
            :search-query="searchQuery"
            @update:search-query="$emit('update:searchQuery', $event)"
            :category-layout="categoryLayout"
            :mobile-category-layout="mobileCategoryLayout"
            :product-card-layout="productCardLayout"
            :visible-menu-categories="visibleMenuCategories"
            :active-menu-category="activeMenuCategory"
            :visible-grid-categories="visibleGridCategories"
            :should-show-category-grid="shouldShowCategoryGrid"
            :should-show-product-grid="shouldShowProductGrid"
            :filtered-products="filteredProducts"
            @category-select="$emit('categorySelect', $event)"
            @product-click="$emit('productClick', $event)"
        />

        <!-- 🎛️ ABA: COMANDAS -->
        <div
            v-if="activeTab === 'operations'"
            class="flex-1 px-4 pb-4 flex flex-col min-h-0 animate-in fade-in"
            :style="{ paddingTop: 'var(--app-header-height, 7rem)' }"
        >
            <OpenTabs
                :orders="onSiteOrders"
                :table-areas="initialTableAreas"
                :is-team-mode="true"
                @open-menu="(...args) => $emit('openMenu', ...args)"
                @create-order="(...args) => $emit('createOrder', ...args)"
            />
        </div>

        <!-- 📦 ABA: REPOSIÇÃO -->
        <div v-if="activeTab === 'restock'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <RestockTab :use-restock-data="restockData" />
        </div>

        <!-- 📦 ABA: ESTOQUE E INVENTÁRIO (WMS MOBILE) -->
        <div v-if="activeTab === 'inventory'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <InventoryTab
                :catalog="catalog || initialProducts"
                :is-active="activeTab === 'inventory'"
            />
        </div>

        <!-- 📊 ABA: GESTOR -->
        <div v-if="activeTab === 'manager'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <ManagerShiftDashboard
                :current-shift="$page.props.managerData?.shift_status === 'open' ? $page.props.managerData.shift_model : null"
                :manager-data="$page.props.managerData"
            />
        </div>

        <!-- 🖥️ ABA: TERMINAIS (FROTA) -->
        <div v-if="activeTab === 'fleet'" class="flex-1 overflow-y-auto hide-scrollbar p-0 pb-32 animate-in fade-in">
            <DeviceFleetPanel />
        </div>

        <!-- 🍹 ABA: MINI KDS DO BAR -->
        <div v-if="activeTab === 'bar'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <TeamBarTab
                :pending-items="barItems"
                @take-item="$emit('kds-take', $event)"
                @complete-item="$emit('kds-complete', $event)"
                @print-item="$emit('kds-print', $event)"
                @loss-item="$emit('kds-loss', $event)"
            />
        </div>

        <!-- ⚙️ ABA: CONFIGURAÇÕES -->
        <div v-show="activeTab === 'settings'" class="flex-1 overflow-y-auto hide-scrollbar p-4 pb-32 animate-in fade-in">
            <AppearanceSettings :is-team-mode="true" />
        </div>

        <!-- 👛 ABA: CARTEIRA (Perfil Corporativo) -->
        <div v-if="activeTab === 'wallet'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <CorporateProfileTab
                :employee-data="$page.props.employeeWalletData"
                @start-allowance-order="$emit('startAllowanceOrder')"
                @open-settings="$emit('update:activeTab', 'settings')"
            />
        </div>

        <!-- 🛵 ABA: MOTOBOY -->
        <div v-if="activeTab === 'driver'" class="flex-1 overflow-hidden flex flex-col min-h-0 animate-in fade-in">
            <DriverBoard />
        </div>

        <!-- ✅ ABA: CHECKLISTS -->
        <div v-if="activeTab === 'checklists'" class="flex-1 overflow-y-auto p-4 pb-32 hide-scrollbar animate-in fade-in">
            <TeamChecklists />
        </div>

        <!-- 🏭 ABA: PRODUÇÃO -->
        <div v-if="activeTab === 'production'" class="flex-1 overflow-hidden flex flex-col min-h-0 pb-20 animate-in fade-in">
            <ProductionBoard :orders="$page.props.productionOrders || []" />
        </div>

        <!-- 🛒 ABA: COMPRAS -->
        <div v-if="activeTab === 'shopping'" class="flex-1 overflow-hidden flex flex-col min-h-0 pb-20 animate-in fade-in">
            <ShoppingList :bidding-matrix="$page.props.biddingMatrix || {}" />
        </div>

        <!-- 🛒 CARRINHO DA EQUIPE -->
        <TeamCart
            ref="cartRef"
            :key="cartComponentKey"
            v-show="activeTab === 'cart'"
            class="!border-none !rounded-none !shadow-none h-full animate-in slide-in-from-right-4"
            :cart="cart"
            :cart-subtotal="cartSubtotal"
            :order-type="orderType"
            :table-areas="initialTableAreas"
            :customers="initialCustomers"
            :edit-order-data="editOrderData"
            :is-allowance-mode="isAllowanceMode"
            :cart-routing-meta="cartRoutingMeta"
            @update-qty="(idx, delta) => $emit('update-qty', idx, delta)"
            @clear-cart="$emit('clearCart')"
            @edit-item="(item, idx) => $emit('edit-item', item, idx)"
            @checkout="$emit('checkout', $event)"
            @update:orderType="$emit('update:orderType', $event)"
            @routing-valid="$emit('routingValid', $event)"
            @routing-meta="$emit('routingMeta', $event)"
        />
    </main>
</template>

<script setup>
import { ref, defineAsyncComponent } from 'vue';
import TeamCart from "@/Pages/Team/TeamCart.vue";
import AppearanceSettings from "@/Pages/Settings/Components/AppearanceSettings.vue";
import OpenTabs from "@/Pages/Operations/Components/Dashboard/OpenTabs.vue";
import TeamMenuTab from "@/Pages/Team/TeamMenuTab.vue";

// Abas Assíncronas (Lazy Load)
const MealAllowanceTab = defineAsyncComponent(() => import('@/Pages/Team/MealAllowanceTab.vue'));
const InventoryTab = defineAsyncComponent(() => import('@/Pages/Team/InventoryTab.vue'));
const TeamHomeTab = defineAsyncComponent(() => import('@/Pages/Team/TeamHomeTab.vue'));
const RestockTab = defineAsyncComponent(() => import('@/Pages/Team/RestockTab.vue'));
const ManagerShiftDashboard = defineAsyncComponent(() => import('@/Pages/Team/ManagerShiftDashboard.vue'));
const CorporateProfileTab = defineAsyncComponent(() => import('@/Pages/Team/CorporateProfileTab.vue'));
const DriverBoard = defineAsyncComponent(() => import('@/Pages/Team/DriverBoard.vue'));
const TeamChecklists = defineAsyncComponent(() => import('@/Pages/Team/TeamChecklists.vue'));
const ProductionBoard = defineAsyncComponent(() => import('@/Pages/Shared/SupplyChain/ProductionBoard.vue'));
const ShoppingList = defineAsyncComponent(() => import('@/Pages/Shared/SupplyChain/ShoppingList.vue'));
const DeviceFleetPanel = defineAsyncComponent(() => import('@/Pages/Team/DeviceFleetPanel.vue'));
const TeamBarTab = defineAsyncComponent(() => import('@/Pages/Team/TeamBarTab.vue'));

const props = defineProps({
    activeTab: String,
    searchQuery: { type: String, default: '' },
    categoryLayout: String,
    mobileCategoryLayout: String,
    productCardLayout: String,
    visibleMenuCategories: Array,
    activeMenuCategory: [String, Number],
    visibleGridCategories: Array,
    shouldShowCategoryGrid: Boolean,
    shouldShowProductGrid: Boolean,
    filteredProducts: Array,
    onSiteOrders: Array,
    initialTableAreas: Array,
    restockData: Object,
    catalog: Array,
    initialProducts: Array,
    cartComponentKey: Number,
    cart: Array,
    cartSubtotal: Number,
    orderType: String,
    initialCustomers: Array,
    editOrderData: Object,
    isAllowanceMode: Boolean,
    cartRoutingMeta: Object,
    barItems: { type: Array, default: () => [] }
});

defineEmits([
    'update:activeTab', 'update:searchQuery', 'categorySelect', 'productClick', 'openMenu',
    'createOrder', 'startAllowanceOrder', 'update-qty', 'clearCart',
    'edit-item', 'checkout', 'update:orderType', 'routingValid', 'routingMeta',
    'kds-take', 'kds-complete', 'kds-print', 'kds-loss', 'kds-cancel', 'open-meal-modifiers'
]);

const cartRef = ref(null);
const mealTabRef = ref(null);

const toggleCartRouting = () => {
    cartRef.value?.toggleRouting();
};

const addMealItemToSlot = (attendeeId, item) => {
    mealTabRef.value?.addItemToActiveAttendee(attendeeId, item);
};

defineExpose({
    toggleCartRouting,
    addMealItemToSlot
});
</script>
```

## File: resources/js/Pages/Team/TeamApp.vue
```vue
<template>
    <div class="h-full w-full bg-surface-1 text-surface-text flex flex-col font-sans overflow-hidden select-none relative">

        <!-- 🔒 FATIA 1: BLOQUEIO DE TURNO -->
        <TeamShiftLockOverlay
            v-if="!isOnDuty"
            :is-starting="isStarting"
            @start-shift="startShift"
        />

        <div v-else class="flex-1 flex flex-col h-full overflow-hidden relative bg-surface-2">

            <!-- 👑 FATIA 2: HEADER GLOBAL COM CONTEXTO CONDICIONAL -->
            <TeamHeader
                :active-tab="activeTab"
                :active-category="activeMenuCategory"
                :active-product="isModifiersVisible || isComboVisible ? activeProduct : null"
                :current-total="Number(productTotal)"
                :context-title="headerContext.title"
                :context-subtitle="headerContext.subtitle"
                @open-notifications="() => {}"
            />

            <!-- 🔍 TELEPORT DIRETO DA BUSCA DO CARDÁPIO (Protegido por isMounted) -->
            <Teleport to="#team-header-action-portal" v-if="isMounted && activeTab === 'menu' && !activeProduct">
                <div class="flex items-center">
                    <ExpandableSearch
                        v-model="searchQuery"
                        placeholder="Buscar no Cardápio..."
                        class="!w-36 sm:!w-48 md:!w-64 animate-in fade-in duration-300"
                    />
                </div>
            </Teleport>

            <div class="flex-1 flex flex-col min-h-0 relative transition-all duration-200">
                <!-- 📖 FATIA 3: RENDERIZADOR DE ABAS DA OPERAÇÃO -->
                <TeamTabContainer
                    ref="tabContainerRef"
                    v-model:active-tab="activeTab"
                    :category-layout="categoryLayout"
                    :mobile-category-layout="mobileCategoryLayout"
                    :product-card-layout="productCardLayout"
                    :visible-menu-categories="visibleMenuCategories"
                    :active-menu-category="selectedCategory"
                    :visible-grid-categories="visibleGridCategories"
                    :should-show-category-grid="shouldShowCategoryGrid"
                    :should-show-product-grid="shouldShowProductGrid"
                    :filtered-products="filteredProducts"
                    :on-site-orders="onSiteOrders"
                    :initial-table-areas="initialTableAreas"
                    :restock-data="restockData"
                    :catalog="catalog || initialProducts"
                    :cart-component-key="cartComponentKey"
                    :cart="cart"
                    :cart-subtotal="cartSubtotal"
                    v-model:order-type="orderType"
                    :initial-customers="initialCustomers"
                    :edit-order-data="editOrderData"
                    :is-allowance-mode="isAllowanceMode"
                    :cart-routing-meta="cartRoutingMeta"
                    :bar-items="barItems"
                    @category-select="selectedCategory = $event"
                    @product-click="onProductClick"
                    @open-menu="openContextMenu"
                    @create-order="handleCreateOrderOnTable"
                    @start-allowance-order="handleStartAllowanceOrder"
                    @update-qty="updateQty"
                    @clear-cart="handleClearCart"
                    @edit-item="openEditModifiers"
                    @checkout="handleCheckoutWrapper"
                    @routing-valid="handleRoutingValid"
                    @routing-meta="handleRoutingMeta"
                    @kds-take="handleKdsTake"
                    @kds-complete="handleKdsComplete"
                    @kds-print="handleKdsPrint"
                    @kds-loss="handleKdsCancel"
                    @kds-cancel="handleKdsCancel"
                    @open-meal-modifiers="handleOpenMealModifiers"
                />
            </div>

            <!-- 🏝 DYNAMIC ISLAND DA OPERAÇÃO -->
            <TeamDynamicIsland
                ref="islandRef"
                v-model:active-tab="activeTab"
                :cart-length="cart.length"
                :selected-category="selectedCategory"
                :category-layout="categoryLayout"
                :breadcrumbs="breadcrumbs"
                :order-context="contextMenuVisible ? selectedOrderContext : null"
                :cart-routing-valid="cartRoutingValid"
                :cart-routing-meta="cartRoutingMeta"
                :is-submitting="isSubmittingOrder"
                :is-success="isOrderSuccess"
                :restock-count="restockData.totalItemsToRestock.value"
                :is-allowance-mode="isAllowanceMode"
                :bar-items-count="pendingBarItemsCount"
                :configurator-type="isComboVisible ? 'combo' : (isModifiersVisible ? 'modifier' : null)"
                @open-more="showMoreMenu = true"
                @go-back-category="goBackCategory"
                @navigate="activeTab = 'operations'"
                @reset-menu="selectedCategory = null; searchQuery = ''"
                @close-context="contextMenuVisible = false"
                @show-more-actions="showOrderActionsSheet = true"
                @toggle-routing="tabContainerRef?.toggleCartRouting()"
                @submit-order="triggerCheckout"
                @clear-cart="handleClearCart"
            />

            <!-- 🎛️ MODAL DE MÓDULOS -->
            <TeamModulesModal
                v-model:visible="showMoreMenu"
                :manager-data="$page.props.managerData"
                :restock-count="restockData.totalItemsToRestock.value"
                @select-tab="selectMenuTab"
            />

            <!-- 🧩 OVERLAYS (CONFIGURADORES E AÇÕES) -->
            <TeamOverlays
                ref="overlaysRef"
                :is-modifiers-visible="isModifiersVisible"
                :is-combo-visible="isComboVisible"
                :active-product="activeProduct"
                :is-edit-mode="editItemIndex !== null"
                :initial-products="initialProducts"
                v-model:contextMenuVisible="contextMenuVisible"
                v-model:showOrderActionsSheet="showOrderActionsSheet"
                :selected-order-context="selectedOrderContext"
                :company-settings="$page.props.companySettings"
                @update:total="productTotal = Number($event || 0)"
                @confirm-modifiers="handleCustomModifiersConfirm"
                @confirm-combo="handleComboConfirm"
                @close-configurators="closeConfigurators"
                @action="(actionId, orderObj) => customHandleMenuAction(actionId, orderObj)"
                @duplicate-item="(item, cb) => customHandleItemDuplicate(item, cb)"
                @modify-item="handleItemModify"
                @swap-item="handleItemSwap"
                @cancel-item="handleItemCancel"
                @reprint-item="handleItemReprint"
            />

            <!-- 🚀 BOTTOM SHEET DE DESBLOQUEIO POR QR CODE -->
            <QuickUnlockSheet
                v-model:visible="showQuickUnlockSheet"
                :token="quickUnlockToken"
                @unlocked="quickUnlockToken = ''"
            />

            <!-- 🔔 MODAL DE ALERTA DE IMPRESSÃO -->
            <PrintAlertNotice
                v-if="activePrintAlert"
                :alert="activePrintAlert"
                mode="modal"
                @dismiss="activePrintAlert = null"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, defineAsyncComponent, watch, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from "primevue/usetoast";

import MobileLayout from "@/Layouts/MobileLayout.vue";
import TeamDynamicIsland from "@/Pages/Team/TeamDynamicIsland.vue";
import TeamModulesModal from "@/Pages/Team/TeamModulesModal.vue";
import TeamOverlays from "@/Pages/Team/TeamOverlays.vue";
import TeamHeader from "@/Pages/Team/TeamHeader.vue";
import TeamShiftLockOverlay from "@/Pages/Team/TeamShiftLockOverlay.vue";
import TeamTabContainer from "@/Pages/Team/TeamTabContainer.vue";
import PrintAlertNotice from '@/Components/UI/PrintAlertNotice.vue';
import ExpandableSearch from '@/Components/UI/ExpandableSearch.vue';

const QuickUnlockSheet = defineAsyncComponent(() => import('@/Pages/Gateway/QuickUnlockSheet.vue'));

import { useAppLifecycleSync } from '@/Composables/useAppLifecycleSync';
import { useCart } from "@/Pages/Shared/Composables/useCart";
import { useMenuNavigation } from "@/Pages/Shared/Composables/useMenuNavigation";
import { usePosSettings } from "@/Pages/Pos/Composables/usePosSettings";
import { useTeamApp } from "@/Pages/Team/Composables/useTeamApp";
import { usePushNotifications } from "@/Composables/usePushNotifications";
import { useOrderFlow } from "@/Pages/Shared/Composables/useOrderFlow";
import { useItemActions } from "@/Pages/Shared/Composables/useItemActions";
import { useRestock } from "@/Pages/Shared/Composables/useRestock";
import { useTeamSubmit } from "@/Pages/Team/Composables/useTeamSubmit";
import { useTeamBarKds } from "@/Pages/Team/Composables/useTeamBarKds";
import { useTeamHeaderContext } from "@/Pages/Team/Composables/useTeamHeaderContext";
import { useTeamSync } from "@/Pages/Team/Composables/useTeamSync";
import { useTenantEcho } from '@/Composables/useTenantEcho';
import { useDynamicFooterHeight } from '@/Composables/useDynamicFooterHeight';

defineOptions({ layout: MobileLayout });

const props = defineProps({
    defaultTab: { type: String, default: 'menu' },
    initialCategories: { type: Array, default: () => [] },
    initialProducts: { type: Array, default: () => [] },
    catalog: { type: Array, default: () => [] },
    initialTableAreas: { type: Array, default: () => [] },
    initialCustomers: { type: Array, default: () => [] },
    initialOrders: { type: Array, default: () => [] },
    editOrderData: Object,
    operatorName: { type: String, default: 'Equipe' },
    isOnDuty: { type: Boolean, default: false }
});

const toast = useToast();
const page = usePage();
const settings = usePosSettings();
const { categoryLayout, productCardLayout } = settings;
const { requestPushPermission, checkSubscriptionStatus, subscribeToWebPush } = usePushNotifications();

const tabContainerRef = ref(null);
const cartComponentKey = ref(0);
const showOrderActionsSheet = ref(false);
const cartRoutingValid = ref(false);
const cartRoutingMeta = ref(null);
const activePrintAlert = ref(null);
const isMounted = ref(false);
const mealTargetAttendee = ref(null);

const handleClearCart = () => {
    isAllowanceMode.value = false;
    clearCart();
    cartRoutingValid.value = false;
    cartRoutingMeta.value = null;

    setTimeout(() => {
        localStorage.removeItem("pos_draft_routing");
        localStorage.removeItem('pos_draft_cart');
        localStorage.removeItem('pos_return_order_id');
        localStorage.removeItem('pos_return_url');
        localStorage.removeItem('pos_append_title');
    }, 50);

    if (window.location.search) {
        window.history.replaceState({}, '', window.location.pathname);
        router.visit(window.location.pathname);
    } else {
        cartComponentKey.value++;
        activeTab.value = 'menu';
    }
};

useAppLifecycleSync({
    only: ['initialOrders', 'initialTableAreas', 'cashierData', 'initialProducts'],
    minSleepSeconds: 10,
    onWake: (freshPage) => {
        const targetOrderId = cartRoutingMeta.value?.id || cartRoutingMeta.value?.order_id;
        if (targetOrderId) {
            const freshOrders = freshPage.props?.initialOrders || [];
            const isStillActive = freshOrders.some(o =>
                Number(o.id) === Number(targetOrderId) &&
                !['closed', 'cancelled', 'completed', 'delivered'].includes(o.status) &&
                o.payment_status !== 'paid'
            );

            if (!isStillActive) {
                toast.add({
                    severity: 'warn',
                    summary: 'Comanda Encerrada',
                    detail: 'Esta mesa foi quitada ou finalizada no caixa.',
                    life: 6000
                });
                handleClearCart();
            }
        }
    }
});

const handleRoutingMeta = (newMeta) => {
    if (!newMeta && !cartRoutingMeta.value) return;
    try {
        if (JSON.stringify(newMeta) === JSON.stringify(cartRoutingMeta.value)) return;
    } catch (e) {}
    cartRoutingMeta.value = newMeta;
};

const handleRoutingValid = (isValid) => {
    if (cartRoutingValid.value !== isValid) {
        cartRoutingValid.value = isValid;
    }
};

const showMoreMenu = ref(false);
const showQuickUnlockSheet = ref(false);
const quickUnlockToken = ref('');

const restockData = useRestock();
const isAllowanceMode = ref(false);

const onSiteOrders = computed(() => {
    return (props.initialOrders || []).filter(o =>
        (o.type === 'table' || o.type === 'counter') &&
        !['closed', 'cancelled', 'completed', 'delivered'].includes(o.status)
    );
});

const {
    isStarting, activeTab, contextMenuVisible, selectedOrderContext,
    startShift: originalStartShift, openContextMenu, handleMenuAction
} = useTeamApp();

activeTab.value = props.defaultTab;

const cartContext = useCart();
const { cart, cartSubtotal, updateQty, clearCart, autoParkCurrent } = cartContext;
const orderType = cartContext.orderType = ref('table');

const { headerContext } = useTeamHeaderContext({
    activeTab,
    contextMenuVisible,
    selectedOrderContext,
    isAllowanceMode,
    cartRoutingMeta,
    cart,
    orderType,
    operatorName: computed(() => props.operatorName)
});

watch(activeTab, (newTab, oldTab) => {
    if (!newTab || newTab === oldTab) return;
    const targetUrl = route('team.app', { tab: newTab });
    if (window.location.href !== targetUrl) {
        window.history.pushState({ tab: newTab }, '', targetUrl);
    }
});

const handleTabChange = (e) => {
    activeTab.value = e.detail;
};

const selectMenuTab = (tab) => {
    activeTab.value = tab;
    showMoreMenu.value = false;
};

const startShift = async () => {
    isStarting.value = true;
    const hasPermission = await requestPushPermission();
    if (hasPermission && page.props.vapidPublicKey) {
        await subscribeToWebPush(page.props.vapidPublicKey);
    }
    originalStartShift();
    restockData.clearRestock();
    restockData.fetchRestockBoard(true);
};

const {
    isSubmittingOrder, isOrderSuccess, handleCreateOrderOnTable,
    customHandleMenuAction, handleSendOrder, triggerCheckout, customHandleItemDuplicate
} = useTeamSubmit({
    props, cart, cartSubtotal, clearCart, activeTab,
    cartRoutingMeta, cartRoutingValid, orderType, cartComponentKey,
    selectedOrderContext, contextMenuVisible, showOrderActionsSheet, openContextMenu, handleMenuAction,
    isAllowanceMode,
    source: 'team_app'
});

const handleStartAllowanceOrder = () => {
    isAllowanceMode.value = true;
    clearCart();
    activeTab.value = 'menu';
    cartRoutingValid.value = true;
    cartRoutingMeta.value = { type: 'counter', identifier: 'Consumo Interno' };
    toast.add({ severity: 'info', summary: 'Modo Consumo Interno', detail: 'Monte seu pedido. O pagamento será debitado da sua carteira.', life: 4000 });
};

const handleCheckoutWrapper = (checkoutPayload) => {
    if (isAllowanceMode.value) {
        checkoutPayload.routing = checkoutPayload.routing || {};
        checkoutPayload.routing.payment_engine = 'internal';
        checkoutPayload.routing.payment_method_name = 'Consumo Interno';
    }
    handleSendOrder(checkoutPayload);
    setTimeout(() => { isAllowanceMode.value = false; }, 1000);
};

const {
    isModifiersVisible, isComboVisible, activeProduct, editItemIndex,
    initializeFlow, closeConfigurators, handleProductClick, openEditModifiers,
    handleModifiersConfirm, handleComboConfirm
} = useOrderFlow(props, cartContext, triggerCheckout);

// 🥪 INTEGRAÇÃO DO CONFIGURADOR DE LANCHE COM O MODAL DE MODIFICADORES
const handleOpenMealModifiers = (payload) => {
    mealTargetAttendee.value = payload.attendee;
    handleProductClick(payload.product);
};

const handleCustomModifiersConfirm = (configuredItem) => {
    if (mealTargetAttendee.value && activeTab.value === 'meal') {
        const prod = activeProduct.value;
        const unitPrice = Number(prod.price || prod.base_price || 0);
        const extra = Number(configuredItem.extraPrice || 0);
        const qty = Number(configuredItem.qty || 1);

        tabContainerRef.value?.addMealItemToSlot(mealTargetAttendee.value.id, {
            product_id: prod.id,
            name: prod.name,
            qty: qty,
            price: unitPrice,
            extraPrice: extra,
            totalPrice: (unitPrice + extra) * qty,
            modifiersText: configuredItem.text || '',
            is_takeaway: Boolean(configuredItem.isTakeaway),
            notes: configuredItem.notes || null,
            structuredModifiers: configuredItem.structuredModifiers || []
        });

        mealTargetAttendee.value = null;
        closeConfigurators();
        return;
    }

    handleModifiersConfirm(configuredItem);
};

const islandRef = ref(null);
const overlaysRef = ref(null);

useDynamicFooterHeight(islandRef, {
    cssVarName: '--app-footer-height',
    fallbackHeight: 96,
    extraOffset: 16
});

const { handleItemModify, handleItemSwap, handleItemCancel } = useItemActions(
    selectedOrderContext,
    contextMenuVisible,
    'team.app'
);

const productTotal = ref(0);

const handleKdsCancelWrapper = (item) => {
    selectedOrderContext.value = item.order;
    handleItemCancel(item);
};

const {
    barItems,
    pendingBarItemsCount,
    handleKdsTake,
    handleKdsComplete,
    handleKdsPrint,
    handleKdsCancel
} = useTeamBarKds(props, handleKdsCancelWrapper);

const mobileCategoryLayout = computed(() => categoryLayout.value === 'list' || productCardLayout.value === 'list' ? 'list' : 'grid');

const mobileSettings = {
    categoryHierarchy: settings.categoryHierarchy,
    productCardLayout: settings.productCardLayout,
    outOfStockMode: settings.outOfStockMode,
    categoryLayout: mobileCategoryLayout
};

const {
    selectedCategory, activeMenuCategory, visibleMenuCategories,
    filteredProducts, shouldShowProductGrid, breadcrumbs, goBackCategory,
    visibleGridCategories, shouldShowCategoryGrid, searchQuery
} = useMenuNavigation(() => props.initialCategories, () => props.initialProducts, mobileSettings);

useTeamSync({
    selectedOrderContext,
    activeTab,
    onShiftClosed: handleClearCart
});

useTenantEcho().listenChannel('operations', '.print.alert', (data) => {
    if (data.source === 'team_app') {
        activePrintAlert.value = data;
    }
});

onMounted(() => {
    checkSubscriptionStatus();
    window.addEventListener('team-tab-changed', handleTabChange);
    window.addEventListener('popstate', (e) => {
        if (e.state && e.state.tab) activeTab.value = e.state.tab;
    });

    if (!props.isOnDuty) {
        handleClearCart();
    } else {
        restockData.fetchRestockBoard();
    }

    const urlParams = new URLSearchParams(window.location.search);
    initializeFlow(urlParams);
    nextTick(() => { isMounted.value = true; });
});

onBeforeUnmount(() => {
    autoParkCurrent();
    window.removeEventListener('team-tab-changed', handleTabChange);
});

const onProductClick = (product) => {
    searchQuery.value = '';
    productTotal.value = Number(product?.price ?? product?.base_price ?? 0);
    handleProductClick(product);
};
</script>
```

## File: resources/js/Pages/Team/TeamDynamicIsland.vue
```vue
<template>
    <nav
        ref="islandRef"
        :class="[baseIslandClass, shapeIslandClass]"
        :style="islandStyle"
    >
        <!-- Container isolado de layout e recorte -->
        <!-- 🚀 Adicionado 'overflow-hidden' e ':class="shapeIslandClass"' -->
        <div
            ref="contentRef"
            class="w-max h-max flex items-center justify-center shrink-0 p-1.5 overflow-hidden"
            :class="shapeIslandClass"
        >

            <!-- 1. MODO CONFIGURADOR -->
            <div
                v-if="configuratorType"
                id="island-portal-target"
                class="w-max flex flex-col justify-center shrink-0"
            ></div>

            <!-- 2. MODO CARRINHO DA EQUIPE (Apenas se houver itens) -->
            <div
                v-else-if="activeTab === 'cart' && cartLength > 0"
                class="flex items-center gap-2 shrink-0 px-1"
            >
                <button
                    @click.prevent="emit('update:activeTab', 'menu')"
                    class="c-island-btn shrink-0 text-surface-text hover:bg-surface-2"
                    title="Voltar ao Cardápio"
                >
                    <i class="pi pi-arrow-left"></i>
                </button>

                <SlideToConfirm
                    class="w-56 sm:w-64 !h-11 transition-all"
                    :color="isAllowanceMode ? 'primary' : 'success'"
                    :text="isAllowanceMode ? 'Debitar' : 'Confirmar'"
                    :success-text="isAllowanceMode ? 'Debitado!' : 'Enviado!'"
                    :icon="isAllowanceMode ? 'pi-wallet' : 'pi-send'"
                    format="long"
                    :disabled="!cartRoutingValid || cartLength === 0"
                    :is-loading="isSubmitting"
                    :is-success="isSuccess"
                    @confirm="emit('submit-order')"
                />

                <button
                    @click.prevent="emit('clear-cart')"
                    class="c-island-btn shrink-0 !bg-danger-500/10 hover:!bg-danger-500 !text-danger-500 hover:!text-white transition-colors"
                    title="Esvaziar Carrinho"
                >
                    <i class="pi pi-trash"></i>
                </button>
            </div>

            <!-- 3. MODO CARDÁPIO EM VENDA ATIVA (Apenas se houver itens no carrinho) -->
            <div
                v-else-if="activeTab === 'menu' && isOrderFlow"
                class="flex items-center gap-1 shrink-0"
            >
                <button
                    v-if="isInCategoryView"
                    @click.prevent="emit('go-back-category')"
                    class="c-island-btn c-island-btn-inactive shrink-0"
                    title="Voltar Categoria"
                >
                    <i class="pi pi-arrow-left"></i>
                </button>

                <button
                    @click.prevent="handleTabClick('menu')"
                    class="c-island-btn c-island-btn-active"
                    title="Cardápio"
                >
                    <i class="pi pi-book"></i>
                </button>

                <!-- 🛒 Só renderiza se houver itens -->
                <button
                    v-if="cartLength > 0"
                    @click.prevent="handleTabClick('cart')"
                    class="c-island-btn c-island-btn-inactive relative"
                    title="Carrinho"
                >
                    <i class="pi pi-shopping-cart text-white"></i>
                    <span class="absolute top-1 right-1 flex h-[18px] min-w-[18px]">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-primary-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                            {{ cartLength }}
                        </span>
                    </span>
                </button>
            </div>

            <!-- 🚀 4. MODO REPOSIÇÃO / WMS -->
            <div
                v-else-if="activeTab === 'restock'"
                class="flex items-center gap-1.5 shrink-0 px-1"
            >
                <button
                    @click.prevent="handleRestockBack"
                    class="c-island-btn shrink-0 text-surface-text hover:bg-surface-2"
                    title="Voltar"
                >
                    <i class="pi pi-arrow-left"></i>
                </button>

                <div class="w-px h-6 bg-surface-border/50 mx-0.5"></div>

                <button
                    v-if="isFreeMode"
                    @click.prevent="activeSubTab = 'clipboard'"
                    class="c-island-btn"
                    :class="activeSubTab === 'clipboard' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                    title="Prancheta: Anotar o que falta"
                >
                    <i class="pi pi-clipboard"></i>
                </button>

                <button
                    @click.prevent="activeSubTab = 'box'"
                    class="c-island-btn relative"
                    :class="activeSubTab === 'box' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                    title="Caixa: Itens para Coleta"
                >
                    <i class="pi pi-box"></i>
                    <span
                        v-if="totalBoxItemsCount > 0"
                        class="absolute top-1 right-1 flex h-[18px] min-w-[18px]"
                    >
                        <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-amber-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                            {{ totalBoxItemsCount }}
                        </span>
                    </span>
                </button>
            </div>

            <!-- 5. NAVEGAÇÃO DA EQUIPE -->
            <div
                v-else
                class="flex items-center shrink-0"
            >
                <!-- 🛵 MODO MOTOBOY EXCLUSIVO -->
                <template v-if="resolvedIsDriver">
                    <button
                        @click.prevent="handleTabClick('home')"
                        class="c-island-btn"
                        :class="activeTab === 'home' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Início"
                    >
                        <i class="pi pi-home"></i>
                    </button>

                    <button
                        @click.prevent="handleTabClick('driver')"
                        class="c-island-btn"
                        :class="activeTab === 'driver' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Minhas Entregas"
                    >
                        <i class="pi pi-truck"></i>
                    </button>

                    <button
                        @click.prevent="handleTabClick('checklists')"
                        class="c-island-btn"
                        :class="activeTab === 'checklists' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Minhas Tarefas"
                    >
                        <i class="pi pi-list-check"></i>
                    </button>
                </template>

                <!-- 🍽️ MODO ATENDIMENTO OPERACIONAL (GARÇOM / BALCÃO / SALÃO) -->
                <template v-else>
                    <button
                        v-if="orderContext"
                        @click.prevent="emit('close-context')"
                        class="c-island-btn c-island-btn-inactive shrink-0"
                        title="Fechar Comanda"
                    >
                        <i class="pi pi-arrow-left"></i>
                    </button>

                    <button
                        v-else-if="isInCategoryView"
                        @click.prevent="emit('go-back-category')"
                        class="c-island-btn c-island-btn-inactive shrink-0"
                        title="Voltar Categoria"
                    >
                        <i class="pi pi-arrow-left"></i>
                    </button>

                    <button
                        @click.prevent="handleTabClick('home')"
                        class="c-island-btn"
                        :class="activeTab === 'home' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Início"
                    >
                        <i class="pi pi-home"></i>
                    </button>

                    <button
                        @click.prevent="handleTabClick('operations')"
                        class="c-island-btn"
                        :class="activeTab === 'operations' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Mapa de Mesas / Salão"
                    >
                        <i class="pi pi-th-large"></i>
                    </button>

                    <button
                        v-if="isKdsBarEnabled"
                        @click.prevent="handleTabClick('bar')"
                        class="c-island-btn relative"
                        :class="activeTab === 'bar' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="KDS Bar / Copa"
                    >
                        <i class="pi pi-receipt"></i>
                        <span
                            v-if="barItemsCount > 0"
                            class="absolute top-1 right-1 flex h-[18px] min-w-[18px]"
                        >
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-orange-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                                {{ barItemsCount }}
                            </span>
                        </span>
                    </button>

                    <button
                        v-if="orderContext"
                        @click.prevent="emit('show-more-actions')"
                        class="c-island-btn c-island-btn-inactive !text-amber-500"
                        title="Ações da Mesa"
                    >
                        <i class="pi pi-plus"></i>
                    </button>

                    <button
                        v-else
                        @click.prevent="handleTabClick('menu')"
                        class="c-island-btn"
                        :class="activeTab === 'menu' ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Cardápio"
                    >
                        <i class="pi pi-book"></i>
                    </button>

                    <!-- 🛒 CARRINHO: Estritamente visível apenas se houver itens -->
                    <button
                        v-if="cartLength > 0"
                        @click.prevent="handleTabClick('cart')"
                        class="c-island-btn c-island-btn-inactive relative"
                        title="Carrinho"
                    >
                        <i class="pi pi-shopping-cart text-white"></i>
                        <span class="absolute top-1 right-1 flex h-[18px] min-w-[18px]">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-[18px] min-w-[18px] px-1 bg-primary-500 text-white text-[10px] font-black items-center justify-center shadow-sm">
                                {{ cartLength }}
                            </span>
                        </span>
                    </button>

                    <button
                        @click.prevent="emit('open-more')"
                        class="c-island-btn relative"
                        :class="isExtraTabActive ? 'c-island-btn-active' : 'c-island-btn-inactive'"
                        title="Mais Módulos"
                    >
                        <i class="pi pi-cog"></i>
                        <span
                            v-if="restockCount > 0"
                            class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full bg-warning-500 ring-2 ring-surface-0 animate-pulse"
                        ></span>
                    </button>
                </template>
            </div>

        </div>
    </nav>
</template>

<script setup>
import { computed, watch } from 'vue';
import { usePage } from "@inertiajs/vue3";
import SlideToConfirm from "@/Components/UI/SlideToConfirm.vue";
import { useDynamicIsland } from "@/Pages/Shared/Composables/useDynamicIsland";
import { useRestock } from "@/Pages/Shared/Composables/useRestock";

const props = defineProps({
    activeTab: String,
    cartLength: { type: Number, default: 0 },
    selectedCategory: [String, Number],
    categoryLayout: String,
    breadcrumbs: { type: Array, default: () => [] },
    orderContext: { type: Object, default: null },
    cartRoutingValid: { type: Boolean, default: false },
    cartRoutingMeta: { type: Object, default: null },
    isEditMode: { type: Boolean, default: false },
    isSubmitting: { type: Boolean, default: false },
    isSuccess: { type: Boolean, default: false },
    restockCount: { type: Number, default: 0 },
    isAllowanceMode: { type: Boolean, default: false },
    barItemsCount: { type: Number, default: 0 },
    configuratorType: { type: String, default: null },
    isDriver: { type: Boolean, default: false }
});

const emit = defineEmits([
    'update:activeTab', 'go-back-category', 'reset-menu', 'order-action',
    'close-context', 'show-more-actions', 'toggle-routing', 'clear-cart',
    'submit-order', 'open-more', 'navigate'
]);

const page = usePage();

// 🚀 Avaliação do perfil: detecta se a visualização atual deve ser a visão limpa de entregas
const resolvedIsDriver = computed(() => {
    if (props.isDriver || page.props.isDriver) return true;

    const user = page.props.auth?.user;
    if (!user) return false;

    const role = (user.role || '').toLowerCase();
    const roles = Array.isArray(user.roles)
        ? user.roles.map(r => (typeof r === 'string' ? r : r.name || '').toLowerCase())
        : [];

    // Se o usuário for estritamente motorista (não acumula funções de gestão/garçom)
    const isExclusivelyDriver = (role === 'driver' || role === 'motoboy') &&
        !roles.some(r => ['admin', 'manager', 'gerente', 'waiter', 'garcom'].includes(r));

    return isExclusivelyDriver;
});

const {
    baseIslandClass,
    shapeIslandClass,
    islandStyle,
    islandRef,
    contentRef,
    handleTabClick
} = useDynamicIsland(props, emit);

// 🚀 Trava defensiva: se o carrinho for zerado enquanto estiver na aba cart, volta ao menu
watch(() => props.cartLength, (newLen) => {
    if (newLen === 0 && props.activeTab === 'cart') {
        emit('update:activeTab', 'menu');
    }
});

const { activeSubTab, isFreeMode, totalBoxItemsCount } = useRestock();

const handleRestockBack = () => {
    if (isFreeMode.value && activeSubTab.value === 'box') {
        activeSubTab.value = 'clipboard';
    } else {
        handleTabClick('home');
    }
};

// 🚀 Venda ativa só existe se houver ao menos 1 item no carrinho
const isOrderFlow = computed(() => props.cartLength > 0);

const isKdsBarEnabled = computed(() => {
    const mode = page.props.companySettings?.kds_panel_mode || 'all';
    return mode === 'all' || mode === 'bar';
});

// Inclui 'driver' e 'checklists' na lista de abas nativas para evitar o destaque falso no ícone da engrenagem
const isExtraTabActive = computed(() => {
    return !['home', 'operations', 'menu', 'cart', 'bar', 'restock', 'driver', 'checklists'].includes(props.activeTab);
});

const isInCategoryView = computed(() => {
    return props.selectedCategory &&
        (props.categoryLayout === 'grid' || props.categoryLayout === 'list') &&
        props.activeTab === 'menu';
});
</script>
```

## File: resources/js/Pages/Team/DriverBoard.vue
```vue
<template>
    <div
        class="h-full flex flex-col overflow-y-auto hide-scrollbar pb-32 px-4 space-y-6"
        :style="{ paddingTop: 'calc(var(--app-header-height, 6rem) + 1rem)' }"
    >
        <!-- 🧭 CONTROLO DE ABAS VIA TELEPORT PARA O TOPO (SegmentedControl) -->
        <Teleport to="#team-header-actions" :disabled="!hasTeleportTarget">
            <SegmentedControl
                v-model="activeTab"
                :options="tabOptions"
            />
        </Teleport>

        <!-- 🗺️ ROTA OTIMIZADA NO GOOGLE MAPS -->
        <div v-if="myDeliveries.length > 0 && (activeTab === 'all' || activeTab === 'route')" class="shrink-0">
            <button
                @click="openGoogleMaps"
                type="button"
                class="w-full bg-surface-1 text-primary-600 dark:text-primary-400 font-black text-xs uppercase tracking-wider py-3.5 px-4 rounded-2xl shadow-sm border border-surface-border flex items-center justify-center gap-2.5 active:scale-98 transition-all outline-none cursor-pointer hover:border-primary-500/40"
            >
                <i class="pi fa-location-dot text-base text-primary-500"></i>
                <span>Gerar Rota Otimizada no Google Maps ({{ myDeliveries.length }})</span>
            </button>
            <p v-if="myDeliveries.length > 1" class="text-center text-[10px] text-surface-muted mt-1.5 font-medium">
                Os pontos com GPS e moradas serão ordenados para o menor trajeto.
            </p>
        </div>

        <!-- 🛵 SECÇÃO 1: COMIGO (EM ROTA) -->
        <div v-if="activeTab === 'all' || activeTab === 'route'" class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Comigo (Em Rota)</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500">
                    {{ myDeliveries.length }}
                </span>
            </div>

            <div v-if="myDeliveries.length === 0" class="text-center py-6 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <i class="pi pi-box text-xl opacity-40 mb-1 block"></i>
                <p class="text-xs font-bold">Nenhum pedido na mochila de momento.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in myDeliveries"
                    :key="order.id"
                    :order="order"
                    @charge-pix="openPixModal"
                >
                    <template #actions>
                        <div class="flex gap-2 pt-1">
                            <button
                                v-if="order.status !== 'dispatched'"
                                @click="updateStatus(order.id, 'dispatched')"
                                type="button"
                                class="flex-1 h-11 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 border-none cursor-pointer shadow-sm active:scale-98"
                            >
                                <i class="pi pi-truck text-xs"></i>
                                <span>Sair p/ Entrega</span>
                            </button>

                            <button
                                v-if="order.status === 'dispatched'"
                                @click="updateStatus(order.id, 'delivered')"
                                type="button"
                                class="flex-1 h-11 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 border-none cursor-pointer shadow-sm active:scale-98"
                            >
                                <i class="pi pi-check-circle text-xs"></i>
                                <span>Confirmar Entrega</span>
                            </button>
                        </div>
                    </template>
                </DeliveryDriverCard>
            </div>
        </div>

        <!-- 📦 SECÇÃO 2: EM DESPACHO (PRONTOS NO BALCÃO) -->
        <div v-if="activeTab === 'all' || activeTab === 'dispatch'" class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="font-black text-surface-text uppercase tracking-widest text-xs flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                    <span>Em Despacho (Prontos no Balcão)</span>
                </h3>
                <span class="text-xs font-black px-2 py-0.5 rounded-full bg-primary-500/10 text-primary-500">
                    {{ readyDeliveries.length }}
                </span>
            </div>

            <div v-if="readyDeliveries.length === 0" class="text-center py-5 border border-dashed border-surface-border rounded-2xl text-surface-muted bg-surface-1/40">
                <p class="text-xs font-medium">Nenhum pedido pronto a aguardar retirada.</p>
            </div>

            <div v-else class="space-y-3">
                <DeliveryDriverCard
                    v-for="order in readyDeliveries"
                    :key="order.id"
                    :order="order"
                    @charge-pix="openPixModal"
                >
                    <template #actions>
                        <button
                            @click="assignToMe(order.id)"
                            type="button"
                            class="w-full h-11 rounded-xl bg-surface-text text-surface-1 font-black text-xs uppercase tracking-wider shadow-sm active:scale-98 transition-all border-none cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <i class="pi pi-plus text-xs"></i>
                            <span>Pegar Pedido</span>
                        </button>
                    </template>
                </DeliveryDriverCard>
            </div>
        </div>

        <!-- MODAL PIX DO ESTAFETA -->
        <OrderPixModal
            v-if="selectedPixOrder"
            v-model:visible="isPixModalOpen"
            :order="selectedPixOrder"
            @payment-success="isPixModalOpen = false"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useTenantEcho } from '@/Composables/useTenantEcho';
import DeliveryDriverCard from '@/Pages/Team/DeliveryDriverCard.vue';
import OrderPixModal from '@/Pages/Shared/OrderPixModal.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';

const activeTab = ref('all');
const hasTeleportTarget = ref(false);

const isPixModalOpen = ref(false);
const selectedPixOrder = ref(null);

const openPixModal = (order) => {
    selectedPixOrder.value = order;
    isPixModalOpen.value = true;
};

const page = usePage();
const toast = useToast();

const authUser = computed(() => page.props.auth?.user || {});
const settings = computed(() => page.props.companySettings || {});

const localOrders = ref([...(page.props.initialOrders || [])]);

watch(() => page.props.initialOrders, (fresh) => {
    if (fresh) localOrders.value = [...fresh];
}, { deep: true });

const upsertOrder = (incomingOrder) => {
    if (!incomingOrder || incomingOrder.type !== 'delivery') return;

    const idx = localOrders.value.findIndex(o => Number(o.id) === Number(incomingOrder.id));
    if (idx > -1) {
        localOrders.value[idx] = { ...localOrders.value[idx], ...incomingOrder };
    } else {
        localOrders.value.unshift(incomingOrder);
    }

    if (incomingOrder.status === 'ready' && typeof navigator !== 'undefined' && navigator.vibrate) {
        navigator.vibrate([200, 100, 200]);
    }
};

onMounted(() => {
    hasTeleportTarget.value = typeof document !== 'undefined' && Boolean(document.getElementById('team-header-actions'));

    const echo = useTenantEcho();
    echo.listenChannel('operations', '.order.created', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.OrderCreated', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.order.updated', (e) => upsertOrder(e.order || e));
    echo.listenChannel('operations', '.OrderUpdated', (e) => upsertOrder(e.order || e));
});

// Pedidos atribuídos ao estafeta (Em Rota)
const myDeliveries = computed(() => {
    const userId = authUser.value.id;
    return localOrders.value.filter(o =>
        o.type === 'delivery' &&
        Number(o.driver_id) === Number(userId) &&
        o.status !== 'closed' &&
        o.status !== 'cancelled' && o.status !== 'delivered'
    );
});

// Pedidos prontos no balcão (Em Despacho)
const readyDeliveries = computed(() => {
    return localOrders.value.filter(o =>
        o.type === 'delivery' &&
        !o.driver_id &&
        o.status === 'ready'
    );
});

const tabOptions = computed(() => [
    { value: 'all', label: `Todos (${myDeliveries.value.length + readyDeliveries.value.length})` },
    { value: 'route', label: `Em Rota (${myDeliveries.value.length})`, icon: 'pi-truck' },
    { value: 'dispatch', label: `Em Despacho (${readyDeliveries.value.length})`, icon: 'pi-box' },
]);

const assignToMe = (orderId) => {
    router.post(route('operations.orders.assign-courier', orderId), { driver_id: authUser.value.id }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.add({
            severity: 'success',
            summary: 'Pedido Atribuído',
            detail: `Comanda #${orderId} adicionada à sua rota.`,
            life: 3000
        })
    });
};

const updateStatus = (orderId, status) => {
    router.post(route('operations.orders.update-status', orderId), { status: status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (status === 'delivered') {
                toast.add({ severity: 'success', summary: 'Entrega Concluída', detail: `Pedido #${orderId} finalizado com sucesso!`, life: 3000 });
            } else if (status === 'dispatched') {
                toast.add({ severity: 'info', summary: 'Em Rota', detail: `Pedido #${orderId} marcado como em rota de entrega.`, life: 3000 });
            }
        }
    });
};

const resolveDestinationString = (order) => {
    const addr = order.address_snapshot || order.address || order.delivery_address;
    if (typeof addr === 'object' && addr !== null) {
        const rawLoc = addr.location_link || addr.location;
        if (rawLoc) {
            const decoded = decodeURIComponent(String(rawLoc));
            const match = decoded.match(/(-?\d{1,2}\.\d+)\s*[,/]\s*(-?\d{1,3}\.\d+)/);
            if (match) return `${match[1]},${match[2]}`;
        }
        if (addr.coordinates && typeof addr.coordinates === 'object') {
            const lat = addr.coordinates.lat || addr.coordinates.latitude;
            const lng = addr.coordinates.lng || addr.coordinates.longitude;
            if (lat && lng) return `${lat},${lng}`;
        }
        return `${addr.street || ''}, ${addr.number || ''}, ${addr.neighborhood || ''}, ${settings.value.city || ''}`;
    }
    return String(order.address || '');
};

const openGoogleMaps = () => {
    if (myDeliveries.value.length === 0) return;

    const origin = `${settings.value.street || ''}, ${settings.value.number || ''}, ${settings.value.city || ''}`;
    const lastOrder = myDeliveries.value[myDeliveries.value.length - 1];
    const destination = resolveDestinationString(lastOrder);

    let url = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(origin)}&destination=${encodeURIComponent(destination)}`;

    if (myDeliveries.value.length > 1) {
        const waypoints = myDeliveries.value.slice(0, -1).map(o => resolveDestinationString(o));
        url += `&waypoints=optimize:true|${waypoints.map(w => encodeURIComponent(w)).join('|')}`;
    }

    window.open(url, '_blank');
};
</script>
```
