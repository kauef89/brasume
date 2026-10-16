<template>
    <div class="flex flex-col gap-3 animate-in fade-in duration-200">
        <div class="relative flex items-center">
            <span class="absolute left-4 pointer-events-none text-surface-muted text-xs">
                <i class="pi pi-search"></i>
            </span>
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Buscar por nome, WhatsApp ou CPF..."
                class="w-full h-12 pl-11 pr-4 bg-surface-3 border border-surface-border rounded-xl text-sm font-semibold text-surface-text placeholder:text-surface-muted/50 outline-none focus:border-primary-500 transition-all"
            />
        </div>

        <div v-if="searchQuery.trim().length >= 2" class="max-h-56 overflow-y-auto hide-scrollbar flex flex-col gap-1 rounded-2xl bg-surface-3/50 p-1.5 border border-surface-border/40">
            <div
                v-for="c in filteredCustomers"
                :key="c.id"
                @click="$emit('select', c)"
                class="p-3 rounded-xl hover:bg-surface-3 cursor-pointer flex items-center justify-between transition-colors group"
            >
                <div class="flex flex-col">
                    <span class="text-sm font-bold text-surface-text group-hover:text-primary-smart transition-colors">{{ c.name }}</span>
                    <span class="text-xs font-medium text-surface-muted">{{ formatPhone(c.phone) || c.cpf || 'Sem telefone' }}</span>
                </div>
                <i class="pi pi-chevron-right text-xs text-surface-muted/40 group-hover:text-primary-smart transition-colors"></i>
            </div>

            <div class="p-3 text-center border-t border-surface-border/30 mt-1">
                <button
                    type="button"
                    @click="$emit('go-to-new', searchQuery)"
                    class="text-xs font-black text-primary-600 dark:text-primary-400 hover:underline uppercase tracking-wider bg-transparent border-none cursor-pointer flex items-center justify-center gap-2 mx-auto"
                >
                    <i class="pi pi-user-plus text-xs"></i>
                    <span>Não encontrou? Cadastrar "{{ searchQuery }}"</span>
                </button>
            </div>
        </div>

        <div v-else class="p-4 text-center text-xs text-surface-muted">
            Digite pelo menos 2 caracteres para localizar o cliente.
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Fuse from 'fuse.js';
import { formatPhone } from '@/Utils/formatters';

const props = defineProps({
    customers: { type: Array, default: () => [] }
});

defineEmits(['select', 'go-to-new']);

const searchQuery = ref('');

const customerFuse = computed(() => {
    return new Fuse(props.customers || [], {
        keys: [
            { name: 'name', weight: 0.7 },
            { name: 'phone', weight: 0.3 },
            { name: 'cpf', weight: 0.3 }
        ],
        threshold: 0.35,
        distance: 100,
        ignoreLocation: true,
        minMatchCharLength: 2
    });
});

const filteredCustomers = computed(() => {
    const q = searchQuery.value.trim();
    if (q.length < 2) return [];

    const digitsOnly = q.replace(/\D/g, '');
    const cleanChars = q.replace(/[\s\-\(\)\.]/g, '');

    // Se a busca for predominantemente números (DDD, telefone ou CPF)
    if (digitsOnly.length >= 2 && digitsOnly.length === cleanChars.length) {
        return props.customers.filter(c => {
            const cleanPhone = (c.phone || '').replace(/\D/g, '');
            const cleanCpf = (c.cpf || '').replace(/\D/g, '');
            return cleanPhone.includes(digitsOnly) || cleanCpf.includes(digitsOnly);
        });
    }

    // Busca difusa para nomes
    return customerFuse.value.search(q).map(hit => hit.item);
});
</script>
