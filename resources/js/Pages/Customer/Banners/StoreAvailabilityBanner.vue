<template>
    <div
        v-if="!isOpen"
        :class="[
            'sticky top-0 left-0 right-0 z-50 p-3 text-center backdrop-blur-md transition-all duration-300 flex flex-col items-center justify-center border-b pt-safe shadow-sm',
            bannerStyleClass
        ]"
    >
        <span class="text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5">
            <i :class="isOpeningSoon ? 'pi pi-clock' : 'pi pi-lock'"></i>
            {{ isOpeningSoon ? (isPreOrder ? 'Pré-Pedido Liberado' : 'Quase abrindo') : 'Loja Fechada' }}
        </span>
        <span class="text-xs font-bold mt-0.5 opacity-95">
            {{ storeAvailabilityText }}
        </span>
    </div>
</template>

<script setup>
import { useStoreStatus } from "@/Composables/useStoreStatus";

const { isOpen, isPreOrder, isOpeningSoon, storeAvailabilityText, bannerStyleClass } = useStoreStatus();
</script>
