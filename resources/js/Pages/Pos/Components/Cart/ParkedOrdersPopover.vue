<template>
    <Popover ref="popoverRef" class="w-80 p-0 overflow-hidden rounded-2xl bg-surface-3!">
        <div class="flex flex-col">
            <div class="p-4 flex items-center justify-between">
                <span class="font-bold text-md text-surface-muted">Rascunhos Pausados</span>
                <span class="bg-danger-500/70 text-white text-[10px] font-black px-2 py-0.5 rounded-full">{{ parkedOrders.length }}</span>
            </div>

            <div class="max-h-80 overflow-y-auto flex flex-col p-2 gap-2 hide-scrollbar">
                <div v-for="parked in parkedOrders" :key="parked.id" class="flex flex-col rounded-xl p-3 hover:bg-primary-500/10 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-black text-sm text-surface-text truncate pr-2">{{ getOrderTitle(parked) }}</span>
                        <span class="text-[10px] font-medium text-surface-muted shrink-0">{{ new Date(parked.date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black text-primary-smart">{{ $formatCurrency(parked.total) }} <span class="text-surface-muted font-medium ml-1">({{ parked.cart.length }} itens)</span></span>

                        <div class="flex items-center gap-2">
                            <button @click="handleDiscardOrder(parked.id)" class="w-8 h-8 rounded-lg bg-surface-3 hover:bg-red-500/10 hover:text-red-500 text-surface-muted transition-colors flex items-center justify-center outline-none" title="Descartar Rascunho">
                                <i class="pi pi-trash text-[10px] font-medium"></i>
                            </button>
                            <button @click="handleResumeOrder(parked.id)" class="px-3 h-8 rounded-lg bg-primary-500 text-white font-black text-[10px] uppercase tracking-widest hover:bg-primary-600 transition-colors shadow-sm outline-none cursor-pointer">
                                Retomar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Popover>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import Popover from 'primevue/popover';
import { useCart } from "@/Pages/Shared/Composables/useCart";
import { formatCurrency } from "@/Utils/formatters";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

const { parkedOrders, resumeOrder, discardParkedOrder } = useCart();
const { getOrderTitle } = useOrderFormatting();
const popoverRef = ref(null);

const toggle = (event) => { popoverRef.value.toggle(event); };
defineExpose({ toggle });

const handleResumeOrder = (id) => {
    resumeOrder(id);
    popoverRef.value.hide();
    window.dispatchEvent(new CustomEvent('pos-tab-changed', { detail: 'new' }));
};

const handleDiscardOrder = (id) => {
    discardParkedOrder(id);
    if (parkedOrders.value.length === 0) popoverRef.value.hide();
};
</script>
