<template>
    <div class="absolute inset-0 bg-surface-2 z-30 flex flex-col p-6 animate-in fade-in slide-from-right-5 duration-200">
        <!-- CABEÇALHO -->
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-surface-border/50 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary-500/10 text-primary-500 flex items-center justify-center font-bold">
                    <i class="pi pi-ticket text-base"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-surface-text leading-tight">Cupons de Desconto</h3>
                    <p class="text-xs text-surface-muted">Selecione uma promoção ou digite o código</p>
                </div>
            </div>

            <button
                @click="$emit('close')"
                type="button"
                class="w-9 h-9 rounded-full bg-surface-3 hover:bg-surface-4 text-surface-muted hover:text-surface-text flex items-center justify-center transition-colors border-none outline-none cursor-pointer"
                title="Fechar"
            >
                <i class="pi pi-times text-xs"></i>
            </button>
        </div>

        <div class="flex flex-col gap-3.5 flex-1 min-h-0">
            <!-- BANNER DE CUPOM ATIVO -->
            <div v-if="appliedCoupon" class="flex items-center justify-between p-3.5 rounded-2xl bg-success-500/10 border border-success-500/20 text-success-600 dark:text-success-400 shrink-0">
                <div class="flex items-center gap-2.5">
                    <i class="pi pi-check-circle text-lg"></i>
                    <div>
                        <div class="font-black text-xs uppercase tracking-wider">{{ appliedCoupon }}</div>
                        <div class="text-[10px] font-bold mt-0.5">Desconto ativo: - R$ {{ Number(appliedDiscount || 0).toFixed(2) }}</div>
                    </div>
                </div>
                <button
                    @click="$emit('remove')"
                    type="button"
                    class="px-3 py-1.5 text-xs font-black uppercase text-danger-500 hover:bg-danger-500/10 rounded-xl transition-colors border-none outline-none cursor-pointer"
                >
                    Remover
                </button>
            </div>

            <!-- CAMPO DE DIGITAÇÃO / BUSCA RÁPIDA -->
            <div class="relative flex items-center w-full shrink-0">
                <i class="pi pi-search absolute left-4 text-surface-muted text-sm pointer-events-none"></i>
                <input
                    v-model="searchTerm"
                    type="text"
                    placeholder="DIGITAR CÓDIGO OU FILTRAR..."
                    class="w-full h-12 pl-11 pr-24 uppercase font-black tracking-wider text-xs rounded-2xl bg-surface-1 border border-surface-border text-surface-text outline-none focus:border-primary-500 transition-colors"
                    @keydown.enter.prevent="handleDirectSubmit"
                />
                <Button
                    v-if="searchTerm.trim()"
                    label="Aplicar"
                    class="absolute right-2 h-8 px-3 text-[10px] font-black uppercase rounded-xl border-none outline-none"
                    @click="handleDirectSubmit"
                />
            </div>

            <!-- LOADER -->
            <div v-if="isLoading" class="flex flex-col items-center justify-center py-12 text-surface-muted gap-2 flex-1">
                <i class="pi pi-spin pi-spinner text-2xl text-primary-500"></i>
                <span class="text-xs font-medium">Buscando cupons disponíveis...</span>
            </div>

            <!-- LISTAGEM DE CUPONS COM SELECTABLE CARD -->
            <div v-else-if="filteredCoupons.length > 0" class="flex-1 min-h-0 overflow-y-auto space-y-2.5 pr-1 hide-scrollbar">
                <SelectableCard
                    v-for="coupon in filteredCoupons"
                    :key="coupon.id"
                    :icon="coupon.is_pos_only ? 'pi-bolt' : 'pi-ticket'"
                    :title="`${coupon.name || coupon.code} (${coupon.label})`"
                    :subtitle="getCouponSubtitle(coupon)"
                    :color="coupon.code === appliedCoupon ? 'green' : (coupon.is_pos_only ? 'warning' : 'primary')"
                    :is-selected="coupon.code === appliedCoupon"
                    :show-checkmark="true"
                    :class="[!coupon.meets_subtotal ? 'opacity-40 cursor-not-allowed' : '']"
                    @select="coupon.meets_subtotal ? handleSelectCoupon(coupon.code) : null"
                />
            </div>

            <!-- ESTADO VAZIO -->
            <div v-else class="flex flex-col items-center justify-center py-12 text-surface-muted gap-2 flex-1 text-center">
                <i class="pi pi-ticket text-3xl opacity-30"></i>
                <span class="text-xs font-medium">Nenhum cupom encontrado para esta comanda.</span>
                <span class="text-[10px] text-surface-muted/70">Você pode digitar um código promocional diretamente no campo acima.</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Button from 'primevue/button';
import SelectableCard from '@/Components/UI/SelectableCard.vue';

const props = defineProps({
    appliedCoupon: String,
    appliedDiscount: Number,
    subtotal: { type: Number, default: 0 },
    customerId: { type: [Number, String, null], default: null }
});

const emit = defineEmits(['close', 'apply', 'remove']);

const coupons = ref([]);
const isLoading = ref(false);
const searchTerm = ref('');

const fetchCoupons = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('pos.coupons.available'), {
            params: {
                subtotal: props.subtotal,
                customer_id: props.customerId || null
            }
        });
        coupons.value = response.data.coupons || [];
    } catch (error) {
        console.error('Erro ao carregar cupons do PDV:', error);
        coupons.value = [];
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchCoupons();
});

const filteredCoupons = computed(() => {
    if (!searchTerm.value) return coupons.value;
    const term = searchTerm.value.toUpperCase().trim();
    return coupons.value.filter(c =>
        (c.name && c.name.toUpperCase().includes(term)) ||
        (c.code && c.code.toUpperCase().includes(term)) ||
        (c.label && c.label.toUpperCase().includes(term)) ||
        (c.description && c.description.toUpperCase().includes(term))
    );
});

const getCouponSubtitle = (coupon) => {
    if (!coupon.meets_subtotal) {
        const missing = (coupon.min_subtotal - props.subtotal).toFixed(2).replace('.', ',');
        return `Falta R$ ${missing} para atingir o valor mínimo`;
    }
    return coupon.description || (coupon.is_pos_only ? 'Uso exclusivo no caixa' : 'Válido para este pedido');
};

const handleSelectCoupon = (code) => {
    emit('apply', code);
};

const handleDirectSubmit = () => {
    const code = searchTerm.value.trim().toUpperCase();
    if (!code) return;
    emit('apply', code);
    searchTerm.value = '';
};
</script>
