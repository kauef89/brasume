<template>
    <AdaptiveModal :visible="visible" @update:visible="$emit('update:visible', $event)">

        <div class="flex flex-col items-center text-center p-6 gap-3">
            <div class="w-16 h-16 rounded-full bg-primary-500/10 text-primary-500 flex items-center justify-center mb-2 text-3xl">
                <i class="pi pi-map-marker"></i>
            </div>

            <h4 class="font-black text-xl">{{ settings?.trade_name || settings?.company_name || 'Nossa Loja' }}</h4>

            <p class="text-sm font-medium leading-relaxed">
                {{ settings?.street }}, {{ settings?.number }}
                <span v-if="settings?.complement"> - {{ settings.complement }}</span> - {{ settings?.neighborhood }}<br>
                {{ settings?.city }}/{{ settings?.state }}<br>
                CEP: {{ settings?.zip_code }}
            </p>

            <a :href="mapUrl" target="_blank" class="py-3.5 px-6 mt-4 bg-primary-500 rounded-full font-black transition-all flex items-center justify-center gap-2 outline-none">
                <i class="pi pi-map"></i> Abrir no Google Maps
            </a>
        </div>

    </AdaptiveModal>
</template>

<script setup>
import { computed } from 'vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

const props = defineProps({
    visible: Boolean,
    settings: Object
});

defineEmits(['update:visible']);

const mapUrl = computed(() => {
    if (!props.settings) return '#';
    const query = `${props.settings.street}, ${props.settings.number}, ${props.settings.neighborhood}, ${props.settings.city}, ${props.settings.state}`;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
});
</script>
