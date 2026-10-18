<template>
    <div class="flex flex-col h-full min-h-0 bg-surface-2 rounded-4xl overflow-hidden relative shadow-sm p-4">
        <!-- SELETOR DE ABA LATERAL -->
        <div class="flex justify-center mb-3 shrink-0">
            <SegmentedControl
                :model-value="activeSideTab"
                :options="sideTabOptions"
                @update:model-value="$emit('update:activeSideTab', $event)"
            />
        </div>

        <!-- ABA 1: POOL DE COLABORADORES (GRID DE 2) -->
        <div v-show="activeSideTab === 'team'" class="flex-1 min-h-0 flex flex-col">
            <span class="text-[10px] font-bold text-surface-muted uppercase tracking-wider mb-2 px-1 block">
                Arraste para os slots ou folgas
            </span>
            <div class="flex-1 min-h-0 overflow-y-auto hide-scrollbar">
                <VueDraggable
                    :list="employeePool"
                    :group="{ name: 'people', pull: 'clone', put: false }"
                    :clone="cloneEmployee"
                    item-key="id"
                    class="grid grid-cols-2 gap-2 pb-4"
                    @start="$emit('drag-start-pool', $event)"
                    @end="$emit('drag-end')"
                >
                    <template #item="{ element }">
                        <div class="p-2 bg-surface-1 border border-surface-border rounded-xl cursor-grab active:cursor-grabbing hover:border-primary-400 hover:shadow-xs transition-all flex flex-col justify-between gap-1.5 select-none">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <div class="w-6 h-6 rounded-full bg-surface-3 flex items-center justify-center font-black text-[9px] shrink-0 text-surface-muted">
                                    {{ element.name ? element.name.substring(0, 2).toUpperCase() : 'CO' }}
                                </div>
                                <span class="font-bold text-xs text-surface-text truncate" :title="element.name">
                                    {{ abbreviateName(element.name) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-surface-muted font-bold">
                                <span class="uppercase truncate text-[9px]">{{ element.employment_type || 'Geral' }}</span>
                                <span class="bg-surface-2 px-1.5 py-0.5 rounded text-[9px] shrink-0">
                                    {{ getEmployeeWeeklyWorkDays(element.id) }}/5
                                </span>
                            </div>
                        </div>
                    </template>
                </VueDraggable>
            </div>
        </div>

        <!-- ABA 2: CRUD E ORDENAÇÃO DE SLOTS -->
        <div v-show="activeSideTab === 'slots'" class="flex-1 min-h-0 flex flex-col">
            <!-- 1. SLOTS CADASTRADOS NO TOPO (ROLÁVEL) -->
            <div class="flex-1 min-h-0 overflow-y-auto hide-scrollbar pb-2">
                <VueDraggable
                    :list="slots"
                    group="slots-order"
                    item-key="id"
                    handle=".drag-handle"
                    class="flex flex-col gap-2"
                    @change="$emit('reorder-slots')"
                >
                    <template #item="{ element: slot, index: sIdx }">
                        <div class="p-2.5 bg-surface-1 border border-surface-border rounded-xl flex flex-col gap-2 transition-all">
                            <!-- VISUALIZAÇÃO PADRÃO -->
                            <div v-if="editingSlotId !== slot.id" class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 overflow-hidden">
                                        <i class="pi pi-bars text-surface-muted text-xs cursor-grab active:cursor-grabbing drag-handle shrink-0 hover:text-primary-500 transition-colors" title="Arraste para reordenar"></i>
                                        <div class="flex flex-col truncate">
                                            <span class="font-bold text-xs text-surface-text truncate">{{ slot.name }}</span>
                                            <span class="text-[10px] text-surface-muted font-bold">
                                                {{ formatSlotCapacityText(slot.capacity) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            @click="startEditing(slot)"
                                            class="w-6 h-6 rounded-lg text-surface-muted hover:text-primary-500 hover:bg-primary-500/10 flex items-center justify-center transition-colors border-none bg-transparent cursor-pointer"
                                            title="Editar Slot"
                                        >
                                            <i class="pi pi-pencil text-xs"></i>
                                        </button>
                                        <button
                                            type="button"
                                            @click="$emit('remove-slot', sIdx)"
                                            class="w-6 h-6 rounded-lg text-danger-500 hover:bg-danger-500/10 flex items-center justify-center transition-colors border-none bg-transparent cursor-pointer"
                                            title="Remover Slot"
                                        >
                                            <i class="pi pi-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- BADGES DAS ROLES PERMITIDAS -->
                                <div class="flex flex-wrap gap-1 pt-1 border-t border-surface-border/40">
                                    <template v-if="slot.allowed_roles && slot.allowed_roles.length > 0">
                                        <span
                                            v-for="rVal in slot.allowed_roles"
                                            :key="rVal"
                                            class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-surface-2 text-surface-text border border-surface-border"
                                        >
                                            {{ getRoleLabel(rVal) }}
                                        </span>
                                    </template>
                                    <span v-else class="text-[9px] font-bold text-surface-muted italic">
                                        Aceita qualquer função
                                    </span>
                                </div>
                            </div>

                            <!-- EDIÇÃO INLINE -->
                            <div v-else class="flex flex-col gap-2 animate-in fade-in duration-200">
                                <div class="flex gap-2">
                                    <input
                                        v-model="editName"
                                        type="text"
                                        class="flex-1 bg-surface-2 border border-primary-500/50 text-xs rounded-lg px-2 py-1 outline-none font-bold text-surface-text"
                                        @keyup.enter="saveEdit(slot.id)"
                                        @keyup.esc="cancelEditing"
                                    />
                                    <input
                                        v-model.number="editCapacity"
                                        type="number"
                                        min="1"
                                        max="30"
                                        class="w-14 bg-surface-2 border border-primary-500/50 text-xs rounded-lg px-1 py-1 outline-none font-bold text-center text-surface-text"
                                        @keyup.enter="saveEdit(slot.id)"
                                        @keyup.esc="cancelEditing"
                                    />
                                </div>

                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="role in availableRoles"
                                        :key="role.value"
                                        type="button"
                                        @click="toggleRoleInEdit(role.value)"
                                        class="px-2 py-0.5 rounded text-[9px] font-bold border transition-all cursor-pointer"
                                        :class="editAllowedRoles.includes(role.value)
                                            ? 'bg-primary-500 text-white border-primary-500'
                                            : 'bg-surface-2 text-surface-muted border-surface-border hover:text-surface-text'"
                                    >
                                        {{ role.label }}
                                    </button>
                                </div>

                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        @click="cancelEditing"
                                        class="px-2 py-0.5 text-[10px] font-bold rounded bg-surface-2 text-surface-muted hover:text-surface-text border border-surface-border cursor-pointer"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="button"
                                        @click="saveEdit(slot.id)"
                                        class="px-2.5 py-0.5 text-[10px] font-black rounded bg-success-500 text-white hover:bg-success-600 transition-colors cursor-pointer border-none"
                                    >
                                        Salvar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </VueDraggable>
            </div>

            <!-- 2. BASE DO PAINEL: BOTÃO / FORMULÁRIO COMPLETO -->
            <div class="shrink-0 pt-3 border-t border-surface-border/50">
                <!-- ESTADO RECOLHIDO: BOTÃO "+ ADICIONAR NOVO" -->
                <button
                    v-if="!isCreating"
                    type="button"
                    @click="openCreateForm"
                    class="w-full py-2.5 bg-surface-1 hover:bg-surface-3 border border-dashed border-surface-border hover:border-primary-500/50 text-surface-text rounded-2xl text-xs font-black transition-all flex items-center justify-center gap-2 cursor-pointer shadow-xs active:scale-[0.99]"
                >
                    <i class="pi pi-plus text-xs text-primary-500 font-bold"></i>
                    <span>Adicionar Novo</span>
                </button>

                <!-- ESTADO EXPANDIDO: FORMULÁRIO COMPLETO COM BOTÃO "SALVAR" -->
                <div v-else class="bg-surface-1 border border-surface-border p-3 rounded-2xl flex flex-col gap-2.5 shadow-sm animate-in fade-in duration-200">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-black uppercase tracking-wider text-surface-text">Novo Slot</span>
                        <button
                            type="button"
                            @click="cancelCreate"
                            class="text-surface-muted hover:text-danger-500 text-xs cursor-pointer border-none bg-transparent transition-colors p-1"
                            title="Fechar"
                        >
                            <i class="pi pi-times text-xs"></i>
                        </button>
                    </div>

                    <div class="flex gap-2">
                        <input
                            ref="newSlotInputRef"
                            v-model="newSlotName"
                            type="text"
                            placeholder="Nome (ex: Cozinha)"
                            class="flex-1 bg-surface-2 border border-surface-border text-xs rounded-lg px-2.5 py-1.5 outline-none font-medium text-surface-text focus:border-primary-500 transition-colors"
                            @keyup.enter="handleAddSlot"
                            @keyup.esc="cancelCreate"
                        />
                        <input
                            v-model.number="newSlotCapacity"
                            type="number"
                            min="1"
                            max="30"
                            placeholder="Vagas"
                            class="w-16 bg-surface-2 border border-surface-border text-xs rounded-lg px-2 py-1.5 outline-none font-bold text-center text-surface-text focus:border-primary-500 transition-colors"
                            @keyup.enter="handleAddSlot"
                            @keyup.esc="cancelCreate"
                        />
                    </div>

                    <!-- SELEÇÃO DE FUNÇÕES -->
                    <div class="flex flex-col gap-1">
                        <span class="text-[9px] font-bold text-surface-muted uppercase">Funções aceitas (vazio = todas):</span>
                        <div class="flex flex-wrap gap-1 max-h-24 overflow-y-auto hide-scrollbar">
                            <button
                                v-for="role in availableRoles"
                                :key="role.value"
                                type="button"
                                @click="toggleRoleInNew(role.value)"
                                class="px-2 py-0.5 rounded text-[9px] font-bold border transition-all cursor-pointer"
                                :class="newAllowedRoles.includes(role.value)
                                    ? 'bg-primary-500 text-white border-primary-500'
                                    : 'bg-surface-2 text-surface-muted border-surface-border hover:text-surface-text'"
                            >
                                {{ role.label }}
                            </button>
                        </div>
                    </div>

                    <!-- BOTÕES DE AÇÃO: CANCELAR E SALVAR -->
                    <div class="flex items-center gap-2 pt-1">
                        <button
                            type="button"
                            @click="cancelCreate"
                            class="flex-1 py-1.5 bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text rounded-lg text-xs font-bold transition-colors cursor-pointer border border-surface-border"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            @click="handleAddSlot"
                            class="flex-1 py-1.5 bg-success-500 hover:bg-success-600 text-white rounded-lg text-xs font-black transition-colors cursor-pointer border-none flex items-center justify-center gap-1.5 shadow-xs"
                        >
                            <i class="pi pi-check text-[10px]"></i>
                            <span>Salvar</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';
import VueDraggable from 'vuedraggable';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import { abbreviateName } from '@/Utils/formatters';

const props = defineProps({
    activeSideTab: { type: String, required: true },
    sideTabOptions: { type: Array, required: true },
    employeePool: { type: Array, required: true },
    slots: { type: Array, required: true },
    availableRoles: { type: Array, default: () => [] },
    cloneEmployee: { type: Function, required: true },
    getEmployeeWeeklyWorkDays: { type: Function, required: true },
    formatSlotCapacityText: { type: Function, required: true }
});

const emit = defineEmits([
    'update:activeSideTab',
    'add-slot',
    'update-slot',
    'remove-slot',
    'reorder-slots',
    'drag-start-pool',
    'drag-end'
]);

// Estado do formulário de criação
const isCreating = ref(false);
const newSlotInputRef = ref(null);
const newSlotName = ref('');
const newSlotCapacity = ref(2);
const newAllowedRoles = ref([]);

// Estado do formulário de edição inline
const editingSlotId = ref(null);
const editName = ref('');
const editCapacity = ref(1);
const editAllowedRoles = ref([]);

const getRoleLabel = (val) => {
    const found = props.availableRoles.find(r => r.value === val);
    return found ? found.label : val;
};

const openCreateForm = async () => {
    cancelEditing();
    isCreating.value = true;
    await nextTick();
    newSlotInputRef.value?.focus();
};

const cancelCreate = () => {
    isCreating.value = false;
    newSlotName.value = '';
    newSlotCapacity.value = 2;
    newAllowedRoles.value = [];
};

const toggleRoleInNew = (roleVal) => {
    if (newAllowedRoles.value.includes(roleVal)) {
        newAllowedRoles.value = newAllowedRoles.value.filter(r => r !== roleVal);
    } else {
        newAllowedRoles.value.push(roleVal);
    }
};

const handleAddSlot = () => {
    if (!newSlotName.value.trim()) return;
    emit('add-slot', {
        name: newSlotName.value,
        capacity: newSlotCapacity.value,
        allowed_roles: [...newAllowedRoles.value]
    });
    cancelCreate();
};

const startEditing = (slot) => {
    cancelCreate();
    editingSlotId.value = slot.id;
    editName.value = slot.name;
    editCapacity.value = slot.capacity;
    editAllowedRoles.value = slot.allowed_roles ? [...slot.allowed_roles] : [];
};

const cancelEditing = () => {
    editingSlotId.value = null;
    editName.value = '';
    editCapacity.value = 1;
    editAllowedRoles.value = [];
};

const toggleRoleInEdit = (roleVal) => {
    if (editAllowedRoles.value.includes(roleVal)) {
        editAllowedRoles.value = editAllowedRoles.value.filter(r => r !== roleVal);
    } else {
        editAllowedRoles.value.push(roleVal);
    }
};

const saveEdit = (slotId) => {
    if (!editName.value.trim()) return;
    emit('update-slot', {
        id: slotId,
        name: editName.value,
        capacity: editCapacity.value,
        allowed_roles: [...editAllowedRoles.value]
    });
    cancelEditing();
};
</script>
