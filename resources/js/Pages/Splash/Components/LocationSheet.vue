<template>
    <AdaptiveModal v-model:visible="isVisible">

        <!-- CABEÇALHO -->
        <div class="flex flex-col items-center justify-center gap-3 shrink-0 mb-6 mt-2">
            <div class="w-14 h-14 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center shadow-inner">
                <i class="pi fa-location-dot text-2xl"></i>
            </div>
            <div class="flex flex-col items-center text-center">
                <h2 class="text-xl font-black text-surface-text leading-tight">Como Chegar</h2>
                <span class="text-[10px] font-black uppercase tracking-widest text-primary-500 mt-0.5">Nossa Localização</span>
            </div>
        </div>

        <!-- INFORMAÇÃO DO ENDEREÇO -->
        <div class="flex flex-col gap-4 pb-4">
            <div class="p-4 bg-surface-2 border border-surface-border rounded-2xl flex items-center gap-4">
                <i class="pi fa-map text-surface-muted text-xl"></i>
                <div class="flex flex-col flex-1 min-w-0">
                    <span class="font-bold text-surface-text text-sm">
                        {{ page.props.companySettings?.trade_name || 'A Nossa Lanchonete' }}
                    </span>
                    <span class="text-xs text-surface-muted leading-tight mt-1">
                        {{ formattedAddress }}
                    </span>
                </div>
            </div>

            <!-- Botão para Mapas Nativos -->
            <button @click="openMaps" class="w-full h-12 rounded-xl bg-primary-500 text-white font-black text-sm uppercase tracking-widest shadow-md hover:bg-primary-600 transition-all outline-none flex items-center justify-center gap-2">
                <i class="pi fa-map-location-dot"></i> Abrir GPS
            </button>
        </div>

    </AdaptiveModal>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

const props = defineProps({
    visible: { type: Boolean, required: true }
});

const emit = defineEmits(['update:visible']);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const page = usePage();

const formattedAddress = computed(() => {
    const settings = page.props.companySettings;
    if (!settings) return 'Endereço não configurado.';

    // Concatena as informações disponíveis
    const parts = [settings.address, settings.address_number, settings.address_district, settings.address_city, settings.address_state];
    return parts.filter(p => p).join(', ');
});

const openMaps = () => {
    const query = encodeURIComponent(formattedAddress.value);
    const url = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
        ? `geo:0,0?q=${query}`
        : `https://www.google.com/maps/search/?api=1&query=${query}`;

    window.open(url, '_blank');
};
</script>
