<template>
    <div v-if="order" class="c-glass-card p-4 rounded-3xl flex flex-col gap-3 border border-white/10 shadow-sm animate-in fade-in slide-in-from-bottom-2 duration-300">
        <!-- CABEÇALHO DO WIDGET -->
        <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2.5">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-primary-500/20 text-primary-400 flex items-center justify-center shrink-0">
                    <i class="pi pi-history text-xs"></i>
                </div>
                <span class="text-[11px] font-black uppercase tracking-wider text-white">
                    Pedir Novamente
                </span>
            </div>
            <span class="text-[10px] font-bold text-white/50">
                Pedido #{{ $formatNumber(order.id) }} • {{ formattedDate }}
            </span>
        </div>

        <!-- CORPO: ITENS DETALHADOS COM COMBOS E ADICIONAIS -->
        <div class="flex items-center justify-between gap-3">
            <div class="flex flex-col min-w-0 flex-1">
                <p class="text-xs font-bold text-white/90 line-clamp-2 leading-snug">
                    {{ itemsSummary }}
                </p>
                <span class="text-[11px] font-black text-primary-smart mt-1">
                    {{ $formatCurrency(order.total) }}
                </span>
            </div>

            <!-- BOTÃO DE REPETIR -->
            <button
                type="button"
                @click="handleReorderClick"
                :disabled="isLoading"
                class="h-11 px-4 rounded-2xl bg-primary-500 hover:bg-primary-400 active:scale-95 text-black font-black text-xs uppercase tracking-wider flex items-center gap-2 shrink-0 border-none cursor-pointer transition-all shadow-md disabled:opacity-50"
            >
                <i class="pi" :class="isLoading ? 'pi-spinner animate-spin' : 'pi-replay'"></i>
                <span>Repetir</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    order: { type: Object, required: true }
});

const emit = defineEmits(['reorder']);

const isLoading = ref(false);

const formattedDate = computed(() => {
    if (!props.order?.created_at) return '';
    const date = new Date(props.order.created_at);
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
});

const itemsSummary = computed(() => {
    const items = props.order?.items || [];
    if (items.length === 0) return 'Itens diversos';

    const mainItems = items.filter(i => !i.parent_item_id && !i.is_combo_item);

    return mainItems.map(main => {
        const qty = main.quantity > 1 ? `${main.quantity}x ` : '';
        const baseName = `${qty}${main.product_name}`;

        // 🚀 Identifica subitens do combo
        const subItems = (main.sub_items || main.subItems || []).length > 0
            ? (main.sub_items || main.subItems)
            : items.filter(i => i.parent_item_id === main.id);

        if (subItems.length > 0) {
            const subDetails = subItems.map(s => {
                const sMods = (s.modifiers || []).map(m => m.name).filter(Boolean);
                return sMods.length > 0
                    ? `${s.product_name} (${sMods.join(', ')})`
                    : s.product_name;
            }).join(', ');
            return `${baseName} [${subDetails}]`;
        }

        // Modificadores do item simples
        const mods = (main.modifiers || []).map(m => m.name).filter(Boolean);
        if (mods.length > 0) {
            return `${baseName} (${mods.join(', ')})`;
        }

        return baseName;
    }).join(' • ');
});

const handleReorderClick = () => {
    isLoading.value = true;
    emit('reorder', props.order);
    setTimeout(() => {
        isLoading.value = false;
    }, 1500);
};
</script>
