<template>
    <section v-if="categories && categories.length > 0" class="mb-6">
        <div class="px-4 flex justify-between items-center mb-3">
            <h2 class="font-black text-sm uppercase tracking-wider text-surface-muted">Categorias</h2>
            <button @click="$emit('navigate-to-menu')" class="text-xs font-bold text-primary-500 hover:underline flex items-center gap-1 outline-none">
                Ver Menu Completo <i class="pi pi-chevron-right text-[10px]"></i>
            </button>
        </div>

        <div class="flex gap-2.5 px-4 overflow-x-auto pb-1 hide-scrollbar">
            <button
                @click="$emit('update:modelValue', null)"
                :class="[
                    'shrink-0 px-4 py-2.5 rounded-2xl text-xs font-bold transition-all border flex items-center gap-2 outline-none',
                    modelValue === null
                        ? 'bg-primary-500 text-white border-primary-600 shadow-md shadow-primary-500/20'
                        : 'bg-surface-2 text-surface-text border-surface-border hover:bg-surface-3'
                ]"
            >
                <i class="pi pi-th-large text-xs"></i>
                <span>Todos</span>
            </button>

            <button
                v-for="cat in categories"
                :key="cat.id"
                @click="$emit('update:modelValue', cat.id)"
                :class="[
                    'shrink-0 px-4 py-2.5 rounded-2xl text-xs font-bold transition-all border flex items-center gap-2 outline-none',
                    modelValue === cat.id
                        ? 'bg-primary-500 text-white border-primary-600 shadow-md shadow-primary-500/20'
                        : 'bg-surface-2 text-surface-text border-surface-border hover:bg-surface-3'
                ]"
            >
                <span>{{ cat.name }}</span>
            </button>
        </div>
    </section>
</template>

<script setup>
defineProps({
    categories: { type: Array, required: true },
    modelValue: { type: [Number, String, null], default: null }
});

defineEmits(['update:modelValue', 'navigate-to-menu']);
</script>

<style scoped>
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
