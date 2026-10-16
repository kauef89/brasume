import { useTenantEcho } from '@/Composables/useTenantEcho'; // 🚀 Importação do isolador de Tenant
import axios from 'axios';
import { ref } from 'vue';

export function useChecklists() {
    const checklists = ref([]);
    const isLoading = ref(true);

    const isIssueModalVisible = ref(false);
    const selectedTask = ref(null);
    const issueNotes = ref('');

    // 🚀 Inicia a escuta isolada
    const { listenChannel } = useTenantEcho();

    const fetchChecklists = async () => {
        isLoading.value = true;
        try {
            const response = await axios.get(route('operations.checklists.index'));
            checklists.value = response.data.checklists || [];
        } catch (e) {
            console.error("Erro ao carregar checklists", e);
        } finally {
            isLoading.value = false;
        }
    };

    const translateTrigger = (type) => {
        const map = {
            shift_start: 'Abertura de Turno',
            shift_end: 'Fechamento',
            scheduled: 'Agendado',
            manual: 'Livre'
        };
        return map[type] || type;
    };

    const formatDeadline = (task) => {
        if (task.recurrence_type === 'once' && task.deadline_date) {
            return `Até ${new Date(task.deadline_date).toLocaleDateString('pt-BR')} ${new Date(task.deadline_date).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'})}`;
        }
        if (['shift_start', 'shift_end'].includes(task.trigger_type) && task.deadline_minutes) {
            return `Tolerância: ${task.deadline_minutes} min`;
        }
        if (task.trigger_type === 'scheduled' && task.trigger_time) {
            return `Às ${task.trigger_time.substring(0, 5)}`;
        }
        return 'Sem prazo rígido';
    };

    const markTask = async (taskId, status, notes = null) => {
        checklists.value.forEach(list => {
            const task = list.tasks.find(t => t.id === taskId);
            if (task) {
                const oldStatus = task.status;
                task.status = status;
                task.notes = notes;

                if (oldStatus === 'pending' && status !== 'pending') list.progress++;
                if (oldStatus !== 'pending' && status === 'pending') list.progress--;
            }
        });

        try {
            await axios.post(route('operations.checklists.sync'), { task_id: taskId, status, notes });
        } catch (e) {
            fetchChecklists();
        }
    };

    const openIssueModal = (task) => {
        selectedTask.value = task;
        issueNotes.value = task.notes || '';
        isIssueModalVisible.value = true;
    };

    const submitIssue = () => {
        if (selectedTask.value && issueNotes.value) {
            markTask(selectedTask.value.id, 'issue', issueNotes.value);
            isIssueModalVisible.value = false;
        }
    };

    const listenForRealTimeUpdates = () => {
        // 🚀 O SEGREDO ESTÁ AQUI: Usamos a nossa função segura em vez do window.Echo direto!
        listenChannel('operations', '.ChecklistsUpdated', () => {
            console.log('🔄 Atualização recebida via Reverb! Recarregando tarefas...');
            fetchChecklists();
        });
    };

    return {
        checklists, isLoading, isIssueModalVisible, selectedTask, issueNotes,
        fetchChecklists, translateTrigger, formatDeadline, markTask,
        openIssueModal, submitIssue, listenForRealTimeUpdates
    };
}
