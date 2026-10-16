import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

export function useAppLifecycleSync(options = {}) {
    const {
        only = [],              // Propriedades do Inertia a recarregar
        minSleepSeconds = 10,   // Tempo mínimo em segundo plano para sincronizar
        onWake = null,          // Callback executado ao acordar com sessão válida
        onSessionExpired = null // Callback customizado caso a sessão expire
    } = options;

    let lastHiddenTime = 0;
    let isChecking = false;

    const handleSessionExpired = () => {
        if (typeof onSessionExpired === 'function') {
            onSessionExpired();
            return;
        }

        // Força um recarregamento total via HTTP GET.
        // Isso renova os cookies, obtém um novo token CSRF e deixa o middleware
        // de autenticação do Laravel redirecionar o utilizador para a tela de PIN/Login.
        window.location.reload();
    };

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'hidden') {
            lastHiddenTime = Date.now();
            return;
        }

        if (document.visibilityState === 'visible') {
            const timeInBackground = (Date.now() - lastHiddenTime) / 1000;

            // Se ficou em segundo plano pelo tempo mínimo configurado
            if (lastHiddenTime > 0 && timeInBackground >= minSleepSeconds && !isChecking) {
                isChecking = true;

                // 1. Reconecta o WebSocket caso o Pusher/Echo tenha dormido
                if (window.Echo?.connector?.pusher) {
                    const state = window.Echo.connector.pusher.connection.state;
                    if (state !== 'connected') {
                        window.Echo.connector.pusher.connect();
                    }
                }

                // 2. Garante que 'auth' SEMPRE seja revalidado no reload
                const propsToReload = only.length > 0
                    ? Array.from(new Set([...only, 'auth']))
                    : undefined;

                // 3. Recarrega as props e inspeciona a saúde da sessão
                router.reload({
                    only: propsToReload,
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: (page) => {
                        isChecking = false;

                        // Se o backend respondeu 200 mas o utilizador não está mais autenticado
                        const user = page.props?.auth?.user;
                        if (!user) {
                            handleSessionExpired();
                            return;
                        }

                        if (typeof onWake === 'function') {
                            onWake(page);
                        }
                    },
                    onError: () => {
                        isChecking = false;
                    },
                    onFinish: (visit) => {
                        isChecking = false;

                        // 401 = Sessão expirada / Não autorizado
                        // 419 = Token CSRF expirado em segundo plano
                        // 403 = Tenant ou permissão revogada
                        if ([401, 419, 403].includes(visit?.statusCode)) {
                            handleSessionExpired();
                        }
                    }
                });
            }

            lastHiddenTime = 0;
        }
    };

    onMounted(() => {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('pageshow', handleVisibilityChange); // Suporte ao bfcache do Safari iOS
    });

    onUnmounted(() => {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        window.removeEventListener('pageshow', handleVisibilityChange);
    });
}
