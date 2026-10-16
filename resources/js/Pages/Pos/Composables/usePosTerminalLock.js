import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref } from 'vue';

export function usePosTerminalLock() {
    const toast = useToast();
    const isTerminalLocked = ref(localStorage.getItem('brasume_terminal_locked') === 'true');

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

    onMounted(() => {
        const currentDeviceUuid = localStorage.getItem('brasume_device_uuid') || window.Laravel?.device_uuid;

        if (currentDeviceUuid && window.Echo) {
            window.Echo.channel(`device.${currentDeviceUuid}`)
                .listen('.DeviceRemoteUnlocked', () => {
                    if (isTerminalLocked.value) {
                        isTerminalLocked.value = false;
                        localStorage.removeItem('brasume_terminal_locked');
                        toast.add({
                            severity: 'success',
                            summary: 'Terminal Retomado',
                            detail: 'O gestor autorizou o seu retorno à operação!',
                            life: 3000
                        });
                    }
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
        lockTerminal
    };
}
