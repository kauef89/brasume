<template>
    <div class="flex flex-col gap-6 animate-in fade-in duration-300">

        <!-- 🚀 1. PAINEL DE AUTOMAÇÕES -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Automação de Aniversário -->
            <div class="bg-surface-2 p-5 rounded-[1.5rem] border shadow-sm flex items-center justify-between transition-colors"
                 :class="crmData?.settings?.auto_birthday_coupon ? 'border-primary-500/30' : 'border-surface-border'">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl transition-colors"
                         :class="crmData?.settings?.auto_birthday_coupon ? 'bg-primary-500/10 text-primary-500' : 'bg-surface-3 text-surface-muted'">
                        <i class="pi pi-gift"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-black text-sm text-surface-text">Presente de Aniversário</span>
                        <span class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">Cupom automático no mês</span>
                    </div>
                </div>
                <div class="flex items-center">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer"
                               :checked="crmData?.settings?.auto_birthday_coupon"
                               @change="toggleAutomation('auto_birthday_coupon', $event.target.checked)">
                        <div class="w-11 h-6 bg-surface-3 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                    </label>
                </div>
            </div>

            <!-- Automação de Resgate (Churn) -->
            <div class="bg-surface-2 p-5 rounded-[1.5rem] border shadow-sm flex items-center justify-between transition-colors"
                 :class="crmData?.settings?.auto_churn_coupon ? 'border-warning-500/30' : 'border-surface-border'">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl transition-colors"
                         :class="crmData?.settings?.auto_churn_coupon ? 'bg-warning-500/10 text-warning-600' : 'bg-surface-3 text-surface-muted'">
                        <i class="pi pi-history"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-black text-sm text-surface-text">Recuperação de Clientes</span>
                        <span class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">Atrair clientes inativos</span>
                    </div>
                </div>
                <div class="flex items-center">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer"
                               :checked="crmData?.settings?.auto_churn_coupon"
                               @change="toggleAutomation('auto_churn_coupon', $event.target.checked)">
                        <div class="w-11 h-6 bg-surface-3 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-warning-500"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- 🚀 2. LISTA DE CUPONS ATIVOS -->
        <div class="flex flex-col bg-surface-2 border border-surface-border rounded-[2rem] overflow-hidden shadow-sm">

            <div class="p-5 border-b border-surface-border flex items-center justify-between bg-surface-1/50">
                <div class="flex flex-col">
                    <h3 class="font-black text-base text-surface-text">Gestão de Cupons</h3>
                    <span class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">Campanhas ativas e histórico</span>
                </div>
                <button @click="isNewCouponModalVisible = true" class="h-10 px-4 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-[10px] font-black uppercase tracking-widest flex items-center gap-2 transition-colors outline-none cursor-pointer border-none shadow-sm">
                    <i class="pi pi-plus"></i> Criar Cupom
                </button>
            </div>

            <div class="flex flex-col p-2 divide-y divide-surface-border/50">

                <!-- Empty State -->
                <div v-if="!crmData?.coupons || crmData.coupons.length === 0" class="p-10 text-center flex flex-col items-center opacity-60">
                    <i class="pi pi-ticket text-4xl text-surface-muted mb-3"></i>
                    <p class="font-bold text-surface-text text-sm">Nenhum cupom criado.</p>
                    <span class="text-xs text-surface-muted">Clique em "Criar Cupom" para começar.</span>
                </div>

                <!-- Lista de Cupons -->
                <div v-for="coupon in crmData?.coupons" :key="coupon.id" class="p-4 flex items-center justify-between hover:bg-surface-1/30 transition-colors">

                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl border-2 border-dashed border-primary-500/30 bg-primary-500/5 text-primary-500 flex items-center justify-center font-black text-xs uppercase tracking-widest">
                            <i class="pi pi-ticket"></i>
                        </div>
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <span class="font-black text-sm text-surface-text uppercase tracking-widest">{{ coupon.code }}</span>
                                <span v-if="coupon.is_active" class="bg-success-500/10 text-success-600 px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border border-success-500/20">Ativo</span>
                                <span v-else class="bg-surface-3 text-surface-muted px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest">Inativo</span>
                            </div>
                            <span class="text-xs font-bold text-surface-muted mt-0.5">
                                {{ formatCouponValue(coupon) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col items-end text-right">
                        <span class="font-black text-sm text-surface-text">{{ coupon.usage_count }} usos</span>
                        <span v-if="coupon.expires_at" class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">
                            Validade: {{ formatDate(coupon.expires_at) }}
                        </span>
                        <span v-else class="text-[10px] font-bold text-surface-muted uppercase tracking-widest">
                            Uso Ilimitado
                        </span>
                    </div>

                </div>
            </div>
        </div>

        <!-- 🚀 MODAL DE CRIAÇÃO (Chama o componente Form que você já tinha) -->
        <Dialog v-model:visible="isNewCouponModalVisible" modal header="Criar Novo Cupom" class="w-[400px]" :draggable="false">
            <CouponQuickForm
                @submit="handleCreateCoupon"
                :is-submitting="isSubmitting"
            />
        </Dialog>

    </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';

// Importa o formulário de cupons
import CouponQuickForm from './CouponQuickForm.vue';

const props = defineProps({
    crmData: Object
});

const toast = useToast();
const isNewCouponModalVisible = ref(false);
const isSubmitting = ref(false);

const formatCouponValue = (coupon) => {
    if (coupon.type === 'percent') return `${coupon.value}% de desconto`;
    if (coupon.type === 'fixed') return `R$ ${coupon.value.toFixed(2)} de desconto`;
    if (coupon.type === 'free_delivery') return `Entrega Gratuita`;
    return 'Desconto Especial';
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const [year, month, day] = dateStr.split('-');
    return `${day}/${month}/${year}`;
};

// 🚀 LIGAÇÃO COM O BACKEND: Criar Cupom
const handleCreateCoupon = (payload) => {
    isSubmitting.value = true;
    router.post(route('operations.crm.create-coupon'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            isNewCouponModalVisible.value = false;
            toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Novo cupom ativado com sucesso!', life: 3000 });
        },
        onError: () => {
            isSubmitting.value = false;
            toast.add({ severity: 'error', summary: 'Aviso', detail: 'Verifique os dados ou se o código já existe.', life: 4000 });
        }
    });
};

// 🚀 LIGAÇÃO COM O BACKEND: Alternar Automações
const toggleAutomation = (key, value) => {
    router.post(route('operations.crm.toggle-automation'), { key, value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Atualizado', detail: 'Regras de automação atualizadas.', life: 2000 });
        }
    });
};
</script>
