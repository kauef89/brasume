<template>
    <div
        class="grid grid-cols-[minmax(120px,1fr)_160px_220px_160px] items-center gap-3 px-4 py-2.5 transition-colors group"
        :class="modelValue.status === 'present' ? 'bg-surface-1/40 hover:bg-surface-2/60' : 'bg-transparent opacity-60 grayscale hover:grayscale-0 hover:bg-surface-2/30'"
    >
        <!-- 1. COLABORADOR COM ÍCONE NO AVATAR -->
        <div class="min-w-0 flex items-center gap-3">
            <div class="relative shrink-0">
                <AvatarBox :name="user.name" :color-class="getAvatarColor(modelValue.assigned_role)" />

                <span
                    v-if="user.is_scheduled"
                    class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-xs border-2 border-surface-2"
                    title="Escalado para o turno de hoje"
                >
                    <i class="pi pi-calendar text-[7px] font-black"></i>
                </span>
            </div>

            <div class="flex flex-col min-w-0">
                <span class="font-black text-sm text-surface-text truncate leading-tight">
                    {{ abbreviateName(user.name) }}
                </span>
                <span class="text-[10px] uppercase font-bold tracking-wider text-surface-muted mt-0.5">
                    {{ user.employment_type === 'clt' ? 'CLT' : 'Freelancer' }}
                </span>
            </div>
        </div>

        <!-- 2. FUNÇÃO / CARGO -->
        <div class="min-w-0">
            <select
                v-if="modelValue.status === 'present'"
                v-model="modelValue.assigned_role"
                class="w-full h-8 min-w-0 text-[11px] font-bold text-surface-text rounded-md bg-surface-1 border border-surface-border outline-none focus:border-primary-500 shadow-sm transition-colors cursor-pointer px-2 pr-6 appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_0.5rem_center] truncate"
            >
                <option v-for="role in (user.available_roles || [])" :key="role.value" :value="role.value">
                    {{ role.label }}
                </option>
            </select>
            <span v-else class="text-xs font-semibold text-surface-muted">
                {{ getRoleLabel(modelValue.assigned_role, user.available_roles) }}
            </span>
        </div>

        <!-- 3. STATUS DE PRESENÇA -->
        <div class="min-w-0 flex justify-center">
            <SegmentedControl
                v-model="modelValue.status"
                :options="attendanceOptions"
            />
        </div>

        <!-- 4. DIÁRIA / LIQUIDAÇÃO -->
        <div class="min-w-0 flex items-center justify-end gap-2">
            <template v-if="user.employment_type !== 'clt' && modelValue.status === 'present'">
                <template v-if="canPayout">
                    <button
                        v-if="!modelValue.is_paid"
                        type="button"
                        @click="$emit('pay-freelancer', user, modelValue.rate)"
                        class="h-7 px-2 rounded-lg bg-warning-500/10 hover:bg-warning-500/20 text-warning-700 dark:text-warning-400 text-[10px] font-black uppercase tracking-wider border border-warning-500/30 transition-colors cursor-pointer outline-none shrink-0"
                    >
                        Liquidar
                    </button>
                    <span
                        v-else
                        class="h-7 px-2 rounded-lg bg-success-500/10 text-success-smart border border-success-500/20 text-[10px] font-black uppercase tracking-wider flex items-center gap-1 shrink-0"
                    >
                        <i class="pi pi-check text-[9px]"></i> Pago
                    </span>
                </template>
                <span v-else class="text-[11px] font-black text-surface-text pr-1 tabular-nums">
                    {{ $formatCurrency(modelValue.rate) }}
                </span>
            </template>

            <span v-else-if="user.employment_type === 'clt'" class="text-[11px] font-semibold text-surface-muted pr-1">
                Folha Mensal
            </span>
            <span v-else class="text-[11px] font-medium text-surface-muted/50 pr-1">
                —
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import AvatarBox from '@/Components/UI/AvatarBox.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import { abbreviateName, formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    user: { type: Object, required: true },
    modelValue: { type: Object, required: true },
    canPayout: { type: Boolean, default: true }
});

defineEmits(['update:modelValue', 'pay-freelancer']);

const isClt = computed(() => props.user.employment_type === 'clt');

const attendanceOptions = computed(() => [
    { label: 'Sim', value: 'present' },
    { label: 'Falta', value: 'absent' },
    { label: 'Folga', value: 'day_off', disabled: !isClt.value },
    { label: 'Férias', value: 'vacation', disabled: !isClt.value }
]);

const getRoleLabel = (roleValue, availableRoles) => {
    if (!availableRoles) return roleValue;
    const role = availableRoles.find(r => r.value === roleValue);
    return role ? role.label : roleValue;
};

const getAvatarColor = (roleValue) => {
    const colors = {
        'admin': 'bg-red-500',
        'manager': 'bg-purple-500',
        'pos': 'bg-blue-500',
        'waiter': 'bg-primary-500',
        'driver': 'bg-orange-500',
        'kitchen': 'bg-amber-500'
    };
    return colors[roleValue] || 'bg-stone-500';
};
</script>
