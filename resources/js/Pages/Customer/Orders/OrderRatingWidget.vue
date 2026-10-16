<template>
    <div
        v-if="!isSubmitted"
        class="c-glass-card p-5 mx-1 flex flex-col items-center text-center animate-in fade-in duration-300 select-none"
    >
        <div class="w-12 h-12 rounded-full bg-warning-500/10 flex items-center justify-center mb-3">
            <i class="pi pi-star-fill text-xl text-warning-400"></i>
        </div>

        <h3 class="font-black text-base text-surface-text leading-tight">
            Como foi o pedido #{{ orderId }}?
        </h3>
        <p class="text-xs font-medium text-surface-muted mt-1 mb-4">
            Sua opinião nos ajuda a melhorar cada detalhe.
        </p>

        <!-- Seletor de Estrelas -->
        <div class="flex items-center gap-2 mb-4">
            <button
                v-for="star in 5"
                :key="star"
                type="button"
                @click="setRating(star)"
                :disabled="isSubmitting"
                class="w-10 h-10 rounded-full transition-all outline-none border-none cursor-pointer hover:scale-110 active:scale-95 flex items-center justify-center"
                :class="rating >= star ? 'bg-warning-400/20 text-warning-400' : 'bg-white/5 text-surface-muted hover:bg-white/10'"
            >
                <i class="pi text-lg" :class="rating >= star ? 'pi-star-fill' : 'pi-star'"></i>
            </button>
        </div>

        <!-- 🚀 ALERTA DE ERRO INLINE (Caso o Toast não esteja visível) -->
        <div v-if="errorMessage" class="w-full mb-3 p-2.5 rounded-xl bg-danger-500/10 border border-danger-500/20 text-danger-400 text-xs font-bold animate-in fade-in">
            <i class="pi pi-exclamation-triangle mr-1"></i> {{ errorMessage }}
        </div>

        <!-- 🚀 FLUXO 5 ESTRELAS (Google Review opcional) -->
        <div v-if="rating === 5" class="w-full flex flex-col gap-3 animate-in slide-in-from-bottom-2 duration-200">
            <p class="text-xs font-bold text-success-400 bg-success-500/10 py-2 px-3 rounded-xl">
                Ficamos muito felizes! 😍
            </p>

            <a
                v-if="googleReviewLink"
                :href="googleReviewLink"
                target="_blank"
                rel="noopener noreferrer"
                @click="submitRating('google')"
                class="w-full py-3.5 rounded-2xl bg-white text-black font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 no-underline active:scale-98 transition-transform shadow-sm cursor-pointer"
            >
                <i class="pi pi-google"></i>
                <span>Avaliar no Google</span>
            </a>

            <button
                v-else
                type="button"
                @click="submitRating('internal')"
                :disabled="isSubmitting"
                class="w-full py-3.5 rounded-2xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 border-none cursor-pointer transition-colors active:scale-98"
            >
                <i v-if="isSubmitting" class="pi pi-spinner pi-spin text-sm"></i>
                <span>{{ isSubmitting ? 'Enviando...' : 'Enviar Avaliação' }}</span>
            </button>
        </div>

        <!-- 🚀 FLUXO 1 A 4 ESTRELAS (Feedback Construtivo com Tags) -->
        <div v-else-if="rating > 0" class="w-full flex flex-col gap-3 animate-in slide-in-from-bottom-2 duration-200 text-left">
            <p class="text-xs font-bold text-surface-text text-center">
                O que podemos fazer melhor?
            </p>

            <div class="flex flex-wrap justify-center gap-2 mb-1">
                <button
                    v-for="tag in availableTags"
                    :key="tag"
                    type="button"
                    @click="toggleTag(tag)"
                    :disabled="isSubmitting"
                    class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider transition-all outline-none border-none cursor-pointer"
                    :class="selectedTags.includes(tag) ? 'bg-primary-500 text-white shadow-sm' : 'bg-white/5 text-surface-muted hover:bg-white/10'"
                >
                    {{ tag }}
                </button>
            </div>

            <textarea
                v-model="comment"
                :disabled="isSubmitting"
                placeholder="Conte-nos mais detalhes (opcional)"
                class="w-full p-3 text-sm rounded-xl bg-surface-1/80 border border-surface-border text-surface-text outline-none focus:border-primary-500 resize-none transition-colors"
                rows="2"
            ></textarea>

            <button
                type="button"
                @click="submitRating('internal')"
                :disabled="isSubmitting"
                class="w-full py-3.5 rounded-2xl bg-primary-500 hover:bg-primary-600 text-white font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 border-none cursor-pointer transition-colors active:scale-98"
            >
                <i v-if="isSubmitting" class="pi pi-spinner pi-spin text-sm"></i>
                <span>{{ isSubmitting ? 'Enviando...' : 'Enviar Feedback' }}</span>
            </button>
        </div>
    </div>

    <!-- Feedback de Sucesso -->
    <div
        v-else
        class="c-glass-card p-5 mx-1 flex flex-col items-center text-center animate-in zoom-in-95 duration-200"
    >
        <div class="w-10 h-10 rounded-full bg-success-500/20 text-success-400 flex items-center justify-center mb-2">
            <i class="pi pi-check text-lg font-bold"></i>
        </div>
        <h3 class="font-black text-sm text-surface-text">Avaliação registrada!</h3>
        <p class="text-xs font-medium text-surface-muted mt-0.5">Obrigado por nos ajudar a evoluir.</p>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    orderId: { type: [Number, String], required: true }
});

const emit = defineEmits(['rated']);

const page = usePage();
const toast = useToast();

const rating = ref(0);
const selectedTags = ref([]);
const comment = ref('');
const isSubmitting = ref(false);
const isSubmitted = ref(false);
const errorMessage = ref(null);

const availableTags = ['Sabor', 'Temperatura', 'Entrega Demorada', 'Embalagem', 'Preço'];

const googleReviewLink = computed(() => {
    return page.props.companySettings?.google_review_link
        || page.props.publicSettings?.google_review_link
        || null;
});

const setRating = (val) => {
    rating.value = val;
    errorMessage.value = null;
};

const toggleTag = (tag) => {
    const idx = selectedTags.value.indexOf(tag);
    if (idx > -1) selectedTags.value.splice(idx, 1);
    else selectedTags.value.push(tag);
};

const submitRating = async (destination = 'internal') => {
    if (isSubmitting.value || rating.value === 0) return;

    isSubmitting.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.post(route('customer.orders.rate', props.orderId), {
            rating: rating.value,
            tags: selectedTags.value,
            comment: comment.value.trim() || null
        }, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        isSubmitted.value = true;
        emit('rated', response.data?.data || { rating: rating.value });

        toast.add({
            severity: 'success',
            summary: 'Obrigado!',
            detail: 'Sua avaliação foi registrada com sucesso.',
            life: 3000
        });

        if (destination === 'google' && googleReviewLink.value) {
            window.open(googleReviewLink.value, '_blank');
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'Não foi possível registrar sua avaliação.';
        errorMessage.value = msg;

        toast.add({
            severity: 'error',
            summary: 'Atenção',
            detail: msg,
            life: 4000
        });
    } finally {
        isSubmitting.value = false;
    }
};
</script>
