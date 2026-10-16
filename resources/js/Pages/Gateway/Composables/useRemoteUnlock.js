import axios from 'axios';
import { onMounted, onUnmounted, ref } from 'vue';

export function useRemoteUnlock() {
    const isWaiting = ref(false);
    const deviceUuid = ref('');

    const generateUUID = () => {
        if (crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    };

    onMounted(() => {
        let uuid = localStorage.getItem('device_uuid');
        if (!uuid) {
            uuid = generateUUID();
            localStorage.setItem('device_uuid', uuid);
        }
        deviceUuid.value = uuid;

        // 🚀 CORREÇÃO: Rota nomeada
        axios.post(route('auth.remote.request'), { device_uuid: uuid })
            .then(() => {
                isWaiting.value = true;
            })
            .catch((e) => console.log('Dispositivo não homologado ainda.', e));

        if (window.Echo) {
            window.Echo.channel(`device.${uuid}`)
                .listen('.device.unlocked', (event) => {

                    // 🚀 CORREÇÃO: Rota nomeada
                    axios.post(route('auth.remote.exchange'), { token: event.token })
                        .then(() => {
                            window.location.href = event.redirectUrl;
                        })
                        .catch((error) => {
                            console.error('Falha ao consumir o Token Remoto', error);
                        });
                });
        }
    });

    onUnmounted(() => {
        if (window.Echo && deviceUuid.value) {
            window.Echo.leaveChannel(`device.${deviceUuid.value}`);
        }
    });

    return {
        isWaiting,
        deviceUuid
    };
}
