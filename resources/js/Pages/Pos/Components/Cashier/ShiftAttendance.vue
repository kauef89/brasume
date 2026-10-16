<template>
    <div class="w-full h-full">
        <div class="w-full h-full flex flex-col p-6 overflow-hidden">

            <!-- CABEÇALHO DO PAINEL -->
            <div class="flex items-center justify-between pb-6 border-b border-surface-border shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-primary-500/10 text-primary-500 rounded-full flex items-center justify-center shadow-inner shrink-0">
                        <i :class="['pi text-xl font-black', headerIcon]"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-surface-text tracking-tight">{{ headerTitle }}</h2>
                        <p class="text-sm font-medium text-surface-muted mt-0.5">{{ headerSubtitle }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <SecureActionButton action-code="cancel" label="Cancelar" variant="text" severity="secondary" custom-class="px-6 h-10 rounded-full font-black text-sm uppercase tracking-widest" @secure-click="$emit('cancel')" />
                    <button
                        @click="handleConfirm"
                        :disabled="roleWarnings.length > 0 || isSubmitting"
                        class="px-6 h-10 rounded-full font-black text-sm uppercase tracking-widest text-white bg-primary-500 hover:bg-primary-600 transition-colors shadow-md flex items-center gap-2 outline-none cursor-pointer border-none"
                        :class="{'opacity-50 cursor-not-allowed': roleWarnings.length > 0 || isSubmitting}"
                    >
                        <i :class="['pi', isSubmitting ? 'pi-spinner pi-spin' : confirmButtonIcon]"></i>
                        {{ isSubmitting ? 'Processando...' : confirmButtonLabel }}
                    </button>
                </div>
            </div>

            <div class="flex-1 flex flex-col overflow-hidden pt-6 min-h-0">
                <!-- ALERTA DE CONFLITOS DE CARGO -->
                <div v-if="roleWarnings.length > 0" class="bg-danger-500/10 border border-danger-500/20 p-4 rounded-xl flex flex-col gap-2 animate-in fade-in shrink-0 mb-4">
                    <div v-for="(warning, index) in roleWarnings" :key="index" class="flex items-center gap-3 text-danger-700 dark:text-danger-400 text-sm font-bold">
                        <i class="pi fa-exclamation-triangle"></i>
                        <span>{{ warning }}</span>
                    </div>
                </div>

                <!-- ESTRUTURA DA TABELA -->
                <div class="flex-1 flex flex-col min-h-0 bg-surface-2/40 border border-surface-border rounded-2xl overflow-hidden shadow-xs">
                    <div class="flex-1 overflow-auto flex flex-col hide-scrollbar">
                        <div class="w-full flex flex-col flex-1">
                            <!-- CABEÇALHO DA TABELA EM GRID -->
                            <div class="grid grid-cols-[minmax(120px,1fr)_160px_220px_160px] items-center gap-3 px-4 py-3 bg-surface-2 border-b border-surface-border text-[10px] font-black uppercase tracking-widest text-surface-muted sticky top-0 z-10 shrink-0 shadow-sm">
                                <div class="min-w-0 truncate">Colaborador</div>
                                <div class="min-w-0 truncate">Função</div>
                                <div class="min-w-0 text-center truncate">Presença</div>
                                <div class="min-w-0 text-right truncate">Liquidação</div>
                            </div>

                            <!-- LINHAS ROLÁVEIS -->
                            <div class="flex-1 flex flex-col divide-y divide-surface-border/50 hide-scrollbar">
                                <template v-for="user in sortedUsers" :key="user.id">
                                    <ShiftAttendanceRow
                                        v-if="attendanceData[user.id]"
                                        :user="user"
                                        :can-payout="effectiveMode !== 'open'"
                                        v-model="attendanceData[user.id]"
                                        @pay-freelancer="(u, rate) => $emit('pay-freelancer', u, rate)"
                                    />
                                </template>

                                <div v-if="sortedUsers.length === 0" class="p-12 text-center text-surface-muted text-sm font-medium">
                                    Nenhum colaborador com cadastro ativo encontrado.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';
import ShiftAttendanceRow from './ShiftAttendanceRow.vue';
import { useShiftAttendance } from '@/Pages/Shared/Composables/useShiftAttendance';

const props = defineProps({
    users: { type: Array, default: () => [] },
    currentAttendees: { type: Array, default: () => [] },
    flowMode: {
        type: String,
        default: 'mid_shift', // 'open', 'close', 'mid_shift'
        validator: (v) => ['open', 'close', 'mid_shift'].includes(v)
    },
    isMidShift: { type: Boolean, default: true }
});

const emit = defineEmits(['cancel', 'confirm', 'pay-freelancer']);

const { syncMidShiftAttendance } = useShiftAttendance();

const attendanceData = ref({});

const effectiveMode = computed(() => {
    if (props.flowMode && props.flowMode !== 'mid_shift') return props.flowMode;
    return props.isMidShift ? 'mid_shift' : 'close';
});

const headerTitle = computed(() => {
    if (effectiveMode.value === 'open') return 'Abertura de Turno • Equipe e Ponto';
    if (effectiveMode.value === 'close') return 'Fechamento de Turno • Equipe e Acertos';
    return 'Escala e Ponto';
});

const headerSubtitle = computed(() => {
    if (effectiveMode.value === 'open') return 'Confirme a presença e funções dos colaboradores antes de abrir o caixa.';
    if (effectiveMode.value === 'close') return 'Confirme a presença e efetue os acertos dos freelancers para encerrar o turno.';
    return 'Gerencie a presença da sua equipe em ordem alfabética.';
});

const headerIcon = computed(() => {
    if (effectiveMode.value === 'open') return 'pi-user-plus';
    if (effectiveMode.value === 'close') return 'pi-user-check';
    return 'pi-users';
});

const confirmButtonLabel = computed(() => {
    if (effectiveMode.value === 'open') return 'Avançar para Abertura de Caixa';
    if (effectiveMode.value === 'close') return 'Confirmar e Finalizar';
    return 'Salvar Ponto';
});

const confirmButtonIcon = computed(() => {
    if (effectiveMode.value === 'open') return 'pi-arrow-right';
    if (effectiveMode.value === 'close') return 'pi-check-circle';
    return 'pi-check';
});

const sortedUsers = computed(() => {
    const safeUsers = Array.isArray(props.users) ? props.users : [];
    return [...safeUsers].sort((a, b) => a.name.localeCompare(b.name));
});

const roleWarnings = computed(() => {
    const warnings = [];
    const roleCounts = {};

    Object.values(attendanceData.value).forEach(data => {
        if (data && data.status === 'present' && data.assigned_role) {
            roleCounts[data.assigned_role] = (roleCounts[data.assigned_role] || 0) + 1;
        }
    });

    if (roleCounts['pos'] > 1) warnings.push('Não é permitido ter mais de um Caixa (POS) ativo.');
    if (roleCounts['manager'] > 1 || roleCounts['admin'] > 1) warnings.push('Há mais de um Gestor/Admin definido.');

    return warnings;
});

const initAttendanceData = () => {
    const newState = {};
    const safeUsers = Array.isArray(props.users) ? props.users : [];
    const safeAttendees = Array.isArray(props.currentAttendees) ? props.currentAttendees : [];

    safeUsers.forEach(u => {
        const existing = safeAttendees.find(a => a.user_id === u.id);

        const primaryRole = u.roles && u.roles.length > 0
            ? u.roles[0].name
            : (u.available_roles?.[0]?.value || 'waiter');

        if (existing) {
            newState[u.id] = {
                status: existing.attendance_status || 'present',
                rate: existing.daily_rate > 0 ? existing.daily_rate : (u.base_remuneration || u.default_rate || 0),
                is_paid: !!existing.account_payable_id,
                assigned_role: existing.assigned_role || primaryRole
            };
        } else {
            let defaultStatus = 'absent';

            if (u.employment_type === 'clt' || u.employment_type === 'partner' || u.is_scheduled) {
                defaultStatus = 'present';
            }

            newState[u.id] = {
                status: defaultStatus,
                rate: u.base_remuneration || u.default_rate || 0,
                is_paid: false,
                assigned_role: primaryRole
            };
        }
    });

    attendanceData.value = { ...attendanceData.value, ...newState };
};

// 🚀 Reage tanto à lista geral quanto às atualizações dos presentes (ex: após pagar diária)
watch(() => [props.users, props.currentAttendees], () => {
    initAttendanceData();
}, { deep: true, immediate: true });

const isSubmitting = ref(false);

const handleConfirm = () => {
    if (roleWarnings.value.length > 0 || isSubmitting.value) return;

    isSubmitting.value = true;

    const payload = Object.keys(attendanceData.value).map(userId => {
        const data = attendanceData.value[userId];
        return {
            user_id: parseInt(userId),
            attendance_status: data.status,
            daily_rate: data.status === 'present' ? Number(data.rate) : 0,
            assigned_role: data.assigned_role
        };
    });

    if (effectiveMode.value === 'mid_shift') {
        syncMidShiftAttendance(payload, () => {
            isSubmitting.value = false;
            emit('cancel');
        });
    } else {
        isSubmitting.value = false;
        emit('confirm', { attendees: payload });
    }
};
</script>
