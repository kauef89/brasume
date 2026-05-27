import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import path from "path";

export default defineConfig({
    plugins: [
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
    // 🚀 ATUALIZE ESTE BLOCO
    server: {
        host: true, // Liga para a rede externa (não precisa mais do --host no terminal)
        allowedHosts: true, // 🚀 OPÇÃO NUCLEAR: Aceita qualquer domínio! Fim do bloqueio.
        cors: true,
    },
});
