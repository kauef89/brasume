<template>
    <div class="grid grid-cols-3 gap-3 w-full">
        <button v-for="n in 9" :key="n" @click="$emit('press', n)"
                class="h-16 rounded-2xl bg-surface-1 border border-surface-border font-black text-2xl hover:bg-surface-3 active:scale-95 transition-all outline-none">
            {{ n }}
        </button>

        <button @click="$emit('clear')"
                class="h-16 rounded-2xl bg-surface-1 border border-surface-border font-black text-xl text-danger-500 hover:bg-danger-50 active:scale-95 transition-all outline-none">
            C
        </button>

        <button @click="$emit('press', 0)"
                class="h-16 rounded-2xl bg-surface-1 border border-surface-border font-black text-2xl hover:bg-surface-3 active:scale-95 transition-all outline-none">
            0
        </button>

        <button @click="$emit('submit')" :disabled="loading"
                class="h-16 rounded-2xl bg-primary-500 text-white font-black text-xl hover:bg-primary-600 active:scale-95 transition-all outline-none disabled:opacity-70">
            <i class="pi pi-check" v-if="!loading"></i>
            <i class="pi pi-spinner pi-spin" v-else></i>
        </button>
    </div>
</template>

<script setup>
defineProps({
    loading: { type: Boolean, default: false }
});

defineEmits(['press', 'clear', 'submit']);
</script>
