<template>
    <div
        class="bg-surface-1 rounded-2xl p-4 shadow-sm border transition-all flex flex-col gap-3"
        :class="hasDrink ? 'border-amber-500/50 bg-amber-500/[0.02]' : 'border-surface-border'"
    >
        <!-- TOPO: Identificação (#1), Bairro (#4), Bebida (#6) e Chevron (#7) -->
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-col min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- 1) Número do Pedido -->
                    <span class="text-xs font-black text-primary-500 uppercase tracking-wider">
                        #{{ order.id }}
                    </span>

                    <!-- 4) Bairro em Chip em Destaque -->
                    <span
                        v-if="neighborhood"
                        class="px-2.5 py-0.5 rounded-lg bg-primary-500/10 text-primary-600 dark:text-primary-400 font-black text-[10px] uppercase tracking-wider truncate"
                    >
                        {{ neighborhood }}
                    </span>

                    <!-- 6) Chip "TEM BEBIDA" (Cozinha type = bar) -->
                    <span
                        v-if="hasDrink"
                        class="px-2 py-0.5 rounded-full bg-amber-500 text-white font-black text-[9px] uppercase tracking-wider flex items-center gap-1 shadow-xs shrink-0"
                    >
                        <i class="pi pi-glass text-[9px]"></i>
                        TEM BEBIDA
                    </span>
                </div>

                <!-- 2) Nome do Cliente Abreviado -->
                <h4 class="font-bold text-sm text-surface-text leading-tight truncate mt-1.5">
                    {{ abbreviateName(customerName) }}
                </h4>
            </div>

            <!-- 7) Ícone de Chevron para Expandir os Itens -->
            <button
                @click="isExpanded = !isExpanded"
                type="button"
                class="w-8 h-8 rounded-xl bg-surface-2 hover:bg-surface-3 text-surface-muted hover:text-surface-text transition-colors flex items-center justify-center shrink-0 border-none cursor-pointer outline-none"
                :title="isExpanded ? 'Recolher itens' : 'Ver itens do pedido'"
            >
                <i
                    class="pi text-xs transition-transform duration-200"
                    :class="isExpanded ? 'pi-chevron-up text-primary-500' : 'pi-chevron-down'"
                ></i>
            </button>
        </div>

        <!-- 3) Morada Clicável com Navegação GPS Direta -->
        <a
            :href="navigationUrl"
            target="_blank"
            class="text-xs text-surface-muted font-medium leading-relaxed bg-surface-2/60 hover:bg-surface-2 p-2.5 rounded-xl border border-surface-border/40 hover:border-primary-500/50 flex items-start justify-between gap-2 transition-all no-underline group cursor-pointer"
            :title="hasExactLocation ? 'Abrir Localização GPS Exata' : 'Pesquisar Morada no Google Maps'"
        >
            <div class="flex items-start gap-2 min-w-0">
                <i class="pi fa-location-dot text-xs mt-0.5 shrink-0" :class="hasExactLocation ? 'text-emerald-500' : 'text-primary-500'"></i>
                <div class="flex flex-col min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-surface-text font-semibold group-hover:text-primary-500 transition-colors">
                            {{ formattedAddress.main }}
                        </span>
                        <span v-if="hasExactLocation" class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            GPS EXATO
                        </span>
                    </div>
                    <span v-if="formattedAddress.extra" class="text-[11px] opacity-80 mt-0.5">
                        {{ formattedAddress.extra }}
                    </span>
                </div>
            </div>
            <div class="shrink-0 flex items-center justify-center w-7 h-7 rounded-lg bg-surface-3/50 group-hover:bg-primary-500 group-hover:text-white transition-all text-surface-muted">
                <i class="pi pi-external-link text-[10px]"></i>
            </div>
        </a>

        <!-- 5) Status Financeiro (PAGO vs Cobrar + Forma + Troco + Cobrança PIX) -->
        <div class="flex items-center justify-between text-xs px-1 font-bold">
            <span class="text-surface-muted text-[11px] uppercase tracking-wider">Pagamento:</span>

            <!-- Cenário 1: Já está Pago -->
            <span
                v-if="order.payment_status === 'paid'"
                class="px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-black text-[10px] uppercase tracking-widest"
            >
                PAGO
            </span>

            <!-- Cenário 2: A Cobrar na Entrega -->
            <div v-else class="flex flex-col items-end gap-1.5">
                <span class="text-danger-500 font-black">
                    Cobrar R$ {{ formatCurrency(totalAmount) }}
                </span>
                <span class="text-[10px] text-surface-muted font-semibold">
                    {{ paymentMethodDescription }}
                </span>

                <!-- ⚡ BOTÃO RECEBER COM PIX (Regra Sicredi Dinâmica) -->
                <button
                    v-if="canChargePix"
                    @click.stop="$emit('charge-pix', order)"
                    type="button"
                    class="mt-0.5 px-2.5 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500 text-emerald-600 dark:text-emerald-400 hover:text-white font-black text-[10px] uppercase tracking-wider transition-colors flex items-center gap-1.5 border border-emerald-500/20 cursor-pointer shadow-xs active:scale-95"
                >
                    <i class="pi pi-qrcode text-[10px]"></i>
                    Receber com PIX
                </button>
            </div>
        </div>

        <!-- 7) Itens Expandidos (sem timer de cozinha) -->
        <div
            v-if="isExpanded"
            class="pt-3 border-t border-surface-border/50 flex flex-col gap-2 animate-in fade-in duration-200"
        >
            <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">
                Itens do Pedido ({{ (order.items || []).length }})
            </span>

            <div class="flex flex-col gap-1.5 max-h-48 overflow-y-auto hide-scrollbar pr-1">
                <div
                    v-for="(item, idx) in order.items || []"
                    :key="item.id || idx"
                    class="p-2 rounded-lg bg-surface-2/40 border border-surface-border/30 flex items-start justify-between gap-2 text-xs"
                >
                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-surface-text leading-tight">
                            <span class="text-primary-500 font-black mr-1">{{ item.quantity || 1 }}x</span>
                            {{ item.product_name || item.name }}
                        </span>

                        <span
                            v-if="item.notes"
                            class="text-[10px] text-surface-muted mt-0.5 leading-tight italic"
                        >
                            Obs: {{ item.notes }}
                        </span>
                    </div>

                    <span v-if="isItemFromBar(item)" class="text-[9px] font-black uppercase text-amber-500 bg-amber-500/10 px-1.5 py-0.5 rounded shrink-0">
                        Bebida
                    </span>
                </div>
            </div>
        </div>

        <!-- Slot de Ações (Botões Pegar / Sair p/ Entrega / Entregue) -->
        <div v-if="$slots.actions" class="pt-1 border-t border-surface-border/40">
            <slot name="actions"></slot>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { abbreviateName } from '@/Utils/formatters';

const props = defineProps({
    order: {
        type: Object,
        required: true
    }
});

defineEmits(['charge-pix']);

const page = usePage();
const isExpanded = ref(false);
const hasExactLocation = computed(() => {
    const addr = props.order.address_snapshot || props.order.address;
    return Boolean(addr && typeof addr === 'object' && addr.location);
});

const navigationUrl = computed(() => {
    const addr = props.order.address_snapshot || props.order.address;
    if (addr && typeof addr === 'object' && addr.location) {
        return addr.location;
    }
    const query = `${formattedAddress.value.main}${neighborhood.value ? ', ' + neighborhood.value : ''}`;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
});

const customerName = computed(() => {
    return props.order.customer_name || props.order.customer?.name || 'Cliente';
});

// 4) Bairro
const neighborhood = computed(() => {
    return props.order.neighborhood ||
        props.order.address?.neighborhood ||
        props.order.address_snapshot?.neighborhood ||
        '';
});

// 6) Verificação se o item pertence à cozinha do tipo 'bar'
const isItemFromBar = (item) => {
    const kitchenType = item.kitchen?.type ||
        item.kitchen_type ||
        item.product?.kitchen?.type;

    return String(kitchenType || '').toLowerCase() === 'bar';
};

const hasDrink = computed(() => {
    const items = props.order.items;
    if (!Array.isArray(items) || items.length === 0) return false;
    return items.some(item => isItemFromBar(item));
});

// 3) Endereço Completo: Rua + Número + Complemento + Referência
const formattedAddress = computed(() => {
    const addr = props.order.address_snapshot || props.order.address;

    if (typeof addr === 'object' && addr !== null) {
        const street = addr.street || addr.street_name || '';
        const number = addr.number || 'S/N';
        const complement = addr.complement ? `, ${addr.complement}` : '';
        const reference = addr.reference ? ` (Ref: ${addr.reference})` : '';

        const main = `${street}, ${number}${complement}`.trim();
        const extra = reference.trim();

        return {
            main: main || 'Endereço cadastrado',
            extra: extra
        };
    }

    if (typeof props.order.address === 'string' && props.order.address.trim()) {
        return {
            main: props.order.address,
            extra: ''
        };
    }

    return {
        main: 'Endereço não informado',
        extra: ''
    };
});

// 5) Valor total devedor real gravado na tabela
const totalAmount = computed(() => {
    const o = props.order;
    if (!o) return 0;

    const alreadyPaid = Array.isArray(o.payments)
        ? o.payments.reduce((acc, p) => acc + Number(p.amount || 0), 0)
        : 0;

    const grandTotal = Number(o.total ?? o.balance_due ?? o.grand_total ?? o.total_amount ?? 0);
    return Math.max(0, grandTotal - alreadyPaid);
});

// 5) Nome da forma de pagamento e valor do troco
const paymentMethodDescription = computed(() => {
    const o = props.order;
    if (!o) return 'Dinheiro';

    const methods = o.payment_intent_methods;
    let label = '';

    if (Array.isArray(methods) && methods.length > 0) {
        label = methods
            .map(m => m.method_name || m.name || (typeof m === 'string' ? m : ''))
            .filter(Boolean)
            .join(', ');
    } else if (typeof methods === 'string') {
        label = methods;
    } else if (o.payment_method_name) {
        label = o.payment_method_name;
    }

    if (!label) {
        label = 'Dinheiro';
    }

    // Leitura do troco
    const rawTroco = o.change_target ?? (Array.isArray(methods) ? methods[0]?.change_target : null);
    const troco = Number(rawTroco || 0);

    if (troco > 0) {
        label += ` (Troco p/ R$ ${troco.toFixed(2).replace('.', ',')})`;
    }

    return label;
});

// ⚡ Regra de Negócio: Libera cobrança PIX pelo Motoboy
const canChargePix = computed(() => {
    const settings = page.props.companySettings || {};
    return (
        Boolean(settings.sicredi_is_active) &&
        settings.sicredi_enable_driver_pix !== false &&
        props.order.payment_status !== 'paid' &&
        totalAmount.value > 0.01
    );
});

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
