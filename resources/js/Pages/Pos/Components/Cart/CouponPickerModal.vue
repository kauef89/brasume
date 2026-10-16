<template>
    <Dialog
        v-model:visible="isOpen"
        modal
        header="Cupons de Desconto & Ações do Caixa"
        :style="{ width: '520px', maxWidth: '95vw' }"
        :closable="true"
        :draggable="false"
        class="border-none rounded-3xl overflow-hidden shadow-2xl bg-surface-0 dark:bg-surface-900"
    >
        <div class="flex flex-col gap-4 pt-1">
            <!-- CAMPO DE DIGITAÇÃO DIRETA / BUSCA -->
            <div class="relative flex items-center w-full">
                <i class="pi pi-search absolute left-4 text-surface-400 text-sm pointer-events-none"></i>
                <input
                    type="text"
                    v-model="searchTerm"
                    placeholder="Digitar código ou buscar promoção..."
                    class="w-full h-12 pl-11 pr-24 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 text-surface-900 dark:text-surface-0 text-sm font-semibold uppercase tracking-wider outline-none focus:ring-2 focus:ring-primary-500 transition-all"
                    @keyup.enter="handleDirectApply"
                />
                <button
                    v-if="searchTerm"
                    @click="handleDirectApply"
                    :disabled="isApplying"
                    class="absolute right-2 px-3 py-1.5 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-bold text-xs uppercase cursor-pointer transition-all disabled:opacity-50"
                >
                    {{ isApplying ? '...' : 'Aplicar' }}
                </button>
            </div>

            <!-- LOADER -->
            <div v-if="isLoading" class="flex flex-col items-center justify-center py-10 text-surface-400 gap-2">
                <i class="pi pi-spin pi-spinner text-2xl text-primary-500"></i>
                <span class="text-xs font-medium">Buscando cupons disponíveis...</span>
            </div>

            <!-- LISTA DE CUPONS -->
            <div v-else-if="filteredCoupons.length > 0" class="max-h-[360px] overflow-y-auto space-y-2.5 pr-1">
                <div
                    v-for="coupon in filteredCoupons"
                    :key="coupon.id"
                    @click="coupon.meets_subtotal ? selectCoupon(coupon.code) : null"
                    class="group relative flex items-center justify-between p-3.5 rounded-2xl border transition-all"
                    :class="[
                        coupon.meets_subtotal
                            ? 'border-surface-200 dark:border-surface-700 hover:border-primary-500 bg-surface-0 dark:bg-surface-800 hover:shadow-md cursor-pointer'
                            : 'border-surface-200/50 dark:border-surface-800 bg-surface-100/50 dark:bg-surface-800/40 opacity-60 cursor-not-allowed'
                    ]"
                >
                    <!-- Detalhes do Cupom -->
                    <div class="flex items-start gap-3 min-w-0 pr-2">
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 font-black text-sm"
                            :class="coupon.is_pos_only ? 'bg-amber-500/15 text-amber-500' : 'bg-primary-500/15 text-primary-500'"
                        >
                            <i :class="coupon.is_pos_only ? 'pi pi-bolt' : 'pi pi-ticket'"></i>
                        </div>

                        <div class="flex flex-col min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-black text-sm text-surface-900 dark:text-surface-0 tracking-wide truncate">
                                    {{ coupon.name || coupon.code }}
                                </span>

                                <span
                                    v-if="coupon.name && coupon.name !== coupon.code"
                                    class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-surface-200 dark:bg-surface-700 text-surface-600 dark:text-surface-300"
                                >
                                    {{ coupon.code }}
                                </span>

                                <span
                                    v-if="coupon.is_pos_only"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-600 dark:text-amber-400"
                                >
                                    Exclusivo PDV
                                </span>
                            </div>

                            <span class="text-xs text-surface-500 dark:text-surface-400 font-medium mt-0.5 line-clamp-2">
                                {{ coupon.description }}
                            </span>
                        </div>
                    </div>

                    <!-- Badge de Desconto & Ação -->
                    <div class="flex items-center gap-2.5 shrink-0">
                        <div class="text-right">
                            <span class="block text-sm font-black text-emerald-600 dark:text-emerald-400">
                                {{ coupon.label }}
                            </span>
                            <span v-if="!coupon.meets_subtotal" class="text-[10px] text-danger-500 font-semibold">
                                Falta R$ {{ (coupon.min_subtotal - subtotal).toFixed(2).replace('.', ',') }}
                            </span>
                        </div>

                        <button
                            v-if="coupon.meets_subtotal"
                            class="w-8 h-8 rounded-full bg-surface-100 dark:bg-surface-700 group-hover:bg-primary-500 group-hover:text-white flex items-center justify-center transition-colors border-none cursor-pointer"
                        >
                            <i class="pi pi-check text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ESTADO VAZIO -->
            <div v-else class="text-center py-8 text-surface-400 text-xs font-medium">
                Nenhum cupom disponível no momento.
            </div>
        </div>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    subtotal: { type: Number, default: 0 },
    customerId: { type: [Number, null], default: null },
});

const emit = defineEmits(['update:modelValue', 'apply']);

const isOpen = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

const coupons = ref([]);
const isLoading = ref(false);
const isApplying = ref(false);
const searchTerm = ref('');

const fetchCoupons = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('pos.coupons.available'), {
            params: {
                subtotal: props.subtotal,
                customer_id: props.customerId,
            },
        });
        coupons.value = response.data.coupons || [];
    } catch (error) {
        console.error('Erro ao carregar cupons do PDV:', error);
        coupons.value = [];
    } finally {
        isLoading.value = false;
    }
};

watch(() => props.modelValue, (visible) => {
    if (visible) {
        searchTerm.value = '';
        fetchCoupons();
    }
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

const selectCoupon = (code) => {
    emit('apply', code);
    isOpen.value = false;
};

const handleDirectApply = () => {
    if (!searchTerm.value.trim()) return;
    emit('apply', searchTerm.value.trim().toUpperCase());
    isOpen.value = false;
};
</script>
