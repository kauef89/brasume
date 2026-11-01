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
                    {{ activeLanchesCount }} / {{ attendees.length }} Atendidos
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
                    hasAnyItems(attendee.id)
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
                            {{ $formatCurrency(getCombinedTotal(attendee.id)) }}
                        </span>

                        <span
                            class="text-[10px] font-bold uppercase tracking-wider"
                            :class="isSlotExceeded(attendee.id) ? (allowTab ? 'text-amber-500' : 'text-danger-500') : 'text-success-smart'"
                        >
                            {{ getSlotStatusText(attendee.id) }}
                        </span>
                    </div>
                </div>

                <!-- ITENS DO SLOT -->
                <div v-if="hasAnyItems(attendee.id)" class="p-4 space-y-2.5 bg-surface-2/40">

                    <!-- 1. ITENS JÁ ENVIADOS / CONFIRMADOS NA COZINHA -->
                    <div
                        v-for="(cItem, cIdx) in getConsumedItems(attendee.id)"
                        :key="'consumed_' + cIdx"
                        class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-surface-1/90 border border-success-500/30 text-xs shadow-xs"
                    >
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="font-bold text-surface-text flex items-center gap-1.5 truncate">
                                <span>{{ cItem.qty }}x {{ cItem.name }}</span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded-md font-black uppercase tracking-wider shrink-0 bg-success-500/15 text-success-600 dark:text-success-400 border border-success-500/20 flex items-center gap-1">
                                    <i class="pi pi-check text-[8px]"></i> Enviado
                                </span>
                                <span
                                    v-if="cItem.is_takeaway"
                                    class="text-[9px] px-1.5 py-0.5 rounded-md font-black uppercase tracking-wider shrink-0 bg-amber-500/10 text-amber-500"
                                >
                                    P/ Levar
                                </span>
                            </span>

                            <span v-if="cItem.modifiersText" class="text-[10px] text-surface-muted truncate mt-0.5">
                                {{ cItem.modifiersText }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-black text-surface-text">
                                {{ $formatCurrency(cItem.totalPrice) }}
                            </span>
                            <div
                                class="w-7 h-7 rounded-lg bg-success-500/10 text-success-600 dark:text-success-400 flex items-center justify-center font-bold"
                                title="Pedido já impresso e em preparo"
                            >
                                <i class="pi pi-check-circle text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <!-- 2. NOVOS ITENS AGUARDANDO CONFIRMAÇÃO -->
                    <div
                        v-for="(nItem, nIdx) in getNewItems(attendee.id)"
                        :key="'new_' + nIdx"
                        class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-surface-1 border border-primary-500/40 text-xs shadow-xs"
                    >
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="font-bold text-surface-text flex items-center gap-1.5 truncate">
                                <span>{{ nItem.qty }}x {{ nItem.name }}</span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded-md font-black uppercase tracking-wider shrink-0 bg-primary-500/15 text-primary-600 dark:text-primary-400 border border-primary-500/20">
                                    Novo
                                </span>
                                <span
                                    v-if="nItem.is_takeaway"
                                    class="text-[9px] px-1.5 py-0.5 rounded-md font-black uppercase tracking-wider shrink-0 bg-amber-500/10 text-amber-500"
                                >
                                    P/ Levar
                                </span>
                            </span>

                            <span v-if="nItem.modifiersText" class="text-[10px] text-surface-muted truncate mt-0.5">
                                {{ nItem.modifiersText }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-black text-surface-text">
                                {{ $formatCurrency(nItem.totalPrice) }}
                            </span>
                            <button
                                type="button"
                                @click="removeItemFromSlot(attendee.id, nIdx)"
                                class="w-7 h-7 rounded-lg bg-danger-500/10 hover:bg-danger-500 hover:text-white text-danger-500 flex items-center justify-center transition-colors cursor-pointer border-none outline-none"
                                title="Remover item pendente"
                            >
                                <i class="pi pi-trash text-xs"></i>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- BOTÃO DE AÇÃO DO SLOT -->
                <div class="p-3 bg-surface-1 flex items-center justify-between">
                    <span class="text-xs text-surface-muted pl-1">
                        {{ getSlotItemSummary(attendee.id) }}
                    </span>

                    <button
                        type="button"
                        @click="openProductPicker(attendee)"
                        class="h-8 px-3 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all outline-none cursor-pointer border select-none"
                        :class="hasAnyItems(attendee.id)
                            ? 'bg-surface-2 hover:bg-surface-3 text-surface-text border-surface-border'
                            : 'bg-primary-500/10 hover:bg-primary-500/20 text-primary-500 border-primary-500/30'"
                    >
                        <i class="pi pi-plus text-xs"></i>
                        <span>{{ hasAnyItems(attendee.id) ? 'Adicionar Item' : 'Escolher Lanche' }}</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- RODAPÉ COM RESUMO E SLIDETOCONFIRM -->
        <footer class="p-4 md:p-6 bg-surface-1 border-t border-surface-border shrink-0 flex flex-col gap-3">
            <div class="flex items-center justify-between text-xs font-bold text-surface-muted px-1">
                <span>Novos Pedidos a Enviar:</span>
                <span class="text-base font-black text-surface-text">{{ $formatCurrency(newItemsTotal) }}</span>
            </div>

            <div v-if="totalNewExcess > 0 && allowTab" class="text-[11px] font-bold text-amber-500 bg-amber-500/10 border border-amber-500/20 p-2.5 rounded-xl flex items-center gap-2">
                <i class="pi pi-info-circle shrink-0"></i>
                <span>Excedente de {{ $formatCurrency(totalNewExcess) }} será debitado na folha dos colaboradores.</span>
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
                            {{ $formatCurrency(prod.price ?? prod.dine_in_price ?? 0) }}
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
import { ref, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { formatCurrency } from '@/Utils/formatters';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';

const props = defineProps({
    attendees: { type: Array, default: () => [] },
    catalog: { type: Array, default: () => [] },
    companySettings: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['back', 'open-modifiers']);

const page = usePage();
const shiftId = computed(() => page.props.currentShift?.id || null);

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

// 1. Persistência em LocalStorage por Turno
const localStorageKey = computed(() => `team_meal_draft_shift_${shiftId.value || 'active'}`);

onMounted(() => {
    try {
        const saved = localStorage.getItem(localStorageKey.value);
        if (saved) {
            slotMap.value = JSON.parse(saved) || {};
        }
    } catch (e) {
        console.error('Erro ao restaurar rascunho de lanches:', e);
    }
});

watch(slotMap, (newMap) => {
    try {
        localStorage.setItem(localStorageKey.value, JSON.stringify(newMap));
    } catch (e) {
        console.error('Erro ao salvar rascunho de lanches:', e);
    }
}, { deep: true });

// 2. Resolução de Itens
const getAttendee = (attendeeId) => props.attendees.find(a => a.id === attendeeId);
const getConsumedItems = (attendeeId) => getAttendee(attendeeId)?.consumed_items || [];
const getNewItems = (attendeeId) => slotMap.value[attendeeId] || [];
const hasAnyItems = (attendeeId) => getConsumedItems(attendeeId).length > 0 || getNewItems(attendeeId).length > 0;

const getSlotItemSummary = (attendeeId) => {
    const cCount = getConsumedItems(attendeeId).length;
    const nCount = getNewItems(attendeeId).length;
    if (cCount === 0 && nCount === 0) return 'Nenhum item escolhido';
    if (cCount > 0 && nCount === 0) return `${cCount} item(ns) já enviados`;
    if (cCount === 0 && nCount > 0) return `${nCount} item(ns) pendente(s)`;
    return `${cCount} enviado(s) + ${nCount} novo(s)`;
};

const getAttendeeCap = (attendeeId) => {
    const attendee = getAttendee(attendeeId);
    if (props.companySettings?.allowance_scope === 'individual' && attendee?.custom_allowance > 0) {
        return Number(attendee.custom_allowance);
    }
    return globalCap.value;
};

const getConsumedTotal = (attendeeId) => {
    return Number(getAttendee(attendeeId)?.consumed_total || 0);
};

const getNewItemsTotal = (attendeeId) => {
    return getNewItems(attendeeId).reduce((acc, i) => acc + Number(i.totalPrice || 0), 0);
};

const getCombinedTotal = (attendeeId) => {
    return getConsumedTotal(attendeeId) + getNewItemsTotal(attendeeId);
};

const isSlotExceeded = (attendeeId) => {
    const cap = getAttendeeCap(attendeeId);
    if (cap <= 0) return false;
    return getCombinedTotal(attendeeId) > cap;
};

const getSlotStatusText = (attendeeId) => {
    const cap = getAttendeeCap(attendeeId);
    const combined = getCombinedTotal(attendeeId);
    if (cap <= 0) return 'Coberto';
    if (combined === 0) return `Saldo: ${formatCurrency(cap)}`;
    if (combined <= cap) return `100% Coberto (Resta ${formatCurrency(cap - combined)})`;
    const excess = combined - cap;
    return allowTab.value
        ? `Excedente: ${formatCurrency(excess)} (Folha)`
        : `Excedeu teto em ${formatCurrency(excess)}`;
};

const removeItemFromSlot = (attendeeId, index) => {
    if (slotMap.value[attendeeId]) {
        slotMap.value[attendeeId].splice(index, 1);
        if (slotMap.value[attendeeId].length === 0) {
            delete slotMap.value[attendeeId];
        }
    }
};

const activeLanchesCount = computed(() => {
    return props.attendees.filter(a => hasAnyItems(a.id)).length;
});

const attendeesWithNewItems = computed(() => {
    return props.attendees.filter(a => getNewItems(a.id).length > 0);
});

const newItemsTotal = computed(() => {
    return props.attendees.reduce((acc, a) => acc + getNewItemsTotal(a.id), 0);
});

const totalNewExcess = computed(() => {
    return props.attendees.reduce((acc, a) => {
        const cap = getAttendeeCap(a.id);
        const combined = getCombinedTotal(a.id);
        return acc + (cap > 0 && combined > cap ? combined - cap : 0);
    }, 0);
});

const hasDisallowedExcess = computed(() => {
    if (allowTab.value) return false;
    return props.attendees.some(a => isSlotExceeded(a.id));
});

const canConfirm = computed(() => {
    return attendeesWithNewItems.value.length > 0 && !hasDisallowedExcess.value;
});

const confirmButtonText = computed(() => {
    if (hasDisallowedExcess.value) return 'Teto Excedido (Ajuste os itens)';
    if (attendeesWithNewItems.value.length === 0) return 'Escolha um lanche para enviar';
    return `Deslize para Enviar à Cozinha (${attendeesWithNewItems.value.length})`;
});

// FILTRAGEM DE PRODUTOS PERMITIDOS
const filteredCatalog = computed(() => {
    const allowedCats = props.companySettings?.meal_allowance_categories || [];
    const allowedProds = props.companySettings?.meal_allowance_products || [];

    let list = props.catalog.filter(p => p.status === 'available');

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

    // 🚀 Garante que o configurador receba o preço preenchido
    const resolvedProduct = {
        ...product,
        price: Number(product.price ?? product.dine_in_price ?? 0)
    };

    emit('open-modifiers', {
        product: resolvedProduct,
        attendee: activeAttendee.value
    });
};

const addItemToActiveAttendee = (attendeeId, itemPayload) => {
    if (!slotMap.value[attendeeId]) {
        slotMap.value[attendeeId] = [];
    }
    slotMap.value[attendeeId].push(itemPayload);
};

defineExpose({
    addItemToActiveAttendee
});

// DISPARO APENAS DOS NOVOS ITENS
const submitMealOrder = () => {
    if (!canConfirm.value) return;

    const slotsPayload = [];
    attendeesWithNewItems.value.forEach(a => {
        const items = getNewItems(a.id);
        if (items.length > 0) {
            slotsPayload.push({
                attendee_id: a.id,
                items: items.map(i => ({
                    product_id: i.product_id || i.id,
                    qty: i.qty,
                    price: i.price,
                    extra_price: i.extraPrice || 0,
                    is_takeaway: Boolean(i.is_takeaway),
                    notes: i.notes || null,
                    modifiers: i.structuredModifiers || []
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

            // Limpa o rascunho persistido
            try {
                localStorage.removeItem(localStorageKey.value);
            } catch (e) {}
            slotMap.value = {};

            setTimeout(() => {
                isSuccess.value = false;
            }, 1200);
        },
        onError: (errors) => {
            console.error('❌ Falha na requisição de lanche:', errors);
            isSubmitting.value = false;
        }
    });
};
</script>
