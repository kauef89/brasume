<template>
    <div class="w-full flex-1 flex flex-col min-h-0 relative px-6 py-4">
        <Transition name="slide-fade" mode="out-in">

            <!-- ==========================================
                 PASSO 1: BUSCA (CEP OU LOGRADOURO)
                 ========================================== -->
            <div v-if="step === 1" class="flex flex-col gap-4 w-full h-full pt-2 pb-4">

                <!-- MODO CEP -->
                <div v-if="lookupMode === 'cep'" class="flex flex-col gap-3 h-full">
                    <FormField label="Digite seu CEP" :is-glass="true">
                        <input
                            v-model="form.zipcode"
                            type="text"
                            placeholder="00000-000"
                            class="w-full h-14 px-4 text-center tracking-widest rounded-xl text-lg font-bold outline-none transition-all bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15"
                            @input="handleCepInput"
                            autofocus
                        />
                    </FormField>

                    <div class="flex flex-col items-center mt-6 gap-4">
                        <i v-if="isSearchingCep" class="pi pi-spinner animate-spin text-3xl text-white"></i>
                        <span v-if="cepError" class="text-danger-500 font-bold text-sm">{{ cepError }}</span>
                    </div>
                </div>

                <!-- MODO LOGRADOURO -->
                <div v-if="lookupMode === 'street'" class="flex flex-col gap-3 h-full relative">
                    <FormField label="Buscar Logradouro" :is-glass="true">
                        <SearchAutocomplete
                            v-model="streetQuery"
                            :suggestions="streetSuggestions"
                            :is-customer-mode="true"
                            placeholder="Ex: Rua das Flores"
                            field="name"
                            :autofocus="true"
                            @search="searchStreets({ query: $event })"
                            @select="handleStreetSelect"
                        >
                            <template #option="{ option }">
                                <span class="font-bold text-sm text-white">
                                    {{ option.name }}
                                </span>
                                <span
                                    v-if="option.segments?.length"
                                    class="text-xs truncate mt-0.5 text-white/50"
                                >
                                    {{ [...new Set(option.segments.map(s => s.neighborhood))].join(', ') }}
                                </span>
                            </template>
                        </SearchAutocomplete>
                    </FormField>

                    <div class="text-center mt-4 text-xs font-medium text-white/40">
                        Digite as 3 primeiras letras da sua rua para buscar.
                    </div>
                </div>

                <!-- SUPORTE -->
                <button
                    @click="openSupport('address_not_found')"
                    type="button"
                    class="mt-auto mb-2 mx-auto text-xs font-bold flex items-center gap-2 transition-colors cursor-pointer outline-none border-none bg-transparent text-white/60 hover:text-white"
                >
                    <i class="pi pi-whatsapp"></i> Minha localização não foi encontrada
                </button>
            </div>

            <!-- ==========================================
                 PASSO 2: FORMULÁRIO DE DETALHES
                 ========================================== -->
            <form v-else-if="step === 2" @submit.prevent="handleSubmit" class="flex flex-col gap-4 overflow-y-auto hide-scrollbar w-full h-full pt-1 pb-4">

                <!-- RESUMO DO LOGRADOURO CENTRALIZADO -->
                <div class="mx-auto w-fit max-w-full flex items-center justify-center gap-4 px-5 py-3.5 rounded-2xl border border-white/20 bg-transparent transition-all shadow-sm">
                    <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center shrink-0 border border-white/5">
                        <i class="pi pi-home text-white/70 text-lg"></i>
                    </div>
                    <div class="flex flex-col text-left min-w-0">
                        <span class="text-[13px] font-black leading-tight text-white truncate">
                            {{ form.street }}
                        </span>
                        <span class="text-[10px] font-bold mt-0.5 uppercase tracking-widest truncate text-white/50">
                            {{ form.neighborhood || 'Buscando Bairro...' }}
                        </span>
                    </div>
                </div>

                <!-- NÚMERO & TOGGLE SEM NÚMERO (Gatilho da Inteligência) -->
                <div class="flex items-center gap-4 mt-2">
                    <div class="flex-1 transition-all duration-300" :class="{ 'opacity-40 pointer-events-none grayscale': form.no_number }">
                        <FormField label="Número da Casa" :required="!form.no_number" :is-glass="true">
                            <input
                                v-model="form.number"
                                type="text"
                                inputmode="numeric"
                                placeholder="Ex: 123"
                                class="w-full h-14 px-4 rounded-xl text-sm font-bold outline-none transition-all bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15"
                                :required="!form.no_number"
                                @blur="triggerFuzzyNeighborhood"
                                autofocus
                            />
                        </FormField>
                    </div>

                    <div class="flex flex-col gap-1.5 items-center justify-center pt-5 select-none">
                        <span class="text-[10px] font-black uppercase tracking-widest transition-colors" :class="form.no_number ? 'text-primary-400' : 'text-white/50'">
                            Sem N.
                        </span>
                        <button
                            type="button"
                            @click="form.no_number = !form.no_number; handleNoNumberChange()"
                            class="w-12 h-6 rounded-full relative transition-all shrink-0 outline-none border-none cursor-pointer"
                            :class="form.no_number ? 'bg-primary-500' : 'bg-white/15'"
                        >
                            <div class="w-4 h-4 rounded-full bg-white absolute top-1 transition-transform shadow-md"
                                 :class="form.no_number ? 'translate-x-7' : 'translate-x-1'"></div>
                        </button>
                    </div>
                </div>

                <!-- COMPLEMENTO -->
                <FormField label="Complemento / Referência" :required="form.no_number" :is-glass="true">
                    <input
                        v-model="form.complement"
                        type="text"
                        placeholder="Apto, Bloco, Cor do portão..."
                        class="w-full h-14 px-4 rounded-xl text-sm font-bold outline-none transition-all bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15"
                        :required="form.no_number"
                    />
                </FormField>

                <!-- APELIDO DO ENDEREÇO -->
                <div v-if="!isGuest" class="flex flex-col gap-2 mt-1">
                    <label class="text-[10px] font-black uppercase tracking-widest text-white/50">Salvar Endereço Como</label>

                    <div class="flex gap-2 w-full">
                        <button
                            v-for="label in ['Casa', 'Trabalho', 'Amor', 'Outro']"
                            :key="label"
                            type="button"
                            @click="activeNicknameTag = label"
                            class="flex-1 h-12 rounded-xl text-xs font-black transition-all outline-none cursor-pointer flex items-center justify-center border-none"
                            :class="[
                                activeNicknameTag === label
                                    ? 'bg-primary-500 text-black shadow-md'
                                    : 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white'
                            ]"
                        >
                            {{ label }}
                        </button>
                    </div>

                    <div v-if="activeNicknameTag === 'Outro'" class="mt-2 animate-in fade-in slide-in-from-top-2">
                        <FormField label="Nome do Local" required :is-glass="true">
                            <input
                                v-model="form.nickname"
                                type="text"
                                placeholder="Ex: Casa da Avó..."
                                class="w-full h-14 px-4 rounded-xl text-sm font-bold outline-none transition-all bg-white/10 border-none text-white placeholder:text-white/30 focus:bg-white/15"
                                required
                            />
                        </FormField>
                    </div>
                </div>

                <!-- BOTÃO SALVAR -->
                <div class="flex w-full shrink-0 mt-auto pt-4 border-t border-white/10">
                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full c-btn-primary !h-14 !text-xs !uppercase !tracking-widest flex items-center justify-center gap-2 cursor-pointer outline-none border-none transition-all"
                    >
                        <i v-if="loading" class="pi pi-spinner animate-spin"></i>
                        <i v-else class="pi pi-save"></i>
                        <span>Salvar Endereço</span>
                    </button>
                </div>
            </form>

        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import FormField from "@/Components/UI/FormField.vue";
import SearchAutocomplete from "@/Components/UI/SearchAutocomplete.vue";
import { useAddressLookup } from "@/Pages/Shared/Composables/useAddressLookup";
import { useWhatsAppSupport } from "@/Pages/Customer/Composables/useWhatsAppSupport";

defineProps({
    isGuest: { type: Boolean, default: false }
});

const emit = defineEmits(["save", "success", "substep"]);

const toast = useToast();
const page = usePage();
const { openSupport } = useWhatsAppSupport();

const lookupMode = computed(() => page.props.companySettings?.address_lookup_mode || 'cep');
const step = ref(1);
const cepError = ref('');
const loading = ref(false);

const {
    isSearchingCep,
    streetSuggestions,
    loadStreetsDataset,
    fetchCep,
    searchStreets,
    calculateFuzzyNeighborhood
} = useAddressLookup(lookupMode);

const defaultForm = () => ({
    nickname: "Casa",
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

onMounted(async () => {
    emit('substep', step.value);
    if (lookupMode.value === 'street') {
        await loadStreetsDataset();
    }
});

watch(step, (val) => emit('substep', val));

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

// ==============================================================
// LÓGICA ORIGINAL RESTAURADA E INTACTA
// ==============================================================
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
    if (step.value === 2) {
        step.value = 1;
        return true;
    }
    return false;
};
defineExpose({ goBack });

watch(() => form.value.number, () => { triggerFuzzyNeighborhood(); });
watch(() => form.value.no_number, () => { triggerFuzzyNeighborhood(); });

const triggerFuzzyNeighborhood = () => {
    if (!form.value.no_number) calculateFuzzyNeighborhood(form);
};

const handleSubmit = () => {
    loading.value = true;

    if (lookupMode.value === 'street' && form.value.streetObj) {
        form.value.street = form.value.streetObj.name;
    }

    if (activeNicknameTag.value !== 'Outro') {
        form.value.nickname = activeNicknameTag.value;
    }

    if (form.value.no_number) {
        form.value.number = 'S/N';
    } else {
        form.value.number = String(form.value.number);
    }

    emit('save', form.value, {
        onSuccess: () => {
            loading.value = false;
            emit("success");
        },
        onError: (errors) => {
            loading.value = false;
            // 🚀 Mantive apenas a leitura real do erro para não ficar cego
            const realError = (errors && Object.values(errors)[0]) || 'Verifique os dados informados no formulário.';
            toast.add({ severity: 'error', summary: 'Atenção', detail: realError, life: 5000 });
        },
        onFinish: () => {
            loading.value = false;
        }
    });
};
</script>

<style scoped>
.slide-fade-enter-active, .slide-fade-leave-active { transition: all 0.25s ease; }
.slide-fade-enter-from { transform: translateX(20px); opacity: 0; }
.slide-fade-leave-to { transform: translateX(-20px); opacity: 0; }
</style>
