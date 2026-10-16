import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const isAttendanceModalVisible = ref(false);
const attendanceActionType = ref('mid_shift');
const isFreelancerPaymentVisible = ref(false);
const freelancerToPay = ref(null);
const freelancerPayoutDetails = ref({});

export function useShiftAttendance() {
    const openMidShiftAttendance = () => {
        attendanceActionType.value = 'mid_shift';
        isAttendanceModalVisible.value = true;
    };

    const openShiftAttendanceFlow = (type) => {
        attendanceActionType.value = type;
        isAttendanceModalVisible.value = true;
    };

    const syncMidShiftAttendance = (attendeesArray, onSuccessCallback) => {
        router.post(route('operations.attendance.sync'), { attendees: attendeesArray }, { preserveState: true, 
            preserveScroll: true,
            onSuccess: () => {
                isAttendanceModalVisible.value = false;
                if (onSuccessCallback) onSuccessCallback();
            },
            onError: () => alert("Erro ao atualizar a equipe. Verifique sua conexão.")
        });
    };

    // 🚀 Busca a apuração de consumo e diária do backend antes de abrir o modal
    const processFreelancerPayment = async (user, rate = null) => {
        freelancerToPay.value = user;

        try {
            const resp = await axios.get(route('operations.attendance.payout-details', user.id));
            freelancerPayoutDetails.value = resp.data;
        } catch (e) {
            // Fallback se a requisição assíncrona falhar
            freelancerPayoutDetails.value = {
                user: { id: user.id, name: user.name },
                gross_rate: Number(rate || user.base_remuneration || user.default_rate || 0),
                consumption_amount: 0,
                net_amount: Number(rate || user.base_remuneration || user.default_rate || 0)
            };
        }

        isFreelancerPaymentVisible.value = true;
    };

    const confirmFreelancerPayment = (payload) => {
        router.post(route('operations.attendance.pay'), { preserveState: true, 
            user_id: freelancerToPay.value.id,
            amount: payload.amount,
            gross_rate: payload.gross_rate,
            consumption_deducted: payload.consumption_deducted,
            payment_method: payload.method,
            bank_account_id: payload.bankAccountId
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isFreelancerPaymentVisible.value = false;
                freelancerToPay.value = null;
                freelancerPayoutDetails.value = {};
            },
            onError: (err) => {
                const msg = err && typeof err === 'object' ? Object.values(err)[0] : 'Erro ao processar pagamento.';
                alert(msg);
            }
        });
    };

    return {
        isAttendanceModalVisible,
        attendanceActionType,
        isFreelancerPaymentVisible,
        freelancerToPay,
        freelancerPayoutDetails,
        openMidShiftAttendance,
        openShiftAttendanceFlow,
        syncMidShiftAttendance,
        processFreelancerPayment,
        confirmFreelancerPayment
    };
}
