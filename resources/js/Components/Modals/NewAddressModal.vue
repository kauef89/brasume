<template>
    <component
        :is="mode === 'inline' ? 'div' : AdaptiveModal"
        :visible="mode === 'inline' ? undefined : isVisible"
        @update:visible="mode === 'inline' ? undefined : (isVisible = $event)"
        class="flex flex-col w-full"
        :class="mode === 'inline' ? 'flex-1 h-full' : 'h-full'"
    >
        <!-- CABEÇALHO -->
        <div v-if="mode !== 'inline'" class="flex items-center gap-3 shrink-0 z-10 mb-6 mt-2 justify-center flex-col text-center relative">
            <button
                @click="handleBack"
                class="w-8 h-8 rounded-full flex items-center justify-center transition-colors outline-none cursor-pointer border-none shrink-0 absolute left-0 top-0"
                :class="isCustomerMode ? 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white' : 'bg-surface-2 text-surface-text hover:bg-surface-3'"
                type="button"
            >
                <i class="pi pi-arrow-left text-sm"></i>
            </button>
            <div class="w-14 h-14 rounded-full flex items-center justify-center shrink-0" :class="isCustomerMode ? 'bg-white/10 text-white' : 'bg-primary-500/10 text-primary-600'">
                <i class="pi pi-map-marker text-2xl"></i>
            </div>
            <div class="flex flex-col items-center">
                <h2 class="font-black leading-tight" :class="[isCustomerMode ? 'text-white text-2xl' : 'text-surface-text text-xl']">
                    {{ addressToEdit ? 'Editar Endereço' : 'Novo Endereço' }}
                </h2>
                <span class="text-[10px] font-black uppercase tracking-widest mt-0.5" :class="isCustomerMode ? 'text-primary-400' : 'text-surface-muted'">
                    {{ step === 1 ? 'Localização' : 'Detalhes do Local' }}
                </span>
            </div>
        </div>

        <div class="flex-1 flex flex-col w-full min-h-0 relative">
            <Transition name="slide-fade" mode="out-in">

                <!-- PASSO 1: BUSCA -->
                <div v-if="step === 1" class="flex flex-col gap-4 w-full h-full" :class="mode === 'inline' ? 'pt-2 pb-4' : 'px-1'">

                    <!-- MODO CEP -->
                    <div v-if="lookupMode === 'cep'" class="flex flex-col gap-3 h-full">
                        <FormField label="Digite seu CEP" :is-glass="isCustomerMode">
                            <input
                                v-model="form.zipcode"
                                type="text"
                                placeholder="00000-000"
                                class="w-full h-14 px-4 text-center tracking-widest rounded-xl text-lg font-bold outline-none transition-all"
                                :class="isCustomerMode ? 'bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15' : 'bg-surface-1 border-2 border-surface-border text-surface-text focus:border-primary-500'"
                                @input="handleCepInput"
                                autofocus
                            />
                        </FormField>

                        <div class="flex flex-col items-center mt-6 gap-4">
                            <i v-if="isSearchingCep" class="pi pi-spinner animate-spin text-3xl" :class="isCustomerMode ? 'text-white' : 'text-primary-500'"></i>
                            <span v-if="cepError" class="text-danger-500 font-bold text-sm">{{ cepError }}</span>
                        </div>
                    </div>

                    <!-- MODO LOGRADOURO -->
                    <div v-if="lookupMode === 'street'" class="flex flex-col gap-3 h-full relative">
                        <FormField label="Buscar Logradouro" :is-glass="isCustomerMode">
                            <SearchAutocomplete
                                v-model="streetQuery"
                                :suggestions="streetSuggestions"
                                :is-customer-mode="isCustomerMode"
                                placeholder="Ex: Rua das Flores"
                                field="name"
                                :autofocus="true"
                                @search="searchStreets({ query: $event })"
                                @select="handleStreetSelect"
                            >
                                <template #option="{ option }">
                                    <span class="font-bold text-sm" :class="isCustomerMode ? 'text-white' : 'text-surface-text'">
                                        {{ option.name }}
                                    </span>
                                    <span
                                        v-if="option.segments?.length"
                                        class="text-xs truncate mt-0.5"
                                        :class="isCustomerMode ? 'text-white/50' : 'text-surface-muted'"
                                    >
                                        {{ [...new Set(option.segments.map(s => s.neighborhood))].join(', ') }}
                                    </span>
                                </template>
                            </SearchAutocomplete>
                        </FormField>

                        <div class="text-center mt-4 text-xs font-medium" :class="isCustomerMode ? 'text-white/40' : 'text-surface-muted'">
                            Digite as 3 primeiras letras da rua para buscar.
                        </div>
                    </div>

                    <!-- SUPORTE -->
                    <button @click="handleSupportNeeded" class="mt-auto mb-4 mx-auto text-xs font-bold flex items-center gap-2 transition-colors cursor-pointer outline-none border-none bg-transparent" :class="isCustomerMode ? 'text-white/60 hover:text-white' : 'text-surface-muted hover:text-primary-500'">
                        <i class="pi pi-whatsapp"></i> Minha localização não foi encontrada
                    </button>
                </div>

                <!-- PASSO 2: DETALHES -->
                <form v-else-if="step === 2" @submit.prevent="handleSubmit" class="flex flex-col gap-4 overflow-y-auto hide-scrollbar w-full h-full" :class="mode === 'inline' ? 'pt-2 pb-4' : 'pb-6 px-1'">

                    <!-- RESUMO DO LOCAL -->
                    <div class="flex flex-col p-4 rounded-2xl transition-all" :class="isCustomerMode ? '' : 'bg-surface-2 border border-surface-border shadow-sm'">
                        <span class="text-sm font-black leading-tight" :class="isCustomerMode ? 'text-white' : 'text-surface-text'">
                            {{ form.street }}
                        </span>
                        <span class="text-xs font-bold mt-1" :class="isCustomerMode ? 'text-white/50' : 'text-surface-muted'">
                            {{ form.neighborhood || 'Bairro não identificado' }}
                        </span>
                    </div>

                    <!-- NÚMERO E TOGGLE "SEM NÚMERO" -->
                    <div class="flex items-center gap-4">
                        <div class="flex-1 transition-all duration-300" :class="{ 'opacity-40 pointer-events-none grayscale': form.no_number }">
                            <FormField label="Número da Casa" :required="!form.no_number" :is-glass="isCustomerMode">
                                <input
                                    v-model="form.number"
                                    type="text"
                                    inputmode="numeric"
                                    placeholder="Ex: 123"
                                    class="w-full h-14 px-4 rounded-xl text-sm font-bold outline-none transition-all"
                                    :class="isCustomerMode ? 'bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15' : 'bg-surface-1 border border-surface-border text-surface-text focus:border-primary-500'"
                                    :required="!form.no_number"
                                    @blur="triggerFuzzyNeighborhood"
                                    autofocus
                                />
                            </FormField>
                        </div>

                        <div class="flex flex-col gap-1.5 items-center justify-center pt-5 select-none">
                            <span class="text-[10px] font-black uppercase tracking-widest transition-colors" :class="form.no_number ? (isCustomerMode ? 'text-primary-400' : 'text-primary-500') : (isCustomerMode ? 'text-white/50' : 'text-surface-muted')">
                                Sem N.
                            </span>
                            <button
                                type="button"
                                @click="form.no_number = !form.no_number; handleNoNumberChange()"
                                class="w-12 h-6 rounded-full relative transition-all shrink-0 outline-none border-none cursor-pointer"
                                :class="form.no_number ? 'bg-primary-500' : (isCustomerMode ? 'bg-white/15' : 'bg-surface-border')"
                                title="Alternar Sem Número"
                            >
                                <div class="w-4 h-4 rounded-full bg-white absolute top-1 transition-transform shadow-md"
                                     :class="form.no_number ? 'translate-x-7' : 'translate-x-1'"></div>
                            </button>
                        </div>
                    </div>

                    <!-- COMPLEMENTO -->
                    <FormField label="Complemento / Referência" :required="form.no_number" :is-glass="isCustomerMode">
                        <input
                            v-model="form.complement"
                            type="text"
                            placeholder="Apto, Bloco, Cor do portão..."
                            class="w-full h-14 px-4 rounded-xl text-sm font-bold outline-none transition-all"
                            :class="isCustomerMode ? 'bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15' : 'bg-surface-1 border border-surface-border text-surface-text focus:border-primary-500'"
                            :required="form.no_number"
                        />
                    </FormField>

                    <!-- APELIDO DO ENDEREÇO -->
                    <div v-if="!isGuest && customerId" class="flex flex-col gap-2 mt-1">
                        <label class="text-[10px] font-black uppercase tracking-widest" :class="isCustomerMode ? 'text-white/50' : 'text-surface-muted'">Salvar Endereço Como</label>

                        <div class="flex gap-2 w-full">
                            <button
                                v-for="label in ['Casa', 'Trabalho', 'Amor', 'Outro']"
                                :key="label"
                                type="button"
                                @click="activeNicknameTag = label"
                                class="flex-1 h-12 rounded-xl text-xs font-black transition-all outline-none cursor-pointer flex items-center justify-center border-none"
                                :class="[
                                    activeNicknameTag === label
                                        ? (isCustomerMode ? 'bg-primary-500 text-black shadow-md' : 'bg-primary-500 text-white shadow-md')
                                        : (isCustomerMode ? 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white' : 'bg-surface-1 text-surface-muted border border-surface-border hover:bg-surface-3')
                                ]"
                            >
                                {{ label }}
                            </button>
                        </div>

                        <div v-if="activeNicknameTag === 'Outro'" class="mt-2 animate-in fade-in slide-in-from-top-2">
                            <FormField label="Nome do Local" required :is-glass="isCustomerMode">
                                <input
                                    v-model="form.nickname"
                                    type="text"
                                    placeholder="Ex: Casa da Avó..."
                                    class="w-full h-14 px-4 rounded-xl text-sm font-bold outline-none transition-all"
                                    :class="isCustomerMode ? 'bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15' : 'bg-surface-1 border border-surface-border text-surface-text focus:border-primary-500'"
                                    required
                                />
                            </FormField>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <div class="flex w-full shrink-0 mt-auto pt-4" :class="isCustomerMode ? 'border-t border-white/10' : 'border-t border-surface-border/50'">
                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full transition-all flex items-center justify-center gap-2 cursor-pointer outline-none border-none"
                            :class="isCustomerMode ? 'c-btn-primary !h-14 !text-xs !uppercase !tracking-widest' : 'h-14 rounded-xl font-black text-sm uppercase tracking-widest shadow-md bg-primary-500 text-white hover:bg-primary-600'"
                        >
                            <i v-if="loading" class="pi pi-spinner animate-spin"></i>
                            <i v-else class="pi pi-save"></i>
                            <span>{{ addressToEdit ? 'Atualizar Endereço' : 'Confirmar Endereço' }}</span>
                        </button>
                    </div>
                </form>

            </Transition>
        </div>
    </component>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";
import FormField from "@/Components/UI/FormField.vue";
import SearchAutocomplete from "@/Components/UI/SearchAutocomplete.vue";
import { useAddressLookup } from "@/Pages/Shared/Composables/useAddressLookup";
import { useWhatsAppSupport } from "@/Pages/Customer/Composables/useWhatsAppSupport";

const props = defineProps({
    visible: Boolean,
    customerId: [Number, String],
    addressToEdit: Object,
    mode: { type: String, default: 'modal' },
    isGuest: { type: Boolean, default: false },
    isCustomerMode: { type: Boolean, default: false }
});

const emit = defineEmits(["update:visible", "cancel", "success", "save", "update-header", "substep"]);

const toast = useToast();
const page = usePage();
const { openSupport } = useWhatsAppSupport();

const lookupMode = computed(() => page.props.companySettings?.address_lookup_mode || 'cep');
const step = ref(1);
const cepError = ref('');
const loading = ref(false);

const { isSearchingCep, streetSuggestions, loadStreetsDataset, fetchCep, searchStreets, calculateFuzzyNeighborhood } = useAddressLookup(lookupMode);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const defaultForm = () => ({
    nickname: "Entrega",
    zipcode: "",
    street: "",
    street_id: null,
    streetObj: null,
    number: "",
    no_number: false,
    neighborhood: "",
    neighborhood_id: null,
    complement: ""
});

const form = ref(defaultForm());
const activeNicknameTag = ref('Casa');
const streetQuery = ref('');

const syncHeader = () => {
    if (props.mode === 'inline') {
        emit('substep', step.value);
    }
};

onMounted(() => syncHeader());
watch(step, () => syncHeader());

watch(() => props.visible, async (isOpen) => {
    if (isOpen) {
        cepError.value = '';
        if (props.addressToEdit) {
            form.value = { ...defaultForm(), ...props.addressToEdit };
            streetQuery.value = props.addressToEdit.street || '';
            activeNicknameTag.value = ['Casa', 'Trabalho', 'Amor'].includes(props.addressToEdit.nickname) ? props.addressToEdit.nickname : 'Outro';
            step.value = 2;
        } else {
            form.value = defaultForm();
            streetQuery.value = '';
            activeNicknameTag.value = 'Casa';
            step.value = 1;
            if (lookupMode.value === 'street') await loadStreetsDataset();
        }
    }
}, { immediate: true });

const handleNoNumberChange = () => {
    if (form.value.no_number) form.value.number = '';
};

const handleCepInput = () => {
    cepError.value = '';
    const raw = form.value.zipcode.replace(/\D/g, "");
    if (raw.length === 8) {
        fetchCep(raw, form,
            () => { step.value = 2; },
            () => { cepError.value = 'CEP não encontrado. Verifique e tente novamente.'; }
        );
    }
};

const handleStreetSelect = (street) => {
    form.value.street = street.name;
    form.value.street_id = street.id;
    form.value.streetObj = street;
    streetQuery.value = street.name;

    if (street.segments && street.segments.length === 1) {
        form.value.neighborhood = street.segments[0].neighborhood;
        form.value.neighborhood_id = street.segments[0].neighborhood_id || null;
    } else {
        form.value.neighborhood = "";
        form.value.neighborhood_id = null;
    }
    step.value = 2;
};

const goBack = () => {
    if (step.value === 2 && !props.addressToEdit) {
        step.value = 1;
        return true;
    }
    return false;
};
defineExpose({ goBack });

const handleBack = () => {
    if (step.value === 2 && !props.addressToEdit) step.value = 1;
    else {
        emit("cancel");
        if (props.mode !== 'inline') isVisible.value = false;
    }
};

const handleSupportNeeded = () => {
    openSupport('address_not_found');
};

watch(() => form.value.number, () => { triggerFuzzyNeighborhood(); });
watch(() => form.value.no_number, () => { triggerFuzzyNeighborhood(); });

const triggerFuzzyNeighborhood = () => {
    if (!form.value.no_number) calculateFuzzyNeighborhood(form);
};

const handleSubmit = () => {
    const isAvulso = props.isGuest || !props.customerId;

    loading.value = true;

    if (lookupMode.value === 'street' && form.value.streetObj) {
        form.value.street = form.value.streetObj.name;
    }

    if (isAvulso) {
        form.value.nickname = 'Entrega';
    } else if (activeNicknameTag.value !== 'Outro') {
        form.value.nickname = activeNicknameTag.value;
    }

    if (form.value.no_number) {
        form.value.number = 'S/N';
    } else {
        form.value.number = String(form.value.number);
    }

    const fullAddress = `${form.value.street}, ${form.value.number}${form.value.complement ? ' - ' + form.value.complement : ''} - ${form.value.neighborhood || ''}`;
    const payload = {
        ...form.value,
        full_address: fullAddress
    };

    if (props.isCustomerMode || isAvulso) {
        loading.value = false;
        emit('save', payload);
        emit('success', payload);

        window.dispatchEvent(new CustomEvent('pos:address-selected', { detail: payload }));
        toast.add({ severity: 'success', summary: 'Endereço Definido', detail: 'Localização vinculada ao pedido.', life: 3000 });

        if (props.mode !== 'inline') isVisible.value = false;
        return;
    }

    const url = props.addressToEdit
        ? `/operations/customers/${props.customerId}/addresses/${props.addressToEdit.id}`
        : `/operations/customers/${props.customerId}/addresses`;

    router.post(url, form.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            loading.value = false;
            toast.add({ severity: 'success', summary: 'Sucesso', detail: props.addressToEdit ? 'Endereço atualizado!' : 'Endereço cadastrado com sucesso!' });
            emit("success", payload);
            window.dispatchEvent(new CustomEvent('pos:address-selected', { detail: payload }));
            if (props.mode !== 'inline') isVisible.value = false;
        },
        onError: () => {
            loading.value = false;
            toast.add({ severity: 'error', summary: 'Erro', detail: 'Houve um problema ao salvar o endereço.' });
        }
    });
};
</script>

<style scoped>
.slide-fade-enter-active, .slide-fade-leave-active { transition: all 0.25s ease; }
.slide-fade-enter-from { transform: translateX(20px); opacity: 0; }
.slide-fade-leave-to { transform: translateX(-20px); opacity: 0; }
</style>
