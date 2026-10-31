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
