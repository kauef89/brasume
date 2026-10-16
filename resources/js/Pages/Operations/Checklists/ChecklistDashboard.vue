<template>
    <div class="flex flex-col h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans p-2 relative">

        <!-- TELEPORT PARA DYNAMIC ISLAND: ABAS & BOTÃO NOVO GRUPO -->
        <Teleport to="#dynamic-island-portal" v-if="isMounted && isActive">
            <div class="flex items-center gap-2 h-10 md:h-12 shrink-0 mr-2">
                <SegmentedControl
                    v-model="activeView"
                    :options="viewOptions"
                    class="!mx-0"
                />

                <button
                    v-if="activeView === 'templates'"
                    type="button"
                    @click="openChecklistModal()"
                    class="h-9 px-3.5 rounded-full bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider flex items-center gap-1.5 border-none cursor-pointer shadow-sm transition-all"
                >
                    <i class="pi pi-plus text-[10px]"></i>
                    <span>Nova Rotina</span>
                </button>
            </div>
        </Teleport>

        <div class="flex-1 flex flex-col h-full bg-surface-2 rounded-4xl border border-surface-border overflow-hidden min-h-0">

            <!-- ========================================================= -->
            <!-- VISÃO 1: DASHBOARD OPERACIONAL DO TURNO EM ANDAMENTO      -->
            <!-- ========================================================= -->
            <div v-if="activeView === 'monitoring'" class="flex-1 flex flex-col p-4 md:p-6 overflow-y-auto hide-scrollbar gap-6">

                <!-- CARDS DE MÉTRICAS (KPIs) -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 shrink-0">
                    <div class="bg-surface-1 p-4 rounded-2xl border border-surface-border flex flex-col justify-between">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Completude do Turno</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-3xl font-black text-surface-text font-mono">{{ overview.completion_percentage }}%</span>
                            <i class="pi pi-chart-pie text-primary-500 text-lg"></i>
                        </div>
                    </div>

                    <div class="bg-surface-1 p-4 rounded-2xl border border-surface-border flex flex-col justify-between">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Total de Tarefas</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-3xl font-black text-surface-text font-mono">{{ overview.total_tasks }}</span>
                            <i class="pi pi-list text-surface-muted text-lg"></i>
                        </div>
                    </div>

                    <div class="bg-surface-1 p-4 rounded-2xl border border-surface-border flex flex-col justify-between">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Concluídas</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-3xl font-black text-emerald-500 font-mono">{{ overview.completed_tasks }}</span>
                            <i class="pi pi-check-circle text-emerald-500 text-lg"></i>
                        </div>
                    </div>

                    <div class="bg-surface-1 p-4 rounded-2xl border border-surface-border flex flex-col justify-between" :class="{'border-amber-500/40 bg-amber-500/5': overview.issues_count > 0}">
                        <span class="text-[10px] font-black uppercase tracking-widest" :class="overview.issues_count > 0 ? 'text-amber-500' : 'text-surface-muted'">Problemas Reportados</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-3xl font-black font-mono" :class="overview.issues_count > 0 ? 'text-amber-500' : 'text-surface-text'">{{ overview.issues_count }}</span>
                            <i class="pi pi-exclamation-triangle text-lg" :class="overview.issues_count > 0 ? 'text-amber-500' : 'text-surface-muted'"></i>
                        </div>
                    </div>
                </div>

                <!-- ALERTA DE PROBLEMAS REGISTRADOS -->
                <div v-if="overview.issues && overview.issues.length > 0" class="flex flex-col gap-2">
                    <span class="text-[11px] font-black uppercase tracking-widest text-amber-500 flex items-center gap-1.5">
                        <i class="pi pi-bell text-xs"></i> Ocorrências Operacionais Não Resolvidas
                    </span>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div v-for="issue in overview.issues" :key="issue.id" class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 flex flex-col gap-1">
                            <div class="flex justify-between items-center text-xs font-black text-surface-text">
                                <span>{{ issue.task_name }}</span>
                                <span class="text-[10px] text-surface-muted font-mono">{{ issue.time }} • {{ issue.reported_by }}</span>
                            </div>
                            <p class="text-xs text-amber-800 dark:text-amber-300">{{ issue.notes || 'Sem observações adicionais.' }}</p>
                        </div>
                    </div>
                </div>

                <!-- LISTAGEM COMPACTA DE GRUPOS E TAREFAS -->
                <div class="flex flex-col gap-4">
                    <span class="text-[11px] font-black uppercase tracking-widest text-surface-muted">Rotinas Cadastradas</span>

                    <div v-for="checklist in checklists" :key="checklist.id" class="p-4 rounded-2xl bg-surface-1 border border-surface-border flex flex-col gap-3">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" :class="checklist.is_active ? 'bg-emerald-500' : 'bg-surface-border'"></span>
                                <h3 class="font-black text-sm text-surface-text">{{ checklist.title }}</h3>
                            </div>
                            <span class="text-xs font-bold text-surface-muted">{{ checklist.tasks?.length || 0 }} tarefas vinculadas</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                            <div v-for="task in checklist.tasks" :key="task.id" class="p-2.5 rounded-xl bg-surface-2 border border-surface-border/60 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2 pr-2 min-w-0">
                                    <i v-if="task.is_inventory_task" class="pi pi-box text-[9px] text-amber-500 shrink-0" title="Auditoria de Estoque"></i>
                                    <span class="font-bold text-surface-text truncate">{{ task.description }}</span>
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded font-black uppercase shrink-0 bg-surface-3 text-surface-muted">
                                    {{ task.recurrence_type }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- VISÃO 2: GERENCIAMENTO E CONFIGURAÇÃO (CRUD)              -->
            <!-- ========================================================= -->
            <div v-else class="flex-1 flex flex-col p-4 md:p-6 overflow-y-auto hide-scrollbar gap-5">
                <div v-for="checklist in checklists" :key="checklist.id" class="p-5 rounded-3xl bg-surface-1 border border-surface-border flex flex-col gap-4">

                    <!-- Cabeçalho da Rotina -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-surface-border/60 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-primary-500/10 text-primary-500 flex items-center justify-center font-black">
                                <i class="pi pi-folder text-sm"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-base text-surface-text leading-tight">{{ checklist.title }}</h3>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-surface-muted">
                                    {{ checklist.is_active ? 'Ativo na Operação' : 'Desativado' }}
                                </span>
                            </div>
                        </div>

                        <!-- Ações do Grupo -->
                        <div class="flex items-center gap-1.5 self-end sm:self-auto">
                            <button type="button" @click="openTaskModal(checklist)" class="h-8 px-3 rounded-lg bg-surface-2 hover:bg-surface-3 text-surface-text text-xs font-bold border border-surface-border flex items-center gap-1 cursor-pointer">
                                <i class="pi pi-plus text-[9px]"></i> Tarefa
                            </button>
                            <button type="button" @click="openChecklistModal(checklist)" class="w-8 h-8 rounded-lg bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text flex items-center justify-center border border-surface-border cursor-pointer">
                                <i class="pi pi-pencil text-xs"></i>
                            </button>
                            <button type="button" @click="deleteChecklist(checklist)" class="w-8 h-8 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-500 flex items-center justify-center border border-red-500/20 cursor-pointer">
                                <i class="pi pi-trash text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Lista de Tarefas do Grupo -->
                    <div v-if="checklist.tasks && checklist.tasks.length > 0" class="flex flex-col gap-2">
                        <div v-for="task in checklist.tasks" :key="task.id" class="p-3 rounded-xl bg-surface-2 border border-surface-border/50 flex items-center justify-between gap-3">
                            <div class="flex flex-col min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <i v-if="task.is_inventory_task" class="pi pi-box text-[9px] text-amber-500 shrink-0" title="Auditoria de Estoque vinculada"></i>
                                    <span class="text-xs font-bold text-surface-text truncate">{{ task.description }}</span>
                                </div>
                                <span v-if="task.instructions" class="text-[11px] text-surface-muted truncate mt-0.5">{{ task.instructions }}</span>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] px-2 py-0.5 rounded font-black uppercase bg-surface-1 text-surface-muted border border-surface-border/50">
                                    {{ task.assignment_type === 'all' ? 'Todos' : task.assignment_type }}
                                </span>
                                <button type="button" @click="openTaskModal(checklist, task)" class="w-7 h-7 rounded-lg bg-surface-1 hover:bg-surface-3 text-surface-muted hover:text-surface-text flex items-center justify-center border border-surface-border cursor-pointer">
                                    <i class="pi pi-pencil text-[10px]"></i>
                                </button>
                                <button type="button" @click="deleteTask(task)" class="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-500 flex items-center justify-center border border-red-500/20 cursor-pointer">
                                    <i class="pi pi-trash text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-4 text-xs font-medium text-surface-muted opacity-60">
                        Nenhuma tarefa cadastrada nesta rotina.
                    </div>

                </div>
            </div>

        </div>

        <!-- ========================================================= -->
        <!-- MODAL: CRIAR / EDITAR GRUPO (CHECKLIST)                   -->
        <!-- ========================================================= -->
        <Dialog v-model:visible="isChecklistModalOpen" modal :header="editingChecklist ? 'Editar Rotina' : 'Nova Rotina'" class="w-[420px]" :draggable="false">
            <div class="flex flex-col gap-4 pt-2">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-surface-muted">Título da Rotina</label>
                    <input v-model="checklistForm.title" type="text" placeholder="Ex: Abertura da Cozinha" class="h-10 px-3 rounded-xl bg-surface-1 border border-surface-border text-xs font-bold text-surface-text outline-none focus:border-primary-500" />
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="chk_active" v-model="checklistForm.is_active" class="rounded border-surface-border" />
                    <label for="chk_active" class="text-xs font-bold text-surface-text cursor-pointer">Rotina Ativa na Operação</label>
                </div>
            </div>
            <template #footer>
                <button type="button" @click="isChecklistModalOpen = false" class="h-10 px-4 rounded-xl text-xs font-bold text-surface-muted hover:bg-surface-2 border-none cursor-pointer">Cancelar</button>
                <button type="button" @click="saveChecklist" class="h-10 px-5 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase border-none cursor-pointer">Salvar</button>
            </template>
        </Dialog>

        <!-- ========================================================= -->
        <!-- MODAL: CRIAR / EDITAR TAREFA                              -->
        <!-- ========================================================= -->
        <Dialog v-model:visible="isTaskModalOpen" modal :header="editingTask ? 'Editar Tarefa' : 'Nova Tarefa'" class="w-[500px]" :draggable="false">
            <div class="flex flex-col gap-3.5 pt-2 max-h-[70vh] overflow-y-auto hide-scrollbar">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold text-surface-muted">Descrição da Atividade</label>
                    <input v-model="taskForm.description" type="text" placeholder="Ex: Sanitizar bancada e ligar fritadeiras" class="h-10 px-3 rounded-xl bg-surface-1 border border-surface-border text-xs font-bold text-surface-text outline-none focus:border-primary-500" />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold text-surface-muted">Instruções Detalhadas (Opcional)</label>
                    <textarea v-model="taskForm.instructions" rows="2" placeholder="Ex: Utilizar sanitizante na proporção 1:5..." class="p-3 rounded-xl bg-surface-1 border border-surface-border text-xs font-medium text-surface-text outline-none resize-none focus:border-primary-500"></textarea>
                </div>

                <!-- 🚀 VÍNCULO COM ESTOQUE -->
                <div class="p-3 rounded-2xl bg-surface-2 border border-surface-border flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-box text-amber-500 text-sm"></i>
                            <span class="text-xs font-black text-surface-text">Vincular Auditoria de Estoque</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="taskForm.is_inventory_task" class="sr-only peer">
                            <div class="w-9 h-5 bg-surface-border peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    <!-- ÁREA EXPANSÍVEL QUANDO ATIVO -->
                    <div v-if="taskForm.is_inventory_task" class="flex flex-col gap-2.5 pt-2 border-t border-surface-border/50 animate-in fade-in duration-200">
                        <label class="text-[10px] font-black uppercase tracking-wider text-surface-muted">Tipo de Item</label>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="taskForm.inventory_item_type = 'App\\Models\\Product'; clearInventorySelection();"
                                class="h-9 px-3 rounded-xl text-xs font-black border transition-all cursor-pointer outline-none flex items-center justify-center gap-1.5"
                                :class="taskForm.inventory_item_type === 'App\\Models\\Product'
                                    ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
                                    : 'bg-surface-1 border-surface-border text-surface-muted hover:text-surface-text'"
                            >
                                <i class="pi pi-tag text-xs"></i> Produto / Insumo
                            </button>
                            <button
                                type="button"
                                @click="taskForm.inventory_item_type = 'App\\Models\\Supply'; clearInventorySelection();"
                                class="h-9 px-3 rounded-xl text-xs font-black border transition-all cursor-pointer outline-none flex items-center justify-center gap-1.5"
                                :class="taskForm.inventory_item_type === 'App\\Models\\Supply'
                                    ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
                                    : 'bg-surface-1 border-surface-border text-surface-muted hover:text-surface-text'"
                            >
                                <i class="pi pi-box text-xs"></i> Suprimento
                            </button>
                        </div>

                        <!-- SEARCH AUTOCOMPLETE -->
                        <div class="flex flex-col gap-1 mt-1 relative">
                            <label class="text-[10px] font-black uppercase tracking-wider text-surface-muted">Item Específico</label>

                            <div v-if="taskForm.inventory_item_id" class="flex items-center justify-between p-2.5 rounded-xl bg-surface-1 border border-amber-500/40">
                                <div class="flex items-center gap-2 truncate">
                                    <i class="pi pi-check-circle text-amber-500 text-xs"></i>
                                    <span class="text-xs font-bold text-surface-text truncate">{{ taskForm.inventory_item_name }}</span>
                                </div>
                                <button type="button" @click="clearInventorySelection" class="text-xs text-danger-500 hover:text-danger-600 border-none bg-transparent cursor-pointer p-1">
                                    <i class="pi pi-times"></i>
                                </button>
                            </div>

                            <SearchAutocomplete
                                v-else
                                :model-value="taskForm.inventory_item_name"
                                :suggestions="inventorySuggestions"
                                :placeholder="taskForm.inventory_item_type === 'App\\Models\\Supply' ? 'Buscar suprimento...' : 'Buscar produto ou insumo...'"
                                field="name"
                                @update:model-value="handleSearchInventory"
                                @select="handleSelectInventoryItem"
                            />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold text-surface-muted">Quem Faz?</label>
                        <select v-model="taskForm.assignment_type" class="h-10 px-3 rounded-xl bg-surface-1 border border-surface-border text-xs font-bold outline-none">
                            <option value="all">Todos</option>
                            <option value="roles">Cargos Específicos</option>
                            <option value="users">Pessoas Específicas</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold text-surface-muted">Recorrência</label>
                        <select v-model="taskForm.recurrence_type" class="h-10 px-3 rounded-xl bg-surface-1 border border-surface-border text-xs font-bold outline-none">
                            <option value="daily">Diária</option>
                            <option value="weekly">Semanal</option>
                            <option value="monthly">Mensal</option>
                            <option value="once">Pontual (Única Vez)</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold text-surface-muted">Momento do Disparo</label>
                    <select v-model="taskForm.trigger_type" class="h-10 px-3 rounded-xl bg-surface-1 border border-surface-border text-xs font-bold outline-none">
                        <option value="manual">Livre (O dia todo)</option>
                        <option value="shift_start">Abertura do Turno</option>
                        <option value="shift_end">Fechamento do Turno</option>
                        <option value="scheduled">Horário Agendado</option>
                    </select>
                </div>
            </div>
            <template #footer>
                <button type="button" @click="isTaskModalOpen = false" class="h-10 px-4 rounded-xl text-xs font-bold text-surface-muted hover:bg-surface-2 border-none cursor-pointer">Cancelar</button>
                <button type="button" @click="saveTask" class="h-10 px-5 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase border-none cursor-pointer">Salvar Tarefa</button>
            </template>
        </Dialog>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import SearchAutocomplete from '@/Components/UI/SearchAutocomplete.vue';

const props = defineProps({
    isActive: { type: Boolean, default: false }
});

const toast = useToast();
const isMounted = ref(false);
const activeView = ref('monitoring');

const viewOptions = [
    { label: 'Acompanhamento', value: 'monitoring', icon: 'pi-chart-bar' },
    { label: 'Gerenciar Rotinas', value: 'templates', icon: 'pi-sliders-h' }
];

const checklists = ref([]);
const overview = ref({
    completion_percentage: 0,
    total_tasks: 0,
    completed_tasks: 0,
    issues_count: 0,
    issues: []
});

const loadManagementData = async () => {
    try {
        const res = await axios.get(route('operations.checklists.management'));
        checklists.value = res.data.checklists || [];
        overview.value = res.data.overview || overview.value;
    } catch (e) {
        console.error('Falha ao carregar dados operacionais dos checklists', e);
    }
};

// Modal de Grupo (Checklist)
const isChecklistModalOpen = ref(false);
const editingChecklist = ref(null);
const checklistForm = ref({ title: '', is_active: true });

const openChecklistModal = (item = null) => {
    editingChecklist.value = item;
    checklistForm.value = {
        title: item?.title || '',
        is_active: item ? Boolean(item.is_active) : true
    };
    isChecklistModalOpen.value = true;
};

const saveChecklist = async () => {
    if (!checklistForm.value.title.trim()) return;
    try {
        if (editingChecklist.value) {
            await axios.put(route('operations.checklists.update', editingChecklist.value.id), checklistForm.value);
        } else {
            await axios.post(route('operations.checklists.store'), checklistForm.value);
        }
        isChecklistModalOpen.value = false;
        loadManagementData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível salvar a rotina.', life: 3000 });
    }
};

const deleteChecklist = async (checklist) => {
    if (!confirm(`Deseja remover o grupo "${checklist.title}" e todas as suas tarefas?`)) return;
    try {
        await axios.delete(route('operations.checklists.destroy', checklist.id));
        toast.add({ severity: 'success', summary: 'Removido', detail: 'Rotina excluída.', life: 2500 });
        loadManagementData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir rotina.', life: 3000 });
    }
};

// Modal de Tarefa e Inventário
const isTaskModalOpen = ref(false);
const currentChecklistForTask = ref(null);
const editingTask = ref(null);

const inventorySuggestions = ref([]);
const isSearchingInventory = ref(false);

const taskForm = ref({
    description: '',
    instructions: '',
    assignment_type: 'all',
    recurrence_type: 'daily',
    trigger_type: 'manual',
    completion_type: 'shared',
    is_inventory_task: false,
    inventory_item_type: 'App\\Models\\Product',
    inventory_item_id: null,
    inventory_item_name: ''
});

// 🚀 Funções do Autocomplete
const handleSearchInventory = async (query) => {
    taskForm.value.inventory_item_name = query;

    if (!query || query.length < 2) {
        inventorySuggestions.value = [];
        return;
    }

    isSearchingInventory.value = true;
    try {
        // Garantir que a rota .search-inventory foi registrada no seu web.php/api.php
        const targetRoute = typeof route === 'function' && route().has('operations.checklists.search-inventory')
            ? route('operations.checklists.search-inventory')
            : '/operations/checklists/search-inventory';

        const response = await axios.get(targetRoute, {
            params: {
                type: taskForm.value.inventory_item_type,
                q: query
            }
        });
        inventorySuggestions.value = response.data;
    } catch (e) {
        inventorySuggestions.value = [];
    } finally {
        isSearchingInventory.value = false;
    }
};

const handleSelectInventoryItem = (item) => {
    taskForm.value.inventory_item_id = item.id;
    taskForm.value.inventory_item_name = item.name;
    inventorySuggestions.value = [];
};

const clearInventorySelection = () => {
    taskForm.value.inventory_item_id = null;
    taskForm.value.inventory_item_name = '';
    inventorySuggestions.value = [];
};

const openTaskModal = (checklist, task = null) => {
    currentChecklistForTask.value = checklist;
    editingTask.value = task;

    taskForm.value = {
        description: task?.description || '',
        instructions: task?.instructions || '',
        assignment_type: task?.assignment_type || 'all',
        recurrence_type: task?.recurrence_type || 'daily',
        trigger_type: task?.trigger_type || 'manual',
        completion_type: task?.completion_type || 'shared',
        is_inventory_task: Boolean(task?.is_inventory_task),
        inventory_item_type: task?.inventory_item_type || 'App\\Models\\Product',
        inventory_item_id: task?.inventory_item_id || null,
        inventory_item_name: task?.inventory_item?.name || ''
    };

    isTaskModalOpen.value = true;
};

const saveTask = async () => {
    if (!taskForm.value.description.trim()) return;

    // Validação extra se a flag de inventário estiver ligada
    if (taskForm.value.is_inventory_task && !taskForm.value.inventory_item_id) {
        toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Selecione um item de estoque ou desmarque a opção.', life: 4000 });
        return;
    }

    try {
        if (editingTask.value) {
            await axios.put(route('operations.checklists.tasks.update', editingTask.value.id), taskForm.value);
        } else {
            await axios.post(route('operations.checklists.tasks.store', currentChecklistForTask.value.id), taskForm.value);
        }
        isTaskModalOpen.value = false;
        loadManagementData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao salvar tarefa.', life: 3000 });
    }
};

const deleteTask = async (task) => {
    if (!confirm(`Deseja remover a tarefa "${task.description}"?`)) return;
    try {
        await axios.delete(route('operations.checklists.tasks.destroy', task.id));
        toast.add({ severity: 'success', summary: 'Removido', detail: 'Tarefa excluída.', life: 2500 });
        loadManagementData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir tarefa.', life: 3000 });
    }
};

onMounted(() => {
    isMounted.value = true;
    loadManagementData();
});
</script>
