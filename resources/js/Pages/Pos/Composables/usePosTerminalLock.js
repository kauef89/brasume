import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

export function usePosTerminalLock() {
    const toast = useToast();
    const page = usePage();

    // 🔒 Defensivo: se o servidor indicar bloqueado ou se o localStorage marcar bloqueio, bloqueia
    const serverLocked = page.props.initialTerminalLocked ?? true;
    const localLocked = localStorage.getItem('brasume_terminal_locked');

    const initialLockState = localLocked !== null ? localLocked === 'true' : serverLocked;

    const isTerminalLocked = ref(initialLockState);

    const lockTerminal = async () => {
        isTerminalLocked.value = true;
        localStorage.setItem('brasume_terminal_locked', 'true');

        const currentDeviceUuid = localStorage.getItem('brasume_device_uuid') || window.Laravel?.device_uuid;
        if (currentDeviceUuid) {
            try {
                await axios.post(route('team.remote-unlock.lock'), { device_uuid: currentDeviceUuid });
            } catch (error) {
                console.error('Falha ao sincronizar trava de terminal com o servidor.', error);
            }
        }
    };

    const unlockTerminal = () => {
        isTerminalLocked.value = false;
        localStorage.removeItem('brasume_terminal_locked');
        localStorage.setItem('brasume_terminal_locked', 'false');
    };

    onMounted(() => {
        const currentDeviceUuid = localStorage.getItem('brasume_device_uuid') || window.Laravel?.device_uuid;

        if (currentDeviceUuid && window.Echo) {
            window.Echo.channel(`device.${currentDeviceUuid}`)
                .listen('.DeviceRemoteUnlocked', () => {
                    unlockTerminal();
                    toast.add({
                        severity: 'success',
                        summary: 'Terminal Retomado',
                        detail: 'O gestor autorizou o seu retorno à operação!',
                        life: 3000
                    });
                })
                .listen('.TerminalUnlocked', () => {
                    unlockTerminal();
                    toast.add({
                        severity: 'success',
                        summary: 'Terminal Retomado',
                        detail: 'O gestor autorizou o seu retorno à operação!',
                        life: 3000
                    });
                })
                .listen('.DeviceRemoteLocked', () => {
                    isTerminalLocked.value = true;
                    localStorage.setItem('brasume_terminal_locked', 'true');
                    toast.add({
                        severity: 'warn',
                        summary: 'Acesso Interrompido',
                        detail: 'O gestor bloqueou este terminal remotamente.',
                        life: 5000
                    });
                });
        }
    });

    return {
        isTerminalLocked,
        lockTerminal,
        unlockTerminal
    };
}
