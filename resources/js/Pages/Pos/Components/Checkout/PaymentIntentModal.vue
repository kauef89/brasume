<template>
    <component
        :is="mode === 'inline' ? 'div' : AdaptiveModal"
        v-model:visible="isVisible"
        class="flex flex-col h-full w-full"
        :class="mode === 'inline' ? 'p-6' : ''"
    >
        <!-- CABEÇALHO DINÂMICO -->
        <div class="flex flex-col items-center justify-center gap-3 shrink-0 mb-4 md:mb-6">
            <div class="w-14 h-14 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center">
                <i class="pi text-2xl" :class="isCounter ? 'pi-shopping-bag' : 'pi-motorcycle'"></i>
            </div>
            <div class="flex flex-col items-center text-center">
                <h2 class="text-xl font-black text-surface-text leading-tight">
                    {{ isCounter ? 'Pagamento no Balcão' : 'Instruções de Entrega' }}
                </h2>
                <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 mt-0.5">
                    {{ isCounter ? 'Como o cliente deseja pagar?' : 'Instruções para o Motoboy' }}
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-4 flex-1 overflow-y-auto hide-scrollbar">
            <!-- VALOR TOTAL DO PEDIDO -->
            <div class="bg-surface-1 p-4 rounded-xl flex justify-between items-center border border-surface-border shrink-0">
                <span class="text-xs font-black text-surface-muted uppercase tracking-wider">Total</span>
                <span class="text-2xl font-black text-primary-smart">{{ $formatCurrency(orderTotal || 0) }}</span>
            </div>

            <!-- FASE 1: SELEÇÃO DA INTENÇÃO -->
            <div
                v-if="currentStep === 'mode'"
                class="flex flex-col gap-3 shrink-0 transition-opacity"
                :class="{ 'pointer-events-none': processingStatus === 'loading' }"
            >
                <SelectableCard
                    v-if="!hidePendingChoice"
                    @select="selectMode('pending_choice')"
                    :is-selected="finalIntent === 'pending_choice'"
                    :status="processingMode === 'pending_choice' ? processingStatus : 'idle'"
                    :show-checkmark="true"
                    color="purple"
                    icon="pi-clock"
                    title="Decidir Depois"
                    :subtitle="isCounter ? 'Envia para a cozinha. O pagamento será cobrado ao entregar o produto.' : 'Envia para a cozinha. Será definido na expedição.'"
                />

                <SelectableCard
                    v-if="!isCounter"
                    @select="selectMode('pay_on_delivery')"
                    :is-selected="finalIntent === 'pay_on_delivery'"
                    :status="processingMode === 'pay_on_delivery' ? processingStatus : 'idle'"
                    :show-checkmark="true"
                    color="primary"
                    icon="pi-motorcycle"
                    title="Pagar na Entrega (Motoboy)"
                    subtitle="Compor o pagamento para o motoboy levar troco ou máquina."
                />

                <SelectableCard
                    v-if="isCounter"
                    @select="selectMode('pay_on_counter')"
                    :is-selected="finalIntent === 'pay_on_counter'"
                    :status="processingMode === 'pay_on_counter' ? processingStatus : 'idle'"
                    :show-checkmark="true"
                    color="primary"
                    icon="pi-tag"
                    title="Pagar na Retirada / Balcão"
                    subtitle="Registrar a promessa (dinheiro, troco, cartão ou PIX) para receber quando o cliente buscar."
                />

                <SelectableCard
                    @select="selectMode('pay_now')"
                    :is-selected="finalIntent === 'pay_now'"
                    :status="processingMode === 'pay_now' ? processingStatus : 'idle'"
                    :show-checkmark="true"
                    color="green"
                    icon="pi-wallet"
                    title="Pagar Agora no Caixa"
                    :subtitle="isCounter ? 'Abre o recebimento imediatamente para cobrar o cliente à frente.' : 'Recebe o valor agora no balcão antes da saída do motoboy.'"
                />
            </div>

            <!-- FASE 2: COMPOSIÇÃO DE FORMAS E TROCO -->
            <div v-if="currentStep === 'payment_panel'" class="animate-fade-in flex flex-col gap-4 shrink-0">
                <div v-if="isOverpaid" class="p-4 bg-danger-500/10 border border-danger-500/30 rounded-xl flex items-start gap-3 text-danger-500">
                    <i class="pi pi-exclamation-triangle mt-0.5"></i>
                    <div class="text-sm font-medium">
                        <p>O valor alocado excede o total do pedido!</p>
                        <p class="text-xs font-normal mt-1 opacity-80">Por favor, ajuste os valores nos pagamentos adicionados.</p>
                    </div>
                </div>

                <PaymentStagingArea
                    :available-methods="resolvedPaymentMethods"
                    :staged-payments="promises"
                    :remaining-amount="remainingAmount"
                    :allow-change-target="true"
                    @add-payment="handleAddPromise"
                    @remove-payment="handleRemovePromise"
                />
            </div>
        </div>

        <!-- RODAPÉ -->
        <div v-if="currentStep === 'mode'" class="flex w-full shrink-0 mt-auto pt-6 border-t border-surface-border/50">
            <SecureActionButton
                action-code="cancel"
                label="Cancelar"
                variant="text"
                severity="secondary"
                custom-class="font-medium flex-1"
                :disabled="processingStatus === 'loading'"
                @secure-click="handleCancel"
            />
        </div>

        <div v-else class="flex gap-2 w-full shrink-0 mt-auto pt-6 border-t border-surface-border/50">
            <Button
                label="Voltar"
                text
                severity="secondary"
                :disabled="processingStatus !== 'idle'"
                @click="currentStep = 'mode'"
                class="flex-1 font-medium cursor-pointer"
            />

            <Button
                :label="processingStatus === 'loading' ? 'Processando...' : (processingStatus === 'success' ? 'Concluído!' : 'Confirmar')"
                :icon="processingStatus === 'loading' ? 'pi pi-spinner pi-spin' : 'pi pi-check'"
                :disabled="remainingAmount > 0 || isOverpaid || processingStatus !== 'idle'"
                :severity="processingStatus === 'error' ? 'danger' : (processingStatus === 'success' ? 'success' : 'primary')"
                @click="handleConfirmFinal"
                class="flex-1 font-black shadow-md cursor-pointer transition-all"
            />
        </div>
    </component>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';

import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";
import PaymentStagingArea from "@/Components/UI/PaymentStagingArea.vue";
import SelectableCard from "@/Components/UI/SelectableCard.vue";
import SecureActionButton from '@/Components/UI/SecureActionButton.vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    visible: Boolean,
    orderTotal: { type: Number, default: 0 },
    existingIntent: String,
    existingMethods: [Array, String, Object],
    mode: { type: String, default: 'modal' },
    hidePendingChoice: { type: Boolean, default: false },
    paymentMethods: { type: Array, default: () => [] },
    customer: { type: Object, default: null },
    order: { type: Object, default: null },
    orderType: { type: String, default: null }
});

const emit = defineEmits(['update:visible', 'confirm', 'cancel']);
const page = usePage();

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});

const isCounter = computed(() => {
    const rawType = props.orderType || props.order?.type || '';
    return ['counter', 'pickup', 'takeaway'].includes(rawType);
});

const currentStep = ref('mode');
const finalIntent = ref('pending_choice');
const promises = ref([]);

const processingMode = ref(null);
const processingStatus = ref('idle');

const resetProcessing = () => {
    processingMode.value = null;
    processingStatus.value = 'idle';
};

const handleSuccess = () => {
    processingStatus.value = 'success';
    setTimeout(() => {
        if (props.mode !== 'inline') isVisible.value = false;
        resetProcessing();
    }, 600);
};

const handleError = () => {
    processingStatus.value = 'error';
    setTimeout(() => resetProcessing(), 2500);
};

const resolvedPaymentMethods = computed(() => {
    const rawMethods = props.paymentMethods?.length > 0
        ? props.paymentMethods
        : (page.props.paymentMethods || page.props.cashierData?.paymentMethods || []);

    if (!rawMethods || rawMethods.length === 0) return [];

    const activeCustomer = props.customer || props.order?.customer;
    const settings = page.props.companySettings || {};
    const conversionRate = Number(settings.loyalty_cashback_conversion_rate || 5);
    const customerPoints = Number(activeCustomer?.loyalty_points || 0);
    const availableCashback = roundNumber((customerPoints / 100) * conversionRate);

    return rawMethods
        .filter(m => m.is_active !== false && m.status !== 'inactive')
        .map(m => {
            const isCash = m.engine_type === 'cash' || String(m.name).toLowerCase().includes('dinheiro');
            const isLoyalty = m.engine_type === 'internal_loyalty' || String(m.name).toLowerCase().includes('cashback');

            let icon = m.icon;
            if (!icon) {
                if (isCash) icon = 'pi-money-bill';
                else if (isLoyalty) icon = 'pi-star';
                else if (String(m.name).toLowerCase().includes('pix')) icon = 'pi-qrcode';
                else icon = 'pi-credit-card';
            }

            let subtitle = m.subtitle || null;
            let disabled = false;

            if (isLoyalty) {
                if (!activeCustomer) {
                    subtitle = 'Cliente não identificado';
                    disabled = true;
                } else if (availableCashback <= 0) {
                    subtitle = 'Sem saldo de pontos';
                    disabled = true;
                } else {
                    subtitle = `Saldo: ${formatCurrency(availableCashback)}`;
                }
            }

            return {
                id: m.id,
                name: m.name,
                engine_type: m.engine_type,
                is_cash: isCash,
                icon,
                subtitle,
                disabled,
                max_value: isLoyalty ? availableCashback : null,
                colorClass: m.colorClass || null
            };
        });
});

const roundNumber = (num) => Math.round((Number(num) + Number.EPSILON) * 100) / 100;

watch(() => props.visible, (val) => {
    if (val) {
        resetProcessing();
        if (props.existingIntent && props.existingIntent !== 'pending_choice') {
            finalIntent.value = props.existingIntent;
            if (['pay_on_delivery', 'pay_on_counter', 'pay_on_pickup'].includes(props.existingIntent)) {
                currentStep.value = 'payment_panel';
                // 🚀 Aqui carregamos o método esticado que veio do hook useOrderAppend!
                let parsed = props.existingMethods;
                while (typeof parsed === 'string') {
                    try { parsed = JSON.parse(parsed); } catch (e) { break; }
                }
                promises.value = Array.isArray(parsed) ? [...parsed] : [];
            } else {
                currentStep.value = 'mode';
                promises.value = [];
            }
        } else {
            currentStep.value = 'mode';
            finalIntent.value = 'pending_choice';
            promises.value = [];
        }
    }
}, { immediate: true });

const allocatedAmount = computed(() => promises.value.reduce((sum, p) => sum + (Number(p.amount) || 0), 0));
const remainingAmount = computed(() => Math.max(0, Number(((props.orderTotal || 0) - allocatedAmount.value).toFixed(2))));
const isOverpaid = computed(() => allocatedAmount.value > (props.orderTotal || 0) + 0.01);

const handleCancel = () => {
    emit("cancel");
    if (props.mode !== 'inline') isVisible.value = false;
};

const selectMode = (mode) => {
    if (processingStatus.value === 'loading') return;

    finalIntent.value = mode;

    if (mode === 'pay_on_delivery' || mode === 'pay_on_counter') {
        currentStep.value = 'payment_panel';
    } else {
        processingMode.value = mode;
        processingStatus.value = 'loading';

        setTimeout(() => {
            emit('confirm', {
                intent: mode,
                payment_intent_status: mode,
                payment_intent_methods: null,
                onSuccess: handleSuccess,
                onError: handleError
            });

            setTimeout(() => {
                if (processingStatus.value === 'loading') {
                    handleSuccess();
                }
            }, 1500);
        }, 50);
    }
};

const handleAddPromise = (payload) => {
    const existingIndex = promises.value.findIndex(p => p.method_id === payload.method_id);
    if (existingIndex > -1) {
        const existing = promises.value[existingIndex];
        existing.amount = roundNumber(existing.amount + Number(payload.amount));
        if (payload.change_target) existing.change_target = payload.change_target;
        if (existing.change_target && existing.change_target < existing.amount) existing.change_target = null;
    } else {
        promises.value.push({
            method_id: payload.method_id,
            method_name: payload.method_name,
            engine_type: payload.engine_type,
            amount: Number(payload.amount),
            change_target: payload.change_target || null
        });
    }
};

const handleRemovePromise = (idx) => promises.value.splice(idx, 1);

const handleConfirmFinal = () => {
    if (processingStatus.value === 'loading') return;

    processingStatus.value = 'loading';
    const resolvedStatus = isCounter.value ? 'pay_on_pickup' : 'pay_on_delivery';
    processingMode.value = resolvedStatus;

    setTimeout(() => {
        // 🚀 O pulo do gato: Entrega exatamente as promessas (promises.value) ajustadas!
        emit('confirm', {
            intent: resolvedStatus,
            payment_intent_status: resolvedStatus,
            payment_intent_methods: promises.value,
            onSuccess: handleSuccess,
            onError: handleError
        });

        setTimeout(() => {
            if (processingStatus.value === 'loading') {
                handleSuccess();
            }
        }, 1500);
    }, 50);
};
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.2s ease-in-out; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
