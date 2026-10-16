<template>
    <div v-if="visible" class="fixed inset-0 z-[150] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
        <div class="bg-surface-1 border border-surface-border rounded-3xl w-full max-w-lg shadow-2xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">

            <!-- HEADER -->
            <div class="p-6 border-b border-surface-border flex items-center justify-between bg-surface-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold">
                        <i class="pi pi-user-plus text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-surface-text">Novo Cliente PDV</h3>
                        <p class="text-xs font-medium text-surface-muted">Identificação rápida para delivery ou balcão</p>
                    </div>
                </div>
                <button @click="close" type="button" class="w-9 h-9 rounded-xl hover:bg-surface-3 text-surface-muted hover:text-surface-text flex items-center justify-center border-none bg-transparent cursor-pointer transition-colors">
                    <i class="pi pi-times text-sm"></i>
                </button>
            </div>

            <!-- FORMULÁRIO -->
            <form @submit.prevent="handleSubmit" class="p-6 flex flex-col gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-surface-muted mb-1.5">Nome Completo *</label>
                    <input
                        ref="nameInput"
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="Ex: Carlos Eduardo"
                        class="w-full h-12 px-4 rounded-xl bg-surface-2 border border-surface-border focus:border-primary-500 outline-none text-sm font-semibold text-surface-text transition-all"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-surface-muted mb-1.5">WhatsApp / Telefone *</label>
                        <input
                            v-model="form.phone"
                            @input="handlePhoneInput"
                            type="text"
                            required
                            maxlength="15"
                            placeholder="(00) 00000-0000"
                            class="w-full h-12 px-4 rounded-xl bg-surface-2 border border-surface-border focus:border-primary-500 outline-none text-sm font-semibold text-surface-text transition-all"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-surface-muted mb-1.5">CPF (Opcional)</label>
                        <input
                            v-model="form.cpf"
                            @input="handleCpfInput"
                            type="text"
                            maxlength="14"
                            placeholder="000.000.000-00"
                            class="w-full h-12 px-4 rounded-xl bg-surface-2 border border-surface-border focus:border-primary-500 outline-none text-sm font-semibold text-surface-text transition-all"
                        />
                    </div>
                </div>

                <!-- AÇÕES -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-surface-border mt-2">
                    <button
                        type="button"
                        @click="close"
                        class="h-12 px-5 rounded-xl text-surface-muted hover:text-surface-text font-bold text-xs uppercase tracking-wider border-none bg-transparent cursor-pointer transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="h-12 px-6 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider shadow-md transition-all flex items-center gap-2 border-none cursor-pointer disabled:opacity-50"
                    >
                        <i v-if="isSubmitting" class="pi pi-spinner pi-spin text-xs"></i>
                        <span>{{ isSubmitting ? 'Salvando...' : 'Cadastrar e Selecionar' }}</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</template>

<script setup>
import { ref, reactive, watch, nextTick } from 'vue';
import { formatPhone, formatCpfCnpj } from '@/Utils/formatters';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    visible: Boolean,
    initialQuery: { type: String, default: '' }
});

const emit = defineEmits(['update:visible', 'success']);

const nameInput = ref(null);
const isSubmitting = ref(false);

const form = reactive({
    name: '',
    phone: '',
    cpf: ''
});

watch(() => props.visible, (isOpen) => {
    if (isOpen) {
        // Pré-preenche se o operador digitou número de telefone ou nome no campo de busca
        const isNumeric = /^\d+$/.test(props.initialQuery.replace(/\D/g, ''));
        if (isNumeric && props.initialQuery.length >= 8) {
            form.phone = formatPhone(props.initialQuery);
            form.name = '';
        } else {
            form.name = props.initialQuery || '';
            form.phone = '';
        }
        form.cpf = '';

        nextTick(() => nameInput.value?.focus());
    }
});

const handlePhoneInput = (e) => {
    form.phone = formatPhone(e.target.value);
};

const handleCpfInput = (e) => {
    form.cpf = formatCpfCnpj(e.target.value);
};

const close = () => {
    emit('update:visible', false);
};

const handleSubmit = () => {
    isSubmitting.value = true;

    router.post(route('operations.customers.store'), form, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const createdCustomer = page.props.flash?.createdCustomer;
            emit('success', createdCustomer || { ...form });
            close();
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};
</script>
