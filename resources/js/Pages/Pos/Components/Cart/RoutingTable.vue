<template>
    <div class="flex flex-col gap-4 overflow-y-auto hide-scrollbar select-none">

        <!-- MESA SELECIONADA (MODO EDIÇÃO / SUB-CONTA) -->
        <div v-if="modelValue && mode !== 'direct'" class="flex flex-col gap-3 p-3 animate-in bg-surface-2 rounded-3xl fade-in slide-in-from-top-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 px-3 rounded-full text-white flex items-center justify-center font-black text-sm shrink-0 bg-primary-500 shadow-xs">
                        {{ getMergedDisplayName(modelValue) }}
                    </div>
                    <div class="flex flex-col">
                        <span class="font-medium text-sm text-surface-text">
                            Mesa {{ getMergedDisplayName(modelValue) }}
                        </span>
                        <span class="text-[10px] text-surface-muted uppercase tracking-widest">
                            {{ isMergedParent(modelValue) ? 'Mesas Agrupadas' : 'Consumo no local' }}
                        </span>
                    </div>
                </div>
                <button @click="clearTable" class="px-3 py-2 rounded-full bg-primary-500/40 text-xs font-medium hover:text-primary-500 whitespace-nowrap cursor-pointer border-none outline-none">
                    Trocar Mesa
                </button>
            </div>

            <div class="transition-all duration-300 ease-in-out bg-surface-3 rounded-2xl overflow-hidden border border-surface-border/20 focus-within:bg-surface-2 focus-within:shadow-lg focus-within:ring-2 focus-within:ring-primary-500/40">
                <div class="relative flex items-center">
                    <span class="absolute left-4">
                        <i class="pi fa-user-plus text-primary-500 text-sm"></i>
                    </span>
                    <input
                        :value="identifier"
                        @input="$emit('update:identifier', $event.target.value)"
                        type="text"
                        class="w-full pl-11 pr-28 py-3.5 bg-transparent border-none outline-none text-sm text-surface-text placeholder:text-surface-muted/50 font-medium"
                        placeholder="Nome do cliente (Opcional)..."
                        @keyup.enter="$emit('apply')"
                    />
                    <button
                        @click="$emit('apply')"
                        class="absolute right-1.5 h-9 px-4 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all outline-none cursor-pointer shadow-sm border-none"
                        :class="identifier ? 'bg-primary-500 text-white hover:bg-primary-600' : 'bg-surface-2 text-surface-muted border border-surface-border hover:bg-surface-1'"
                    >
                        {{ identifier ? 'Criar Conta' : 'Pular' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- LISTA DE MESAS POR ÁREA -->
        <template v-else>
            <!-- BARRA DO MODO DE JUNÇÃO -->
            <div class="flex items-center justify-between px-1">
                <span class="text-xs font-bold text-surface-muted uppercase tracking-wider">
                    {{ isMergeMode ? 'Selecione mesas para unir ou desfaça grupos' : '' }}
                </span>
                <button
                    type="button"
                    @click="toggleMergeMode"
                    class="px-3 py-1.5 rounded-full text-xs font-medium transition-all cursor-pointer border-none outline-none flex items-center gap-1.5 shadow-xs"
                    :class="isMergeMode
                        ? 'bg-red-500/20 text-red-500 hover:bg-red-500/30'
                        : 'bg-primary-500/20 text-primary-300 hover:bg-primary-500/30'"
                >
                    <i class="pi" :class="isMergeMode ? 'pi-times' : 'pi-link'"></i>
                    {{ isMergeMode ? 'Sair do Modo Junção' : 'Juntar Mesas' }}
                </button>
            </div>

            <div
                v-for="area in tableAreas"
                :key="area.id"
                class="flex flex-col gap-5"
                v-show="!expandedTableId || getVisibleTables(area).some(t => t.id === expandedTableId)"
            >
                <h3 v-if="!expandedTableId" class="text-sm font-medium text-surface-muted text-center">{{ area.name }}</h3>

                <div class="flex flex-wrap justify-center gap-3">
                    <template v-for="table in getVisibleTables(area)" :key="table.id">

                        <template v-if="!expandedTableId || expandedTableId === table.id">

                            <!-- BOTÃO DA MESA -->
                            <div
                                @click="handleTableClick(table)"
                                class="relative h-14 p-1 rounded-full transition-all cursor-pointer flex flex-col items-center justify-center text-center shadow-xs select-none border"
                                :class="[
                                    isMergedParent(table) ? 'min-w-28 w-auto px-4' : 'w-14',
                                    expandedTableId === table.id
                                        ? (isTableOccupied(table)
                                            ? 'bg-warning-500/30 border-warning-500/50 text-warning-400 scale-110 ring-2 ring-warning-500/40'
                                            : 'bg-primary-500/80 border-primary-500 text-white scale-110 ring-2 ring-primary-500/30')
                                        : (isTableOccupied(table)
                                            ? 'bg-warning-500/15 border-warning-500/30 text-warning-400 hover:bg-warning-500/25'
                                            : 'bg-primary-500/10 border-transparent text-primary-300 hover:bg-primary-500/25 hover:border-primary-500/30')
                                ]"
                            >
                                <div class="flex items-center justify-center gap-1">
                                    <span
                                        class="font-bold leading-none tracking-tight"
                                        :class="isMergedParent(table) ? 'text-base' : 'text-xl'"
                                    >
                                        {{ getMergedDisplayName(table) }}
                                    </span>
                                    <i v-if="isMergedParent(table)" class="pi pi-link text-[10px] ml-0.5 opacity-80"
                                       :class="isTableOccupied(table) ? 'text-warning-400' : 'text-primary-300'"></i>
                                </div>

                                <!-- BOTÃO RÁPIDO PARA DESMEMBRAR NO MODO JUNÇÃO -->
                                <button
                                    v-if="isMergeMode && isMergedParent(table)"
                                    type="button"
                                    @click.stop="handleSplitTable(table)"
                                    class="absolute -top-1.5 -left-1.5 w-6 h-6 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center border-2 border-surface-2 shadow-md z-20 transition-transform hover:scale-110 cursor-pointer"
                                    title="Desmembrar estas mesas"
                                >
                                    <i class="pi pi-unlink text-[10px] font-black"></i>
                                </button>

                                <!-- BADGE SELEÇÃO NO MODO JUNÇÃO -->
                                <div
                                    v-if="isMergeMode && selectedForMerge.includes(table.id)"
                                    class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-success-500 text-white flex items-center justify-center border-2 border-surface-2 shadow-sm z-10 animate-in zoom-in-50 duration-150"
                                    title="Selecionada para Junção"
                                >
                                    <i class="pi pi-check text-[9px] font-black"></i>
                                </div>

                                <!-- INDICADOR DE STATUS OCUPADA -->
                                <div
                                    v-else-if="!isMergeMode && isTableOccupied(table)"
                                    class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-warning-500 border-2 border-surface-2 shadow-sm animate-pulse"
                                    title="Mesa Ocupada"
                                ></div>
                            </div>

                            <!-- CARD EXPANDIDO: DETALHES E CONTAS DA MESA -->
                            <div v-if="expandedTableId === table.id" class="basis-full w-full my-1 p-3 bg-surface-2 border border-surface-border rounded-2xl flex flex-col gap-3 animate-in fade-in slide-in-from-top-2 shadow-inner">
                                <div class="flex items-center justify-between">
                                    <span class="micro-label">
                                        Mesa {{ getMergedDisplayName(table) }} • {{ isTableOccupied(table) ? `${getTableActiveOrders(table).length} conta(s) ativa(s)` : 'Livre' }}
                                    </span>

                                    <!-- Botão Separar se a mesa estiver agrupada -->
                                    <button
                                        v-if="isMergedParent(table)"
                                        type="button"
                                        @click.stop="handleSplitTable(table)"
                                        class="px-2.5 py-1 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white text-[10px] font-black uppercase tracking-wider border border-red-500/20 transition-all cursor-pointer flex items-center gap-1"
                                    >
                                        <i class="pi pi-unlink text-xs"></i> Separar Mesas
                                    </button>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <!-- Contas existentes -->
                                    <button v-for="order in getTableActiveOrders(table)" :key="order.id"
                                        @click="selectExistingOrder(table, order)"
                                        class="p-3 bg-surface-1 border border-surface-border hover:border-warning-500 hover:text-warning-400 rounded-xl text-sm font-medium text-surface-text text-left truncate transition-colors flex items-center gap-2 outline-none cursor-pointer">
                                        <i class="pi fa-receipt text-warning-500 text-xs"></i>
                                        {{ order.identifier ? `${getMergedDisplayName(table)} (${order.identifier})` : `Conta #${order.id}` }}
                                    </button>

                                    <!-- Abrir nova conta / pedido -->
                                    <button @click="createNewSubAccount(table)"
                                        class="p-3 bg-primary-500/10 text-primary-400 hover:bg-primary-500/20 rounded-xl text-sm font-black border border-transparent transition-colors flex items-center justify-center gap-2 shadow-sm outline-none cursor-pointer">
                                        <i class="pi pi-plus-circle text-sm"></i> {{ getTableActiveOrders(table).length > 0 ? 'Nova Conta Paralela' : 'Abrir Pedido na Mesa' }}
                                    </button>
                                </div>
                            </div>

                        </template>
                    </template>
                </div>
            </div>

            <!-- BLOCO DE CONFIRMAÇÃO DA JUNÇÃO NA BASE -->
            <div
                v-if="isMergeMode && selectedForMerge.length > 0"
                class="mt-4 p-3.5 bg-surface-2 border border-surface-border rounded-2xl flex items-center justify-between animate-in fade-in slide-in-from-bottom-2 shadow-lg shrink-0"
            >
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-surface-text">
                        {{ selectedForMerge.length }} mesa(s) selecionada(s)
                    </span>
                    <span class="text-[11px] text-surface-muted font-medium">
                        {{ selectedForMergeNames.join(' + ') }}
                    </span>
                </div>

                <button
                    type="button"
                    @click="confirmMergeTables"
                    :disabled="selectedForMerge.length < 2"
                    class="h-9 px-4 rounded-xl bg-success-500 hover:bg-success-600 disabled:opacity-40 disabled:cursor-not-allowed text-white font-black text-xs uppercase tracking-wider transition-all cursor-pointer border-none shadow-sm flex items-center gap-1.5"
                >
                    <i class="pi pi-check text-xs"></i>
                    Confirmar Junção
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    modelValue: Object,
    identifier: String,
    tableAreas: Array,
    resetTrigger: { type: Number, default: 0 },
    mode: { type: String, default: 'selector' }
});

const emit = defineEmits(["update:modelValue", "update:identifier", "apply", "load-order", "table-click"]);

const toast = useToast();
const expandedTableId = ref(null);

// Estados de Junção
const isMergeMode = ref(false);
const selectedForMerge = ref([]);

// Todas as mesas do salão em array plano
const allTables = computed(() => {
    return (props.tableAreas || []).flatMap(area => area.tables || []);
});

// Resolução de mesas secundárias vinculadas
const getLinkedChildren = (table) => {
    if (!table) return [];
    if (table.linked_tables && table.linked_tables.length > 0) {
        return table.linked_tables;
    }
    return allTables.value.filter(t => Number(t.parent_table_id) === Number(table.id));
};

const isMergedParent = (table) => {
    return getLinkedChildren(table).length > 0;
};

const isMergedChild = (table) => {
    return table.parent_table_id !== null && table.parent_table_id !== undefined && table.parent_table_id !== '';
};

const getVisibleTables = (area) => {
    if (!area?.tables) return [];
    return area.tables.filter(table => !isMergedChild(table));
};

const getMergedDisplayName = (table) => {
    if (!table) return '';
    const children = getLinkedChildren(table);
    if (children.length === 0) return table.name;

    const names = [table.name, ...children.map(c => c.name)].sort((a, b) =>
        String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' })
    );

    return names.join('+');
};

const getTableActiveOrders = (table) => {
    if (!table) return [];
    const orders = [...(table.active_orders || [])];

    const children = getLinkedChildren(table);
    children.forEach(child => {
        const fullChild = allTables.value.find(t => t.id === child.id) || child;
        if (Array.isArray(fullChild.active_orders)) {
            orders.push(...fullChild.active_orders);
        }
    });

    const seen = new Set();
    return orders.filter(o => {
        if (!o || !o.id || seen.has(o.id)) return false;
        seen.add(o.id);
        return true;
    });
};

const isTableOccupied = (table) => {
    if (!table) return false;
    if (table.status === 'occupied') return true;
    if (getTableActiveOrders(table).length > 0) return true;

    const children = getLinkedChildren(table);
    return children.some(c => {
        const fullChild = allTables.value.find(t => t.id === c.id) || c;
        return fullChild.status === 'occupied' || (fullChild.active_orders?.length > 0);
    });
};

const selectedForMergeNames = computed(() => {
    return selectedForMerge.value
        .map(id => allTables.value.find(t => t.id === id)?.name)
        .filter(Boolean)
        .sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' }));
});

watch(() => props.resetTrigger, () => {
    expandedTableId.value = null;
    isMergeMode.value = false;
    selectedForMerge.value = [];
});

const toggleMergeMode = () => {
    isMergeMode.value = !isMergeMode.value;
    selectedForMerge.value = [];
    expandedTableId.value = null;
};

const handleTableClick = (table) => {
    if (isMergeMode.value) {
        if (selectedForMerge.value.includes(table.id)) {
            selectedForMerge.value = selectedForMerge.value.filter(id => id !== table.id);
        } else {
            selectedForMerge.value.push(table.id);
        }
        return;
    }

    const occupied = isTableOccupied(table);
    const activeOrders = getTableActiveOrders(table);
    const existingOrder = occupied && activeOrders.length > 0 ? activeOrders[0] : null;
    const isMerged = isMergedParent(table);

    // Se estiver em modo direto (PDV / Cardápio de Salão)
    if (props.mode === 'direct') {
        // Se a mesa estiver ocupada com múltiplas contas OU se for agrupada, abre o seletor para gerenciar/separar
        if ((occupied && activeOrders.length > 1) || isMerged) {
            expandedTableId.value = expandedTableId.value === table.id ? null : table.id;
            return;
        }
        emit('table-click', table, occupied, existingOrder);
        return;
    }

    // Modo seletor comum: se estiver ocupada OU for agrupada, expande para ver opções
    if (occupied || isMerged) {
        expandedTableId.value = expandedTableId.value === table.id ? null : table.id;
    } else {
        expandedTableId.value = null;
        emit('update:modelValue', table);
        emit('update:identifier', "");
        emit('apply');
    }
};

const confirmMergeTables = async () => {
    if (selectedForMerge.value.length < 2) return;

    try {
        const sortedSelectedTables = [...selectedForMerge.value]
            .map(id => allTables.value.find(t => t.id === id))
            .filter(Boolean)
            .sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { numeric: true, sensitivity: 'base' }));

        const primaryTable = sortedSelectedTables[0];
        const secondaryTableIds = sortedSelectedTables.slice(1).map(t => t.id);

        await axios.post(route('operations.tables.join', primaryTable.id), {
            secondary_table_ids: secondaryTableIds
        });

        toast.add({
            severity: 'success',
            summary: 'Mesas Unidas',
            detail: `As mesas ${selectedForMergeNames.value.join(' + ')} foram agrupadas no lugar da Mesa ${primaryTable.name}.`,
            life: 3000
        });

        isMergeMode.value = false;
        selectedForMerge.value = [];
        router.reload({ only: ['initialTableAreas', 'tableAreas'], preserveState: true, preserveScroll: true });

    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Falha na Junção',
            detail: error.response?.data?.message || 'Não foi possível juntar as mesas.',
            life: 4000
        });
    }
};

const handleSplitTable = async (table) => {
    try {
        await axios.post(route('operations.tables.split', table.id));

        toast.add({
            severity: 'info',
            summary: 'Mesas Separadas',
            detail: `A Mesa ${getMergedDisplayName(table)} foi desmembrada com sucesso.`,
            life: 3000
        });

        expandedTableId.value = null;
        router.reload({ only: ['initialTableAreas', 'tableAreas'], preserveState: true, preserveScroll: true });

    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Atenção',
            detail: error.response?.data?.message || 'Não foi possível separar a mesa.',
            life: 4000
        });
    }
};

const selectExistingOrder = (table, order) => {
    expandedTableId.value = null;
    emit('update:modelValue', table);
    emit('update:identifier', order.identifier || order.customer_name || "");
    emit('load-order', order.id);
    emit('apply');
};

const createNewSubAccount = (table) => {
    expandedTableId.value = null;

    if (props.mode === 'direct') {
        emit('table-click', table, false, null);
    } else {
        emit('update:modelValue', table);
        emit('update:identifier', "");
        emit('apply');
    }
};

const clearTable = () => {
    expandedTableId.value = null;
    emit('update:modelValue', null);
    emit('update:identifier', "");
};
</script>
