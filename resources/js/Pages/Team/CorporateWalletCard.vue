<template>
    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 relative overflow-hidden mb-4">
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>

        <p class="text-sm font-medium opacity-80 uppercase tracking-wider mb-1">Saldo Disponível</p>
        <h2 class="text-4xl font-black mb-1" :class="{'text-danger-200': balance < 0}">
            {{ $formatCurrency(balance) }}
        </h2>
        <p class="text-xs opacity-75">
            Renovação {{ frequency === 'daily' ? 'Diária' : 'Mensal' }}
        </p>
    </div>
</template>

<script setup>
defineProps({
    balance: {
        type: Number,
        required: true
    },
    frequency: {
        type: String,
        default: 'monthly'
    }
});
</script>
