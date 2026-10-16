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
