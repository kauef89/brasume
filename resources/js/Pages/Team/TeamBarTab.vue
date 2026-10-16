<template>
    <div
        class="flex flex-col h-full w-full"
        :style="{ paddingTop: 'var(--app-header-height, 7rem)' }"
    >
        <!-- 🚀 TELEPORT DO SEGMENTED CONTROL PARA O HEADER (AO LADO DO TÍTULO) -->
        <Teleport to="#team-header-action-portal" v-if="isMounted && isActive">
            <SegmentedControl
                v-model="activeView"
                :options="barViewOptions"
                class="!h-9 text-xs"
            />
        </Teleport>

        <!-- LISTA DE ITENS -->
        <div class="flex-1 overflow-y-auto px-4 pt-3 pb-32 hide-scrollbar flex flex-col gap-4 relative">

            <EmptyState
                v-if="filteredItems.length === 0"
                :title="activeView === 'pending' ? 'Copa Livre' : 'Sem Histórico'"
                :subtitle="activeView === 'pending' ? 'Nenhuma bebida aguardando preparo.' : 'Nenhum item finalizado no turno.'"
                :icon="activeView === 'pending' ? 'pi-check-circle' : 'pi-clock'"
                class="mt-10"
            />

            <TransitionGroup v-else name="list" tag="div" class="flex flex-col gap-3">
                <TeamBarCard
                    v-for="item in filteredItems"
                    :key="item.id"
                    :item="item"
                    :current-time="currentTime"
                    :current-user-id="$page.props.auth.user.id"
                    @take="$emit('take-item', $event)"
                    @complete="$emit('complete-item', $event)"
                    @print="$emit('print-item', $event)"
                    @cancel="openLossModal(item)"
                />
            </TransitionGroup>
        </div>

        <!-- MODAL DE PERDA / CANCELAMENTO -->
        <OperationReasonModal
            v-if="selectedItemForLoss"
            v-model:visible="isLossModalVisible"
            mode="modal"
            :item-name="selectedItemForLoss.product_name || selectedItemForLoss.name"
            action-type="cancel"
            :requires-pin="false"
            :item-to-process="selectedItemForLoss"
            @confirm="handleLossConfirm"
            @cancel="isLossModalVisible = false"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import TeamBarCard from '@/Pages/Team/TeamBarCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import OperationReasonModal from '@/Pages/Pos/Components/Checkout/OperationReasonModal.vue';

const props = defineProps({
    isActive: { type: Boolean, default: true },
    pendingItems: { type: Array, default: () => [] }
});

const emit = defineEmits(['take-item', 'complete-item', 'print-item', 'loss-item']);

const isMounted = ref(false);
const activeView = ref('pending');

const barViewOptions = [
    { label: 'Fila', value: 'pending', icon: 'pi-list' },
    { label: 'Histórico', value: 'history', icon: 'pi-history' }
];

const filteredItems = computed(() => {
    return props.pendingItems.filter(item => {
        const status = item.production_status || item.status || 'pending';
        const isDelivered = ['delivered', 'ready'].includes(status);

        if (activeView.value === 'history') return isDelivered;
        return !isDelivered;
    });
});

const currentTime = ref(new Date());
let timer;

const isLossModalVisible = ref(false);
const selectedItemForLoss = ref(null);

const openLossModal = (item) => {
    selectedItemForLoss.value = item;
    isLossModalVisible.value = true;
};

const handleLossConfirm = (lossData) => {
    isLossModalVisible.value = false;

    emit('loss-item', {
        item: selectedItemForLoss.value,
        isLoss: lossData.isLoss,
        reason: lossData.reason,
        password: lossData.password
    });

    setTimeout(() => { selectedItemForLoss.value = null; }, 300);
};

onMounted(() => {
    isMounted.value = true;
    timer = setInterval(() => { currentTime.value = new Date(); }, 15000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<style scoped>
.list-enter-active, .list-leave-active { transition: all 0.4s ease; }
.list-enter-from { opacity: 0; transform: translateY(20px) scale(0.95); }
.list-leave-to { opacity: 0; transform: scale(0.9); }
</style>
