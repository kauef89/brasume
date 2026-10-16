import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * 🚀 ECHO & REVERB: O SISTEMA NERVOSO EM TEMPO REAL
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// 1. Instanciamos a conexão
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

console.log("📻 Antena de WebSockets inicializada!");

// ==========================================
// 🚀 O DESFIBRILADOR AUTOMÁTICO (Auto-Reconnect)
// ==========================================
const pusherInstance = window.Echo.connector.pusher;

// Monitoriza os estados apenas para ajudar no debug (opcional, pode remover no futuro)
pusherInstance.connection.bind('state_change', function(states) {
    console.log(`📡 WebSocket mudou de [${states.previous}] para [${states.current}]`);
});

/**
 * GATILHO 1: O PC acordou ou a Internet voltou!
 * O navegador dispara o evento 'online' no momento em que a placa de rede ganha IP.
 */
window.addEventListener('online', () => {
    console.log("🌐 Internet restabelecida! Forçando reconexão do WebSocket...");
    if (pusherInstance.connection.state !== 'connected') {
        pusherInstance.connect();
    }
});

/**
 * GATILHO 2: O utilizador voltou para a aba do sistema.
 * O Chrome/Safari modernos "congelam" abas em segundo plano. Quando o utilizador volta
 * a olhar para a aba (visível), nós garantimos que a antena está ligada.
 */
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        if (pusherInstance.connection.state !== 'connected' && pusherInstance.connection.state !== 'connecting') {
            console.log("👀 Aba em foco novamente! Despertando o WebSocket...");
            pusherInstance.connect();
        }
    }
});

// ==========================================
// 📱 REGISTRO DO SERVICE WORKER (PWA)
// ==========================================
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('✅ PWA: Service Worker registrado com sucesso!', registration.scope);
            })
            .catch((error) => {
                console.error('❌ PWA: Falha ao registrar o Service Worker:', error);
            });
    });
}
