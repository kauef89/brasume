<template>
    <div>
        <!-- 👥 MODAL 1: SELEÇÃO DE PERFIS -->
        <AdaptiveModal v-model:visible="isProfilesVisible">
            <div class="flex flex-col items-center relative w-full pt-2">
                <button @click="closeFlow" class="absolute top-2 right-2 w-8 h-8 bg-surface-3 rounded-full flex items-center justify-center text-surface-muted hover:text-surface-text transition-colors outline-none border-none cursor-pointer">
                    <i class="pi pi-times text-xs font-medium"></i>
                </button>
                <h3 class="font-black text-2xl mb-2 text-surface-text text-center">Quem está a operar?</h3>
                <p class="text-sm font-medium text-surface-muted mb-6 text-center">Selecione o seu perfil para desbloquear o ecrã.</p>

                <div v-if="staff.length > 0" class="flex flex-wrap justify-center gap-6 mb-4 w-full">
                    <button v-for="user in staff" :key="user.id" @click="selectProfile(user)" class="flex flex-col items-center gap-3 group outline-none bg-transparent border-none cursor-pointer">
                        <AvatarBox :name="user.name" size="xl" color-class="bg-surface-2 text-surface-muted group-hover:bg-primary-500/10 group-hover:text-primary-500" class="border-4 border-surface-border group-hover:border-primary-500 group-hover:scale-110 transition-all shadow-md" />
                        <span class="font-medium text-sm text-surface-text group-hover:text-primary-500 transition-colors">{{ abbreviateName(user.name) }}</span>
                    </button>
                </div>
                <div v-else class="flex flex-col items-center justify-center p-6 bg-warning-500/10 border border-warning-500/20 rounded-2xl text-warning-700 text-center mb-4 w-full">
                    <i class="pi pi-lock text-3xl mb-2"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Caixa Fechado</span>
                    <span class="text-xs font-medium mt-1 opacity-80">O turno ainda não foi aberto pelo Gestor ou não há ninguém escalado para hoje.</span>
                </div>
            </div>
        </AdaptiveModal>

        <!-- 🔢 MODAL 2: INSERÇÃO DO PIN NUMÉRICO -->
        <AdaptiveModal v-model:visible="isPinVisible">
            <div class="flex flex-col items-center w-full pt-2">
                <div v-if="selectedProfile" class="flex items-center gap-3 mb-4 bg-surface-2 px-4 py-2 rounded-full border border-surface-border shadow-sm">
                    <AvatarBox :name="selectedProfile.name" size="sm" class="!w-6 !h-6" />
                    <span class="font-black text-sm text-surface-text">{{ abbreviateName(selectedProfile.name) }}</span>
                </div>

                <h3 class="font-black text-xl mb-3 text-center">Digite o seu PIN</h3>

                <!-- 🚀 ALERTA DE ERRO INLINE DIRETO NA TELA DO MODAL -->
                <div v-if="errorMessage" class="w-full max-w-xs mb-4 p-3 bg-danger-500/10 border border-danger-500/30 rounded-xl text-danger-600 dark:text-danger-400 font-black text-xs text-center flex items-center justify-center gap-2 animate-in fade-in slide-in-from-top-1 shadow-sm">
                    <i class="pi pi-exclamation-circle text-sm shrink-0"></i>
                    <span>{{ errorMessage }}</span>
                </div>

                <!-- Bolinhas indicadoras do PIN -->
                <div class="flex gap-3 mb-6">
                    <div v-for="i in 4" :key="i" class="w-4 h-4 rounded-full border-2 transition-all" :class="pin.length >= i ? 'bg-primary-500 border-primary-500 scale-110' : 'border-surface-border'"></div>
                </div>

                <div class="mb-5 w-full">
                    <NumericKeypad
                        :loading="isLoggingIn"
                        @press="addPin"
                        @clear="clearPin"
                        @submit="submit"
                    />
                </div>

                <button @click="backToProfiles" class="text-xs font-black text-surface-muted hover:text-surface-text uppercase tracking-widest outline-none transition-colors bg-transparent border-none cursor-pointer">
                    Voltar aos Perfis
                </button>
            </div>
        </AdaptiveModal>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { abbreviateName } from "@/Utils/formatters";
import AvatarBox from "@/Components/UI/AvatarBox.vue";
import NumericKeypad from "@/Components/UI/NumericKeypad.vue";
import AdaptiveModal from "@/Components/UI/AdaptiveModal.vue";

const props = defineProps({
    visible: Boolean,
    staff: Array,
    isLoggingIn: Boolean,
    errorMessage: String // 🚀 Prop de erro direto na tela
});

const emit = defineEmits(['update:visible', 'submit-pin', 'clear-error']);

const isProfilesVisible = ref(false);
const isPinVisible = ref(false);
const selectedProfile = ref(null);
const pin = ref('');

watch(() => props.visible, (newVal) => {
    if (newVal) {
        isProfilesVisible.value = true;
        isPinVisible.value = false;
        selectedProfile.value = null;
        pin.value = '';
        emit('clear-error');
    } else {
        isProfilesVisible.value = false;
        isPinVisible.value = false;
    }
});

const closeFlow = () => emit('update:visible', false);

const backToProfiles = () => {
    isPinVisible.value = false;
    pin.value = '';
    emit('clear-error');
    isProfilesVisible.value = true;
};

const selectProfile = (user) => {
    selectedProfile.value = user;
    isProfilesVisible.value = false;
    isPinVisible.value = true;
    emit('clear-error');
};

const addPin = (num) => {
    if (props.errorMessage) emit('clear-error');
    if (pin.value.length < 4) pin.value += num;
    if (pin.value.length === 4) submit();
};

const clearPin = () => {
    pin.value = '';
    emit('clear-error');
};

const submit = () => {
    if (pin.value.length < 4 || !selectedProfile.value) return;
    emit('submit-pin', { email: selectedProfile.value.email, pin: pin.value }, clearPin);
};
</script>
