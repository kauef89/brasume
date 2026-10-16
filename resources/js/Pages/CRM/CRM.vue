<template>
    <div class="flex flex-col h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans p-2 relative">

        <!-- 🏝️ DYNAMIC ISLAND: Controle de Abas -->
        <Teleport v-if="isMounted && isActive" to="#dynamic-island-portal">
            <div class="flex items-center h-10 md:h-12 shrink-0 mr-2">
                <SegmentedControl
                    v-model="activeTab"
                    :options="tabOptions"
                />
            </div>
        </Teleport>

        <div class="flex-1 flex flex-col h-full bg-surface-2 rounded-[2rem] overflow-hidden min-h-0 animate-in fade-in duration-300">
            <!-- HEADER DA PÁGINA -->
            <div class="p-6 border-b border-surface-border bg-surface-2 shrink-0">
                <h2 class="text-2xl font-black text-surface-text leading-tight flex items-center gap-2">
                    <i class="pi pi-chart-line text-primary-500"></i> CRM & Retenção
                </h2>
                <p class="text-sm font-medium text-surface-muted mt-1 uppercase tracking-widest">
                    Acompanhe o ROI e recupere clientes.
                </p>
            </div>

            <!-- CONTEÚDO DINÂMICO DAS ABAS -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-surface-1/30 hide-scrollbar">
                <Transition name="fade" mode="out-in">
                    <KeepAlive>
                        <component
                            :is="activeComponent"
                            :crm-data="crmData"
                        />
                    </KeepAlive>
                </Transition>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import DesktopLayout from '@/Layouts/DesktopLayout.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';

// Importação das Abas Inteligentes
import CouponManager from '@/Pages/CRM/Coupons/CouponManager.vue';
import CrmMetricsTab from '@/Pages/CRM/CrmMetricsTab.vue';
import CrmOpportunitiesTab from '@/Pages/CRM/CrmOpportunitiesTab.vue';

defineOptions({ layout: DesktopLayout });

const props = defineProps({
    crmData: Object,
    isActive: {
        type: Boolean,
        default: false
    }
});

const isMounted = ref(false);
onMounted(() => isMounted.value = true);

const activeTab = ref('coupons');

const tabOptions = computed(() => {
    const oppCount = props.crmData?.opportunities?.length || 0;
    return [
        { value: 'metrics', label: 'Métricas', icon: 'pi-chart-line' },
        { value: 'opportunities', label: oppCount > 0 ? `Oportunidades (${oppCount})` : 'Oportunidades', icon: 'pi-users' },
        { value: 'coupons', label: 'Cupons & Automação', icon: 'pi-ticket' }
    ];
});

const activeComponent = computed(() => {
    switch (activeTab.value) {
        case 'metrics': return CrmMetricsTab;
        case 'opportunities': return CrmOpportunitiesTab;
        case 'coupons': return CouponManager;
        default: return CouponManager;
    }
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(5px); }
</style>
