import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

export function useStoreStatus() {
    const page = usePage();
    const toast = useToast();

    const status = computed(() => page.props.storeStatus || {
        is_open: false,
        is_pre_order: false,
        is_past_closing: false,
        active_channels: ['delivery', 'counter', 'table'],
        next_open_time: null,
        current_close_time: null,
        last_closed_at: null,
        reason: null
    });

    const settings = computed(() => page.props.companySettings || page.props.publicSettings || {});

    const is24Hours = computed(() => {
        return !!(settings.value.is_24_hours || settings.value.always_open);
    });

    const isClosedIndefinitely = computed(() => {
        return !!(settings.value.is_closed_indefinitely || settings.value.closed_indefinitely);
    });

    const isOpen = computed(() => {
        if (isClosedIndefinitely.value) return false;
        return !!status.value.is_open;
    });

    const isPreOrder = computed(() => isClosedIndefinitely.value ? false : !!status.value.is_pre_order);
    const activeChannels = computed(() => status.value.active_channels);
    const closedReason = computed(() => status.value.reason);

    const canOrder = computed(() => {
        if (isClosedIndefinitely.value) return false;
        return isOpen.value || isPreOrder.value;
    });

    const minutesToOpen = computed(() => {
        if (isClosedIndefinitely.value || !status.value.next_open_time) return null;
        const now = new Date();
        const openTime = new Date(status.value.next_open_time);
        return Math.ceil((openTime - now) / (1000 * 60));
    });

    const isOpeningSoon = computed(() => {
        if (status.value.reason === 'cashier_closed') return true;
        const mins = minutesToOpen.value;
        return mins !== null && mins > 0 && mins <= 30;
    });

    const closeTimeString = computed(() => {
        if (!status.value.current_close_time) return null;
        const closeDate = new Date(status.value.current_close_time);
        return closeDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    });

    // 🚀 REGRA 2: Passou do horário regular de fechar, mas o turno continua aberto
    const isPastClosingTime = computed(() => {
        if (!isOpen.value || is24Hours.value) return false;
        if (status.value.is_past_closing) return true;
        if (!status.value.current_close_time) return false;
        const now = new Date();
        const closeDate = new Date(status.value.current_close_time);
        return now >= closeDate;
    });

    // 🏷️ TÍTULO PRINCIPAL DINÂMICO
    const statusTitle = computed(() => {
        if (isClosedIndefinitely.value) return 'Voltamos em breve!';
        if (isOpen.value) {
            if (isPastClosingTime.value) return 'Fechando em breve';
            return 'Estamos abertos!';
        }
        if (status.value.reason === 'cashier_closed') return 'Preparando a loja';
        if (isOpeningSoon.value) return 'Quase abrindo!';
        if (isPreOrder.value) return 'Garanta o seu lugar!';
        return 'Fechado agora';
    });

    // 🏷️ SUBTÍTULO DINÂMICO (Cobre abertura mais cedo e encerramento iminente)
    const storeAvailabilityText = computed(() => {
        if (isClosedIndefinitely.value) return 'Fechado temporariamente.';

        if (status.value.reason === 'manual_override_closed') {
            if (!status.value.next_open_time) return 'Nosso expediente já encerrou.';
            const openTime = new Date(status.value.next_open_time);
            const timeString = openTime.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            return `Expediente encerrado. Voltamos às ${timeString}`;
        }

        if (isOpen.value) {
            if (isPastClosingTime.value) {
                return 'Última chance de pedir!';
            }
            if (is24Hours.value) {
                return 'Funcionando 24 horas';
            }
            if (closeTimeString.value) {
                return `Funcionando até às ${closeTimeString.value}`;
            }
            return 'Estamos abertos!';
        }

        if (status.value.reason === 'cashier_closed') {
            return isPreOrder.value
                ? 'Preparando a loja. Você já pode fazer seu pedido!'
                : 'Preparando a loja. Abriremos em instantes!';
        }

        if (!status.value.next_open_time) return 'Fechado no momento.';

        const mins = minutesToOpen.value;
        if (isOpeningSoon.value) {
            return isPreOrder.value
                ? `Abre em ${mins} min. Cardápio liberado!`
                : `Abre em ${mins} minutos`;
        }

        const openTime = new Date(status.value.next_open_time);
        const timeString = openTime.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        const now = new Date();
        const tomorrow = new Date();
        tomorrow.setDate(now.getDate() + 1);

        if (now.toDateString() === openTime.toDateString()) {
            return `Voltamos hoje às ${timeString}`;
        }
        if (tomorrow.toDateString() === openTime.toDateString()) {
            return `Voltamos amanhã às ${timeString}`;
        }

        const weekday = openTime.toLocaleDateString('pt-BR', { weekday: 'long' });
        return `Voltamos ${weekday.charAt(0).toUpperCase() + weekday.slice(1)} às ${timeString}`;
    });

    const bannerStyleClass = computed(() => {
        if (isClosedIndefinitely.value) return 'bg-danger-500/80 text-white border-danger-500/20';
        if (isOpen.value && isPastClosingTime.value) return 'bg-amber-500/90 text-amber-950 border-amber-500/30';
        if (isOpeningSoon.value) return 'bg-warning-500/80 text-warning-950 border-warning-500/20';
        return 'bg-danger-500/80 text-white border-danger-500/20';
    });

    const isNotifyMeActive = ref(page.props.auth?.user?.wants_opening_notification || false);
    const isTogglingNotification = ref(false);

    const toggleOpeningNotification = async () => {
        isTogglingNotification.value = true;
        try {
            await axios.post(route('customer.profile.toggle-notification'), {
                wants_notification: isNotifyMeActive.value
            });

            toast.add({
                severity: 'success',
                summary: isNotifyMeActive.value ? 'Aviso Ativado!' : 'Aviso Desativado',
                detail: isNotifyMeActive.value ? 'Enviaremos uma notificação assim que abrirmos.' : 'Você não será mais notificado.',
                life: 3000
            });
        } catch (e) {
            isNotifyMeActive.value = !isNotifyMeActive.value;
            toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar preferência.', life: 3000 });
        } finally {
            isTogglingNotification.value = false;
        }
    };

    return {
        status, isOpen, isPreOrder, activeChannels, closedReason, canOrder,
        minutesToOpen, isOpeningSoon, isWithinOneHour: isOpeningSoon,
        closeTimeString, storeAvailabilityText, bannerStyleClass,
        is24Hours, isClosedIndefinitely, isPastClosingTime,
        statusTitle, statusSubtitle: storeAvailabilityText,
        isNotifyMeActive, isTogglingNotification, toggleOpeningNotification
    };
}
