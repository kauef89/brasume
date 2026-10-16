import tailwindcss from "@tailwindcss/vite";
// ❌ REMOVIDA A IMPORTAÇÃO DO basicSsl
import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import path from "path";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        // ❌ REMOVIDA A CHAMADA basicSsl()
        tailwindcss(),
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/css/filament/store/theme.css",
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "./resources/js"),
        },
    },
    // 🚀 O SEGREDO NINJA ESTÁ AQUI
    server: {
        host: '127.0.0.1',
        port: 5173,
        cors: true,
        allowedHosts: ['vite.brasume.com.br'], // 🛡️ Permite o tráfego seguro vindo do Nginx
        origin: 'https://vite.brasume.com.br', // 🧭 A bússola para o Laravel acertar o domínio
        hmr: {
            host: 'vite.brasume.com.br',
            protocol: 'wss',
            clientPort: 443
        },
        watch: {
            ignored: ['**/storage/**']
        }
    },
});
