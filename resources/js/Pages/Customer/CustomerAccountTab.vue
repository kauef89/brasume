<template>
    <div class="flex-1 w-full h-full flex flex-col overflow-hidden select-none">
        <div
            class="flex-1 overflow-y-auto px-4 pb-28 flex flex-col gap-6 hide-scrollbar relative"
            :style="{ top: 'var(--app-header-height, 7rem)' }"
        >

            <!-- 🛑 NÍVEL 1: VISITANTE 100% ANÔNIMO -->
            <div v-if="!customer && !isGuest" class="c-glass-card p-8 mt-4 text-center flex flex-col items-center justify-center">
                <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mb-4">
                    <i class="pi pi-user text-3xl text-white"></i>
                </div>
                <h2 class="c-title text-xl mb-2">Área do Cliente</h2>
                <p class="c-text-muted mb-6 max-w-sm leading-relaxed">
                    Identifique-se para gerenciar seus endereços salvos, conferir o histórico de pedidos e acumular cashback!
                </p>

                <button @click="$emit('open-login')" class="c-btn-primary max-w-xs !h-12 !text-xs">
                    <i class="pi pi-sign-in text-xs"></i> Entrar ou Criar Conta
                </button>
            </div>

            <!-- 👻 NÍVEL 2: VISITANTE TEMPORÁRIO (GUEST) -->
            <div v-else-if="isGuest" class="flex flex-col gap-4 mt-2">
                <div class="c-glass-card p-6 relative overflow-hidden">
                    <div class="w-12 h-12 bg-white/15 rounded-full flex items-center justify-center mb-3">
                        <i class="pi pi-star-fill text-lg"></i>
                    </div>

                    <h3 class="c-title text-lg">Desbloqueie Vantagens!</h3>
                    <p class="c-text-muted mt-1 mb-4 leading-relaxed">
                        Você está usando uma sessão temporária. Crie sua conta grátis agora e libere recursos exclusivos:
                    </p>

                    <ul class="flex flex-col gap-2 mb-6">
                        <li class="flex items-center gap-2 c-text-main text-xs"><i class="pi pi-check-circle text-emerald-400"></i> Programa de Cashback</li>
                        <li class="flex items-center gap-2 c-text-main text-xs"><i class="pi pi-check-circle text-emerald-400"></i> Todas as Formas de Pagamento</li>
                        <li class="flex items-center gap-2 c-text-main text-xs"><i class="pi pi-check-circle text-emerald-400"></i> Acompanhamento do Pedido em Tempo Real</li>
                        <li class="flex items-center gap-2 c-text-main text-xs"><i class="pi pi-check-circle text-emerald-400"></i> Histórico Completo de Compras</li>
                    </ul>

                    <button @click="$emit('upgrade-account')" class="c-btn-primary">
                        <i class="pi pi-user-plus text-xs"></i> Criar Conta Grátis
                    </button>
                </div>

                <button @click="$emit('logout')" class="c-btn-ghost !h-12 !text-xs text-white/70">
                    <i class="pi pi-sign-out text-xs"></i> Encerrar Sessão
                </button>
            </div>

            <!-- 👤 NÍVEL 3: CLIENTE LOGADO -->
            <template v-else-if="customer">
                <!-- DADOS PESSOAIS -->
                <div class="flex flex-col gap-2 mt-1">
                    <h3 class="text-xs font-black uppercase tracking-wider text-white pl-1">Dados Pessoais</h3>

                    <div class="c-glass-card p-4 flex items-center justify-between relative overflow-hidden">

                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-12 h-12 rounded-full bg-primary-500/20 text-primary-300 flex items-center justify-center font-black shrink-0 text-base">
                                {{ getInitials(customer?.name || '') }}
                            </div>
                            <div class="flex flex-col min-w-0 pr-10">
                                <span class="c-text-main font-black truncate">{{ customer?.name }}</span>
                                <span class="c-text-muted truncate">{{ formatPhone(customer?.phone) }}</span>
                            </div>
                        </div>

                        <button @click="openEditProfile" class="c-btn-icon shrink-0 mt-3" title="Editar Perfil">
                            <i class="pi pi-pencil text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- 🎁 NOVO CARD: FIDELIDADE & EXTRATO DE CASHBACK -->
                <div v-if="loyaltyEnabled" class="flex flex-col gap-2 mt-2">
                    <h3 class="text-xs font-black uppercase tracking-wider text-white pl-1">Fidelidade</h3>

                    <button
                        type="button"
                        @click="isStatementModalVisible = true"
                        class="c-glass-card p-4 flex items-center justify-between transition-all hover:bg-white/10 text-left outline-none cursor-pointer border border-white/10 group"
                    >
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-11 h-11 rounded-2xl bg-amber-500/20 text-amber-300 flex items-center justify-center shrink-0">
                                <i class="pi pi-star-fill text-lg"></i>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-[11px] font-bold text-white/60">Saldo de Cashback</span>
                                <span class="text-base font-black text-amber-300 leading-tight">
                                    {{ loyaltyScoreText }}
                                </span>
                                <span class="text-[10px] font-medium text-white">
                                    {{ customer?.loyalty_points || 0 }} pts acumulados
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0 px-3 py-1.5 rounded-xl transition-colors">
                            <span class="text-xs font-bold text-primary-300">Ver Extrato</span>
                            <i class="pi pi-chevron-right text-[10px] text-white/60 group-hover:translate-x-0.5 transition-transform"></i>
                        </div>
                    </button>
                </div>

                <!-- ENDEREÇOS -->
                <div class="flex flex-col gap-2 mt-2">
                    <div class="flex items-center justify-between px-1">
                        <h3 class="text-xs font-black uppercase tracking-wider text-white">Meus Endereços</h3>
                        <button @click="$emit('add-address')" class="text-xs font-black text-primary-400 active:text-primary-300 outline-none bg-transparent border-none cursor-pointer">
                            + Adicionar
                        </button>
                    </div>

                    <div v-if="activeAddresses.length > 0" class="flex flex-col gap-2.5">
                        <div v-for="addr in visibleAddresses" :key="addr.id" class="c-glass-card p-4 flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <span class="c-badge !bg-white/10 !text-white/80">{{ addr.nickname || 'Casa' }}</span>
                                <button @click="confirmDeactivateAddress(addr.id)" class="c-btn-icon-danger !w-7 !h-7" title="Excluir Endereço">
                                    <i class="pi pi-trash text-[10px]"></i>
                                </button>
                            </div>
                            <p class="c-text-main text-xs font-bold leading-snug">{{ addr.full_address }}</p>
                            <p v-if="addr.complement" class="c-text-muted text-[11px] line-clamp-1">{{ addr.complement }}</p>
                        </div>

                        <button
                            v-if="hasMoreAddresses"
                            @click="expandAddresses = true"
                            class="c-btn-ghost !h-10 !text-xs text-white/70"
                        >
                            Ver mais {{ activeAddresses.length - 3 }} endereço(s)
                        </button>
                    </div>

                    <div v-else class="c-glass-card p-6 text-center flex flex-col items-center justify-center gap-1.5 opacity-60">
                        <i class="pi pi-map-marker text-xl text-white/40"></i>
                        <span class="c-text-muted">Nenhum endereço salvo.</span>
                    </div>
                </div>

                <!-- AÇÕES DA CONTA -->
                <div class="flex flex-col gap-2.5 mt-4">
                    <button @click="$emit('logout')" class="c-btn-ghost w-full !h-12 !text-xs">
                        <i class="pi pi-sign-out text-xs"></i> Sair da Conta
                    </button>

                    <button @click="$emit('delete-account')" class="c-btn-ghost w-full !h-12 !text-xs !bg-red-500/10 text-red-400 active:!bg-red-500/20">
                        <i class="pi pi-trash text-xs"></i> Excluir Minha Conta
                    </button>
                </div>
            </template>

            <!-- GAVETA DE EDIÇÃO DE PERFIL -->
            <component
                :is="isMobile ? Drawer : Dialog"
                v-model:visible="isEditModalVisible"
                :position="isMobile ? 'bottom' : undefined"
                :modal="true"
                :header="!isMobile ? 'Editar Perfil' : undefined"
                :class="isMobile ? 'h-auto! max-h-[90vh]! rounded-t-3xl! c-glass-panel' : 'w-[400px] c-glass-panel rounded-2xl'"
                :pt="isMobile ? { header: { class: 'hidden' } } : {}"
                :draggable="false"
            >
                <div class="flex flex-col gap-4 pt-2" :class="isMobile ? 'pb-6 px-2' : ''">
                    <div v-if="isMobile" class="flex justify-between items-center mb-1 pb-3 border-b border-white/10">
                        <h2 class="c-title text-lg">Meus Dados</h2>
                        <button @click="isEditModalVisible = false" class="c-btn-icon !w-8 !h-8">
                            <i class="pi pi-times text-xs"></i>
                        </button>
                    </div>

                    <div class="flex flex-col gap-3">
                        <FormField label="Nome Completo">
                            <input
                                v-model="editForm.name"
                                type="text"
                                class="w-full h-12 px-4 rounded-xl bg-white/10 text-white font-bold text-sm outline-none focus:bg-white/15 border-none transition-all"
                            />
                        </FormField>

                        <FormField label="WhatsApp">
                            <input
                                v-model="editForm.phone"
                                type="text"
                                class="w-full h-12 px-4 rounded-xl bg-white/10 text-white font-bold text-sm outline-none focus:bg-white/15 border-none transition-all"
                                @input="formatPhoneInput"
                            />
                        </FormField>

                        <button
                            @click="submitProfileUpdate"
                            class="c-btn-primary !h-12 !text-xs mt-2"
                        >
                            Salvar Alterações
                        </button>
                    </div>
                </div>
            </component>

            <!-- 🚀 MODAL DE EXTRATO DE FIDELIDADE & CASHBACK -->
            <LoyaltyStatementModal
                v-model:visible="isStatementModalVisible"
                :customer="customer"
                :orders="historyOrders"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import FormField from "@/Components/UI/FormField.vue";
import LoyaltyStatementModal from "@/Pages/Customer/Profile/LoyaltyStatementModal.vue";
import { formatPhone, formatCurrency, getInitials } from "@/Utils/formatters";
import { useViewport } from '@/Composables/useViewport';

const props = defineProps({
    customer: Object,
    isGuest: Boolean,
    loginConfig: Object,
    myHistory: { type: Array, default: () => [] }
});

const emit = defineEmits([
    'open-register', 'open-login', 'upgrade-account', 'add-address',
    'logout', 'delete-account', 'update-profile', 'deactivate-address'
]);

const { isMobile } = useViewport();
const page = usePage();

const isEditModalVisible = ref(false);
const isStatementModalVisible = ref(false);
const editForm = ref({ name: '', phone: '' });

// 🚀 Resolução dos pedidos para alimentar o extrato (prop ou Inertia share)
const historyOrders = computed(() => {
    if (props.myHistory && props.myHistory.length > 0) return props.myHistory;
    return page.props.myHistory || [];
});

const loyaltyEnabled = computed(() => {
    const isActive = page.props.companySettings?.loyalty_is_active;
    return isActive === true || isActive === 1 || isActive === '1';
});

const loyaltyScoreText = computed(() => {
    if (!props.customer) return formatCurrency(0);

    const rewardType = page.props.companySettings?.loyalty_reward_type;
    const pts = Number(props.customer.loyalty_points) || 0;

    if (rewardType === 'product') {
        return `${pts} ${pts === 1 ? 'Ponto' : 'Pontos'}`;
    }

    const conversionRate = Number(page.props.companySettings?.loyalty_cashback_conversion_rate || 5);
    const balanceInReais = (pts / 100) * conversionRate;

    return formatCurrency(balanceInReais);
});

const expandAddresses = ref(false);

const activeAddresses = computed(() => {
    if (!props.customer || !props.customer.addresses) return [];
    return props.customer.addresses.filter(a => a.is_active !== false && a.is_active !== 0 && a.is_active !== '0');
});

const visibleAddresses = computed(() => {
    if (expandAddresses.value) return activeAddresses.value;
    return activeAddresses.value.slice(0, 3);
});

const hasMoreAddresses = computed(() => {
    return !expandAddresses.value && activeAddresses.value.length > 3;
});

const openEditProfile = () => {
    editForm.value = {
        name: props.customer?.name || '',
        phone: formatPhone(props.customer?.phone) || ''
    };
    isEditModalVisible.value = true;
};

const formatPhoneInput = (e) => {
    editForm.value.phone = formatPhone(e.target.value);
};

const submitProfileUpdate = () => {
    emit('update-profile', editForm.value, () => {
        isEditModalVisible.value = false;
    });
};

const confirmDeactivateAddress = (id) => {
    if (confirm('Tem certeza de que deseja remover este endereço?')) {
        emit('deactivate-address', id);
    }
};
</script>
