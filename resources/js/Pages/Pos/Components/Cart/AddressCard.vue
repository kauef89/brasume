<template>
    <div
        @click="$emit('select', address)"
        class="p-option flex items-center justify-between w-full text-left gap-3 p-3 relative cursor-pointer transition-all rounded-2xl"
        :class="{ 'bg-primary-500/60': isSelected }"
    >
        <!-- 🚀 COLUNA 1: APELIDO E TAXA DE ENTREGA (Ultra compacta) -->
        <div class="shrink-0 flex flex-col items-center gap-1">
            <span
                class="font-black text-xs truncate max-w-full block "
            >
                {{ address.nickname || 'Casa' }}
            </span>
            <span
                class="text-xs font-black truncate"            >
                {{ displayFee }}
            </span>
        </div>

        <!-- 🚀 COLUNA 2: ENDEREÇO COMPLETO E REFERÊNCIA -->
        <div class="flex-1 min-w-0 flex flex-col justify-center">
            <span
                class="font-medium text-sm break-words line-clamp-2"
                :class="isSelected ? 'text-white' : 'text-surface-text'"
            >
                {{ displayAddress }}
            </span>
            <span
                v-if="address.reference"
                class="text-xs mt-0.5 font-medium line-clamp-1 opacity-80"
                :class="isSelected ? 'text-white/80' : 'text-surface-muted'"
            >
                <span class="font-bold uppercase tracking-wider text-[9px]">Ref:</span> {{ address.reference }}
            </span>
        </div>

        <!-- 🚀 COLUNA 3: BOTÕES DE AÇÃO (Sempre visíveis, 1 lápis e 1 lixeira) -->
        <div class="shrink-0 flex items-center gap-1.5">
            <button
                type="button"
                @click.stop.prevent="$emit('edit', address)"
                class="w-8 h-8 rounded-full flex items-center justify-center transition-all outline-none cursor-pointer"
                :class="isSelected
                    ? 'hover:bg-primary-500/40'
                    : 'hover:text-primary-smart hover:bg-surface-3/20'
                "
                title="Editar Endereço"
            >
                <i class="pi pi-pencil text-xs"></i>
            </button>

            <button
                type="button"
                @click.stop.prevent="handleDeactivate"
                class="w-8 h-8 rounded-full flex items-center justify-center transition-all outline-none cursor-pointer"
                :class="isSelected
                ? 'hover:bg-primary-500/40'
                : 'hover:text-primary-smart hover:bg-surface-3/20'
                "
                title="Excluir Endereço"
            >
                <i class="pi pi-trash text-xs"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted } from "vue";
import { formatCurrency } from "@/Utils/formatters";
import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";

const props = defineProps({
    address: { type: Object, required: true },
    isSelected: { type: Boolean, default: false },
    customerId: [Number, String]
});

const emit = defineEmits(['select', 'deactivate', 'edit']);

const { formatAddress } = useOrderFormatting();

// 🚀 FORMATADOR DINÂMICO REAPROVEITADO
const displayAddress = computed(() => {
    // O formatAddress espera um objeto order, mas podemos passar um "fake order" só com a propriedade address!
    return formatAddress({ address: props.address }) || 'Endereço sem detalhes';
});

// 🚀 FORMATADOR DINÂMICO PARA A TAXA DE ENTREGA
const displayFee = computed(() => {
    let fee = 0;
    if (props.address.delivery_fee !== undefined && props.address.delivery_fee !== null) {
        fee = Number(props.address.delivery_fee);
    } else if (props.address.neighborhood?.delivery_fee !== undefined) {
        fee = Number(props.address.neighborhood.delivery_fee);
    }
    return fee === 0 ? "Grátis" : formatCurrency(fee);
});

const nicknameColorClass = computed(() => {
    const nick = String(props.address?.nickname || 'Casa').toLowerCase();
    if (props.isSelected) return 'bg-white/20 text-white border-white/10';
    if (nick === 'trabalho') return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20';
    if (nick === 'outro') return 'bg-warning-500/10 text-warning-600 dark:text-warning-400 border-warning-500/20';
    return 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/20';
});

const handleDeactivate = () => {
    if (confirm(`Deseja realmente remover este endereço de ${props.address.nickname || 'Casa'} da lista?`)) {
        emit('deactivate', props.address.id);
    }
};

onMounted(() => {
    console.log('🕵️‍♂️ [SONDA 2 - AddressCard] Dados brutos do endereço injetado:', props.address);
});
</script>
