<template>
    <component
        :is="mode === 'inline' ? 'div' : AdaptiveModal"
        v-model:visible="isVisible"
        class="flex flex-col h-full w-full"
        :class="mode === 'inline' ? 'absolute inset-0 bg-surface-2 p-6 animate-in fade-in slide-from-right-5 duration-200' : ''"
    >
        <!-- CABEÇALHO ADAPTATIVO -->
        <div class="flex items-center gap-3 mb-6 shrink-0" :class="mode === 'inline' ? '' : 'mt-2 justify-center flex-col text-center'">
            <SecureActionButton v-if="mode === 'inline'" action-code="cancel" icon="pi-times" variant="text" severity="secondary" custom-class="w-10 h-10 rounded-full shrink-0" @secure-click="handleCancel" />
            <div v-else class="w-14 h-14 rounded-full flex items-center justify-center shrink-0" :class="iconColors.bg">
                <i :class="['pi', iconColors.icon, 'text-2xl', iconColors.text]"></i>
            </div>

            <div class="flex flex-col" :class="mode === 'inline' ? '' : 'items-center'">
                <h2 class="text-xl font-black text-surface-text leading-tight">{{ title }}</h2>
                <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted mt-0.5" v-if="displayItemName">
                    {{ displayItemName }}
                </span>
            </div>
        </div>

        <!-- FORMULÁRIO ROLÁVEL -->
        <div class="flex flex-col gap-4 flex-1 overflow-y-auto hide-scrollbar pr-0.5 pb-4">

            <!-- Opções Pré-definidas de Motivo com SelectableCard -->
            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">
                    Selecione o Motivo
                </label>
                <div class="flex flex-col gap-2">
                    <SelectableCard
                        v-for="r in reasons"
                        :key="r"
                        :title="r"
                        :is-selected="form.reason === r"
                        :color="cardColor"
                        :show-checkmark="true"
                        icon="pi-comment"
                        @select="form.reason = r"
                    />
                </div>
            </div>

            <!-- Campo de Texto / Justificativa com Validação SEFAZ -->
            <div class="flex flex-col gap-1.5">
                <div class="flex justify-between items-center">
                    <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest">
                        {{ isFiscalAction ? 'Justificativa SEFAZ (Mínimo 15 caracteres)' : 'Ou digite uma justificativa' }}
                    </label>
                    <span v-if="isFiscalAction" class="text-[10px] font-black tracking-wider" :class="reasonLength >= 15 ? 'text-success-500' : 'text-amber-500'">
                        {{ reasonLength }}/15 mín.
                    </span>
                </div>
                <textarea
                    v-model="form.reason"
                    rows="2"
                    :placeholder="isFiscalAction ? 'Selecione uma opção acima ou descreva (mínimo 15 caracteres para a SEFAZ)...' : 'Descreva o motivo detalhado...'"
                    class="w-full p-3 rounded-xl bg-surface-1 border border-surface-border font-medium text-xs text-surface-text outline-none focus:border-primary-500 transition-colors resize-none leading-relaxed"
                ></textarea>
                <span v-if="isFiscalAction && reasonLength > 0 && reasonLength < 15" class="text-[10px] font-medium text-amber-500 leading-tight">
                    A SEFAZ exige ao menos 15 caracteres para autorizar o cancelamento fiscal.
                </span>
            </div>

            <!-- Toggle de Desperdício e Checklist -->
            <div v-if="showLossOptions" class="flex flex-col gap-2 p-4 bg-surface-1 border border-surface-border rounded-xl transition-all">
                <div class="flex items-center justify-between cursor-pointer outline-none" @click="form.isLoss = !form.isLoss">
                    <div class="flex flex-col">
                        <span class="font-black text-sm text-surface-text">Houve Desperdício?</span>
                        <span class="text-[10px] font-medium text-surface-muted leading-tight mt-0.5">Abater do estoque e registrar perda</span>
                    </div>
                    <div class="w-12 h-6 rounded-full transition-colors relative" :class="form.isLoss ? 'bg-orange-500' : 'bg-surface-border'">
                        <div class="absolute top-1 bottom-1 w-4 bg-white rounded-full transition-all shadow-sm" :class="form.isLoss ? 'left-[26px]' : 'left-1'"></div>
                    </div>
                </div>

                <div v-if="form.isLoss && isOrderContext && orderItems.length > 0" class="mt-4 pt-4 border-t border-surface-border animate-in fade-in slide-from-top-2">
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-[10px] font-black text-orange-500 uppercase tracking-widest">O que foi perdido?</label>
                        <button type="button" @click="toggleAllItems" class="text-[10px] font-bold text-primary-500 hover:underline bg-transparent border-none outline-none cursor-pointer p-0">
                            {{ form.lostItems.length === orderItems.length ? 'Desmarcar Tudo' : 'Marcar Tudo' }}
                        </button>
                    </div>
                    <div class="flex flex-col gap-2 max-h-[180px] overflow-y-auto hide-scrollbar pr-1">
                        <SelectableCard
                            v-for="item in orderItems"
                            :key="getItemId(item)"
                            :title="getItemTitle(item)"
                            :subtitle="getItemSubtitle(item)"
                            :is-selected="form.lostItems.includes(getItemId(item))"
                            color="warning"
                            :show-checkmark="true"
                            icon="pi-box"
                            @select="toggleLossItem(getItemId(item))"
                        />
                    </div>
                </div>
            </div>

            <!-- Senha do Gestor -->
            <div v-if="requiresPin" class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-surface-muted uppercase tracking-widest flex items-center gap-1">
                    <i class="pi pi-lock"></i> Senha de Autorização
                </label>
                <input
                    v-model="form.password"
                    type="password"
                    placeholder="••••"
                    maxlength="6"
                    class="w-full h-12 px-4 rounded-xl bg-surface-1 border border-surface-border font-black text-center tracking-[0.5em] text-lg text-surface-text outline-none focus:border-primary-500 transition-colors"
                />
            </div>
        </div>

        <!-- RODAPÉ DE AÇÕES -->
        <div class="flex gap-2 w-full mt-auto shrink-0 pt-4 border-t border-surface-border/50">
            <SecureActionButton action-code="cancel" label="Voltar" variant="text" severity="secondary" custom-class="flex-1 font-medium cursor-pointer" @secure-click="handleCancel" />
            <SecureActionButton
                action-code="confirm_reason"
                :label="confirmLabel"
                :severity="['cancel', 'cancel_order', 'rollback_payment'].includes(actionType) ? 'danger' : (actionType === 'correct_payment' ? 'warning' : 'primary')"
                :disabled="!isValid"
                custom-class="flex-1 font-black shadow-md cursor-pointer"
                @secure-click="handleConfirm"
            />
        </div>
    </component>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import SelectableCard from '@/Components/UI/SelectableCard.vue';

const props = defineProps({
    visible: Boolean,
    mode: { type: String, default: 'modal' },
    itemName: String,
    actionType: String,
    requiresPin: Boolean,
    itemToProcess: Object
});

const emit = defineEmits(['update:visible', 'cancel', 'confirm']);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});

const form = ref({ reason: null, isLoss: false, lostItems: [], password: '' });

watch(() => props.visible, (isOpen) => {
    if (isOpen) {
        form.value = { reason: null, isLoss: false, lostItems: [], password: '' };
    }
}, { immediate: true });

const isOrderContext = computed(() => props.actionType === 'cancel_order');

const isFiscalAction = computed(() => ['cancel_order', 'reactivate', 'correct_payment'].includes(props.actionType));

const reasonLength = computed(() => (form.value.reason || '').trim().length);

const cardColor = computed(() => {
    if (['cancel', 'cancel_order'].includes(props.actionType)) return 'red';
    if (['rollback_payment', 'correct_payment'].includes(props.actionType)) return 'warning';
    if (props.actionType === 'reactivate') return 'primary';
    return 'primary';
});

const displayItemName = computed(() => {
    if (props.itemName) return props.itemName;
    if (props.itemToProcess) {
        return props.itemToProcess.name
            || props.itemToProcess.product_name
            || props.itemToProcess.product?.name
            || null;
    }
    return null;
});

const orderItems = computed(() => {
    if (isOrderContext.value && props.itemToProcess?.items) {
        return props.itemToProcess.items.filter(i => i.status !== 'cancelled');
    }
    return [];
});

const getItemId = (item) => {
    return item?.id ?? item?.item_id ?? item?.cartId ?? null;
};

const getItemTitle = (item) => {
    if (!item) return 'Item';
    const name = item.name
        || item.product_name
        || item.product?.name
        || item.title
        || 'Item';

    const qty = item.qty
        ?? item.quantity
        ?? item.base_qty
        ?? 1;

    return `${qty}x ${name}`;
};

const getItemSubtitle = (item) => {
    if (!item) return null;
    if (item.notes && item.notes.trim()) return item.notes;
    if (item.observation && item.observation.trim()) return item.observation;
    if (Array.isArray(item.structuredModifiers) && item.structuredModifiers.length > 0) {
        return item.structuredModifiers.map(m => m.name || m).filter(Boolean).join(', ');
    }
    return null;
};

watch(() => form.value.isLoss, (newVal) => {
    if (newVal && isOrderContext.value && orderItems.value.length > 0) {
        form.value.lostItems = orderItems.value.map(i => getItemId(i)).filter(Boolean);
    } else {
        form.value.lostItems = [];
    }
});

const toggleLossItem = (id) => {
    if (!id) return;
    const idx = form.value.lostItems.indexOf(id);
    if (idx > -1) form.value.lostItems.splice(idx, 1);
    else form.value.lostItems.push(id);
};

const toggleAllItems = () => {
    const allIds = orderItems.value.map(i => getItemId(i)).filter(Boolean);
    if (form.value.lostItems.length === allIds.length) {
        form.value.lostItems = [];
    } else {
        form.value.lostItems = [...allIds];
    }
};

const title = computed(() => {
    if (props.actionType === 'cancel') return 'Estornar Item';
    if (props.actionType === 'cancel_order') return 'Cancelar Pedido';
    if (props.actionType === 'rollback_payment') return 'Estornar Pagamento';
    if (props.actionType === 'reactivate') return 'Reativar Pedido';
    if (props.actionType === 'correct_payment') return 'Corrigir Pagamento';
    if (props.actionType === 'edit' || props.actionType === 'swap') return 'Alteração de Item';
    return 'Autorização';
});

const iconColors = computed(() => {
    if (props.actionType === 'cancel' || props.actionType === 'cancel_order') {
        return { bg: 'bg-red-500/10', icon: 'pi-times-circle', text: 'text-red-600' };
    }
    if (props.actionType === 'rollback_payment' || props.actionType === 'correct_payment') {
        return { bg: 'bg-amber-500/10', icon: 'pi-exclamation-triangle', text: 'text-amber-500' };
    }
    if (props.actionType === 'reactivate') {
        return { bg: 'bg-primary-500/10', icon: 'pi-refresh', text: 'text-primary-600' };
    }
    return { bg: 'bg-primary-500/10', icon: 'pi-pencil', text: 'text-primary-600' };
});

const showLossOptions = computed(() => ['cancel', 'cancel_order', 'swap'].includes(props.actionType));

const reasons = computed(() => {
    if (props.actionType === 'reactivate') {
        return [
            'Reabertura para inclusão de novos itens',
            'Reabertura solicitada pelo cliente',
            'Fechamento indevido pelo operador',
            'Ajuste operacional de comanda'
        ];
    }
    if (props.actionType === 'correct_payment') {
        return [
            'Forma de pagamento lançada incorreta',
            'Cliente solicitou troca da forma de pagamento',
            'Divisão de valores lançada incorretamente',
            'Ajuste de bandeira ou modalidade do cartão'
        ];
    }
    if (props.actionType === 'rollback_payment') {
        return [
            'Erro no lançamento da forma de pagamento',
            'Cliente solicitou troca do meio de pagamento',
            'Divisão de conta lançada incorretamente',
            'Pagamento duplicado no sistema'
        ];
    }
    if (props.actionType === 'cancel' || props.actionType === 'cancel_order') {
        return [
            'Erro de lançamento operacional do pedido',
            'Desistência do pedido pelo cliente',
            'Falta de insumos para preparo do prato',
            'Demora excessiva na produção ou entrega'
        ];
    }
    return [
        'Troca solicitada pelo cliente',
        'Erro no lançamento do pedido',
        'Falta de insumos para preparo',
        'Problema na qualidade do item'
    ];
});

const confirmLabel = computed(() => {
    if (props.actionType === 'cancel' || props.actionType === 'cancel_order' || props.actionType === 'rollback_payment') {
        return 'Confirmar Estorno';
    }
    if (props.actionType === 'reactivate') {
        return 'Reativar Pedido';
    }
    if (props.actionType === 'correct_payment') {
        return 'Corrigir Pagamento';
    }
    return 'Confirmar Alteração';
});

const isValid = computed(() => {
    const trimmedReason = (form.value.reason || '').trim();
    if (!trimmedReason) return false;
    if (isFiscalAction.value && trimmedReason.length < 15) return false;
    if (props.requiresPin && (!form.value.password || form.value.password.length < 4)) return false;
    if (form.value.isLoss && isOrderContext.value && form.value.lostItems.length === 0) return false;
    return true;
});

const handleCancel = () => {
    emit('cancel');
    if (props.mode !== 'inline') isVisible.value = false;
};

const handleConfirm = () => {
    emit('confirm', {
        reason: (form.value.reason || '').trim(),
        isLoss: form.value.isLoss,
        lostItems: form.value.lostItems,
        password: form.value.password
    });
};
</script>
