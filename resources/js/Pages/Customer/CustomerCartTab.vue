<template>
    <div
        class="w-full h-full flex flex-col overflow-hidden shrink-0"
        :style="{ top: 'var(--app-header-height, 7rem)' }"
    >

        <!-- ==========================================
             CABEÇALHO
             ========================================== -->
        <div class="p-6 pb-4 pt-12 shrink-0 flex items-center justify-between">
            <div class="flex flex-col">
                <h2 class="c-title">{{ appendOrder ? 'Adicionar ao Pedido' : 'Revise seu Pedido' }}</h2>
                <p v-if="cart.length > 0 || appendOrder" class="c-text-muted mt-1 uppercase tracking-widest text-[11px]">
                    <span v-if="appendOrder" class="text-amber-400 font-bold">Complementando Pedido #{{ $formatNumber(appendOrder.id) }}</span>
                    <span v-else>{{ cart.length }} {{ cart.length === 1 ? 'item selecionado' : 'itens selecionados' }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    v-if="appendOrder"
                    @click="() => { console.log('[SONDA: CustomerCartTab] Cancelar append acionado'); $emit('cancel-append'); }"
                    class="c-btn-ghost !h-9 !px-3 text-white/70 hover:text-white"
                    title="Desistir do complemento"
                >
                    <i class="pi pi-times text-xs"></i>
                    <span>Cancelar</span>
                </button>

                <button
                    v-if="cart.length > 0"
                    @click="() => { console.log('[SONDA: CustomerCartTab] Limpar carrinho acionado'); $emit('clear-cart'); }"
                    class="c-btn-ghost !h-9 !px-3 text-red-400 active:text-red-300"
                >
                    <i class="pi pi-trash text-xs"></i>
                    <span class="hidden sm:inline">Limpar</span>
                </button>
            </div>
        </div>

        <!-- ==========================================
             LISTAGEM DE ITENS
             ========================================== -->
        <div class="flex-1 overflow-y-auto px-4 pt-2 flex flex-col gap-4 hide-scrollbar relative pb-[calc(env(safe-area-inset-bottom,1rem)+7rem)]">

            <!-- 🛑 BLOCO 1: ITENS JÁ ENVIADOS (Modo Append - Somente Leitura) -->
            <div v-if="appendOrder && appendOrder.items?.length > 0" class="flex flex-col gap-2">
                <div class="flex items-center justify-between px-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted flex items-center gap-1.5">
                        <i class="pi pi-lock text-[10px]"></i> Já em Preparo (Não alterável)
                    </span>
                    <span class="text-[10px] font-bold text-amber-400">Pedido #{{ $formatNumber(appendOrder.id) }}</span>
                </div>

                <div class="c-glass-card !bg-white/5 border border-white/10 p-3 flex flex-col gap-2 opacity-70 select-none">
                    <div v-for="(oldItem, oIdx) in appendOrder.items" :key="'old_' + oIdx" class="flex justify-between items-center text-xs py-1 border-b border-white/5 last:border-none">
                        <div class="flex items-center gap-2 text-surface-text">
                            <span class="font-bold text-white/60">{{ oldItem.quantity }}×</span>
                            <span class="font-medium line-clamp-1">{{ oldItem.product_name || oldItem.name }}</span>
                        </div>
                        <span class="font-bold text-white/80 shrink-0">{{ $formatCurrency(oldItem.total_price || oldItem.price) }}</span>
                    </div>
                </div>
            </div>

            <!-- 🛒 BLOCO 2: NOVOS ITENS (Carrinho Editável) -->
            <div class="flex flex-col gap-2">
                <span v-if="appendOrder" class="text-[10px] font-black uppercase tracking-widest text-emerald-400 px-1 flex items-center gap-1.5 mt-2">
                    <i class="pi pi-plus-circle text-[10px]"></i> Novos Itens a Adicionar
                </span>

                <!-- EMPTY STATE -->
                <div v-if="cart.length === 0" class="c-glass-card p-8 flex flex-col items-center justify-center text-center mx-2" :class="appendOrder ? 'mt-4' : 'mt-20'">
                    <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-3">
                        <i class="pi pi-shopping-bag text-2xl text-white/40"></i>
                    </div>
                    <h3 class="c-title text-base mb-1">{{ appendOrder ? 'Escolha novos itens' : 'Carrinho Vazio' }}</h3>
                    <p class="c-text-muted px-4 mb-4 text-xs">
                        {{ appendOrder ? 'Navegue no cardápio para adicionar bebidas, sobremesas ou pratos ao pedido em andamento.' : 'Nenhum produto foi adicionado ao seu pedido ainda.' }}
                    </p>

                    <button @click="$emit('go-to-menu')" class="c-btn-primary max-w-xs !h-11 !text-xs">
                        Explorar Cardápio
                    </button>
                </div>

                <!-- ITENS EDITÁVEIS -->
                <div v-for="(item, index) in cart" :key="item.cartId || index" class="flex flex-col">
                    <CustomerOrderLineItem
                        :title="item.name"
                        :qty="item.qty"
                        :price="item.price * item.qty"
                        :structured-modifiers="item.structuredModifiers"
                        :combo-items="item.comboItems"
                        :is-takeaway="item.isTakeaway"
                        :is-customer-mode="true"
                    >
                        <template #actions>
                            <div class="flex items-center justify-between w-full gap-2 cursor-default" @click.stop>
                                <QtyStepper
                                    :model-value="item.qty"
                                    :min="1"
                                    @increment="$emit('update-qty', index, 1)"
                                    @decrement="$emit('update-qty', index, -1)"
                                    size="sm"
                                    class="bg-white/5!"
                                >
                                    <template #value><span class="text-xs text-white font-black">{{ item.qty }}</span></template>
                                </QtyStepper>

                                <div class="flex items-center gap-2">
                                    <button
                                        @click="$emit('edit-item', item, index)"
                                        type="button"
                                        class="c-btn-icon"
                                        title="Editar Adicionais"
                                    >
                                        <i class="pi pi-pencil text-xs"></i>
                                    </button>
                                    <button
                                        @click="$emit('update-qty', index, -item.qty)"
                                        type="button"
                                        class="c-btn-icon-danger"
                                        title="Remover"
                                    >
                                        <i class="pi pi-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </CustomerOrderLineItem>
                </div>
            </div>

            <!-- ==========================================
                 RODAPÉ FINANCEIRO & AVISOS
                 ========================================== -->
            <template v-if="cart.length > 0">

                <!-- Resumo Repactuado (Se for Complemento) -->
                <div v-if="appendOrder" class="c-glass-card p-4 rounded-2xl flex flex-col gap-2 border border-white/10 mt-2 mb-2">
                    <div class="flex justify-between text-xs text-white/70">
                        <span>Novos Itens Adicionados</span>
                        <span class="font-bold text-white">{{ $formatCurrency(cartSubtotal) }}</span>
                    </div>

                    <div class="flex justify-between text-xs text-white/70">
                        <span>Nova Taxa de Entrega</span>
                        <span class="text-emerald-400 font-bold">Grátis (Mesmo Envio)</span>
                    </div>

                    <!-- LINHA DA TAXA DE SERVIÇO EM COMPLEMENTO -->
                    <div v-if="hasServiceTaxGlobal" class="flex justify-between text-xs text-white/70">
                        <span>Taxa de Serviço {{ serviceTaxPercentage > 0 ? `(${serviceTaxPercentage}%)` : '' }}</span>
                        <span v-if="appendServiceFee === 0" class="text-emerald-400 font-bold uppercase tracking-wider text-[11px]">R$ 0,00</span>
                        <span v-else class="text-white font-bold">+ {{ $formatCurrency(appendServiceFee) }}</span>
                    </div>

                    <div class="h-px bg-white/10 my-1"></div>

                    <div class="flex justify-between text-sm font-black text-white">
                        <span>Total Adicional a Pagar</span>
                        <span class="text-primary-400">{{ $formatCurrency(appendTotal) }}</span>
                    </div>
                </div>

                <!-- Aviso de Loja Fechada -->
                <CustomerClosedCheckoutBlock
                    v-if="!canOrder"
                    :is-guest="isGuest"
                    :customer="customer"
                    @open-register="$emit('open-register')"
                    class="mt-2 mb-8"
                />

                <!-- Se a loja estiver aberta, exibimos o indicador para a Dynamic Island -->
                <div v-else class="h-20 mt-2 mb-8 flex flex-col items-center justify-center opacity-50 select-none pointer-events-none">
                    <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted text-center max-w-[200px]">
                        {{ appendOrder ? 'Deslize o botão abaixo para confirmar os adicionais' : 'Deslize o botão no rodapé para finalizar' }}
                    </span>
                    <i class="pi pi-arrow-down mt-2 animate-bounce"></i>
                </div>

            </template>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { formatCurrency } from '@/Utils/formatters';
import CustomerOrderLineItem from "@/Pages/Customer/Cart/CustomerOrderLineItem.vue";
import QtyStepper from "@/Components/UI/QtyStepper.vue";
import CustomerClosedCheckoutBlock from "@/Pages/Customer/Cart/CustomerClosedCheckoutBlock.vue";
import { useStoreStatus } from '@/Composables/useStoreStatus';

const props = defineProps({
    cart: { type: Array, required: true },
    cartSubtotal: { type: Number, required: true },
    isGuest: { type: Boolean, default: false },
    customer: { type: Object, default: null },
    appendOrder: { type: Object, default: null }
});

defineEmits(['update-qty', 'clear-cart', 'edit-item', 'checkout', 'go-to-menu', 'open-register', 'cancel-append']);

const page = usePage();
const { canOrder } = useStoreStatus();

const companySettings = computed(() => page.props.companySettings || {});
const hasServiceTaxGlobal = computed(() => Boolean(companySettings.value.has_service_tax));
const serviceTaxPercentage = computed(() => Number(companySettings.value.service_tax_percentage || 0));

const isTaxEnabledForChannel = computed(() => {
    if (!hasServiceTaxGlobal.value) return false;
    const type = props.appendOrder?.type || 'delivery';
    if (type === 'table') {
        return companySettings.value.service_tax_table !== false && companySettings.value.service_tax_table !== 0;
    }
    if (type === 'counter' || type === 'takeaway' || type === 'pickup') {
        return Boolean(companySettings.value.service_tax_counter);
    }
    if (type === 'delivery') {
        return Boolean(companySettings.value.service_tax_delivery);
    }
    return false;
});

const appendServiceFee = computed(() => {
    if (!props.appendOrder || !isTaxEnabledForChannel.value) return 0;
    return Math.round((props.cartSubtotal * (serviceTaxPercentage.value / 100)) * 100) / 100;
});

const appendTotal = computed(() => {
    return props.cartSubtotal + appendServiceFee.value;
});
</script>
