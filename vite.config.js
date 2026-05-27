import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite'; // <-- 1. O MOTOR IMPORTADO AQUI
import path from 'path';

export default defineConfig({
    plugins: [
        tailwindcss(), // <-- 2. O MOTOR LIGADO AQUI!
        laravel({
            input: ['resources/css/app.css',
                    'resources/js/app.js',
                    'resources/css/filament/store/theme.css'
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
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});