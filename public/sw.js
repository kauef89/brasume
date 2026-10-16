const CACHE_NAME = 'brasume-v1.02.00'; // Incrementado para forçar atualização nos aparelhos

const URLS_TO_CACHE = [
    '/',
    '/manifest.json',
    '/icon-192x192.png'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            // Adiciona os arquivos individualmente para que a falha de um não quebre o SW
            for (const url of URLS_TO_CACHE) {
                try {
                    await cache.add(url);
                } catch (err) {
                    console.warn('[SW] Falha não bloqueante ao cachear:', url);
                }
            }
        })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

self.addEventListener('fetch', (event) => {
    // Só intercepta requisições GET
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    // Ignora Vite, extensões de navegadores e requisições da API
    if (url.origin.includes('localhost') ||
        url.protocol === 'chrome-extension:' ||
        url.pathname.startsWith('/api/')) {
        return;
    }

    // 🚀 Navegação SPA (Network-first com fallback para App Shell)
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const appShell = await caches.match('/');
                return appShell || new Response('Offline', { status: 503 });
            })
        );
        return;
    }

    // 🚀 Recursos Estáticos (Network-first com fallback de cache)
    event.respondWith(
        fetch(event.request).catch(async () => {
            const cachedResponse = await caches.match(event.request);
            return cachedResponse || new Response(null, { status: 404, statusText: 'Not Found' });
        })
    );
});

// 📡 Notificação Push vinda do Servidor
self.addEventListener('push', (event) => {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    const data = event.data ? event.data.json() : {};
    const title = data.title || 'Atualização do Pedido';

    const options = {
        body: data.body || 'Seu pedido teve uma alteração.',
        icon: data.icon || '/icon-192x192.png',
        badge: data.badge || '/icon-192x192.png',
        vibrate: [200, 100, 200, 100, 200, 100, 400],
        data: {
            // Preserva a rota específica do pedido vinda do backend
            url: data.action_url || data.url || '/customer/orders'
        }
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// 👆 Ação ao clicar na notificação
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // 🚀 Resgata a rota exata enviada na notificação ou usa /customer/orders como fallback
    const targetUrl = event.notification.data?.url || '/customer/orders';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Se o app já estiver aberto no navegador, foca e redireciona para a tela do pedido
            for (let i = 0; i < clientList.length; i++) {
                let client = clientList[i];
                if (client.url.includes(self.registration.scope) && 'focus' in client) {
                    return client.focus().then(c => c.navigate(targetUrl));
                }
            }
            // Se estiver fechado, abre a janela direto no destino correto
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
