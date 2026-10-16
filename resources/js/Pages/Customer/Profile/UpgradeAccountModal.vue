<template>
    <AdaptiveModal v-model:visible="isVisible" :pt="{ content: { class: 'p-0' } }">
        <div class="flex flex-col w-full px-6 pt-5 pb-8 select-none">
            <div class="flex items-center gap-3 mb-6">
                <div class="bg-primary-500/20 text-primary-400 p-2.5 rounded-xl shrink-0">
                    <i class="pi pi-star text-xl"></i>
                </div>
                <div class="flex flex-col">
                    <h2 class="text-xl font-black text-white leading-tight">Clube de Vantagens</h2>
                    <span class="text-xs text-white/60">Complete seu perfil para acumular cashback</span>
                </div>
            </div>

            <form @submit.prevent="handleUpgrade" class="flex flex-col gap-4">
                <FormField label="Seu Nome Completo" required :is-glass="true">
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="Ex: João da Silva"
                        required
                        class="w-full h-12 px-4 rounded-xl font-bold text-sm outline-none bg-white/10 border-none text-white focus:bg-white/15"
                    />
                </FormField>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="CPF" required :is-glass="true">
                        <input
                            v-model="form.cpf"
                            @input="formatCpfInput"
                            placeholder="000.000.000-00"
                            required
                            maxlength="14"
                            class="w-full h-12 px-4 rounded-xl font-mono font-bold text-sm outline-none bg-white/10 border-none text-white focus:bg-white/15"
                        />
                    </FormField>

                    <FormField label="WhatsApp" required :is-glass="true">
                        <input
                            v-model="form.phone"
                            @input="formatPhoneInput"
                            placeholder="(00) 00000-0000"
                            required
                            maxlength="15"
                            class="w-full h-12 px-4 rounded-xl font-bold text-sm outline-none bg-white/10 border-none text-white focus:bg-white/15"
                        />
                    </FormField>
                </div>

                <!-- Campo de Aniversário Orientado a Valor -->
                <FormField label="Data de Nascimento (Opcional)" :is-glass="true">
                    <input
                        v-model="form.birth_date"
                        type="date"
                        class="w-full h-12 px-4 rounded-xl font-bold text-sm outline-none bg-white/10 border-none text-white focus:bg-white/15"
                    />
                    <template #helper>
                        <span class="text-[10px] text-primary-400 font-medium">
                            🎂 Ganhe um presente especial da loja no mês do seu aniversário!
                        </span>
                    </template>
                </FormField>

                <div class="flex items-center gap-3 w-full mt-2 pt-2">
                    <button
                        type="button"
                        @click="isVisible = false"
                        class="h-12 flex-1 rounded-xl font-bold text-xs uppercase tracking-wider border border-white/20 bg-transparent text-white/70 hover:bg-white/10"
                    >
                        Depois
                    </button>
                    <button
                        type="submit"
                        :disabled="!isFormValid || isSubmitting"
                        class="h-12 flex-[2] rounded-xl bg-primary-500 hover:bg-primary-600 text-black font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 border-none shadow-md disabled:opacity-40"
                    >
                        <i v-if="isSubmitting" class="pi pi-spinner pi-spin"></i>
                        <span>{{ isSubmitting ? 'Salvando...' : 'Salvar e Pontuar' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import FormField from '@/Components/UI/FormField.vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import { formatPhone, formatCpfCnpj } from '@/Utils/formatters';

const props = defineProps({ visible: Boolean });
const emit = defineEmits(['update:visible']);

const page = usePage();
const toast = useToast();
const isSubmitting = ref(false);

const isVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val)
});

const form = ref({
    name: '',
    cpf: '',
    phone: '',
    birth_date: ''
});

const formatCpfInput = (e) => {
    form.value.cpf = formatCpfCnpj(e.target.value);
};

const formatPhoneInput = (e) => {
    form.value.phone = formatPhone(e.target.value);
};

const isFormValid = computed(() => {
    const cleanCpf = form.value.cpf.replace(/\D/g, '');
    const cleanPhone = form.value.phone.replace(/\D/g, '');
    return form.value.name.trim().length >= 2 && cleanCpf.length === 11 && cleanPhone.length >= 10;
});

watch(() => props.visible, (isOpen) => {
    if (isOpen) {
        const c = page.props.customer || {};
        form.value.name = c.name || '';
        form.value.phone = c.phone ? formatPhone(c.phone) : '';
        form.value.cpf = c.cpf ? formatCpfCnpj(c.cpf) : '';
        form.value.birth_date = c.birth_date || '';
    }
});

const handleUpgrade = () => {
    if (!isFormValid.value || isSubmitting.value) return;
    isSubmitting.value = true;

    router.post(route('customer.auth.upgrade'), form.value, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isVisible.value = false;
            toast.add({ severity: 'success', summary: 'Perfil Atualizado!', detail: 'Seus dados foram salvos com sucesso.', life: 4000 });
        },
        onError: (err) => {
            toast.add({ severity: 'error', summary: 'Atenção', detail: Object.values(err)[0] || 'Erro ao atualizar.', life: 5000 });
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};
</script>
