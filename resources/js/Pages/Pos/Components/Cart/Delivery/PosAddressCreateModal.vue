<template>
    <div v-if="visible" class="flex flex-col gap-4 animate-in fade-in slide-in-from-right-4 duration-300 p-4">

        <!-- HEADER INTEGRADO -->
        <div class="flex items-center justify-between border-b-2 border-surface-border/50 pb-4">
            <div class="flex items-center gap-2">
                <i class="pi pi-map-marker text-primary-500 text-lg"></i>
                <h3 class="font-black text-sm text-surface-text uppercase tracking-wider">Novo Endereço</h3>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <!-- 1. BUSCA DE RUA COM AUTOCOMPLETE + FUSE.JS -->
            <div class="flex flex-col gap-1.5 relative z-10">
                <label class="text-[10px] font-black uppercase text-surface-muted">Endereço / Rua *</label>

                <SearchAutocomplete
                    v-model="streetSearchText"
                    :suggestions="streetResults"
                    placeholder="Digite o nome da rua..."
                    field="name"
                    @search="handleStreetSearch"
                    @select="handleStreetSelect"
                />

                <span v-if="detectedNeighborhoodName" class="text-[11px] font-semibold text-primary-500 mt-1 flex items-center gap-1">
                    <i class="pi pi-map-marker text-[10px]"></i>
                    Bairro: {{ detectedNeighborhoodName }} (Taxa: {{ $formatCurrency(detectedDeliveryFee) }})
                </span>
            </div>

            <!-- 2. NÚMERO E CHECKBOX S/N -->
            <div class="flex items-end gap-3 relative z-0">
                <div class="flex-1 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase text-surface-muted">Número *</label>
                    <input
                        v-model="form.number"
                        :disabled="form.no_number"
                        type="text"
                        placeholder="Ex: 123"
                        class="w-full h-14 px-3.5 bg-surface-1 border border-transparent rounded-xl text-sm font-semibold text-surface-text outline-none focus:border-primary-500 disabled:opacity-40 transition-all"
                    />
                </div>

                <div class="h-14 flex items-center shrink-0">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <div class="w-6 h-6 rounded-md border flex items-center justify-center transition-colors"
                             :class="form.no_number ? 'bg-primary-500 border-primary-500 text-white' : 'bg-surface-2 border-surface-border text-transparent group-hover:border-primary-400'">
                            <i class="pi pi-check text-[10px]"></i>
                        </div>
                        <span class="text-xs font-black uppercase text-surface-text select-none">S/N</span>
                        <input type="checkbox" v-model="form.no_number" @change="handleNoNumberChange" class="hidden" />
                    </label>
                </div>
            </div>

            <!-- 3. COMPLEMENTO (OBRIGATÓRIO SE S/N) -->
            <div class="flex flex-col gap-1.5 relative z-0">
                <label class="text-[10px] font-black uppercase transition-colors" :class="form.no_number ? 'text-primary-500' : 'text-surface-muted'">
                    Complemento {{ form.no_number ? '*' : '(Opcional)' }}
                </label>
                <input
                    v-model="form.complement"
                    type="text"
                    :placeholder="form.no_number ? 'Obrigatório (Ex: Casa verde, Fundos)' : 'Ex: Apto 101, Bloco B'"
                    class="w-full h-14 px-3.5 border rounded-xl text-sm font-semibold text-surface-text outline-none transition-all"
                    :class="form.no_number && !form.complement.trim()
                        ? 'border-primary-400 bg-primary-500/5 focus:border-primary-500'
                        : 'bg-surface-1 border-transparent focus:border-primary-500'"
                />
            </div>

            <!-- 4. REFERÊNCIA -->
            <div class="flex flex-col gap-1.5 relative z-0">
                <label class="text-[10px] font-black uppercase text-surface-muted">Ponto de Referência</label>
                <input
                    v-model="form.reference"
                    type="text"
                    placeholder="Ex: Próximo ao mercado..."
                    class="w-full h-14 px-3.5 bg-surface-1 border border-transparent rounded-xl text-sm font-semibold text-surface-text outline-none focus:border-primary-500"
                />
            </div>

            <!-- AÇÕES -->
            <div class="flex items-center gap-3 w-full mt-2 relative z-0">
                <button
                    type="button"
                    @click="close"
                    class="flex-1 h-12 bg-surface-3 hover:bg-primary-500/10 text-surface-text font-black text-xs uppercase tracking-wider rounded-full cursor-pointer transition-colors"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    @click="handleSubmit"
                    :disabled="!canSubmit || isSubmitting"
                    class="flex-[2] h-12 bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-wider rounded-full transition-all flex items-center justify-center gap-2 border-none cursor-pointer disabled:opacity-40"
                >
                    <i v-if="isSubmitting" class="pi pi-spinner pi-spin text-xs"></i>
                    <i v-else class="pi pi-check text-xs"></i>
                    <span>Salvar Endereço</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Fuse from 'fuse.js';
import { formatCurrency } from '@/Utils/formatters';
import SearchAutocomplete from '@/Components/UI/SearchAutocomplete.vue';

const props = defineProps({
    visible: Boolean,
    customerId: [Number, String],
    customerName: String,
    streets: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:visible', 'success']);

const streetSearchText = ref('');
const streetResults = ref([]);
const selectedStreet = ref(null);
const isSubmitting = ref(false);

const form = reactive({
    street_id: null,
    neighborhood_id: null,
    number: '',
    no_number: false,
    complement: '',
    reference: ''
});

// =====================================
// ÍNDICE FUZZY SEARCH (FUSE.JS)
// =====================================
const fuseInstance = computed(() => {
    return new Fuse(props.streets, {
        keys: [
            { name: 'name', weight: 0.7 },
            { name: 'neighborhood.name', weight: 0.3 },
            { name: 'segments.neighborhood.name', weight: 0.3 }
        ],
        threshold: 0.35, // 0.0 é correspondência exata, 1.0 aceita qualquer coisa
        distance: 100,
        ignoreLocation: true,
        minMatchCharLength: 2,
        useExtendedSearch: false
    });
});

const handleStreetSearch = (term) => {
    const q = (term || '').trim();
    if (!q || q.length < 2) {
        streetResults.value = [];
        return;
    }

    const searchHits = fuseInstance.value.search(q);
    // Limita aos 15 melhores resultados e desembrulha o objeto do Fuse
    streetResults.value = searchHits.slice(0, 15).map(hit => hit.item);
};

const handleStreetSelect = (street) => {
    selectedStreet.value = street;
    form.street_id = street.id;

    form.neighborhood_id = street.neighborhood_id
        || street.neighborhood?.id
        || street.segments?.[0]?.neighborhood_id
        || street.segments?.[0]?.neighborhood?.id
        || null;

    streetSearchText.value = street.name;
};

watch(streetSearchText, (val) => {
    if (!val || (selectedStreet.value && val !== selectedStreet.value.name)) {
        selectedStreet.value = null;
        form.street_id = null;
        form.neighborhood_id = null;
    }
});

// =====================================
// COMPUTADOS E VALIDAÇÕES
// =====================================
const detectedNeighborhoodName = computed(() => {
    if (!selectedStreet.value) return null;
    if (form.neighborhood_id && selectedStreet.value.segments) {
        const seg = selectedStreet.value.segments.find(s => s.neighborhood_id === form.neighborhood_id || s.neighborhood?.id === form.neighborhood_id);
        if (seg) return seg.neighborhood?.name || seg.neighborhood;
    }
    return selectedStreet.value?.neighborhood?.name
        || selectedStreet.value?.segments?.[0]?.neighborhood?.name
        || null;
});

const detectedDeliveryFee = computed(() => {
    if (!selectedStreet.value) return 0;
    if (form.neighborhood_id && selectedStreet.value.segments) {
        const seg = selectedStreet.value.segments.find(s => s.neighborhood_id === form.neighborhood_id || s.neighborhood?.id === form.neighborhood_id);
        if (seg) return Number(seg.neighborhood?.delivery_fee ?? 0);
    }
    const fee = selectedStreet.value?.neighborhood?.delivery_fee
        ?? selectedStreet.value?.segments?.[0]?.neighborhood?.delivery_fee
        ?? 0;
    return Number(fee);
});

watch(() => form.number, () => { resolvePosSegment(); });
watch(() => form.no_number, () => { resolvePosSegment(); });

const resolvePosSegment = () => {
    const street = selectedStreet.value;
    if (!street || !street.segments || street.segments.length === 0) return;

    if (street.segments.length === 1) {
        form.neighborhood_id = street.segments[0].neighborhood_id || street.segments[0].neighborhood?.id || null;
        return;
    }

    const num = parseInt(form.number);
    let matched = null;

    if (!form.no_number && !isNaN(num)) {
        matched = street.segments.find(s => {
            const start = parseInt(s.start_number) || 0;
            const end = s.end_number ? parseInt(s.end_number) : Infinity;
            return num >= start && num <= end;
        });
    }

    if (!matched) matched = street.segments[0];

    if (matched) {
        form.neighborhood_id = matched.neighborhood_id || matched.neighborhood?.id || null;
    }
};

const handleNoNumberChange = () => {
    if (form.no_number) form.number = '';
};

const canSubmit = computed(() => {
    const hasStreet = !!form.street_id;
    const hasNumber = form.no_number || form.number.trim() !== '';
    const hasComplement = !form.no_number || form.complement.trim() !== '';

    return hasStreet && hasNumber && hasComplement;
});

const isTemporaryCustomer = computed(() => {
    return !props.customerId || String(props.customerId).startsWith('temp_');
});

// =====================================
// SUBMISSÃO
// =====================================
const close = () => {
    emit('update:visible', false);
};

const handleSubmit = () => {
    if (!canSubmit.value) return;
    isSubmitting.value = true;

    const streetName = selectedStreet.value?.name || streetSearchText.value || 'Logradouro';
    const neighborhoodName = detectedNeighborhoodName.value || 'Bairro';

    // 1. Cliente em Memória (Rascunho Rápido)
    if (isTemporaryCustomer.value) {
        const tempAddress = {
            id: `temp_addr_${Date.now()}`,
            is_temporary: true,
            nickname: 'Casa',
            street_id: form.street_id,
            neighborhood_id: form.neighborhood_id,
            street: selectedStreet.value,
            neighborhood: selectedStreet.value?.neighborhood || selectedStreet.value?.segments?.[0]?.neighborhood,
            number: form.no_number ? 'S/N' : form.number,
            no_number: form.no_number,
            noNumber: form.no_number,
            complement: form.complement,
            reference: form.reference,
            delivery_fee: detectedDeliveryFee.value,
            full_address: `${streetName}, ${form.no_number ? 'S/N' : form.number}${form.complement ? ' - ' + form.complement : ''}`
        };

        setTimeout(() => {
            emit('success', tempAddress);
            isSubmitting.value = false;
            close();
        }, 200);
        return;
    }

    // 2. Cliente Persistido no Banco de Dados
    const payload = {
        nickname: 'Casa',
        street: streetName,
        street_id: form.street_id,
        number: form.no_number ? 'S/N' : form.number,
        noNumber: Boolean(form.no_number),
        no_number: Boolean(form.no_number),
        neighborhood: neighborhoodName,
        neighborhood_id: form.neighborhood_id,
        complement: form.complement || null,
        reference: form.reference || null
    };

    router.post(route('operations.customers.addresses.store', props.customerId), payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const created = page.props.flash?.createdAddress || {
                id: `temp_${Date.now()}`,
                ...payload,
                full_address: `${streetName}, ${payload.number}`
            };
            emit('success', created);
            close();
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

watch(() => props.visible, (val) => {
    if (val) {
        streetSearchText.value = '';
        selectedStreet.value = null;
        streetResults.value = [];

        form.street_id = null;
        form.neighborhood_id = null;
        form.number = '';
        form.no_number = false;
        form.complement = '';
        form.reference = '';
    }
});
</script>
